<?php

class HttpMessage {
    public $headers;
    public $body;

    public function __construct() {
        $this->headers = array();
        $this->body = "";
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

    public function removeHeader($field) {
        foreach($this->headers as $key => $value) {
            if(!strcasecmp($key, $field)) {
                unset($this->headers[$key]);
            }
        }
    }

    public function setBody($body) {
        $this->body = $body;
    }

    public function getBody() {
        return $this->body;
    }
}

class RequestMessage extends HttpMessage {
    public $method;
    public $uri;
    public $version;

    public function __construct() {
        $this->method = "";
        $this->uri = "";
        $this->version = "1.1";

        parent::__construct();
    }

    public function setRequestLine($method, $uri, $version = "1.1") {
        $this->method = $method;
        $this->uri = $uri;
        $this->version = $version;
    }
}

class ResponseMessage extends HttpMessage {
    public $version;
    public $status;
    public $reason;

    public function __construct() {
        $this->version = "1.1";
        $this->status = null;
        $this->reason = "";

        parent::__construct();
    }

    public function setResponseLine($status, $reason, $version = "1.1") {
        $this->status = $status;
        $this->reason = $reason;
        $this->version = $version;
    }
}

class HttpBuilder {
    const REQUEST   = 1;
    const RESPONSE  = 2;

    public $header_fields = array(
                                  "general" => array("Cache-Control", "Connection", "Date", "Pragma", "Trailer", "Transfer-Encoding", "Upgrade", "Via", "Warning"),

                                  "request" => array("Accept", "Accept-Charset", "Accept-Encoding", "Accept-Language", "Authorization", "Expect",
                                                     "From", "Host", "If-Match", "If-Modified-Since", "If-None-Match", "If-Range", "If-Unmodified-Since",
                                                     "Max-Forwards", "Proxy-Authorization", "Range", "Referer", "TE", "User-Agent"),

                                  "response" => array("Accept-Ranges", "Age", "ETag", "Location", "Proxy-Authenticate", "Retry-After",
                                                      "Server", "Vary", "WWW-Authenticate"),

                                  "entity" => array("Allow", "Content-Encoding", "Content-Language", "Content-Length", "Content-Location",
                                                    "Content-MD5", "Content-Range", "Content-Type", "Expires", "Last-Modified"),
                                 );

    public $fd;
    public $message;

    public function __construct($type = null, $realtime = false, $fd = null) {

        if($realtime) {
            if(is_resource($fd)) {
                $this->fd = $fd;
            } else {
                throw Exception("invalid fd");
            }
            $this->message = null;
        } else {
            $this->fd = null;
            if($type == self::REQUEST) {
                $this->message = new RequestMessage();
            } else if($type == self::RESPONSE){
                $this->message = new ResponseMessage();
            } else {
                throw Exeption("message type error");
            }
        }
    }

    public function setRequestLine($method, $uri, $version = "1.1") {
        if (is_a($this->message, "RequestMessage")) {
            $this->message->setRequestLine($method, $uri, $version);
        }
    }

    public function setResponseLine($status, $reason, $version = "1.1") {
        if (is_a($this->message, "ResponseMessage")) {
            $this->message->setResponseLine($status, $reason, $version);
        }
    }

    public function setHeader($field, $value) {
        $this->message->setHeader($field, $value);
    }

    public function removeHeader($field) {
        $this->message->removeHeader($field);
    }

    public function setBody($body) {
        $this->message->body = $body;
    }

    public function getMessage() {
        $msg = "";
        if (is_a($this->message, "RequestMessage")) {
            $msg .= $this->message->method . " " . $this->message->uri . " HTTP/" . $this->message->version . "\r\n";
        } else if(is_a($this->message, "ResponseMessage")) {
            $msg .= "HTTP/" . $this->message->version . " " . $this->message->status . " " .$this->message->reason . "\r\n";
        }

        foreach($this->header_fields as $fields) {
            foreach($fields as $field) {
                if(($value = $this->message->getHeader($field)) != null) {
                    $msg .= $field . ": " . $value . "\r\n";
                    $this->message->removeHeader($field);
                }
            }
        }

        foreach($this->message->headers as $key => $value) {
            $msg .= $key . ": " . $value . "\r\n";
        }
        $msg .= "\r\n";
        $msg .= $this->message->body;

        return $msg;
    }

