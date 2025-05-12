<?php


define('ENCRYPTION_KEY', 'i-have-no-enemies'); 

function encrypt($data) {
    $key = ENCRYPTION_KEY;
    $iv = substr(hash('sha256', $key), 0, 16);
    return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv));
}

function decrypt($data) {
    $key = ENCRYPTION_KEY;
    $iv = substr(hash('sha256', $key), 0, 16);
    return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, 0, $iv);
}

?>