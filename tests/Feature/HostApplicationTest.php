<?php

namespace Tests\Feature;

use App\Models\HostApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HostApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_personal_information_comes_from_the_profile_and_is_not_duplicated(): void
    {
        $client = User::factory()->create(['name' => 'Original Profile Name']);

        $this->actingAs($client)
            ->get(route('host-applications.show'))
            ->assertOk()
            ->assertSee('Submit host application');

        $this->actingAs($client)
            ->post(route('host-applications.store'), $this->validApplicationData())
            ->assertRedirect(route('host-applications.show'));

        $application = $client->hostApplication()->firstOrFail();

        $this->assertSame(HostApplication::STATUS_SUBMITTED, $application->status);
        $this->assertFalse(Schema::hasColumn('host_applications', 'name'));
        $this->assertFalse(Schema::hasColumn('host_applications', 'email'));
        $this->assertFalse(Schema::hasColumn('host_applications', 'phone'));

        $client->update(['name' => 'Updated Profile Name']);

        $this->actingAs($client)
            ->get(route('host-applications.show'))
            ->assertOk()
            ->assertSee('Updated Profile Name');
    }

    public function test_complete_profile_is_required_before_applying(): void
    {
        $client = User::factory()->incompleteProfile()->create();

        $this->actingAs($client)
            ->post(route('host-applications.store'), $this->validApplicationData())
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('profile');

        $this->assertDatabaseCount('host_applications', 0);
    }

    public function test_business_application_requires_and_privately_stores_registration_document(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $data = [
            ...$this->validApplicationData(),
            'account_type' => 'business',
            'business_name' => 'Davao Rentals Inc.',
            'business_registration_number' => 'SEC-123456',
            'business_document' => UploadedFile::fake()->create('registration.pdf', 200, 'application/pdf'),
        ];

        $this->actingAs($client)->post(route('host-applications.store'), $data)->assertRedirect();

        $application = $client->hostApplication()->firstOrFail();
        $this->assertSame('SEC-123456', $application->business_registration_number);
        Storage::disk('local')->assertExists($application->business_document_path);
    }

    public function test_submitted_application_cannot_be_changed_while_in_review_queue(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client)->post(route('host-applications.store'), $this->validApplicationData());

        $this->actingAs($client)
            ->post(route('host-applications.store'), $this->validApplicationData(['motivation' => 'A completely different hosting plan that is long enough.']))
            ->assertSessionHasErrors('application');

        $this->assertSame(1, $client->hostApplication->histories()->count());
    }

    public function test_only_admin_can_open_the_applicant_table_and_review_page(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client)->post(route('host-applications.store'), $this->validApplicationData());
        $application = $client->hostApplication;

        $this->actingAs($client)->get(route('admin.host-applications.index'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.host-applications.show', $application))->assertForbidden();

        $admin = User::factory()->host()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.host-applications.index'))
            ->assertOk()
            ->assertSee($client->name)
            ->assertSee('Submitted');
        $this->actingAs($admin)
            ->get(route('admin.host-applications.show', $application))
            ->assertOk()
            ->assertSee('Approve host')
            ->assertSee($client->name);
    }

    public function test_admin_can_request_changes_and_client_can_resubmit(): void
    {
        $client = User::factory()->create();
        $admin = User::factory()->host()->create(['is_admin' => true]);
        $this->actingAs($client)->post(route('host-applications.store'), $this->validApplicationData());
        $application = $client->hostApplication;

        $this->actingAs($admin)->patch(route('admin.host-applications.review', $application), [
            'status' => HostApplication::STATUS_NEEDS_CHANGES,
            'review_note' => 'Please clarify what you plan to list.',
        ])->assertRedirect(route('admin.host-applications.show', $application));

        $this->assertDatabaseHas('host_applications', [
            'id' => $application->id,
            'status' => HostApplication::STATUS_NEEDS_CHANGES,
            'reviewed_by' => $admin->id,
        ]);

        $this->actingAs($client)->post(route('host-applications.store'), $this->validApplicationData([
            'motivation' => 'I plan to provide a maintained condo and a reliable transport service for local guests.',
        ]))->assertRedirect(route('host-applications.show'));

        $application->refresh();
        $this->assertSame(HostApplication::STATUS_SUBMITTED, $application->status);
        $this->assertNull($application->reviewed_by);
        $this->assertCount(3, $application->histories);
    }

    public function test_approval_promotes_client_to_host_and_unlocks_listing_routes(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $admin = User::factory()->host()->create(['is_admin' => true]);
        $this->actingAs($client)->post(route('host-applications.store'), $this->validApplicationData());
        $application = $client->hostApplication;

        $this->actingAs($admin)->patch(route('admin.host-applications.review', $application), [
            'status' => HostApplication::STATUS_APPROVED,
            'review_note' => 'Identity and payout ownership reviewed.',
        ])->assertRedirect(route('admin.host-applications.show', $application));

        $this->assertSame('host', $client->refresh()->role);
        $this->assertSame(HostApplication::STATUS_APPROVED, $application->refresh()->status);
        $this->actingAs($client)->get(route('units.index'))->assertOk();
    }

    public function test_rejection_keeps_the_applicant_as_a_client(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $admin = User::factory()->host()->create(['is_admin' => true]);
        $this->actingAs($client)->post(route('host-applications.store'), $this->validApplicationData());

        $this->actingAs($admin)->patch(route('admin.host-applications.review', $client->hostApplication), [
            'status' => HostApplication::STATUS_REJECTED,
            'review_note' => 'The submitted payout ownership could not be verified.',
        ])->assertRedirect();

        $this->assertSame('client', $client->refresh()->role);
        $this->actingAs($client)->get(route('units.index'))->assertForbidden();
    }

    public function test_private_business_document_is_limited_to_applicant_and_admin(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $admin = User::factory()->host()->create(['is_admin' => true]);
        $this->actingAs($client)->post(route('host-applications.store'), [
            ...$this->validApplicationData(),
            'account_type' => 'business',
            'business_name' => 'Private Rentals',
            'business_registration_number' => 'PRIVATE-100',
            'business_document' => UploadedFile::fake()->create('registration.pdf', 50, 'application/pdf'),
        ]);
        $application = $client->hostApplication;

        $this->actingAs($client)->get(route('host-applications.business-document', $application))->assertOk();
        $this->actingAs($admin)->get(route('host-applications.business-document', $application))->assertOk();
        $this->actingAs($otherClient)->get(route('host-applications.business-document', $application))->assertForbidden();
    }

    private function validApplicationData(array $overrides = []): array
    {
        return [
            'account_type' => 'individual',
            'hosting_experience' => 'none',
            'motivation' => 'I want to offer dependable local rentals and provide responsive service to every client.',
            'payout_method' => 'e_wallet',
            'payout_provider' => 'GCash',
            'payout_account_name' => 'Test Account Holder',
            'payout_account_number' => '09171234567',
            'authority_confirmed' => '1',
            'safety_confirmed' => '1',
            'terms_accepted' => '1',
            'privacy_consented' => '1',
            ...$overrides,
        ];
    }
}
