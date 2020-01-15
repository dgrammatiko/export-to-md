#!/usr/bin/env php
<?php
// Where shall we export the data to?
define('BASEFOLDER', 'exported_k2');


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
use Joomla\CMS\Language;

/**
 * Class ExportToMd
 */
class ExportToMd extends CliApplication {

  /** Run Forest, run... */
  public function execute() {
    $lang = Factory::getLanguage();

    // Get the articles
    $articles = $this->getAllArticles();
    $attachments = $this->getAllAttachments();
    $tags = $this->getAllAtags();

    // The converter
    $converter = new HtmlConverter();

    foreach ($articles as $article) {
      $tagsFinal = '';
      $tagsArr = [];
      $tagNames = [];
      $atcms = [];
      $image = '';
      $imageAlt = '';

      $db = Factory::getDbo();
      $db->setQuery("SELECT cat.alias FROM #__k2_categories cat WHERE cat.id='$article->catid'");
      $category_title = $db->loadResult();

      $category_title = $this->stringURLSafe(trim($category_title), $lang);
      $introtext = $converter->convert($article->introtext);
      $fulltext = $converter->convert($article->fulltext);
      // $images = json_decode($article->images);

      $date = new Joomla\CMS\Date\Date($article->created);
      $dateString = $date->toISO8601();


      // Get the original image
      if (file_exists( JPATH_ROOT . '/media/k2/items/src/' . md5("Image".$article->id) . '.jpg')) {
        $image = '/media/k2/items/src/' . md5("Image".$article->id) . '.jpg';
        $imageAlt = $article->image_caption;
      }

      // Get any attachments
      foreach ($attachments as $att) {
        if ($att->itemID === $article->id) {
          array_push($atcms, $att);
        }
      }

      if (!empty($atcms)) {
        var_dump($atcms);
      }

      // Get the tags
      foreach ($tags as $tagT) {
        if ($tagT->itemID === $article->id) {
          array_push($tagsArr, $tagT->tagID);
        }
      }

      if (!empty($tagsArr)) {
        for ($ik = 0; $ik < count($tagsArr); $ik++) {
          $del = ',';
          if ($ik == count($tagsArr) -1) {
            $del = '';
          }
          $tagsFinal .= $this->getTag((int) $tagsArr[$ik]) . $del;
        }
      }
      

      $content =
<<<TXT
  title: $article->title
  description: $article->metadesc
  tags: $tagsFinal
  date: $dateString
  image: $image
  imageAlt: $imageAlt
  layout: layouts/$category_title.njk
---
$introtext
<!-- excerpt -->
$fulltext
TXT;

      $this->createFile([
        'category' => $category_title,
        'slug' => $article->alias,
        'content' => $content
      ], $lang);
    }

    exit(0);
  }

  /** Now let's save to a new file */
  private function createFile($options, $lang) {
    if (empty($options) || !isset($options['category']) || !isset($options['slug']) || !isset($options['content'])) {
      return;
    }

    $isRoot = strtolower(trim($options['category'])) === 'uncategorised' ?? true;

    // Make sure we have the base dir for the converted files
    if (!is_dir(JPATH_ROOT . '/' . BASEFOLDER)) {
      mkdir(JPATH_ROOT . '/' . BASEFOLDER);
    }

    $base = JPATH_ROOT . '/' . BASEFOLDER . '/';

    // Do we have the category Folder?
    if ($isRoot === false && !is_dir($base . trim($options['category']))) {
      mkdir($base . trim($options['category']));
    }

    $englishSlug = $this->stringURLSafe(trim($options['slug']), $lang);
    // The path of the file
    $filename = $base . ($isRoot === false ?  strtolower(trim($options['category'])) . '/' : '') . $englishSlug . '.md';

    // Now let's create that file
    file_put_contents(
      $filename,
      $options['content'],
      0
    );
  }

  /** Get all the articles */
  private function getAllArticles() {
    $db = Factory::getDbo();
    $q = $db->getQuery(true)
      ->select("*")
      ->from($db->qn("#__k2_items"));
    $db->setQuery($q);
    return $db->loadObjectList();
  }

  // Get all the attachments
  private function getAllAttachments() {
    $db = Factory::getDbo();
    $q = $db->getQuery(true)
      ->select("*")
      ->from($db->qn("#__k2_attachments"));
    $db->setQuery($q);
    return $db->loadObjectList();
  }

  // Get all the tags
  private function getAllAtags() {
    $db = Factory::getDbo();
    $q = $db->getQuery(true)
      ->select("*")
      ->from($db->qn("#__k2_tags_xref"));
    $db->setQuery($q);
    return $db->loadObjectList();
  }

  // Get the name of a tag by id
  private function getTag($id) {
    $db = Factory::getDbo();
    $q = $db->getQuery(true)
      ->select('name')
      ->where('id = ' . (int) $id)
      ->where('published = 1')
      ->from($db->qn("#__k2_tags"));
    $db->setQuery($q);
    return $db->loadResult();
  }

  
 private function stringURLSafe($string, $lang) {
        //remove any '-' from the string they will be used as concatonater
        $str = str_replace('-', ' ', $string);
        $str = str_replace('_', ' ', $string);

        // $lang = Factory::getLanguage();
        $str = $lang->transliterate($str);

        // remove any duplicate whitespace, and ensure all characters are alphanumeric
        $str = preg_replace(array('/\s+/','/[^A-Za-z0-9\-]/'), array('-',''), $str);

        // lowercase and trim
        $str = trim(strtolower($str));
        return $str;
    }
} 

try {
  CliApplication::getInstance('ExportToMd')->execute();
} catch (Exception $e) {
  echo $e->getMessage() . "\n";
  exit(1);
}
