<?php

namespace Drupal\Tests\dcx_article_import\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Class ArticleImportTest.
 */
class ArticleImportTest extends JavascriptTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'dcx_article_import',
    'dcx_test',
  ];

  /**
   * Test article import.
   */
  public function testArticleImport() {

    $this->config('dcx_integration.jsonclientsettings')->setData([
      'url' => getenv('DCX_URL'),
      'username' => getenv('DCX_USER'),
      'password' => getenv('DCX_PASS'),
      'publication' => getenv('DCX_PUBLICATION'),
      'notification_access_key' => getenv('DCX_NOTIFICATION_KEY'),
    ])->save();
    $this->config('system.site')->setData(['mail' => 'admin@admin.de', 'name' => 'Integration Test'])->save();

    $this->drupalLogin($this->createUser(['import from dcx']));

    $this->drupalGet('node/add/article/dcx-import');
  }

}
