<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Formatter;

/**
 * A row that can be emitted to any output format supported by {@see Renderer}.
 *
 * Implementations expose a flat, column-indexed representation composed only
 * of scalars (or null) so that every format — compact text, JSON, ndjson,
 * YAML, or a Symfony Console table — can render them without further
 * conversion.
 */
interface RowInterface
{
    /**
     * @return array<string, scalar|null>
     */
    public function toColumns(): array;
}
