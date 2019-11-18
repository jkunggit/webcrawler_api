<?php
use MyCrawler\Crawler;
//use Phalcon\Config;

$app->get('/api/crawler', function() use ($app, $config){

  $url = $app->request->getQuery('url');

  // only allow api call from our domain
  header('Access-Control-Allow-Origin: '.$config['allowOriginUrls']);
  header('Content-Type: application/json');
  
  // make sure the url is provided
  if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
    echo json_encode(array("error"=>"Invalid url!"));  
  }
  else{
    //$start = "https://agencyanalytics.com";
    //$start = "https://stackoverflow.com";

    $crawler = new Crawler(50, true);
    $crawler->init_crawl_link($url);
    echo json_encode($crawler->getPageCrawlData());  
    // echo file_get_contents( API_PATH . "/config/output.json");
  }
});

// Retrieves all robots
$app->get('/api/robots', function() use ($app){
  $phql = "SELECT * FROM Store\Toys\Robots ORDER BY name";
  $robots = $app->modelsManager->executeQuery($phql);

  $data = array();
  foreach ($robots as $robot) {
      $data[] = array(
          'id' => $robot->id,
          'name' => $robot->name,
      );
  }
  echo json_encode($data);
});