<?php

declare(strict_types=1);

/**
 * TraceLog Config
 *
 * Immutable value object holding configuration for the TraceLog facade.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog;

use BackedEnum;
use PHPdot\TraceLog\Log\Channel\Channel;

final readonly class TraceLogConfig
{
    /**
     * Create a TraceLog configuration.
     *
     * @param string $logPath Base directory for log files
     * @param string $logLevel Minimum log level string
     * @param BackedEnum $defaultChannel Default log channel
     * @param int $maxCompletedSpans Maximum completed spans retained in registry
     * @param int $maxChannels Maximum cached channel handlers
     * @param string $defaultFormatter Default formatter type ('json' or 'text')
     * @param string|null $encryptionKey Optional encryption key for secure logging
     */
    public function __construct(
        public string $logPath = '/var/log/app',
        public string $logLevel = 'debug',
        public BackedEnum $defaultChannel = Channel::App,
        public int $maxCompletedSpans = 100,
        public int $maxChannels = 50,
        public string $defaultFormatter = 'json',
        public ?string $encryptionKey = null,
    ) {}
}
