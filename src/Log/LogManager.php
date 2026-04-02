<?php

declare(strict_types=1);

/**
 * Log Manager
 *
 * PSR-3 compliant logger with multi-channel routing, context enrichment,
 * and optional encryption support. Uses immutable cloning for channel
 * and context switching.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log;

use BackedEnum;
use DateTimeImmutable;
use PHPdot\TraceLog\Encryption\EncryptorInterface;
use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\Context\ContextInterface;
use Psr\Log\LoggerInterface;
use Stringable;

final class LogManager implements LoggerInterface
{
    private ?BackedEnum $currentChannel = null;

    private ?ContextInterface $context = null;

    /**
     * Create a log manager.
     *
     * @param ChannelManager $channelManager The channel manager for handler resolution
     * @param EncryptorInterface|null $encryptor Optional encryptor for secure logging
     * @param BackedEnum $defaultChannel The default channel when none is set
     */
    public function __construct(
        private readonly ChannelManager $channelManager,
        private readonly ?EncryptorInterface $encryptor = null,
        private readonly BackedEnum $defaultChannel = Channel::App,
    ) {}

    /**
     * Log an emergency message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::EMERGENCY, (string) $message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::ALERT, (string) $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::CRITICAL, (string) $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::ERROR, (string) $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::WARNING, (string) $message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::NOTICE, (string) $message, $context);
    }

    /**
     * Log an informational message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::INFO, (string) $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->writeRecord(LogLevel::DEBUG, (string) $message, $context);
    }

    /**
     * Log a message at an arbitrary level.
     *
     * @param mixed $level The PSR-3 log level string
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $intLevel = is_int($level) ? $level : LogLevel::fromPsr(is_string($level) ? $level : 'debug');

        $this->writeRecord($intLevel, (string) $message, $context);
    }

    /**
     * Return a clone scoped to the given channel.
     *
     * @param BackedEnum $channel The channel to scope to
     *
     * @return self A new LogManager instance scoped to the channel
     */
    public function channel(BackedEnum $channel): self
    {
        $clone = clone $this;
        $clone->currentChannel = $channel;

        return $clone;
    }

    /**
     * Create a pending (deferred) log entry.
     *
     * @param string $level The PSR-3 log level string
     * @param string|Stringable $message The log message
     * @param array<mixed> $context Additional context data
     *
     * @return PendingLog A deferred log entry
     */
    public function pending(string $level, string|Stringable $message, array $context = []): PendingLog
    {
        return new PendingLog(
            $this,
            LogLevel::fromPsr($level),
            (string) $message,
            $context,
            $this->encryptor,
        );
    }

    /**
     * Return a clone enriched with additional context.
     *
     * @param ContextInterface $context The context provider
     *
     * @return self A new LogManager instance with context enrichment
     */
    public function withContext(ContextInterface $context): self
    {
        $clone = clone $this;
        $clone->context = $context;

        return $clone;
    }

    /**
     * Build and write a log record to the appropriate channel handler.
     *
     * @param int $level The integer log level
     * @param string $message The log message
     * @param array<mixed> $context Additional context data
     */
    public function writeRecord(int $level, string $message, array $context): void
    {
        $channel = $this->currentChannel ?? $this->defaultChannel;

        $now = new DateTimeImmutable();

        /** @var string $channelValue */
        $channelValue = $channel->value;

        $record = [
            'timestamp'  => $now->format('Y-m-d\TH:i:s.uP'),
            'level'      => $level,
            'level_name' => LogLevel::name($level),
            'message'    => $message,
            'channel'    => $channelValue,
            'context'    => $context,
        ];

        if ($this->context !== null) {
            $record = array_merge($this->context->toArray(), $record);
        }

        $handler = $this->channelManager->getHandler($channel);
        $handler->handle($record);
    }
}
