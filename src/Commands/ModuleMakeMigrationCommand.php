<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeMigrationCommand extends Command
{
    protected $signature = 'module:make-migration {module} {name} {--create=} {--table=}';
    protected $description = 'Create a new migration file for a module';

    public function __construct(
        private Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $migrationName = $this->argument('name');
        $createTable = $this->option('create');
        $modifyTable = $this->option('table');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        // Generate timestamped migration filename
        $timestamp = date('Y_m_d_His');
        $className = Str::studly($migrationName);
        $filename = "{$timestamp}_{$migrationName}.php";

        $this->createMigration($moduleName, $filename, $className, $createTable, $modifyTable);

        $this->info("Migration '{$filename}' created successfully for module '{$moduleName}'.");
        $this->line("Location: modules/{$moduleName}/Database/Migrations/{$filename}");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createMigration(string $moduleName, string $filename, string $className, ?string $createTable, ?string $modifyTable): void
    {
        $migrationsDir = base_path("modules/{$moduleName}/Database/Migrations");
        $this->ensureDirectoryExists($migrationsDir);

        $migrationFile = "{$migrationsDir}/{$filename}";

        // Determine template based on options
        if ($createTable) {
            $template = $this->getCreateTableTemplate();
            $tableName = $createTable;
        } elseif ($modifyTable) {
            $template = $this->getModifyTableTemplate();
            $tableName = $modifyTable;
        } else {
            // Use existing stub or generic template
            $stubPath = $this->getStubPath();
            if ($this->files->exists($stubPath)) {
                $template = $this->files->get($stubPath);
                $tableName = $this->inferTableName($className);
            } else {
                $template = $this->getGenericMigrationTemplate();
                $tableName = $this->inferTableName($className);
            }
        }

        $replacements = [
            '{{ table }}' => $tableName,
            '{{table}}' => $tableName,
            '{{ class }}' => $className,
            '{{class}}' => $className,
            '{{ TABLE }}' => strtoupper($tableName),
            '{{TABLE}}' => strtoupper($tableName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        $this->files->put($migrationFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0o755, true);
        }
    }

    private function getStubPath(): string
    {
        return __DIR__ . '/../../stubs/database/migration.stub';
    }

    private function inferTableName(string $className): string
    {
        // Extract table name from class name
        if (preg_match('/Create(.+)Table/', $className, $matches)) {
            return Str::snake(Str::plural($matches[1]));
        }

        if (preg_match('/(.+)Table/', $className, $matches)) {
            return Str::snake($matches[1]);
        }

        // Fallback to snake case of class name
        return Str::snake($className);
    }

    private function getCreateTableTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('{{ table }}', function (Blueprint $table) {
                        $table->uuid('id')->primary();
                        $table->timestamps();

                        // Add your columns here

                        // Add indexes for better query performance
                        $table->index(['created_at']);
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('{{ table }}');
                }
            };
            PHP;
    }

    private function getModifyTableTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::table('{{ table }}', function (Blueprint $table) {
                        // Add your column modifications here
                        // $table->string('new_column')->nullable();
                    });
                }

                public function down(): void
                {
                    Schema::table('{{ table }}', function (Blueprint $table) {
                        // Reverse your column modifications here
                        // $table->dropColumn('new_column');
                    });
                }
            };
            PHP;
    }

    private function getGenericMigrationTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    // Add your migration logic here

                    // Example: Create table
                    // Schema::create('{{ table }}', function (Blueprint $table) {
                    //     $table->uuid('id')->primary();
                    //     $table->timestamps();
                    // });

                    // Example: Modify table
                    // Schema::table('{{ table }}', function (Blueprint $table) {
                    //     $table->string('new_column')->nullable();
                    // });
                }

                public function down(): void
                {
                    // Reverse your migration logic here

                    // Example: Drop table
                    // Schema::dropIfExists('{{ table }}');

                    // Example: Remove column
                    // Schema::table('{{ table }}', function (Blueprint $table) {
                    //     $table->dropColumn('new_column');
                    // });
                }
            };
            PHP;
    }
}
