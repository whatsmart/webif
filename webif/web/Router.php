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
        $path = implode("\\/", $paths);

        self::$routes[$path]["methods"] = $methods;
        self::$routes[$path]["callback"] = $callback;
    }

    public function dispatch($req, $method, $connection, $path) {
        $len = 0;
        $r = null;
        foreach(self::$routes as $key => $value) {
            if(preg_match("/" . $key . "/", $path, $match)) {
                if(strlen($match[0]) > $len) {
                    $len = strlen($match[0]);
                    $r = $key;
                }
            }
        }

        if($r != null) {
            preg_match("/" . $r . "/", $path);
            $callback = self::$routes[$r]["callback"];

            if($ms = self::$routes[$r]["methods"]) {
                foreach($ms as $m) {
                    if(!strcasecmp($m, $method)) {
                        $callback($req, $method, $connection);
                        return true;
                    }
                }
                return false;
            } else {
                $callback($req, $method, $connection);
                return true;
            }
        }
        return false;
    }
}
