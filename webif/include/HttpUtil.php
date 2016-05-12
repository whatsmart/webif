<?php

class Request {
    public $version;
    public $method;
    public $path;
    public $host;
    public $query;
    public $fragment;

    public $body;

    public $cookie;

    public $finished;

    public function __construct($message) {
        $this->method = $message->getMethod();
        $this->path = parse_url($message->uri)["path"];
        $this->body = $message->getBody();
    }
}
