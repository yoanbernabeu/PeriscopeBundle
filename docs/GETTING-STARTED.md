# Getting Started with PeriscopeBundle

> Written for **v0.1.0-alpha** — validated against the playground scaffold.

New to PeriscopeBundle? This guide takes you from a fresh Symfony project to your first `periscope:messages` row in under ten minutes.

For contributors working on the bundle itself, see [`docs/DEVELOPMENT.md`](./DEVELOPMENT.md).

---

## 1. Prerequisites

Before you begin, make sure you have:

| Requirement | Minimum version | Notes |
|---|---|---|
| PHP | 8.4+ | `php -v` to verify |
| Symfony | 7.4 LTS or 8.x | |
| Doctrine DBAL | 3.8+ or 4.x | |
| Database | PostgreSQL (recommended), MySQL/MariaDB, or SQLite | Docker is an easy way to get one running locally |
| Composer | 2.x | |
| Symfony CLI | latest | |

If you don't have a Symfony project yet, create one:

```bash
symfony new my-app --version=7.4
cd my-app
```

---

## 2. Install

```bash
composer require yoanbernabeu/periscope-bundle
```

> **Stability note:** until the first stable release, add the flag:
> ```bash
> composer require yoanbernabeu/periscope-bundle:@dev
> ```

---

## 3. Pick a storage connection

### Option A — Default (same database as your app)

No configuration needed. Periscope will use your application's default Doctrine connection and store its tables alongside your existing ones.

### Option B — Dedicated connection (recommended for production)

A dedicated connection isolates Periscope's writes from your main database.

**Step 1** — Add a second connection in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        connections:
            default:
                url: '%env(DATABASE_URL)%'
            periscope:
                url: '%env(PERISCOPE_DATABASE_URL)%'
```

**Step 2** — Tell Periscope to use it in `config/packages/periscope.yaml`:

```yaml
periscope:
    storage:
        connection: periscope
        table_prefix: 'periscope_'
```

**Step 3** — Add the second URL in your `.env`:

```bash
PERISCOPE_DATABASE_URL="postgresql://app:password@127.0.0.1:5432/periscope"
```

> **Tip:** For development, Option A is perfectly fine. Switch to Option B when you go to production.


---

## 4. Run periscope:install

First, make sure your database exists:

```bash
bin/console doctrine:database:create --if-not-exists
```

Then create the Periscope tables:

```bash
bin/console periscope:install
```

You should see:

```
Periscope schema created (6 statement(s) executed).
```

> **Note:** This command is idempotent — you can run it multiple times without any risk. To preview the SQL statements without executing them, use `--dump-sql`:
> ```bash
> bin/console periscope:install --dump-sql
> ```


---

## 5. Dispatch something

First, install the required packages:

```bash
composer require symfony/messenger
composer require symfony/doctrine-messenger
```

Then configure the `async` transport in `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        transports:
            async: 'doctrine://default'
        routing:
            'App\Message\SendEmailMessage': async
```

Then create the message class in `src/Message/SendEmailMessage.php`:

```php
<?php

namespace App\Message;

final class SendEmailMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
    ) {}
}
```

And its handler in `src/MessageHandler/SendEmailMessageHandler.php`:

```php
<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendEmailMessageHandler
{
    public function __invoke(SendEmailMessage $message): void
    {
        // Simulate sending an email
        echo sprintf(
            "Sending email to %s with subject: %s\n",
            $message->to,
            $message->subject
        );
    }
}
```
> **Note:** If `make:command` is not available, install the maker bundle first:
> ```bash
> composer require symfony/maker-bundle --dev
> ```

Then create the dispatch command:
```bash
bin/console make:command app:send-email
```

Replace the content of `src/Command/SendEmailCommand.php` with:

```php
<?php

namespace App\Command;

