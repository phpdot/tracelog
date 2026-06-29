<?php

declare(strict_types=1);

/**
 * Pending Log
 *
 * Deferred log entry that supports optional secure encryption.
 * The log is written when secure() is called or when the object is destroyed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log;

use PHPdot\TraceLog\Encryption\EncryptorInterface;
use PHPdot\TraceLog\Exception\EncryptionException;

final class PendingLog
{
    private bool $written = false;

    /**
     * Create a pending log entry.
     *
     * @param LogManager $logger The log manager to delegate writing to
     * @param int $level The integer log level
     * @param string $message The log message
     * @param array<mixed> $context Additional context data
     * @param EncryptorInterface|null $encryptor Optional encryptor for secure logging
     */
    public function __construct(
        private readonly LogManager $logger,
        private readonly int $level,
        private readonly string $message,
        private readonly array $context = [],
        private readonly ?EncryptorInterface $encryptor = null,
    ) {}

    /**
     * Encrypt the message and context, then write the log immediately.
     *
     * Fails closed: throws (and writes nothing) if no encryptor is configured or encryption
     * fails — the sensitive payload is never written in plaintext.
     *
     * @throws EncryptionException If no encryptor is configured or encryption fails
     */
    public function secure(): void
    {
        if ($this->written) {
            return;
        }

        // Mark consumed up front: if encryption is unavailable or fails, the log is DROPPED —
        // it must never fall through to a plaintext write (including the __destruct fallback).
        $this->written = true;

        // Fail closed: secure() refuses to write the sensitive payload without encryption.
        if ($this->encryptor === null) {
            throw EncryptionException::secureWithoutEncryptor();
        }

        // Encrypt the message AND the user context together — context is where structured
        // logging puts the actual secrets. Encrypt before writing, so a failure writes nothing.
        try {
            $payload = json_encode(
                ['message' => $this->message, 'context' => $this->context],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );
        } catch (\JsonException) {
            throw EncryptionException::securePayloadNotEncodable();
        }

        $ciphertext = $this->encryptor->encrypt($payload);

        $this->logger->writeRecord($this->level, $ciphertext, ['encrypted' => true]);
    }

    /**
     * Write the log entry when the object is destroyed if not already written.
     */
    public function __destruct()
    {
        $this->write();
    }

    /**
     * Write the log entry if it has not been written yet.
     */
    private function write(): void
    {
        if ($this->written) {
            return;
        }

        $this->written = true;

        // Implicit (destructor) write must NEVER throw — a throw from __destruct is a fatal error.
        try {
            $this->logger->writeRecord($this->level, $this->message, $this->context);
        } catch (\Throwable) {
            // Best-effort: a normal log is dropped rather than crashing the application.
        }
    }
}
