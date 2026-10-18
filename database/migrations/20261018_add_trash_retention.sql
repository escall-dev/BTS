ALTER TABLE report_processes
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER status,
    ADD COLUMN deleted_from_status VARCHAR(32) NULL DEFAULT NULL AFTER deleted_at,
    ADD INDEX idx_report_processes_status_deleted_at (status, deleted_at);

UPDATE report_processes p
LEFT JOIN archives a ON a.process_id = p.id
SET p.deleted_at = COALESCE(a.archived_at, p.updated_at, CURRENT_TIMESTAMP)
WHERE p.status = 'deleted'
  AND p.deleted_at IS NULL;

UPDATE report_processes
SET deleted_from_status = CASE
    WHEN completed_at IS NOT NULL THEN 'completed'
    ELSE 'in_progress'
END
WHERE status = 'deleted'
  AND deleted_from_status IS NULL;
