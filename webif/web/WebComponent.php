<?php
require_once(WEBIF_ROOT . "/web/Client.php");

class WebComponent {
    public $clients;
    public $webif;
    public $sock;
    public $name;

    public function __construct($webif) {
        $this->name = "web";
        $this->sock = null;
        $this->clients = [];
        $this->webif = $webif;

        if(($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            exit("fail to create socket\n");
        }

        socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->sock);
        if(socket_bind($this->sock, "0.0.0.0", 80) === false) {
            exit("fail to bind socket\n");
        }
        if(socket_listen($this->sock, 50) === false) {
            exit("fail to connect socket\n");
        }
//var_dump($this->sock);
    }

    public function write($data) {
        socket_write($this->sock, $data);
    }

    public function onEvents($listener, $revents) {
        $sock = socket_accept($this->sock);
        if($sock === false)
            return;
        echo "new client: $sock\n";

        $client = new Client();
        $client->sock = $sock;
        if(!socket_set_nonblock($client->sock)) {
            echo "error setnonblock\n";
        }

        $client->webif = $this->webif;
        $this->clients[] = $client;

        $listener = new IOListener($this->webif->evloop, array($client, "onEvents"), $client, $client->sock, EventLoop::READ);
        $listener->enable();
    }
}
