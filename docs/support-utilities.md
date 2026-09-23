# Support Utilities

Support utilities are small package-level building blocks. They do not impose application content
policy or replace domain services.

## Slugger

`Lemonade\Framework\Support\Slug\Slugger` creates URL-friendly ASCII slugs.

```php
use Lemonade\Framework\Support\Slug\Slugger;

$slugger = new Slugger();

$slug = $slugger->slug('Žluťoučký kůň');
// zlutoucky-kun

$shortSlug = $slugger->slug('Jak navrhnout přehlednou administraci', 80);
$germanSlug = $slugger->slug('Straße', 120, 'de');
```

Its public API is:

```php
slug(string $text, int $maxLength = 120, string $locale = Slugger::DEFAULT_LOCALE): string
supportedLocales(): array
```

`DEFAULT_LOCALE` is `cs`. `supportedLocales()` returns the explicit locale maps available to
applications, including `cs`, `de`, `pl`, `ru`, `uk`, `tr`, `vi`, `ar`, `fa` and `sk`.

Slugger is URLify-inspired and uses curated transliteration maps. It is a best-effort ASCII slug
generator, not a universal Unicode transliterator: unsupported characters are discarded and an input
containing no supported characters can produce an empty string. The result is lowercase and contains
only `[a-z0-9-]`, without leading, trailing or repeated hyphens. A non-positive `$maxLength` returns
an empty string.

The locale argument selects a preferred transliteration map. Unknown locale strings are handled
safely, but applications should use `supportedLocales()` when they need an explicit supported-locale
list.

## Other Utilities

The `Support` namespace also contains environment and base-URL helpers, escaping and formatting
helpers, XML stream writing and clock abstractions. These are intentionally small helpers; use
application services for domain-specific formatting, identifiers and content rules.
