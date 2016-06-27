<?php

namespace Drupal\Tests\dcx_integration\Unit;

use Drupal\dcx_integration\JsonClient;
use Drupal\Tests\dcx_integration\DummyDcxApiClient;
use Drupal\Tests\UnitTestCase;

/**
 * @group dcx
 */
class DcxJsonClientTest extends UnitTestCase {
  protected $client;

  protected $api_client;

  function setUp() {
    $jsonclientsettings = ['publication' => 'dummy_publication'];
    $config_factory = $this->getConfigFactoryStub(['dcx_integration.jsonclientsettings' => $jsonclientsettings]);
    $user = $this->getMock('\Drupal\Core\Session\AccountProxyInterface');
    $stringTranslation = $this->getStringTranslationStub();
    $this->api_client = new DummyDcxApiClient();
    $this->client = new JsonClient($config_factory, $user, $stringTranslation, $this->api_client);
  }

  function testGetJson_noparams() {
    $this->client->getJson('dcxapi:id');
    list($url, $params,) = $this->api_client->args;
    $this->assertEquals($this->api_client->method, 'getObject', 'getObject method of API client is called.');
    $this->assertEquals($url, 'id', 'Client disposes "dcxapi:" part of the id.');
    $this->assertNotEmpty($params, 'If no params are given, default params are passed to the API client.');
  }

  function testGetJson_custom_params() {
    $this->client->getJson('dcxapi:id', ['params']);
    list(, $params,) = $this->api_client->args;
    $this->assertArrayEquals(['params'], $params, 'If params are given, they are passed to the API client');
  }

  function testGetJson_exception_on_non_200_response() {
    $this->api_client->expected_return_value = 23;
    $this->setExpectedException('Exception', 'Error getting "id". Status code was 23.');
    $this->client->getJson('dcxapi:id');
  }

  function testArchiveArticle_emptyResponse() {
    // Expect empty response -> Exception.
    $this->api_client->expected_response_body = NULL;
    $this->setExpectedException('Exception', 'Unable to archive node/1, "The operation yielded no result."');
    $this->client->archiveArticle('node/1', [], NULL);
  }

  function testArchiveArticle_invalidResponse() {
    // Expect invalid response -> Exception.
    $this->api_client->expected_response_body = 'invalid';
    $this->setExpectedException('Exception', 'Unable to archive node/1, "The result operation has no type."');
    $this->client->archiveArticle('node/1', [], NULL);
  }

  function testArchiveArticle_noSuccess() {
    // Expect response without _type == success -> Exception.
    $this->api_client->expected_response_body = ['_type' => 'no success'];
    $this->setExpectedException('Exception', 'Unable to archive node/1, "no success"');
    $this->client->archiveArticle('node/1', [], NULL);
  }

  function testArchiveArticle_noLocation() {
    // Expect response without key location -> Exception.
    $this->api_client->expected_response_body = ['_type' => 'dcx:success'];
    $this->setExpectedException('Exception', 'Unable to archive node/1, "The operation was successful, but key location was not found."');
    $this->client->archiveArticle('node/1', [], NULL);
  }

  function testArchiveArticle_invalidLocation() {
    // Expect response without key location -> Exception.
    $this->api_client->expected_response_body = ['_type' => 'dcx:success', 'location' => 'invalid'];
    $this->setExpectedException('Exception', 'Unable to archive node/1, "The operation was successful, but the location was not parseable."');
    $this->client->archiveArticle('node/1', [], NULL);
  }

  function testArchiveArticle_newArticle() {
    $this->api_client->expected_response_body = ['_type' => 'dcx:success', 'location' => '/dcx/api/document/docABC'];
    $dcx_id = $this->client->archiveArticle('node/1', [], NULL);
    $this->assertEquals($this->api_client->method, 'createObject', 'createObject ist called is dcx_id is NULL');
    $this->assertEquals($dcx_id, 'document/docABC', '$dcx_id is derived from key location in response.');
  }

  function testArchiveArticle_exisitingArticle() {
    $this->api_client->expected_response_body = [
      '_type' => 'dcx:success',
      'location' => '/dcx/api/document/docABC',
      'properties' => ['_modcount' => 1],
    ];
    $dcx_id = $this->client->archiveArticle('node/1', [], '123');
    $this->assertEquals($this->api_client->method, 'setObject', 'setObject is called if dcx_id is set');
    $this->assertEquals($dcx_id, 'document/docABC', '$dcx_id is derived from key location in response.');
  }

  function testPubinfoOnPath_noResults() {
    $this->api_client->expected_response_body = ['entries' => []];
    $pubinfos = $this->client->pubinfoOnPath('node/1');
    $this->assertArrayEquals([], $pubinfos);
    $this->assertEquals($this->api_client->method, 'getObject', 'getObject is called when retrieving pubinfo data');
    list($url) = $this->api_client->args;
    $this->assertEquals($url, 'pubinfo', 'url "pubinfo is requested"');
  }

  function testPubinfoOnPath_exception_on_non_200_response() {
    $this->api_client->expected_return_value = 23;
    $this->setExpectedException('Exception', 'Error getting object "pubinfo". Status code was 23.');
    $this->client->pubinfoOnPath('node/1');
  }

}
