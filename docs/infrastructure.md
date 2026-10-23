# Infrastructure Modules

Lemonade Framework provides focused infrastructure services through its normal provider and typed
configuration pipeline. Applications choose the services they use; this document describes package
capabilities, not application-specific domain architecture.

## Cache

The cache service exposes `Psr\Cache\CacheItemPoolInterface` and a convenience
`Lemonade\Framework\Cache\CacheManager`. The default file store, plus array and null stores, are
provided by the package.

```yaml
module: cache
config:
  default: file
  stores:
    file:
      path: cache/framework
      prefix: lemonade
      ttl: 300
```

`CacheManager` is available as `CacheManager` and the `cache` alias. It provides `get()`, `put()`,
`remember()`, `rememberForever()`, `has()`, `forget()` and `clear()` while the PSR-6 pool remains
available for integrations that need native cache items.

The file store keeps one versioned `.cache` data envelope per item. Every envelope contains the
cache metadata and serialized PSR-6 value authenticated with HMAC-SHA-256; a value is unserialized
only after the file size, envelope shape, version, pool namespace, cache key and MAC have all been
verified. This preserves round trips for ordinary PHP-serializable values, including enums,
`DateTimeImmutable` and application value objects, without loading a cache file as PHP code.

`app.secret` supplies the integrity root when configured. A zero-configuration application instead
creates one cryptographically random local key below private writable storage. Key creation is
serialized with an OS file lock, and both cache and key files use mode `0600` below directories with
mode `0700`. Configured cache paths must remain inside application storage and outside the public web
root; traversal and symbolic-link components are rejected.

The previous `*.php` file-cache format is intentionally not read or migrated. Deployment cleanup may
delete the old hashed PHP files from the configured cache directory after stopping processes running
the previous framework version. The files contain disposable cache data: do not include, execute or
deserialize them during migration. `clear()` only removes `.cache` files owned by its current pool
namespace, so legacy removal remains an explicit deployment operation.

## Events

`EventDispatcherInterface` is a scoped runtime service backed by `ScopedEventDispatcher`.
`EventListenerRegistry` stores immutable `EventListenerDefinition` values assembled during
bootstrap, and `EventListenerInvoker` resolves each listener class from the active runtime scope.
Listener dependencies can therefore use Request, Command or Job scoped services as appropriate.

Listeners are configured as listener classes with an optional method and priority. Callable
listeners and runtime `addListener()` registration are not supported.

```yaml
module: events
config:
  listeners:
    App\Event\InvoicePaid:
      - listener: App\Listener\SendInvoiceReceipt
        method: __invoke
        priority: 0
```

Dispatch invokes listeners registered for the concrete event class, its parents and implemented
interfaces. `EventDispatcherInterface::dispatch()` is intended for an active Request, Command or
Job scope; resolving it from the root container is not a normal runtime model. The package does
not prescribe event names, transports or application event-sourcing policy.

## Sessions

`SessionServiceProvider` resolves the host application's single `module: session` configuration and
binds its canonical `SessionInterface` and `FlashBagInterface` as request-scoped services. Repeated
resolution inside one request returns the same session identity and storage instance; a later
request receives a fresh object graph. `SessionStorageInterface` is an internal construction
boundary and is intentionally not container-bound. `SessionFactory::create()` can build another
independent `SessionInterface` from an explicit `SessionConfig`; the consumer owns any additional
DI registration. The framework does not keep a named-session registry.

`SessionFactory` supports the `native`, `file`, `database` and `redis` drivers. Database connectivity
is resolved only for the `database` driver. PHP native sessions still use process-global
`session_start()`, `session_name()` and `$_SESSION` state, so consumers that need two concurrently
active physical sessions should use `file`, `database` or `redis` for the additional session.

The configured positive `lifetime` is a shared sliding lifetime for the browser identity and server-side
record. It is always positive, so the session cookie is persistent rather than a browser-session
cookie. Every successful `start()` of a live session refreshes both the cookie expiry and the
server-side file mtime, database `expires_at`, Redis TTL or active native save-handler record. A syntactically
valid cookie whose server-side record is missing, expired or unreadable is not reused: the storage
creates a cryptographically random replacement identity. Cookies use path `/`, `HttpOnly` and
the configured `SameSite` policy, which defaults to `Lax`. `cookie.secure` defaults to `auto` and
uses only the request-scoped trusted-proxy context; `always` unconditionally emits Secure and is
appropriate when a cookie can be created outside an HTTP request. `SameSite=None` requires the
resulting Secure value to be true because browsers reject an insecure `None` cookie. Session code
never reads `$_SERVER` or forwarding headers directly. Starting or changing a session identity
after headers were sent fails explicitly instead of allowing browser and server state to diverge.

