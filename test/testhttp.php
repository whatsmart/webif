<?php
require_once("../webif/include/Http.php");

function handleMessage($message, $args) {
        var_dump($message);
}
$parser = new HttpParser();
//$s = "CONNECTION /device HTTP/1.2\r\ncontent-length: 18\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n4\r\ncdef\r\n0\r\n\r\n";
//$s = "CONNECTION /device HTTP/1.2\r\ncontent-length: 18\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n4\r\ncdef\r\n0\r\n\r\nCONNECTION /device HTTP/1.2\r\ncontent-length: 18\r\ntransfer-encoding: chunked\r\n\r\n2\r\nab\r\n4\r\ncdef\r\n0\r\n\r\n";

$parser->setFinishCallback("handleMessage", null);

$s = "CONNECTION /device HTTP/1.";
$parser->parse($s);
sleep(1);
$s = "2\r\ncontent-length: 12\r\n\r\nsdfghjkuytreffffffffffff";
$parser->parse($s);




$hb = new HttpRequest("GET", "device", ["test" => "ffffff"], "bbbbbbbbbbbbbb", "1.0");
$hb->setRequestLine("GET", "control", "1.1")
->setHeaders(["hhh" => "hhhhhhhh"])
->setHeader("content-encoding", "gzip")
->setHeader("myname", "foweiuafhpoihaf")
->setBody("zzzzzzzzxxxxxxxxxxxccccccc\r\n");
echo $hb->getMessage();

$hb = new HttpResponse("301", "redirect", ["res" => "resresresres"], "rrrrrrrrrrrrrrrrr", "1.0");
$hb->setResponseLine("200", "OK", "1.1")
->setHeaders(["hhh" => "hhhhhhhh"])
->setHeader("content-encoding", "gzip")
->setHeader("myname", "foweiuafhpoihaf")
->setBody("hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh\r\n");
echo $hb->getMessage();

