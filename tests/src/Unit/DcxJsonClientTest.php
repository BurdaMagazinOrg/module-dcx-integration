<?php

namespace Drupal\Tests\dcx_integration\Unit;

use Drupal\dcx_integration\JsonClient;
use Drupal\Tests\UnitTestCase;

/**
 * @group dcx
 * @codingStandardsIgnoreFile
 */
class DcxJsonClientTest extends UnitTestCase {

  /**
   * @var \Drupal\dcx_integration\JsonClient
   */
  protected $client;

  protected $api_client;

  /**
   *
   */
  public function setUp() {
    $jsonclientsettings = ['publication' => 'dummy_publication'];
    $config_factory = $this->getConfigFactoryStub(['dcx_integration.jsonclientsettings' => $jsonclientsettings]);
    $user = $this->getMock('\Drupal\Core\Session\AccountProxyInterface');

    $logger = $this->getMock('\Psr\Log\LoggerInterface');
    $loggerFactory = $this->getMock('\Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $loggerFactory->expects($this->any())
      ->method('get')
      ->will($this->returnValue($logger));

    $stringTranslation = $this->getStringTranslationStub();
    $this->api_client = new DummyDcxApiClient();
    $this->client = new JsonClient($config_factory, $user, $stringTranslation, $loggerFactory, $this->api_client);
  }

  /**
   *
   */
  public function testGetJson_noparams() {
    $this->client->getJson('dcxapi:id');
    list($url, $params,) = $this->api_client->args;
    $this->assertEquals($this->api_client->method, 'get', 'getObject method of API client is called.');
    $this->assertEquals($url, 'id', 'Client disposes "dcxapi:" part of the id.');
    $this->assertNotEmpty($params, 'If no params are given, default params are passed to the API client.');
  }

  /**
   *
   */
  public function testGetJson_custom_params() {
    $this->client->getJson('dcxapi:id', ['params']);
    list(, $params,) = $this->api_client->args;
    $this->assertArrayEquals(['params'], $params, 'If params are given, they are passed to the API client');
  }

  /**
   *
   */
  public function testGetJson_exception_on_non_200_response() {
    $this->api_client->expected_return_value = 23;
    $this->setExpectedException('Drupal\dcx_integration\Exception\DcxClientException', 'Error performing get on url "id". Status code was 23.');
    $this->client->getJson('dcxapi:id');
  }

  /**
   *
   */
  public function testArchiveArticle_emptyResponse() {
    // Expect empty response -> Exception.
    $this->api_client->expected_response_body = NULL;
    $this->setExpectedException('Drupal\dcx_integration\Exception\DcxClientException', 'The operation yielded no result');
    $this->client->archiveArticle('node/1', [], NULL);

  }

  /**
   *
   */
  public function testArchiveArticle_invalidResponse() {
    // Expect invalid response -> Exception.
    $this->api_client->expected_response_body = 'invalid';
    $this->setExpectedException('Drupal\dcx_integration\Exception\DcxClientException', 'Unable to archive: The result operation has no type');
    $this->client->archiveArticle('node/1', [], NULL);

  }

  /**
   *
   */
  public function testArchiveArticle_noSuccess() {
    // Expect response without _type == success -> Exception.
    $this->api_client->expected_response_body = ['_type' => 'no success'];
    $this->setExpectedException('Drupal\dcx_integration\Exception\DcxClientException', 'Error performing createObject|setObject on url "document". Status code was 200.');
    $this->client->archiveArticle('node/1', [], NULL);
  }

  /**
   *
   */
  public function testArchiveArticle_noLocation() {
    // Expect response without key location -> Exception.
    $this->api_client->expected_response_body = ['_type' => 'dcx:success'];
    $this->setExpectedException('Drupal\dcx_integration\Exception\DcxClientException', "The operation was successful, but key location was not found");
    $this->client->archiveArticle('node/1', [], NULL);
  }

  /**
   *
   */
  public function testArchiveArticle_invalidLocation() {
    // Expect response without key location -> Exception.
    $this->api_client->expected_response_body = ['_type' => 'dcx:success', 'location' => 'invalid'];
    $this->setExpectedException('Exception', 'The operation was successful, but the location was not parseable');
    $this->client->archiveArticle('node/1', [], NULL);
  }

  /**
   *
   */
  public function testArchiveArticle_newArticle() {
    $this->api_client->expected_response_body_array = [
    // Response of createObject/document.
      [
        '_type' => 'dcx:success',
        'location' => '/dcx/api/document/docABC',
      ],
// Response of getObject/pubinfo (pubinfoOnPath)
      [
        'entries' => [
          [
            '_id' => 'pubinfo1',
            'properties' => [
              'publication_id' => ['_id' => 'dcxapi:tm_topic/dummy_publication'],
              'doc_id' => ['_id' => 'dcxapi:document/docABC'],
              '_modcount' => 42,
            ],
          ],
        ],
      ],
    ];
    $dcx_id = $this->client->archiveArticle('node/1', [], NULL);
    // There are multiple calls to the api_client. The first one should be.
    $this->assertEquals($this->api_client->methods[0], 'createObject', 'createObject is called if dcx_id is NULL.');
    $this->assertEquals($this->api_client->urls[0], 'document', 'A document is created.');
    $this->assertEquals($dcx_id, 'document/docABC', '$dcx_id is derived from key location in response.');
  }

  /**
   *
   */
  public function testArchiveArticle_exisitingArticle() {
    $this->api_client->expected_response_body_array = [
    // Response to getObject:documentABC.
      [
        '_type' => 'dcx:success',
        'location' => '/dcx/api/document/docABC',
        'properties' => ['_modcount' => 1],

      ],
      [
        '_type' => 'dcx:success',
        'location' => '/dcx/api/document/docABC',
        'properties' => ['_modcount' => 1],
      ],
      [
        'entries' => [
          [
            '_id' => 'pubinfo1',
            'properties' => [
              'publication_id' => ['_id' => 'dcxapi:tm_topic/dummy_publication'],
              'doc_id' => ['_id' => 'dcxapi:document/docABC'],
              '_modcount' => 42,
            ],
          ],
        ],
      ],
    ];

    $dcx_id = $this->client->archiveArticle('node/1', [], 'document/docABC');

    $this->assertEquals($this->api_client->methods[0], 'get', 'getObject is called if dcx_id is set');
    $this->assertEquals($this->api_client->methods[1], 'setObject', 'setObject is called if dcx_id is set');
    $this->assertEquals($this->api_client->urls[1], 'document/docABC', 'The given document is set.');
    $this->assertEquals($dcx_id, 'document/docABC', '$dcx_id is derived from key location in response.');
  }

  /**
   *
   */
  public function testPubinfoOnPath_noResults() {
    $this->api_client->expected_response_body = ['entries' => []];
    $pubinfos = $this->client->pubinfoOnPath('node/1', 'article');
    $this->assertArrayEquals([], $pubinfos);
    $this->assertEquals($this->api_client->method, 'get', 'getObject is called when retrieving pubinfo data');
    list($url) = $this->api_client->args;
    $this->assertEquals($url, 'pubinfo', 'url "pubinfo is requested"');
  }

  /**
   *
   */
  public function testPubinfoOnPath_exception_on_non_200_response() {
    $this->api_client->expected_return_value = 23;
    $this->setExpectedException('Drupal\dcx_integration\Exception\DcxClientException', 'Error performing get on url "pubinfo". Status code was 23');
    $this->client->pubinfoOnPath('node/1', 'article');
  }

}