use App\Message\SendEmailMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:send-email',
    description: 'Dispatch a test SendEmail message',
)]
class SendEmailCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bus->dispatch(new SendEmailMessage(
            to: 'hello@example.com',
            subject: 'Welcome to PeriscopeBundle',
            body: 'This is a test message.',
        ));

        $output->writeln('Email message dispatched!');

        return Command::SUCCESS;
    }
}
```


Now dispatch the message:

```bash
bin/console app:send-email
```

You should see:

```
Email message dispatched!
```

---

## 6. Consume one message

Run the worker to consume the message from the queue:

```bash
bin/console messenger:consume async --time-limit=5
```

You should see:
```
Sending email to hello@example.com with subject: Welcome to PeriscopeBundle
```

> **Note:** `--time-limit=5` stops the worker automatically after 5 seconds. In production, you would run the worker without this flag and manage it with a process manager like Supervisor.


---

## 7. Read your first periscope:messages row

> Watch the full demo: `asciinema play docs/images/periscope-demo.cast`

Now check that Periscope recorded the message:

```bash
bin/console periscope:messages
```

You should see a table with your message:

| ID | STATUS | CLASS | ATTEMPTS | TRANSPORT | HANDLER | DURATION_MS | LAST_SEEN_AT |
|---|---|---|---|---|---|---|---|
| 019dae87-babd-7906-a7cd-a0bc67c16deb | succeeded | SendEmailMessage | 1 | async | SendEmailMessageHandler::__invoke | 35 | 2026-04-21T07:24:37+00:00 |


For machine-friendly output, use `--format=ndjson`:

```bash
bin/console periscope:messages --format=ndjson
```

You should see:

```json
{"id":"019dae87-babd-7906-a7cd-a0bc67c16deb","status":"succeeded","class":"SendEmailMessage","attempts":1,"transport":"async","handler":"SendEmailMessageHandler::__invoke","duration_ms":35,"last_seen_at":"2026-04-21T07:24:37+00:00"}
```

> **Tip:** Pipe the output to `jq` for filtering:
> ```bash
> bin/console periscope:messages --format=ndjson | jq '.status'
> ```

---

## 8. Drill down into a single message

To see the full timeline of a single message, use its ID:

```bash
bin/console periscope:message <uuid>
```

Replace `<uuid>` with the ID from the previous step. You should see:

| AT | EVENT | TRANSPORT | HANDLER | DURATION_MS | ERROR |
|---|---|---|---|---|---|
| 2026-04-21T05:33:44+00:00 | dispatched | async | | | |
| 2026-04-21T07:24:37+00:00 | received | async | | | |
| 2026-04-21T07:24:37+00:00 | handled | async | SendEmailMessageHandler::__invoke | 35 | |

The timeline shows every step the message went through:

- **dispatched** — the message was sent to the queue
- **received** — the worker picked it up
- **handled** — the handler processed it successfully

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `periscope:messages` returns no rows | The worker has not consumed any message yet | Run `bin/console messenger:consume async --time-limit=5` |
| `periscope:messages` returns no rows | `periscope:install` was not run | Run `bin/console periscope:install` |
| Schedules do not show in `periscope:schedules` | No Symfony Scheduler configured | Add a `RecurringMessage` with `#[AsSchedule]` in your app |
| Transport depth unknown in `periscope:queues` | Transport is not `MessageCountAware` | Use Doctrine, Redis or AMQP transport |
| `periscope:install` fails | Database does not exist | Run `bin/console doctrine:database:create --if-not-exists` first |


---

## 10. Where to go next

Now that you have your first `periscope:messages` row, here is what you can explore next:

**Check the health of your queues:**
```bash
bin/console periscope:health
```

**Check the depth of your transports:**
```bash
bin/console periscope:queues
```

**Filter messages by status:**
```bash
bin/console periscope:messages --status=failed
```

**Configure retention to auto-purge old data:**
```yaml
# config/packages/periscope.yaml
periscope:
    retention:
        days: 30
```

**Mask sensitive fields in payloads:**
```yaml
# config/packages/periscope.yaml
periscope:
    masking:
        fields: [email, password, token]
```

**Limit observed transports:**
```yaml
# config/packages/periscope.yaml
periscope:
    transports:
        include: [async, high_priority]
```

> For the full list of available commands and options, see the [README](../README.md).