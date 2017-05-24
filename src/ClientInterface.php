<?php

namespace Drupal\dcx_integration;

/**
 * Interface ClientInterface.
 *
 * @package Drupal\dcx_integration
 */
interface ClientInterface {

  /**
   *
   */
  public function getObject($id);

  /**
   * Track usage of DC-X Documents on the given URL.
   *
   * The given URL is expanded to the appropriate public absolute URL
   * on DC-X side.
   *
   * @param array $used_entities
   *   List entities keyed by their DC-X document ids.
   * @param string $url
   *   Relative canonical URL where the documents are used.
   * @param bool $published
   *   Status of the given URL.
   * @param string $type
   *   Type of the document. should be image or document.
   *
   * @throws \Exception
   *   If something is going wrong.
   */
  public function trackUsage($used_entities, $url, $published, $type);

  /**
   * Archive an article.
   *
   * @param string $url
   *   The relative canonical path of the article, e.g. node/42.
   * @param array|mixed $data
   *   to archive. Possible keys depend on implementation.
   * @param string $dcx_id
   *   The DC-X document ID of the article. If it's null a new one is created.
   *
   * @return int
   *   The DC-X document ID of the article
   *
   * @throws \Exception
   *   If something is going wrong.
   */
  public function archiveArticle($url, $data, $dcx_id);

  /**
   * Return all DC-X documents which have a pubinfo referencing the given path.
   *
   * Results are filtered by the publication_id configured in the settings
   * 'dcx_integration.jsonclientsettings'
   *
   * @param string $path
   *   Canonical path (e.g. node/23).
   * @param string $type
   *   Type of the document. should be image or document.
   *
   * @return array
   *   Array of array of pubinfo data keyed by DC-X document ID.
   */
  public function pubinfoOnPath($path, $type);

  /**
   * Removes all usage information about the given DC-X ID on the current site.
   *
   * The main reason for calling this would be deleting the entity representing
   * the given ID.
   *
   * @param string $dcx_id
   *   The DC-X document ID.
   */
  public function removeAllUsage($dcx_id);

  /**
   * Retrieve collections of the current user.
   *
   * @return array of arrays keyed by collection id.
   */
  public function getCollections();

  /**
   * Return filename and url of a thumbnail for the given (image) document.
   *
   * @param string $id
   *
   * @return data array containg filename, url and id.
   *
   * @throws DcxClientException
   */
  public function getPreview($id);

  /**
   * Removes usage information about the given DC-X ID on the current site, but
   * only for the given entity.
   *
   * The reason for calling this is deleting a cloned media entity.
   *
   * @param string $dcx_id
   *   The DC-X document ID.
   * @param string $entity_type
   *   Entity type of the entity representing the dcx_id.
   * @param int $entity_id
   *   Entity id of the entity representing the dcx_id.
   */
  public function removeUsageForCertainEntity($dcx_id, $entity_type, $entity_id);

}
