<?php
// Set up the database service

$di->set('db', function() use ($config){
  return new \Phalcon\Db\Adapter\Pdo\Mysql(array(
    "host"     => $config['database']['host'],
    "username" => $config['database']["username"],
    "password" => $config['database']["password"],
    "dbname"   => $config['database']["dbname"]    
  ));
});