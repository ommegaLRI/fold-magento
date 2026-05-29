# Fold Magento

FOld Magento is a private, read-only discovery scanner for Magento Open Source and Adobe Commerce.

It runs locally, does not upload data, masks sensitive values in scan output, and generates a portable technical map of the store for discovery, scoping, audits, migrations, upgrades, debugging, and rebuild planning.

This repository currently contains the **Tier 1 MVP scaffold**:

- Magento module registration
- Composer package definition
- CLI command registration
- `bin/magento xray:doctor`
- `bin/magento xray:scan --profile=minimal`

The scan runner, collectors, output bundle writers, config intelligence, and reports are added in later MVP tiers.

## Package

```bash
composer require commerce-xray/magento-xray
```

For local development, place this module at:

```text
app/code/CommerceXray/MagentoXray
```

Then enable it:

```bash
bin/magento module:enable CommerceXray_MagentoXray
bin/magento setup:upgrade
bin/magento cache:clean
```

## Commands

### Doctor

```bash
bin/magento xray:doctor
```

Runs basic environment checks:

- PHP version
- Magento root readability
- `app/etc` readability
- `var` writability
- `composer.lock` readability

### Scan

```bash
bin/magento xray:scan --profile=minimal
```

Optional output path:

```bash
bin/magento xray:scan --profile=minimal --output=var/xray
```

In Tier 1, the command validates the profile and output directory. The full scan pipeline is intentionally added in the next implementation tiers.

## MVP target output bundle

The stable MVP scan bundle will be:

```text
var/xray/scans/{scan_id}/
├─ manifest.json
├─ storegraph.entities.jsonl
├─ storegraph.relationships.jsonl
├─ config.schema.jsonl
├─ config.state.jsonl
├─ orphans.jsonl
├─ scan-errors.json
└─ summary.md
```

## Privacy principles

fold-magento is designed around trust:

- Runs locally
- Read-only by design
- No SaaS upload in the open-source scanner
- No customer, quote, order, invoice, payment, or shipment data collection for the minimal MVP profile
- Sensitive configuration values are masked before they are written to scan output
- Output is portable and inspectable

## MVP scope

The first useful scanner profile is:

```bash
bin/magento xray:scan --profile=minimal
```

The minimal profile is intended to collect:

- platform identity
- Magento version
- PHP version
- runtime mode
- enabled and disabled modules
- Composer package inventory
- website / store / store-view tree
- admin configuration schema
- current configuration state
- scope-level overrides
- `core_config_data` rows
- orphan config paths
- masked sensitive values

## License

MIT