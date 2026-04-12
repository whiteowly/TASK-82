<?php

use think\migration\Migrator;

class CreateSchema extends Migrator
{
    public function change()
    {
        // ============================================================
        // Identity & Scope Tables
        // ============================================================

        $this->table('users')
            ->addColumn('username', 'string', ['limit' => 50])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('display_name', 'string', ['limit' => 100])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['username'], ['unique' => true])
            ->create();

        $this->table('roles')
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('display_name', 'string', ['limit' => 100])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        $this->table('permissions')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('display_name', 'string', ['limit' => 200])
            ->addColumn('module', 'string', ['limit' => 50])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        $this->table('user_roles')
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('role_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'role_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('role_permissions')
            ->addColumn('role_id', 'integer', ['signed' => false])
            ->addColumn('permission_id', 'integer', ['signed' => false])
            ->addIndex(['role_id', 'permission_id'], ['unique' => true])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // sites must be created before user_site_scopes (foreign key dependency)
        $this->table('sites')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('code', 'string', ['limit' => 20])
            ->addColumn('address', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['code'], ['unique' => true])
            ->create();

        $this->table('user_site_scopes')
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'site_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // ============================================================
        // Organization Tables
        // ============================================================

        $this->table('communities')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('group_leaders')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('community_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('phone', 'string', ['limit' => 20])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('community_id', 'communities', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('products')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('category', 'string', ['limit' => 100])
            ->addColumn('unit', 'string', ['limit' => 20])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('companies')
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('tax_id_encrypted', 'text')
            ->addColumn('address', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->create();

        $this->table('positions')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('department', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        $this->table('participants')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('phone', 'string', ['limit' => 20])
            ->addColumn('company_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('position_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addIndex(['company_id'])
            ->addIndex(['position_id'])
            ->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // ============================================================
        // Recipe Workflow Tables
        // ============================================================

        $this->table('recipes')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 200])
            ->addColumn('published_version_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addIndex(['site_id', 'status'])
            ->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('recipe_versions')
            ->addColumn('recipe_id', 'integer', ['signed' => false])
            ->addColumn('version_number', 'integer')
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('content_json', 'text', ['limit' => 4294967295, 'null' => true])
            ->addColumn('prep_time', 'integer', ['null' => true])
            ->addColumn('cook_time', 'integer', ['null' => true])
            ->addColumn('total_time', 'integer', ['null' => true])
            ->addColumn('difficulty', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('reviewer_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('reviewed_at', 'timestamp', ['null' => true])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['recipe_id'])
            ->addForeignKey('recipe_id', 'recipes', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('recipe_steps')
            ->addColumn('version_id', 'integer', ['signed' => false])
            ->addColumn('step_number', 'integer')
            ->addColumn('instruction', 'text')
            ->addColumn('duration_minutes', 'integer', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['version_id'])
            ->addForeignKey('version_id', 'recipe_versions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('recipe_tags')
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        $this->table('recipe_version_tags')
            ->addColumn('version_id', 'integer', ['signed' => false])
            ->addColumn('tag_id', 'integer', ['signed' => false])
            ->addIndex(['version_id', 'tag_id'], ['unique' => true])
            ->addForeignKey('version_id', 'recipe_versions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('tag_id', 'recipe_tags', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('recipe_images')
            ->addColumn('version_id', 'integer', ['signed' => false])
            ->addColumn('file_path', 'string', ['limit' => 500])
            ->addColumn('original_name', 'string', ['limit' => 255])
            ->addColumn('mime_type', 'string', ['limit' => 50])
            ->addColumn('file_size', 'integer')
            ->addColumn('sha256_hash', 'string', ['limit' => 64])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['version_id'])
            ->addIndex(['sha256_hash'])
            ->addForeignKey('version_id', 'recipe_versions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('review_comments')
            ->addColumn('version_id', 'integer', ['signed' => false])
            ->addColumn('author_id', 'integer', ['signed' => false])
            ->addColumn('anchor_type', 'string', ['limit' => 50])
            ->addColumn('anchor_ref', 'string', ['limit' => 200])
            ->addColumn('content', 'text')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['version_id'])
            ->addForeignKey('version_id', 'recipe_versions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('author_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('review_actions')
            ->addColumn('version_id', 'integer', ['signed' => false])
            ->addColumn('reviewer_id', 'integer', ['signed' => false])
            ->addColumn('action', 'string', ['limit' => 20])
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['version_id'])
            ->addForeignKey('version_id', 'recipe_versions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('reviewer_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // ============================================================
        // Transaction & Analytics Tables
        // ============================================================

        $this->table('orders')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('participant_id', 'integer', ['signed' => false])
            ->addColumn('group_leader_id', 'integer', ['signed' => false])
            ->addColumn('total_amount', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('status', 'string', ['limit' => 20])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addIndex(['participant_id'])
            ->addIndex(['group_leader_id'])
            ->addIndex(['site_id', 'created_at'])
            ->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('order_items')
            ->addColumn('order_id', 'integer', ['signed' => false])
            ->addColumn('product_id', 'integer', ['signed' => false])
            ->addColumn('quantity', 'integer')
            ->addColumn('unit_price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('subtotal', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addIndex(['order_id'])
            ->addIndex(['product_id'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('refunds')
            ->addColumn('order_id', 'integer', ['signed' => false])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('reason', 'text')
            ->addColumn('status', 'string', ['limit' => 20])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['order_id'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('metric_snapshots')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('metric_type', 'string', ['limit' => 50])
            ->addColumn('dimension_key', 'string', ['limit' => 50])
            ->addColumn('dimension_value', 'string', ['limit' => 200])
            ->addColumn('value', 'decimal', ['precision' => 15, 'scale' => 4])
            ->addColumn('snapshot_date', 'date')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addIndex(['metric_type'])
            ->addIndex(['snapshot_date'])
            ->addIndex(['site_id', 'metric_type', 'snapshot_date'])
            ->create();

        $this->table('analytics_refresh_requests')
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('site_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'requested'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('completed_at', 'timestamp', ['null' => true])
            ->addIndex(['user_id'])
            ->create();

        // ============================================================
        // Reporting Tables
        // ============================================================

        $this->table('report_definitions')
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('dimensions_json', 'text', ['null' => true])
            ->addColumn('filters_json', 'text', ['null' => true])
            ->addColumn('columns_json', 'text', ['null' => true])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->create();

        $this->table('report_schedules')
            ->addColumn('definition_id', 'integer', ['signed' => false])
            ->addColumn('cadence', 'string', ['limit' => 20])
            ->addColumn('next_run_at', 'timestamp', ['null' => true])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['definition_id'])
            ->addForeignKey('definition_id', 'report_definitions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('report_runs')
            ->addColumn('definition_id', 'integer', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'queued'])
            ->addColumn('artifact_path', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addColumn('started_at', 'timestamp', ['null' => true])
            ->addColumn('completed_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['definition_id'])
            ->addIndex(['status'])
            ->addForeignKey('definition_id', 'report_definitions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('report_artifacts')
            ->addColumn('run_id', 'integer', ['signed' => false])
            ->addColumn('file_path', 'string', ['limit' => 500])
            ->addColumn('file_size', 'integer')
            ->addColumn('sha256_hash', 'string', ['limit' => 64])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['run_id'])
            ->addForeignKey('run_id', 'report_runs', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // ============================================================
        // Settlement Tables
        // ============================================================

        $this->table('freight_rules')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('distance_band_json', 'text', ['null' => true])
            ->addColumn('weight_tiers_json', 'text', ['null' => true])
            ->addColumn('volume_tiers_json', 'text', ['null' => true])
            ->addColumn('surcharges_json', 'text', ['null' => true])
            ->addColumn('tax_rate', 'decimal', ['precision' => 5, 'scale' => 4, 'default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->create();

        $this->table('settlement_statements')
            ->addColumn('site_id', 'integer', ['signed' => false])
            ->addColumn('period', 'string', ['limit' => 20])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('total_amount', 'decimal', ['precision' => 15, 'scale' => 2, 'default' => 0])
            ->addColumn('generated_by', 'integer', ['signed' => false])
            ->addColumn('submitted_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('approved_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('submitted_at', 'timestamp', ['null' => true])
            ->addColumn('approved_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_id'])
            ->addIndex(['site_id', 'period'])
            ->create();

        $this->table('settlement_lines')
            ->addColumn('statement_id', 'integer', ['signed' => false])
            ->addColumn('description', 'string', ['limit' => 500])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('category', 'string', ['limit' => 100])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['statement_id'])
            ->addForeignKey('statement_id', 'settlement_statements', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('reconciliation_variances')
            ->addColumn('statement_id', 'integer', ['signed' => false])
            ->addColumn('field_name', 'string', ['limit' => 100])
            ->addColumn('expected_value', 'string', ['limit' => 200])
            ->addColumn('actual_value', 'string', ['limit' => 200])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('resolved', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['statement_id'])
            ->addForeignKey('statement_id', 'settlement_statements', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('settlement_approvals')
            ->addColumn('statement_id', 'integer', ['signed' => false])
            ->addColumn('actor_id', 'integer', ['signed' => false])
            ->addColumn('action', 'string', ['limit' => 50])
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['statement_id'])
            ->addForeignKey('statement_id', 'settlement_statements', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('settlement_reversals')
            ->addColumn('original_statement_id', 'integer', ['signed' => false])
            ->addColumn('replacement_statement_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('reason', 'text')
            ->addColumn('reversed_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['original_statement_id'])
            ->create();

        // ============================================================
        // Compliance Tables
        // ============================================================

        $this->table('audit_logs')
            ->addColumn('actor_id', 'integer', ['signed' => false])
            ->addColumn('actor_role', 'string', ['limit' => 50])
            ->addColumn('site_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('target_type', 'string', ['limit' => 50])
            ->addColumn('target_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('event_type', 'string', ['limit' => 50])
            ->addColumn('request_id', 'string', ['limit' => 50])
            ->addColumn('payload_summary', 'text', ['null' => true])
            ->addColumn('prev_hash', 'string', ['limit' => 64])
            ->addColumn('entry_hash', 'string', ['limit' => 64])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['actor_id'])
            ->addIndex(['site_id'])
            ->addIndex(['target_type'])
            ->addIndex(['event_type'])
            ->addIndex(['request_id'])
            ->addIndex(['created_at'])
            ->create();

        $this->table('export_logs')
            ->addColumn('actor_id', 'integer', ['signed' => false])
            ->addColumn('site_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('export_type', 'string', ['limit' => 50])
            ->addColumn('record_count', 'integer')
            ->addColumn('reason', 'text', ['null' => true])
            ->addColumn('request_id', 'string', ['limit' => 50])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['actor_id'])
            ->create();

        $this->table('permission_change_logs')
            ->addColumn('actor_id', 'integer', ['signed' => false])
            ->addColumn('target_user_id', 'integer', ['signed' => false])
            ->addColumn('change_type', 'string', ['limit' => 50])
            ->addColumn('old_value', 'text', ['null' => true])
            ->addColumn('new_value', 'text', ['null' => true])
            ->addColumn('request_id', 'string', ['limit' => 50])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['actor_id'])
            ->addIndex(['target_user_id'])
            ->create();
    }
}
