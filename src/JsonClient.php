<?php

namespace Drupal\dcx_integration;

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
   * See file api_client/dcx_api_client.class.php.
   *
   * @var \DCX_Api_Client
   */
  protected $api_client;

  /**
   * JSON client settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;
  /**
   * Constructor.
   */

  /**
   * Publication ID from 'dcx_integration.jsonclientsettings'.
   *
   * @var string
   */
  protected $publication_id;

  /**
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

      $url = $this->config->get('url');
      $username = $this->config->get('username');
      $password = $this->config->get('password');

      if (empty($current_user_email)) {
        $current_user_email = $username;
      }
      else {
        $current_user_email = "burda_ad/$current_user_email";
      }

      global $base_url;
      $options = [
        'http_headers' => ['X-DCX-Run-As' => "$current_user_email"],
        'http_useragent' => "DC-X Integration for Drupal (dcx_integration) running on $base_url <$site_mail>",
      ];

      require drupal_get_path('module', 'dcx_integration') . '/api_client/dcx_api_client.class.php';
      $this->api_client = new \DCX_Api_Client($url, $username, $password, $options);
    }
    else {
      $this->api_client = $override_client_class;
    }

    $this->publication_id = $this->config->get('publication');
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
    $http_status = $this->api_client->getObject($url, $params, $json);

    if (200 !== $http_status) {
      $exception = new DcxClientException('getObject', $http_status, $url, $params, $json);
      $this->watchdog_exception(__METHOD__, $exception);
      throw $exception;
    }

    return $json;
  }

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
   * @return Drupal\dcx_integration\Asset\BaseAsset
   *   An instance of BaseAsset depending on the retrieved data.
   *
   * @throws \Exception
   *   Throws exceptions if anything fails.
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
        $this->watchdog_exception(__METHOD__, $exception);
        throw $exception;
    }
    return $asset;
  }

  /**
   * Builds an Image object from given json array.
   *
   * @return Drupal\dcx_integration\Asset\Image
   *   The Image object.
   */
  protected function buildImageAsset($json) {
    $data = [];

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
      'kill_date' => [[$this, 'computeExpire']],
    ];

    $data = $this->processAttributeMap($attribute_map, $json);

    try {
      $asset = new Image($data);
    } catch(\Exception $e) {
      $this->watchdog_exception(__METHOD__, $e);
      throw $e;
    }
    return $asset;
  }

  /**
   * Builds an Article object from given json array.
   *
   * @return Drupal\dcx_integration\Asset\Article
   *   The Article object.
   */
  protected function buildArticleAsset($json) {
    $data = [];

    $attribute_map = [
      'id' => ['_id'],
      'title' => ['fields', 'Headline', 0, 'value'],
      'body' => ['fields', 'body', 0, 'value'],
      'files' => [[$this, 'extractImageIds'], 'fields', 'Image'],
    ];

    $data = $this->processAttributeMap($attribute_map, $json);

    try {
      $asset = new Article($data);
    } catch(\Exception $e) {
      $this->watchdog_exception(__METHOD__, $e);
      throw $e;
    }
    return $asset;

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
   * @return array of extracted data
   */
  protected function processAttributeMap($attribute_map, $source) {
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
   * Descends in the nested array $json following the path of keys given in keys.
   *
   * Returns whatever it finds there.
   *
   * @param array $keys
   * @param array $json
   *
   * @return mixed $value
   */
  protected function extractData($json, $keys) {
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
   * @param array $keys
   * @param array $json
   *
   * @return string
   *   URL referenced by the file_id nested in $keys.
   */
  protected function extractUrl($json, $keys) {
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
   * @param array $keys
   * @param array $json
   *
   * @return array of image IDs
   */
  protected function extractImageIds($json, $keys) {
    $data = $this->extractData($json, $keys);
    if (!$data) return;

    foreach ($data as $image_data) {
      $images[] = $this->extractData($image_data, ['fields', 'DocumentRef', 0, '_id']);
    }
    return $images;
  }

  /**
   * Computes the (published) status of the image, evaluating the key
   * '_rights_effective'.
   *
   * Searches for a right with the topic_id 'dcxapi:tm_topic/rightsusage-Online'.
   *
   * @param array $json
   *
   * @return bool
   *   The status of the image. True if a right with topic_id
   *   'dcxapi:tm_topic/rightsusage-Online' is present, false otherwise
   */
  protected function computeStatus($json) {
    $rights_ids = $this->extractData($json, [
      '_rights_effective',
      'rightstype-UsagePermitted',
    ]);
    foreach (current($rights_ids) as $right) {
      $right_id = $right['_id'];
      $dereferenced_right_id = $json['_referenced']['dcx:rights'][$right_id]['properties']['topic_id']['_id'];
      if ('dcxapi:tm_topic/rightsusage-Online' == $dereferenced_right_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Computes the expired of the image, evaluating the key
   * '_rights_effective'.
   *
   * Searches for a right with the topic_id 'dcxapi:tm_topic/rightsusage-Online'.
   *
   * @param array $json
   *
   * @return string
   *   date string of expired date
   */
  protected function computeExpire($json) {
    $rights_ids = $this->extractData($json, [
      '_rights_effective',
      'rightstype-UsagePermitted',
    ]);
    foreach (current($rights_ids) as $right) {
      $right_id = $right['_id'];
      $dereferenced_right_id = $json['_referenced']['dcx:rights'][$right_id]['properties']['topic_id']['_id'];
      if ('dcxapi:tm_topic/rightsusage-Online' == $dereferenced_right_id) {
        if (isset($right['from_date']) && isset($right['to_date']) && empty($right['to_date'])) {
          $date = new \DateTime($right['from_date']);
          return $date->format('Y-m-d');
        }
        if (isset($right['to_date'])) {
          $date = new \DateTime($right['to_date']);
          return $date->format('Y-m-d');
        }
        return NULL;
      }
    }
    return FALSE;
  }

  /**
   * Returns a comma separated string of the values of the list referenced by
   * $keys. Use to collect the values of a multi values DC-X field.
   *
   * @param array $keys
   * @param array $json
   *
   * @return string
   *   The referenced values as comma separated string.
   */
  protected function joinValues($json, $keys) {
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
  public function trackUsage($used_entities, $path, $published, $type) {

    $dcx_status = $published ? 'pubstatus-published' : 'pubstatus-unpublished';

    $dateTime = new \DateTime();
    $date = $dateTime->format(\DateTime::W3C);

    $dcx_publication = $this->publication_id;

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
          'entity_id' => $entity->id(),
          'entity_type' => $entity->getEntityTypeId(),
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
        $http_status = $this->api_client->createObject('pubinfo', [], $data, $response_body);
        if (201 !== $http_status) {
          $exception = new DcxClientException('createObject', $http_status, 'pubinfo', [], $data);
          $this->watchdog_exception(__METHOD__, $exception);
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

        $http_status = $this->api_client->setObject($dcx_api_url, [], $data, $response_body);
        if (200 !== $http_status) {
          $exception = new DcxClientException('createObject', $http_status, $dcx_api_url, [], $data);
          $this->watchdog_exception(__METHOD__, $exception);
          throw $exception;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function archiveArticle($url, $info, $dcx_id) {

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
          '_id' => '/dcx/api/pool/native',
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
      $http_status = $this->api_client->setObject($dcx_api_url, [], $data, $response_body);
    }
    else {
      $dcx_api_url = 'document';
      $http_status = $this->api_client->createObject($dcx_api_url, [], $data, $response_body);
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

      $this->trackUsage(["dcxapi:$dcx_id"], ltrim($url, '/'), $status, 'article');
    }
    else {
      if (!$error) {
        $message = $this->t('The operation was successful, but the location was not parseable.');
        $error = TRUE;
      }
    }

    if ($error) {
      $exception = new DcxClientException('createObject|setObject', $http_status, $dcx_api_url, [], $data, sprintf('Unable to archive: %s', $message));
      $this->watchdog_exception(__METHOD__, $exception);
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

    $http_status = $this->api_client->getObject('pubinfo', $params, $json);
    if (200 !== $http_status) {
      $exception = new DcxClientException('getObject', $http_status, 'pubinfo', $params, $json);
      $this->watchdog_exception(__METHOD__, $exception);
      throw $exception;
    }

    $pubinfo = [];
    foreach ($json['entries'] as $entry) {
      // Ignore entry, if the publication id of this entry does not match ours.
      if ("dcxapi:tm_topic/" . $this->publication_id !== $entry['properties']['publication_id']['_id']) {
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
  protected function removePubinfos($pubinfos) {
    $response_body = 'we know we wont evaluate this ;)';
    foreach ($pubinfos as $data) {
      $dcx_api_url = $data['_id_url'];
      $http_status = $this->api_client->deleteObject($dcx_api_url, [], $response_body);
      if (204 != $http_status) {
        $exception = new DcxClientException('deleteObject', $http_status, $dcx_api_url);
        $this->watchdog_exception(__METHOD__, $exception);
        throw $exception;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeAllUsage($dcx_id) {
    $pubinfos = getAllUsage($dcx_id);
    $this->removePubinfos($pubinfos);

    return array_keys($pubinfos);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllUsage($dcx_id, $entity_type =  NULL, $entity_id = NULL) {
    $document = $this->getJson($dcx_id);
    $pubinfos = $document['_referenced']['dcx:pubinfo'];

    $selected_pubinfos = [];
    foreach ($pubinfos as $key => $pubinfo) {
      if ("dcxapi:tm_topic/" . $this->publication_id === $pubinfo['properties']['publication_id']['_id']) {
        // If either type or id is not set, find all.
        if (!$entity_type || !$entity_id) {
          $selected_pubinfos[$pubinfo['properties']['uri']] = $pubinfo;
        }
        // If pubinfo contains type and id both equal to the given one, find it.
        elseif (isset($pubinfo['info']['entity_type'])
            && isset($pubinfo['info']['entity_id'])
            && $pubinfo['info']['entity_type'] == $entity_type
            && $pubinfo['info']['entity_id'] == $entity_id) {
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
  protected function watchdog_exception($type, \Exception $exception, $message = NULL, $variables = array(), $severity = RfcLogLevel::ERROR, $link = NULL) {
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
      $variables['link'] = $link;
    }

    $variables += Error::decodeException($exception);

    $this->logger->log($severity, $message, $variables);
  }
}
