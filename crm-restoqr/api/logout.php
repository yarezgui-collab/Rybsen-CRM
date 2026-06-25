<?php
/**
 * api/logout.php — POST avec token => invalide la session
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_method('POST');

$token = extract_token();
if ($token) {
    logout($token);
}

json_response(['success' => true]);
