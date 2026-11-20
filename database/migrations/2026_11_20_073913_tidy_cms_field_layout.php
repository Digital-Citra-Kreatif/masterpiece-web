<?php

use App\Support\CmsLayout;
use Illuminate\Database\Migrations\Migration;

/** Rapikan form CMS */
return new class extends Migration
{
    public function up(): void
    {
        CmsLayout::apply();
    }

    /** Tidak dikembalikan */
    public function down(): void {}
};
