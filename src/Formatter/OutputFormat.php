<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Formatter;

/**
 * Output formats supported by every `periscope:*` command.
 *
 * The `Auto` format picks between {@see Compact} (non-TTY, agent-friendly) and
 * {@see Pretty} (TTY, human-friendly) based on the terminal capabilities.
 */
enum OutputFormat: string
{
    case Auto = 'auto';
    case Compact = 'compact';
    case Pretty = 'pretty';
    case Json = 'json';
    case Ndjson = 'ndjson';
    case Yaml = 'yaml';
}
