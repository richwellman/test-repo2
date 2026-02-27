<?php
$hostname = $_SERVER['MYSQL_REDCAP_CI_HOSTNAME'];
$username = $_SERVER['MYSQL_REDCAP_CI_USERNAME'];
$password = $_SERVER['MYSQL_REDCAP_CI_PASSWORD'];
$db = $_SERVER['MYSQL_REDCAP_CI_DB'];
$salt = sha1($password);