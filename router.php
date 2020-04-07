<?php

use Config\Routes;
use Config\Config;

class Router { 
    
    public function getRoute($controller, $method, $params) {

        $config = Config::get();

        if ($method == "") {
            $method = 'index';
        }
        
        $routes = Routes::getRoutes();

        if (isset($routes[$controller])) {
            
            switch ($config['app_mode']) {
                case "api":
                    if (method_exists('\App\Controllers\Api\Tests\\' . ucfirst($controller), $method) && is_callable(array('\App\Controllers\Api\Tests\\' . ucfirst($controller), $method))) {

                        try {
                            call_user_func_array(array('\App\Controllers\Api\Tests\\'.ucfirst($controller), $method), $params);
                        } catch (ArgumentCountError $e) {
                            require($config['api_route'] . 'errors/bad_request.php');
                            BadRequest::ArgumentCountError();
                        }

                    } else {
                        require($config['api_route'] . 'errors/bad_request.php');
                        BadRequest::index();
                    }
                    break;
                case "web":
                    require($config['web_route'] . $routes[$controller]);
                    if (method_exists('\App\Controllers\Web\\' . ucfirst($controller), $method) && is_callable(array('\App\Controllers\Web\\' . ucfirst($controller), $method))) {
                        try {
                            call_user_func_array(array(ucfirst($controller), $method), $params);
                        } catch (ArgumentCountError $e) {
                            require($config['web_route'] . 'errors/bad_request.php');
                            BadRequest::ArgumentCountError();
                        }
                        
                    } else {
                        require($config['web_route'] . 'errors/bad_request.php');
                        BadRequest::index();
                    }
                    break;
            }
        } else {
            switch ($config['app_mode']) {
                case "api":
                    require($config['api_route'] . 'errors/bad_request.php');
                    BadRequest::index();
                    break;
                case "web":
                    require($config['web_route'] . 'errors/bad_request.php');
                    BadRequest::index();
                    break;
            }
        }
    }
}