<?php
// bootstrap
use Phalcon\Mvc\Micro;
use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
use Phalcon\Http\Response;

error_reporting(E_ALL); // Error engine - always TRUE!

ini_set('ignore_repeated_errors', TRUE); // always TRUE

ini_set('display_errors', TRUE); // Error display - FALSE only in production environment or real server

define('BASE_PATH', dirname(__DIR__));
define('API_PATH', BASE_PATH . '/api');

$app = new \Phalcon\Mvc\Micro();

include API_PATH . '/config/config.php';

include API_PATH . '/config/loader.php';

// echo "dir is ".__DIR__."<br />";exit;
$di = new \Phalcon\DI\FactoryDefault();

$di->setShared('config', $config);
//(new \Phalcon\Debug())->listen();

include API_PATH . '/config/services.php';

//Create and bind the DI to the application
$app = new \Phalcon\Mvc\Micro($di);
//Retrieves all robots


include API_PATH . '/config/routes.php';

$app->handle();
