<?php
require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/include/Hipc.php");
require_once(WEBIF_ROOT . "/web/Router.php");
require_once(WEBIF_ROOT . "/include/HttpUtil.php");

require_once(WEBIF_ROOT . "/web/webroot/jsonrpc.php");

/*
* response must write finished in one time, because there is no lock for $sock.
*/

$router = new Router();

function getHub($webif) {
    foreach($webif->components as $comp) {
        if($comp->name == "hub") {
            return $comp;
        }
    }
}

$router->route("/device", function($message, $method, $conn) {
    $hub = getHub($conn->webif);
    $req = new HipcRequest("device", $message->body, ["origin" => spl_object_hash($message)]);
    socket_write($hub->sock, $req->getMessage());

    $func = function ($args) use ($message, $method, $conn) {
        $resp = new HttpResponse("200", "OK", [], $args);
        socket_write($conn->sock, $resp->getMessage());
        $conn->finishRequest();
    };

    $loop = $conn->webif->evloop;
    $loop->asyncRun(spl_object_hash($message), 20, $func);
});




/*
//$message, $client

require_once(WEBIF_ROOT . "/include/Http.php");
//var_dump($client);


*/
