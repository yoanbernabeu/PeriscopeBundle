# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial bundle scaffolding: `PeriscopeBundle` class based on `AbstractBundle`.
- Configuration tree: `storage.connection`, `storage.table_prefix`, `storage.schema`, `transports.include`, `transports.exclude`, `retention.days`, `masking.fields`.
- `PeriscopeIdStamp` envelope stamp and `AddPeriscopeIdStampMiddleware`, wired automatically to every Messenger bus via `prependExtension`.
- Append-only event log: `StorageInterface` + Doctrine DBAL implementation (`DoctrineStorage`), `SchemaProvider` (table prefix + optional Postgres schema) and `SchemaManager` (with dedicated `toCreateSql()` for `--dump-sql`).
- `MessengerEventSubscriber` capturing `SendMessageToTransports`, `WorkerMessageReceived`, `WorkerMessageHandled`, `WorkerMessageFailed` and `WorkerMessageRetried`.
- `SchedulerEventSubscriber` capturing `PreRunEvent`, `PostRunEvent` and `FailureEvent`.
- Payload extraction with case-insensitive field masking and stamp summarisation (bus, sender, retry count, transport message id, handled stamps, `ScheduledStamp`).
- Transport include/exclude filter honoured at ingestion time.
- Generic CLI rendering pipeline: `Formatter\Renderer` consumes a `RowInterface` list and emits `auto|compact|pretty|json|ndjson|yaml`; `Cli\CommonOptions` centralises the parsing of `--format`, `--fields`, `--since`, `--until`, `--limit`, `--offset`.
- Plug-in point `Transport\TransportInspectorInterface` (tagged `periscope.transport_inspector`). First implementation: `MessageCountAwareInspector`, covering every Doctrine/Redis/AMQP transport.
- Scheduler introspection via `Scheduler\ScheduleInspector`, producing `ScheduleDescriptor` entries consumable by the renderer.
- CLI commands:
  - `periscope:install` — idempotent schema creation with `--dump-sql`.
  - `periscope:messages` — filters: status, transport, class, scheduled, since, until, limit, offset.
  - `periscope:message <uuid>` — full timeline of a single message.
  - `periscope:schedules` — every recurring message with next run, filterable via `--schedule`.
  - `periscope:queues` — on-demand depth of every observed Messenger transport.
  - `periscope:purge` — retention-driven cleanup with `--dry-run` and `--older-than`.
  - `periscope:health` — aggregated snapshot with `--threshold-failure-rate` / `--threshold-min-total` exiting with code 3 on breach for alerting scripts.
- Project skeleton: PHPStan 2.x at max level, PHP-CS-Fixer ruleset, PHPUnit 12, GitHub Actions CI matrix (PHP 8.3/8.4, Symfony 7.4.*/8.0.*).
- Exhaustive unit test suite (147 tests, 383 assertions) covering models, stamps, middleware, storage, internal helpers, formatter, CLI option parser, scheduler/transport/health components, and every `periscope:*` command via `CommandTester`.
- Functional test booting a minimal kernel and verifying the container exposes every configuration parameter.
- Local-only playground Symfony application (not committed) with Postgres running in Docker, documented in `docs/DEVELOPMENT.md`. End-to-end validation recorded dispatched → received → handled, received → retried → failed, scheduled_before → scheduled_after cycles; CLI commands verified with correct exit codes and machine-friendly output.
