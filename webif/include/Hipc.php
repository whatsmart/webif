<?php

class HipcMessage {
    public $version;
    public $headers;
    public $body;

    public function __construct() {
        $this->headers = null;
        $this->body = "";
        $this->version = "1.0";
    }

    public function setVersion($version) {
        $this->version = $version;
    }

    public function getVersion() {
        return $this->version;
    }

    public function setHeader($field, $value) {
        $this->headers[$field] = $value;
    }

    public function getHeader($field) {
        foreach($this->headers as $key => $value) {
            if(!strcasecmp($key, $field)) {
                return $value;
            }
        }

        return null;
    }

    public function setBody($body) {
        $this->body = $body;
    }

    public function getBody() {
        return $this->body;
    }
}

class HipcRequest extends HipcMessage {
    public $res;

    public function __construct($res = "", $body = "", $headers = [], $version = "1.0") {
        $this->version = $version;
        $this->res = $res;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function setResource($res) {
        $this->res = $res;
    }

    public function getResource() {
        return $this->res;
    }

    public function getMessage() {
        $msg = "";

        $this->setHeader("length", strval(strlen($this->body)));
        $this->setHeader("checksum", strval(crc32($this->body)));

        $msg .= "HIPC/" . $this->version . " request " . $this->res . "\r\n";
        foreach($this->headers as $key => $value) {
            $msg .= $key . ": " . $value . "\r\n";
        }
        $msg .= "\r\n" . $this->body;

        return $msg;
    }
}

class HipcResponse extends HipcMessage {
    public $dest;

    public function __construct($dest = "", $body = "", $headers = [], $version = "1.0") {
        $this->version = $version;
        $this->dest = $dest;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function setDest($dest) {
        $this->dest = $dest;
    }

    public function getDest() {
        return $this->dest;
    }

    public function getMessage() {
        $msg = "";

        $this->setHeader("length", strval(strlen($this->body)));
        $this->setHeader("checksum", strval(crc32($this->body)));

        if($this->dest) {
            $msg .= "HIPC/" . $this->version . " response " . $this->dest . "\r\n";
        } else {
            $msg .= "HIPC/" . $this->version . " response\r\n";
        }
        foreach($this->headers as $key => $value) {
            $msg .= $key . ": " . $value . "\r\n";
        }
        $msg .= "\r\n" . $this->body;

        return $msg;
    }
}

class HipcParser {
    const STATE_FIRST_LINE  = 1;
    const STATE_HEADERS     = 2;
    const STATE_BODY        = 3;
    const STATE_FINISHED    = 4;

    const PARSING           = 1;
    const FINISHED          = 2;

    const REQUEST_LINE_RE = "/HIPC\\/(\\d+\\.\\d+) +request +(.*)/";
    const RESPONSE_LINE_RE = "/HIPC\\/(\\d+\\.\\d+) +response(( +(.*))|())?/";

    private $state;
    private $message;
    private $buffer;

    private $finish_callback;
    private $finish_args;

    public function __construct() {
        $this->state = self::STATE_FIRST_LINE;
        $this->message = null;
        $this->buffer = "";
        $this->finish_callback = null;
        $this->finish_args = null;
    }

    public function reset() {
        $this->state = self::STATE_FIRST_LINE;
        $this->message = null;
        $this->buffer = "";
    }

    public function setFinishCallback($callback, $args) {
        $this->finish_callback = $callback;
        $this->finish_args = $args;
    }

    public function parseFirstLine() {
        $num = preg_match_all("/\r\n/", $this->buffer);

        for ($i=0;$i<$num; $i++) {
            $p = strpos($this->buffer, "\r\n");
            $line = substr($this->buffer, 0, $p);
            $this->buffer = substr($this->buffer, $p+2, strlen($this->buffer)-$p-2);

            if(preg_match(self::REQUEST_LINE_RE, $line, $match)) {
                $this->message = new HipcRequest();
                $this->message->version = $match[1];
                $this->message->res = $match[2];

                return self::FINISHED;
            } else if(preg_match(self::RESPONSE_LINE_RE, $line, $match)) {
                $this->message = new HipcResponse();
                $this->message->version = $match[1];
                $this->message->dest = $match[4];

                return self::FINISHED;
            }
        }

        return self::PARSING;
    }

    public function parseHeaders() {
        $num = preg_match_all("/\r\n/", $this->buffer);

        for ($i=0;$i<$num; $i++) {
            $p = strpos($this->buffer, "\r\n");
            $line = substr($this->buffer, 0, $p);
            $this->buffer = substr($this->buffer, $p+2, strlen($this->buffer)-$p-2);

            if(strlen($line) == 0) {
                return self::FINISHED;
            }

            if(($c = strpos($line, ":")) != false) {
                $name = trim(substr($line, 0, $c));
                $value = trim(substr($line, $c+1, strlen($line)-1));
                $this->message->setHeader($name, $value);
            }
        }

        return self::PARSING;
    }

    public function parseBody() {
        $len = $this->message->getHeader("length");

        if(strlen($this->buffer) >= $len) {
            $this->message->body = substr($this->buffer, 0, $len);
            $this->buffer = substr($this->buffer, $len);
            return self::FINISHED;
        }
    }

    public function parse($data) {
        $this->buffer .= $data;

        if($this->state == self::STATE_FIRST_LINE) {
            switch($this->parseFirstLine()) {
                case self::FINISHED:
                    $this->state = self::STATE_HEADERS;
            }
        }
        if($this->state == self::STATE_HEADERS) {
            switch($this->parseHeaders()) {
                case self::FINISHED:
                    $this->state = self::STATE_BODY;
            }
        }
        if($this->state == self::STATE_BODY) {
            if(($ecs = $this->message->getHeader("transfer-encoding")) != null) {
                $this->chunked = true;
                $this->chunk_state = self::CHUNK_STATE_SIZE;
            }
            switch($this->parseBody()) {
                case self::FINISHED:
                    $this->state = self::STATE_FINISHED;
            }
        }
        if($this->state == self::STATE_FINISHED) {
            if($this->finish_callback) {
                call_user_func($this->finish_callback, $this->message, $this->finish_args);
            }

            if(strlen($this->buffer) > 0) {
                $buf = $this->buffer;
                $this->reset();
                $this->parse($buf);
            } else {
                $this->reset();
            }
        }
    }
}
