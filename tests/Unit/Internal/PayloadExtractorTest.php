<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Internal;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Internal\PayloadExtractor;

#[CoversClass(PayloadExtractor::class)]
final class PayloadExtractorTest extends TestCase
{
    public function testExtractPublicScalarProperties(): void
    {
        $message = new class {
            public string $to = 'user@example.com';

            public int $attempts = 3;

            public bool $urgent = true;

            public ?string $note = null;
        };

        $payload = (new PayloadExtractor())->extract($message);

        self::assertSame([
            'to' => 'user@example.com',
            'attempts' => 3,
            'urgent' => true,
            'note' => null,
        ], $payload);
    }

    public function testMaskingIsCaseInsensitive(): void
    {
        $message = new class {
            public string $email = 'user@example.com';

            public string $password = 'hunter2';

            public string $TOKEN = 'xyz';
        };

        $payload = (new PayloadExtractor(maskedFields: ['password', 'token']))->extract($message);

        self::assertSame('user@example.com', $payload['email']);
        self::assertSame('***', $payload['password']);
        self::assertSame('***', $payload['TOKEN']);
    }

    public function testNestedArraysAreMaskedRecursively(): void
    {
        $message = new class {
            /** @var array<string, mixed> */
            public array $credentials = [
                'username' => 'admin',
                'password' => 'hunter2',
                'nested' => ['api_key' => 'secret'],
            ];
        };

        $payload = (new PayloadExtractor(maskedFields: ['password', 'api_key']))->extract($message);

        self::assertIsArray($payload['credentials']);
        self::assertSame('admin', $payload['credentials']['username']);
        self::assertSame('***', $payload['credentials']['password']);
        self::assertIsArray($payload['credentials']['nested']);
        self::assertSame('***', $payload['credentials']['nested']['api_key']);
    }

    public function testNestedObjectsAreUnwound(): void
    {
        $inner = new class {
            public string $region = 'eu-west-1';
        };

        $message = new class($inner) {
            public function __construct(public object $zone)
            {
            }
        };

        $payload = (new PayloadExtractor())->extract($message);

        self::assertIsArray($payload['zone']);
        self::assertSame(['region' => 'eu-west-1'], $payload['zone']);
    }

    public function testDatetimesAreFormattedAsAtom(): void
    {
        $moment = new DateTimeImmutable('2026-04-16 12:00:00', new DateTimeZone('UTC'));

        $message = new class($moment) {
            public function __construct(public DateTimeImmutable $when)
            {
            }
        };

        $payload = (new PayloadExtractor())->extract($message);

        self::assertSame('2026-04-16T12:00:00+00:00', $payload['when']);
    }

    public function testBackedEnumsBecomeTheirScalarValue(): void
    {
        $message = new class {
            public Priority $priority = Priority::High;
        };

        $payload = (new PayloadExtractor())->extract($message);

        self::assertSame('high', $payload['priority']);
    }

    public function testPrivatePropertiesAreIgnored(): void
    {
        $message = new class {
            public string $public = 'shown';

            private string $hidden = 'secret'; // @phpstan-ignore property.onlyWritten
        };

        $payload = (new PayloadExtractor())->extract($message);

        self::assertArrayHasKey('public', $payload);
        self::assertArrayNotHasKey('hidden', $payload);
    }
}

enum Priority: string
{
    case High = 'high';
    case Low = 'low';
}
