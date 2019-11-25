<?php
use Phalcon\Loader;

// Use Loader() to autoload our model
$loader = new Loader();

$loader->registerNamespaces(
  [
    'Store\Toys' => API_PATH . '/models/',
  ]
);

$loader->registerClasses(
  [
    'MyCrawler\CurlMultiCrawler' => API_PATH .'/library/CurlMultiCrawler.php',
  ]
);


$loader->register();