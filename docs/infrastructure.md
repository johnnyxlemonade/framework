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

## Events

`EventDispatcherInterface` is backed by `InMemoryEventDispatcher`. Listeners can be configured by
event class or registered programmatically. Listener classes are resolved through the container and
callable listeners are supported.

```yaml
module: events
config:
  listeners:
    App\Event\InvoicePaid:
      - App\Listener\SendInvoiceReceipt
```

Programmatic `addListener()` also accepts a priority. Dispatch includes listeners registered for the
concrete event class, its parents and implemented interfaces. The package does not prescribe event
names, transports or application event-sourcing policy.

## Queues

`QueueBusInterface` dispatches object messages to configured handlers. The package offers `sync` and
database-backed transports, serialized messages, delayed dispatch and failed-job storage. The default
configuration is synchronous; applications opt into the database transport explicitly.

```yaml
module: queue
config:
  default: database
  transports:
    - database
  database:
    table: system_queue_job
    failed_table: system_queue_failed_job
  handlers:
    App\Message\SendInvoice: App\Queue\SendInvoiceHandler
```

Create the database tables with `vendor/bin/lemonade queue:install`. Run a worker with
`vendor/bin/lemonade queue:work [queue] [transport] [max] [sleep-ms]`; a worker requires an
asynchronous transport such as `database`. Worker lifecycle, deployment supervision and retry policy
remain application or operations concerns.

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
