<?php
/**
 * api/login.php — POST { email, password } => { token, user }
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_method('POST');

if (!rate_limit('login_' . client_ip(), 8, 60)) {
    json_error('Trop de tentatives. Réessayez dans une minute.', 429);
}

$body = read_json_body();
$email = trim($body['email'] ?? '');
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_error('Email et mot de passe requis.');
}

$result = login($email, $password);

if (!$result['success']) {
    json_error($result['error'], 401);
}

json_response($result);
