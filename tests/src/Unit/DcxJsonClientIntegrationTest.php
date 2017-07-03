<?php

namespace Drupal\Tests\dcx_integration\Unit;

use Drupal\dcx_integration\Asset\Image;
use Drupal\dcx_integration\JsonClient;
use Drupal\Tests\UnitTestCase;

/**
 * @group dcx
 */
class DcxJsonClientIntegrationTest extends UnitTestCase {

  /**
   * @var \Drupal\dcx_integration\JsonClient
   */
  protected $client;

  /**
   *
   */
  public function setUp() {

		$jsonclientsettings = json_decode(getenv('DCX_SETTINGS'), 1);

    $siteSettings = ['mail' => 'admin@admin.de'];

    $config_factory = $this->getConfigFactoryStub(['dcx_integration.jsonclientsettings' => $jsonclientsettings, 'system.site' => $siteSettings]);
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
   *
   */
  public function testGetImage() {

    $image = $this->client->getObject('dcxapi:document/doc6vkgudvfik99vei734v');

		$this->assertTrue($image instanceof Image);
		$this->assertSame('dcxapi:document/doc6vkgudvfik99vei734v', $image->data()['id']);
  }


}
