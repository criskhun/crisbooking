<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_requests_can_overlap_until_one_is_pre_approved(): void
    {
        $host = User::factory()->host()->create();
        $firstClient = User::factory()->create();
        $secondClient = User::factory()->create();
        $unit = $this->unit($host);
        $start = now()->addWeek()->startOfHour();

        $this->booking($unit, $firstClient, $start, 'pending');
        $secondInquiry = $this->inquiry($unit, $secondClient, $start->copy()->addMinutes(30));

        $this->actingAs($secondClient)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $secondInquiry->id,
            'start_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
            'end_at' => $start->copy()->addHours(2)->toDateTimeString(),
        ])->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
        $this->assertSame(2, Booking::where('status', 'pending')->count());
    }

    public function test_pre_approval_requires_payment_proof_and_confirmation_disables_conflicting_pending_requests(): void
    {
        Storage::fake('local');
        $host = User::factory()->host()->create();
        $firstClient = User::factory()->create();
        $secondClient = User::factory()->create();
        $outsider = User::factory()->create();
        $unit = $this->unit($host);
        $start = now()->addWeek()->startOfHour();
        $selected = $this->booking($unit, $firstClient, $start, 'pending');
        $competing = $this->booking($unit, $secondClient, $start->copy()->addMinutes(30), 'pending');

        $this->actingAs($host)->patch(route('bookings.status', $selected), [
            'status' => 'pre_approved',
        ])->assertRedirect();
        $this->assertSame('pre_approved', $selected->fresh()->status);

        $this->actingAs($host)->patch(route('bookings.status', $selected), [
            'status' => 'confirmed',
        ])->assertStatus(422);

        $this->actingAs($host)->patch(route('bookings.status', $competing), [
            'status' => 'pre_approved',
        ])->assertSessionHasErrors('status');

        $proof = UploadedFile::fake()->image('payment-receipt.jpg');
        $this->actingAs($firstClient)->post(route('bookings.payment-proof.store', $selected), [
            'payment_proof' => $proof,
        ])->assertRedirect();

        $selected->refresh();
        $this->assertSame('payment_submitted', $selected->status);
        Storage::disk('local')->assertExists($selected->payment_proof_path);
        $this->actingAs($outsider)->get(route('bookings.payment-proof.show', $selected))->assertForbidden();
        $this->actingAs($host)->get(route('bookings.payment-proof.show', $selected))->assertOk();

        $this->actingAs($host)->patch(route('bookings.status', $selected), [
            'status' => 'confirmed',
        ])->assertRedirect();

        $this->assertSame('confirmed', $selected->fresh()->status);
        $this->assertNotNull($selected->fresh()->payment_reviewed_at);
        $this->assertSame('unavailable', $competing->fresh()->status);
        $this->assertSame('closed', $competing->inquiry->fresh()->status);

        $this->actingAs($host)->get(route('bookings.show', $competing))
            ->assertOk()
            ->assertSee('Schedule no longer available')
            ->assertDontSee('Pre-approve');
    }

    public function test_host_can_explicitly_decline_a_pending_request(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $booking = $this->booking($this->unit($host), $client, now()->addWeek(), 'pending');

        $this->actingAs($host)->patch(route('bookings.status', $booking), [
            'status' => 'declined',
        ])->assertRedirect();

        $this->assertSame('declined', $booking->fresh()->status);
        $this->assertSame('closed', $booking->inquiry->fresh()->status);
    }

    private function unit(User $host): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => 'Approval Workflow Service',
            'kind' => 'service',
            'category' => 'driving',
            'location' => 'Davao City',
            'rules' => 'Coordinate before the service.',
            'capacity' => 4,
            'price' => 600,
            'pricing_unit' => 'hour',
            'is_active' => true,
        ]);
    }

    private function inquiry(Unit $unit, User $client, mixed $start): Inquiry
    {
        return Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'host_id' => $unit->host_id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHours(2),
            'party_size' => 1,
            'status' => 'open',
        ]);
    }

    private function booking(Unit $unit, User $client, mixed $start, string $status): Booking
    {
        $inquiry = $this->inquiry($unit, $client, $start);

        return Booking::create([
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => $status,
            'total_amount' => 1200,
            'party_size' => 1,
        ]);
    }
}
