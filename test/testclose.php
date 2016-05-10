<?php

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($sock, "127.0.0.1", 80);
socket_close($sock);
