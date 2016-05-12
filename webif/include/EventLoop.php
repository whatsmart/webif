<?php

class EvListener {
    public $loop;
    public $callback;
    public $data;
    public $enabled;

    public function __construct($loop, $callback, $data) {
        $this->loop = $loop;
        $this->callback = $callback;
        $this->data = $data;
        $this->enabled = false;
    }

    public function enable() {
        $this->enabled = true;
    }

    public function disable() {
        $this->enabled = false;
    }

    public function destroy() {
        $this->loop->destroyIOListener($this);
    }
}

class IOListener extends EvListener {
    public $fd;
    public $flags;

    public function __construct($loop, $callback, $data, $fd, $flags) {
        parent::__construct($loop, $callback, $data);
        $this->fd = $fd;
        $this->flags = $flags;

        $this->loop->iolisteners[] = $this;
    }
}

class EventLoop {
    const READ = 1;
    const WRITE = 2;

    public $reads;
    public $writes;
    public $stop;

    public $iolisteners;
    public $callbacks;

    public function __construct() {
        $this->reads = [];
        $this->writes = [];
        $this->stop = false;
        $this->iolisteners = [];
    }

    public function destroyIOListener($listener) {
        foreach($this->iolisteners as $key => $value) {
            if($value == $listener) {
                array_splice($this->iolisteners, $key, 1);
            }
        }
    }

    public function time() {
        return time();
    }

    public function asyncRun($id, $timeout, $callback){

        $this->callbacks[] = array("id" => $id, "callback" => $callback, "start" => $this->time(), "timeout" => $timeout);
    }

    public function asyncCall($id, $args) {
        foreach($this->callbacks as $cb) {
            if($cb["id"] == $id) {
                $cb["callback"]($args);
            }
        }
    }

    public function run() {
        $this->stop = false;

        while (!$this->stop) {
//echo "loop";
            //io pool
            $reads = [];
            $writes = [];
            $excepts = [];
            foreach($this->iolisteners as $iolistener) {
                if($iolistener->enabled) {
                    if($iolistener->flags & self::READ) {
                        $reads[] = $iolistener->fd;
                    }
                    if($iolistener->flags & self::WRITE) {
                        $writes[] = $iolistener->fd;
                    }
                }
            }

            $nfd = socket_select($reads, $writes, $excepts, 0, 1000);

            if ($nfd === false) {
                //timeout
                //echo "timeout";
            } else if ($nfd > 0) {
                foreach($this->iolisteners as $iolistener) {
                    if(in_array($iolistener->fd, $reads)) {
                        call_user_func($iolistener->callback, $iolistener, self::READ);
                    }
                    if(in_array($iolistener->fd, $writes)) {
                        call_user_func($iolistener->callback, $iolistener, self::WRITE);
                    }
                }
            }

            //async
/*
            foreach($this->generators as $gen) {
                if (time() >= $gen["start"] + $gen["timeout"]) {
                    unset($gen);
                }
            }
*/
        }
    }
}
