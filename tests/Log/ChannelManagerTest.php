<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\Handler\HandlerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChannelManagerTest extends TestCase
{
    #[Test]
    public function getHandlerReturnsHandlerInterface(): void
    {
        $manager = new ChannelManager(sys_get_temp_dir());

        $handler = $manager->getHandler(Channel::App);

        self::assertInstanceOf(HandlerInterface::class, $handler);
    }

    #[Test]
    public function sameChannelReturnsCachedHandler(): void
    {
        $manager = new ChannelManager(sys_get_temp_dir());

        $first = $manager->getHandler(Channel::App);
        $second = $manager->getHandler(Channel::App);

        self::assertSame($first, $second);
    }

    #[Test]
    public function differentChannelsReturnDifferentHandlers(): void
    {
        $manager = new ChannelManager(sys_get_temp_dir());

        $app = $manager->getHandler(Channel::App);
        $auth = $manager->getHandler(Channel::Auth);

        self::assertNotSame($app, $auth);
    }
}
