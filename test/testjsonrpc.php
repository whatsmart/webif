<?php

require_once("../webif/include/JsonRPC.php");

$req = new JsonRPC\RequestDecoder();
if(!$req->decode('{"jsonrpc": "2.0", "method": "zxcv", "params": {"device": ["qwe", "asd", "zxc"], "nima": "sdfg"}, "id": 123}')) {
    echo $req->errcode . ": " . $req->errmsg . PHP_EOL;
}
echo $req->jsonrpc . PHP_EOL;
echo $req->method . PHP_EOL;
echo $req->id . PHP_EOL;
//var_dump($this->params);
echo $req->getParam("device->2") . PHP_EOL;

echo "*********" . PHP_EOL;

$res = new JsonRPC\ResponseDecoder();
//$ret = $res->decode('{"jsonrpc": "2.0", "result": "zxcv", "error": {"code": 123, "message": "error message"}, "id": 123}');
//$ret = $res->decode('{"jsonrpc": "2.0", "result": "zxcv", "id": 123}');
$ret = $res->decode('{"jsonrpc": "2.0", "error": {"code": 123, "message": "error message", "data": "vvvvvvvvvvvvv"}, "id": 123}');
if(-1 == $ret) {
    echo $res->jsonrpc . PHP_EOL;
    echo $res->getErrorCode() . ": " . $res->getErrorMsg() . $res->getErrorData() . PHP_EOL;
    echo $res->id . PHP_EOL;
} else if (1 == $ret) {
    echo $res->jsonrpc . PHP_EOL;
    echo $res->result . PHP_EOL;
    echo $res->id . PHP_EOL;
} else {
    echo $res->errcode . ": " . $res->errmsg . PHP_EOL;
}

echo "*********" . PHP_EOL;

echo JsonRPC\RequestEncoder::encode("set_name", ["1" => 2], 1, "2.0") . PHP_EOL;

echo "*********" . PHP_EOL;

echo JsonRPC\ResultEncoder::encode(["1" => 2], 1, "2.0") . PHP_EOL;

echo "*********" . PHP_EOL;

echo JsonRPC\ErrorEncoder::encode(123, "error message", "error data", 234) . PHP_EOL;
