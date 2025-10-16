<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Permisos;
use App\Models\ModulesViews;
use Illuminate\Support\Facades\DB;

class FixPermissionNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:fix-names
                            {--dry-run : Show what would be changed without actually changing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix permission names to match the expected format (action:view_path)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in DRY-RUN mode. No changes will be made.');
        }
        
        $this->info('Scanning permissions for incorrect names...');
        
        $permissions = Permisos::with('moduleView')->get();
        $fixed = 0;
        $skipped = 0;
        $errors = 0;
        
        if (!$dryRun) {
            DB::beginTransaction();
        }
        
        try {
            foreach ($permissions as $perm) {
                if (!$perm->moduleView) {
                    $this->warn("Permission ID {$perm->id}: no associated moduleview, skipping");
                    $skipped++;
                    continue;
                }
                
                $expectedName = $perm->action . ':' . $perm->moduleView->view_path;
                
                if ($perm->name !== $expectedName) {
                    $this->line("Permission ID {$perm->id}:");
                    $this->line("  Current:  {$perm->name}");
                    $this->line("  Expected: {$expectedName}");
                    
                    if (!$dryRun) {
                        try {
                            // Check if expected name already exists
                            $duplicate = Permisos::where('name', $expectedName)
                                ->where('id', '!=', $perm->id)
                                ->first();
                            
                            if ($duplicate) {
                                $this->error("  ✗ Cannot update: name '{$expectedName}' already exists (ID: {$duplicate->id})");
                                $this->line("    Consider deleting one of the duplicate permissions");
                                $errors++;
                            } else {
                                $perm->update(['name' => $expectedName]);
                                $this->info("  ✓ Updated");
                                $fixed++;
                            }
                        } catch (\Exception $e) {
                            $this->error("  ✗ Failed: " . $e->getMessage());
                            $errors++;
                        }
                    } else {
                        $this->info("  [Would update]");
                        $fixed++;
                    }
                } else {
                    $skipped++;
                }
            }
            
            if (!$dryRun && $errors === 0) {
                DB::commit();
                $this->info("\nChanges committed successfully.");
            } elseif (!$dryRun) {
                DB::rollBack();
                $this->error("\nRolled back due to errors.");
            }
            
            $this->newLine();
            $this->info('==============================================');
            $this->info('Summary:');
            if ($dryRun) {
                $this->info("Would fix: {$fixed}");
            } else {
                $this->info("Fixed: {$fixed}");
            }
            $this->info("Already correct: {$skipped}");
            $this->info("Errors: {$errors}");
            $this->info('==============================================');
            
            if ($dryRun && $fixed > 0) {
                $this->newLine();
                $this->info('Run without --dry-run to apply changes:');
                $this->info('  php artisan permissions:fix-names');
            }
            
            return $errors > 0 ? 1 : 0;
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $this->error('Fatal error: ' . $e->getMessage());
            return 1;
        }
    }
}
