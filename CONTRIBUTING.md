# Contributing to Milpa Mercure

Thanks for your interest in contributing! Milpa Mercure is the Mercure hub publisher
of the Milpa framework — it mints the self-signed JWTs a Mercure hub expects for both
server-side publish and browser-side subscribe, and drives the publish request over
cURL directly. It has **zero package dependencies** beyond PHP itself.

## Getting started

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse src
php tools/validate-docblocks.php
```

These run in CI on PHP 8.3 and 8.4 (alongside `composer validate --strict` and a
`php -l` syntax pass); run them locally before opening a PR.

## Guidelines

- **PHP >= 8.3**, with `declare(strict_types=1);` in every file.
- **Document every public symbol.** A public class/interface/enum/trait or public
  method without a DocBlock summary fails CI (`tools/validate-docblocks.php`).
  Trivial accessors and magic methods are exempt.
- **Respect the zero-dependency boundary.** Milpa Mercure depends on nothing but PHP
  itself — no `milpa/core`, no HTTP client abstraction, no third-party SDK. It talks to
  the Mercure hub with `curl_*` directly and signs JWTs by hand (`hash_hmac` +
  base64url). Keep it that way: a change that would require adding a package
  dependency belongs in a different package, not here.
- **[Conventional Commits](https://www.conventionalcommits.org/)** — releases and
  the CHANGELOG are generated automatically from commit messages. Use
  `feat:` / `fix:` / `docs:` / `chore:` etc.; a breaking change to a public
  interface or capability schema is a `feat!:` / `BREAKING CHANGE:` (bumps MINOR
  while the package is `0.x`, MAJOR once it reaches `1.0`).

## Code style

The whole Milpa family (`milpa/core`, `milpa/http`, `milpa/tool-runtime`,
`milpa/mercure`) shares one coding standard, committed verbatim in every repo as
`.php-cs-fixer.dist.php` and enforced by CI. In short:

- **[PSR-12](https://www.php-fig.org/psr/psr-12/) base**: 4 spaces (never tabs);
  opening braces on the **next line** for classes and methods, on the **same line**
  for control structures; one statement per line.
- **Family deltas on top of PSR-12**: short array syntax (`[]`), one space around
  string concatenation (`$a . $b`), fully-multiline method arguments when split,
  no unused imports, aligned/separated/trimmed PHPDoc tags, trailing commas in
  multiline constructs.

Check and fix locally before pushing:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff   # what CI runs
vendor/bin/php-cs-fixer fix                    # apply
```

Do not tweak `.php-cs-fixer.dist.php` in one package alone — the standard changes
in lockstep across the family or not at all.

## Pull requests

Keep PRs focused, add tests for behavior changes, and make sure the four commands
above are green. A maintainer will review and, once merged to `main`,
release-please will handle versioning.

## License

By contributing, you agree that your contributions are licensed under the
[Apache License 2.0](LICENSE).

---

Milpa is developed and maintained by [TeamX Agency](https://teamx.agency).
