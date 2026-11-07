<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_sales_rep_cannot_approve_a_quotation(): void
    {
        $rep = User::factory()->create();
        $rep->assignRole('Sales Rep');

        $quotation = Quotation::factory()->create(['status' => QuotationStatus::InReview]);

        $this->actingAs($rep)
            ->post(route('quotations.approve', $quotation))
            ->assertForbidden();

        $this->assertSame(QuotationStatus::InReview, $quotation->refresh()->status);
    }

    public function test_a_sales_manager_can_approve_a_quotation(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Sales Manager');

        $quotation = Quotation::factory()->create(['status' => QuotationStatus::InReview]);

        $this->actingAs($manager)
            ->post(route('quotations.approve', $quotation))
            ->assertRedirect();

        $quotation->refresh();

        $this->assertSame(QuotationStatus::Approved, $quotation->status);
        $this->assertSame($manager->id, $quotation->approved_by_id);
        $this->assertNotNull($quotation->approved_at);
    }

    public function test_a_draft_quotation_cannot_be_approved(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Sales Manager');

        $quotation = Quotation::factory()->create(['status' => QuotationStatus::Draft]);

        $this->actingAs($manager)
            ->post(route('quotations.approve', $quotation))
            ->assertForbidden();
    }
}
