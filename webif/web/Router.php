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

    public function dispatch($req, $connection, $path) {
        $method = $req->method;
        $r = null;
        $path = preg_replace("/\\/+/", "/", $path);
        $ps = explode("/", $path);
        array_shift($ps);

        foreach(self::$routes as $key => $value) {
            $pts = explode("\\/", $key);
            array_shift($pts);
            if(count($ps) == count($pts)) {
                $c = count($pts);
                for($i=0; $i<$c-1; $i++) {
                    if(!preg_match("/" . $pts[$i] . "/", $ps[$i])) {
                        break;
                    }
                }
                if($i == $c - 1) {
                    if($pts[$c-1] != ""){
                        if(preg_match("/".$pts[$c-1]."/", $ps[$c-1])) {
                            $r = $key;
                        }
                    } else if($pts[$c-1] == "") {
                        if($ps[$c-1] == "") {
                            $r = $key;
                        }
                    }
                }
            }
        }

        if($r != null) {
            $callback = self::$routes[$r]["callback"];

            if($ms = self::$routes[$r]["methods"]) {
                foreach($ms as $m) {
                    if(!strcasecmp($m, $method)) {
                        $callback($req, $connection);
                        return true;
                    }
                }
                return false;
            } else {
                $callback($req, $connection);
                return true;
            }
        }
        return false;
    }
}
