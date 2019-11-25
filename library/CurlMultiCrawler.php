<?php
namespace MyCrawler;
use DOMDocument;

/*
 * Crawls websites recursively starting with the passed in url.
 * @author: Jim Kung
 * @version 1.0
 * 
 * Usage: 
 *   $crawler = new CurlMultiCrawler(100, true);
 *   $crawler->init_crawl_link($url);
 *   echo json_encode($crawler->getPageCrawlData());
 *
 */ 
class CurlMultiCrawler{
  public $max_page_curls;
  public $max_depth = 2;  
  public $only_curl_url_domain = true; // only allow to curl from the starting url domain 
  public $allow_recursive_crawl; 

  private $already_crawled_array = [];
  private $page_curl_count = 0;
  private $crawling_page_array = [];
  private $verbose = false; // toggle to display test output
  private $start_url;
  private $curl_docs_array = [];
  
  /*
   * Class Constructor
   * 
   * @param int $max_page_curls -> The maximum number of pages to curl
   * @param bool $allow_recursive_crawl -> To allow recursively crawl the page links 
   */
  function __construct($max_page_curls = 5, $allow_recursive_crawl = false){
    $this->max_page_curls = $max_page_curls;
    $this->allow_recursive_crawl = $allow_recursive_crawl;
  }

  /*
   * display the dump var within a pre tag
   * 
   * @param variable $value -> the variable you want to dump
   */
  static function dump($value){
    echo "<pre>", var_dump($value), "</pre><BR>"; 
  }

  /*
   * calls on the first url link to be crawled and outputs the final resulting json data collected from all the pages crawled
   * @param string $url -> initial starting page link
   */
  function init_crawl_link($url){
    $this->start_url = $url;
    // curl the first page so we have links to work with
    
    $this->curl_multi_links($this->start_url, 0);

    if($this->verbose){ 
      $this->dump($this->crawling_page_array);
      echo json_encode($this->crawling_page_array);
    }
  }

  /*
   * returns an array of all the pages the crawler visited
   * @return array -> an associated array of the page data
   */
  public function getPageCrawlData(){
    return array_values($this->crawling_page_array);
  }

  /*
   * curl multiple links asynchronously if the url passed in have data already stored
   * @param string $url    -> url of the link to curl
   * @param integer $depth -> recursion depth
   */
  private function curl_multi_links($url, $depth){
    if ($this->page_curl_count <= $this->max_page_curls){
      if($this->verbose)
        echo "curl_multi_links: $url depth: $depth <BR>";

      $links_array = array();

      // if we aleady have data in the crawling_page_array then we look at all the passed in url links
      if(count($this->crawling_page_array)){
        $links_array = $this->only_curl_url_domain ? 
        $this->crawling_page_array[$url]["internal_links"] : 
        array_merge($this->crawling_page_array[$url]["internal_links"], $this->crawling_page_array[$url]["external_links"]);
      }
      else{
        // the first time we curl
        array_push($links_array, $url);
      }

      $curl_handler_array = array();
      $curl_multi_handle = curl_multi_init();
      //echo "links_array: ".count($links_array)."<BR>";

      // setup the multi curl 
      foreach($links_array as $link){
        if($this->page_curl_count >= $this->max_page_curls){
          break;
        }   
        if(!array_key_exists($link, $this->crawling_page_array)){
          if($this->verbose)
            echo "url: $link depth: $depth<BR>";
          
          $curl_handle = curl_init($link);
          
          curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl_handle, CURLOPT_USERAGENT, "JK webcrawler");  
          curl_setopt($curl_handle, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);  
          curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, 1);

          curl_multi_add_handle($curl_multi_handle, $curl_handle);
          
          $curl_handler_array[$link] = $curl_handle;
          $this->page_curl_count++; 
        }
      }

