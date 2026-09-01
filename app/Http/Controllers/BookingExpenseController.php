<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingExpense;
use App\Models\ServiceProviderApplication;
use App\Models\Unit;
use App\Services\AppNotificationService;
use App\Services\FinancialAccountSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function updateStatus(Request $request, Booking $booking, BookingExpense $expense, AppNotificationService $notifications, FinancialAccountSelection $accountSelection): RedirectResponse
    {
        abort_unless($expense->booking_id === $booking->id, 404);
        $this->authorizeManager($request, $booking);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['recorded', 'assigned', 'completed', 'paid', 'cancelled'])],
            'payment_proof' => ['required_if:status,paid', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'financial_account_id' => ['nullable', 'integer'],
        ]);

        $financialAccount = $validated['status'] === 'paid'
            ? $accountSelection->resolve($booking->unit->host()->firstOrFail(), $validated['financial_account_id'] ?? null)
            : null;

        if ($validated['status'] === 'paid') {
            abort_unless($expense->provider_user_id && $expense->status === 'completed', 422, 'A provider must complete this task before it can be marked paid.');
        }
        if ($expense->provider_user_id && $validated['status'] === 'completed' && $expense->status !== 'completed') {
            abort(422, 'The assigned provider must mark this task completed.');
        }
        abort_if($expense->status === 'payment_received', 422, 'This task is already closed.');

        $proof = $validated['payment_proof'] ?? null;
        $proofPath = $proof?->store('booking-expenses/'.$expense->id.'/payment', 'local');

        try {
            DB::transaction(function () use ($expense, $validated, $proof, $proofPath, $financialAccount) {
                $locked = BookingExpense::query()->lockForUpdate()->findOrFail($expense->id);
                $status = $validated['status'];
                abort_if($locked->status === 'payment_received', 422, 'This task is already closed.');
                if ($status === 'paid') {
                    abort_unless($locked->provider_user_id && $locked->status === 'completed', 422, 'A provider must complete this task before it can be marked paid.');
                }
                if ($locked->provider_user_id && $status === 'completed' && $locked->status !== 'completed') {
                    abort(422, 'The assigned provider must mark this task completed.');
                }
                $locked->update([
                    'status' => $status,
                    'completed_at' => in_array($status, ['completed', 'paid'], true) ? ($locked->completed_at ?: now()) : null,
                    'paid_at' => $status === 'paid' ? now() : null,
                    'financial_account_id' => $status === 'paid' ? $financialAccount?->id : $locked->financial_account_id,
                    'payment_proof_path' => $status === 'paid' ? $proofPath : $locked->payment_proof_path,
                    'payment_proof_name' => $status === 'paid' ? $proof?->getClientOriginalName() : $locked->payment_proof_name,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

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

    public function paymentProof(Request $request, Booking $booking, BookingExpense $expense): StreamedResponse
    {
        abort_unless($expense->booking_id === $booking->id, 404);
        $booking->loadMissing('unit');
        abort_unless(
            $request->user()->is_admin
                || $booking->unit->host_id === $request->user()->id
                || $expense->provider_user_id === $request->user()->id,
            403,
        );
        abort_unless($expense->payment_proof_path && Storage::disk('local')->exists($expense->payment_proof_path), 404);

        return Storage::disk('local')->response($expense->payment_proof_path, $expense->payment_proof_name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeManager(Request $request, Booking $booking): void
    {
        abort_unless(
            $request->user()->is_admin || $booking->unit()->where('host_id', $request->user()->id)->exists(),
            403,
        );
    }
}
