<?php
use Phalcon\Mvc\Micro\Collection as MicroCollection;
require_once 'FireController.php';

$di = new \Phalcon\DI\FactoryDefault();

$di->set('url', function(){
  $url = new \Phalcon\Mvc\Url();
  $url->setBaseUri('/api/v1');
  return $url;
},true);

$di->set('redis', function(){
  $redis = new Redis();
  $redis->pconnect('127.0.0.1');
  return $redis;
},true);
$app = new \Phalcon\Mvc\Micro();
$app->setDI($di);

$mc = new MicroCollection();
$mc->setHandler("FireController", true);
//Use the method 'index' in PostsController
$mc->get('(/.*)*', 'get');
$mc->delete('(/.*)*', 'delete');
$mc->post('(/.*)*', 'create');
$mc->put('(/.*)*', 'create'); //same as create
$app->mount($mc);

$app->notFound(function() use ($app) {
  $app->response->setStatusCode(404, 'Not Found')->sendHeaders();
});

try {
  $app->handle();
} catch (\Exception $e) {
  $app->response->setStatusCode(500, 'BomBom')->setContent($e->getMessage())->send();
}
