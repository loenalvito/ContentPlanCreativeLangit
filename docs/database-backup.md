# MySQL backup and restore

Use a dedicated backup destination outside the release directory. Encrypt backups at rest and test restoration regularly.

## Backup

```bash
mkdir -p /srv/backups/kolabo
mysqldump --single-transaction --quick --set-gtid-purged=OFF --no-tablespaces \
  --default-character-set=utf8mb4 --triggers \
  --host=127.0.0.1 --user=kolabo_app --password \
  kolabo_creative | gzip > /srv/backups/kolabo/kolabo_creative_$(date +%Y%m%d_%H%M%S).sql.gz
```

The interactive `--password` form avoids leaking the password through the process list or shell history. For MariaDB, omit `--set-gtid-purged=OFF` if the client does not support it.

## Restore rehearsal

Restore into a separate database first, never over the live database:

```bash
mysql --host=127.0.0.1 --user=DATABASE_ADMIN --password \
  -e "CREATE DATABASE kolabo_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c /srv/backups/kolabo/kolabo_creative_YYYYMMDD_HHMMSS.sql.gz | \
  mysql --host=127.0.0.1 --user=DATABASE_ADMIN --password kolabo_restore
```

Use an authorized database administrator only for creating the isolated rehearsal database. Validate table and row counts, foreign-key checks, login against an isolated app instance, and the latest content/activity records. Drop the rehearsal database only after verification and only with an explicitly reviewed database name.

## Retention baseline

- Daily backups: 14 days.
- Weekly backups: 8 weeks.
- Monthly backups: 12 months.
- Run an automated restore test at least monthly.
- Always take and verify a backup immediately before schema migration.