      // execute the multi curl handle
      $running = null;
      do {
        curl_multi_exec($curl_multi_handle, $running);
      } while ($running);

      
      //$this->dump($curl_handler_array);
      // once all the pages has been asynchronously visited, we need to process the content
      foreach($curl_handler_array as $link => $curl_handle){
        $contents = curl_multi_getcontent($curl_handle);

        $info = curl_getinfo($curl_handle);
        $doc = new DOMDocument();
        @$doc->loadHTML($contents);
    
        $this->crawling_page_array[$link] = array(
          "link" => $link,
          "http_code" => $info["http_code"],
          "total_time" => $info["total_time"],
          "avg_word_len" => $this->avg_word_len($contents)
        );
        
        $this->page_details($link, $doc, $depth);

        // close the handle
        curl_multi_remove_handle($curl_multi_handle, $curl_handle);
        curl_close($curl_handle); 
      }   
    } 

    // we call on the next page to be visited recursively
    $depth++; 
    foreach($curl_handler_array as $link => $curl_handle){
      if($this->page_curl_count < $this->max_page_curls && $this->allow_recursive_crawl){
        if($depth <= $this->max_depth){
          $this->curl_multi_links($link, $depth);
        }  
      } 
      else{
        break;
      }  
    }
  }

  /*
   * process the page details and store in crawling_page_array
   * 
   * @param string $url -> url of page
   * @param array $doc -> doc from the last curl
   */
  private function page_details($url, $doc, $depth){
    if($this->verbose)
      echo "Details curled: $url <B>$depth</B> ".count($this->crawling_page_array). " == " .$this->page_curl_count. "<BR>";

    // Create an array of all of the title tags.
    $title = $doc->getElementsByTagName("title");
    // There should only be one <title> on each page, so our array should have only 1 element.
    $page_title = "";
    if($title->length > 0){
      $page_title = $title->item(0)->nodeValue;  
    }
  
    $images = $doc->getElementsByTagName("img");
    $images_array = array();
    for($i =0; $i < $images->length; $i++){
      $image = $images->item($i);
      $images_array[] = $this->absolute_link($url, $image->getAttribute("src")); 
    }
    
    // get all the current page links
    $link_list = $doc->getElementsByTagName("a");

    $internal_links_array = array();
    $external_links_array = array();
    foreach ($link_list as $each_link) {
      $each_link =  $this->absolute_link($url, $each_link->getAttribute("href"));
      if($this->link_is_external($each_link)){
        $external_links_array[] = $each_link;
      }
      else{
        $internal_links_array[] = $each_link;
      }
    }// EO foreach 
    $page_data_array = array(
      "title" => $page_title, //strlen(trim($page_title)),
      "link" => $url,
      "link_is_external" =>  $this->link_is_external($url),
      "images" => array_values(array_unique($images_array)),
      "internal_links" => array_values(array_unique($internal_links_array)), 
      "external_links" => array_values(array_unique($external_links_array))
    );

    $this->crawling_page_array[$url] = array_merge($this->crawling_page_array[$url], $page_data_array);
  }// EO page_details  

  /*
   * Calculate the average word count for given string content
   * 
   * @param string $contents -> $content from page
   * @return int -> average word count
   */
  function avg_word_len($contents){
    // Get rid of style, script etc
    $search = array('@<script[^>]*?>.*?</script>@si',  // Strip out javascript
              '@<head>.*?</head>@siU',            // Lose the head section
              '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
              '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments including CDATA
    );
    $contents = preg_replace($search, '', $contents);
  
    return str_word_count(strip_tags($contents), 0);       
  } 

  /*
   * converts any link to an absolute link. If the link has the index such as index.html then we return without the path info.
   * 
   * @param string $url -> page url 
   * @param string $link -> page links
   * @return string -> absolute page link
   */
  public function absolute_link($url, $link){
    $url_components = parse_url($url);

    // remove hashtags
    $link = strtok($link, "#");  

    if (substr($link, 0, 1) == "/" && substr($link, 0, 2) != "//") {
      // "/index.php"  
      $link = $url_components["scheme"] 
              . "://"
              . $url_components["host"] 
              . $link;
    } 
    else if (substr($link, 0, 2) == "//") {
      // "//www.example.com"
      $link = $url_components["scheme"]
              . ":"
              . $link;
    } 
    else if (substr($link, 0, 2) == "./") {
      // "./index.php"
      $link = $url_components["scheme"]
              . "://"
              . $url_components["host"]
              . substr($link, 1);
    } 
    else if (substr($link, 0, 3) == "../") {
      // "../../innerContent.php"
      $link = $url_components["scheme"]
              . "://"
              . $url_components["host"]
              . "/"
              . $link;
    } 
    else if (substr($link, 0, 5) != "https" && substr($link, 0, 4) != "http") {
      $link = $url_components["scheme"]
              . "://"
              . $url_components["host"]
              . "/"
              . $link;
    }
    else if (substr($link, 0, 11) == "javascript:") {
      // "javascript:doSomeThing();"
      return false; // skip it 
    } 
    // check if the link contain index.html, index.php, index.aspx ..etc and remove it from the link 
    // so we can compare the link if we have already visited it.
    $link_components = parse_url($link);
    //$this->dump($link_components);

    if(!empty($link_components["path"])){
      if(strrpos(strtolower($link_components["path"]), "index.")){
        $link = $link_components['scheme']."://".$link_components["host"];
      }     
        
      // remove paths with / at the end without any other info such as www.example.com/
      if($link_components["path"] == '/'){
        $link = substr($link, 0, strlen($link)-1);
      }  
    }// EO if link_components path is not empty   

    return $link;  
  }// EO absolute_link

  /*
   * Check if the link is internal or external
   * @param string $link -> a link from the current page
   * @return bool 
   */
  private function link_is_external($page_link) {
    $page_host = $w = preg_replace('/^www\./i', '', parse_url($this->start_url)["host"]); // remove the www prefix from host
    $page_link_components = parse_url($page_link);

    if ( empty($page_link_components['host']) ) return false;  // host is empty
    if ( strcasecmp($page_link_components['host'], $page_host) === 0 ) return false; // host is same as the page link

    // check the page subdomain is within the same page domain
    /* for example: www.example.com, www.jim.example.com
     * domain_last_pos: 7 since there is 7 chars in www.jim
     * expected_domain_pos: 19(www.jim.example.com) - 12(.example.com) = 7
     */
    $domain_last_pos = strrpos(strtolower($page_link_components['host']), '.'.$page_host );
    $expected_domain_pos = strlen($page_link_components['host']) - strlen('.'.$page_host);
    return  $domain_last_pos !== $expected_domain_pos; // check if the url host is a subdomain
  }// EO link_is_external
}// EO Crawler class 