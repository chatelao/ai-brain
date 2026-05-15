# Concept: Database Upgrades and Migrations

This document outlines a robust strategy for deploying new releases with database changes using versioned SQL patches. The goal is to make the upgrade process as seamless and automated as possible.

## Objectives

- **Robustness**: Ensure migrations are applied reliably and in the correct order.
- **Ease of Use**: Minimize manual intervention during deployment.
- **Traceability**: Maintain a clear history of applied changes.
- **Idempotency**: Safely handle repeat attempts to upgrade.

## Migration Structure

### 1. Directory Layout

All database-related files are located in `src/sql/`. New patches should be placed in a `patches/` subdirectory:

```text
src/sql/
├── schema.sql          # Initial full schema (baseline)
└── patches/            # Incremental updates
    ├── 001_initial.sql
    ├── 002_add_user_settings.sql
    └── 003_fix_task_status_enum.sql
```

### 2. Naming Convention

Patches must follow a strict lexicographical ordering to ensure they are applied in the correct sequence.
- **Format**: `XXX_description.sql` (where `XXX` is a zero-padded incremental number) or `YYYYMMDDHHMMSS_description.sql` (timestamp-based).
- **Example**: `001_create_migrations_table.sql`, `002_add_github_id_to_users.sql`.

## Tracking Mechanism

A dedicated table in the database tracks which patches have already been applied.

### Migrations Table Schema

```sql
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patch_name VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## The Upgrade Process

The application or a deployment script should follow this logic to perform an upgrade:

1.  **Initialize**: Ensure the `migrations` table exists.
2.  **Scan**: List all `.sql` files in `src/sql/patches/`, sorted alphabetically.
3.  **Filter**: Query the `migrations` table to find which patches have already been applied.
4.  **Apply**: For each patch not yet in the `migrations` table:
    - Start a database transaction (if supported/appropriate).
    - Execute the SQL content of the patch.
    - Insert the patch name into the `migrations` table.
    - Commit the transaction.
    - Log success or failure.

## Implementation Considerations

### Automated Upgrade Script

A PHP-based CLI script (e.g., `scripts/migrate.php`) can implement the above logic. This script can be called manually or integrated into a CI/CD pipeline.

### Integration with Deployment

- **Vagrant**: The `Vagrantfile` provisioning can call the migration script to ensure the local environment is up-to-date.
- **Production**: The deployment process (e.g., GitHub Actions or manual SSH) should include a step to run the migrations after the code is updated but before the new version is fully active.

## Best Practices for Patches

- **Atomic**: Each patch should represent a single logical change.
- **Reversible (Optional)**: While not always required, considering how to "down" or "rollback" a change is good practice.
- **Testable**: Patches should be tested against a copy of the production database before being merged.
- **Avoid changing existing patches**: Once a patch is merged and deployed, it should never be edited. Any corrections should be made in a new patch.
