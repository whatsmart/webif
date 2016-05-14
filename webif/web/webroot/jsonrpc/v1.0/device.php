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
            switch($rpc->method) {
                case "get_devices":
                    $ipcReq = new HipcRequest("device", $req->body, ["origin" => $id]);
                    socket_write($hub->sock, $ipcReq->getMessage());

                    $func = function ($args) use ($req, $conn) {
                        $resp = new HttpResponse("200", "OK", ["Cache-Control" => "no-cache", "Content-Type" => "application/json"], $args);
//echo $resp->getMessage();
                        socket_write($conn->sock, $resp->getMessage());
                        $conn->finishRequest();
                    };

                    $loop = $conn->webif->evloop;
                    $loop->asyncRun($id, $func, 20);
                    break;
                default:
                    $rb = JsonRPC\ErrorEncoder::encode(1, "unsupported rpc method", null, $rpc->id);
                    $resp = new HttpResponse("200", "OK", ["Cache-Control" => "no-cache", "Content-Type" => "application/json"], $rb);
//echo $resp->getMessage();
                    socket_write($conn->sock, $resp->getMessage());
                    $conn->finishRequest();
            }

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

    if($req->method == "POST") {
        $rpc = new JsonRPC\RequestDecoder();
        if(!$rpc->decode($req->body)) {

            $resp = new HttpResponse("400", "Bad Request", ["Cache-Control" => "no-cache"], "");
            socket_write($conn->sock, $resp->getMessage());
            $conn->finishRequest();

        } else {

            if(in_array($rpc->method, ["get_name", "set_name", "get_position", "set_position"])) {
                $ipcReq = new HipcRequest("device/". $did, $req->body, ["origin" => $id]);
                socket_write($hub->sock, $ipcReq->getMessage());

                $func = function ($args) use ($req, $conn) {
                    $resp = new HttpResponse("200", "OK", ["Cache-Control" => "no-cache", "Content-Type" => "application/json"], $args);

                    socket_write($conn->sock, $resp->getMessage());
                    $conn->finishRequest();
                };

                $loop = $conn->webif->evloop;
                $loop->asyncRun($id, $func, 20);
            } else {
                $ipcResp = JsonRPC\ErrorEncoder::encode(1, "unsupported rpc method", null, $rpc->id);
                $resp = new HttpResponse("200", "OK", ["Cache-Control" => "no-cache", "Content-Type" => "application/json"], $ipcResp);

                socket_write($conn->sock, $resp->getMessage());
                $conn->finishRequest();
            }

        }
    } else {
        $resp = new HttpResponse("405", "Method Not Allowed", ["Cache-Control" => "no-cache", "Allow" => "POST"], "");
        socket_write($conn->sock, $resp->getMessage());
        $conn->finishRequest();
    }
});

