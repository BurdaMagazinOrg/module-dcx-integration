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
   *
   */
  public function testArticleImport() {

    $jsonclientsettings = json_decode(getenv('DCX_SETTINGS'), 1);
    $this->config('dcx_integration.jsonclientsettings')->setData($jsonclientsettings)->save();
    $this->config('system.site')->setData(['mail' => 'admin@admin.de', 'name' => 'Integration Test'])->save();

    $this->drupalLogin($this->createUser(['import from dcx']));

    $this->drupalGet('node/add/article/dcx-import');

#    $this->getSession()->getPage()->fillField()

  }

}
