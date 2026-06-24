# request-insurance

[![QA](https://github.com/cego/request-insurance/actions/workflows/quality-assurance.yml/badge.svg)](https://github.com/cego/request-insurance/actions/workflows/quality-assurance.yml)

# Supported versions

| Package version | PHP versions supported | Status
|----------|------------------------|---|
| ^1       | ^7.4,^8.0              | Security and bug fixes only
| ^2       | ^8.3                   | Security and bug fixes only
| ^3       | ^8.3                   | Active development

## Partitioning & the exceptions tables

### Overview

On MySQL/MariaDB and PostgreSQL the `request_insurances` and `request_insurance_logs` tables are RANGE-partitioned by `created_at`, and retention drops whole partitions instead of deleting rows one chunk at a time — far cheaper on large, high-throughput tables.

For that to be safe, a partition must contain only `COMPLETED` rows by the time it ages out. So request insurances that leave the success path — `FAILED` and `ABANDONED` — are moved into separate, plain **exceptions tables** (`request_insurances_failed` / `request_insurance_logs_failed`), in the spirit of Laravel's `failed_jobs` table. The bulk of the data (the `COMPLETED` firehose) is never moved or deleted row-by-row; it simply ages out when its partition is dropped.

On SQLite and other unsupported drivers the main tables stay plain and retention falls back to row-based deletes; the exceptions tables still apply.

### Lifecycle

- A request that **fails** (or is **abandoned**) is moved out of the partitioned main tables into the exceptions tables, together with its logs, in one transaction.
- **Retrying** a failed/abandoned request restores it into the main table as `READY` with a fresh `created_at` (so it lands in a current partition). Its historical logs remain in the exceptions logs table as an audit record.
- The web UI is unaffected: route-model binding, the listing, retry, abandon and edit all transparently span both the main and the exceptions tables.

### Configuration

```php
// Exceptions table names (default to "{table}_failed" / "{table_logs}_failed").
'table_failed'      => null,
'table_failed_logs' => null,

'partitioning' => [
    // Partition size: 'daily' | 'weekly' | 'monthly'  (default: 'daily')
    'granularity' => env('REQUEST_INSURANCE_PARTITION_GRANULARITY', 'daily'),

    // Number of future partitions to pre-create ahead of now  (default: 7)
    'precreate_ahead' => (int) env('REQUEST_INSURANCE_PARTITION_PRECREATE_AHEAD', 7),
],
```

Retention reuses the existing `cleanUpKeepDays` setting (default `14` days): main partitions whose entire range falls outside the window are dropped, and aged `ABANDONED` rows are removed from the exceptions tables. `FAILED` rows are kept until a human resolves them. Partition pre-creation and dropping are handled by the existing scheduled `clean:request-insurances` command.

| Config key | Env var | Default |
|---|---|---|
| `partitioning.granularity` | `REQUEST_INSURANCE_PARTITION_GRANULARITY` | `daily` |
| `partitioning.precreate_ahead` | `REQUEST_INSURANCE_PARTITION_PRECREATE_AHEAD` | `7` |
| `cleanUpKeepDays` | _(none)_ | `14` |

### Migration

Running `artisan migrate` applies `2026_06_22_000000_partition_request_insurance_tables` automatically. It:

1. Creates the exceptions tables, shaped like the main tables.
2. Moves any existing `FAILED`/`ABANDONED` rows (and their logs) into the exceptions tables.
3. On supported drivers, converts the main tables to RANGE partitioning by `created_at` with a composite primary key `(id, created_at)` (on MySQL/MariaDB `created_at` becomes `datetime(6) NOT NULL`, required by `RANGE COLUMNS`). Only the small set of in-flight rows is copied into the new partitioned tables; `COMPLETED` rows stay in the renamed `*_legacy` tables and age out there — the bulk of historical data is never copied.

The `*_legacy` tables are safe to drop manually once the retention window has passed. `down()` throws — the migration is not automatically reversible.

### Retention guard

When the cleaner drops an aged partition it first verifies the partition holds only `COMPLETED` rows. A non-`COMPLETED` row in an aged partition means an extraction was missed (or a request is genuinely stuck), so the cleaner throws `PartitionNotDroppableException` rather than silently dropping a row that still needs attention.

### Breaking changes

- **Composite primary key.** The primary key on the main tables is now `(id, created_at)`. Raw SQL or query builders that filter by `id` alone are no longer partition-pruned and should include a `created_at` predicate.
- **`created_at` column type (MySQL/MariaDB).** Converted from `timestamp` to `datetime(6) NOT NULL`. Applications that stored or compared explicit `NULL` must be updated.
- **FAILED/ABANDONED rows live in the exceptions tables.** External tooling that reads `FAILED`/`ABANDONED` rows directly from `request_insurances` must look in `request_insurances_failed`. The package's own models and UI handle this transparently.
- **Tables renamed on migration.** Pre-migration `COMPLETED` rows are preserved in the `*_legacy` tables until you drop them.
