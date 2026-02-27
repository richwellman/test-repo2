<?php
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Determine redirect URI from POST or GET
$redirectUri = null;
if (!empty($_POST['redirect_uri'])) {
    $redirectUri = $_POST['redirect_uri'];
} elseif (!empty($_GET['redirect_uri'])) {
    $redirectUri = $_GET['redirect_uri'];
}

// If we have a redirect URI, perform the redirect
if ($redirectUri !== null) {
    $separator = strpos($redirectUri, '?') === false ? '?' : '&';
    header('Location: ' . $redirectUri . $separator . 'sso=ok');
    exit;
}

// Otherwise, return JSON response (for API calls without redirect)
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'time'   => time()
]);
exit;