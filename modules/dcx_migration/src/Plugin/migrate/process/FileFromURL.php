<?php

namespace Drupal\dcx_migration\Plugin\migrate\process;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Entity\File;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This plugins takes a remote file URL an imports it as managed file to Drupal.
 *
 * The destination folder the file is imported to depends on the (file) field
 * the file is imported to. Thus it needs "entity_type", "bundle" and "field"
 * as arguments to retrieve this information from EntityFieldManager.
 *
 * @MigrateProcessPlugin(
 *   id = "file_from_url"
 * )
 */
class FileFromURL extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // @TODO All this information could probably be retrieved through the
    // migration configuration. This way makes the Dev replicate info which is
    // already given :/
    $target_entity = $this->configuration['entity_type'];
    $target_bundle = $this->configuration['bundle'];
    $target_field = $this->configuration['field'];

    // We need to prevent already existing files from being overridden here, if
    // this is an update of an existing row. We know this because this
    // information might have been added to the row in
    // \Drupal\dcx_migration\Plugin\migrate\source\DcxSource:prepareRow().
    // So if we see that this is an update we just return the fid of the file
    // already present in $destination_property of the already migrated
    // entity.
    if (isset($row->isUpdate) && $row->isUpdate) {
      $entity_id = $row->destid1;
      $entity = $this->entityTypeManager->getStorage($target_entity)->load($entity_id);
      return $entity->$destination_property->target_id;
    }

    $field_defs = $this->entityFieldManager->getFieldDefinitions($target_entity, $target_bundle);
    $field_image_def = $field_defs[$target_field];

    // Construct the file destination.
    // Basically reimplementing
    // Drupal\file\Plugin\Field\FieldType\FileItem::doGetUploadLocation()
    $file_directory = trim($field_image_def->getSetting('file_directory'), '/');
    $file_directory = PlainTextOutput::renderFromHtml(\Drupal::token()->replace($file_directory));
    $destination_uri = $field_image_def->getSetting('uri_scheme') . '://' . $file_directory;

    // Make sure the destination URI exists ...
    if (!is_dir($destination_uri)) {
      mkdir($destination_uri);
    }

    // Obtain filename.
    $name_attribute = $this->configuration['filename'];
    $file_name = $row->getSourceProperty($name_attribute);

    // Obtain source url.
    $url_attribute = $this->configuration['url'];
    $file_url = $row->getSourceProperty($url_attribute);

    // Fetch source file to tempile.
    $file_data = file_get_contents($file_url);
    $tmp_name = tempnam('temp://', 'dcx-');
    file_put_contents($tmp_name, $file_data);

    // Copy tempfile to destination, make sure to use canonical file uri.
    $uri = file_unmanaged_copy(
      $tmp_name,
      file_stream_wrapper_uri_normalize($destination_uri . DIRECTORY_SEPARATOR . $file_name),
      FILE_EXISTS_RENAME
    );

    // Remove.
    unlink($tmp_name);

    $file = File::create([
      'uri' => $uri,
      'filename' => $file_name,
    ]);

    $file->save();

    return $file->id();
  }

}
