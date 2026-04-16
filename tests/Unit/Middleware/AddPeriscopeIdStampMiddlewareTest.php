<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use YoanBernabeu\PeriscopeBundle\Middleware\AddPeriscopeIdStampMiddleware;
use YoanBernabeu\PeriscopeBundle\Stamp\PeriscopeIdStamp;

#[CoversClass(AddPeriscopeIdStampMiddleware::class)]
final class AddPeriscopeIdStampMiddlewareTest extends TestCase
{
    public function testAddsStampToFreshEnvelope(): void
    {
        $middleware = new AddPeriscopeIdStampMiddleware();

        $envelope = $middleware->handle(Envelope::wrap(new stdClass()), $this->stack());

        self::assertNotNull($envelope->last(PeriscopeIdStamp::class));
    }

    public function testDoesNotOverrideExistingStamp(): void
    {
        $middleware = new AddPeriscopeIdStampMiddleware();
        $existing = PeriscopeIdStamp::generate();

        $envelope = $middleware->handle(Envelope::wrap(new stdClass(), [$existing]), $this->stack());

        self::assertSame($existing, $envelope->last(PeriscopeIdStamp::class));
    }

    public function testDoesNotAddStampToReceivedMessage(): void
    {
        $middleware = new AddPeriscopeIdStampMiddleware();

        $envelope = $middleware->handle(
            Envelope::wrap(new stdClass(), [new ReceivedStamp('async')]),
            $this->stack(),
        );

        self::assertNull($envelope->last(PeriscopeIdStamp::class));
    }

    public function testPreservesStampOnReceivedMessageWhenAlreadyPresent(): void
    {
        $middleware = new AddPeriscopeIdStampMiddleware();
        $existing = PeriscopeIdStamp::generate();

        $envelope = $middleware->handle(
            Envelope::wrap(new stdClass(), [new ReceivedStamp('async'), $existing]),
            $this->stack(),
        );

        self::assertSame($existing, $envelope->last(PeriscopeIdStamp::class));
    }

    private function stack(): StackInterface
    {
        return new StackMiddleware();
    }
}
