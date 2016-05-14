<?php
namespace JsonRPC;

class RequestDecoder {
    public $data;
    public $errcode;
    public $errmsg;

    public function __construct() {
        $this->data = [];
        $this->errcode = 0;
        $this->errmsg = "";
    }

    public function decode($req) {
        $this->data = json_decode($req, true);
        if(!$this->data) {
            $this->errcode = 1;
            $this->errmsg = "invalid json string";
            return false;
        }
        if(!$this->jsonrpc or !$this->method) {
            $this->errcode = 2;
            $this->errmsg = "invalid jsonrpc request, \"jsonrpc\" and \"method\" are required";
            return false;
        }
        return true;
    }

    public function array_get($arr, $key) {
        if(is_array($arr) and array_key_exists($key, $arr)) {
            return $arr[$key];
        }
        return null;
    }

    public function getVersion() {
        return $this->array_get($this->data, "jsonrpc");
    }

    public function getMethod() {
        return $this->array_get($this->data, "method");
    }

    public function getParams() {
        return $this->array_get($this->data, "params");
    }

    public function getId() {
        return $this->array_get($this->data, "id");
    }

    public function getParam($name) {
        $ps = explode("->", $name);
        $params = $this->params;
        foreach($ps as $p) {
            $params = $this->array_get($params, $p);
            if(!$params) {
                return null;
            }
        }
        return $params;
    }

    public function __get($name) {
        switch($name) {
            case "jsonrpc":
                return $this->getVersion();
                break;
            case "method":
                return $this->getMethod();
                break;
            case "params":
                return $this->getParams();
                break;
            case "id":
                return $this->getId();
                break;
            default:
                return null;
        }
    }
}

class ResponseDecoder {
    public $data;
    public $errcode;
    public $errmsg;

    public function __construct() {
        $this->data = [];
        $this->errcode = 0;
        $this->errmsg = "";
    }

    public function decode($res) {
        $this->data = json_decode($res, true);
        if(!$this->data) {
            $this->errcode = 1;
            $this->errmsg = "invalid json string";
            return false;
        }
        if(!($this->jsonrpc and $this->id and ($this->result xor $this->error))) {
            $this->errcode = 2;
            $this->errmsg = "invalid jsonrpc request, \"jsonrpc\" and \"id\" are required, \"result\" and \"error\" must exits one";
            return false;
        }
        if($this->error) {
            return -1;
        } else {
            return 1;
        }
    }

    public function array_get($arr, $key) {
        if(is_array($arr) and array_key_exists($key, $arr)) {
            return $arr[$key];
        }
        return null;
    }

    public function getVersion() {
        return $this->array_get($this->data, "jsonrpc");
    }

    public function getResult() {
        return $this->array_get($this->data, "result");
    }

    public function getError() {
        return $this->array_get($this->data, "error");
    }

    public function getId() {
        return $this->array_get($this->data, "id");
    }

    public function getErrorCode() {
        if($error = $this->array_get($this->data, "error")) {
            return $this->array_get($error, "code");
        }
        return null;
    }

    public function getErrorMsg() {
        if($error = $this->array_get($this->data, "error")) {
            return $this->array_get($error, "message");
        }
        return null;
    }

    public function getErrorData() {
        if($error = $this->array_get($this->data, "error")) {
            return $this->array_get($error, "data");
        }
        return null;
    }

    public function __get($name) {
        switch($name) {
            case "jsonrpc":
                return $this->getVersion();
                break;
            case "result":
                return $this->getResult();
                break;
            case "error":
                return $this->getError();
                break;
            case "id":
                return $this->getId();
                break;
            default:
                return null;
        }
    }
}

class RequestEncoder {
    public static function encode($method, $params, $id, $jsonrpc = "2.0") {
        if(!$method or !$jsonrpc) {
            return null;
        }
        $req = [
            "jsonrpc" => $jsonrpc,
            "method" => $method,
        ];
        if ($params) {
            $req["params"] = $params;
        }
        if($id) {
            $req["id"] = $id;
        }
        return json_encode($req, JSON_PRETTY_PRINT);
    }
}

class ResultEncoder {
    public static function encode($result, $id, $jsonrpc = "2.0") {
        if(!$result or !$jsonrpc or !$id) {
            return null;
        }
        $res = [
            "jsonrpc" => $jsonrpc,
            "result" => $result,
            "id" => $id
        ];
        return json_encode($res, JSON_PRETTY_PRINT);
    }
}

class ErrorEncoder {
    public static function encode($errcode, $errmsg, $data = null, $id, $jsonrpc = "2.0") {
        if(!$errcode or !$errmsg or !$jsonrpc or !$id) {
            return null;
        }
        $err = [
            "jsonrpc" => $jsonrpc,
            "error" => [
                "code" => $errcode,
                "message" => $errmsg
            ],
            "id" => $id
        ];
        if($data) {
            $err["error"]["data"] = $data;
        }
        return json_encode($err, JSON_PRETTY_PRINT);
    }
}
