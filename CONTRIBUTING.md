# Contributing to PeriscopeBundle

Thanks for taking the time to look at the code — pull requests and issues are welcome.

## Local setup

```bash
composer install
composer ci            # cs + phpstan (max) + phpunit
```

For end-to-end development against a real Postgres instance, generate the local playground (it is not committed):

```bash
cd playground
docker compose up -d
composer install
bin/console doctrine:database:create --if-not-exists
bin/console periscope:install
bin/console app:dispatch-demo
bin/console messenger:consume async --time-limit=3
bin/console periscope:messages
```

The full smoke-test sequence is documented in [`docs/DEVELOPMENT.md`](./docs/DEVELOPMENT.md).

## Quality bar

Every pull request must keep the following signals green:

- `composer cs` — PHP-CS-Fixer (Symfony + PHP 8.3 rulesets)
- `composer phpstan` — PHPStan at `level: max`
- `composer test` — PHPUnit; every new class ships with unit tests
- GitHub Actions CI matrix (PHP 8.3 / 8.4 × Symfony 7.4.* / 8.0.*)

## Guiding principles

- **Agent-first CLI**: compact, machine-parseable output by default; pretty tables only on an interactive TTY.
- **Append-only storage**: every Messenger / Scheduler event is a new row; aggregations happen at read time.
- **Opt-in extension points**: new transports plug in by implementing `Transport\TransportInspectorInterface` and tagging the service `periscope.transport_inspector`. New storage backends plug in behind `Storage\StorageInterface`.
- **Observability only**: Periscope intentionally does not reimplement features that already exist in Symfony Messenger (automatic retries, `messenger:failed:retry`). When in doubt, wrap rather than duplicate.

## Commit messages

Short, imperative, scoped to the area that changes. Examples:

- `feat(storage): add ClickHouse adapter behind StorageInterface`
- `fix(formatter): stringify DateTimeInterface values in pretty mode`
- `chore(ci): bump PHPStan to 2.1`