    //below methods are for realtime transfer
    public function sendRequestLine($method, $uri, $version = "1.1") {
        socket_write($this->fd, $method . " " . $uri . " HTTP/" . $version . "\r\n");
    }

    public function sendResponseLine($status, $reason, $version = "1.1") {
        socket_write($this->fd, "HTTP/" . $version . " " . $status . " " .$reason . "\r\n");
    }

    public function sendHeader($field, $value) {
        socket_write($this->fd, $field . ": " . $value . "\r\n");
    }

    public function sendHeaderFinished() {
        socket_write($this->fd, "\r\n");
    }

    public function sendBody($body) {
        socket_write($this->fd, $body);
    }

    public function sendChunk($data) {
        $len = strval(strlen($data));
        socket_write($this->fd, $len . "\r\n");
        socket_write($this->fd, $data . "\r\n");
    }

    public function sendChunkFinished() {
        socket_write($this->fd, "0\r\n");
    }

    public function sendTrailer($field, $value) {
        socket_write($this->fd, $field . ": " . $value . "\r\n");
    }

    public function sendTrailerFinished() {
        socket_write($this->fd, "\r\n");
    }
}

class HttpParser {
    const REQUEST_LINE_RE = "/([^ ]+) ([^ ]+) HTTP\\/(\\d+\\.\\d+)/";
    const RESPONSE_LINE_RE = "/HTTP\\/(\\d+\\.\\d+) (\\d{3}) (.*)/";
    const RFC_TOKEN_RE = '/[\\x00-\\x1f \\(\\)<>@,;:\\\\"\\/\\[\\]\\?=\\{\\}]+/';
    const RFC_HTSP_RE = "/(^\\x09|^ )/";

    const STATE_FIRST_LINE              = 1;
    const STATE_HEADERS                 = 2;
    const STATE_BODY                    = 3;
    const STATE_FINISHED                = 4;

    const PARSING                       = 1;
    const FINISHED                      = 2;

    const CHUNK_STATE_SIZE              = 1;
    const CHUNK_STATE_DATA              = 2;
    const CHUNK_STATE_TRAILER           = 3;
    const CHUNK_STATE_FINISHED          = 4;

    private $state;
    private $message;
    private $buffer;
    private $field_name;
    private $field_value;
    private $chunked;
    private $chunk_state;
    private $chunk_size;

    private $finish_callback;
    private $finish_args;

    public function __construct() {
        $this->state = self::STATE_FIRST_LINE;
        $this->buffer = "";
        $this->message = null;
        $this->field_name = "";
        $this->field_value = "";
        $this->chunked = false;
        $this->finish_callback = null;
        $this->finish_args = null;
    }

    public function setFinishCallback($callback, $args) {
        $this->finish_callback = $callback;
        $this->finish_args = $args;
    }

    public function reset() {
        $this->state = self::STATE_FIRST_LINE;
        $this->buffer = "";
        $this->message = null;
        $this->field_name = "";
        $this->field_value = "";
        $this->chunked = false;
    }

    public function isToken($str) {
        if(preg_match(self::RFC_TOKEN_RE, $str)) {
            return false;
        }
        return true;
    }

    public function isHeaderBegin($line){
        if(preg_match(self::RFC_HTSP_RE, $line)) {
            return false;
        }
        return true;
    }

