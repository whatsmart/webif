<?php
require_once("../webif/include/Http.php");
/*
function handleMessage($message, $args) {
        var_dump($message);
}
$parser = new HttpParser();
//$s = "CONNECTION /device HTTP/1.2\r\ncontent-length: 18\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n4\r\ncdef\r\n0\r\n\r\n";
//$s = "CONNECTION /device HTTP/1.2\r\ncontent-length: 18\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n4\r\ncdef\r\n0\r\n\r\nCONNECTION /device HTTP/1.2\r\ncontent-length: 18\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n4\r\ncdef\r\n0\r\n\r\n";

$parser->setFinishCallback("handleMessage", null);

$s = "CONNECTION /device HTTP/1.2\r";
$parser->parse($s);
sleep(1);
$s = "\ncontent-length: 18";
$parser->parse($s);
sleep(1);
$s = "\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n4\r\ncdef\r\n0\r\nmyname: fadsofijpoaifjposadijf\r\n\r\n";
$parser->parse($s);

$s = "GET /device HTTP/2.";
$parser->parse($s);
sleep(1);
$s = "2\r\ncontent-length: 48\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n6\r\ncdeffg\r\n0\r\n\r\n";
$parser->parse($s);
*/

$hb = new HttpBuilder(HttpBuilder::RESPONSE);
$hb->setResponseLine("302", "sfowje fawoijfo fwoeif");
$hb->setHeader("content-encoding", "gzip");
$hb->setHeader("myname", "foweiuafhpoihaf");
$hb->setBody("zzzzzzzzxxxxxxxxxxxccccccc\r\n");
echo $hb->getMessage();
