<?php

//require_once(__DIR__ . '/config/config.php');

use Config\Config;

$config = new Config();
$config = $config->get();
$request = $_SERVER['REQUEST_URI'];
$base_request = basename($request);
$uri = explode('/', $request);

if ($uri[1] == $config['base_path']) {

  if (isset($uri[2])) {
    $controller = $uri[2];
  } else {
    $controller = "";
  }
  if (isset($uri[3])) {
    $method = $uri[3];
  } else {
    $method = "";
  }
} else {

  if (isset($uri[1])) {
    $controller = $uri[1];
  } else {
    $controller = "";
  }

  if (isset($uri[2])) {
    $method = $uri[2];
  } else {
    $controller = "";
  }
}

$params = [];

foreach ($_POST as $param) {
  array_push($params, $param);
}

Router::getRoute($controller, $method, $params);
