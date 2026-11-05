<?php
    date_default_timezone_set("Asia/Manila");

    $hostname = '127.0.0.1';
    $user = 'root';
    $password = 'my-secret-pw';
    $database = 'bella-vista';
    $port = 3307;

    // throw exceptions on errors instead of failing silently
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $mysqli = new mysqli($hostname, $user, $password, $database, $port);
    $mysqli->set_charset("utf8mb4");
    $mysqli->query("SET time_zone = '+08:00'");
?>