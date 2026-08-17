<?php

namespace App\Http\Controllers;

use App\Models\HostApplication;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminHostApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = [
            HostApplication::STATUS_SUBMITTED,
            HostApplication::STATUS_UNDER_REVIEW,
            HostApplication::STATUS_NEEDS_CHANGES,
            HostApplication::STATUS_APPROVED,
            HostApplication::STATUS_REJECTED,
        ];
        $query = HostApplication::query()->with(['user', 'reviewer']);

        if ($request->filled('status') && in_array($request->string('status')->toString(), $statuses, true)) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->trim()).'%';
            $query->whereHas('user', fn ($users) => $users
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search));
        }

        return view('admin.host-applications.index', [
            'applications' => $query->orderByRaw("CASE status WHEN 'submitted' THEN 1 WHEN 'under_review' THEN 2 WHEN 'needs_changes' THEN 3 ELSE 4 END")
                ->latest('submitted_at')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => $statuses,
            'counts' => HostApplication::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function show(HostApplication $hostApplication): View
    {
        $hostApplication->load(['user', 'reviewer', 'histories.actor']);

        return view('admin.host-applications.show', compact('hostApplication'));
    }

    public function review(Request $request, HostApplication $hostApplication): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                HostApplication::STATUS_UNDER_REVIEW,
                HostApplication::STATUS_NEEDS_CHANGES,
                HostApplication::STATUS_APPROVED,
                HostApplication::STATUS_REJECTED,
            ])],
            'review_note' => [
                Rule::requiredIf(fn () => in_array($request->input('status'), [HostApplication::STATUS_NEEDS_CHANGES, HostApplication::STATUS_REJECTED], true)),
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $allowedTransitions = [
            HostApplication::STATUS_SUBMITTED => [HostApplication::STATUS_UNDER_REVIEW, HostApplication::STATUS_NEEDS_CHANGES, HostApplication::STATUS_APPROVED, HostApplication::STATUS_REJECTED],
            HostApplication::STATUS_UNDER_REVIEW => [HostApplication::STATUS_NEEDS_CHANGES, HostApplication::STATUS_APPROVED, HostApplication::STATUS_REJECTED],
        ];

        DB::transaction(function () use ($request, $hostApplication, $validated, $allowedTransitions): void {
            $application = HostApplication::query()->lockForUpdate()->findOrFail($hostApplication->id);

            abort_unless(in_array($validated['status'], $allowedTransitions[$application->status] ?? [], true), 422, 'That application status transition is not allowed.');

            if ($validated['status'] === HostApplication::STATUS_APPROVED && ! $application->user->hasCompleteProfile()) {
                abort(422, 'The applicant must complete their verification profile before approval.');
            }

            if ($validated['status'] === HostApplication::STATUS_APPROVED && $application->needsIdentityImages()) {
                abort(422, 'A face selfie and a selfie holding the valid ID are required before approval.');
            }

            $fromStatus = $application->status;
            $application->update([
                'status' => $validated['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);

            if ($validated['status'] === HostApplication::STATUS_APPROVED) {
                $application->user()->update(['role' => 'host']);
            }

            $application->histories()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $fromStatus,
                'to_status' => $validated['status'],
                'note' => $validated['review_note'] ?? null,
            ]);
        });

        $hostApplication->refresh()->load('user');
        $note = filled($hostApplication->review_note)
            ? ' Note: '.Str::limit($hostApplication->review_note, 220)
            : '';
        app(AppNotificationService::class)->send(
            $hostApplication->user,
            'host_application_status',
            'Host application status: '.$hostApplication->statusLabel(),
            'Your host application is now '.$hostApplication->statusLabel().'.'.$note,
            route('host-applications.show'),
        );

        return redirect()->route('admin.host-applications.show', $hostApplication)->with('status', 'The host application status was updated.');
    }
}
