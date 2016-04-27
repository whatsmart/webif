<?php
require_once("include/Component.php");

class WebIF {
    private $components;
    public $loop;

    public function __construct() {
        $this->loop = new EvLoop();
    }

    public function add_component(Component $comp) {
        $this->components[] = $comp;
    }

    public function run() {
        foreach($this->components as $key => $comp) {
            $evio = $this->loop->io($comp->sock , Ev::READ , array($comp, "onEvents"), $this);
            $evio->start();
        }
        $this->loop->run();
    }
}

$webif = new WebIF();

//$chub = new CHub();
//$webif->add_component($chub);
$cweb = new CWeb();
$webif->add_component($cweb);

$webif->run();
