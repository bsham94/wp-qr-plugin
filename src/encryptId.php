<?php

class EncryptID
{
    public function __construct()
    {

    }
    public static function encryptID($key)
    {
        $secret = get_option('ENCRYPTION_KEY');
        $iv = get_option('IV');
        $encrypt_key = openssl_encrypt($key, 'AES-256-CBC', $secret, 0, $iv);
        return $encrypt_key;

    }
}
