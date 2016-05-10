<?php
define("WEBIF_ROOT", __DIR__);

require_once("web/WebComponent.php");
require_once("hub/HubComponent.php");
require_once("include/EventLoop.php");

function call() {

}

class WebIF {
    public $components;
    public $evloop;

    public function __construct() {
        $this->evloop = new EventLoop();
    }

    public function add_component($comp) {
        $this->components[] = $comp;
    }

    public function run() {
        $chub = new HubComponent($this);
        $this->add_component($chub);

        $listener = new IOListener($this->evloop, array($chub, "onEvents"), null, $chub->sock, EventLoop::READ);
        $listener->enable();

        $cweb = new WebComponent($this);
        $this->add_component($cweb);

        $listener = new IOListener($this->evloop, array($cweb, "onEvents"), null, $cweb->sock, EventLoop::READ);
        $listener->enable();

        $this->evloop->run();
    }

}

$webif = new WebIF();
$webif->run();
