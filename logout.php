<?php
session_start();

/* CLEAR ALL SESSION DATA */
$_SESSION = [];

/* DESTROY SESSION */
session_destroy();

/* OPTIONAL: DELETE SESSION COOKIE (more secure) */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* REDIRECT TO LOGIN PAGE */
header("Location: login.php");
exit();
?>