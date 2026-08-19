<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\SupportReport;
use App\Models\Unit;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupportReportController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_admin) {
            return redirect()->route('admin.support-reports.index');
        }

        return view('support.index', [
            'reports' => SupportReport::query()->with(['unit', 'booking.unit', 'reviewer'])->where('reporter_id', $request->user()->id)->latest()->get(),
            'units' => $this->availableUnits($request->user())->get(),
            'bookings' => $this->availableBookings($request->user())->latest('start_at')->limit(100)->get(),
            'categories' => SupportReport::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->is_admin, 403);
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportReport::CATEGORIES))],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);

        $allowedUnitIds = $this->availableUnits($request->user())->pluck('id');
        if (! empty($validated['unit_id']) && ! $allowedUnitIds->contains((int) $validated['unit_id'])) {
            throw ValidationException::withMessages(['unit_id' => 'You can report only a listing you own or are assigned to.']);
        }

        if (! empty($validated['booking_id']) && ! $this->availableBookings($request->user())->whereKey($validated['booking_id'])->exists()) {
            throw ValidationException::withMessages(['booking_id' => 'You can report only a booking connected to your listings or affiliate sales.']);
        }

        $report = SupportReport::query()->create([
            ...$validated,
            'reporter_id' => $request->user()->id,
            'status' => 'open',
        ]);

        User::query()->where('is_admin', true)->where('is_active', true)->get()->each(fn (User $admin) => app(AppNotificationService::class)->send(
            $admin,
            'support_report',
            'New admin report: '.$report->subject,
            $request->user()->name.' submitted a '.$report->categoryLabel().'.',
            route('admin.support-reports.show', $report),
            'support-report:'.$report->id.':admin:'.$admin->id,
        ));

        return redirect()->route('support.index')->with('status', 'Your report was sent to the administrators.');
    }

    private function availableUnits(User $user): Builder
    {
        return Unit::query()->with('host:id,name')->where(function (Builder $units) use ($user) {
            $units->when($user->isHost(), fn (Builder $owned) => $owned->where('host_id', $user->id))
                ->orWhereHas('affiliatePartnerships', fn (Builder $partnerships) => $partnerships
                    ->where('status', 'accepted')
                    ->where('marketer_id', $user->id));
        })->orderBy('name');
    }

    private function availableBookings(User $user): Builder
    {
        return Booking::query()->with('unit:id,name')->where(function (Builder $bookings) use ($user) {
            if ($user->isHost()) {
                $bookings->whereHas('unit', fn (Builder $units) => $units->where('host_id', $user->id));
            }

            $bookings->orWhereHas('affiliatePartnership', fn (Builder $partnerships) => $partnerships->where('marketer_id', $user->id));
        });
    }
}
