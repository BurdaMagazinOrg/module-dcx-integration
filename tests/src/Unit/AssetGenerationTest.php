<?php

namespace Drupal\Tests\dcx_integration\Unit;

use Drupal\dcx_integration\JsonClient;
use Drupal\Tests\UnitTestCase;

/**
 * @group dcx
 */
class AssetGenerationTest extends UnitTestCase {
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
  public function testGetObject__unknown_type() {
    $this->api_client->expected_response_body = [
      'fields' => ['Type' => [0 => ['_id' => 'unknown']]],
    ];

    $this->setExpectedException('Drupal\dcx_integration\Exception\UnknownDocumentTypeException', "DC-X object idOfUnknownType has unknown type 'unknown'.");
    $this->client->getObject('idOfUnknownType');
  }

  /**
   *
   */
  public function testGetObject__image() {
    $this->api_client->expected_response_body = [
      '_id' => 'document/xyz',
      'fields' => [
        'Type' => [0 => ['_id' => 'dcxapi:tm_topic/documenttype-image']],
        'Filename' => [0 => ['value' => 'test__title']],
        'url' => [[$this, 'extractUrl'], 'files', 0, '_id'],
        'Creator' => [['value' => 'test__Creator']],
      ],
      "files" => [["_id" => "test__file"]],
      '_referenced' => [
        'dcx:file' => ["test__file" => ['properties' => ['_file_url_absolute' => 'test__url']]],
        'dcx:rights' => ["test__right" => ['properties' => ['topic_id' => ['_id' => 'dcxapi:tm_topic/rightsusage-Online']]]],
      ],
      '_rights_effective' => ['rightstype-UsagePermittedDigital' => [[["_id" => "test__right"]]]],
    ];

    $asset = $this->client->getObject('document/xyz');
    $this->assertInstanceOf('Drupal\dcx_integration\Asset\Image', $asset);
  }

  /**
   *
   */
  public function testGetObject__article() {
    $this->api_client->expected_response_body = [
      '_id' => 'document/abc',
      '_type' => 'dcx:document',
      'fields' => [
        'Type' => [0 => ['_id' => 'dcxapi:tm_topic/documenttype-story']],
        'Headline' => [0 => ['value' => 'test__title']],
        'body' => [0 => ['value' => 'test__body']],
      ],
    ];
    $asset = $this->client->getObject('document/abc');
    $this->assertInstanceOf('Drupal\dcx_integration\Asset\Article', $asset);
  }

}
