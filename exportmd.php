#!/usr/bin/env php
<?php
// Where shall we export the data to?
define('BASEFOLDER', 'some_folder_name_goes_here');


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
        $tagsStr .= '"' . trim($tags[$i]->title) . '"' . $suffix;
      }

      $db = Factory::getDbo();
      $db->setQuery("SELECT cat.title FROM #__categories cat WHERE cat.id='$article->catid'");
      $category_title = $db->loadResult();

      $category_title = strtolower(trim($category_title));
      $introtext = $converter->convert($article->introtext);
      $fulltext = $converter->convert($article->fulltext);
      $images = json_decode($article->images);

      $date = new Joomla\CMS\Date\Date($article->created);
      $dateString = $date->toISO8601();
      $content =
        <<<TXT
---json
{
  "title": "$article->title",
  "description": "$article->metadesc",
  "tags": [$tagsStr],
  "date": "$dateString",
  "introImage" : "$images->image_intro",
  "introImageAlt" : "$images->image_intro_alt",
  "fulltextImage" : "$images->image_fulltext",
  "fulltextImageAlt" : "$images->image_fulltext_alt",
  "layout": "layouts/$category_title.njk"
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

    $isRoot = strtolower(trim($options['category'])) === 'uncategorised' ?? true;


    // Make sure we have the base dir for the converted files
    if (!is_dir(JPATH_ROOT . '/' . BASEFOLDER)) {
      mkdir(JPATH_ROOT . '/' . BASEFOLDER);
    }

    $base = $isRoot === true ? JPATH_ROOT . '/' . BASEFOLDER . '/' : JPATH_ROOT . '/';

    // Do we have the category Folder?
    if ($isRoot === false && !is_dir($base . trim($options['category']))) {
      mkdir($base . trim($options['category']));
    }

    // The path of the file
    $filename = $base . ($isRoot === false ?  strtolower(trim($options['category'])) . '/' : '') . $options['slug'] . '.md';

    // Now let's create that file
    file_put_contents(
      $filename,
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
