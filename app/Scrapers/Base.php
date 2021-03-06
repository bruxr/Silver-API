<?php

namespace App\Scrapers;

use phpQuery;
use Monolog\Logger;

/**
 * Scraper Base
 *
 * Base class for all classes responsible for scraping movie schedules off
 * our client cinema websites. This class provides several convenience methods
 * for fetching and sanitation of input data.
 *
 * @package Silver
 * @author brux
 * @since 0.1.0
 */
abstract class Base
{

  /**
   * Contains the official MTRCB ratings.
   */
  protected static $RATINGS = ['G', 'PG', 'R-13', 'R-16', 'R-18'];
  
  /**
   * All our collected movies.
   *
   * @var array
   */
  protected $movies = [];

    /**
     * A reference to the app logger.
     * 
     * @var Monolog\Logger
     */
    protected $logger;
  
  /**
   * Does the actual scraping of movie schedules.
   *
   * @return void
   */
  abstract public function fetch();
  
    /**
     * Constructor.
     * 
     * @param Logger $logger a reference to the logger
     */
    function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

  /**
   * Returns TRUE if we have collected schedules.
   *
   * @return bool
   */
  public function hasMovies()
  {
    return count($this->movies) > 0;
  }

  /**
   * Returns the movies we have collected.
   *
   * @return array
   */
  public function getMovies()
  {
    return $this->movies;
  }
  
  /**
   * Loads a page and passes it to phpQuery so it is available
   * for parsing and processing.
   *
   * @param string $url page url
   * @return phpQuery
   */
  protected function loadPage($url)
  {
    return phpQuery::newDocumentHTML(file_get_contents($url));
  }
  
  /**
   * Returns TRUE if the provided MTRCB rating is a valid one.
   *
   * @return bool
   */
  protected function isValidMtrcbRating($rating)
  {
    return in_array($rating, static::$RATINGS);
  }
  
  /**
   * General purpose cleaning function.
   *
   * @param string $input input string
   * @return string
   */
  protected function sanitize($input)
  {
    return strip_tags(trim($input));
  }
  
  public function cleanMovieTitle($title)
  {
    return $this->sanitize($title);
  }
  
  public function cleanCinemaName($name)
  {
    if ( preg_match('(Cinema\s[0-9])', $name, $matches) )
    {
      return $matches[0];
    }
    else
    {
      return 'Unknown Cinema';
    }
  }
   
}