<?php

namespace Drupal\Tests\dcx_integration\Kernel;

use Drupal\dcx_integration\Asset\Image;
use Drupal\dcx_integration\Asset\Article;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\UnitTestCase;

/**
 * Description of AssetTest.
 *
 * @group dcx
 */
class AssetTest extends KernelTestBase {

  /**
   *
   */
  public function testCreateImage__mandatory_attr() {
    $data = [];
    foreach (Image::$mandatoryAttributes as $attr) {
      $data[$attr] = "test__$attr.jpg";
    }
    $asset = new Image($data);

    $this->assertArraySubset($data, $asset->data(), "Mandatory attributes suffice to create an Image");
  }

  /**
   *
   */
  public function testCreateImage__optional_attr() {
    $data = [];
    foreach (array_merge(Image::$mandatoryAttributes, Image::$optionalAttributes) as $attr) {
      $data[$attr] = "test__$attr.jpg";
    }
    $asset = new Image($data);

    $this->assertArraySubset($data, $asset->data(), "Mandatory and optional attributes are able to create an Image");
  }

  /**
   *
   */
  public function testCreateImage__missing_mandatory() {
    $data = [];
    foreach (Image::$mandatoryAttributes as $attr) {
      $data[$attr] = 'test__' . $attr;
    }
    array_shift($data);

    $this->setExpectedException('Drupal\dcx_integration\Exception\MandatoryAttributeException');
    new Image($data);
  }

  /**
   *
   */
  public function testCreateImage__stray_option() {
    $invalid_attribute = 'invalid';
    $this->assertTrue(!in_array($invalid_attribute, Image::$mandatoryAttributes + Image::$optionalAttributes), 'Invalid attribute in the list of mandatory or optional attributes.');

    $data = [];
    foreach (array_merge(Image::$mandatoryAttributes, [$invalid_attribute]) as $attr) {
      $data[$attr] = 'test__' . $attr;
    }

    $this->setExpectedException('Drupal\dcx_integration\Exception\IllegalAttributeException');
    new Image($data);
  }

  /**
   *
   */
  public function testCreateArticle__mandatory_attr() {
    $data = [];
    foreach (Article::$mandatoryAttributes as $attr) {
      $data[$attr] = 'test__' . $attr;
    }
    $asset = new Article($data);

    $this->assertArraySubset($data, $asset->data(), "Mandatory attributes suffice to create an Article");
  }

  /**
   *
   */
  public function testCreateArticle__optional_attr() {
    $data = [];
    foreach (array_merge(Article::$mandatoryAttributes, Article::$optionalAttributes) as $attr) {
      $data[$attr] = 'test__' . $attr;
    }
    $asset = new Article($data);

    $this->assertArraySubset($data, $asset->data(), "Mandatory and optional attributes are able to create an Article");
  }

  /**
   *
   */
  public function testCreateArticle__missing_mandatory() {
    $data = [];
    foreach (Article::$mandatoryAttributes as $attr) {
      $data[$attr] = 'test__' . $attr;
    }
    array_shift($data);

    $this->setExpectedException('Drupal\dcx_integration\Exception\MandatoryAttributeException');
    new Article($data);
  }

  /**
   *
   */
  public function testCreateArticle__stray_option() {
    $invalid_attribute = 'invalid';
    $this->assertTrue(!in_array($invalid_attribute, Article::$mandatoryAttributes + Article::$optionalAttributes), 'Invalid attribute in the list of mandatory or optional attributes.');

    $data = [];
    foreach (array_merge(Article::$mandatoryAttributes, [$invalid_attribute]) as $attr) {
      $data[$attr] = 'test__' . $attr;
    }

    $this->setExpectedException('Drupal\dcx_integration\Exception\IllegalAttributeException');
    new Article($data);
  }

}
