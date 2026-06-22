# request-insurance

[![QA](https://github.com/cego/request-insurance/actions/workflows/quality-assurance.yml/badge.svg)](https://github.com/cego/request-insurance/actions/workflows/quality-assurance.yml)

# Supported versions

| Package version | PHP versions supported | Status
|----------|------------------------|---|
| ^1       | ^7.4,^8.0              | Security and bug fixes only
| ^2       | ^8.3                   | Security and bug fixes only
| ^3       | ^8.3                   | Active development

## Partitioning

### Overview

On MySQL/MariaDB and PostgreSQL, the `request_insurances` and `request_insurance_logs` tables are RANGE-partitioned by `created_at`. Retention is performed by dropping entire partitions instead of row-by-row deletes, which is faster and produces less I/O on large tables. On SQLite and other unsupported drivers the tables remain plain and row-based retention continues unchanged.

### Configuration

Publish the config file and adjust the `partitioning` block:

```php
'partitioning' => [
    // Partition size: 'daily' | 'weekly' | 'monthly'  (default: 'daily')
    'granularity' => env('REQUEST_INSURANCE_PARTITION_GRANULARITY', 'daily'),

    // Number of future partitions to pre-create ahead of now  (default: 7)
    'precreate_ahead' => (int) env('REQUEST_INSURANCE_PARTITION_PRECREATE_AHEAD', 7),
],
```

The retention window is shared with the existing `cleanUpKeepDays` setting (default `14` days). Partitions whose entire range falls outside that window are dropped.

| Config key | Env var | Default |
|---|---|---|
| `partitioning.granularity` | `REQUEST_INSURANCE_PARTITION_GRANULARITY` | `daily` |
| `partitioning.precreate_ahead` | `REQUEST_INSURANCE_PARTITION_PRECREATE_AHEAD` | `7` |
| `cleanUpKeepDays` | _(none)_ | `14` |

### Migration

Running `artisan migrate` applies `2026_06_22_000000_partition_request_insurance_tables` automatically. The migration:

1. Renames the existing `request_insurances` table to `request_insurances_legacy` (and `request_insurance_logs` to `request_insurance_logs_legacy`) — an atomic rename with no window where the canonical name is absent.
2. Creates new partitioned tables in their place with a composite primary key `(id, created_at)`. On MySQL/MariaDB the `created_at` column is converted to `datetime(6) NOT NULL` (required by `RANGE COLUMNS`).
3. Copies only non-terminal (non-COMPLETED, non-ABANDONED) rows from the legacy tables into the new partitioned tables. Terminal rows stay in `*_legacy` — zero copy for the bulk of historical data.
4. Pre-creates the initial forward partitions so inserts immediately after cutover have a target.

The `*_legacy` tables are safe to drop manually once you have confirmed they are no longer needed (e.g. after the retention window has passed and all active rows have settled).

`down()` throws a `RuntimeException` — the migration is not automatically reversible. The pre-migration data is preserved in the `*_legacy` tables and can be restored manually if required.

### Partition lifecycle command

```
artisan request-insurance:manage-partitions
```

This command pre-creates upcoming partitions (up to `precreate_ahead` units ahead of now) and drops partitions older than the `cleanUpKeepDays` retention window. It guards against dropping parent partitions that still contain non-terminal rows.

The service provider auto-schedules this command to run **hourly** (with overlap protection), so no consumer action is required. If you manage the scheduler yourself outside of Laravel's built-in schedule runner, ensure this command runs at least once per day.

### Breaking changes

Consumers upgrading to this version must be aware of the following:

- **Composite primary key.** The primary key on `request_insurances` and `request_insurance_logs` is now `(id, created_at)`. Raw SQL or query builders that filter by `id` alone are no longer partition-pruned and should include a `created_at` predicate to avoid full-table scans across all partitions.
- **Tables renamed on migration.** The existing tables are renamed to `request_insurances_legacy` and `request_insurance_logs_legacy` during the migration. Any external tooling (e.g. direct SQL, reporting queries, custom Eloquent models) that references the original table names will break until the migration is applied.
- **`created_at` column type change (MySQL/MariaDB).** The `created_at` column is converted from `timestamp` to `datetime(6) NOT NULL`. Applications that stored or compared explicit `NULL` in this column must be updated.
