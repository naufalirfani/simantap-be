<?php

namespace App\Services;

class TokenEncryptionService
{
    public static function encryptTokenForHeader(string $token, array $options = []): string
    {
        $salt = (string) ($options['salt'] ?? $token);
        $key = hash('sha256', $token . $salt, true);
        $iv = random_bytes(16);

        $ciphertext = openssl_encrypt($token, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new \RuntimeException('Failed to encrypt API token header.');
        }

        return 'v1.aes:' . base64_encode($iv . $ciphertext);
    }
}
