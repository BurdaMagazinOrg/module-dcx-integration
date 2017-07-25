<?php

namespace Drupal\Tests\dcx_integration\Unit;

use Drupal\dcx_integration\Asset\Article;
use Drupal\dcx_integration\Asset\Image;
use Drupal\dcx_integration\JsonClient;
use Drupal\Tests\UnitTestCase;

/**
 * Class DcxJsonClientIntegrationTest.
 *
 * @group dcx_integration
 */
class DcxJsonClientIntegrationTest extends UnitTestCase {

  const DCX_IMAGE_ID = 'dcxapi:document/doc6vkgudvfik99vei734v';
  const DCX_ARTICLE_ID = 'dcxapi:document/doc6u9t0hf7jf99jzteot4';

  /**
   * Client class.
   *
   * @var \Drupal\dcx_integration\JsonClient
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function setUp() {

    $jsonclientsettings = json_decode(getenv('DCX_SETTINGS'), 1);

    $siteSettings = ['mail' => 'admin@admin.de', 'name' => 'Integration Test'];

    $config_factory = $this->getConfigFactoryStub([
      'dcx_integration.jsonclientsettings' => $jsonclientsettings,
      'system.site' => $siteSettings,
    ]);
    $user = $this->getMock('\Drupal\Core\Session\AccountProxyInterface');
    $user->method('getEmail')->willReturn(getenv('DCX_USER_MAIL'));

    $logger = $this->getMock('\Psr\Log\LoggerInterface');
    $loggerFactory = $this->getMock('\Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $loggerFactory->expects($this->any())
      ->method('get')
      ->will($this->returnValue($logger));

    $stringTranslation = $this->getStringTranslationStub();
    $this->client = new JsonClient($config_factory, $user, $stringTranslation, $loggerFactory);

  }

  /**
   * Test retrieving an image from dcx server.
   */
  public function testGetImage() {

    $image = $this->client->getObject(static::DCX_IMAGE_ID);

    $this->assertTrue($image instanceof Image);
    $this->assertSame(static::DCX_IMAGE_ID, $image->data()['id']);
    $this->assertSame('fotolia_160447209.jpg', $image->data()['filename']);
    $this->assertSame(TRUE, $image->data()['status']);
  }

  /**
   * Test retrieving an article from dcx server.
   */
  public function testGetArticle() {

    $article = $this->client->getObject(static::DCX_ARTICLE_ID);

    $this->assertTrue($article instanceof Article);
    $this->assertSame(static::DCX_ARTICLE_ID, $article->data()['id']);
    $this->assertSame('„Meine Ehrlichkeit hat mir oft geschadet“', $article->data()['title']);
  }

  /**
   * Test usage tracking.
   */
  public function testTrackUsage() {

    $entities = [
      static::DCX_IMAGE_ID => ['id' => 1, 'entity_type_id' => 'media'],
    ];

    $this->client->removeAllUsage(static::DCX_IMAGE_ID);

    $infos = $this->client->pubinfoOnPath('node/1', 'image');
    $this->assertEmpty($infos);

    $this->client->trackUsage($entities, 'node/1', TRUE, 'image');
    $infos = $this->client->pubinfoOnPath('node/1', 'image');
    $this->assertCount(1, $infos);

    $pubInfo = current($infos[static::DCX_IMAGE_ID]);
    $this->assertSame('dcx:pubinfo', $pubInfo['_type']);
    $this->assertSame(static::DCX_IMAGE_ID, $pubInfo['properties']['doc_id']['_id']);
    $this->assertSame('dcxapi:tm_topic/publication-thunder-testing', $pubInfo['properties']['publication_id']['_id']);

    $this->client->removeUsageForCertainEntity(static::DCX_IMAGE_ID, 'media', 1);
    $infos = $this->client->pubinfoOnPath('node/1', 'image');
    $this->assertEmpty($infos);
  }

}
