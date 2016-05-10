<?php
require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/include/EventLoop.php");
require_once(WEBIF_ROOT . "/web/Router.php");
require_once(WEBIF_ROOT . "/web/webroot/index.php");

class Client {
    public $sock;
    public $parser;
    public $webif;
    public $watcher;
    public $conns;

    public function __construct() {
        $this->parser = new HttpParser();
        $this->parser->setFinishCallback(array($this, "handleMessage"), $this);
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
    }

    public function handleMessage($message, $client) {
        if(is_a($message, "HttpRequest")) {
            //需要实现http pipeline, $this->conns保存请求信息
            $path = parse_url($message->uri)["path"];
            $file = WEBIF_ROOT . "/web/webroot" . $path;
    //        echo $file . PHP_EOL;
            if(is_file($file)){
                $mime = mime_content_type($file);
                $fp = fopen($file, "r");
                $contents = fread($fp, filesize($file));
                fclose($fp);
                $resp = new HttpResponse("200", "OK", ["Content-Type" => $mime], $contents);
                socket_write($this->sock, $resp->getMessage());
            } else {
                if(!Router::dispatch($message, $client, $message->getMethod(), $path)) {
                    $resp = new HttpResponse("404", "Not Found", [], "not found");
                    socket_write($this->sock, $resp->getMessage());
                }
            }
        }
    }
}

