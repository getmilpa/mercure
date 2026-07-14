<p align="center">
  <a href="https://github.com/getmilpa">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-dark.svg">
      <img src="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-light.svg" alt="Milpa" width="300">
    </picture>
  </a>
</p>

# Milpa Mercure

> The **Mercure hub publisher** for the Milpa PHP framework, with **zero package dependencies**. One class, `MercureService`, mints self-signed HS256 JWTs by hand and pushes real-time updates to a [Mercure](https://mercure.rocks/) hub over `curl_*` directly — no HTTP client abstraction, no `milpa/core` at runtime.

[![CI](https://github.com/getmilpa/mercure/actions/workflows/ci.yml/badge.svg)](https://github.com/getmilpa/mercure/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/milpa/mercure.svg)](https://packagist.org/packages/milpa/mercure)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-API%20reference-blue.svg)](https://getmilpa.github.io/mercure/)

`milpa/mercure` is the smallest possible seam onto a [Mercure hub](https://mercure.rocks/):
sign a publisher JWT, `POST` a topic + JSON payload to the hub's `/.well-known/mercure`
endpoint, and mint short-lived subscriber JWTs for the browser's `EventSource` connection.
**No ORM, no event dispatcher, no framework coupling** — construct it with four strings and
call `publish()`.

## Install

```bash
composer require milpa/mercure
```

## Quick example

```php
use Milpa\Mercure\MercureService;

$mercure = new MercureService(
    hubUrl: 'https://hub.internal/.well-known/mercure',      // server-side POST target
    publicUrl: 'https://hub.example.com/.well-known/mercure', // what the browser connects to
    publisherKey: $_ENV['MERCURE_PUBLISHER_JWT_KEY'],
    subscriberKey: $_ENV['MERCURE_SUBSCRIBER_JWT_KEY'],
);

// Server-side: push an update. Mints a 60s publisher JWT, POSTs form-encoded
// topic + JSON data, throws RuntimeException on cURL failure or HTTP >= 400.
$mercure->publish('conversations/42/messages', [
    'event' => 'message.created',
    'body' => 'hello',
]);

// Browser-side: mint a 5-minute subscriber JWT scoped to the topics this
// visitor may listen to, and hand the browser the public URL to connect to.
$jwt = $mercure->generateSubscriberJwt(['conversations/42/messages']);
$publicUrl = $mercure->getPublicUrl();
```

The browser then opens `new EventSource(publicUrl + '?topic=...', { withCredentials: true })`
with `$jwt` set as the `mercureAuthorization` cookie — standard Mercure subscribe flow, nothing
Milpa-specific about it.

## What it does — and doesn't

- **`publish(string $topic, array $data)`** — signs a 1-minute publisher JWT scoped to
  `$topic`, form-encodes `topic` + `json_encode($data)`, and `POST`s it to `hubUrl` with
  `Authorization: Bearer <jwt>`. A `curl_init()` failure, a `curl_exec()` failure, or an HTTP
  status `>= 400` all throw `RuntimeException` — `publish()` never fails silently.
- **`generateSubscriberJwt(array $topics)`** — a 5-minute HS256 JWT whose payload is
  `{"mercure":{"subscribe":$topics},"exp":...}`, signed with `subscriberKey`. This is the
  token a browser presents to subscribe; it grants no publish access.
- **`getPublicUrl()`** — returns the `publicUrl` given to the constructor, unmodified. Kept
  separate from `hubUrl` because the two are commonly different: the server publishes to an
  internal/Docker-network hostname, while the browser subscribes through a public one.

Both JWTs are HS256, hand-assembled from `header.payload.signature` with `hash_hmac()` +
base64url — no JWT library dependency. There is no retry, no queue, and no HTTP client
abstraction to inject: `publish()` talks to `curl_*` directly, by design (see
[Requirements](#requirements)).

## Requirements

- PHP **≥ 8.3** with the **cURL extension** enabled (`ext-curl`)
- Nothing else — `milpa/mercure` has no package dependencies, Milpa or otherwise

## Documentation

**Full API reference: [getmilpa.github.io/mercure](https://getmilpa.github.io/mercure/)** —
generated straight from the source DocBlocks and dressed with the Milpa design system.

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Please report security
issues via [SECURITY.md](SECURITY.md), and note that this project follows a
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

[Apache-2.0](LICENSE) © Rodrigo Vicente - TeamX Agency.

---

Milpa is designed, built, and maintained by **[Rodrigo Vicente - TeamX Agency](https://teamx.agency/?utm_source=github&utm_medium=readme&utm_campaign=milpa&utm_content=mercure)**.
