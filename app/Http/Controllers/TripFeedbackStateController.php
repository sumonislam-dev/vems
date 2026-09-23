<?php

namespace App\Http\Controllers;

use App\Models\TripFeedback;
use App\Notifications\ComplaintAssigned;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TripFeedbackStateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:assign-complaints', only: ['assign']),
            new Middleware('permission:resolve-complaints', only: ['resolve', 'close', 'reopen']),
        ];
    }

    /**
     * Assign a complaint to a handler.
     */
    public function assign(Request $request, TripFeedback $complaint)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $complaint->update($validated);

        if ($complaint->status === 'open') {
            $complaint->transitionTo('in_review');
        }

        $complaint->assignee?->notify(new ComplaintAssigned($complaint));

        return back()->with('success', 'Complaint assigned.');
    }

    /**
     * Mark a complaint resolved with a resolution note.
     */
    public function resolve(Request $request, TripFeedback $complaint)
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        if (!$complaint->transitionTo('resolved', [
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ])) {
            return back()->with('error', 'Complaint cannot be resolved in its current status.');
        }

        return back()->with('success', 'Complaint marked resolved.');
    }

    /**
     * Close a complaint (feedback needs no further action, or a resolved complaint is confirmed done).
     */
    public function close(Request $request, TripFeedback $complaint)
    {
        if (!$complaint->transitionTo('closed')) {
            return back()->with('error', 'Complaint cannot be closed in its current status.');
        }

        return back()->with('success', 'Complaint closed.');
    }

    /**
     * Reopen a resolved complaint back into review.
     */
    public function reopen(Request $request, TripFeedback $complaint)
    {
        if (!$complaint->transitionTo('in_review')) {
            return back()->with('error', 'Complaint cannot be reopened from its current status.');
        }

        return back()->with('success', 'Complaint reopened.');
    }
}
