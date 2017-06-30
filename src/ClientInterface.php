<?php

namespace Drupal\dcx_integration;

/**
 * Interface ClientInterface.
 *
 * @package Drupal\dcx_integration
 */
interface ClientInterface {

  /**
   * Retrieve a DC-X object with the given id.
   *
   * Emits an HTTP request to the DC-X server and evaluates the response.
   * Depending on the document "Type" (an attribute stored within the fields,
   * not to be confused with the attribute "type") it returns subclasses of
   * BaseAsset which encapsulate a flat array representation of the data
   * retrieved.
   *
   * @param string $id
   *   A dcx object identifier. Something like "dcxapi:document/xyz".
   *
   * @return \Drupal\dcx_integration\Asset\BaseAsset
   *   An instance of BaseAsset depending on the retrieved data.
   *
   * @throws \Exception
   *   Throws exceptions if anything fails.
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
   * @param string $path
   *   Relative canonical URL where the documents are used.
   * @param bool $published
   *   Status of the given URL.
   * @param string $type
   *   Type of the document. should be image or document.
   *
   * @throws \Exception
   *   If something is going wrong.
   */
  public function trackUsage(array $used_entities, $path, $published, $type);

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
  public function archiveArticle($url, array $data, $dcx_id);

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
   * @return array
   *   Of arrays keyed by collection id.
   */
  public function getCollections();

  /**
   * Return filename and url of a thumbnail for the given (image) document.
   *
   * @param string $id
   *   DC-X document ID.
   *
   * @return array
   *   Data containg filename, url and id.
   *
   * @throws \Drupal\dcx_integration\Exception\DcxClientException
   */
  public function getPreview($id);

  /**
   * Removes usage information about the given DC-X ID on the current site.
   *
   * But only for the given entity.
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
