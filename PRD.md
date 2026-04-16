# PeriscopeBundle — PRD

> **Tagline** : *See through the surface of your queues.*
> Bundle Symfony d'observabilité pour Symfony Messenger & Symfony Scheduler.

---

## 🎯 Vision produit

**Problème** : Symfony Messenger et Scheduler sont puissants mais **opaques en production**. Quand un message échoue, quand un cron ne tourne pas, quand la latence explose… on découvre le problème tard, et on manque d'outils unifiés pour :
- **Monitorer en prod** (santé, métriques, alertes)
- **Auditer l'historique** (retrouver un message traité il y a N jours)
- **Debug en dev et prod** (voir en temps réel ce qui passe)

**Solution** : un **bundle Symfony** qui s'installe en 5 minutes, expose un **dashboard web** complet, et couvre tout le cycle de vie Messenger + Scheduler sans service externe à maintenir.

**Positionnement** : à mi-chemin entre le [Symfony Profiler](https://symfony.com/doc/current/profiler.html) (trop orienté dev, pas prod) et des solutions d'observabilité génériques type Sentry/Datadog (chères, pas spécifiques Messenger).

---

## 👤 Personas

1. **Dev Symfony** qui bosse sur une app en dev/staging et veut comprendre ce que fait Messenger sans multiplier les `dump()`.
2. **Tech lead / SRE** qui veut un dashboard en prod pour surveiller la santé des workers et être alerté en cas de dérive.
3. **Product owner / support** qui a besoin de retrouver un message traité ("est-ce que l'email de commande #42 a bien été envoyé ?") pour l'audit / la résolution d'incident.

---

## 🧱 Architecture technique

### Décisions validées

| Décision | Choix | Rationale |
|----------|-------|-----------|
| **Type de solution** | Bundle Symfony full (pas de service externe) | Adoption massive, zéro friction d'install, écosystème Doctrine/Security/Twig réutilisé |
| **Storage** | Pluggable via `StorageInterface`, **Doctrine par défaut** | Future-proof sans surcoût de dév immédiat |
| **DB cibles** | Postgres (prod recommandé), SQLite (dev), MySQL (supporté) | Couvre 99% des setups Symfony |
| **Connexion DB** | **Dédiée configurable** (option : même DB que l'app, ou isolée) | Isoler la charge d'ingestion de la DB métier |
| **Interface** | **CLI uniquement** (Symfony Console) | Pas de front-end. Observabilité terminal-first, pipeable, intégrable dans scripts / CI / tmux |
| **Volume cible** | 10k – 100k messages / jour | Dimensionne le design (partitioning, purge, index) sans surdimensionner |
| **Scope v1** | **Observabilité pure (lecture seule)** | MVP livrable rapidement, sécurité simplifiée (pas d'actions destructrices) |

### Schéma d'architecture (high-level)

```
┌────────────────────────────────────────────────────────┐
│                  Application Symfony                   │
│                                                        │
│  ┌──────────────┐         ┌──────────────────────┐     │
│  │  Messenger   │─────────▶│  Periscope Listener │     │
│  │  (Workers)   │  events  │  (Event Subscriber) │     │
│  └──────────────┘         └──────────┬───────────┘     │
│                                      │                 │
│  ┌──────────────┐                    │                 │
│  │  Scheduler   │────────────────────┤                 │
│  │   (tasks)    │   events           │                 │
│  └──────────────┘                    ▼                 │
│                            ┌──────────────────┐        │
│                            │ StorageInterface │        │
│                            └────────┬─────────┘        │
│                                     │                  │
│                            ┌────────▼──────────┐       │
│                            │ DoctrineStorage   │       │
│                            │ (connexion dédiée)│       │
│                            └────────┬──────────┘       │
│                                     │                  │
│  ┌─────────────────────────┐        │                  │
│  │  bin/console periscope:*│◀───────┤                  │
│  │   (Symfony Console CLI) │        │                  │
│  └─────────────────────────┘        │                  │
└─────────────────────────────────────┼──────────────────┘
                                      ▼
                          ┌───────────────────────┐
                          │  Periscope Database   │
                          │  (Postgres / SQLite)  │
                          └───────────────────────┘
```

### Points clés de design

- **Ingestion via events natifs Symfony** : `WorkerMessageReceivedEvent`, `WorkerMessageHandledEvent`, `WorkerMessageFailedEvent`, `SendMessageToTransportsEvent` pour Messenger ; events Scheduler équivalents.
- **Écriture asynchrone possible** (batch / transport dédié) pour ne pas pénaliser la perf des workers.
- **Partitioning des tables** par date dès le départ (pg_partman ou partitioning natif).
- **Index costauds** sur `(created_at, status)`, `(message_class, status)`, `(transport)`.
- **Purge automatique** configurable (rétention N jours).
- **Connexion Doctrine dédiée** configurable via `doctrine.dbal.periscope_connection`.

---

## 📦 Scope V1 — Observabilité (lecture seule)

### Messenger

- ✅ **M1** — Liste des messages traités avec filtres (`periscope:messages` : `--status`, `--transport`, `--class`, `--scheduled`, `--since`, `--until`, `--limit`, `--offset`)
- ✅ **M2** — Détail d'un message : timeline complète via `periscope:message <uuid>` (payload, stacktrace, durée, transport, handler, stamps)
- 🟡 **M4** — Métriques agrégées : snapshot via `periscope:health` (total, succeeded, failed, running, pending, failure_rate). Latences p50/p95/p99 à compléter en v2.

### Scheduler

- ✅ **S1** — Liste des schedules configurés (`periscope:schedules` : nom, classe, trigger, prochaine exécution, provider)
- ✅ **S2** — Historique des exécutions passées (capturé par `SchedulerEventSubscriber` → `scheduled_before` / `scheduled_after` / `scheduled_failed`, consultable via `periscope:messages --scheduled=true`)

### Transverse

- ~~**T1** — Authentification / restriction par rôle Symfony~~ → **obsolète** (CLI only : accès shell = auth)
- ✅ **T5** — Rétention configurable + purge (`periscope:purge` avec `--older-than` et `--dry-run`)

### Bonus livrés au-delà du scope v1

- ✅ **periscope:queues** — Queue depth on-demand (tous les transports `MessageCountAware` : Doctrine, Redis, AMQP)
- ✅ **periscope:health** — Rapport agrégé + threshold check avec exit code 3 dédié à l'alerting scripté

---

## 🚧 Hors scope V1 — roadmap future

### V2 — Actions (écriture)

- **M5** — Retry manuel d'un message en échec (un par un)
- **M6** — Retry en masse (tous les échecs d'un type)
- **M7** — Suppression / archivage de messages
- **S3** — Trigger manuel d'un schedule depuis l'UI
- **S4** — Pause / reprise d'un schedule
- **T2** — Alerting (webhook Slack/Discord sur taux d'échec > seuil)

### V3 — Avancé

- **M3** — Recherche full-text dans les payloads (Meilisearch / Postgres tsvector)
- **T3** — Export CSV / JSON pour audit
- **T4** — API REST (lecture) pour intégrations externes
- Storage backend **ClickHouse** pour les utilisateurs à gros volumes
- Mode **OpenTelemetry exporter** pour intégration avec stack observabilité existante

---

## 🖥️ Interface CLI (agent-first)

Toute l'observabilité passe par des **commandes Symfony Console** — pas d'UI web.

### 🤖 Philosophie : "agent-first, human-friendly"

Les **consommateurs principaux** sont les **agents de code** (Claude Code, Cursor, Copilot Workspace, etc.) qui lisent le terminal. L'humain reste un usage légitime mais secondaire.

**Conséquences design :**
- **Non-interactif par défaut** : jamais de prompt, jamais de confirmation bloquante, jamais de "press any key"
- **Output compact et structuré par défaut** (agents paient à la ligne/token)
- **Pas de couleurs / spinners / ASCII art** par défaut (détection TTY auto : piped → machine, tty interactif → pretty)
- **Schéma de sortie stable et versionné** (les agents se cassent si le format change)
- **Codes de retour explicites** : `0` OK, `1` pas de data, `2` erreur, `3` seuil dépassé
- **Limites par défaut** : `--limit=20` implicite, pour ne pas exploser le contexte agent
- **Filtres time-windowed par défaut** : `--since=1h` implicite

### Commandes v1 pressenties

```bash
# Messages
bin/console periscope:messages [--status=failed] [--transport=async] [--since=1h] [--limit=20]
bin/console periscope:message <id>
bin/console periscope:metrics [--transport=async] [--period=24h]

# Scheduler
bin/console periscope:schedules
bin/console periscope:schedule <name>

# Maintenance
bin/console periscope:purge [--older-than=30d] [--dry-run]

# Santé / alerting scripté
bin/console periscope:health [--threshold-failure-rate=5]  # exit 3 si dépassé
```

### Flags universels sur toutes les commandes

| Flag | Défaut | Description |
|------|--------|-------------|
| `--format=table\|json\|ndjson\|yaml\|compact` | `compact` (non-tty) / `table` (tty) | Format de sortie |
| `--fields=id,status,handler` | all | Sélection de colonnes (réduit les tokens) |
| `--limit=N` | 20 | Limite du nombre de résultats |
| `--since=1h` / `--until=5m` | `--since=1h` | Fenêtre temporelle |
| `--no-color` | auto (TTY) | Force désactivation des couleurs |
| `--interactive` / `--pretty` | off | Force le mode humain (tables riches, couleurs) |
| `--quiet` | off | Silence tout sauf le résultat |
| `--summary` | off | Compte / agrégats uniquement, pas les détails |

### Format `compact` (défaut non-tty, optimisé agent)

Exemple pour `periscope:messages --status=failed` :

```
ID  CLASS                       HANDLER           FAILED_AT            ERROR
42  App\Message\SendEmail       EmailHandler      2026-04-16 14:22:11  Mailer timeout
43  App\Message\ProcessInvoice  InvoiceHandler    2026-04-16 14:23:02  DB deadlock
```

Dense, parseable, prévisible. Pas de bordures ASCII, pas de couleurs, une ligne = une entrée.

### Format `ndjson` (pour pipe vers jq / scripts)

```json
{"id":42,"class":"App\\Message\\SendEmail","handler":"EmailHandler","failed_at":"2026-04-16T14:22:11Z","error":"Mailer timeout"}
{"id":43,"class":"App\\Message\\ProcessInvoice","handler":"InvoiceHandler","failed_at":"2026-04-16T14:23:02Z","error":"DB deadlock"}
```

### Mode interactif (opt-in via `--interactive` ou TTY auto)

- Tables riches avec couleurs (Symfony Console `Table`)
- Timestamps relatifs ("3 min ago")
- Tronquage intelligent des payloads longs
- Suggestion de commandes liées en fin de sortie

---

## 🔒 Sécurité

- **Accès = accès shell** (CLI only) : pas de surface d'attaque web.
- Attention aux payloads sensibles : option de **masquage de champs** (email, password, token) configurable en YAML pour éviter la fuite dans les logs.
- Pas d'actions destructrices en v1 = surface de risque minimale.

---

## 📊 Performance & scalabilité

- **Cible v1** : 10k-100k messages/jour → Postgres sur machine modeste suffit.
- **Design pour tenir 1M+ msg/jour** via :
  - Écriture batch / async
  - Partitioning par date
  - Index bien pensés
  - Purge automatique
- **Échappatoire** : si utilisateur atteint les limites du Doctrine storage → implémenter `StorageInterface` avec ClickHouse (v3).

---

## 🛠️ Stack technique

| Composant | Choix |
|-----------|-------|
| Langage | PHP 8.3+ |
| Framework | Symfony 7.4 LTS + Symfony 8.x ✅ |
| ORM | Doctrine ORM / DBAL |
| Interface | **Symfony Console (CLI only)** ✅ |
| ~~CSS~~ | ~~N/A~~ |
| Tests | PHPUnit + Symfony test-pack |
| CI | GitHub Actions |
| Distribution | Packagist |
| License | **MIT** ✅ |

---

## 📦 Distribution

- Package Packagist : **`yoanbernabeu/PeriscopeBundle`**
- Installation :
  ```bash
  composer require yoanbernabeu/periscope-bundle
  php bin/console doctrine:migrations:migrate
  # ajouter la route dans routes.yaml
  ```
- Docker-compose d'exemple fourni pour self-host avec Postgres dédié.
- Recipe Symfony Flex à soumettre à terme.

---

## 🗓️ Roadmap indicative

### Phase 0 — Setup (1-2 semaines)
- Repo GitHub, squelette bundle, CI, tests
- Choix final du stack CSS / asset mapper
- Structure DB + migrations

### Phase 1 — MVP observabilité (4-6 semaines)
- Ingestion Messenger (events)
- Storage Doctrine
- UI : liste messages + détail
- Métriques de base
- Scheduler : liste + historique
- Auth
- Purge

### Phase 2 — Polish & release 1.0
- Docs complètes
- Docker-compose d'exemple
- Démo en ligne
- Article de blog + annonce communauté Symfony

### Phase 3+ — V2 / V3
- Actions (retry, etc.)
- Alerting
- Full-text search
- ClickHouse backend

---

## ❓ Questions ouvertes à trancher

- [x] ~~Nom Packagist disponible ?~~ → **`yoanbernabeu/PeriscopeBundle`** (validé)
- [x] ~~Licence~~ → **MIT** (validé)
- [x] ~~Stack CSS~~ → **Obsolète** : pas de front, CLI only ✅
- [x] ~~Format de sortie par défaut~~ → **Hybride** : `compact` non-TTY, `pretty` TTY, `--format=ndjson\|json\|yaml` opt-in ✅
- [x] ~~Versions Symfony supportées~~ → **Symfony 7.4 LTS + Symfony 8.x** (validé)
- [x] ~~Nom des tables en DB~~ → **Préfixe configurable** (défaut `periscope_`) + **schéma Postgres dédié** en option (Postgres uniquement) ✅

  Exemple config :
  ```yaml
  periscope:
    storage:
      table_prefix: 'periscope_'   # défaut
      # OU, pour Postgres uniquement :
      schema: 'periscope'          # override : pas de prefix, tables dans schéma dédié
  ```
- [x] ~~Support multi-transports Messenger~~ → **Scope configurable + regroupement par message logique** (PeriscopeIdStamp) ✅

  - Par défaut : écoute tous les transports async (whitelist/blacklist possible via config YAML)
  - Middleware injecte un `PeriscopeIdStamp` à l'émission, propagé à travers les retries
  - Timeline complète d'un message via `periscope:message <uuid>` (toutes tentatives, tous transports)
  - Fallback pour messages sans stamp (émis avant install ou depuis autre système) : regroupement par signature (class + payload hash)

  Exemple config :
  ```yaml
  periscope:
    transports:
      include: ['async', 'failed', 'high_priority']  # ou 'all' (défaut)
      exclude: ['sync']
  ```
- [x] ~~Scheduler events~~ → **Piggyback Messenger + inspection active des `ScheduleProviderInterface`** ✅ (spike terminé — voir section "Spike technique" plus bas)
- [x] ~~Queue depth~~ → **On-demand + polling optionnel** ✅

  - `periscope:queues` → temps réel, toujours dispo, zéro overhead (interroge directement les transports)
  - `periscope:poll` → optionnel, à ajouter au Scheduler pour alimenter l'historique et activer trending/alertes
  - **Transports supportés v1** : Doctrine, Redis, AMQP/RabbitMQ
  - Autres transports (SQS, Beanstalkd…) → v2+

---

## 🔬 Spike technique — Symfony 7.4 Messenger & Scheduler

*Spike réalisé en amont via context7 sur la doc officielle Symfony 7.4.*

### Events Messenger exposés (v1 confirmée)

Tous dans `Symfony\Component\Messenger\Event\` :

| Event | Déclenché | Utilité Periscope |
|-------|-----------|-------------------|
| `SendMessageToTransportsEvent` | avant envoi vers transport | Capte les dispatchs outbound |
| `MessageSentToTransportsEvent` | après envoi | Confirmation d'émission |
| `WorkerMessageReceivedEvent` | worker reçoit un message | Début de traitement |
| `WorkerMessageHandledEvent` | handler a terminé avec succès | Durée + statut OK |
| `WorkerMessageFailedEvent` | handler a échoué | Stacktrace + statut KO |
| `WorkerMessageRetriedEvent` | retry programmé | **Feature clé** : détection des retries |
| `WorkerRateLimitedEvent` | message rate-limité | Observabilité du throttling |
| `WorkerStartedEvent` / `WorkerStoppedEvent` / `WorkerRunningEvent` | cycle de vie worker | Heartbeat workers (v2) |

### Events Scheduler exposés (v1 confirmée)

Tous dans `Symfony\Component\Scheduler\Event\` :

| Event | Déclenché | Utilité Periscope |
|-------|-----------|-------------------|
| `PreRunEvent` | avant exécution d'une `RecurringMessage` | Timestamp de déclenchement |
| `PostRunEvent` | après exécution réussie | Durée + résultat |
| `FailureEvent` | exécution en échec | Stacktrace |

**Accès** : via `EventSubscriberInterface` standard (simple) OU via `ScheduleProviderInterface::getSchedule()` avec `->before()`, `->after()`, `->onFailure()`.

### Détection des messages d'origine Scheduler

**Confirmé** : Symfony attache automatiquement un **`ScheduledStamp`** aux messages émis par le Scheduler (via `RedispatchMessage`).

```php
// Dans un listener Messenger de Periscope :
if (null !== $envelope->last(ScheduledStamp::class)) {
    // Ce message vient du Scheduler → marquer comme tel en DB
}
```

### Énumération des schedules configurés

- Les `ScheduleProviderInterface` sont enregistrés via l'attribut `#[AsSchedule('name')]` (défaut : `'default'`)
- Symfony fournit déjà la commande `bin/console debug:scheduler` → **inspiration directe pour notre `periscope:schedules`**
- `debug:scheduler` affiche : Trigger (cron / intervalle), Provider, Next Run
- Periscope doit injecter un `ServiceLocator` taggé `scheduler.schedule_provider` (ou équivalent) pour lister les schedules à la demande
- Option `--date=YYYY-MM-DD` pour calculer les prochaines exécutions à une date donnée (à reprendre)

### Stamps Messenger utiles à capturer

- `BusNameStamp` — quel bus a dispatché
- `HandledStamp` — handler + valeur de retour (multiples si plusieurs handlers)
- `TransportMessageIdStamp` — ID côté transport (utile pour lien avec logs RabbitMQ/Redis)
- `ReceivedStamp` — le message a été reçu via transport (distinguer sync vs async)
- `DelayStamp` — délai d'exécution configuré
- `ScheduledStamp` — message déclenché par Scheduler (cf. section au-dessus)
- Custom `PeriscopeIdStamp` (à créer) — identité logique stable à travers les retries

### Implications pour l'implémentation v1

1. **Event Subscriber unique** côté Messenger qui écoute les 5 events principaux (Received / Handled / Failed / Retried / Sent)
2. **Event Subscriber unique** côté Scheduler qui écoute PreRun / PostRun / Failure
3. **Middleware Messenger** qui injecte `PeriscopeIdStamp` à l'émission (si absent) et le propage
4. **Service `ScheduleInspector`** qui liste les `ScheduleProviderInterface` pour `periscope:schedules`
5. **Pas besoin de hacker les internals** → toute l'API est publique et stable en 7.4

✅ **Conclusion spike** : la faisabilité est **confirmée avec API publique stable**. Aucun monkey-patching nécessaire. Bon pour attaquer le code.

---

## 📚 Inspirations / benchmarks

- [Laravel Horizon](https://laravel.com/docs/horizon) — référence UI/UX pour les queues Laravel
- [Sentry](https://sentry.io) — observabilité générique, design dashboard
- [Symfony Profiler](https://symfony.com/doc/current/profiler.html) — pour la partie dev-time
- [Telescope (Laravel)](https://laravel.com/docs/telescope) — bundle d'observabilité in-app
- [RabbitMQ Management UI](https://www.rabbitmq.com/management.html) — pour l'inspection de transport

---

*Document vivant — à mettre à jour au fil des décisions.*
*Dernière mise à jour : 2026-04-16*