```yaml
module: session
config:
  cookie:
    name: LEMONADE_SESSION
    same_site: lax # lax, strict or none
    secure: auto # auto or always
```

Typed configuration rejects non-positive lifetimes, empty or non-canonical drivers, invalid cookie
names, unsupported Secure policies, out-of-range Redis ports, negative Redis database numbers and
non-positive Redis timeouts. Session configuration is resolved during provider bootstrap so these
errors cannot be deferred until the first session access.

The lifecycle operations have distinct meanings:

- `clear()` writes an empty payload but keeps the current identifier, cookie and storage record;
- `destroy()` removes the current record, invalidates the identifier and expires the cookie; a
  later access on the same object starts a new identity;
- `regenerate(false)` copies the payload to a new identifier and leaves the old record replayable
  until its current retention expires;
- `regenerate(true)` copies the payload to a new identifier and invalidates the old record.

Cleanup belongs to each storage instance. No Session CLI command, cron job, scheduler or registry is
required, including for a consumer-owned session returned by `SessionFactory`:

- `native` configures `session.gc_maxlifetime`, a 2/100 request GC probability, strict identifier
  mode and cookie-only transport before `session_start()`. PHP's active save handler performs the
  physical GC in its configured storage; distro `sessionclean` jobs are not part of the contract.
  The active handler also owns the physical write and any timestamp renewal when PHP closes the
  session, normally during request shutdown. `gc_maxlifetime` is a collection threshold, not a
  read-time expiry check or a guarantee of immediate deletion. The framework deliberately leaves
  the native session writable for the traditional PHP request lifetime, so it does not call
  `session_write_close()` early. It does not force `session.save_handler=files`.
- `file` performs a 2/100 opportunistic scan with a private cursor and examines at most 100 session
  files in one selected request. Active read-only sessions have their mtime refreshed. The session
  directory is mode `0700`, files are mode `0600`, and writes use an atomic same-directory
  replacement. The same bounded sweep removes sufficiently old `.session-write-*` files abandoned
  by a process termination while leaving recent writes alone.
- `database` performs a 2/100 opportunistic cleanup. A selected request first selects at most 100
  expired identifiers and then deletes only that still-expired batch; it never issues a global
  unbounded expiry delete on every session start.
- `redis` relies on mandatory per-key TTL. `start()` verifies TTL renewal, and identity invalidation
  verifies that the old key is absent. No application-level Redis sweep is needed.

The 2/100 selection rate combined with the file/database batch size of 100 gives an expected scan
capacity of two expired candidates per request. That is intentionally above the maximum creation
rate of one new session per request while keeping each selected cleanup bounded.

File, database and Redis requests load one payload snapshot. Atomic file replacement prevents
partial-file reads, while concurrent payload changes remain last-writer-wins for all three drivers;
sessions are not a distributed transactional data store.

The portable session payload contract is `null`, scalar values and arrays composed from those
values. Non-native drivers decode with `unserialize(..., ['allowed_classes' => false])`, so storing
arbitrary objects is not a portable promise across drivers. Native PHP serialization behavior is
owned by the active save handler, but applications should follow the same storage-neutral contract.

The framework does not own a database session migration. A consumer-provided session table must
provide this schema contract:

```text
session_id  string, primary key or unique and not null
payload     text/blob large enough for the serialized payload and not null
updated_at  integer timestamp and not null
expires_at  integer timestamp and not null, indexed
```

The unique `session_id` constraint is required for race-safe identity creation, and the
`expires_at` index keeps bounded cleanup and expiry lookup efficient.

Native and file paths are resolved by `ApplicationContext::resolveSessionPath()`. A relative path is
relative to `storage/writable`, so the canonical default is `sessions`, producing
`storage/writable/sessions`; configuring `writable/sessions` would duplicate the writable path
segment. Absolute paths are preserved.

`SessionNamespace` provides `has()`, `get()`, `set()`, `remove()`, `all()` and `clear()` for a logical
key space inside one physical session. Namespaces share the parent cookie, identifier, storage,
retention and regeneration lifecycle; clearing one namespace leaves every other namespace and
top-level session value intact. Framework flash values, CSRF tokens and session-backed old input use
this same primitive while remaining separate logical namespaces.

