<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Internal;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use Stringable;
use UnitEnum;

/**
 * Produces a JSON-serialisable snapshot of a Messenger message payload,
 * masking any field whose name (case-insensitive) is configured as sensitive.
 *
 * The extractor only inspects public properties of objects — it never touches
 * private state, never calls getters, and therefore never triggers unintended
 * side-effects on DTOs.
 */
final readonly class PayloadExtractor
{
    /**
     * @param list<string> $maskedFields field names to replace with '***'
     */
    public function __construct(private array $maskedFields = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $message): array
    {
        return $this->snapshotObject($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotObject(object $object): array
    {
        $data = [];
        $reflection = new ReflectionClass($object);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            $value = $property->isInitialized($object) ? $property->getValue($object) : null;

            $data[$name] = $this->isMasked($name) ? '***' : $this->normalize($value);
        }

        return $data;
    }

    private function normalize(mixed $value): mixed
    {
        if (\is_scalar($value) || null === $value) {
            return $value;
        }

        if (\is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = \is_string($key) && $this->isMasked($key)
                    ? '***'
                    : $this->normalize($item);
            }

            return $normalized;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (\is_object($value)) {
            return $this->snapshotObject($value);
        }

        return null;
    }

    private function isMasked(string $name): bool
    {
        $lower = strtolower($name);
        foreach ($this->maskedFields as $masked) {
            if ($lower === strtolower($masked)) {
                return true;
            }
        }

        return false;
    }
}
