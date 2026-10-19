<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The navbar zone switcher: lets a user with access to more than one zone
 * (a multi-zone grant, or 'view-all-zones') temporarily narrow their own
 * view to a single zone, without changing their actual access — see
 * Zone::accessIds()/realAccessIds(). Stored in session, not the database,
 * so it resets naturally per login/browser.
 */
class ActiveZoneController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $zoneId = $validated['zone_id'] ?? null;
        $realIds = Zone::realAccessIds($request->user());

        if ($zoneId !== null && $realIds !== null && ! in_array($zoneId, $realIds, true)) {
            abort(403, 'You do not have access to that zone.');
        }

        if ($zoneId === null) {
            $request->session()->forget('active_zone_id');

            return redirect()->back()->with('success', 'Now viewing all your zones.');
        }

        $request->session()->put('active_zone_id', $zoneId);
        $zoneName = Zone::find($zoneId)?->name ?? 'selected zone';

        return redirect()->back()->with('success', "Now viewing {$zoneName} only.");
    }
}
