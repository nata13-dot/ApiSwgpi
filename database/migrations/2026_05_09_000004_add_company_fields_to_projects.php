<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'company_name')) {
                $table->string('company_name')->nullable()->after('authors');
            }
            if (!Schema::hasColumn('projects', 'company_contact_name')) {
                $table->string('company_contact_name')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('projects', 'company_contact_position')) {
                $table->string('company_contact_position')->nullable()->after('company_contact_name');
            }
            if (!Schema::hasColumn('projects', 'company_address')) {
                $table->text('company_address')->nullable()->after('company_contact_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'company_address')) {
                $table->dropColumn('company_address');
            }
            if (Schema::hasColumn('projects', 'company_contact_position')) {
                $table->dropColumn('company_contact_position');
            }
            if (Schema::hasColumn('projects', 'company_contact_name')) {
                $table->dropColumn('company_contact_name');
            }
            if (Schema::hasColumn('projects', 'company_name')) {
                $table->dropColumn('company_name');
            }
        });
    }
};