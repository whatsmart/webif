<?php
require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/include/Hipc.php");
require_once(WEBIF_ROOT . "/web/Router.php");

require_once(WEBIF_ROOT . "/web/webroot/jsonrpc.php");

//$message, $client
$router = new Router();

function getHub($webif) {
    foreach($webif->components as $comp) {
        if($comp->name == "hub") {
            return $comp;
        }
    }
}

$router->route("/device", function($message, $client, $method, $args) {
    $hub = getHub($client->webif);
    $req = new HipcRequest("device", $message->getBody());
    socket_write($hub->sock, $req->getMessage());
});




/*
//$message, $client

require_once(WEBIF_ROOT . "/include/Http.php");
//var_dump($client);


*/
