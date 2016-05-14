<?php
require_once(WEBIF_ROOT . "/include/Hipc.php");
require_once(WEBIF_ROOT . "/include/EventLoop.php");

class HubComponent {
    public $parser;
    public $sock;
    public $name;
    public $webif;    

    public function __construct($webif) {
        $this->name = "hub";
        $this->webif = $webif;
        $this->parser = new HipcParser();
        $this->parser->setFinishCallback(array($this, "handleMessage"), $this);

        if(($this->sock = socket_create(AF_UNIX, SOCK_STREAM, 0)) === false) {
            exit("fail to create socket\n");
        }
        if(!socket_set_nonblock($this->sock)) {
            echo "error setnonblock\n";
        }
        if(socket_connect($this->sock, "/tmp/hub_sock") === false) {
            exit("fail to connect socket\n");
        }

        $req = new HipcRequest("component", '{"jsonrpc": "2.0", "method": "register_component", "params": {"name": "webif", "type": "WebInterface"}, "id": 1}');
        socket_write($this->sock, $req->getMessage());
//var_dump($this->sock);
    }

    public function write($data) {
        socket_write($this->sock, $data);
    }

    public function onEvents($listener, $revents) {
        $data = "";
        if($revents & EventLoop::READ) {
            $tmp = socket_read($this->sock, 1024, PHP_BINARY_READ);
            //有时返回空字符串
            if ($tmp == false) {
//              echo "remote closed\r\n";
                socket_close($this->sock);
                $listener->destroy();
                return;
            } else {
                $data = $tmp;
                while($tmp = socket_read($this->sock, 1024, PHP_BINARY_READ)) {
                    $data .= $tmp;
                }
                $this->parser->parse($data);
            }
        }
        if($revents & EventLoop::WRITE) {
            //@todo write async
        }
    }

    public function handleMessage($message, $client) {
//var_dump($message);
        if($message->getDest()) {
            $this->webif->evloop->asyncCall($message->getDest(), $message->getBody());
        }
    }
}
