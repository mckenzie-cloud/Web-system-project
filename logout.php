<?php
    include("auth.php");
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
?>