<?php

require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/web/Router.php");
require_once(WEBIF_ROOT . "/include/JsonRPC.php");

$router = new Router();
$router->setPrefix("/jsonrpc/v1.0");

/*
* 对用户的管理和操作
*/
$router->route("/user", function($req, $conn) {
    $hub = getHub($conn->webif);
    $id = spl_object_hash($req);

    if($req->method == "POST") {
        $rpc = new JsonRPC\RequestDecoder();
        if(!$rpc->decode($req->body)) {
            $resp = new HttpResponse("400", "Bad Request", ["Cache-Control" => "no-cache"], "");
            socket_write($conn->sock, $resp->getMessage());
            $conn->finishRequest();
        } else {
            switch($rpc->method) {
                //注册用户
                case "register_user":
                    $username = $rpc->getParam("username");
                    $password = $rpc->getParam("password");
                    echo $username . $password;
                    break;
            }
        }
    } else {
        $resp = new HttpResponse("405", "Method Not Allowed", ["Cache-Control" => "no-cache", "Allow" => "POST"], "");
        socket_write($conn->sock, $resp->getMessage());
        $conn->finishRequest();
    }
});
