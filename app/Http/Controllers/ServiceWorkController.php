<?php

namespace App\Http\Controllers;

use App\Models\BookingExpense;
use App\Models\ServiceProviderApplication;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceWorkController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = BookingExpense::query()
            ->with(['booking.unit:id,host_id,name,category,location', 'serviceUnit:id,name,category'])
            ->where('provider_user_id', $request->user()->id)
            ->latest('scheduled_at')
            ->latest()
            ->get();
        $active = $assignments->whereIn('status', ['assigned', 'completed']);
        $metrics = [
            'active_count' => $active->count(),
            'assigned_total' => $assignments->where('status', '!=', 'cancelled')->sum('amount'),
            'payment_pending' => $assignments->where('status', 'completed')->sum('amount'),
            'paid_total' => $assignments->where('status', 'paid')->sum('amount'),
        ];
        $myApplications = ServiceProviderApplication::query()
            ->with('host:id,name')
            ->where('applicant_user_id', $request->user()->id)
            ->latest()
            ->get();
        $receivedApplicationsQuery = ServiceProviderApplication::query()->with('applicant:id,name,email');
        if (! $request->user()->is_admin) {
            $receivedApplicationsQuery->where(
                $request->user()->isHost() ? 'host_id' : 'id',
                $request->user()->isHost() ? $request->user()->id : 0,
            );
        }
        $receivedApplications = $receivedApplicationsQuery->latest()->get();
        $availableHosts = User::query()
            ->where('role', 'host')
            ->where('is_active', true)
            ->whereKeyNot($request->user()->id)
            ->whereHas('units', fn ($units) => $units->whereIn('category', ['car', 'condo'])->where('is_active', true))
            ->withCount(['units' => fn ($units) => $units->whereIn('category', ['car', 'condo'])->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('service-work.index', compact('assignments', 'metrics', 'myApplications', 'receivedApplications', 'availableHosts'));
    }

    public function complete(Request $request, BookingExpense $expense, AppNotificationService $notifications): RedirectResponse
    {
        abort_unless($expense->provider_user_id === $request->user()->id, 403);
        abort_unless($expense->status === 'assigned', 422, 'Only assigned work can be marked completed.');
        $expense->update(['status' => 'completed', 'completed_at' => now()]);
        $expense->loadMissing('booking.unit.host');
        $notifications->send(
            $expense->booking->unit->host,
            'service_work_completed',
            'Assigned service completed',
            $request->user()->name.' completed '.$expense->categoryLabel().' for booking #'.$expense->booking_id.'.',
            route('bookings.show', $expense->booking_id),
            'booking-expense:'.$expense->id.':completed',
        );

        return back()->with('status', 'Work marked completed. The booking host was notified.');
    }
}
