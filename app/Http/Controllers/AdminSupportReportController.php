<?php

namespace App\Http\Controllers;

use App\Models\SupportReport;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSupportReportController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $query = SupportReport::query()->with(['reporter', 'unit.host', 'booking']);

        if ($request->filled('status') && in_array($request->string('status')->toString(), $statuses, true)) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category') && array_key_exists($request->string('category')->toString(), SupportReport::CATEGORIES)) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('search')) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->trim()).'%';
            $query->where(fn ($reports) => $reports->where('subject', 'like', $search)
                ->orWhere('message', 'like', $search)
                ->orWhereHas('reporter', fn ($users) => $users->where('name', 'like', $search)->orWhere('email', 'like', $search))
                ->orWhereHas('unit', fn ($units) => $units->where('name', 'like', $search)));
        }

        return view('admin.support-reports.index', [
            'reports' => $query->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'resolved' THEN 3 ELSE 4 END")->latest()->paginate(25)->withQueryString(),
            'statuses' => $statuses,
            'categories' => SupportReport::CATEGORIES,
            'counts' => SupportReport::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function show(SupportReport $supportReport): View
    {
        $supportReport->load(['reporter', 'unit.host', 'booking.unit', 'reviewer']);

        return view('admin.support-reports.show', compact('supportReport'));
    }

    public function update(Request $request, SupportReport $supportReport): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'admin_response' => [Rule::requiredIf(fn () => in_array($request->input('status'), ['resolved', 'closed'], true)), 'nullable', 'string', 'max:5000'],
        ]);

        $supportReport->update([
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        app(AppNotificationService::class)->send(
            $supportReport->reporter,
            'support_report_update',
            'Admin report updated: '.$supportReport->subject,
            'Status: '.$supportReport->statusLabel().'.'.($supportReport->admin_response ? ' Response: '.str($supportReport->admin_response)->limit(220) : ''),
            route('support.index'),
        );

        return back()->with('status', 'The report was updated and the reporter was notified.');
    }
}
