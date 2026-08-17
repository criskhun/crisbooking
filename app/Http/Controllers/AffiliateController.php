<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePartnership;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $partnerships = AffiliatePartnership::query()
            ->with(['host:id,name', 'marketer:id,name', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->withCount(['bookings as confirmed_referrals_count' => fn ($query) => $query->where('status', 'confirmed')])
            ->withSum(['bookings as commission_earned' => fn ($query) => $query->where('status', 'confirmed')], 'affiliate_commission_amount')
            ->where(fn ($query) => $query->where('marketer_id', $user->id)->orWhere('host_id', $user->id))
            ->latest('updated_at')
            ->get();

        $availableHosts = User::query()
            ->select(['id', 'name', 'city', 'bio'])
            ->where('role', 'host')
            ->where('is_active', true)
            ->whereNotNull('profile_completed_at')
            ->where('id', '!=', $user->id)
            ->whereHas('units', fn ($query) => $query->where('is_active', true))
            ->whereDoesntHave('hostAffiliatePartnerships', fn ($query) => $query->where('marketer_id', $user->id))
            ->withCount(['units' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('affiliates.index', compact('partnerships', 'availableHosts'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->hasCompleteProfile()) {
            return redirect()->route('profile.edit')->withErrors(['profile' => 'Complete your verification profile before applying as a sales affiliate.']);
        }

        $validated = $request->validate([
            'host_id' => ['required', 'integer', 'different:marketer_id', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'host')->where('is_active', true)->whereNotNull('profile_completed_at'))],
            'application_message' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $host = User::query()->withCount(['units' => fn ($query) => $query->where('is_active', true)])->findOrFail($validated['host_id']);
        if ($host->id === $request->user()->id || $host->units_count < 1) {
            throw ValidationException::withMessages(['host_id' => 'Choose an established host with at least one active listing.']);
        }

        $affiliate = AffiliatePartnership::create([
            'marketer_id' => $request->user()->id,
            'host_id' => $host->id,
            'application_message' => $validated['application_message'],
        ]);

        app(AppNotificationService::class)->send(
            $host,
            'affiliate_application',
            'New affiliate application',
            $request->user()->name.' applied to market your listings.',
            route('affiliates.show', $affiliate),
        );

        return redirect()->route('affiliates.index')->with('status', 'Your sales affiliate application was sent to '.$host->name.'.');
    }

    public function show(Request $request, AffiliatePartnership $affiliate): View
    {
        abort_unless($affiliate->involves($request->user()), 403);
        $affiliate->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        $affiliate->load([
            'host.units' => fn ($query) => $query->where('is_active', true)->with(['rates', 'images']),
            'marketer',
            'messages.sender',
            'bookings' => fn ($query) => $query->with(['unit:id,name', 'client:id,name'])->latest()->limit(25),
            'reviews',
        ]);

        return view('affiliates.show', compact('affiliate'));
    }

    public function review(Request $request, AffiliatePartnership $affiliate): RedirectResponse
    {
        abort_unless($request->user()->is_admin || $affiliate->host_id === $request->user()->id, 403);
        abort_unless($affiliate->status === 'pending', 422, 'This application has already been reviewed.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
            'commission_percentage' => ['nullable', 'required_if:status,accepted', 'numeric', 'min:0.01', 'max:100'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($affiliate, $validated): void {
            $locked = AffiliatePartnership::query()->lockForUpdate()->findOrFail($affiliate->id);
            abort_unless($locked->status === 'pending', 422, 'This application has already been reviewed.');
            $locked->update([
                'status' => $validated['status'],
                'commission_percentage' => $validated['status'] === 'accepted' ? $validated['commission_percentage'] : null,
                'referral_code' => $validated['status'] === 'accepted' ? $this->uniqueReferralCode() : null,
                'review_note' => $validated['review_note'] ?? null,
                'reviewed_at' => now(),
            ]);
        });

        $affiliate->refresh()->load('marketer');
        app(AppNotificationService::class)->send(
            $affiliate->marketer,
            'affiliate_application_status',
            $validated['status'] === 'accepted' ? 'Affiliate application approved' : 'Affiliate application declined',
            'Your affiliate application was '.$validated['status'].'.',
            route('affiliates.show', $affiliate),
        );

        return redirect()->route('affiliates.show', $affiliate)->with('status', $validated['status'] === 'accepted'
            ? 'Affiliate approved. Their tracked sharing links are ready.'
            : 'The affiliate application was declined.');
    }

    public function message(Request $request, AffiliatePartnership $affiliate): RedirectResponse
    {
        abort_unless($affiliate->involves($request->user()), 403);
        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $affiliate->messages()->create(['sender_id' => $request->user()->id, 'body' => $validated['message']]);
        $affiliate->touch();

        $recipients = collect([$affiliate->host, $affiliate->marketer])
            ->filter(fn (User $user) => ! $user->is($request->user()));
        $recipients->each(fn (User $recipient) => app(AppNotificationService::class)->send(
            $recipient,
            'affiliate_message',
            'New affiliate message',
            $request->user()->name.' sent a partnership message.',
            route('affiliates.show', $affiliate),
        ));

        return back()->with('status', 'Message sent.');
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(12));
        } while (AffiliatePartnership::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
