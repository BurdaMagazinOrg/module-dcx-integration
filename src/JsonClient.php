<?php

namespace Drupal\dcx_integration;

use Digicol\DcxSdk\DcxApiClient;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dcx_integration\Asset\Image;
use Drupal\dcx_integration\Asset\Article;

use Drupal\Core\Utility\Error;
use Drupal\Core\Logger\RfcLogLevel;

use Drupal\dcx_integration\Exception\DcxClientException;
use Drupal\dcx_integration\Exception\UnknownDocumentTypeException;

/**
 * Class Client.
 *
 * @package Drupal\dcx_integration
 */
class JsonClient implements ClientInterface {

  use StringTranslationTrait;

  /**
   * Instance of the low level PHP JSON API Client provided by digicol.
   *
   * @var \Digicol\DcxSdk\DcxApiClient
   */
  protected $dcxApiClient;

  /**
   * JSON client settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Publication ID from 'dcx_integration.jsonclientsettings'.
   *
   * @var string
   */
  protected $publicationId;

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountProxyInterface $user, TranslationInterface $string_translation, LoggerChannelFactoryInterface $logger_factory, $override_client_class = NULL) {
    $this->stringTranslation = $string_translation;

    $this->config = $config_factory->get('dcx_integration.jsonclientsettings');
    $this->logger = $logger_factory->get(__CLASS__);

    if (!$override_client_class) {
      $current_user_email = $user->getEmail();
      $site_mail = $config_factory->get("system.site")->get('mail');
      $site_name = $config_factory->get("system.site")->get('name');

      $url = $this->config->get('url');
      $username = $this->config->get('username');
      $password = $this->config->get('password');

      if (empty($current_user_email)) {
        $current_user_email = $username;
      }
      else {
        $current_user_email = "burda_ad/$current_user_email";
      }

      $options = [
        'http_headers' => ['X-DCX-Run-As' => "$current_user_email"],
        'http_useragent' => "DC-X Integration for Drupal (dcx_integration) running on $site_name <$site_mail>",
      ];

      $credentials = [
        'username' => $username,
        'password' => $password,
      ];
      $this->dcxApiClient = new DcxApiClient($url, $credentials, $options);
    }
    else {
      $this->dcxApiClient = $override_client_class;
    }

    $this->publicationId = $this->config->get('publication');
  }

  /**
   * Does a server request on a non existing doc to check the server status.
   */
  public function checkServerStatus() {

    $this->dcxApiClient->guzzleClient->request(
      'GET',
      $this->dcxApiClient->fullUrl('document'),
      $this->dcxApiClient->getRequestOptions([
        'query' => $this->dcxApiClient->mergeQuery('document', []),
      ])
    );
  }

  /**
   * This is public only for debugging purposes.
   *
   * It's not part of the interface, it should be protected.
   * It really shouldn't be called directly.
   */
  public function getJson($id, $params = NULL) {
    $json = NULL;

    if ($params == NULL) {
      $params = [
        's[pubinfos]' => '*',
        // All fields.
        's[fields]' => '*',
        // All properties.
        's[properties]' => '*',
        // All files.
        's[files]' => '*',
        // Attribute _file_absolute_url of all referenced files in the document.
        's[_referenced][dcx:file][s][properties]' => '_file_url_absolute',

        's[_referenced][dcx:pubinfo][s]' => '*',
        's[_rights_effective]' => '*',
        's[_referenced][dcx:rights][s][properties]' => '*',
      ];
    }

    $url = preg_replace('/^dcxapi:/', '', $id);
    $http_status = $this->dcxApiClient->get($url, $params, $json);

    if (200 !== $http_status) {
      $exception = new DcxClientException('get', $http_status, $url, $params, $json);
      $this->watchdogException(__METHOD__, $exception);
      throw $exception;
    }

    return $json;
  }

  /**
   * {@inheritdoc}
   */
  public function getObject($id) {
    $json = $this->getJson($id);

    $type = $this->extractData($json, ['fields', 'Type', 0, '_id']);

    switch ($type) {
      case "dcxapi:tm_topic/documenttype-story":
        $asset = $this->buildArticleAsset($json);
        break;

      case "dcxapi:tm_topic/documenttype-image":
        $asset = $this->buildImageAsset($json);
        break;

      default:
        $exception = new UnknownDocumentTypeException($type, $id);
        $this->watchdogException(__METHOD__, $exception);
        throw $exception;
    }
    return $asset;
  }

  /**
   * Builds an Image object from given json array.
   *
   * @param array $json
   *   Data array.
   *
   * @return \Drupal\dcx_integration\Asset\Image
   *   The Image object.
   *
   * @throws \Exception
   *   If it's not possible to create an asset.
   */
  protected function buildImageAsset(array $json) {
    /*
     * Maps an asset attribute to
     *  - the keys of a nested array, or
     *  - to a callback (class + method) and (optional) arguments for further
     *    processing. The callback method called like like this:
     *    call_user_func($callback, $json, $arguments)
     */
    $attribute_map = [
      'id' => ['_id'],
      'filename' => ['fields', 'Filename', 0, 'value'],
      'title' => ['fields', 'Filename', 0, 'value'],
      'url' => [[$this, 'extractUrl'], 'files', 0, '_id'],
      'source' => [[$this, 'joinValues'], 'fields', 'Creator'],
      'copyright' => ['fields', 'CopyrightNotice', 0, 'value'],
      'status' => [[$this, 'computeStatus']],
    ];

    $data = $this->processAttributeMap($attribute_map, $json);

    try {
      return new Image($data);
    }
    catch (\Exception $e) {
      $this->watchdogException(__METHOD__, $e);
      throw $e;
    }
  }

  /**
   * Builds an Article object from given json array.
   *
   * @param array $json
   *   Data array.
   *
   * @return \Drupal\dcx_integration\Asset\Article
   *   The Article object.
   *
   * @throws \Exception
   *   If it's not possible to create an asset.
   */
  protected function buildArticleAsset(array $json) {

    $attribute_map = [
      'id' => ['_id'],
      'title' => ['fields', 'Headline', 0, 'value'],
      'body' => ['fields', 'body', 0, 'value'],
      'files' => [[$this, 'extractImageIds'], 'fields', 'Image'],
    ];

    $data = $this->processAttributeMap($attribute_map, $json);

    try {
      return new Article($data);
    }
    catch (\Exception $e) {
      $this->watchdogException(__METHOD__, $e);
      throw $e;
    }
  }

  /**
   * Extract data specified in the attribute map on the given source array.
   *
   * An attribute map is an associative array.
   * Keys will be the keys of the resulting data array.
   * Values are arrays of keys in the source or, if the first element is a
   * callable [object, method] pair, arguments for further processing in this
   * very callable.
   *
   * @param array $attribute_map
   *   Array of attributes.
   * @param array $source
   *   Data array.
   *
   * @return array
   *   Array of extracted data.
   */
  protected function processAttributeMap(array $attribute_map, array $source) {
    $data = [];

    foreach ($attribute_map as $target_key => $source_keys) {
      if (is_array($source_keys[0]) && method_exists($source_keys[0][0], $source_keys[0][1])) {
        $callback = array_shift($source_keys);
        $data[$target_key] = call_user_func($callback, $source, $source_keys);
      }
      elseif (is_array($source_keys)) {
        $data[$target_key] = $this->extractData($source, $source_keys);
      }
    }

    return $data;
  }

  /**
   * Descends in the array $json following the path of keys given in keys.
   *
   * Returns whatever it finds there.
   *
   * @param array $json
   *   Data array.
   * @param array $keys
   *   Keys to look for.
   *
   * @return mixed
   *   Value of an asset.
   */
  protected function extractData(array $json, array $keys) {
    foreach ($keys as $key) {
      $json = !empty($json[$key]) ? $json[$key] : '';
    }
    return $json;
  }

  /**
   * Returns the URL for the file reference described by $keys.
   *
   * This function "knows" where to look for the URL of the file in question.
   *
   * @param array $json
   *   Data array.
   * @param array $keys
   *   Keys to look for.
   *
   * @return string
   *   URL referenced by the file_id nested in $keys.
   */
  protected function extractUrl(array $json, array $keys) {
    $file_id = $this->extractData($json, $keys);

    $file_url = $this->extractData($json, [
      '_referenced',
      'dcx:file',
      $file_id,
      'properties',
      '_file_url_absolute',
    ]);
    return $file_url;
  }

  /**
   * Returns the image IDs nested in a story document.
   *
   * This function "knows" where to look for the IDs  in question.
   *
   * @param array $json
   *   Data array.
   * @param array $keys
   *   Keys to look for.
   *
   * @return array
   *   of image IDs
   */
  protected function extractImageIds(array $json, array $keys) {
    $data = $this->extractData($json, $keys);
    if (!$data) {
      return [];
    }

    $images = [];
    foreach ($data as $image_data) {
      $images[] = $this->extractData($image_data, [
        'fields',
        'DocumentRef',
        0,
        '_id',
      ]);
    }
    return $images;
  }

  /**
   * Computes the (published) status of the image.
   *
   * Evaluating the key '_rights_effective'.
   *
   * Searches for a right with the topic_id
   * 'dcxapi:tm_topic/rightsusage-UsagePermittedDigital'.
   *
   * @param array $json
   *   Data array.
   *
   * @return bool
   *   The status of the image. True if a right with topic_id
   *   'dcxapi:tm_topic/rightsusage-Online' is present, false otherwise
   */
  protected function computeStatus(array $json) {
    $rights_ids = $this->extractData($json, [
      '_rights_effective',
      'rightstype-UsagePermittedDigital',
    ]);
    if (is_array($rights_ids)) {
      foreach (current($rights_ids) as $right) {
        $right_id = $right['_id'];
        $dereferenced_right_id = $json['_referenced']['dcx:rights'][$right_id]['properties']['topic_id']['_id'];
        if ('dcxapi:tm_topic/rightsusage-Online' == $dereferenced_right_id) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Returns comma separated string of values of the list referenced by $keys.
   *
   * Use to collect the values of a multi values DC-X field.
   *
   * @param array $json
   *   Data array.
   * @param array $keys
   *   Keys to look for.
   *
   * @return string
   *   The referenced values as comma separated string.
   */
  protected function joinValues(array $json, array $keys) {
    $items = $this->extractData($json, $keys);

    $values = [];
    foreach ($items as $item) {
      $values[] = $item['value'];
    }

    return implode(', ', $values);
  }

  /**
   * {@inheritdoc}
   */
  public function trackUsage(array $used_entities, $path, $published, $type) {

    $dcx_status = $published ? 'pubstatus-published' : 'pubstatus-unpublished';

    $dateTime = new \DateTime();
    $date = $dateTime->format(\DateTime::W3C);

    $dcx_publication = $this->publicationId;

    $known_publications = $this->pubinfoOnPath($path, $type);

    // Delete usage for DC-X Images which are not used anymore.
    foreach ($known_publications as $dcx_id => $pubinfos) {
      // If a DC-X ID with a know usage on this $path is not in the usage list
      // anymore.
      if (!in_array($dcx_id, array_keys($used_entities))) {
        $this->removePubinfos($pubinfos);
      }
    }

    foreach ($used_entities as $id => $entity) {
      $data = [
        "_type" => "dcx:pubinfo",
        'info' => [
          // While json takes care of the encoding this over the wire
          // we need to make sure that the id is actually encoded in the data,
          // because it's supposed to be called by a http_client.
          'callback_url' => '/dcx-notification?id=' . urlencode($id),
          'entity_id' => $entity['id'],
          'entity_type' => $entity['entity_type_id'],
        ],
        "properties" => [
          "doc_id" => [
            "_id" => $id,
            "_type" => "dcx:document",
          ],
          "uri" => $path,
          "date" => $date,
          "status_id" => [
            "_id" => "dcxapi:tm_topic/$dcx_status",
            "_type" => "dcx:tm_topic",
            "value" => "Published",
          ],
          "publication_id" => [
            "_id" => "dcxapi:tm_topic/$dcx_publication",
            "_type" => "dcx:tm_topic",
            "value" => "Bunte",
          ],
          "type_id" => [
            "_id" => "dcxapi:tm_topic/pubtype-$type",
            "_type" => "dcx:tm_topic",
            "value" => ucfirst($type),
          ],
        ],
      ];

      // Pubinfo is either already known or an empty array.
      $pubinfo = isset($known_publications[$id]) ? $known_publications[$id] : [];

      if (count($pubinfo) > 1) {
        throw new \Exception($this->t('For document %id exists more that one pubinfo refering to %url. This should not be the case and cannot be resolved manually. Please fix this in DC-X.',
          ['%id' => $id, '%url' => $path]));
      }
      $response_body = NULL;
      if (0 == count($pubinfo)) {
        $http_status = $this->dcxApiClient->createObject('pubinfo', [], $data, $response_body);
        if (201 !== $http_status) {
          $exception = new DcxClientException('createObject', $http_status, 'pubinfo', [], $data);
          $this->watchdogException(__METHOD__, $exception);
          throw $exception;
        }
      }
      // 1 == count($pubinfo)
      else {
        $pubinfo = current($pubinfo);
        $dcx_api_url = preg_replace('/dcxapi:/', '', $pubinfo['_id']);

        $modcount = $pubinfo['properties']['_modcount'];
        $data['properties']['_modcount'] = $modcount;
        $data['_id'] = $pubinfo['_id'];

        $http_status = $this->dcxApiClient->setObject($dcx_api_url, [], $data, $response_body);
        if (200 !== $http_status) {
          $exception = new DcxClientException('createObject', $http_status, $dcx_api_url, [], $data);
          $this->watchdogException(__METHOD__, $exception);
          throw $exception;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function archiveArticle($url, array $info, $dcx_id) {

    $id = isset($info['id']) ? $info['id'] : '';
    $entity_type_id = isset($info['entity_type_id']) ? $info['entity_type_id'] : '';
    $title = isset($info['title']) ? $info['title'] : '';
    $status = isset($info['status']) ? $info['status'] : FALSE;
    $body = isset($info['body']) ? $info['body'] : '';
    $media = isset($info['media']) ? $info['media'] : [];

    $data = [
      '_type' => 'dcx:document',
      'fields' => [
        'Headline' => [
          0 => [
            'value' => $title,
          ],
        ],
        'body' => [
          0 => [
            '_type' => 'xhtml',
            'value' => $body,
          ],
        ],
        'Type' => [
          [
            "_id" => "dcxapi:tm_topic\/documenttype-story",
            "_type" => "dcx:tm_topic",
            "value" => "Story",
          ],
        ],
        'StoryType' => [
          [
            "_id" => "dcxapi:tm_topic\/storytype-online",
            "_type" => "dcx:tm_topic",
            "value" => "Online",
          ],
        ],
      ],
      'properties' => [
        'pool_id' => [
          '_id' => '/dcx/api/pool/story',
          '_type' => 'dcx:pool',
        ],
      ],
    ];

    // We can't be 100% sure that $media has numeric keys in order.
    $i = 0;

    // Going with the good old counter.
    foreach ($media as $item) {
      $i++;

      if (1 == $i) {
        $tag_group_id = 'primary_image';
      }
      else {
        $tag_group_id = 'image_' . $i . '_' . substr($item['id'], -13);
      }

      $data['fields']['Image'][] = [
        '_type' => 'dcx:taggroup',
        'taggroup_id' => $tag_group_id,
        'fields' => [
          'DocumentRef' => [
            [
              '_id' => $item['id'],
              '_type' => 'dcx:document',
              'file_variant' => 'master',
              'position' => 1,
            ],
          ],
          'ImageCaption' => [
            [
              '_type' => 'xhtml',
              'position' => 1,
              'value' => isset($item['caption']) ? $item['caption'] : '',
            ],
          ],
        ],
      ];
    }

    $response_body = NULL;
    if (NULL != $dcx_id) {
      $json = $this->getJson($dcx_id);
      $modcount = $json['properties']['_modcount'];
      $data['properties']['_modcount'] = $modcount;
      $data['_id'] = '/dcx/api/' . $dcx_id;
      $dcx_api_url = $dcx_id;
      $http_status = $this->dcxApiClient->setObject($dcx_api_url, [], $data, $response_body);
    }
    else {
      $dcx_api_url = 'document';
      $http_status = $this->dcxApiClient->createObject($dcx_api_url, [], $data, $response_body);
    }
    $error = FALSE;

    if (!$response_body) {
      $message = $this->t('The operation yielded no result.');
      $error = TRUE;
    }

    if (!$error && !isset($response_body['_type'])) {
      $message = $this->t('The result operation has no type.');
      $error = TRUE;
    }

    if (!$error && $response_body['_type'] !== 'dcx:success') {
      $message = $response_body['_type'];
      if (isset($response_body['title'])) {
        $message .= ":: " . $response_body['title'];
      }
      $error = TRUE;
    }

    if (!$error && !isset($response_body['location'])) {
      $message = $this->t('The operation was successful, but key location was not found.');
      $error = TRUE;
    }

    if (!$error && preg_match('|/dcx/api/(document/doc.*)|', $response_body['location'], $matches)) {
      $dcx_id = $matches[1];

      $url = parse_url($url)['path'];

      $this->trackUsage(["dcxapi:$dcx_id" => ['id' => $id, 'entity_type_id' => $entity_type_id]], ltrim($url, '/'), $status, 'article');
    }
    else {
      if (!$error) {
        $message = $this->t('The operation was successful, but the location was not parseable.');
        $error = TRUE;
      }
    }

    if ($error) {
      $exception = new DcxClientException('createObject|setObject', $http_status, $dcx_api_url, [], $data, sprintf('Unable to archive: %s', $message));
      $this->watchdogException(__METHOD__, $exception);
      throw $exception;
    }

    return $dcx_id;
  }

  /**
   * {@inheritdoc}
   */
  public function pubinfoOnPath($path, $type) {
    $json = NULL;
    // @TODO would be nice to filter by publication_id via params to spare us
    // from iterating over bogus results.
    $params = [
      'q[uri]' => $path,
      's[properties]' => '*',
      'q[_limit]' => '*',
      'q[type_id]' => "pubtype-$type",
    ];

    $http_status = $this->dcxApiClient->get('pubinfo', $params, $json);
    if (200 !== $http_status) {
      $exception = new DcxClientException('get', $http_status, 'pubinfo', $params, $json);
      $this->watchdogException(__METHOD__, $exception);
      throw $exception;
    }

    $pubinfo = [];
    foreach ($json['entries'] as $entry) {
      // Ignore entry, if the publication id of this entry does not match ours.
      if ("dcxapi:tm_topic/" . $this->publicationId !== $entry['properties']['publication_id']['_id']) {
        continue;
      }
      $doc_id = $entry['properties']['doc_id']['_id'];
      $id = $entry['_id'];
      $pubinfo[$doc_id][$id] = $entry;
    }

    return $pubinfo;
  }

  /**
   * Deletes the given pubinfo entries.
   *
   * This just deletes. It does not make any sanity checks at all.
   *
   * @param array $pubinfos
   *   List of pubinfo entries as returned by DC-X.
   *
   * @throws \Exception
   */
  protected function removePubinfos(array $pubinfos) {
    $response_body = 'we know we wont evaluate this ;)';
    foreach ($pubinfos as $data) {
      $dcx_api_url = $data['_id_url'];
      $http_status = $this->dcxApiClient->deleteObject($dcx_api_url, [], $response_body);
      if (204 != $http_status) {
        $exception = new DcxClientException('deleteObject', $http_status, $dcx_api_url);
        $this->watchdogException(__METHOD__, $exception);
        throw $exception;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeUsageForCertainEntity($dcx_id, $entity_type, $entity_id) {
    $pubinfos = $this->getAllUsage($dcx_id, $entity_type, $entity_id);
    $this->removePubinfos($pubinfos);

    return array_keys($pubinfos);
  }

  /**
   * {@inheritdoc}
   */
  public function removeAllUsage($dcx_id) {
    $pubinfos = $this->getAllUsage($dcx_id);
    $this->removePubinfos($pubinfos);

    return array_keys($pubinfos);
  }

  /**
   * Retrieve all usage information about the given DC-X ID on the current site.
   *
   * May be filtered by a certain entity (say media:image) instance.
   *
   * @param string $dcx_id
   *   The DC-X document ID.
   * @param string $entity_type
   *   Entity type of the entity representing the dcx_id.
   * @param int $entity_id
   *   Entity id of the entity representing the dcx_id.
   *
   * @return array
   *   Usages of an document.
   */
  protected function getAllUsage($dcx_id, $entity_type = NULL, $entity_id = NULL) {
    $document = $this->getJson($dcx_id);
    $pubinfos = $document['_referenced']['dcx:pubinfo'];

    $selected_pubinfos = [];
    foreach ($pubinfos as $pubinfo) {
      if ("dcxapi:tm_topic/" . $this->publicationId === $pubinfo['properties']['publication_id']['_id']) {
        // If either type or id is not set, find all.
        if (!$entity_type || !$entity_id) {
          $selected_pubinfos[$pubinfo['properties']['uri']] = $pubinfo;
        }
        // If pubinfo contains type and id both equal to the given one, find it.
        elseif (isset($pubinfo['info']['entity_type'])
          && isset($pubinfo['info']['entity_id'])
          && $pubinfo['info']['entity_type'] == $entity_type
          && $pubinfo['info']['entity_id'] == $entity_id
        ) {
          $selected_pubinfos[$pubinfo['properties']['uri']] = $pubinfo;
        }
      }
    }

    return $selected_pubinfos;
  }

  /**
   * Replacement for \watchdog_exception.
   *
   * Global watchdog_exception is not unit testable. :( This method is.
   */
  protected function watchdogException($type, \Exception $exception, $message = NULL, $variables = [], $severity = RfcLogLevel::ERROR, $link = NULL) {
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
      $variables['link'] = $link;
    }

    $variables += Error::decodeException($exception);

    $this->logger->log($severity, $message, $variables);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollections() {
    $params = [
      'q' => [
        '_mode' => 'my_usertags',
        'type_id' => 'usertagtype-default',
        'parent_id' => NULL,
        '_sort' => 'UTAG_VALUE',
      ],
      's' => [
        'properties' => '_label',
        'children' => '*',
      ],
    ];

    $this->dcxApiClient->get('usertag', $params, $usertags);

    $collections = [];
    foreach ($usertags['entries'] as $usertag) {
      $utag_id = preg_replace('#dcxapi:usertag/#', '', $usertag['_id']);
      $collections[$usertag['_id']] = [
        'label' => $usertag['properties']['_label'],
        'id' => $utag_id,
        'parent' => NULL,
      ];

      if (isset($usertag['children'])) {
        $collections[$usertag['_id']]['children'] = array_map(function ($c) {
          return $c['_id'];
        }, $usertag['children']);
      }
      else {
        $collections[$usertag['_id']]['children'] = [];
      }
    }

    foreach ($collections as $collection) {
      foreach ($collection['children'] as $child_id) {
        $collections[$child_id]['parent'] = $child_id;
      }
    }

    return $collections;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocsOfCollection($utag_id) {
    $doctoutag_params = [
      'q[utag_id]' => $utag_id,
      's[properties]' => '*',
      's[_referenced][dcx:document][s][_rights_effective]' => '*',
      's[_referenced][dcx:document][s][files]' => '*',
      's[_referenced][dcx:document][s][_referenced][dcx:rights][s][properties]' => '*',
    ];

    $this->dcxApiClient->get('doctoutag', $doctoutag_params, $docs);

    $documents = [];
    foreach ($docs['entries'] as $doc) {

      $document = reset($this->extractData($doc, ['_referenced', 'dcx:document']));
      $rights = $this->extractData($doc, ['_referenced', 'dcx:rights']);

      $document['_referenced']['dcx:rights'] = $rights;

      if ($this->computeStatus($document)) {
        $documents[] = $doc['properties']['doc_id']['_id'];
      }
    }

    return $documents;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreview($id) {
    $json = NULL;

    $params = [
      's[fields]' => 'Filename',
      // All files.
      's[files]' => '*',
      // Attribute _file_absolute_url of all referenced files in the document.
      's[_referenced][dcx:file][s][properties]' => '_file_url_absolute',
    ];

    $url = preg_replace('/^dcxapi:/', '', $id);
    $http_status = $this->dcxApiClient->get($url, $params, $json);

    if (200 !== $http_status) {
      $exception = new DcxClientException('get', $http_status, $url, $params, $json);
      $this->watchdogException(__METHOD__, $exception);
      throw $exception;
    }

    $variant_types = $this->processAttributeMap([
      'variants' => [
        '_files_index',
        'variant_type',
        'master',
      ],
    ], $json);

    $thumb_id = $variant_types['variants']['thumbnail'];

    $attribute_map = [
      'id' => ['_id'],
      'filename' => ['fields', 'Filename', 0, 'value'],
      'url' => [[$this, 'extractUrl'], 'files', $thumb_id, '_id'],
    ];

    $data = $this->processAttributeMap($attribute_map, $json);

    return $data;
  }

}
