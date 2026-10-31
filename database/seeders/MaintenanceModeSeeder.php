<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Support\Locales;

class MaintenanceModeSeeder extends Seeder
{
    public function run(StructureImporter $importer): void
    {
        $importer->import([
            'blueprints' => [[
                'handle' => 'maintenance_mode',
                'title' => 'Maintenance Mode',
                'fields' => [
                    ['handle' => 'heading', 'type' => 'text', 'label' => 'Judul'],
                    ['handle' => 'description', 'type' => 'textarea', 'label' => 'Deskripsi'],
                    ['handle' => 'date', 'type' => 'date', 'label' => 'Tanggal dan jam', 'translatable' => false, 'width' => 50, 'config' => ['time' => true]],
                    ['handle' => 'active', 'type' => 'toggle', 'label' => 'Aktif', 'translatable' => false, 'width' => 50, 'config' => ['default' => false]],
                ],
            ]],
            'globals' => [[
                'handle' => 'maintenance_mode',
                'title' => 'Maintenance Mode',
                'blueprint' => 'maintenance_mode',
                'values' => [Locales::default() => ['active' => false]],
            ]],
        ]);
    }
}
