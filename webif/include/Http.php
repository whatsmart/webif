<?php

class ParseException {

}

class RequestObject {
    public $version;
    public $method;
    public $uri;
    public $headers;

    public function getHeader($field) {
        foreach($this->headers as $key => $value) {
            if(!strcasecmp($key, $field)) {
                return $value;
            }
        }

        return null;
    }

}

class ResponseObject {
    public $version;
    public $status;
    public $reason;
    public $headers;
}

class HttpParser {
    const REQUEST_LINE_RE = "/([^ ]+) ([^ ]+) HTTP\\/(\\d+\\.\\d+)/";
    const RESPONSE_LINE_RE = "/HTTP\\/(\\d+\\.\\d+) (\\d{3}) (.*)/";
    const RFC_TOKEN_RE = '/[\\x00-\\x1f \\(\\)<>@,;:\\\\"\\/\\[\\]\\?=\\{\\}]+/';
    const RFC_HTSP_RE = "/(^\\x09|^ )/";

    const STATE_FIRST_LINE              = 0;
    const STATE_HEADERS                 = 5;
    const STATE_BODY                    = 7;
    const STATE_FINISHED                = 8;

    private $state;
    private $message;
    private $buffer;
    private $field_name;
    private $field_value;
    private $chunked = false;

    public function __construct() {
        $this->state = self::STATE_FIRST_LINE;
        $this->buffer = "";
        $this->message = null;
        $this->field_name = "";
        $this->field_value = "";
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

    public function popLine() {
        if(($p = strpos($this->buffer, "\r\n")) !== false) {
            $line = substr($this->buffer, 0, $p);
            $this->buffer = substr($this->buffer, $p+2, strlen($this->buffer)-$p);
            return $line;
        }
        return false;
    }

    

    public function parse($data) {
        echo $data;
        $this->buffer .= $data;
        if($this->state == self::STATE_FIRST_LINE) {
            if(($p = strpos($this->buffer, "\r\n")) === false) {
                return;
            } else {
                $num = preg_match_all("/\r\n/", $this->buffer);

                for ($i=0;$i<$num; $i++) {
                    $p = strpos($this->buffer, "\r\n");
                    $line = substr($this->buffer, 0, $p);
                    $this->buffer = substr($this->buffer, $p+2, strlen($this->buffer)-$p-2);

                    if(preg_match(self::REQUEST_LINE_RE, $line, $match)) {
                        if($this->isToken($match[1])) {
                            $this->message = new RequestObject();
                            $this->message->version = $match[3];
                            $this->message->uri = $match[2];
                            $this->message->method = $match[1];

                            $this->state = self::STATE_HEADERS;
                            break;
                        }
                    } else if(preg_match(self::RESPONSE_LINE_RE, $line, $match)) {
                        $this->message = new ResponseObject();
                        $this->message->version = $match[1];
                        $this->message->status = $match[2];
                        $this->message->reason = $match[3];

                        $this->state = self::STATE_HEADERS;
                        break;
                    }
                }
            }
        }
        if($this->state == self::STATE_HEADERS) {
            if(($p = strpos($this->buffer, "\r\n")) === false) {
                return;
            } else {
                $num = preg_match_all("/\r\n/", $this->buffer);

                for ($i=0;$i<$num; $i++) {
                    $p = strpos($this->buffer, "\r\n");
                    $line = substr($this->buffer, 0, $p);
                    $this->buffer = substr($this->buffer, $p+2, strlen($this->buffer)-$p-2);

                    if(strlen($line) == 0) {
                        $this->state = self::STATE_BODY;
                        break;
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
            }
        }
        if($this->state == self::STATE_BODY) {
            if($this->field_name) {
                $this->message->headers[$this->field_name] = rtrim($this->field_value);
                $this->field_name = "";
            }

            if($this->message->getHeader("content-length")) {
            }

            var_dump($this->message);
        }
    }


}