## Queues

`QueueBusInterface` dispatches object messages to configured handlers. The package offers `sync` and
database-backed transports, delayed dispatch and failed-job storage. The default configuration is
synchronous; applications opt into the database transport explicitly.

The database transport stores a bounded, versioned JSON envelope authenticated with HMAC-SHA-256.
Its HMAC key is derived from `app.secret` with a queue-specific context. Database queue resolution
fails when that secret is absent or empty; applications that use only `sync` need neither a secret
nor message codecs. There is no unsigned mode and no PHP-serialization fallback.

Every database message class needs an explicit stable alias, schema version, and
`MessageCodecInterface` implementation. Register codecs from an application service provider after
the framework providers have registered the queue module:

```php
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Queue\MessageCodecRegistry;

final readonly class ApplicationQueueProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        $container->get(MessageCodecRegistry::class)->register(
            alias: 'invoice.send',
            messageClass: SendInvoice::class,
            codec: new SendInvoiceCodec(),
            version: 1,
        );
    }
}
```

A codec converts its one declared object to JSON-compatible arrays and scalars and reconstructs that
same type from authenticated data. This explicit mapping supports readonly messages, enums,
`DateTimeImmutable`, and value objects without reflective hydration. Objects returned inside the
encoded data are rejected. Handler registration is a separate concern and never adds a class to the
codec allow-list.

Handlers are registered as class strings or legacy callables. Class-string handlers are resolved
through the container when invoked; this is the preferred form for dependency injection. Handler
selection prefers the concrete message class, then parent classes, then implemented interfaces.
Registering the same message class again replaces its prior handler.

`QueueBus` orchestrates transport dispatch and processing. `JobHandlerRegistry` owns this handler
selection; `JobHandlerInvoker` creates the Job scope and resolves a class-string handler from it.
This separation is intentional: a legacy callable remains compatible, but is invoked directly and
is not container-resolved.

```yaml
module: queue
config:
  default: database
  transports:
    - database
  database:
    table: system_queue_job
    failed_table: system_queue_failed_job
    lease_seconds: 300
    max_attempts: 3
  handlers:
    App\Message\SendInvoice: App\Queue\SendInvoiceHandler
```

Queue configuration accepts only the built-in `sync` and `database` transport names. The selected
default must also appear in `transports`; unknown names, an explicitly empty transport list, and a
missing default transport fail during configuration resolution instead of falling back to sync.
Omitting queue configuration entirely retains the zero-configuration synchronous queue.

Create the database tables with `vendor/bin/lemonade queue:install`. Run a worker with
`vendor/bin/lemonade queue:work [queue] [transport] [max] [sleep-ms]`; a worker requires an
asynchronous transport such as `database`. Worker lifecycle and deployment supervision remain
application or operations concerns. There is no application-controlled retry or release mechanism
in the queue contract; a handler exception remains a terminal failure.

The database transport reserves each delivery with a random 256-bit owner token and a lease that
expires after `database.lease_seconds` (300 seconds by default). Queue availability, reservation,
renewal, and failure timestamps use the database clock, so worker host clock differences do not
decide ownership. An expired delivery can be reserved again with a new token. After
`database.max_attempts` reservations (three by default), the next worker moves the job to failed
storage without invoking its handler again.

Acknowledgement, failure, and renewal all require the job id, queue name, current owner token, and a
lease that is still valid according to database time. Losing any of those conditions raises
`QueueLeaseLostException`; the transport never falls back to an id-only update or delete. This fences
the queue row but does not provide exactly-once execution. Handlers must make external side effects
idempotent because a process can finish an external operation and then exit before acknowledgement.

Class-string handlers whose work can outlive the configured lease may inject `QueueLeaseInterface`
from their Job scope and call `renew()` between bounded work units. Renewal cannot revive an expired
lease, and the framework does not run a background heartbeat or interrupt arbitrary PHP code.
Configure a longer lease for a single blocking operation that cannot renew cooperatively.

Database queues support native MySQL, PDO MySQL, and PDO SQLite. SQLite is intended for modest write
concurrency because it serializes writers. Generic ODBC is rejected before queue use: its server-time
and affected-row guarantees are not sufficiently specific for ownership-safe compare-and-set writes.

