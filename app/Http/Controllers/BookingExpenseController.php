<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingExpense;
use App\Models\Unit;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingExpenseController extends Controller
{
    public function store(Request $request, Booking $booking, AppNotificationService $notifications): RedirectResponse
    {
        $this->authorizeManager($request, $booking);
        if ($request->filled('amount')) {
            $request->merge(['amount' => str_replace([',', '₱', ' '], '', (string) $request->input('amount'))]);
        }

        $categories = BookingExpense::categoryOptions($booking->unit()->value('category'));
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys($categories))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'service_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $serviceUnit = null;
        if (! empty($validated['service_unit_id'])) {
            $serviceUnit = Unit::query()->with('host')->findOrFail($validated['service_unit_id']);
            if ($serviceUnit->kind !== 'service' || ! $serviceUnit->is_active) {
                throw ValidationException::withMessages([
                    'service_unit_id' => 'Choose an active service-provider listing.',
                ]);
            }
        }

        $vendorName = trim((string) ($validated['vendor_name'] ?? ''));
        $expense = $booking->expenses()->create([
            'recorded_by_user_id' => $request->user()->id,
            'provider_user_id' => $serviceUnit?->host_id,
            'service_unit_id' => $serviceUnit?->id,
            'category' => $validated['category'],
            'vendor_name' => $serviceUnit ? null : ($vendorName !== '' ? $vendorName : null),
            'amount' => round((float) $validated['amount'], 2),
            'status' => $serviceUnit ? 'assigned' : 'recorded',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        if ($serviceUnit) {
            $notifications->send(
                $serviceUnit->host,
                'service_work_assigned',
                'New service work assigned',
                $booking->unit()->value('name').' needs '.$expense->categoryLabel().' for booking #'.$booking->id.'.',
                route('service-work.index'),
                'booking-expense:'.$expense->id.':assigned',
            );
        }

        return back()->with('status', 'Expense recorded'.($serviceUnit ? ' and the service provider was notified.' : '.'));
    }

    public function updateStatus(Request $request, Booking $booking, BookingExpense $expense, AppNotificationService $notifications): RedirectResponse
    {
        abort_unless($expense->booking_id === $booking->id, 404);
        $this->authorizeManager($request, $booking);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['recorded', 'assigned', 'completed', 'paid', 'cancelled'])],
        ]);

        DB::transaction(function () use ($expense, $validated) {
            $locked = BookingExpense::query()->lockForUpdate()->findOrFail($expense->id);
            $status = $validated['status'];
            $locked->update([
                'status' => $status,
                'completed_at' => in_array($status, ['completed', 'paid'], true) ? ($locked->completed_at ?: now()) : null,
                'paid_at' => $status === 'paid' ? now() : null,
            ]);
        });

        if ($expense->provider_user_id) {
            $notifications->send(
                $expense->provider,
                'service_work_status',
                'Service work updated',
                $expense->categoryLabel().' for booking #'.$booking->id.' is now '.$validated['status'].'.',
                route('service-work.index'),
                'booking-expense:'.$expense->id.':status:'.$validated['status'],
            );
        }

        return back()->with('status', 'Expense status updated.');
    }

    private function authorizeManager(Request $request, Booking $booking): void
    {
        abort_unless(
            $request->user()->is_admin || $booking->unit()->where('host_id', $request->user()->id)->exists(),
            403,
        );
    }
}
