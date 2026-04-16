# Development

This document describes how to run the bundle tests locally and how to exercise it end-to-end against the `playground/` Symfony application.

## Prerequisites

- PHP 8.3 or 8.4 (CLI)
- Composer 2.x
- Docker + Docker Compose (for the playground database)

## Bundle

```bash
composer install
composer ci      # runs cs, phpstan (max), and phpunit
```

Individual tools:

```bash
composer cs         # PHP-CS-Fixer dry-run
composer cs-fix     # apply style fixes
composer phpstan    # PHPStan at max level
composer test       # PHPUnit
```

## Playground

The `playground/` directory contains a minimal Symfony application wired to use the bundle through a Composer path repository (`../`). It is not committed — generate and run it locally.

```bash
cd playground
docker compose up -d            # Postgres 16 on 127.0.0.1:5432
composer install
bin/console doctrine:database:create --if-not-exists
bin/console cache:clear

# Dispatch a few demo messages (3 emails, 2 invoices, one failing)
bin/console app:dispatch-demo

# In another terminal, run the worker
bin/console messenger:consume async --time-limit=30

# And a scheduler worker if you want to exercise schedules
bin/console messenger:consume scheduler_default --time-limit=60
```

Useful Symfony debug commands:

```bash
bin/console debug:messenger
bin/console debug:scheduler
bin/console debug:container periscope
bin/console debug:config periscope
```

## End-to-end smoke test

After seeding messages and running a few worker cycles, every `periscope:*` command should produce machine-parseable output and return the expected exit code. The following sequence doubles as a pre-release checklist:

```bash
cd playground
APP_ENV=prod bin/console cache:clear

bin/console app:dispatch-demo
bin/console messenger:consume async --time-limit=3 --limit=10
bin/console messenger:consume scheduler_default --time-limit=3 --limit=5

# Read-only inspection commands — each must exit 0 with at least one row.
APP_ENV=prod bin/console periscope:messages --fields=id,status,class,duration_ms
APP_ENV=prod bin/console periscope:message <uuid>
APP_ENV=prod bin/console periscope:schedules
APP_ENV=prod bin/console periscope:queues
APP_ENV=prod bin/console periscope:health --since=1h --format=ndjson

# Alerting exit code: expected to be 3 when the failure rate is breached.
APP_ENV=prod bin/console periscope:health --since=1h --threshold-failure-rate=0.1; echo "exit=$?"

# Retention cleanup: dry-run then apply.
APP_ENV=prod bin/console periscope:purge --dry-run
APP_ENV=prod bin/console periscope:purge --older-than=30d
```

## Expected directory layout

```
PeriscopeBundle/
├── src/                     # Bundle code (published)
├── config/services.php      # Service wiring (published)
├── tests/                   # PHPUnit tests (published)
├── docs/                    # Developer docs (published)
├── composer.json            # Bundle manifest (published)
├── LICENSE, README.md       # ...
└── playground/              # Local dev app (NOT published, gitignored)
```
