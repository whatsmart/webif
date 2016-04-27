<?php
require_once("Http.php");

abstract class Component {
    public $name;
    public $sock;

}

class CHub extends Component {
    public function __construct() {
        $this->name = "hub";

        if(($this->sock = socket_create(AF_UNIX, SOCK_STREAM, 0)) === false) {
            exit("fail to create socket\n");
        }
        if(socket_connect($this->sock, "/tmp/hub_sock") === false) {
            exit("fail to connect socket\n");
        }
    }

    public function write($data) {
        socket_write($this->sock, $data);
    }

    public function onEvents($watcher, $revents) {
        
    }
}

class Client {
    public $sock;
    public $parser;
    public $webif;

    public function __construct() {
        $this->parser = new HttpParser();
    }

    public static function onEvents($watcher, $revents) {
        $client = $watcher->data;
//        if($revents & Ev::READ) {
//            echo "data come\n";
            $data = "";
            $tmp = socket_read($client->sock, 1024);
            echo $tmp;
            if ($tmp === false)
                socket_close($client->sock);
//        }
    }
}

class CWeb extends Component {
    public $clients;

    public function __construct() {
        $this->name = "web";
        $this->clients = array();

        if(($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            exit("fail to create socket\n");
        }
        if(socket_bind($this->sock, "127.0.0.1", 80) === false) {
            exit("fail to bind socket\n");
        }
        if(socket_listen($this->sock, 50) === false) {
            exit("fail to connect socket\n");
        }
    }

    public function write($data) {
        socket_write($this->sock, $data);
    }

    public function onEvents($watcher, $revents) {
        $sock = socket_accept($this->sock);
        echo "new client: $sock\n";

        $webif = $watcher->data;

        $client = new Client();
        $client->sock = $sock;
        $client->webif = $webif;

        $this->clients[] = $client;

        $evio = $webif->loop->io($client->sock , Ev::READ , array("Client", "self::onEvents"), $client);
        $evio->start();
    }
}
