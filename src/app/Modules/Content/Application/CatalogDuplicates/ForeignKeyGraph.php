<?php

namespace App\Modules\Content\Application\CatalogDuplicates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the database's own foreign keys, so duplicate merging can prove
 * that nothing still points at a row it retires — including tables added
 * by modules after this code was written (VIK-16).
 */
final class ForeignKeyGraph
{
    /** @var array<string, list<array{table: string, column: string}>>|null */
    private ?array $referencesByTable = null;

    /** @return list<array{table: string, column: string}> */
    public function referencesTo(string $table): array
    {
        return $this->referencesByTable()[$table] ?? [];
    }

    /**
     * Rows still pointing at `$id` in `$table`, per referencing column.
     *
     * @return array<string, int> "table.column" => row count, only non-zero
     */
    public function remainingReferences(string $table, int $id): array
    {
        $remaining = [];

        foreach ($this->referencesTo($table) as $reference) {
            $count = DB::table($reference['table'])->where($reference['column'], $id)->count();
            if ($count > 0) {
                $remaining[$reference['table'].'.'.$reference['column']] = $count;
            }
        }

        return $remaining;
    }

    /** @return array<string, list<array{table: string, column: string}>> */
    private function referencesByTable(): array
    {
        if ($this->referencesByTable !== null) {
            return $this->referencesByTable;
        }

        $map = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (count($foreignKey['columns']) !== 1) {
                    continue;
                }
                $map[$foreignKey['foreign_table']][] = ['table' => $table, 'column' => $foreignKey['columns'][0]];
            }
        }

        return $this->referencesByTable = $map;
    }
}