    public function parseFirstLine() {
        $num = preg_match_all("/\r\n/", $this->buffer);

        for ($i=0;$i<$num; $i++) {
            $p = strpos($this->buffer, "\r\n");
            $line = substr($this->buffer, 0, $p);
            $this->buffer = substr($this->buffer, $p+2, strlen($this->buffer)-$p-2);

            if(preg_match(self::REQUEST_LINE_RE, $line, $match)) {
                if($this->isToken($match[1])) {
                    $this->message = new RequestMessage();
                    $this->message->version = $match[3];
                    $this->message->uri = $match[2];
                    $this->message->method = $match[1];

                    return self::FINISHED;
                }
            } else if(preg_match(self::RESPONSE_LINE_RE, $line, $match)) {
                $this->message = new ResponseMessage();
                $this->message->version = $match[1];
                $this->message->status = $match[2];
                $this->message->reason = $match[3];

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
                if($this->field_name) {
                    $this->message->headers[$this->field_name] = rtrim($this->field_value);
                    $this->field_name = "";
                }

                return self::FINISHED;
            }

            if($this->isHeaderBegin($line)) {
                if($this->field_name) {
                    $this->message->headers[$this->field_name] = rtrim($this->field_value);
                    $this->field_name = "";
                }

                if(($c = strpos($line, ":")) != false) {
                    $name = substr($line, 0, $c);
                    if($this->isToken($name)) {
                        $this->field_name = $name;
                        $this->field_value = ltrim(substr($line, $c+1, strlen($line)-1));
                    } 
                }
            } else {
                if($this->field_name) {
                    $this->field_value .= $line;
                }
            }
        }

        return self::PARSING;
    }

    public function parseChunks() {
       if($this->chunk_state == self::CHUNK_STATE_SIZE) {
            if(preg_match("/\r\n/", $this->buffer)) {
                $buf = explode("\r\n", $this->buffer, 2);
                $this->chunk_size = hexdec(explode(";", $buf[0])[0]);
                $this->buffer = $buf[1];
                if($this->chunk_size == 0) {
                    return self::FINISHED;
                } else {
                    $this->chunk_state = self::CHUNK_STATE_DATA;
                }
            }
        }
        if($this->chunk_state == self::CHUNK_STATE_DATA) {
            $len = strlen($this->buffer);
            if($len >= $this->chunk_size + 2) {
                $this->message->body .= substr($this->buffer, 0, $this->chunk_size);
                $this->buffer = substr($this->buffer, $this->chunk_size+2);

                $this->chunk_state = self::CHUNK_STATE_SIZE;
                return $this->parseChunks();
            }
        }

        return self::PARSING;
    }

    public function parseTrailer() {
        return $this->parseHeaders();
    }

    public function parseBody() {
        if($this->chunked) {
            if(($this->chunk_state == self::CHUNK_STATE_SIZE) || ($this->chunk_state == self::CHUNK_STATE_DATA)) {
                switch($this->parseChunks()) {
                    case self::FINISHED:
                    $this->chunk_state = self::CHUNK_STATE_TRAILER;
                }
            }
            if($this->chunk_state == self::CHUNK_STATE_TRAILER) {
                switch($this->parseTrailer()) {
                    case self::FINISHED:
                        $this->chunk_state = self::CHUNK_STATE_FINISHED;
                }
            }
            if ($this->chunk_state == self::CHUNK_STATE_FINISHED) {
                return self::FINISHED;
            }
        } else {
            if(($msglen = $this->message->getHeader("content-length")) != null) {
                $msglen = intval($msglen);
                if(strlen($this->buffer) >= $msglen) {
                    $this->message->body = substr($this->buffer, 0, $msglen);
                    $this->buffer = substr($this->buffer, $msglen);
                    return self::FINISHED;
                }
            } else {
                //maybe not finished
                return self::FINISHED;
            }
        }

        return self::PARSING;
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
            $tecs = $this->message->getHeader("transfer-encoding");
            $tecs = explode(",", $tecs);
            $tecs = array_reverse($tecs);
            foreach($tecs as $ec) {
                switch($ec) {
                    case "chunked":
                        break;
                    case "identity":
                        break;
                    case "gzip":
                        $this->message->body = gzdecode($this->message->body);
                        break;
                    case "compress":
                        $this->message->body = gzuncompress($this->message->body);
                        break;
                    case "deflate":
                        $this->message->body = gzinflate($this->message->body);
                        break;
                }
            }
            $this->message->removeHeader("transfer-encoding");

            if($this->finish_callback) {
                call_user_func($this->finish_callback, $this->message, $this->finish_args);
            }
            if(strlen($this->buffer) > 0) {
                $buf = $this->buffer;
                $this->reset();
                $this->parse($buf);
            }
        }
    }
}
