<?php

use think\migration\Migrator;

/**
 * Adds DB-level triggers to enforce append-only immutability on audit_logs.
 *
 * These triggers block any UPDATE or DELETE operation on the audit_logs table,
 * ensuring that once an audit entry is written, it cannot be mutated or removed
 * through the application database connection.
 *
 * This complements the application-level hash chain in AuditService by providing
 * a defense-in-depth control at the database layer.
 */
class AuditLogsImmutabilityTriggers extends Migrator
{
    public function up()
    {
        $this->execute("
            CREATE TRIGGER audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Updates to audit_logs are not permitted. Audit log entries are immutable.';
            END
        ");

        $this->execute("
            CREATE TRIGGER audit_logs_no_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Deletes from audit_logs are not permitted. Audit log entries are immutable.';
            END
        ");
    }

    public function down()
    {
        $this->execute("DROP TRIGGER IF EXISTS audit_logs_no_update");
        $this->execute("DROP TRIGGER IF EXISTS audit_logs_no_delete");
    }
}
