<?php

declare(strict_types=1);

/**
 * Encryptor Interface
 *
 * Contract for symmetric encryption implementations.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Encryption;

interface EncryptorInterface
{
    /**
     * Encrypt a plaintext string.
     *
     * @param string $plaintext The plaintext to encrypt
     *
     * @return string The encrypted ciphertext
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt a ciphertext string.
     *
     * @param string $ciphertext The ciphertext to decrypt
     *
     * @return string The decrypted plaintext
     */
    public function decrypt(string $ciphertext): string;
}
