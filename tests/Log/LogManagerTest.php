<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Log\PendingLog;
use PHPdot\TraceLog\Tests\Stubs\AppChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LogManagerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_test_' . uniqid();
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    private function createManager(): LogManager
    {
        $channelManager = new ChannelManager($this->tmpDir, null, 100);

        return new LogManager($channelManager);
    }

    #[Test]
    public function implementsLoggerInterface(): void
    {
        $manager = $this->createManager();

        self::assertInstanceOf(LoggerInterface::class, $manager);
    }

    #[Test]
    public function infoWritesLog(): void
    {
        $manager = $this->createManager();

        $manager->info('Test info message');

        $logFile = $this->tmpDir . '/app.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Test info message', $content);
    }

    #[Test]
    public function channelReturnsNewInstance(): void
    {
        $manager = $this->createManager();
        $auth = $manager->channel(Channel::Auth);

        self::assertInstanceOf(LogManager::class, $auth);
        self::assertNotSame($manager, $auth);
    }

    #[Test]
    public function channelWritesToCorrectFile(): void
    {
        $manager = $this->createManager();

        $manager->channel(Channel::Auth)->info('Auth message');

        $logFile = $this->tmpDir . '/auth.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Auth message', $content);
    }

    #[Test]
    public function allEightPsr3LevelsWork(): void
    {
        $manager = $this->createManager();

        $manager->emergency('emergency msg');
        $manager->alert('alert msg');
        $manager->critical('critical msg');
        $manager->error('error msg');
        $manager->warning('warning msg');
        $manager->notice('notice msg');
        $manager->info('info msg');
        $manager->debug('debug msg');

        $logFile = $this->tmpDir . '/app.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);

        self::assertStringContainsString('emergency msg', $content);
        self::assertStringContainsString('alert msg', $content);
        self::assertStringContainsString('critical msg', $content);
        self::assertStringContainsString('error msg', $content);
        self::assertStringContainsString('warning msg', $content);
        self::assertStringContainsString('notice msg', $content);
        self::assertStringContainsString('info msg', $content);
        self::assertStringContainsString('debug msg', $content);
    }

    #[Test]
    public function withContextEnrichesLogs(): void
    {
        $manager = $this->createManager();

        $context = new class implements \PHPdot\TraceLog\Log\Context\ContextInterface {
            /** @return array<string, mixed> */
            public function toArray(): array
            {
                return ['trace_id' => 'test-trace-123'];
            }
        };

        $enriched = $manager->withContext($context);
        self::assertNotSame($manager, $enriched);

        $enriched->info('Enriched message');

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('test-trace-123', $content);
    }

    #[Test]
    public function pendingReturnsPendingLog(): void
    {
        $manager = $this->createManager();

        $pending = $manager->pending('info', 'Pending message');

        self::assertInstanceOf(PendingLog::class, $pending);
    }

    #[Test]
    public function logWithStringLevelNameInfo(): void
    {
        $manager = $this->createManager();

        $manager->log('info', 'String level info');

        $logFile = $this->tmpDir . '/app.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('String level info', $content);
    }

    #[Test]
    public function logWithStringLevelNameError(): void
    {
        $manager = $this->createManager();

        $manager->log('error', 'String level error');

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('String level error', $content);
    }

    #[Test]
    public function emergencyWritesToFile(): void
    {
        $manager = $this->createManager();

        $manager->emergency('Emergency happened');

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Emergency happened', $content);
        self::assertStringContainsString('EMERGENCY', $content);
    }

    #[Test]
    public function alertWritesToFile(): void
    {
        $manager = $this->createManager();

        $manager->alert('Alert happened');

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Alert happened', $content);
        self::assertStringContainsString('ALERT', $content);
    }

    #[Test]
    public function criticalWritesToFile(): void
    {
        $manager = $this->createManager();

        $manager->critical('Critical happened');

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Critical happened', $content);
        self::assertStringContainsString('CRITICAL', $content);
    }

    #[Test]
    public function noticeWritesToFile(): void
    {
        $manager = $this->createManager();

        $manager->notice('Notice happened');

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Notice happened', $content);
        self::assertStringContainsString('NOTICE', $content);
    }

    #[Test]
    public function writeRecordBuildsCorrectRecordStructure(): void
    {
        $manager = $this->createManager();

        $manager->writeRecord(\PHPdot\TraceLog\Log\LogLevel::INFO, 'Structured message', ['key' => 'val']);

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);

        $decoded = json_decode(trim($content), true);
        self::assertIsArray($decoded);
        self::assertSame('Structured message', $decoded['message']);
        self::assertSame('INFO', $decoded['level_name']);
        self::assertSame(200, $decoded['level']);
        self::assertSame('app', $decoded['channel']);
        self::assertArrayHasKey('timestamp', $decoded);
        self::assertArrayHasKey('context', $decoded);
    }

    #[Test]
    public function channelScopingPersistsAcrossMultipleCalls(): void
    {
        $manager = $this->createManager();

        $authManager = $manager->channel(Channel::Auth);
        $authManager->info('First auth message');
        $authManager->warning('Second auth message');

        $logFile = $this->tmpDir . '/auth.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('First auth message', $content);
        self::assertStringContainsString('Second auth message', $content);
    }

    #[Test]
    public function customBackedEnumChannelWritesToCorrectFile(): void
    {
        $channelManager = new ChannelManager($this->tmpDir);
        $manager = new LogManager($channelManager);

        $manager->channel(AppChannel::Payment)->info('Payment received');

        $logFile = $this->tmpDir . '/payment.log';
        self::assertFileExists($logFile);
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Payment received', $content);
    }

    #[Test]
    public function builtInAndCustomChannelsCoexist(): void
    {
        $channelManager = new ChannelManager($this->tmpDir);
        $manager = new LogManager($channelManager);

        $manager->channel(Channel::Auth)->info('Auth event');
        $manager->channel(AppChannel::Webhook)->info('Webhook event');

        self::assertFileExists($this->tmpDir . '/auth.log');
        self::assertFileExists($this->tmpDir . '/webhook.log');

        $authContent = file_get_contents($this->tmpDir . '/auth.log');
        $webhookContent = file_get_contents($this->tmpDir . '/webhook.log');

        self::assertIsString($authContent);
        self::assertIsString($webhookContent);
        self::assertStringContainsString('Auth event', $authContent);
        self::assertStringContainsString('Webhook event', $webhookContent);
        self::assertStringNotContainsString('Webhook event', $authContent);
        self::assertStringNotContainsString('Auth event', $webhookContent);
    }
}
