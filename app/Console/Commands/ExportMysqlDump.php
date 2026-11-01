<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Ekspor SQLite lokal jadi file .sql MySQL */
#[Signature('db:export-mysql {path=database.sql : File tujuan, relatif ke root project}')]
#[Description('Ekspor database ke file .sql siap import MySQL/MariaDB (phpMyAdmin)')]
class ExportMysqlDump extends Command
{
    /** Tabel sementara: struktur saja */
    private const STRUCTURE_ONLY = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    /** Batas ukuran satu INSERT */
    private const CHUNK_BYTES = 1_000_000;

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->components->error('Perintah ini membaca database SQLite lokal.');

            return self::FAILURE;
        }

        $path = base_path($this->argument('path'));
        $file = fopen($path, 'wb');
        if ($file === false) {
            throw new RuntimeException("Tidak bisa menulis {$path}");
        }

        fwrite($file, implode("\n", [
            '-- '.config('app.name').', diekspor '.now()->toDateTimeString(),
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';",
            '', '',
        ]));

        $tables = collect(Schema::getTables())->pluck('name')->reject(fn (string $name): bool => str_starts_with($name, 'sqlite_'))->sort()->values();
        foreach ($tables as $table) {
            fwrite($file, $this->createTable($table)."\n\n");
            $rows = in_array($table, self::STRUCTURE_ONLY, true) ? 0 : $this->insertRows($file, $table);
            $this->components->twoColumnDetail($table, in_array($table, self::STRUCTURE_ONLY, true) ? 'struktur saja' : "{$rows} baris");
        }

        fwrite($file, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($file);

        $this->components->info("Tersimpan di {$path} (".number_format(filesize($path) / 1_048_576, 1).' MB)');

        return self::SUCCESS;
    }

    /** DDL MySQL satu tabel */
    private function createTable(string $table): string
    {
        $lines = collect(Schema::getColumns($table))->map(fn (array $column): string => '  '.$this->columnDefinition($column));

        foreach (Schema::getIndexes($table) as $index) {
            $columns = implode(', ', array_map(self::name(...), $index['columns']));
            $lines->push(match (true) {
                $index['primary'] => "  PRIMARY KEY ({$columns})",
                $index['unique'] => '  UNIQUE KEY '.self::name(self::indexName($table, $index['name']))." ({$columns})",
                default => '  KEY '.self::name(self::indexName($table, $index['name']))." ({$columns})",
            });
        }

        foreach (Schema::getForeignKeys($table) as $foreign) {
            $lines->push(sprintf(
                '  FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s',
                implode(', ', array_map(self::name(...), $foreign['columns'])),
                self::name($foreign['foreign_table']),
                implode(', ', array_map(self::name(...), $foreign['foreign_columns'])),
                strtoupper($foreign['on_delete'] ?: 'no action'),
                strtoupper($foreign['on_update'] ?: 'no action'),
            ));
        }

        return 'DROP TABLE IF EXISTS '.self::name($table).";\n"
            .'CREATE TABLE '.self::name($table)." (\n".$lines->implode(",\n")."\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }

    /**
     * Kolom SQLite ke MySQL
     *
     * @param  array{name: string, type: string, type_name: string, nullable: bool, default: mixed, auto_increment: bool}  $column
     */
    private function columnDefinition(array $column): string
    {
        $type = strtolower($column['type']);
        $mysql = match (true) {
            $type === 'integer' => 'BIGINT',
            $type === 'tinyint(1)' => 'TINYINT(1)',
            $type === 'text' => 'LONGTEXT',
            $type === 'datetime' => 'DATETIME',
            $type === 'date' => 'DATE',
            $type === 'time' => 'TIME',
            $type === 'numeric' => 'DECIMAL(8,2)',
            in_array($type, ['float', 'double', 'real'], true) => 'DOUBLE',
            $type === 'blob' => 'LONGBLOB',
            $type === 'varchar' => 'VARCHAR(255)',
            str_starts_with($type, 'varchar(') => strtoupper($type),
            default => throw new RuntimeException("Tipe kolom belum dipetakan: {$column['type']}"),
        };

        $definition = self::name($column['name']).' '.$mysql.($column['nullable'] ? ' NULL' : ' NOT NULL');
        if ($column['auto_increment']) {
            return $definition.' AUTO_INCREMENT';
        }
        // Default teks/blob tidak didukung MySQL lama
        if ($column['default'] !== null && ! in_array($mysql, ['LONGTEXT', 'LONGBLOB'], true)) {
            $definition .= ' DEFAULT '.$column['default'];
        }

        return $definition;
    }

    /** INSERT bertahap, kembalikan jumlah baris */
    private function insertRows($file, string $table): int
    {
        $columns = collect(Schema::getColumns($table))->pluck('name');
        $prefix = 'INSERT INTO '.self::name($table).' ('.$columns->map(self::name(...))->implode(', ').') VALUES ';
        $order = collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'] ?? [$columns->first()];

        $count = 0;
        $batch = [];
        $bytes = 0;
        foreach (DB::table($table)->orderBy($order[0])->lazy(500) as $row) {
            $values = '('.$columns->map(fn (string $column): string => self::value($row->{$column}))->implode(', ').')';
            $batch[] = $values;
            $bytes += strlen($values);
            $count++;
            if ($bytes >= self::CHUNK_BYTES) {
                fwrite($file, $prefix.implode(",\n", $batch).";\n");
                [$batch, $bytes] = [[], 0];
            }
        }
        if ($batch !== []) {
            fwrite($file, $prefix.implode(",\n", $batch).";\n");
        }
        if ($count > 0) {
            fwrite($file, "\n");
        }

        return $count;
    }

    /** Nilai aman untuk MySQL */
    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => "'".strtr((string) $value, ['\\' => '\\\\', "'" => "\\'", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z'])."'",
        };
    }

    private static function name(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /** Nama index maks 64 karakter */
    private static function indexName(string $table, string $name): string
    {
        $name = str_starts_with($name, 'sqlite_autoindex_') ? "{$table}_unique_".substr(md5($name), 0, 6) : $name;

        return strlen($name) > 64 ? substr($name, 0, 55).'_'.substr(md5($name), 0, 8) : $name;
    }
}
