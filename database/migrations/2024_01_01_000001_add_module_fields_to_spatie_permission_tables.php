<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add module_id to permissions table
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('permissions', 'module_id')) {
                    $table->string('module_id')->nullable()->after('guard_name');
                    $table->string('group')->default('general')->after('module_id');
                    $table->text('description')->nullable()->after('name');
                }
            });
        }

        // Add module_id to roles table
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (!Schema::hasColumn('roles', 'module_id')) {
                    $table->string('module_id')->nullable()->after('guard_name');
                    $table->text('description')->nullable()->after('name');
                }
            });
        }

        // Add indexes for better performance
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->index(['module_id'], 'permissions_module_id_index');
                $table->index(['module_id', 'group'], 'permissions_module_group_index');
            });
        }

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->index(['module_id'], 'roles_module_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove module fields from permissions table
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropIndex('permissions_module_id_index');
                $table->dropIndex('permissions_module_group_index');
                $table->dropColumn(['module_id', 'group', 'description']);
            });
        }

        // Remove module fields from roles table
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex('roles_module_id_index');
                $table->dropColumn(['module_id', 'description']);
            });
        }
    }
};