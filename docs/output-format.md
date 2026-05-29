# fold-magento MVP Output Format

fold-magento writes a portable scan bundle. The storegraph files are machine-readable and intended to remain stable enough for reports, rule packs, diffs, and LLM-assisted review.

Default location:

```txt
var/xray/scans/{scan_id}/
```

Default MVP files:

```txt
manifest.json
storegraph.entities.jsonl
storegraph.relationships.jsonl
config.schema.jsonl
config.state.jsonl
orphans.jsonl
scan-errors.json
summary.md
```

## Scan ID

The default scan ID is UTC timestamp-based:

```txt
YYYYMMDDTHHMMSSZ
```

Example:

```txt
20260529T153012Z
```

## `manifest.json`

The manifest describes the scan bundle and points to the files inside it.

Example shape:

```json
{
  "schema_version": "0.1.0",
  "scanner": "magento-xray",
  "profile": "minimal",
  "scan_id": "20260529T153012Z",
  "generated_at": "2026-05-29T15:30:12+00:00",
  "started_at": "2026-05-29T15:30:12+00:00",
  "counts": {
    "entities": 10,
    "relationships": 9,
    "config_schema": 1200,
    "config_state": 350,
    "orphans": 12,
    "errors": 0
  },
  "files": {
    "manifest": "manifest.json",
    "entities": "storegraph.entities.jsonl",
    "relationships": "storegraph.relationships.jsonl",
    "config_schema": "config.schema.jsonl",
    "config_state": "config.state.jsonl",
    "orphans": "orphans.jsonl",
    "scan_errors": "scan-errors.json",
    "summary": "summary.md"
  },
  "summary": {},
  "metadata": {}
}
```

## JSONL convention

Files ending in `.jsonl` use newline-delimited JSON:

- one JSON object per line
- no enclosing array
- UTF-8 JSON
- safe for streaming and diffing

This is preferred for large Magento stores because it avoids holding or parsing a giant JSON document when downstream tools only need selected records.

## `storegraph.entities.jsonl`

Entities describe things discovered in the store.

Example entity:

```json
{"kind":"entity","type":"module","id":"Magento_Catalog","label":"Magento_Catalog","attributes":{"enabled":true}}
```

Required fields:

| Field | Type | Description |
| --- | --- | --- |
| `kind` | string | Always `entity` |
| `type` | string | Entity type, such as `platform`, `module`, `composer_package`, `website`, `store_group`, or `store_view` |
| `id` | string | Stable ID within the entity type |
| `label` | string or null | Human-readable label |
| `attributes` | object | Entity-specific metadata |

## `storegraph.relationships.jsonl`

Relationships describe connections between entities.

Example relationship:

```json
{"kind":"relationship","type":"contains","from":{"type":"website","id":"1"},"to":{"type":"store_group","id":"1"},"attributes":{}}
```

Required fields:

| Field | Type | Description |
| --- | --- | --- |
| `kind` | string | Always `relationship` |
| `type` | string | Relationship type, such as `contains`, `installed_on`, or `depends_on` |
| `from` | object | Source entity reference with `type` and `id` |
| `to` | object | Target entity reference with `type` and `id` |
| `attributes` | object | Relationship-specific metadata |

## `config.schema.jsonl`

Configuration schema records represent known Admin/system configuration fields discovered from Magento configuration metadata.

Common fields:

| Field | Description |
| --- | --- |
| `path` | Full config path, such as `web/secure/base_url` |
| `section` | Admin config section |
| `group` | Admin config group |
| `field` | Admin config field |
| `label` | Human-facing label when available |
| `frontend_type` | Field input type when available |
| `source_model` | Source model class when available |
| `backend_model` | Backend model class when available |
| `sort_order` | Field sort order when available |

## `config.state.jsonl`

Configuration state records represent rows from `core_config_data` with sensitive values masked.

Common fields:

| Field | Description |
| --- | --- |
| `config_id` | Source row ID from `core_config_data` |
| `path` | Config path |
| `scope` | `default`, `websites`, or `stores` |
| `scope_id` | Scope ID |
| `value` | Masked or unmasked value |
| `is_sensitive` | Whether the path was treated as sensitive |
| `value_summary` | Type and length metadata for the original value |
| `is_override` | Whether the value is stored outside default scope |

Sensitive values are represented as:

```txt
[masked]
```

## `orphans.jsonl`

Orphan records identify config paths present in `core_config_data` but absent from the discovered Admin configuration schema.

These are not always errors. They may represent:

- removed extensions
- legacy settings
- hidden or dynamic config paths
- config written by modules that do not expose Admin fields
- custom project configuration

Common fields:

| Field | Description |
| --- | --- |
| `path` | Config path |
| `row_count` | Number of rows found for this path |
| `scopes` | Scopes where the path appears |
| `first_seen_scope` | First observed scope |
| `first_seen_scope_id` | First observed scope ID |

## `scan-errors.json`

Scans should fail gracefully where possible. Collector failures are written here instead of aborting the entire scan.

Example shape:

```json
{
  "count": 1,
  "errors": [
    {
      "collector": "CommerceXray\\MagentoXray\\Scanner\\ConfigSchemaCollector",
      "message": "Unable to read config schema",
      "recorded_at": "2026-05-29T15:30:12+00:00"
    }
  ]
}
```

## `summary.md`

The Markdown summary is a human-readable overview. It is useful for quick review, but it is not the canonical data source.

Downstream tools should prefer:

- `manifest.json` for bundle metadata
- JSONL files for scan records
- `scan-errors.json` for error inspection

## Stability guidance

For the MVP, treat these as the most stable contracts:

- file names
- JSONL-per-record format
- entity `kind`, `type`, `id`, `label`, `attributes`
- relationship `kind`, `type`, `from`, `to`, `attributes`
- masked value token: `[masked]`

New fields may be added over time. Consumers should ignore fields they do not understand.
