<?php
require_once(WEBIF_ROOT . "/web/Connection.php");

class WebComponent {
    public $connections;
    public $webif;
    public $sock;
    public $name;

    public function __construct($webif) {
        $this->name = "web";
        $this->sock = null;
        $this->connections = [];
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

    public function onEvents($listener, $revents) {
        if($revents & EventLoop::READ) {
            $sock = socket_accept($this->sock);
            if($sock === false)
                return;
            echo "new connection: $sock\n";

            $connection = new Connection();
            $connection->sock = $sock;
            if(!socket_set_nonblock($connection->sock)) {
                echo "error setnonblock\n";
            }

            $connection->webif = $this->webif;
            $this->connections[] = $connection;

            $listener = new IOListener($this->webif->evloop, array($connection, "onEvents"), null, $connection->sock, EventLoop::READ);
            $listener->enable();
        }
    }
}
