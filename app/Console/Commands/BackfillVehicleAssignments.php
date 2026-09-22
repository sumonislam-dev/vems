<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;

class BackfillVehicleAssignments extends Command
{
    protected $signature = 'vems:backfill-vehicle-assignments {--dry : Only show diagnostics, do not write changes} {--force : Recreate current assignment for all vehicles with a driver}';
    protected $description = 'Create current driver assignment records for vehicles that have a driver but no assignment history (with diagnostics)';

    public function handle(): int
    {
        $totalVehicles = Vehicle::count();
        $withDriver = Vehicle::whereNotNull('driver_id')->count();
        $totalAssignments = VehicleDriverAssignment::count();
        $withCurrentAssignment = VehicleDriverAssignment::where('is_current', true)->distinct('vehicle_id')->count('vehicle_id');

        $this->line("Vehicles total: {$totalVehicles}");
        $this->line("Vehicles with driver_id: {$withDriver}");
        $this->line("Assignments total: {$totalAssignments}");
        $this->line("Vehicles with current assignment: {$withCurrentAssignment}");

        $candidates = Vehicle::whereNotNull('driver_id')
            ->whereDoesntHave('driverAssignments', function($q){
                $q->where('is_current', true);
            })
            ->limit(10)
            ->get(['id','registration_number','driver_id']);

        if ($candidates->isEmpty()) {
            $this->info('No vehicles require backfill (or none have driver_id).');
        } else {
            $this->info('Sample vehicles needing backfill (max 10):');
            foreach ($candidates as $v) {
                $this->line(" - ID {$v->id} | Reg: {$v->registration_number} | driver_id: {$v->driver_id}");
            }
        }

        if ($this->option('dry')) {
            $this->comment('DRY RUN: No changes written.');
            return self::SUCCESS;
        }

        $count = 0;
        if ($this->option('force')) {
            $this->warn('FORCE mode: Correcting the current assignment for vehicles whose driver_id disagrees with it (idempotent — already-correct vehicles are left untouched).');
            Vehicle::whereNotNull('driver_id')
                ->with('driverAssignments')
                ->chunk(200, function ($vehicles) use (&$count) {
                    foreach ($vehicles as $v) {
                        $current = $v->driverAssignments()->where('is_current', true)->first();

                        // Already correct — skip, so reruns don't inflate history
                        // or reset started_at for vehicles that haven't changed.
                        if ($current && $current->driver_id == $v->driver_id) {
                            continue;
                        }

                        // Close all current assignments for this vehicle
                        $now = now();
                        $v->driverAssignments()->where('is_current', true)->update([
                            'is_current' => false,
                            'ended_at' => $now,
                        ]);
                        // Create a fresh current assignment
                        VehicleDriverAssignment::create([
                            'vehicle_id'  => $v->id,
                            'driver_id'   => $v->driver_id,
                            'started_at'  => $now,
                            'is_current'  => true,
                            'assigned_by' => null,
                        ]);
                        $count++;
                    }
                });
        } else {
            Vehicle::with('driverAssignments')
                ->whereNotNull('driver_id')
                ->chunk(200, function ($vehicles) use (&$count) {
                    foreach ($vehicles as $v) {
                        $hasCurrent = $v->driverAssignments()->where('is_current', true)->exists();
                        if (!$hasCurrent) {
                            VehicleDriverAssignment::create([
                                'vehicle_id'  => $v->id,
                                'driver_id'   => $v->driver_id,
                                'started_at'  => now(),
                                'is_current'  => true,
                                'assigned_by' => null,
                            ]);
                            $count++;
                        }
                    }
                });
        }

        $this->info("Backfilled {$count} vehicles.");
        return self::SUCCESS;
    }
}