For an asynchronously dequeued message, success calls `ack()`. A handler failure calls `fail()` and
then rethrows the original handler exception; if `fail()` itself fails, the resulting exception
retains the original handler exception. An `ack()` failure does not call `fail()`.

For an asynchronously dequeued message, a successful handler is acknowledged only after it returns.
When a handler throws, the transport's `fail()` operation runs and the original handler error remains
the primary failure. An `ack()` error is a transport-confirmation error and does not invoke `fail()`.
Every handler invocation runs in an isolated `Job` scope. Class-string handlers can inject the
scope-local `JobContext`; asynchronously dequeued handlers can also inject `QueuedMessage`.
Synchronous dispatch receives its own Job scope and does not reuse an active HTTP request scope.
Legacy callables remain supported, but are not container-resolved and therefore cannot receive these
values through constructor injection.

Malformed, oversized, unsigned, tampered, unknown, unsupported, and legacy PHP-serialized database
payloads are never hydrated. After a worker wins the row reservation, such a poison message is moved
transactionally to the failed-job table and removed from the active queue. The failed record preserves
the original raw payload as opaque diagnostic data; the error description is bounded and never embeds
the payload. Applications displaying failed payloads must continue to treat them as untrusted text.

The database queue format is intentionally incompatible with queue rows created by earlier framework
versions. Deploy it without automatic conversion:

1. Stop old producers and workers.
2. Let trusted old workers drain the existing active queue, or archive and then delete those rows.
3. Deploy the new codecs, stable `APP_SECRET`, framework code, and new producers together.
4. Start workers only after all running processes use the new envelope format.

Do not copy legacy payloads into the new queue or build a runtime unserializing migrator. Any legacy
row left in the active table will be quarantined into failed jobs as opaque data. Rotating
`APP_SECRET` likewise invalidates queued envelopes, so drain the queue before rotating it.

### Migrating an existing database queue to leases

`queue:install` creates fresh lease-aware tables and validates tables that already exist. It never
alters an existing table. A pre-lease table is rejected with an explicit migration error instead of
being reported as ready.

An in-place migration requires a coordinated stop because an old worker acknowledges with an id-only
delete and can otherwise remove a job already reclaimed by a new worker:

1. Stop producers, all old workers, and supervisor restarts. Confirm that no old handler can return.
2. Back up both queue tables.
3. Add the nullable lease columns and the new claim index using the appropriate database migration.
4. Clear legacy reservations only after every old worker is stopped. Preserve `attempts` and payloads.
5. Deploy new producers and workers together, run `queue:install` to validate the schema, and then
   restart processing. Mixed old/new workers are unsupported.

For the default MySQL table name, the schema portion is equivalent to:

```sql
ALTER TABLE system_queue_job
    ADD COLUMN reservation_token CHAR(64) NULL,
    ADD COLUMN reserved_until BIGINT UNSIGNED NULL,
    ADD INDEX idx_queue_claim (queue_name, available_at, reserved_until, id);

UPDATE system_queue_job
SET reserved_at = NULL,
    reservation_token = NULL,
    reserved_until = NULL;
```

For SQLite:

```sql
ALTER TABLE system_queue_job ADD COLUMN reservation_token CHAR(64) NULL;
ALTER TABLE system_queue_job ADD COLUMN reserved_until INTEGER NULL;
CREATE INDEX IF NOT EXISTS idx_queue_claim
    ON system_queue_job (queue_name, available_at, reserved_until, id);

UPDATE system_queue_job
SET reserved_at = NULL,
    reservation_token = NULL,
    reserved_until = NULL;
```

Use the configured table name instead of `system_queue_job`. Register these statements as an explicit
application migration; do not execute them from bootstrap. Keep the existing `APP_SECRET` and codec
registrations while queued HMAC envelopes remain active.

## Outbound HTTP Clients

Outbound clients use `Psr\Http\Client\ClientInterface` (PSR-18). The package contains optional
providers for Guzzle, Symfony HTTP Client and PHP-HTTP cURL transport. Install the selected client
package and register its corresponding provider from an application provider, or bind
`ClientInterface` directly.

The shared `http_client` configuration supports `timeout`, `connect_timeout` and `verify_ssl`:

```yaml
module: http_client
config:
  timeout: 10
  connect_timeout: 5
  verify_ssl: true
```

See [PSR compatibility](psr-compatibility.md) for the supported PSR boundaries and optional Composer
packages.
