<?php

namespace Drupal\Tests\dcx_article_import\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

class ArticleImportTest extends JavascriptTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [

    'dcx_article_import',
  ];

  /**
   *
   */
  public function testArticleImport() {

    $this->drupalGet('node/add/article/dcx-import');
  }

}
