<?php

/**
 * @file
 * Contains \Drupal\dcx_integration_debug\MockClient.
 */

namespace Drupal\dcx_integration_debug;

use Drupal\dcx_integration\Asset\Image;
use Drupal\dcx_integration\Asset\Article;
use Drupal\dcx_integration\ClientInterface;

/**
 * Class Client.
 *
 * @package Drupal\dcx_integration_debug
 */
class MockClient implements ClientInterface {

  /**
   * The mock client extracts an int from the first argument and evaluates it.
   * If it's divisible by 3 it's an article, if it's divisible by 2 it's an
   * image.
   */
  public function getObject($url, $params = []) {
    if (preg_match('/^dcxapi:doc/', $url)) {
      $type = filter_var($url, FILTER_SANITIZE_NUMBER_INT);

      return $this->buildImageAsset($url);
      /*
      // Evaluate data and decide what kind of asset we have here
      if (0 == $type%3) {
        return $this->buildStoryAsset($url);
      }
      if (0 == $type%2) {
        return $this->buildImageAsset($url);
      }
      */
    }
    else {
      throw new \Exception("No handler for URL type $url.");
    }
  }

  protected function buildImageAsset($url) {
    global $base_url;

    $data['id'] = $url;
    $data['title'] = "Mocked image $url";
    $data['filename'] = 'mockimg.png';
    $data['url'] = $base_url . '/core/themes/bartik/screenshot.png';
    $data['kill_date'] = date('Y-m-d', strtotime('now - 1 day'));


    return new Image($data);
  }

  protected function buildStoryAsset($url) {
    $data['id'] = $url;
    $data['title'] = "Mocked article $url";
    $data['headline'] = 'Fake news!';
    $data['body'] = "Eine wunderbare Heiterkeit hat meine ganze Seele"
      . " eingenommen, gleich den süßen Frühlingsmorgen, die ich mit ganzem"
      . " Herzen genieße. Ich bin allein und freue mich meines Lebens in dieser"
      . " Gegend, die für solche Seelen geschaffen ist wie die meine.";

    return new Article($data);
  }

  public function trackUsage($dcx_ids, $url, $published) {
    dpm("Media " . print_r($dcx_ids, 1) . " used on URL {" . $url . "}");
  }

  /**
   * {{@inheritdoc}}
   */
  public function archiveArticle($url, $title, $text, $dcx_id) {
    if (!$dcx_id) {
      $dcx_id = "dcxapi:document/doc__mocked__" . rand(10000000000, 99999999999);
    }

    return $dcx_id;
  }
}
