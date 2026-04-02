<?php

declare(strict_types=1);

/**
 * ChaCha20-Poly1305 Encryptor
 *
 * Authenticated encryption with compression using ChaCha20-Poly1305.
 * Compress, encrypt, and base64-encode for safe transport and storage.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Encryption;

use RuntimeException;

final class ChaChaEncryptor implements EncryptorInterface
{
    private const CIPHER    = 'chacha20-poly1305';
    private const NONCE_LEN = 12;
    private const TAG_LEN   = 16;
    private const KEY_LEN   = 32;

    private readonly string $key;

    /**
     * Create a new ChaChaEncryptor instance.
     *
     * @param string $key Base64-encoded 256-bit (32-byte) encryption key
     *
     * @throws RuntimeException If the key is invalid
     */
    public function __construct(string $key)
    {
        $decoded = base64_decode($key, true);

        if ($decoded === false || strlen($decoded) !== self::KEY_LEN) {
            throw new RuntimeException('Encryption key must be a base64-encoded 256-bit (32 bytes) key');
        }

        $this->key = $decoded;
    }

    /**
     * Generate a new random encryption key.
     *
     * @return string Base64-encoded 256-bit key
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(self::KEY_LEN));
    }

    /**
     * Encrypt a plaintext string (compress, encrypt, base64-encode).
     *
     * @param string $plaintext The plaintext to encrypt
     *
     *
     * @throws RuntimeException If compression or encryption fails
     * @return string Base64-encoded ciphertext
     */
    public function encrypt(string $plaintext): string
    {
        $compressed = gzcompress($plaintext, 9);

        if ($compressed === false) {
            throw new RuntimeException('Compression failed');
        }

        $nonce = random_bytes(self::NONCE_LEN);
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $compressed,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LEN,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt a ciphertext string (base64-decode, decrypt, decompress).
     *
     * @param string $ciphertext Base64-encoded ciphertext
     *
     *
     * @throws RuntimeException If decoding, decryption, or decompression fails
     * @return string The decrypted plaintext
     */
    public function decrypt(string $ciphertext): string
    {
        $payload = base64_decode($ciphertext, true);

        $minLength = self::NONCE_LEN + self::TAG_LEN;

        if ($payload === false || strlen($payload) < $minLength) {
            throw new RuntimeException('Invalid payload');
        }

        $nonce      = substr($payload, 0, self::NONCE_LEN);
        $tag        = substr($payload, self::NONCE_LEN, self::TAG_LEN);
        $encrypted  = substr($payload, $minLength);

        $compressed = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($compressed === false) {
            throw new RuntimeException('Decryption failed');
        }

        $message = gzuncompress($compressed);

        if ($message === false) {
            throw new RuntimeException('Decompression failed');
        }

        return $message;
    }
}
