<?php
declare(strict_types=1);

namespace app\service\report;

use think\facade\Db;

class CsvExportService
{
    private const STORAGE_DIR = '/app/storage/reports/';

    /**
     * Generate a CSV file from the given data, applying column selection and masking rules.
     *
     * @param array $data         Row data to export.
     * @param array $columns      Column definitions / headers.
     * @param array $maskingRules Field masking rules to apply before writing (column_name => rule).
     * @return string Absolute file path to the generated CSV.
     */
    public function generateCsv(array $data, array $columns, array $maskingRules): string
    {
        $dir = self::STORAGE_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'report_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.csv';
        $filePath = $dir . $filename;

        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open file for writing: ' . $filePath);
        }

        // Write header row
        fputcsv($handle, $columns);

        // Write data rows with masking applied
        foreach ($data as $row) {
            $outputRow = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                if (isset($maskingRules[$col])) {
                    $value = $this->applyMask($value, $maskingRules[$col]);
                }
                $outputRow[] = $value;
            }
            fputcsv($handle, $outputRow);
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * Generate a CSV from a report definition, querying data server-side.
     *
     * @param array $definition   The report definition record (with dimensions_json, filters_json, columns_json).
     * @param array $siteScopes   Site IDs the requesting user has access to.
     * @param array $maskingRules Column masking rules to apply (column_name => rule).
     * @return string Absolute file path to the generated CSV.
     */
    public function generateFromDefinition(array $definition, array $siteScopes, array $maskingRules = []): string
    {
        $dimensions = !empty($definition['dimensions_json'])
            ? (is_string($definition['dimensions_json']) ? json_decode($definition['dimensions_json'], true) : $definition['dimensions_json'])
            : [];
        $filters = !empty($definition['filters_json'])
            ? (is_string($definition['filters_json']) ? json_decode($definition['filters_json'], true) : $definition['filters_json'])
            : [];
        $columns = !empty($definition['columns_json'])
            ? (is_string($definition['columns_json']) ? json_decode($definition['columns_json'], true) : $definition['columns_json'])
            : [];

        // Determine the source table
        $table = $dimensions['table'] ?? ($filters['table'] ?? 'orders');
        $allowedTables = ['orders', 'participants', 'recipes', 'settlements'];
        if (!in_array($table, $allowedTables, true)) {
            $table = 'orders';
        }

        $query = Db::table($table);

        // Apply site scope filtering
        if (!empty($siteScopes)) {
            $query->whereIn('site_id', $siteScopes);
        }

        // Apply definition filters
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }

        // Select specific columns if defined
        if (!empty($columns)) {
            $query->field($columns);
        }

        $data = $query->select()->toArray();

        // Determine CSV columns
        $csvColumns = !empty($columns) ? $columns : (!empty($data) ? array_keys($data[0]) : []);

        return $this->generateCsv($data, $csvColumns, $maskingRules);
    }

    /**
     * Get the storage path of a previously generated report artifact.
     *
     * @param int $runId
     * @return string|null File path or null if not found.
     */
    public function getArtifactPath(int $runId): ?string
    {
        $artifact = Db::table('report_artifacts')
            ->where('run_id', $runId)
            ->field('file_path')
            ->find();

        return $artifact ? $artifact['file_path'] : null;
    }

    /**
     * Apply a masking rule to a value.
     *
     * @param mixed  $value
     * @param string $rule Masking rule name (e.g. 'partial', 'full', 'hash', 'last4').
     * @return string Masked value.
     */
    private function applyMask(mixed $value, string $rule): string
    {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        switch ($rule) {
            case 'full':
                return str_repeat('*', strlen($value));

            case 'partial':
                $len = strlen($value);
                if ($len <= 2) {
                    return str_repeat('*', $len);
                }
                return $value[0] . str_repeat('*', $len - 2) . $value[$len - 1];

            case 'last4':
                $len = strlen($value);
                if ($len <= 4) {
                    return $value;
                }
                return str_repeat('*', $len - 4) . substr($value, -4);

            case 'hash':
                return hash('sha256', $value);

            default:
                return $value;
        }
    }
}
