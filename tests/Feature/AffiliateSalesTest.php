<?php

namespace Tests\Feature;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_listing_has_a_public_page_but_inquiry_requires_an_account(): void
    {
        $unit = $this->unit(User::factory()->host()->create());

        $this->get(route('listings.show', $unit))
            ->assertOk()
            ->assertSee($unit->name)
            ->assertSee('Log in to inquire or book')
            ->assertSee(route('listings.inquire', $unit), false);

        $this->get(route('listings.inquire', $unit))
            ->assertRedirect(route('login'));

        $unit->update(['is_active' => false]);
        $this->get(route('listings.show', $unit))->assertNotFound();
    }

    public function test_user_can_apply_and_only_the_host_can_accept_with_a_percentage(): void
    {
        $host = User::factory()->host()->create(['name' => 'Established Host']);
        $marketer = User::factory()->create(['name' => 'Sales Marketer']);
        $outsider = User::factory()->create();
        $unit = $this->unit($host);

        $this->actingAs($marketer)->post(route('affiliates.store'), [
            'host_id' => $host->id,
            'application_message' => 'I will market your rentals through my local travel community and social channels.',
        ])->assertRedirect(route('affiliates.index'));

        $affiliate = AffiliatePartnership::firstOrFail();
        $this->assertSame('pending', $affiliate->status);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $host->id,
            'type' => 'affiliate_application',
        ]);

        $this->actingAs($outsider)->patch(route('affiliates.review', $affiliate), [
            'status' => 'accepted',
            'commission_percentage' => 12.5,
        ])->assertForbidden();

        $this->actingAs($host)->patch(route('affiliates.review', $affiliate), [
            'status' => 'accepted',
            'commission_percentage' => 12.5,
        ])->assertSessionHasErrors('unit_ids');

        $this->actingAs($host)->patch(route('affiliates.review', $affiliate), [
            'status' => 'accepted',
            'commission_percentage' => 12.5,
            'unit_ids' => [$unit->id],
            'review_note' => 'Approved for all active listings.',
        ])->assertRedirect(route('affiliates.show', $affiliate));

        $affiliate->refresh();
        $this->assertSame('accepted', $affiliate->status);
        $this->assertSame('12.50', $affiliate->commission_percentage);
        $this->assertNotNull($affiliate->referral_code);
        $this->assertTrue($affiliate->units()->whereKey($unit->id)->exists());
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $marketer->id,
            'type' => 'affiliate_application_status',
        ]);

        $this->actingAs($marketer)->post(route('affiliates.messages.store', $affiliate), [
            'message' => 'Thank you. I will begin sharing the tracked links today.',
        ])->assertRedirect();
        $this->assertDatabaseHas('affiliate_messages', ['affiliate_partnership_id' => $affiliate->id, 'sender_id' => $marketer->id]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $host->id,
            'type' => 'affiliate_message',
        ]);
    }

    public function test_tracked_link_snapshots_commission_on_inquiry_and_booking(): void
    {
        $host = User::factory()->host()->create();
        $marketer = User::factory()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host, ['price' => 600, 'pricing_unit' => 'hour']);
        $affiliate = AffiliatePartnership::create([
            'host_id' => $host->id,
            'marketer_id' => $marketer->id,
            'status' => 'accepted',
            'commission_percentage' => 15,
            'referral_code' => 'TRACKEDSALE1',
            'application_message' => 'I will promote this host to my established client audience.',
            'reviewed_at' => now(),
        ]);
        $affiliate->units()->attach($unit);
        $start = now()->addDays(3)->startOfHour();
        $end = $start->copy()->addHours(2);

        $this->actingAs($client)->post(route('inquiries.store'), [
            'unit_id' => $unit->id,
            'desired_start_at' => $start->toDateTimeString(),
            'desired_end_at' => $end->toDateTimeString(),
            'party_size' => 2,
            'initial_message' => 'I found this through the shared sales link and would like to book.',
            'referral_code' => $affiliate->referral_code,
        ])->assertRedirect();

        $inquiry = Inquiry::firstOrFail();
        $this->assertSame($affiliate->id, $inquiry->affiliate_partnership_id);
        $this->assertSame('15.00', $inquiry->affiliate_commission_percentage);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $marketer->id,
            'type' => 'affiliate_referral',
        ]);

        $affiliate->update(['commission_percentage' => 20]);

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'party_size' => 2,
        ])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame($affiliate->id, $booking->affiliate_partnership_id);
        $this->assertSame('15.00', $booking->affiliate_commission_percentage);
        $this->assertSame('180.00', $booking->affiliate_commission_amount);
        $this->assertSame('1200.00', $booking->total_amount);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $marketer->id,
            'type' => 'affiliate_booking',
        ]);

        $this->actingAs($client)->patch(route('bookings.change-request', $booking), [
            'change_start_at' => $start->toDateTimeString(),
            'change_end_at' => $end->copy()->addHour()->toDateTimeString(),
            'change_party_size' => 2,
        ])->assertRedirect();

        $this->actingAs($host)->patch(route('bookings.change-request.review', $booking), [
            'decision' => 'approve',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame('1800.00', $booking->total_amount);
        $this->assertSame('270.00', $booking->affiliate_commission_amount);
    }

    public function test_host_can_manage_assigned_listings_and_unassigned_links_are_not_tracked(): void
    {
        $host = User::factory()->host()->create();
        $marketer = User::factory()->create();
        $client = User::factory()->create();
        $assigned = $this->unit($host, ['name' => 'Assigned Service']);
        $unassigned = $this->unit($host, ['name' => 'Unassigned Service']);
        $affiliate = AffiliatePartnership::create([
            'host_id' => $host->id,
            'marketer_id' => $marketer->id,
            'status' => 'accepted',
            'commission_percentage' => 10,
            'referral_code' => 'ASSIGNEDONLY1',
            'application_message' => 'I will market the listings assigned by this host.',
            'reviewed_at' => now(),
        ]);
        $affiliate->units()->attach($assigned);

        $this->get($assigned->publicUrl($affiliate->referral_code))->assertOk()->assertSee('Shared by an approved sales affiliate');
        $this->get($unassigned->publicUrl($affiliate->referral_code))->assertOk()->assertDontSee('Shared by an approved sales affiliate');

        $this->actingAs($host)->get(route('affiliates.index'))
            ->assertOk()
            ->assertSee('Affiliate management')
            ->assertSee('1 assigned listing');

        $this->actingAs($host)->patch(route('affiliates.assignments.update', $affiliate), [
            'commission_percentage' => 14,
            'unit_ids' => [$unassigned->id],
        ])->assertRedirect();

        $this->assertSame('14.00', $affiliate->fresh()->commission_percentage);
        $this->assertFalse($affiliate->units()->whereKey($assigned->id)->exists());
        $this->assertTrue($affiliate->units()->whereKey($unassigned->id)->exists());

        $start = now()->addDays(3)->startOfHour();
        $this->actingAs($client)->post(route('inquiries.store'), [
            'unit_id' => $assigned->id,
            'desired_start_at' => $start->toDateTimeString(),
            'desired_end_at' => $start->copy()->addHour()->toDateTimeString(),
            'party_size' => 1,
            'initial_message' => 'I want to ask about this listing through the provided link.',
            'referral_code' => $affiliate->referral_code,
        ])->assertRedirect();

        $this->assertNull(Inquiry::latest('id')->firstOrFail()->affiliate_partnership_id);
    }

    private function unit(User $host, array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'host_id' => $host->id,
            'name' => 'Affiliate-ready Driving Service',
            'kind' => 'service',
            'category' => 'driving',
            'location' => 'Davao City',
            'description' => 'A reliable local service ready to be marketed by approved affiliates.',
            'rules' => 'Coordinate the meeting point with the host.',
            'capacity' => 4,
            'price' => 600,
            'pricing_unit' => 'session',
            'is_active' => true,
        ], $attributes));
    }
}
