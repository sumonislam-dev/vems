<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleDowntime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Add/edit/end/delete a vehicle's out-of-service periods from the vehicle
 * page. Status-driven start/end happens separately in VehicleObserver.
 */
class VehicleDowntimeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:edit-vehicles'),
        ];
    }

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $this->validated($request, $vehicle);
        $this->assertNoOverlap($vehicle, $validated);

        $vehicle->downtimes()->create([...$validated, 'recorded_by' => $request->user()->id]);

        return back()->with('success', 'Downtime recorded.');
    }

    public function update(Request $request, Vehicle $vehicle, VehicleDowntime $downtime): RedirectResponse
    {
        $this->ensureBelongs($vehicle, $downtime);
        $validated = $this->validated($request, $vehicle);
        $this->assertNoOverlap($vehicle, $validated, $downtime->id);

        $downtime->update($validated);

        return back()->with('success', 'Downtime updated.');
    }

    /**
     * End an open downtime today — the vehicle is back in service.
     */
    public function end(Vehicle $vehicle, VehicleDowntime $downtime): RedirectResponse
    {
        $this->ensureBelongs($vehicle, $downtime);

        if ($downtime->ends_on !== null) {
            return back()->with('error', 'This downtime has already ended.');
        }

        $downtime->update(['ends_on' => today()->max($downtime->starts_on)]);

        return back()->with('success', 'Downtime ended.');
    }

    public function destroy(Vehicle $vehicle, VehicleDowntime $downtime): RedirectResponse
    {
        $this->ensureBelongs($vehicle, $downtime);
        $downtime->delete();

        return back()->with('success', 'Downtime deleted.');
    }

    private function validated(Request $request, Vehicle $vehicle): array
    {
        if ($request->input('replacement_vehicle_id') === 'none') {
            $request->merge(['replacement_vehicle_id' => null]);
        }

        return $request->validate([
            'starts_on' => 'required|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'reason' => ['required', Rule::in(array_keys(VehicleDowntime::REASONS))],
            'replacement_vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id', Rule::notIn([$vehicle->id])],
            'notes' => 'nullable|string|max:2000',
        ], [
            'replacement_vehicle_id.not_in' => 'A vehicle cannot replace itself.',
        ]);
    }

    /**
     * A vehicle can only be down once on any given day, and only one
     * downtime can be left open.
     */
    private function assertNoOverlap(Vehicle $vehicle, array $data, ?int $ignoreId = null): void
    {
        $overlaps = $vehicle->downtimes()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->overlapping($data['starts_on'], $data['ends_on'] ?? '9999-12-31')
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_on' => 'This period overlaps another downtime for this vehicle.',
            ]);
        }
    }

    private function ensureBelongs(Vehicle $vehicle, VehicleDowntime $downtime): void
    {
        abort_unless($downtime->vehicle_id === $vehicle->id, 404);
    }
}
