<?php

namespace App\Actions\Catalog;

use App\Models\ServiceTemplate;

/**
 * Loads database/data/service_templates.php into `service_templates`.
 * Idempotent (upsert by code): safe to run on every deploy.
 */
class SyncServiceTemplates
{
    public function handle(): int
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = require database_path('data/service_templates.php');
        $now = now();

        $records = array_map(fn (array $row): array => [
            ...$row,
            'tags' => json_encode($row['tags'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        ServiceTemplate::query()->upsert(
            $records,
            uniqueBy: ['code'],
            update: array_values(array_diff(array_keys($records[0]), ['code', 'created_at'])),
        );

        return count($records);
    }
}
