<?php

declare(strict_types=1);

/**
 * Signer Exception
 *
 * Thrown when traceparent signing configuration is invalid.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Exception;

final class SignerException extends TraceLogException
{
    /**
     * Create for an empty signer secret.
     *
     * @return self Exception instance
     */
    public static function emptySecret(): self
    {
        return new self('Signer secret must not be empty');
    }
}
