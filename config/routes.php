<?php
use MyCrawler\CurlMultiCrawler;
//use Phalcon\Config;

$app->get('/api/crawler', function() use ($app, $config){

  $url = $app->request->getQuery('url');

  // make sure the url is provided
  $url_parsed = parse_url($url);
  $max_curls = 10;

  if($url_parsed){
    if($url_parsed["host"] == "agencyanalytics.com"){
      $max_curls = 200;
    } 
    if ( !isset($url_parsed["scheme"]) )
      {
        $url = "http://{$url}";
      }
  }

  if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
    echo json_encode(array("error"=>"Invalid url!"));  
  }
  else{

    // only allow api call from our domain
    header('Access-Control-Allow-Origin: '.$config['allowOriginUrls']);
    header('Content-Type: application/json');

    $crawler = new CurlMultiCrawler($max_curls, true);
    $crawler->init_crawl_link($url);
    echo json_encode($crawler->getPageCrawlData());

    //$crawler->dump($crawler->getPageCrawlData());

    // output what is already saved for faster developement
    //sleep(1);
    // echo file_get_contents( API_PATH . "/config/output.json");
  }
});