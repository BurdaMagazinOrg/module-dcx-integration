<?php

namespace Drupal\Tests\dcx_integration\Kernel;

use Drupal\dcx_integration\Asset\Article;
use Drupal\dcx_integration\Asset\Image;
use Drupal\dcx_integration\JsonClient;
use Drupal\KernelTests\KernelTestBase;

/**
 * Class DcxJsonClientIntegrationTest.
 *
 * @group dcx_integration
 */
class DcxJsonClientIntegrationTest extends KernelTestBase {

  const DCX_IMAGE_ID = 'dcxapi:document/doc6vkgudvfik99vei734v';
  const DCX_ARTICLE_ID = 'dcxapi:document/doc6u9t0hf7jf99jzteot4';

  protected static $modules = ['dcx_integration', 'system'];

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
    parent::setUp();

    $siteSettings = ['mail' => 'admin@admin.de', 'name' => 'Integration Test'];

    $this->config('dcx_integration.jsonclientsettings')->setData([
      'url' => getenv('DCX_URL'),
      'username' => getenv('DCX_USER'),
      'password' => getenv('DCX_PASS'),
      'publication' => getenv('DCX_PUBLICATION'),
      'notification_access_key' => getenv('DCX_NOTIFICATION_KEY'),
    ])->save();

    $this->config('system.site')->setData($siteSettings)->save();
    $user = $this->getMock('\Drupal\Core\Session\AccountProxyInterface');
    $user->method('getEmail')->willReturn(getenv('DCX_USER_MAIL'));

    $logger = $this->getMock('\Psr\Log\LoggerInterface');
    $loggerFactory = $this->getMock('\Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $loggerFactory->expects($this->any())
      ->method('get')
      ->will($this->returnValue($logger));

    $this->client = new JsonClient($this->container->get('config.factory'), $user, $this->container->get('string_translation'), $loggerFactory);

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
