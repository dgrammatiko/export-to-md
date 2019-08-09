#!/usr/bin/env php
<?php
/** 
 * Joomla default section START
 */
// We are a valid entry point.
define('_JEXEC', 1);

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php')) {
  require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES')) {
  define('JPATH_BASE', dirname(__DIR__));
  require_once JPATH_BASE . '/includes/defines.php';
}

// Get the framework.
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

// Import the configuration.
require_once JPATH_CONFIGURATION . '/configuration.php';

// Import the HTML To Markdown for PHP
require __DIR__ . '/vendor/autoload.php';

// System configuration.
$config = new JConfig;

// Configure error reporting to maximum for CLI output.
error_reporting(E_ALL);
ini_set('display_errors', 1);

/** 
 * Joomla default section END
 */
use Joomla\CMS\Factory;
use Joomla\CMS\Application\CliApplication;
use League\HTMLToMarkdown\HtmlConverter;
use Joomla\CMS\Helper\TagsHelper;

/**
 * Class ExportToMd
 */
class ExportToMd extends CliApplication {

    // Where shall we export the data to?
    private $baseFolder = 'articles_md';

  /** Run Forest, run... */
  public function execute( ) {
      // Get the articles
    $articles = $this->getAllArticles();

    // The converter
    $converter = new HtmlConverter();

    foreach ($articles as $article) {
      /**
       * Just for ref...
       *
       * $article->id
       * $article->title
       * $article->alias
       * $article->introtext
       * $article->fulltext
       * $article->catid
       * $article->created
       * $images->
       * $image_intro
       * $image_intro_alt
       * $image_fulltext
       * $image_fulltext_alt
       */
      $tagsStr = '';
      $tags = (new TagsHelper)->getItemTags('com_content.article', $article->id);

      for ($i=0; $i<count($tags); $i++) {
        $suffix = $i+1 < count($tags) ? ', ' : '';
        $tagsStr .= trim($tags[$i]->title) . $suffix;
      }

      $db = Factory::getDbo();
      $db->setQuery("SELECT cat.title FROM #__categories cat WHERE cat.id='$article->catid'");
      $category_title = $db->loadResult();

      $introtext = $converter->convert($article->introtext);
      $fulltext = $converter->convert($article->fulltext);
      $images = json_decode($article->images);

      $content =
<<<TXT
---json
{
  "title": "$article->title"
  "tags": "$tagsStr"
  "date": "$article->created"
  "introImage" : "$images->image_intro"
  "introImageAlt" : "$images->image_intro_alt"
  "fulltextImage" : "$images->image_fulltext"
  "fulltextImageAlt" : "$images->image_fulltext_alt"
}
---
$introtext
<!-- excerpt -->
$fulltext
TXT;

        $this->createFile([
            'category' => $category_title,
            'slug' => $article->alias,
            'content' => $content
        ]);
    }

    exit(0);
  }

  /** Now let's save to a new file */
  private function createFile($options) {
      if (empty($options) || !isset($options['category']) || !isset($options['slug']) || !isset($options['content'])) {
          return;
      }

      // Make sure we have the base dir for the converted files
    if (!is_dir(JPATH_ROOT . '/' . $this->baseFolder)) {
        mkdir(JPATH_ROOT . '/' . $this->baseFolder);
    }

    // Do we have the category Folder?
    if (trim($options['category']) !== 'Uncategorised' && !is_dir(JPATH_ROOT . '/' . $this->baseFolder . '/' . trim($options['category']))) {
      mkdir(JPATH_ROOT . '/' . $this->baseFolder . '/' . trim($options['category']));
    }

    // Now let's create that file
    file_put_contents(
      JPATH_ROOT . '/' . $this->baseFolder . '/' . (trim($options['category']) !== 'Uncategorised' ? '/' . trim($options['category']) : '') . $options['slug'] . '.md',
      $options['content'],
      0
    );
  }
  /** Get all the articles */
  private function getAllArticles()
  {
    $db = Factory::getDbo();
    $q = $db->getQuery(true)
      ->select("*")
      ->from($db->qn("#__content"));
    $db->setQuery($q);
    return $db->loadObjectList();
  }
}

try {
  CliApplication::getInstance('ExportToMd')->execute();
} catch (Exception $e) {
  echo $e->getMessage() . "\n";
  exit(1);
}
