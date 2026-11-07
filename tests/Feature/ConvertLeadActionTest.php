<?php

namespace Tests\Feature;

use App\Actions\ConvertLeadAction;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Exceptions\LeadNotConvertibleException;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertLeadActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_creates_account_contact_and_opportunity_and_locks_the_lead(): void
    {
        $actor = User::factory()->create();
        $lead = Lead::factory()->qualified()->create([
            'company' => 'Acme Industrial',
            'first_name' => 'Jamie',
            'last_name' => 'Rivera',
            'email' => 'jamie@acme.test',
            'phone' => '+1 555 0134',
        ]);

        $result = app(ConvertLeadAction::class)->execute($lead, ['amount' => 25000], $actor);

        $this->assertSame('Acme Industrial', $result->account->name);
        $this->assertSame('jamie@acme.test', $result->contact->email);
        $this->assertTrue($result->contact->is_primary);
        $this->assertSame($result->account->id, $result->opportunity->account_id);
        $this->assertSame($result->contact->id, $result->opportunity->primary_contact_id);
        $this->assertSame(OpportunityStage::Prospecting, $result->opportunity->stage);
        $this->assertEqualsWithDelta(25000.0, (float) $result->opportunity->amount, 0.001);

        $lead->refresh();

        $this->assertSame(LeadStatus::Converted, $lead->status);
        $this->assertNotNull($lead->converted_at);
        $this->assertSame($result->account->id, $lead->converted_account_id);
        $this->assertSame($result->contact->id, $lead->converted_contact_id);
        $this->assertSame($result->opportunity->id, $lead->converted_opportunity_id);
        $this->assertTrue($lead->isLocked());
    }

    public function test_a_converted_lead_cannot_be_converted_twice(): void
    {
        $lead = Lead::factory()->qualified()->create();
        $action = app(ConvertLeadAction::class);

        $action->execute($lead);

        $this->expectException(LeadNotConvertibleException::class);

        $action->execute($lead->refresh());
    }

    public function test_an_unqualified_lead_cannot_be_converted(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::Unqualified]);

        $this->expectException(LeadNotConvertibleException::class);

        app(ConvertLeadAction::class)->execute($lead);
    }

    public function test_a_converted_lead_rejects_updates_through_the_api(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Sales Rep');

        $lead = Lead::factory()->qualified()->create(['first_name' => 'Original']);
        app(ConvertLeadAction::class)->execute($lead);

        $this->actingAs($user)
            ->patch(route('leads.update', $lead), [
                'first_name' => 'Tampered',
                'last_name' => 'Lead',
                'status' => LeadStatus::Contacted->value,
            ])
            ->assertForbidden();

        $this->assertSame('Original', $lead->refresh()->first_name);
    }
}
