<?php

$db = new SQLite3("../webif/web/webif.db");
$db->exec("create table user(username STRING, password STRING)");
$db->close();
