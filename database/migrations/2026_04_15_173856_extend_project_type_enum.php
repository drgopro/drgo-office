<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE projects MODIFY COLUMN project_type ENUM('visit','remote','design','inquiry','as','troubleshoot') NOT NULL DEFAULT 'visit'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE projects MODIFY COLUMN project_type ENUM('visit','remote','as') NOT NULL DEFAULT 'visit'");
    }
};
