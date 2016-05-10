<?php

class Router {
    public static $routes = [];

    public $prefix;

    public function __construct() {
        $this->prefix = "";
    }

    public function setPrefix($prefix) {
        $this->prefix = $prefix;
    }

    public function route($pattern, $callback, $methods = []) {
        $paths = explode("/", $this->prefix . $pattern);
        $paths = preg_replace("/\\<int\\>/", "(\\d+)", $paths);
        $paths = preg_replace("/\\<str\\>/", "(\\S+)", $paths);
        $uri = implode("\\/", $paths);

        self::$routes[$uri]["methods"] = $methods;
        self::$routes[$uri]["callback"] = $callback;
    }

    public function dispatch($message, $client, $method, $uri) {
        $len = 0;
        $r = null;
        foreach(self::$routes as $key => $value) {
            if(preg_match("/" . $key . "/", $uri, $match)) {
                if(strlen($match[0]) > $len) {
                    $len = strlen($match[0]);
                    $r = $key;
                }
            }
        }

        if($r != null) {
            preg_match("/" . $r . "/", $uri, $match);
            $callback = self::$routes[$r]["callback"];
            array_shift($match);

            if($ms = self::$routes[$r]["methods"]) {
                foreach($ms as $m) {
                    if(!strcasecmp($m, $method)) {
                        $callback($message, $client, $method, $match);
                        return true;
                    }
                }
                return false;
            } else {
                $callback($message, $client, $method, $match);
                return true;
            }
        }
        return false;
    }
}
