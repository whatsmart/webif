<?php
require_once("../webif/include/Hipc.php");

function handleMessage($message, $args) {
        var_dump($message);
}

$parser = new HipcParser();
$parser->setFinishCallback("handleMessage", null);

$s = "HIPC/1.0 reques";
$parser->parse($s);
sleep(1);
$s = "t /device\r\nlength: 8\r\nchecksum: 123\r\norigin: 1@webif\r\n\r\n{\"json\"}HIPC";
$parser->parse($s);

$s = "/1.0 request /device\r\nlength: 8\r\nchecksum: 123\r\norigin: 1@webif\r\n\r\n{\"json\"}";
$parser->parse($s);



$req = new HipcRequest("/device", ["myname" => "sdafsdffsdf"], "{dlkajfowijf}");
echo $req->getMessage();

$res = new HipcResponse("", ["myname" => "sdafsdffsdf"], "{dlkajfowijf}");
echo $res->getMessage();

