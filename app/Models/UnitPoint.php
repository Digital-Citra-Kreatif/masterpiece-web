<?php

namespace App\Models;

use Database\Factories\UnitPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Titik unit di siteplan proyek */
#[Fillable(['project_id', 'unit_id', 'x', 'y'])]
class UnitPoint extends Model
{
    /** @use HasFactory<UnitPointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'unit_id' => 'integer',
            'x' => 'float',
            'y' => 'float',
        ];
    }
}
