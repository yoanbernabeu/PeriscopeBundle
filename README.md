# PeriscopeBundle

> See through the surface of your queues.

Agent-first CLI observability for [Symfony Messenger] and [Symfony Scheduler].

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)

## Overview

PeriscopeBundle records every Messenger message and Scheduler run that flows through your Symfony application and exposes them through a suite of `bin/console periscope:*` commands. Output is designed to be:

- **Machine-friendly by default** (compact tabular text when piped), so coding agents and scripts can consume it cheaply;
- **Human-friendly on demand** (rich tables, colors) when run in an interactive terminal;
- **Strictly structured** via `--format=ndjson|json|yaml` for pipelines.

No web UI. No prompts. No surprises.

## Requirements

- PHP 8.4+
- Symfony 7.4 LTS or 8.x
- Doctrine DBAL 3.8+ or 4.x
- A database supported by the Doctrine storage backend (PostgreSQL recommended, MySQL/MariaDB, SQLite)

## Installation

```bash
composer require yoanbernabeu/periscope-bundle
php bin/console periscope:install
```

> New here? Start with the **[Getting Started guide](docs/GETTING-STARTED.md)**.

## CLI commands

| Command | Description | Exit codes |
|---|---|---|
| `periscope:install` | Create the Periscope schema on the configured connection. Idempotent; `--dump-sql` prints the statements without executing. | 0 |
| `periscope:messages` | List observed Messenger/Scheduler messages. Supports `--status`, `--transport`, `--class`, `--scheduled`, `--since`, `--until`, `--limit`, `--offset`, `--format`, `--fields`. | 0 results / 1 no result / 2 invalid input |
| `periscope:message <uuid>` | Full event timeline of a single message identified by its periscope id. | 0 / 1 unknown id / 2 invalid input |
| `periscope:schedules` | Every recurring message configured in the application, with its next run. `--schedule=name` filters. | 0 / 1 none / 2 |
| `periscope:queues` | On-demand depth of every observed Messenger transport. | 0 / 1 no transport / 2 |
| `periscope:purge` | Delete events older than the retention window. `--older-than=7d` overrides, `--dry-run` previews. | 0 / 2 |
| `periscope:health` | Aggregated snapshot and threshold check, designed for cron/alerting scripts. `--threshold-failure-rate` and `--threshold-min-total` exit with code 3 on breach. | 0 / 2 / 3 |

Every command supports `--format=auto|compact|pretty|json|ndjson|yaml`. `auto` picks `pretty` on a TTY and `compact` otherwise, which is what agents and pipes expect.

## Status

Early development — the bundle is not yet functional. The product definition lives in [`PRD.md`](./PRD.md); releases are tracked in [`CHANGELOG.md`](./CHANGELOG.md).

## Development

A local Symfony application with Postgres running in Docker is used to exercise the bundle end-to-end. The scaffold lives under `playground/` and is intentionally not committed — each contributor generates it locally. See [`docs/DEVELOPMENT.md`](./docs/DEVELOPMENT.md) once it is in place.

Quality tooling:

```bash
composer ci          # full pipeline (cs, phpstan, phpunit)
composer phpstan     # PHPStan at max level
composer cs          # PHP-CS-Fixer (dry-run)
composer cs-fix      # apply style fixes
composer test        # PHPUnit
```

## License

MIT — see [`LICENSE`](./LICENSE).

[Symfony Messenger]: https://symfony.com/doc/current/messenger.html
[Symfony Scheduler]: https://symfony.com/doc/current/scheduler.html
