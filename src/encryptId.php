<?php

class EncryptID
{
    public static function encryptID($key)
    {
        $secret = get_option('encryption_message');
        $iv = get_option('iv');
        $encrypt_key = openssl_encrypt($key, 'AES-256-CBC', $secret, 0, $iv);
        return $encrypt_key;

    }
}