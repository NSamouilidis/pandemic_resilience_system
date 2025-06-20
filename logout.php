<?php
session_start();

require_once "config/db_connect.php";

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["prs_id"])) {
    log_activity($_SESSION["prs_id"], "logout", "system", "auth", "success");
}

$_SESSION = array();

session_destroy();

header("location: login.php");
exit;