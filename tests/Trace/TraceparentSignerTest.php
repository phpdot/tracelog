<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Trace;

use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\Trace\TraceparentSigner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TraceparentSignerTest extends TestCase
{
    private TraceparentSigner $signer;
    private Traceparent $traceparent;

    protected function setUp(): void
    {
        $this->signer = new TraceparentSigner('my-webhook-secret-key');
        $this->traceparent = new Traceparent(
            str_repeat('a', 32),
            str_repeat('b', 16),
        );
    }

    #[Test]
    public function signReturns64CharHexString(): void
    {
        $signature = $this->signer->sign($this->traceparent);

        self::assertSame(64, strlen($signature));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);
    }

    #[Test]
    public function verifyReturnsTrueForValidSignature(): void
    {
        $signature = $this->signer->sign($this->traceparent);

        self::assertTrue($this->signer->verify($this->traceparent, $signature));
    }

    #[Test]
    public function verifyReturnsFalseForTamperedTraceparent(): void
    {
        $signature = $this->signer->sign($this->traceparent);

        $tampered = new Traceparent(
            str_repeat('f', 32),
            str_repeat('b', 16),
        );

        self::assertFalse($this->signer->verify($tampered, $signature));
    }

    #[Test]
    public function verifyReturnsFalseForForgedSignature(): void
    {
        self::assertFalse($this->signer->verify($this->traceparent, str_repeat('0', 64)));
    }

    #[Test]
    public function verifyReturnsFalseForEmptySignature(): void
    {
        self::assertFalse($this->signer->verify($this->traceparent, ''));
    }

    #[Test]
    public function differentSecretsProduceDifferentSignatures(): void
    {
        $signer2 = new TraceparentSigner('different-secret-key');

        $sig1 = $this->signer->sign($this->traceparent);
        $sig2 = $signer2->sign($this->traceparent);

        self::assertNotSame($sig1, $sig2);
    }

    #[Test]
    public function sameInputProducesSameSignature(): void
    {
        $sig1 = $this->signer->sign($this->traceparent);
        $sig2 = $this->signer->sign($this->traceparent);

        self::assertSame($sig1, $sig2);
    }

    #[Test]
    public function signatureChangesWhenSpanIdChanges(): void
    {
        $other = new Traceparent(
            str_repeat('a', 32),
            str_repeat('c', 16),
        );

        $sig1 = $this->signer->sign($this->traceparent);
        $sig2 = $this->signer->sign($other);

        self::assertNotSame($sig1, $sig2);
    }

    #[Test]
    public function emptySecretThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not be empty');

        new TraceparentSigner('');
    }

    #[Test]
    public function fullWebhookFlow(): void
    {
        $signer = new TraceparentSigner('stripe-webhook-secret');

        $outbound = new Traceparent(
            'abc123def456789012345678abcdef00',
            '1234567890abcdef',
        );
        $signature = $signer->sign($outbound);

        $inbound = Traceparent::fromHeader($outbound->toHeader());
        self::assertTrue($signer->verify($inbound, $signature));

        $forged = new Traceparent(
            '00000000000000000000000000000000',
            '0000000000000000',
        );
        self::assertFalse($signer->verify($forged, $signature));
    }

    #[Test]
    public function verifyWithWrongSecretFails(): void
    {
        $signature = $this->signer->sign($this->traceparent);

        $wrongSigner = new TraceparentSigner('wrong-secret');
        self::assertFalse($wrongSigner->verify($this->traceparent, $signature));
    }
}
