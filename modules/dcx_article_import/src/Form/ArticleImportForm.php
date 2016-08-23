<?php

/**
 * @file
 * Contains \Drupal\dcx_article_import\Form\ArticleImportForm.
 */

namespace Drupal\dcx_article_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\dcx_integration\ClientInterface;
use Drupal\dcx_integration\Exception\MandatoryAttributeException;
use Drupal\dcx_migration\DcxImportServiceInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\user\PrivateTempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ArticleImportForm.
 *
 * @package Drupal\dcx_article_import\Form
 */
class ArticleImportForm extends FormBase {

  /**
   * DC-X Client.
   *
   * @var Drupal\dcx_integration\ClientInterface
   */
  protected $dcx_integration_client;

  /**
   * DC-X Import service.
   *
   * @var Drupal\dcx_migration\DcxImportServiceInterface
   */
  protected $dcx_import_service;

  /**
   * Temporary storage.
   *
   * @var Drupal\user\PrivateTempStore
   */
  protected $store;

  /**
   * The current user account.
   * @var Drupal\Core\Session\AccountProxyInterface
   */
  protected $user_account;

  public function __construct(ClientInterface $dcx_integration_client, DcxImportServiceInterface $dcx_import_service, PrivateTempStoreFactory $temp_store_factory, AccountProxyInterface $user_account) {
    $this->dcx_integration_client = $dcx_integration_client;
    $this->dcx_import_service = $dcx_import_service;
    $this->store = $temp_store_factory->get(__CLASS__);
    $this->user_account = $user_account;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcx_integration.client'),
      $container->get('dcx_migration.import'),
      $container->get('user.private_tempstore'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'article_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $asset = $this->store->get('asset');

    if (!$asset) { // Step 1
      $form['dcx_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('DC-X ID'),
        '#description' => 'Please give a DC-X story document id. Something like "document/doc6p9gtwruht4gze9boxi".',
        '#maxlength' => 64,
        '#size' => 64,
        '#required' => TRUE,
        '#attached' => [
          'library' => ['dcx_article_import/dropzone'],
        ],
      ];
      $form['actions'] = [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Import'),
          '#button_type' => 'primary',
        ],
      ];
    } else { // Step 2
      $data = $asset->data();

      $form['title'] = [
        '#markup' => "<h2>" . $data['title'] . "</h2>",
      ];
      $form['body'] = array(
        '#type' => 'text_format',
        '#default_value' => isset($data['body']) ? $data['body'] : '',
        '#format' => 'full_html',
        '#description' => $this->t('Please insert horizontal rule tags to split the text body into separate paragraphs.'),
      );
      $form['actions'] = [
        '#type' => 'actions',
        'save' => [
          '#type' => 'submit',
          '#submit' => ['::saveArticle'],
          '#value' => $this->t('Save article'),
          '#button_type' => 'primary',
        ],
        'clear' => [
          '#type' => 'submit',
          '#submit' => ['::clearTempStore'],
          '#value' => $this->t('Clear'),
          '#button_type' => 'danger',
        ],
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // $dcx_id might be NULL in step 2
    if ($dcx_id = $form_state->getValue('dcx_id')) {
      if (!preg_match('#^document/doc\w+$#', $dcx_id)) {
        $form_state->setError($form, $this->t('Please provide a valid DC-X ID.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $dcx_id = $form_state->getValue('dcx_id');

    try {
      $asset = $this->dcx_integration_client->getObject('dcxapi:' . $dcx_id);
      $this->store->set('asset', $asset);
    }
    catch (MandatoryAttributeException $e) {
      $message = $this->t('DC-X Story %id is missing the mandatory attribute %attr. Please fix this in DC-X.', ['%id' => $dcx_id, '%attr' => $e->attribute]);
      drupal_set_message($message);
      return;
    }
    catch (\Exception $e) {
      $this->store->delete('asset');
      drupal_set_message($e->getMessage());
      return;
    }

    if ($files = $asset->data()['files']) {
      $this->dcx_import_service->import($files);
    }
  }

  public function clearTempStore(array &$form, FormStateInterface $form_state) {
    $this->store->set('asset', NULL);
  }

  public function saveArticle(array &$form, FormStateInterface $form_state) {
    $uid = $this->user_account->id();
    $data = $this->store->get('asset')->data();

    $title = $data['title'];

    $node = Node::create([
        'type' => 'article',
        'title' => $title,
        'uid' => $uid,
        'status' => 0,
    ]);

    $body = $form_state->getValue('body')['value'];
    foreach (preg_split('#<hr />#', $body) as $body_part) {
      $body_paragraph = Paragraph::create([
        'type' => 'text',
        'uid' => $uid,
        'status' => 1,
        'field_text' => [
          ['value' => $body_part, 'format' => 'basic_html'],
        ]
      ]);
      $body_paragraph->save();
      $node->field_paragraphs->appendItem($body_paragraph);
    }

    $files = $data['files'];
    $media_ids = $this->dcx_import_service->getEntityIds($files);
    foreach ($media_ids as $media_id) {
      $media_paragraph = Paragraph::create([
        'type' => 'media',
        'uid' => $uid,
        'status' => 1,
        'field_media' => [
          ['target_id' => $media_id]
        ],
      ]);
      $media_paragraph->save();
      $node->field_paragraphs->appendItem($media_paragraph);
    }

    $node->save();

    $this->store->delete('asset');
    $form_state->setRedirectUrl(Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]));
  }
}
