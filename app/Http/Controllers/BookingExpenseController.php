<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingExpense;
use App\Models\ServiceProviderApplication;
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
        $booking->loadMissing('unit');
        $categories = BookingExpense::categoryOptions($booking->unit->category);
        $expenseRows = collect($request->input('expenses', []))
            ->filter(fn ($row) => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL))
            ->map(function ($row, $category) {
                $row['category'] = $category;
                $row['amount'] = str_replace([',', '₱', ' '], '', (string) ($row['amount'] ?? ''));

                return $row;
            })
            ->all();
        if ($expenseRows === [] && $request->filled('category')) {
            $expenseRows[] = [
                'category' => $request->input('category'),
                'amount' => str_replace([',', '₱', ' '], '', (string) $request->input('amount')),
                'service_unit_id' => $request->input('service_unit_id'),
                'provider_application_id' => $request->input('provider_application_id'),
                'vendor_name' => $request->input('vendor_name'),
                'scheduled_at' => $request->input('scheduled_at'),
                'notes' => $request->input('notes'),
            ];
        }
        $request->merge(['expenses' => $expenseRows]);
        $validated = $request->validate([
            'expenses' => ['required', 'array', 'min:1', 'max:20'],
            'expenses.*.category' => ['required', Rule::in(array_keys($categories))],
            'expenses.*.amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'expenses.*.provider_application_id' => ['nullable', 'integer', 'exists:service_provider_applications,id'],
            'expenses.*.service_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'expenses.*.vendor_name' => ['nullable', 'string', 'max:120'],
            'expenses.*.scheduled_at' => ['nullable', 'date'],
            'expenses.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $createdExpenses = DB::transaction(function () use ($validated, $booking, $request) {
            return collect($validated['expenses'])->map(function ($row) use ($booking, $request) {
                $providerApplication = null;
                $serviceUnit = null;
                if (! empty($row['provider_application_id'])) {
                    $providerApplication = ServiceProviderApplication::query()->with('applicant')->findOrFail($row['provider_application_id']);
                    if ($providerApplication->host_id !== $booking->unit->host_id
                        || $providerApplication->status !== 'accepted'
                        || collect(BookingExpense::compatibleProviderServices($row['category']))->intersect($providerApplication->services)->isEmpty()) {
                        throw ValidationException::withMessages([
                            'expenses' => 'Choose an approved provider who applied for each selected service.',
                        ]);
                    }
                } elseif (! empty($row['service_unit_id'])) {
                    $serviceUnit = Unit::query()->with('host')->findOrFail($row['service_unit_id']);
                    if ($serviceUnit->kind !== 'service' || ! $serviceUnit->is_active) {
                        throw ValidationException::withMessages(['expenses' => 'Choose an active service provider.']);
                    }
                }

                $vendorName = trim((string) ($row['vendor_name'] ?? ''));

                return $booking->expenses()->create([
                    'recorded_by_user_id' => $request->user()->id,
                    'provider_user_id' => $providerApplication?->applicant_user_id ?? $serviceUnit?->host_id,
                    'service_unit_id' => $serviceUnit?->id,
                    'service_provider_application_id' => $providerApplication?->id,
                    'category' => $row['category'],
                    'vendor_name' => ($providerApplication || $serviceUnit) ? null : ($vendorName !== '' ? $vendorName : null),
                    'amount' => round((float) $row['amount'], 2),
                    'status' => ($providerApplication || $serviceUnit) ? 'assigned' : 'recorded',
                    'scheduled_at' => $row['scheduled_at'] ?? null,
                    'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                ]);
            });
        });

        $createdExpenses->whereNotNull('provider_user_id')->each(function (BookingExpense $expense) use ($notifications, $booking) {
            $expense->loadMissing('provider');
            $notifications->send(
                $expense->provider,
                'service_work_assigned',
                'New service work assigned',
                $booking->unit->name.' needs '.$expense->categoryLabel().' for booking #'.$booking->id.'.',
                route('service-work.index'),
                'booking-expense:'.$expense->id.':assigned',
            );
        });

        return back()->with('status', $createdExpenses->count().' '.str('expense')->plural($createdExpenses->count()).' recorded'.($createdExpenses->whereNotNull('provider_user_id')->isNotEmpty() ? '; assigned providers were notified.' : '.'));
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
