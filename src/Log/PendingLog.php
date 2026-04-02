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
     * Encrypt the message and write the log immediately.
     */
    public function secure(): void
    {
        if ($this->written) {
            return;
        }

        $this->written = true;

        $message = $this->message;
        $context = $this->context;

        if ($this->encryptor !== null) {
            $message = $this->encryptor->encrypt($this->message);
            $context['encrypted'] = true;
        }

        $this->logger->writeRecord($this->level, $message, $context);
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

        $this->logger->writeRecord($this->level, $this->message, $this->context);
    }
}
