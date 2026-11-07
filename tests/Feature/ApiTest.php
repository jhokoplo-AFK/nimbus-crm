<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\PricebookEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuoteLineItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesTarget;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'API Admin',
            'email' => 'api-admin@example.test',
            'password' => 'password',
        ]);

        $this->admin->assignRole('Admin');
    }

    public function test_requests_require_a_bearer_token(): void
    {
        $this->getJson('/api/leads')->assertUnauthorized();
        $this->postJson('/api/auth/token', [
            'email' => $this->admin->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_it_issues_and_revokes_api_tokens(): void
    {
        $token = $this->token();

        $this->withToken($token)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', $this->admin->email);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_it_manages_and_converts_leads(): void
    {
        $token = $this->token();

        $leadId = $this->withToken($token)->postJson('/api/leads', [
            'first_name' => 'Nora',
            'last_name' => 'Fitzgerald',
            'company' => 'Arcadia Robotics',
            'email' => 'nora@arcadia.example',
            'lead_source' => 'Web',
            'status' => 'Qualified',
        ])->assertCreated()->json('id');

        $this->withToken($token)
            ->patchJson("/api/leads/{$leadId}", ['title' => 'Head of Automation'])
            ->assertOk()
            ->assertJsonPath('title', 'Head of Automation');

        $this->withToken($token)->getJson('/api/leads')->assertOk()->assertJsonPath('total', 1);

        $this->withToken($token)
            ->postJson("/api/leads/{$leadId}/convert", [
                'account_name' => 'Arcadia Robotics',
                'opportunity_name' => 'Arcadia — CRM rollout',
                'amount' => 25000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.account.name', 'Arcadia Robotics');

        $this->assertDatabaseHas('leads', ['id' => $leadId, 'status' => 'Converted']);

        // Converted leads are locked: the update path rejects further edits.
        $this->withToken($token)
            ->patchJson("/api/leads/{$leadId}", ['title' => 'Changed after conversion'])
            ->assertForbidden();
    }

    public function test_stage_endpoint_runs_the_closed_won_automation(): void
    {
        $token = $this->token();
        $opportunity = Opportunity::factory()->create(['stage' => 'Negotiation', 'amount' => 42000]);

        $this->withToken($token)
            ->patchJson("/api/opportunities/{$opportunity->id}/stage", ['stage' => 'Closed Won'])
            ->assertOk()
            ->assertJsonPath('probability_pct', 100);

        $this->assertDatabaseHas('sales_orders', ['opportunity_id' => $opportunity->id]);
    }

    public function test_every_table_is_exposed_as_a_paginated_resource(): void
    {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create(['brand_id' => $brand->id]);
        $pricebook = Pricebook::factory()->create();
        PricebookEntry::factory()->create(['pricebook_id' => $pricebook->id, 'product_id' => $product->id]);
        $account = Account::factory()->create();
        Contact::factory()->create(['account_id' => $account->id]);
        $opportunity = Opportunity::factory()->create(['account_id' => $account->id]);
        $quotation = Quotation::factory()->create(['opportunity_id' => $opportunity->id, 'pricebook_id' => $pricebook->id]);
        QuoteLineItem::factory()->create(['quotation_id' => $quotation->id, 'product_id' => $product->id]);
        Contract::factory()->create(['account_id' => $account->id, 'opportunity_id' => $opportunity->id]);
        $order = SalesOrder::factory()->create(['account_id' => $account->id, 'opportunity_id' => $opportunity->id]);
        SalesOrderItem::factory()->create(['sales_order_id' => $order->id, 'product_id' => $product->id]);
        Activity::factory()->create(['relatable_id' => $opportunity->id, 'user_id' => $this->admin->id]);
        SalesTarget::factory()->create();
        Lead::factory()->create();

        $token = $this->token();

        $endpoints = [
            'leads',
            'accounts',
            'contacts',
            'opportunities',
            'brands',
            'products',
            'pricebooks',
            'pricebook-entries',
            'quotations',
            'quote-line-items',
            'contracts',
            'sales-orders',
            'sales-order-items',
            'activities',
            'sales-targets',
            'users',
        ];

        foreach ($endpoints as $endpoint) {
            $this->withToken($token)
                ->getJson("/api/{$endpoint}")
                ->assertOk()
                ->assertJsonStructure(['data', 'total']);
        }
    }

    public function test_it_writes_catalog_targets_orders_activities_and_quotations(): void
    {
        $token = $this->token();
        $account = Account::factory()->create(['name' => 'Globex']);
        $opportunity = Opportunity::factory()->create(['account_id' => $account->id, 'stage' => 'Proposal']);

        $brandId = $this->withToken($token)
            ->postJson('/api/brands', ['name' => 'Acme Cloud'])
            ->assertCreated()
            ->json('id');

        $productId = $this->withToken($token)
            ->postJson('/api/products', [
                'brand_id' => $brandId,
                'sku' => 'ACME-001',
                'name' => 'Acme Suite',
            ])
            ->assertCreated()
            ->json('id');

        $this->withToken($token)
            ->postJson('/api/contacts', [
                'account_id' => $account->id,
                'first_name' => 'Dana',
                'last_name' => 'Scully',
                'is_primary' => true,
            ])
            ->assertCreated();

        $pricebookId = $this->withToken($token)
            ->postJson('/api/pricebooks', ['name' => 'FY26 Standard', 'is_standard' => true])
            ->assertCreated()
            ->json('id');

        $this->withToken($token)
            ->postJson('/api/pricebook-entries', [
                'pricebook_id' => $pricebookId,
                'product_id' => $productId,
                'list_price' => 60000,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/sales-targets', [
                'year' => now()->year,
                'brand_id' => $brandId,
                'target_amount' => 250000,
            ])
            ->assertCreated();

        $orderId = $this->withToken($token)
            ->postJson('/api/sales-orders', [
                'account_id' => $account->id,
                'opportunity_id' => $opportunity->id,
                'order_date' => now()->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1110,
            ])
            ->assertCreated()
            ->json('id');

        $this->withToken($token)
            ->patchJson("/api/sales-orders/{$orderId}/status", ['status' => 'Confirmed'])
            ->assertOk()
            ->assertJsonPath('status', 'Confirmed');

        $this->withToken($token)
            ->postJson('/api/sales-order-items', [
                'sales_order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => 2,
                'unit_price' => 500,
            ])
            ->assertCreated()
            ->assertJsonPath('total_price', '1000.00');

        $activityId = $this->withToken($token)
            ->postJson('/api/activities', [
                'relatable_type' => 'account',
                'relatable_id' => $account->id,
                'subject' => 'Discovery call',
                'type' => 'Call',
            ])
            ->assertCreated()
            ->json('id');

        $this->withToken($token)
            ->patchJson("/api/activities/{$activityId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'Completed');

        $this->withToken($token)
            ->postJson('/api/contracts', [
                'account_id' => $account->id,
                'opportunity_id' => $opportunity->id,
                'contract_number' => 'CT-TEST-1',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
            ])
            ->assertCreated();

        // A 60k line pushes the quotation over the approval threshold, so it
        // starts in review and can then be approved and sent.
        $quoteId = $this->withToken($token)
            ->postJson('/api/quotations', [
                'opportunity_id' => $opportunity->id,
                'pricebook_id' => $pricebookId,
                'discount_type' => 'none',
                'discount_value' => 0,
                'tax_rate' => 11,
                'lines' => [
                    ['product_id' => $productId, 'quantity' => 1, 'discount_pct' => 0],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'In Review')
            ->json('id');

        $this->withToken($token)
            ->postJson("/api/quotations/{$quoteId}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'Approved');

        $this->withToken($token)
            ->postJson("/api/quotations/{$quoteId}/send")
            ->assertOk()
            ->assertJsonPath('status', 'Sent');

        $this->withToken($token)
            ->postJson("/api/quotations/{$quoteId}/revise")
            ->assertCreated()
            ->assertJsonPath('status', 'In Review');
    }

    private function token(): string
    {
        return $this->postJson('/api/auth/token', [
            'email' => $this->admin->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertCreated()->json('token');
    }
}
