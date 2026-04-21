# Transi-Store Translation Provider

Provides [Transi-Store](https://transi-store.com) integration for Symfony Translation.

## Installation

```bash
composer require transi-store/translation-provider
```

## DSN example

```
# .env
TRANSI_STORE_DSN=transi-store://API_KEY@default/ORG_SLUG/PROJECT_SLUG
```

where:

- `API_KEY` is the API key generated from your Transi-Store organization settings
- `ORG_SLUG` is your Transi-Store organization slug
- `PROJECT_SLUG` is your Transi-Store project slug

The `default` host resolves to `transi-store.com`. You may replace it with a
custom host if you run Transi-Store behind your own domain.

## How it works

Each Transi-Store _file_ maps to a Symfony translation _domain_. The provider
fetches the project metadata (`GET /api/orgs/{org}/projects/{project}`) to
build this mapping from the `filePath` declared for each file (e.g.
`translations/messages.<lang>.yaml` ↔ domain `messages`).

Translations are exchanged using the XLIFF format.

### Supported operations

- **read**: downloads XLIFF translations per locale and domain.
- **write**: uploads the catalogue as XLIFF per locale and domain.
- **delete**: **not supported** — calling it raises a `RuntimeException`.

## Configuration (Symfony)

### 1. Enable the bundle

If you use Symfony Flex, the bundle is enabled automatically. Otherwise,
register it manually in `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    TransiStore\TranslationProvider\TransiStoreTranslationProviderBundle::class => ['all' => true],
];
```

The bundle registers `TransiStoreProviderFactory` as a service tagged with
`translation.provider_factory`, so nothing else is needed in `services.yaml`.

### 2. Declare the provider

```yaml
# config/packages/translation.yaml
framework:
  translator:
    providers:
      transi_store:
        dsn: "%env(TRANSI_STORE_DSN)%"
        locales: ["en", "fr"]
        domains: ["messages"]
```

### ICU Support

To use ICU message format, make sure your file paths include the `+intl-icu`, but before the `<lang>` placeholder, e.g. `translations/messages+intl-icu.<lang>.yaml`.
But in your domain, you should omit the `+intl-icu` part, e.g. `messages` AND you should add the `--intl-icu --force` option to the command.
If you don't, then Symfony will not suffix the file with `+intl-icu`.

## Resources

- [Transi-Store API documentation](https://transi-store.com/api/doc.json)
- [Symfony Translation Providers](https://symfony.com/doc/current/translation.html#installing-and-configuring-a-third-party-provider)
