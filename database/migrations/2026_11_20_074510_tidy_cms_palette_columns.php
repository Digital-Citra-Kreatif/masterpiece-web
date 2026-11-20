<?php

use App\Support\CmsLayout;
use Illuminate\Database\Migrations\Migration;

/** Warna dua kolom */
return new class extends Migration
{
    public function up(): void
    {
        CmsLayout::apply();
    }

    /** Tidak dikembalikan */
    public function down(): void {}
};
