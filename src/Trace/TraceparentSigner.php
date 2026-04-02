<?php

declare(strict_types=1);

/**
 * Traceparent Signer
 *
 * Signs and verifies traceparent headers using HMAC-SHA256.
 * Used to reconnect traces across async boundaries (webhooks, callbacks)
 * where a third party echoes back your traceparent.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Trace;

use RuntimeException;

final class TraceparentSigner
{
    /**
     * @param string $secret HMAC secret key (minimum 16 characters recommended)
     * @throws RuntimeException If the secret is empty
     */
    public function __construct(
        private readonly string $secret,
    ) {
        if ($secret === '') {
            throw new RuntimeException('Signer secret must not be empty.');
        }
    }

    /**
     * Sign a traceparent header with HMAC-SHA256.
     *
     * @param Traceparent $traceparent The traceparent to sign
     * @return string 64-character hex HMAC signature
     */
    public function sign(Traceparent $traceparent): string
    {
        return hash_hmac('sha256', $traceparent->toHeader(), $this->secret);
    }

    /**
     * Verify a traceparent signature using constant-time comparison.
     *
     * @param Traceparent $traceparent The traceparent to verify
     * @param string $signature The signature to check against
     * @return bool True if the signature is valid
     */
    public function verify(Traceparent $traceparent, string $signature): bool
    {
        return hash_equals($this->sign($traceparent), $signature);
    }
}
