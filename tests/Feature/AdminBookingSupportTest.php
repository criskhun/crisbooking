<?php

namespace Tests\Feature;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\SupportReport;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBookingSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_and_permanently_remove_a_booking_with_an_audit_record(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Platform Admin']);
        $host = User::factory()->host()->create(['name' => 'Calendar Host']);
        $client = User::factory()->create(['name' => 'Test Customer']);
        $unit = $this->unit($host, 'Test Condo Record');
        $start = today()->addDays(8);
        Storage::disk('local')->put('booking-payment-proofs/1/proof.jpg', 'test proof');
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addDays(2),
            'status' => 'confirmed',
            'total_amount' => 6400,
            'party_size' => 2,
            'payment_proof_path' => 'booking-payment-proofs/1/proof.jpg',
        ]);

        $this->actingAs($host)->get(route('admin.bookings.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.bookings.index', [
            'host_id' => $host->id,
            'unit_id' => $unit->id,
            'status' => 'confirmed',
            'origin' => 'platform',
            'period' => 'upcoming',
        ]))->assertOk()
            ->assertSee('Booking records')
            ->assertSee('Test Condo Record')
            ->assertSee('Calendar Host')
            ->assertSee('Test Customer')
            ->assertSee('Delete record');

        $this->actingAs($admin)->delete(route('admin.bookings.destroy', $booking), [
            'confirmation' => 'remove',
            'removal_reason' => 'Testing record removed at the host request.',
        ])->assertRedirect(route('admin.bookings.index'));

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseHas('booking_deletions', [
            'original_booking_id' => $booking->id,
            'unit_id' => $unit->id,
            'host_id' => $host->id,
            'client_id' => $client->id,
            'removed_by' => $admin->id,
            'unit_name' => 'Test Condo Record',
            'removal_reason' => 'Testing record removed at the host request.',
        ]);
        Storage::disk('local')->assertMissing('booking-payment-proofs/1/proof.jpg');
        $this->assertTrue(Unit::query()->availableBetween($start, $start->copy()->addDay())->whereKey($unit)->exists());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $host->id, 'type' => 'booking_removed_by_admin']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $client->id, 'type' => 'booking_removed_by_admin']);

        $this->actingAs($admin)->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('Recently removed records')
            ->assertSee('Testing record removed at the host request.');
    }

    public function test_booking_removal_requires_an_admin_and_a_reason(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->host()->create();
        $unit = $this->unit($host, 'Protected Booking');
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'source_channel' => 'direct',
            'start_at' => today()->addDays(4),
            'end_at' => today()->addDays(5),
            'status' => 'confirmed',
            'total_amount' => 1000,
            'party_size' => 1,
        ]);

        $this->actingAs($host)->delete(route('admin.bookings.destroy', $booking), [
            'confirmation' => 'remove',
            'removal_reason' => 'Host may not hard delete.',
        ])->assertForbidden();
        $this->actingAs($admin)->from(route('admin.bookings.index'))->delete(route('admin.bookings.destroy', $booking), [
            'confirmation' => 'remove',
            'removal_reason' => '',
        ])->assertRedirect(route('admin.bookings.index'))->assertSessionHasErrors('removal_reason');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
        $this->assertDatabaseCount('booking_deletions', 0);
    }

    public function test_host_and_assigned_affiliate_can_report_to_admin_and_receive_a_response(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Support Admin']);
        $host = User::factory()->host()->create(['name' => 'Reporting Host']);
        $affiliate = User::factory()->create(['name' => 'Reporting Affiliate']);
        $outsider = User::factory()->create();
        $unit = $this->unit($host, 'Reported Car');
        $partnership = AffiliatePartnership::create([
            'marketer_id' => $affiliate->id,
            'host_id' => $host->id,
            'status' => 'accepted',
            'commission_percentage' => 10,
            'referral_code' => 'REPORT10',
            'application_message' => 'Affiliate request',
            'reviewed_at' => now(),
        ]);
        $partnership->units()->attach($unit);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booked_by_user_id' => $affiliate->id,
            'booking_origin' => 'manual',
            'source_channel' => 'affiliate',
            'affiliate_partnership_id' => $partnership->id,
            'external_customer_name' => 'Offline Customer',
            'start_at' => today()->addDays(12),
            'end_at' => today()->addDays(14),
            'status' => 'confirmed',
            'total_amount' => 5000,
            'party_size' => 3,
        ]);

        $this->actingAs($host)->get(route('support.index'))->assertOk()
            ->assertSee('Contact admin')
            ->assertSee('Reported Car')
            ->assertSee('#'.$booking->id);
        $this->actingAs($host)->post(route('support.store'), [
            'category' => 'booking_removal',
            'subject' => 'Please remove our test booking',
            'message' => 'This was created only to test the outside booking calendar.',
            'unit_id' => $unit->id,
            'booking_id' => $booking->id,
        ])->assertRedirect(route('support.index'));

        $report = SupportReport::query()->sole();
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'type' => 'support_report']);
        $this->actingAs($affiliate)->get(route('support.index'))->assertOk()->assertSee('Reported Car')->assertSee('#'.$booking->id);
        $this->actingAs($outsider)->from(route('support.index'))->post(route('support.store'), [
            'category' => 'booking_issue',
            'subject' => 'Unrelated record',
            'message' => 'I should not be able to attach another host booking.',
            'unit_id' => $unit->id,
            'booking_id' => $booking->id,
        ])->assertRedirect(route('support.index'))->assertSessionHasErrors('unit_id');

        $this->actingAs($host)->get(route('admin.support-reports.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.support-reports.index'))->assertOk()
            ->assertSee('Please remove our test booking')
            ->assertSee('Reporting Host')
            ->assertSee('Reported Car');
        $this->actingAs($admin)->get(route('admin.support-reports.show', $report))->assertOk()
            ->assertSee('Open in booking records')
            ->assertSee('This was created only to test the outside booking calendar.');
        $this->actingAs($admin)->patch(route('admin.support-reports.update', $report), [
            'status' => 'resolved',
            'admin_response' => 'The request was reviewed and the test record can now be removed.',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $host->id, 'type' => 'support_report_update']);
        $this->actingAs($host)->get(route('support.index'))->assertOk()
            ->assertSee('Resolved')
            ->assertSee('The request was reviewed and the test record can now be removed.');
    }

    private function unit(User $host, string $name): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => 'unit',
            'category' => 'condo',
            'location' => 'Davao City',
            'capacity' => 4,
            'price' => 3200,
            'pricing_unit' => 'night',
            'is_active' => true,
        ]);
    }
}
