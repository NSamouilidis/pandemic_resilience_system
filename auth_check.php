<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../login.php");
    exit;
}

function check_role($required_role) {
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== $required_role) {
        header("location: ../../index.php");
        exit;
    }
}