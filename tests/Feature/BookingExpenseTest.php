<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingExpense;
use App\Models\ServiceProviderApplication;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_record_a_booking_expense_assign_a_provider_and_track_the_providers_earnings(): void
    {
        Storage::fake('local');
        $host = User::factory()->host()->create();
        $account = $this->financialAccount($host);
        $client = User::factory()->create();
        $provider = User::factory()->create(['name' => 'Davao Cleaning Partner']);
        $condo = $this->unit($host, 'Expense Test Condo', 'condo');
        $providerApplication = ServiceProviderApplication::create([
            'applicant_user_id' => $provider->id,
            'host_id' => $host->id,
            'services' => ['cleaning', 'laundry'],
            'status' => 'accepted',
            'application_message' => 'I provide reliable cleaning and laundry assistance.',
            'reviewed_at' => now(),
        ]);
        $booking = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => $client->id,
            'booking_origin' => 'platform',
            'start_at' => now()->addDays(3)->setTime(14, 0),
            'end_at' => now()->addDays(4)->setTime(12, 0),
            'status' => 'confirmed',
            'total_amount' => 5000,
            'party_size' => 2,
        ]);

        $this->actingAs($host)->post(route('bookings.expenses.store', $booking), [
            'expenses' => [
                'cleaning' => [
                    'enabled' => 1,
                    'amount' => '1,250.00',
                    'provider_application_id' => $providerApplication->id,
                    'vendor_name' => 'This vendor must be ignored',
                    'scheduled_at' => now()->addDays(4)->setTime(12, 30)->format('Y-m-d H:i:s'),
                    'notes' => 'Turnover cleaning after checkout.',
                ],
                'laundry' => [
                    'enabled' => 1,
                    'amount' => '400.00',
                    'vendor_name' => 'Neighborhood Laundry Shop',
                ],
                'drinking_water' => ['enabled' => 0],
            ],
        ])->assertRedirect()->assertSessionHas('status');

        $expense = BookingExpense::query()->where('category', 'cleaning')->sole();
        $this->assertSame($provider->id, $expense->provider_user_id);
        $this->assertSame($providerApplication->id, $expense->service_provider_application_id);
        $this->assertNull($expense->service_unit_id);
        $this->assertNull($expense->vendor_name);
        $this->assertSame('assigned', $expense->status);
        $this->assertSame('1250.00', $expense->amount);
        $this->assertDatabaseHas('booking_expenses', [
            'booking_id' => $booking->id,
            'category' => 'laundry',
            'vendor_name' => 'Neighborhood Laundry Shop',
            'amount' => 400,
        ]);
        $this->assertDatabaseCount('booking_expenses', 2);
        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $provider->id,
            'type' => 'service_work_assigned',
        ]);

        $this->actingAs($host)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking expenses & assigned services', false)
            ->assertSee('name="expenses[cleaning][enabled]"', false)
            ->assertSee('name="expenses[laundry][enabled]"', false)
            ->assertSee('data-expense-provider-select', false)
            ->assertSee('data-expense-vendor-field', false)
            ->assertSee('Record all selected expenses')
            ->assertSee('Davao Cleaning Partner')
            ->assertSee('Neighborhood Laundry Shop')
            ->assertSee('Net ₱3,350.00');
        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Private operating costs')
            ->assertDontSee('Davao Cleaning Partner')
            ->assertDontSee('Neighborhood Laundry Shop');

        $this->actingAs($provider)->get(route('service-work.index'))
            ->assertOk()
            ->assertSee('Service providers & earnings', false)
            ->assertSee('Expense Test Condo')
            ->assertSee('₱1,250.00');
        $this->actingAs($provider)->patch(route('service-work.complete', $expense), [
            'completion_images' => [
                UploadedFile::fake()->image('cleaned-room.jpg'),
                UploadedFile::fake()->image('fresh-linens.png'),
            ],
        ])
            ->assertRedirect();
        $expense->refresh();
        $this->assertSame('completed', $expense->status);
        $this->assertCount(2, $expense->completion_images);
        Storage::disk('local')->assertExists(collect($expense->completion_images)->pluck('path')->all());
        $this->actingAs($host)->get(route('service-work.completion-images.show', [$expense, 0]))->assertOk();
        $this->actingAs($client)->get(route('service-work.completion-images.show', [$expense, 0]))->assertForbidden();

        $this->actingAs($host)->from(route('bookings.show', $booking))->patch(route('bookings.expenses.status', [$booking, $expense]), [
            'status' => 'paid',
        ])->assertRedirect(route('bookings.show', $booking))->assertSessionHasErrors('payment_proof');
        $this->assertSame('completed', $expense->fresh()->status);

        $this->actingAs($host)->patch(route('bookings.expenses.status', [$booking, $expense]), [
            'status' => 'paid',
            'payment_proof' => UploadedFile::fake()->image('provider-transfer.jpg'),
            'financial_account_id' => $account->id,
        ])->assertRedirect();
        $expense->refresh();
        $this->assertSame('paid', $expense->status);
        $this->assertNotNull($expense->paid_at);
        Storage::disk('local')->assertExists($expense->payment_proof_path);
        $this->actingAs($provider)->get(route('bookings.expenses.payment-proof', [$booking, $expense]))->assertOk();
        $this->actingAs($client)->get(route('bookings.expenses.payment-proof', [$booking, $expense]))->assertForbidden();
        $this->actingAs($provider)->get(route('service-work.index'))
            ->assertOk()
            ->assertSee('Paid earnings')
            ->assertSee('₱1,250.00')
            ->assertSee('Confirm payment received');
        $this->actingAs($provider)->patch(route('service-work.payment-received', $expense))->assertRedirect();
        $expense->refresh();
        $this->assertSame('payment_received', $expense->status);
        $this->assertNotNull($expense->payment_received_at);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $host->id,
            'type' => 'service_payment_received',
        ]);
        $this->actingAs($provider)->patch(route('service-work.payment-received', $expense))
            ->assertRedirect()
            ->assertSessionHas('status', 'Payment was already confirmed. This service task is closed.');
    }

    public function test_regular_user_can_apply_directly_to_a_host_without_a_service_listing(): void
    {
        Storage::fake('local');
        $host = User::factory()->host()->create(['name' => 'Condo Operations Host']);
        $applicant = User::factory()->create(['name' => 'Independent Laundry Worker']);
        $this->unit($host, 'Application Test Condo', 'condo');

        $this->actingAs($applicant)->get(route('service-work.index'))
            ->assertOk()
            ->assertSee('Condo Operations Host')
            ->assertSee('You do not need to create a public service listing.');
        $this->actingAs($applicant)->post(route('service-provider-applications.store'), [
            'host_id' => $host->id,
            'services' => ['cleaning', 'laundry'],
            'application_message' => 'I have experience preparing condo linens and cleaning rooms.',
            'application_images' => [
                UploadedFile::fake()->image('previous-cleaning.jpg'),
                UploadedFile::fake()->image('laundry-sample.webp'),
            ],
        ])->assertRedirect()->assertSessionHas('status');

        $application = ServiceProviderApplication::query()->sole();
        $this->assertSame('pending', $application->status);
        $this->assertSame(['cleaning', 'laundry'], $application->services);
        $this->assertCount(2, $application->application_images);
        Storage::disk('local')->assertExists(collect($application->application_images)->pluck('path')->all());
        $this->assertDatabaseCount('units', 1);
        $this->actingAs($host)->get(route('service-work.index'))
            ->assertOk()
            ->assertSee('Independent Laundry Worker')
            ->assertSee('Cleaning, Laundry')
            ->assertSee('Application images:');
        $this->actingAs($host)->get(route('service-provider-applications.images.show', [$application, 0]))->assertOk();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->get(route('service-provider-applications.images.show', [$application, 0]))->assertForbidden();

        $this->actingAs($host)->patch(route('service-provider-applications.review', $application), [
            'status' => 'accepted',
            'review_note' => 'Approved for turnovers.',
        ])->assertRedirect();
        $this->assertSame('accepted', $application->fresh()->status);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $applicant->id,
            'type' => 'service_provider_application_status',
        ]);
    }

    public function test_only_the_booking_host_or_admin_can_record_expenses(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $client = User::factory()->create();
        $condo = $this->unit($host, 'Private Expense Condo', 'condo');
        $booking = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => $client->id,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(3),
            'status' => 'confirmed',
            'total_amount' => 3500,
            'party_size' => 1,
        ]);

        $this->actingAs($otherHost)->post(route('bookings.expenses.store', $booking), [
            'category' => 'laundry',
            'amount' => 500,
        ])->assertForbidden();
        $this->assertDatabaseCount('booking_expenses', 0);
    }

    public function test_host_service_dashboard_defaults_to_action_needed_and_filters_the_full_request_history(): void
    {
        $host = User::factory()->host()->create(['name' => 'Service Dashboard Host']);
        $this->financialAccount($host);
        $provider = User::factory()->create(['name' => 'Dashboard Service Provider']);
        $pendingApplicant = User::factory()->create(['name' => 'Pending Dashboard Applicant']);
        $condo = $this->unit($host, 'Dashboard Request Condo', 'condo');
        $booking = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => User::factory()->create()->id,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(3),
            'status' => 'confirmed',
            'total_amount' => 6000,
            'party_size' => 2,
        ]);
        ServiceProviderApplication::create([
            'applicant_user_id' => $pendingApplicant->id,
            'host_id' => $host->id,
            'services' => ['cleaning'],
            'status' => 'pending',
            'application_message' => 'I am applying for dashboard cleaning work.',
        ]);
        $requestRows = [
            ['status' => 'assigned', 'category' => 'cleaning', 'notes' => 'Assigned request marker', 'amount' => 500],
            ['status' => 'completed', 'category' => 'laundry', 'notes' => 'Completed request marker', 'amount' => 600],
            ['status' => 'paid', 'category' => 'drinking_water', 'notes' => 'Paid request marker', 'amount' => 200],
            ['status' => 'payment_received', 'category' => 'guest_supplies', 'notes' => 'Closed request marker', 'amount' => 300],
            ['status' => 'cancelled', 'category' => 'other', 'notes' => 'Cancelled request marker', 'amount' => 100],
        ];
        foreach ($requestRows as $row) {
            BookingExpense::create([
                'booking_id' => $booking->id,
                'recorded_by_user_id' => $host->id,
                'provider_user_id' => $provider->id,
                ...$row,
            ]);
        }

        $this->actingAs($host)->get(route('service-work.index'))
            ->assertOk()
            ->assertSee('Host operations report')
            ->assertSee('Requested service overview')
            ->assertSee('Request distribution')
            ->assertSee('Requested-service costs')
            ->assertSee('Needs your action')
            ->assertSee('1 applications · 1 payments')
            ->assertSee('data-service-work-action-count="2"', false)
            ->assertSee('Completed request marker')
            ->assertDontSee('Assigned request marker')
            ->assertDontSee('Paid request marker')
            ->assertDontSee('Closed request marker')
            ->assertDontSee('Cancelled request marker')
            ->assertSee('Attach proof & mark paid', false);

        $this->actingAs($host)->get(route('service-work.index', [
            'host_filter_submitted' => 1,
            'host_statuses' => ['assigned', 'payment_received'],
        ]))
            ->assertOk()
            ->assertSee('Assigned request marker')
            ->assertSee('Closed request marker')
            ->assertDontSee('Completed request marker')
            ->assertDontSee('Paid request marker')
            ->assertDontSee('Cancelled request marker')
            ->assertSee('Provider confirmed payment');
    }

    public function test_provider_can_confirm_a_legacy_paid_job_without_a_payment_proof(): void
    {
        $host = User::factory()->host()->create();
        $provider = User::factory()->create();
        $condo = $this->unit($host, 'Legacy Provider Payment Condo', 'condo');
        $booking = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => User::factory()->create()->id,
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDay(),
            'status' => 'confirmed',
            'total_amount' => 3200,
            'party_size' => 1,
        ]);
        $legacyExpense = BookingExpense::create([
            'booking_id' => $booking->id,
            'recorded_by_user_id' => $host->id,
            'provider_user_id' => $provider->id,
            'category' => 'cleaning',
            'amount' => 700,
            'status' => 'paid',
            'completed_at' => now()->subDay(),
            'paid_at' => now()->subHours(2),
            'payment_proof_path' => null,
        ]);

        $this->actingAs($provider)->patch(route('service-work.payment-received', $legacyExpense))
            ->assertRedirect()
            ->assertSessionHas('status', 'Payment receipt confirmed. This service task is now closed.');

        $legacyExpense->refresh();
        $this->assertSame('payment_received', $legacyExpense->status);
        $this->assertNotNull($legacyExpense->payment_received_at);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $host->id,
            'type' => 'service_payment_received',
        ]);
    }

    private function unit(User $host, string $name, string $category, string $kind = 'unit'): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => $kind,
            'category' => $category,
            'location' => 'Davao City',
            'rules' => 'Follow the service instructions.',
            'capacity' => 6,
            'price' => 1500,
            'pricing_unit' => 'session',
            'is_active' => true,
        ]);
    }
}
