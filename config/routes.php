<?php
use MyCrawler\CurlMultiCrawler;
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

    $crawler = new CurlMultiCrawler(200, true);
    $crawler->init_crawl_link($url);
    echo json_encode($crawler->getPageCrawlData());

    // $crawler->dump($crawler->getPageCrawlData());

    // output what is already saved for faster developement
    //sleep(1);
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