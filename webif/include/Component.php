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
        $this->parser->setFinishCallback(array($this, "handleMessage"), $this);
    }

    public static function onEvents($watcher, $revents) {
        $client = $watcher->data;
        if($revents & Ev::READ) {
            $tmp = socket_read($client->sock, 1024, PHP_BINARY_READ);
            //有时返回空字符串
            if ($tmp == false) {
echo "remote closed\r\n";
                socket_close($client->sock);
                $watcher->stop();
                return;
            }
            $data = $tmp;

            while($tmp = socket_read($client->sock, 1024, PHP_BINARY_READ)) {
                $data .= $ttt;
            }

            $client->parser->parse($data);
        }
    }

    public function handleMessage($message, $args) {
        $resp = new HttpBuilder(HttpBuilder::RESPONSE);
        $resp->setResponseLine("200", "OK");

        $body = "hello\r\n";

        $resp->setHeader("content-length", intval(strlen($body)));
        $resp->setBody($body);

        socket_write($this->sock, $resp->getMessage());
    }
}

class CWeb extends Component {
    public $clients;
    public $sock;
    public $webif;

    public function __construct() {
        $this->name = "web";
        $this->clients = array();

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
    }

    public function write($data) {
        socket_write($this->sock, $data);
    }

    public static function onEvents($watcher, $revents) {
        $cweb = $watcher->data;
        $sock = socket_accept($cweb->sock);
        if($sock === false)
            return;
//        echo "new client: $sock\n";

        $client = new Client();
        $client->sock = $sock;
        if(!socket_set_nonblock($client->sock)) {
            echo "error setnonblock\n";
        }

        $webif = $cweb->webif;
        $client->webif = $webif;
        $cweb->clients[] = $client;

        $evio = $webif->loop->io($client->sock , Ev::READ , array("Client", "self::onEvents"), $client);
        $evio->start();
        $webif->loop->run();
    }
}
