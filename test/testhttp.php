<?php
require_once("../webif/include/Http.php");

$parser = new HttpParser();
$s = "CONNECTION fpwaeoijwafijopfwef HTTP/1.2\r\ncontent-length: 23442\r\n\r\nsdfHTTP/1.1 ";
$parser->parse($s);
