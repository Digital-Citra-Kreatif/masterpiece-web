<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->path = 'storage/framework/testing/dump-'.uniqid().'.sql';
});

afterEach(function () {
    @unlink(base_path($this->path));
});

it('writes mysql table definitions and rows', function () {
    User::factory()->create(['name' => "O'Neil \\ \"Uji\"", 'email' => 'uji@example.com']);

    $this->artisan('db:export-mysql', ['path' => $this->path])->assertSuccessful();

    $sql = file_get_contents(base_path($this->path));
    expect($sql)
        ->toContain('CREATE TABLE `users`')
        ->toContain('`id` BIGINT NOT NULL AUTO_INCREMENT')
        ->toContain('PRIMARY KEY (`id`)')
        ->toContain('UNIQUE KEY `users_email_unique` (`email`)')
        ->toContain("'O\\'Neil \\\\ \"Uji\"'")
        ->toContain('SET FOREIGN_KEY_CHECKS = 1;');
});

it('keeps only the structure of temporary tables', function () {
    DB::table('cache')->insert(['key' => 'rahasia', 'value' => 'isi', 'expiration' => 0]);

    $this->artisan('db:export-mysql', ['path' => $this->path])->assertSuccessful();

    $sql = file_get_contents(base_path($this->path));
    expect($sql)
        ->toContain('CREATE TABLE `cache`')
        ->not->toContain('INSERT INTO `cache`')
        ->not->toContain('rahasia');
});
