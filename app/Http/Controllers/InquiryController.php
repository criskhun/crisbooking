<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePartnership;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\Unit;
use App\Services\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $inquiries = Inquiry::query()
            ->with(['unit:id,name,category,photo_path', 'client:id,name,profile_completed_at,profile_image_path,google_avatar,facebook_avatar', 'host:id,name,profile_completed_at,profile_image_path,google_avatar,facebook_avatar', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->withCount(['messages as unread_messages_count' => fn ($query) => $query->where('sender_id', '!=', $user->id)->whereNull('read_at')])
            ->when(! $user->is_admin, fn ($query) => $query->where(function ($participants) use ($user) {
                $participants->where('client_id', $user->id)->orWhere('host_id', $user->id);
            }))
            ->latest('updated_at')
            ->get();

        return view('inquiries.index', compact('inquiries'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->hasCompleteProfile()) {
            return redirect()->route('profile.edit')->withErrors(['profile' => 'Complete your identity and contact profile before starting an inquiry.']);
        }

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'desired_start_at' => ['required', 'date', 'after:now'],
            'desired_end_at' => ['required', 'date', 'after:desired_start_at'],
            'party_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'initial_message' => ['required', 'string', 'min:10', 'max:2000'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ]);

        $unit = Unit::query()->where('is_active', true)->with('host')->findOrFail($validated['unit_id']);

        if ($unit->host_id === $request->user()->id) {
            throw ValidationException::withMessages(['unit_id' => 'You cannot inquire about or book your own listing.']);
        }

        if (! $unit->host->hasCompleteProfile()) {
            throw ValidationException::withMessages(['unit_id' => 'This host must complete verification before receiving inquiries.']);
        }

        if ($unit->capacity && $validated['party_size'] > $unit->capacity) {
            throw ValidationException::withMessages(['party_size' => "This listing can accommodate up to {$unit->capacity} people."]);
        }

        [$desiredStart, $desiredEnd] = $unit->standardizeBookingPeriod(
            Carbon::parse($validated['desired_start_at']),
            Carbon::parse($validated['desired_end_at']),
        );

        if ($desiredStart->isPast()) {
            throw ValidationException::withMessages(['desired_start_at' => 'Choose a check-in date whose host-set arrival time is still in the future.']);
        }

        if ($desiredEnd->lte($desiredStart)) {
            throw ValidationException::withMessages(['desired_end_at' => 'Check-out must be on a later date than check-in for this property.']);
        }

        $affiliate = filled($validated['referral_code'] ?? null)
            ? AffiliatePartnership::query()
                ->where('referral_code', $validated['referral_code'])
                ->where('host_id', $unit->host_id)
                ->where('status', 'accepted')
                ->whereHas('units', fn ($units) => $units->whereKey($unit->id))
                ->first()
            : null;

        $inquiry = DB::transaction(function () use ($request, $validated, $unit, $affiliate, $desiredStart, $desiredEnd) {
            $inquiry = Inquiry::create([
                'unit_id' => $unit->id,
                'client_id' => $request->user()->id,
                'host_id' => $unit->host_id,
                'desired_start_at' => $desiredStart,
                'desired_end_at' => $desiredEnd,
                'party_size' => $validated['party_size'],
                'status' => 'open',
                'affiliate_partnership_id' => $affiliate?->id,
                'affiliate_commission_percentage' => $affiliate?->commission_percentage,
            ]);
            $inquiry->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $validated['initial_message'],
            ]);

            return $inquiry;
        });

        app(AppNotificationService::class)->send(
            $unit->host,
            'inquiry',
            'New listing inquiry',
            $request->user()->name.' sent an inquiry about '.$unit->name.'.',
            route('inquiries.show', $inquiry),
        );

        if ($affiliate) {
            $affiliate->loadMissing('marketer');
            app(AppNotificationService::class)->send(
                $affiliate->marketer,
                'affiliate_referral',
                'New referred inquiry',
                $request->user()->name.' used your referral link for '.$unit->name.'.',
                route('affiliates.show', $affiliate),
            );
        }

        return redirect()->route('inquiries.show', $inquiry)->with('status', 'Your inquiry was sent. You can now chat with the host before booking.');
    }

    public function show(Request $request, Inquiry $inquiry): View
    {
        abort_unless($inquiry->involves($request->user()), 403);
        $inquiry->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        $inquiry->load(['unit.rates', 'unit.images', 'client', 'host', 'messages.sender', 'booking', 'priceProposals.proposer']);

        return view('inquiries.show', compact('inquiry'));
    }

    public function message(Request $request, Inquiry $inquiry): RedirectResponse|JsonResponse
    {
        abort_unless($inquiry->involves($request->user()), 403);
        abort_if($inquiry->status === 'closed', 422, 'This inquiry is closed.');

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $bookingApproved = $inquiry->booking()->where('status', 'confirmed')->exists();
        if ($request->hasFile('attachment') && ! $bookingApproved) {
            throw ValidationException::withMessages(['attachment' => 'Images can be attached after the booking is approved.']);
        }

        $attachment = $request->file('attachment');
        $message = $inquiry->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $validated['message'] ?? '',
            'attachment_path' => $attachment?->store('inquiry-attachments/'.$inquiry->id, 'local'),
            'attachment_name' => $attachment?->getClientOriginalName(),
        ]);
        $inquiry->touch();
        Cache::forget($this->typingKey($inquiry, $request->user()->id));
        $recipient = $request->user()->id === $inquiry->client_id ? $inquiry->host : $inquiry->client;
        app(AppNotificationService::class)->send(
            $recipient,
            'chat_message',
            'New chat message',
            $request->user()->name.' sent a message about '.$inquiry->unit->name.'.',
            route('inquiries.show', $inquiry),
        );

        if ($request->expectsJson()) {
            $message->load('sender');

            return response()->json(['message' => $this->messagePayload($message, $request->user()->id)]);
        }

        return back()->with('status', 'Message sent.');
    }

    public function messages(Request $request, Inquiry $inquiry): JsonResponse
    {
        abort_unless($inquiry->involves($request->user()), 403);
        $afterId = max(0, (int) $request->query('after_id', 0));
        $messages = $inquiry->messages()->with('sender:id,name')->where('id', '>', $afterId)->orderBy('id')->limit(100)->get();
        $inquiry->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        $partnerId = $request->user()->id === $inquiry->client_id ? $inquiry->host_id : $inquiry->client_id;
        $partnerName = $request->user()->id === $inquiry->client_id ? $inquiry->host->name : $inquiry->client->name;

        return response()->json([
            'messages' => $messages->map(fn ($message) => $this->messagePayload($message, $request->user()->id))->values(),
            'typing' => Cache::has($this->typingKey($inquiry, $partnerId)),
            'typing_text' => $partnerName.' is typing…',
        ]);
    }

    public function typing(Request $request, Inquiry $inquiry): JsonResponse
    {
        abort_unless($inquiry->involves($request->user()), 403);
        $validated = $request->validate(['is_typing' => ['required', 'boolean']]);
        $key = $this->typingKey($inquiry, $request->user()->id);

        if ($validated['is_typing']) {
            Cache::put($key, true, now()->addSeconds(5));
        } else {
            Cache::forget($key);
        }

        return response()->json(['ok' => true]);
    }

    public function attachment(Request $request, InquiryMessage $message): StreamedResponse
    {
        $message->loadMissing('inquiry');
        abort_unless($message->inquiry->involves($request->user()), 403);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function typingKey(Inquiry $inquiry, int $userId): string
    {
        return "inquiry:{$inquiry->id}:typing:{$userId}";
    }

    private function messagePayload(InquiryMessage $message, int $viewerId): array
    {
        return [
            'id' => $message->id,
            'mine' => $message->sender_id === $viewerId,
            'sender' => $message->sender_id === $viewerId ? 'You' : $message->sender->name,
            'body' => $message->body,
            'time' => $message->created_at->format('M j, g:i A'),
            'attachment_url' => $message->attachment_path ? route('inquiries.attachments.show', $message) : null,
            'attachment_name' => $message->attachment_name,
        ];
    }
}
