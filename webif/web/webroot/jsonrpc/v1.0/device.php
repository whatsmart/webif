<?php

require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/web/Router.php");
require_once(WEBIF_ROOT . "/include/JsonRPC.php");

$router = new Router();
$router->setPrefix("/jsonrpc/v1.0");

/*
* 对设备的操作
*/
$router->route("/device", function($req, $conn) {
    $hub = getHub($conn->webif);
    $id = spl_object_hash($req);

    if($req->method == "POST") {
        $rpc = new JsonRPC\RequestDecoder();
        if(!$rpc->decode($req->body)) {
            $resp = new HttpResponse("400", "Bad Request", ["Cache-Control" => "no-cache"], "");
            socket_write($conn->sock, $resp->getMessage());
            $conn->finishRequest();
        } else {
            $ipcReq = new HipcRequest("device", $req->body, ["origin" => $id]);
            socket_write($hub->sock, $ipcReq->getMessage());

            $func = function () use ($req, $conn) {
                $data = yield;
                $resp = new HttpResponse("200", "OK", ["Cache-Control" => "no-cache", "Content-Type" => "application/json"], $data);
                socket_write($conn->sock, $resp->getMessage());
                $conn->finishRequest();
            };
            $gen = $func();
            $loop = $conn->webif->evloop;
            $loop->asyncRun($id, $gen, 5);
        }
    } else {
        $resp = new HttpResponse("405", "Method Not Allowed", ["Cache-Control" => "no-cache", "Allow" => "POST"], "");
        socket_write($conn->sock, $resp->getMessage());
        $conn->finishRequest();
    }
});

/*
* 对具体设备的操作
*/
$router->route("/device/<int>", function($req, $conn) {
    $hub = getHub($conn->webif);
    $id = spl_object_hash($req);

    preg_match("/\\/device\\/(\d+)/", $req->path, $match);
    $did = $match[1];

    //检查是否为POST方法
    if($req->method == "POST") {
        $rpc = new JsonRPC\RequestDecoder();
        //检查是否为合法的JsonRPC请求，若不合法，返回Bad Request
        if(!$rpc->decode($req->body)) {
            $resp = new HttpResponse("400", "Bad Request", ["Cache-Control" => "no-cache"], "");
            socket_write($conn->sock, $resp->getMessage());
            $conn->finishRequest();
        } else {
            //若为合法的JsonRPC请求，将其直接转发给hub
            $ipcReq = new HipcRequest("device/". $did, $req->body, ["origin" => $id]);
            socket_write($hub->sock, $ipcReq->getMessage());

            $func = function () use ($req, $conn) {
                $data = yield;
                $resp = new HttpResponse("200", "OK", ["Cache-Control" => "no-cache", "Content-Type" => "application/json"], $data);

                socket_write($conn->sock, $resp->getMessage());
                $conn->finishRequest();
            };
            $gen = $func();
            $loop = $conn->webif->evloop;
            $loop->asyncRun($id, $gen, 5);
        }
    } else {
        //若不是POST方法，返回Method Not Allowed
        $resp = new HttpResponse("405", "Method Not Allowed", ["Allow" => "POST"], "");
        socket_write($conn->sock, $resp->getMessage());
        $conn->finishRequest();
    }
});

