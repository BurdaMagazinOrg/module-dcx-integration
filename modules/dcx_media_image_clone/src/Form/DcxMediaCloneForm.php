<?php


namespace Drupal\dcx_media_image_clone\Form;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the media edit forms.
 */
class DcxMediaCloneForm extends FormBase {

  /**
   * The entity manager.
   *
   * @var EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dcx_media_image_clone_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $media = NULL) {
    // Meh.. this should make use of Paramconverter or EntityBaseForm, but I
    // cannot figure out how either of them works atm :(
    $media = $this->entityManager->getStorage('media')->load($media);
    $form_state->set('media', $media);

    $form['notice']['#markup'] = $this->t('<p>Do you want to clone this media entity?</p>');

    $form['clone'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clone'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $media = $form_state->get('media');
    $parent_id = $media->id();
    if (!empty($media->field_parent_media->target_id)) {
      $parent_id = $media->field_parent_media->target_id;
    }
    $clone = $media->createDuplicate();
    $clone->set('field_parent_media', $parent_id);
    $clone->save();

    $label = $media->label();
    $url = Url::fromRoute('entity.media.canonical', ['media' => $media->id()]);
    drupal_set_message($this->t('Media @label was cloned.', ['@label' => Link::fromTextAndUrl($label, $url)->toString()]));
    $form_state->setRedirect('entity.media.edit_form', ['media' => $clone->id()]);
  }
}
