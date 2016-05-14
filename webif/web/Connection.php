<?php
require_once(WEBIF_ROOT . "/include/Http.php");
require_once(WEBIF_ROOT . "/include/EventLoop.php");
require_once(WEBIF_ROOT . "/web/Router.php");
require_once(WEBIF_ROOT . "/web/webroot/index.php");

class Connection {
    public $sock;
    public $parser;
    public $webif;
    public $reqs;

    public function __construct() {
        $this->parser = new HttpParser();
        $this->parser->setFinishCallback(array($this, "handleMessage"), null);
        $this->reqs = [];
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

    public function finishRequest() {
        array_shift($this->reqs);
        if(count($this->reqs) > 0) {
            $this->handleRequest();
        }
    }

    public function handleRequest() {
        if(!Router::dispatch($this->reqs[0], $this, $this->reqs[0]->path)) {
            $resp = new HttpResponse("404", "Not Found", [], "not found");
            socket_write($this->sock, $resp->getMessage());
            array_shift($this->reqs);
        }
    }

    public function handleMessage($message, $args) {
        if(is_a($message, "HttpRequest")) {
            $path = parse_url($message->uri)["path"];
            $file = WEBIF_ROOT . "/web/webroot" . $path;
            //echo $file. PHP_EOL;
            if(is_file($file)){
                $mime = mime_content_type($file);
                $fp = fopen($file, "r");
                $contents = fread($fp, filesize($file));
                fclose($fp);
                $resp = new HttpResponse("200", "OK", ["Content-Type" => $mime], $contents);
                socket_write($this->sock, $resp->getMessage());
            } else {
                $req = new Request($message);
                $this->reqs[] = $req;
                //http pipeline，先将请求添加到队列中，如果队列中只有一个请求则立即执行，否则不执行。
                //当正在执行的请求处理完成后，会依次执行后面的请求。
                if(count($this->reqs) == 1) {
                    $this->handleRequest();
                }
            }
        }
    }
}

