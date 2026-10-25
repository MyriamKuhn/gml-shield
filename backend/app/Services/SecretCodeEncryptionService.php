<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;

class SecretCodeEncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    private const KEY_LENGTH = 32;

    private const NONCE_LENGTH = 12;

    private const TAG_LENGTH = 16;

    private const VERSION = 'v1';

    /**
     * Encrypt a warehouse access code using authenticated encryption.
     *
     * The random nonce and authentication tag are stored alongside
     * the ciphertext. The encryption key is never stored with it.
     */
    public function encrypt(string $secretCode): string
    {
        $key = $this->encryptionKey();
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $secretCode,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Secret encryption failed.');
        }

        return implode(':', [
            self::VERSION,
            base64_encode($nonce),
            base64_encode($tag),
            base64_encode($ciphertext),
        ]);
    }

    /**
     * Decrypt a code and verify its authentication tag.
     *
     * Reject malformed or altered encrypted values.
     */
    public function decrypt(string $encryptedCode): string
    {
        $parts = explode(':', $encryptedCode);

        if (count($parts) !== 4 || $parts[0] !== self::VERSION) {
            throw new DecryptException('Invalid encrypted secret.');
        }

        $nonce = base64_decode($parts[1], true);
        $tag = base64_decode($parts[2], true);
        $ciphertext = base64_decode($parts[3], true);

        if (
            $nonce === false ||
            $tag === false ||
            $ciphertext === false ||
            strlen($nonce) !== self::NONCE_LENGTH ||
            strlen($tag) !== self::TAG_LENGTH
        ) {
            throw new DecryptException('Invalid encrypted secret.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new DecryptException('Secret decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Load a dedicated 256-bit key from application configuration.
     *
     * The key must be Base64 encoded and must not reuse APP_KEY.
     */
    private function encryptionKey(): string
    {
        $encodedKey = config('security.secret_code_key');

        if (!is_string($encodedKey) || $encodedKey === '') {
            throw new RuntimeException(
                'Secret code encryption key is not configured.'
            );
        }

        $key = base64_decode($encodedKey, true);

        if ($key === false || strlen($key) !== self::KEY_LENGTH) {
            throw new RuntimeException(
                'Invalid secret code encryption key.'
            );
        }

        return $key;
    }
}
