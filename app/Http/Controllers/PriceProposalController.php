<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\InquiryPriceProposal;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PriceProposalController extends Controller
{
    public function store(Request $request, Inquiry $inquiry): RedirectResponse
    {
        abort_unless(in_array($request->user()->id, [$inquiry->client_id, $inquiry->host_id], true), 403);
        abort_if($inquiry->booking()->exists() || $inquiry->status === 'closed', 422, 'Price negotiation is available before a booking request is submitted.');
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $proposal = DB::transaction(function () use ($inquiry, $request, $validated) {
            $lockedInquiry = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);
            abort_if($lockedInquiry->booking()->exists(), 422, 'A booking request already exists for this inquiry.');
            $lockedInquiry->priceProposals()->where('status', 'pending')->update(['status' => 'superseded', 'responded_at' => now()]);

            return $lockedInquiry->priceProposals()->create([
                'proposed_by' => $request->user()->id,
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'status' => 'pending',
            ]);
        });

        $recipient = $request->user()->id === $inquiry->client_id ? $inquiry->host : $inquiry->client;
        app(AppNotificationService::class)->send(
            $recipient,
            'price_proposal',
            'New price proposal',
            $request->user()->name.' proposed ₱'.number_format((float) $proposal->amount, 2).' for '.$inquiry->unit->name.'.',
            route('inquiries.show', $inquiry),
        );

        return back()->with('status', 'Your price proposal was sent for approval.');
    }

    public function review(Request $request, InquiryPriceProposal $proposal): RedirectResponse
    {
        $proposal->loadMissing('inquiry.client', 'inquiry.host', 'inquiry.unit');
        $inquiry = $proposal->inquiry;
        abort_unless(in_array($request->user()->id, [$inquiry->client_id, $inquiry->host_id], true), 403);
        abort_if($proposal->proposed_by === $request->user()->id, 403, 'The other booking party must review this proposal.');
        abort_if($proposal->status !== 'pending' || $inquiry->booking()->exists(), 422, 'This proposal can no longer be reviewed.');
        $validated = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])]]);

        DB::transaction(function () use ($proposal, $inquiry, $validated) {
            $lockedProposal = InquiryPriceProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_if($lockedProposal->status !== 'pending', 422, 'This proposal was already reviewed.');
            $lockedProposal->update([
                'status' => $validated['decision'] === 'accept' ? 'accepted' : 'declined',
                'responded_at' => now(),
            ]);
            if ($validated['decision'] === 'accept') {
                $inquiry->update(['agreed_price' => $lockedProposal->amount, 'price_agreed_at' => now()]);
                $inquiry->priceProposals()->whereKeyNot($lockedProposal->id)->where('status', 'pending')->update(['status' => 'superseded', 'responded_at' => now()]);
            }
        });

        $recipient = $proposal->proposer;
        app(AppNotificationService::class)->send(
            $recipient,
            'price_proposal',
            $validated['decision'] === 'accept' ? 'Price proposal accepted' : 'Price proposal declined',
            $request->user()->name.' '.($validated['decision'] === 'accept' ? 'accepted' : 'declined').' the ₱'.number_format((float) $proposal->amount, 2).' proposal for '.$inquiry->unit->name.'.',
            route('inquiries.show', $inquiry),
        );

        return back()->with('status', $validated['decision'] === 'accept' ? 'The negotiated price is now locked for booking.' : 'The price proposal was declined. Either party may send a new offer.');
    }
}
