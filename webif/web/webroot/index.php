<?php
require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/include/Hipc.php");
require_once(WEBIF_ROOT . "/web/Router.php");
require_once(WEBIF_ROOT . "/include/HttpUtil.php");

require_once(WEBIF_ROOT . "/web/webroot/jsonrpc/v1.0/device.php");
require_once(WEBIF_ROOT . "/web/webroot/jsonrpc/v1.0/control.php");
require_once(WEBIF_ROOT . "/web/webroot/jsonrpc/v1.0/message.php");
require_once(WEBIF_ROOT . "/web/webroot/jsonrpc/v1.0/user.php");

$router = new Router();

function getHub($webif) {
    foreach($webif->components as $comp) {
        if($comp->name == "hub") {
            return $comp;
        }
    }
}

$router->route("/", function($req, $conn) {
    $resp = new HttpResponse("200", "OK", [], "hello world");
    socket_write($conn->sock, $resp->getMessage());
    $conn->finishRequest();
});
