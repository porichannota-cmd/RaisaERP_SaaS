# RAISA ERP — BACKUP & DISASTER RECOVERY
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## 1. Backup Strategy

"Do not assume backup exists means restore works." — Wave 0 Constitution

### 1.1 MySQL Database Backups

| Backup Type | Frequency | Retention | Storage |
|-------------|-----------|-----------|---------|
| Full backup | Daily (2 AM) | 30 days | Encrypted S3 |
| Incremental (binlog) | Every hour | 7 days | Encrypted S3 |
| Weekly snapshot | Weekly (Sunday) | 12 weeks | Encrypted S3 cross-region |
| Monthly archive | Monthly | 7 years (regulatory) | Encrypted cold storage |

```bash
# Daily backup command (example using mysqldump)
mysqldump --single-transaction --routines --triggers \
  --all-databases | gzip | \
  openssl enc -aes-256-cbc -pass env:BACKUP_ENCRYPTION_KEY | \
  aws s3 cp - s3://raisa-backups/mysql/$(date +%Y-%m-%d-%H%M).sql.gz.enc
```

### 1.2 Object Storage Backups

- Enable versioning on all S3 buckets (private + public)
- Cross-region replication for business-critical data
- Lifecycle policy: move to Glacier after 90 days for non-critical

### 1.3 Application Code

- Git repository is the source of truth
- Tagged releases for each deployed version
- Configuration encrypted and stored separately from code

---

## 2. Recovery Objectives

| Scenario | RTO (Recovery Time Objective) | RPO (Recovery Point Objective) |
|----------|------------------------------|-------------------------------|
| Single table corruption | 1 hour | 1 hour (binlog replay) |
| Full DB failure | 4 hours | 1 hour |
| Server failure | 2 hours | 1 hour |
| Data center failure | 24 hours | 4 hours |
| Full disaster | 48 hours | 24 hours |

---

## 3. Backup Verification

**Monthly automated restore testing:**

```bash
# Restore to isolated test environment
# Verify row counts match source
# Verify referential integrity
# Run automated test suite against restored data
# Document results in backup_audit_log
```

Restore results are logged in:
```sql
backup_audit_logs
  id, backup_type, backup_date, backup_size,
  restore_tested_at, restore_success, row_counts_match,
  integrity_check_pass, test_environment,
  notes, created_at
```

---

## 4. Encryption

- All backups encrypted with AES-256-CBC before upload
- Encryption keys stored in secrets manager (not in code)
- Key rotation: every 90 days
- Keys backed up separately from encrypted data
- Never store encryption key in same location as backup

---

## 5. Disaster Recovery Runbook

### Scenario 1: Database Corruption

1. Stop application traffic (maintenance mode: `php artisan down`)
2. Identify last clean backup
3. Restore to new isolated server
4. Replay binlogs to minimize data loss
5. Verify data integrity
6. Switch DNS/connection to restored DB
7. Test application functionality
8. Restore application traffic (`php artisan up`)
9. Post-incident review

### Scenario 2: Complete Server Loss

1. Provision new server from infrastructure templates
2. Restore application from Git + deploy scripts
3. Restore database from latest backup
4. Restore object storage files (sync from backup)
5. Update DNS records
6. Verify all functionality
7. Post-incident review

### Scenario 3: Ransomware / Data Breach

1. IMMEDIATELY isolate affected systems
2. DO NOT pay ransom
3. Notify affected tenants per legal requirements
4. Restore from clean backup (pre-infection)
5. Security forensics
6. Regulatory notification (if applicable)
7. Post-incident review + hardening

---

## 6. Tenant Data Deletion Audit

When tenant data is deleted (post legal hold period):

```sql
data_deletion_audit_log
  id, tenant_id, deletion_type, requested_by,
  authorized_by, records_deleted_count, tables_affected,
  deletion_started_at, deletion_completed_at,
  verification_hash, created_at
-- This record is NEVER deleted. Legal evidence.
```

---

## 7. Backup Monitoring

- Alert if backup job fails (PagerDuty / SMS / Email to SA)
- Alert if backup size anomaly detected (>50% deviation)
- Alert if restore test fails
- Dashboard: backup status for all environments

---

*Document Owner: DevOps Architect*
