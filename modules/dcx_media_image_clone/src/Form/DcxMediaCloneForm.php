<?php

namespace Drupal\dcx_media_image_clone\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the media edit forms.
 */
class DcxMediaCloneForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
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
    // cannot figure out how either of them works atm :(.
    $media = $this->entityTypeManager->getStorage('media')->load($media);
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
    $this->messenger->addStatus($this->t('Media @label was cloned.', ['@label' => Link::fromTextAndUrl($label, $url)->toString()]));
    $form_state->setRedirect('entity.media.edit_form', ['media' => $clone->id()]);
  }

}
