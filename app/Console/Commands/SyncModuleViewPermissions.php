<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ModulesViews;
use App\Models\Permisos;
use Illuminate\Support\Facades\DB;

class SyncModuleViewPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync
                            {--action=view : The action to create permissions for (view, create, edit, delete, export, all)}
                            {--force : Force creation even if permission name exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permissions for all moduleviews, creating missing ones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $actionOption = $this->option('action');
        $force = $this->option('force');
        
        $actions = $actionOption === 'all' 
            ? ['view', 'create', 'edit', 'delete', 'export']
            : [$actionOption];

        $this->info('Starting permission synchronization...');
        $this->info('Actions to sync: ' . implode(', ', $actions));

        $moduleviews = ModulesViews::all();
        $created = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();
        try {
            foreach ($moduleviews as $mv) {
                $this->info("\nProcessing moduleview #{$mv->id}: {$mv->menu} > {$mv->submenu}");
                
                foreach ($actions as $action) {
                    $permName = $action . ':' . $mv->view_path;
                    
                    // Check if permission already exists
                    $existing = Permisos::where('name', $permName)->first();
                    
                    if ($existing) {
                        if ($force) {
                            $this->warn("  - Permission '{$permName}' exists (ID: {$existing->id}), updating moduleview_id...");
                            $existing->update(['moduleview_id' => $mv->id]);
                            $skipped++;
                        } else {
                            $this->line("  - Permission '{$permName}' already exists (ID: {$existing->id})");
                            $skipped++;
                        }
                        continue;
                    }
                    
                    // Check by moduleview_id and action
                    $existingByMv = Permisos::where('moduleview_id', $mv->id)
                        ->where('action', $action)
                        ->first();
                    
                    if ($existingByMv && !$force) {
                        $this->line("  - Permission for action '{$action}' already exists (ID: {$existingByMv->id})");
                        // Update name if it's wrong
                        if ($existingByMv->name !== $permName) {
                            $this->warn("    Updating name from '{$existingByMv->name}' to '{$permName}'");
                            $existingByMv->update(['name' => $permName]);
                        }
                        $skipped++;
                        continue;
                    }
                    
                    try {
                        $permission = Permisos::create([
                            'moduleview_id' => $mv->id,
                            'action' => $action,
                            'name' => $permName,
                            'description' => ucfirst($action) . ' permission for ' . $mv->submenu
                        ]);
                        
                        $this->info("  ✓ Created permission '{$permName}' (ID: {$permission->id})");
                        $created++;
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed to create permission '{$permName}': " . $e->getMessage());
                        $errors++;
                    }
                }
            }
            
            DB::commit();
            
            $this->newLine();
            $this->info('==============================================');
            $this->info('Permission synchronization completed!');
            $this->info("Created: {$created}");
            $this->info("Skipped: {$skipped}");
            $this->info("Errors: {$errors}");
            $this->info('==============================================');
            
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Transaction failed: ' . $e->getMessage());
            return 1;
        }
    }
}
