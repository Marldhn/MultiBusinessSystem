<?php

/*
|--------------------------------------------------------------------------
| SESSION INACTIVITY TIMEOUT
|--------------------------------------------------------------------------
| Automatically logs the user out after 5 minutes of inactivity.
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$inactiveTimeout = 300; // 5 minutes = 300 seconds

/*
|--------------------------------------------------------------------------
| CHECK LAST ACTIVITY
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['last_activity'])) {

    $inactiveTime = time() - (int)$_SESSION['last_activity'];

    if ($inactiveTime >= $inactiveTimeout) {

        /*
        |--------------------------------------------------------------------------
        | SAVE OPTIONAL MESSAGE
        |--------------------------------------------------------------------------
        */

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        /*
        |--------------------------------------------------------------------------
        | REDIRECT TO LOGIN
        |--------------------------------------------------------------------------
        */

        header('Location: index.php?page=login&timeout=1');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE LAST ACTIVITY
|--------------------------------------------------------------------------
*/

$_SESSION['last_activity'] = time();