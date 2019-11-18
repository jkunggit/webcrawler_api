<?php
namespace MyCrawler;
use DOMDocument;

/*
 * Crawls any website and print out the json detail info
 * @author: Jim Kung
 * @version 1.0
 * 
 */
class Crawler{
  var $already_crawled_array = [];
  var $max_page_crawls;
  var $page_crawl_count = 0;
  var $allow_recursive_crawl; 
  var $crawling_page_array = [];
  var $verbose = false; // toggle to display test output

  /*
   * Class Constructor
   * 
   * @param int $max_page_crawls -> The maximum number of pages to crawl
   * @param bool $allow_recursive_crawl -> To allow recursively crawl the page links 
   */
  function __construct($max_page_crawls = 5, $allow_recursive_crawl = false){
    $this->max_page_crawls = $max_page_crawls;
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
    $this->crawl_link($url);
    if($this->verbose){ 
      $this->dump($this->crawling_page_array);
      echo json_encode($this->crawling_page_array);
    }
  }

  public function getPageCrawlData(){
    return $this->crawling_page_array;
  }

  /*
   * Crawl the initial url. The url should be an absolute url.
   *
   * @param string $url -> url to crawl
   * @
   */
  private function crawl_link($url){
    if($this->verbose)
      echo "crawl_link: $url, ".$this->page_crawl_count."<BR>";
    
    $doc = $this->get_url_dom_doc($url);
  
    // get the details for the $url 
    $link = $this->absolute_link($url, $url);

    if (!in_array($link, $this->already_crawled_array) && $this->page_crawl_count <= $this->max_page_crawls) {
      //echo "crawl_link: $url, ".$this->page_crawl_count."<BR>";
      $this->page_details($url, $link);
      $this->already_crawled_array[] = $link;
      $this->page_crawl_count++;
    }
    //$this->dump($this->already_crawled_array);
    //$this->dump($this->crawling_array);

    // find all the achor links in the page
    $link_list = $doc->getElementsByTagName("a");

    $crawling_array = [];
    foreach ($link_list as $each_link) {
      $link =  $this->absolute_link($url, $each_link->getAttribute("href"));
      if($link === false){
        continue;
      }    

      if (!in_array($link, $this->already_crawled_array) && $this->page_crawl_count <= $this->max_page_crawls) {
        //echo "link: $link " .$this->page_crawl_count."<BR>";
        $this->already_crawled_array[] = $link;
        $crawling_array[] = $link;
        $this->page_details($url, $link);
        $this->page_crawl_count++;
      } 

      if($this->page_crawl_count >= $this->max_page_crawls){
        break;
      }      
    }// EO foreach
	 
    // crawl each link in the crawling array.
    // $this->dump($crawling_array);
    if($this->page_crawl_count <= $this->max_page_crawls && $this->allow_recursive_crawl){
      foreach ($crawling_array as $site) {
        $this->crawl_link($site); 
      }
    }
  }// EO crawl_link

  /*
   * curl the url and returns a DOM document or an array containing the DOM document and extra data 
   * 
   * @param string $url -> url of the page
   * @param bool $return_data -> return data  
   * @return DOM Doc || array -> DOM document and page count
   */
  private function get_url_dom_doc($url, $return_data = false){
    $doc = new DOMDocument();
  
    $curl_handle = curl_init($url);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_USERAGENT, "My User Agent Name");
    $contents = curl_exec($curl_handle);
    $info = curl_getinfo($curl_handle);
    curl_close($curl_handle);
    //echo "<pre>",print_r($info),"</pre>";
    @$doc->loadHTML($contents);  
    $data = array();

    $data = array(
      "http_code" => $info["http_code"],
      "total_time" => $info["total_time"],
      "avg_word_len" => $this->avg_word_len($contents)
    );

    if($return_data){
      return array("doc" => $doc, "data"=> $data);
    }
    else{
      return $doc;
    }
  }// EO get_url_dom_doc

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
    else if (substr($link, 0, 1) == "#") {
      // "#myanchor"
      $link = $url_components["scheme"]
              . "://"
              . $url_components["host"]
              . $link;
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
      
      // remove paths with url with hashtags
      $link = preg_replace('/#.*/', '', $link);       

      // remove paths with / at the end without any other info such as www.example.com/
      if($link_components["path"] == '/'){
        $link = substr($link, 0, strlen($link)-1);
      }  
    }// EO if link_components path is not empty   

    return $link;  
  }// EO absolute_link

  /*
   * process the page details and store in crawling_page_array
   * 
   * @param string $url -> url of page
   * @param string $link -> a link from the page
   */
  private function page_details($url, $link){

    $docDataArray = $this->get_url_dom_doc($link, true);
    $doc = $docDataArray["doc"];

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
      $images_array[] = $image->getAttribute("src"); 
    }
    
    // get all the current page links
    $link_list = $doc->getElementsByTagName("a");

    $internal_links_array = array();
    $external_links_array = array();
    foreach ($link_list as $each_link) {
      $each_link =  $this->absolute_link($url, $each_link->getAttribute("href"));
      if($this->link_is_external($url, $each_link)){
        $external_links_array[] = $each_link;
      }
      else{
        $internal_links_array[] = $each_link;
      }
    }// EO foreach 

    $page_data_array = array(
      "title" => $page_title, //strlen(trim($page_title)),
      "link" => $link,
      "link_is_external" =>  $this->link_is_external($url, $link),
      "unique_images" => array_values(array_unique($images_array)),
      "unique_internal_links" => array_values(array_unique($internal_links_array)), 
      "unique_external_links" => array_values(array_unique($external_links_array))
    );

    $this->crawling_page_array[] = array_merge($page_data_array, $docDataArray["data"]);
  }// EO page_details

  /*
   * Check if the link is internal or external
   * @param string $url -> url of the current page
   * @param string $link -> a link from the current page
   * @return bool 
   */
  private function link_is_external($page_url, $page_link) {
    $page_host = $w = preg_replace('/^www\./i', '', parse_url($page_url)["host"]); // remove the www prefix from host
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