<?php

require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/web/Router.php");
require_once(WEBIF_ROOT . "/include/JsonRPC.php");

$router = new Router();
$router->setPrefix("/jsonrpc/v1.0");

/*
* 对具体设备的控制
*/
$router->route("/control/<int>", function($req, $conn) {
    $hub = getHub($conn->webif);
    $id = spl_object_hash($req);

    preg_match("/\\/control\\/(\d+)/", $req->path, $match);
    $did = $match[1];

    if($req->method == "POST") {
        $rpc = new JsonRPC\RequestDecoder();
        if(!$rpc->decode($req->body)) {
            $resp = new HttpResponse("400", "Bad Request", ["Cache-Control" => "no-cache"], "");
            socket_write($conn->sock, $resp->getMessage());
            $conn->finishRequest();
        } else {
            $ipcReq = new HipcRequest("control/". $did, $req->body, ["origin" => $id]);
            socket_write($hub->sock, $ipcReq->getMessage());

            //normal response from hub
            $func = function () use ($req, $conn) {
                $data = yield;
                $resp = new HttpResponse("200", "OK", ["Cache-Control" => "no-cache", "Content-Type" => "application/json"], $data);
                socket_write($conn->sock, $resp->getMessage());
                $conn->finishRequest();
            };
            //timeout from hub
            $timeout = function() use ($req, $conn) {
                $resp = new HttpResponse("504", "Gateway Timeout", [], "");
                socket_write($conn->sock, $resp->getMessage());
                 $conn->finishRequest();
            };
            $gen = $func();
            $loop = $conn->webif->evloop;
            $loop->asyncRun($id, $gen, 5, $timeout);
        }
    } else {
        $resp = new HttpResponse("405", "Method Not Allowed", ["Cache-Control" => "no-cache", "Allow" => "POST"], "");
        socket_write($conn->sock, $resp->getMessage());
        $conn->finishRequest();
    }
});

