<?php

class HttpMessage {
    public $version;
    public $headers;
    public $body;
    public static $header_fields = array(
                                  "general" => array("Cache-Control", "Connection", "Date", "Pragma", "Trailer", "Transfer-Encoding", "Upgrade", "Via", "Warning"),

                                  "request" => array("Accept", "Accept-Charset", "Accept-Encoding", "Accept-Language", "Authorization", "Expect",
                                                     "From", "Host", "If-Match", "If-Modified-Since", "If-None-Match", "If-Range", "If-Unmodified-Since",
                                                     "Max-Forwards", "Proxy-Authorization", "Range", "Referer", "TE", "User-Agent"),

                                  "response" => array("Accept-Ranges", "Age", "ETag", "Location", "Proxy-Authenticate", "Retry-After",
                                                      "Server", "Vary", "WWW-Authenticate"),

                                  "entity" => array("Allow", "Content-Encoding", "Content-Language", "Content-Length", "Content-Location",
                                                    "Content-MD5", "Content-Range", "Content-Type", "Expires", "Last-Modified"),
                                 );

    public function __construct($headers = [], $body = "", $version = "1.1") {
        $this->headers = $headers;
        $this->body = $body;
        $this->version = $version;
    }

    public function setVersion($version) {
        $this->version = $version;
        return $this;
    }

    public function getVersion() {
        return $this->version;
    }

    public function setHeader($field, $value) {
        $this->headers[$field] = $value;
        return $this;
    }

    public function setHeaders($headers) {
        $this->headers = $headers;
        return $this;
    }

    public function getHeader($field) {
        foreach($this->headers as $key => $value) {
            if(!strcasecmp($key, $field)) {
                return $value;
            }
        }

        return null;
    }

    public function getHeaders() {
        return $this->headers;
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
        return $this;
    }

    public function getBody() {
        return $this->body;
    }
}

class HttpRequest extends HttpMessage {
    public $method;
    public $uri;

    public function __construct($method = "", $uri = "", $headers = [], $body = "", $version = "1.1") {
        $this->method = $method;
        $this->uri = $uri;

        parent::__construct($headers, $body, $version);
    }

    public function setRequestLine($method, $uri, $version = "1.1") {
        $this->method = $method;
        $this->uri = $uri;
        $this->version = $version;
        return $this;
    }

    public function setMethod($method) {
        $this->method = $method;
        return $this;
    }

    public function getMethod() {
        return $this->method;
    }

    public function setUri($uri) {
        $this->uri = $uri;
        return $this;
    }

    public function getUri() {
        return $this->uri;
    }

    public function getMessage() {
        $msg = "";
        $msg .= $this->method . " " . $this->uri . " HTTP/" . $this->version . "\r\n";

        $this->setHeader("Content-Length", strval(strlen($this->body)));

        foreach(parent::$header_fields as $fields) {
            foreach($fields as $field) {
                if(($value = $this->getHeader($field)) != null) {
                    $msg .= $field . ": " . $value . "\r\n";
                    $this->removeHeader($field);
                }
            }
        }

        foreach($this->headers as $key => $value) {
            $msg .= $key . ": " . $value . "\r\n";
        }
        $msg .= "\r\n";
        $msg .= $this->body;

        return $msg;
    }
}

class HttpResponse extends HttpMessage {
    public $status;
    public $reason;

    public function __construct($status = "", $reason = "", $headers = [], $body = "", $version = "1.1") {
        $this->status = $status;
        $this->reason = $reason;

        parent::__construct($headers, $body, $version);
    }

    public function setResponseLine($status, $reason, $version = "1.1") {
        $this->status = $status;
        $this->reason = $reason;
        $this->version = $version;
        return $this;
    }

    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setReason($reason) {
        $this->reason = $reason;
        return $this;
    }

    public function getReason() {
        return $this->reason;
    }

    public function getMessage() {
        $msg = "";
        $msg .= "HTTP/" . $this->version . " " . $this->status . " " .$this->reason . "\r\n";

        $this->setHeader("Content-Length", strval(strlen($this->body)));

        foreach(parent::$header_fields as $fields) {
            foreach($fields as $field) {
                if(($value = $this->getHeader($field)) != null) {
                    $msg .= $field . ": " . $value . "\r\n";
                    $this->removeHeader($field);
                }
            }
        }

        foreach($this->headers as $key => $value) {
            $msg .= $key . ": " . $value . "\r\n";
        }
        $msg .= "\r\n";
        $msg .= $this->body;

        return $msg;
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
                    $this->message = new HttpRequest();
                    $this->message->version = $match[3];
                    $this->message->uri = $match[2];
                    $this->message->method = $match[1];

                    return self::FINISHED;
                }
            } else if(preg_match(self::RESPONSE_LINE_RE, $line, $match)) {
                $this->message = new HttpResponse();
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
                    $this->field_value = "";
                }

                return self::FINISHED;
            }

            if($this->isHeaderBegin($line)) {
                if($this->field_name) {
                    $this->message->headers[$this->field_name] = rtrim($this->field_value);
                    $this->field_name = "";
                    $this->field_value = "";
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
                //assume no body
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
            if($tecs) {
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
            }

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
