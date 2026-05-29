# fold-magento Privacy Model

fold-magento is designed to be a private, local, read-only discovery scanner for Magento Open Source and Adobe Commerce stores.

The MVP trust model is simple:

- It runs from the Magento CLI.
- It reads Magento application state and selected database tables.
- It writes a local scan bundle to `var/xray` by default.
- It does not upload scan data.
- It does not require a SaaS account.
- It does not collect customer records, orders, carts, quotes, payments, or session data.
- It masks sensitive configuration values before writing output.

## What the MVP reads

The minimal profile focuses on technical discovery data:

- Magento version and runtime mode
- PHP version
- Enabled and disabled module inventory
- Composer package inventory from `composer.lock`
- Website, store group, and store view structure
- Admin configuration schema metadata
- `core_config_data` configuration rows
- Scope-level configuration overrides
- Likely orphaned configuration paths

## What the MVP writes

A scan creates a local portable bundle:

```txt
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

The bundle is intended for technical discovery, scoping, migration planning, debugging, and expert review.

## Sensitive value masking

Configuration values are passed through `Security/SecretMasker.php` before they are written.

Paths containing sensitive fragments are masked, including common patterns such as:

- `password`
- `secret`
- `private_key`
- `api_key`
- `access_key`
- `token`
- `oauth`
- `crypt/key`
- payment provider paths
- SMTP, FTP, and SFTP paths

Masked values are written as:

```txt
[masked]
```

Empty sensitive values remain empty so the scan can distinguish “configured but blank” from “configured and masked.”

## Data that should not be collected in the MVP

The MVP should not read or write:

- customer records
- customer addresses
- orders
- invoices
- shipments
- credit memos
- carts or quotes
- payment transaction data
- sessions
- admin users
- password hashes
- access tokens from integration tables
- logs containing personal data

## Sharing scan bundles

Before sharing a scan bundle with an agency, freelancer, consultant, client, or LLM tool:

1. Review `summary.md` for high-level context.
2. Review `config.state.jsonl` for environment-specific values.
3. Confirm sensitive values are masked.
4. Remove any custom values your organization considers confidential.
5. Share only with parties who need the technical discovery data.

fold-magento reduces exposure by masking likely secrets, but a scan bundle may still reveal private business and implementation details such as installed vendors, integrations, store structure, feature flags, and operational complexity.

## Network behavior

The open-source MVP is intentionally local-first. It should not make outbound network requests as part of `xray:scan` or `xray:doctor`.

Future hosted or SaaS features should remain explicit opt-in workflows and should not change the local scanner’s default privacy posture.
