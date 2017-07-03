<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Drupal\Tests\dcx_integration\Unit;

use Drupal\dcx_integration\Asset\Image;
use Drupal\dcx_integration\Asset\Article;
use Drupal\Tests\UnitTestCase;

/**
 * Description of AssetTest.
 *
 * @group dcx
 */
class AssetTest extends UnitTestCase {

  /**
   *
   */
  public function testCreateImage__mandatory_attr() {
    $data = [];
    foreach (Image::$mandatoryAttributes as $attr) {
      $data[$attr] = 'test__' . $attr;
    }
    $asset = new Image($data);

    $this->assertArrayEquals($data, $asset->data(), "Mandatory attributes suffice to create an Image");
  }

  /**
   *
   */
  public function testCreateImage__optional_attr() {
    $data = [];
    foreach (array_merge(Image::$mandatoryAttributes, Image::$optionalAttributes) as $attr) {
      $data[$attr] = 'test__' . $attr;
    }
    $asset = new Image($data);

    $this->assertArrayEquals($data, $asset->data(), "Mandatory and optional attributes are able to create an Image");
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
    $asset = new Image($data);
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
    $asset = new Image($data);
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

    $this->assertArrayEquals($data, $asset->data(), "Mandatory attributes suffice to create an Article");
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

    $this->assertArrayEquals($data, $asset->data(), "Mandatory and optional attributes are able to create an Article");
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
    $asset = new Article($data);
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
    $asset = new Article($data);
  }

}
