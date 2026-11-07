<?php

namespace Database\Seeders;

use App\Actions\ApproveQuotationAction;
use App\Actions\CloseWonOpportunityAction;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\ContractStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\SalesOrderStatus;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->user('Avery Chen', 'admin@nimbus.test', 'Admin');
        $this->user('Dana Iverson', 'director@nimbus.test', 'Director');
        $gm = $this->user('Grace Lim', 'gm@nimbus.test', 'General Manager');
        $this->user('Rafael Duarte', 'regional@nimbus.test', 'Regional Manager');
        $manager = $this->user('Jordan Blake', 'manager@nimbus.test', 'Manager');
        $rep = $this->user('Riley Novak', 'rep@nimbus.test', 'Sales');
        $sam = $this->user('Sam Okafor', 'sam@nimbus.test', 'Sales');
        $owners = [$rep, $manager, $sam];

        [$products, $brands] = $this->catalog();
        [$accounts, $contacts] = $this->accounts();
        $this->leads();
        $opportunities = $this->opportunities($accounts, $contacts, $owners);

        $this->quotations($opportunities, $products, $rep, $manager, $gm);
        $this->wonDeals($opportunities, $manager);
        $this->revenueHistory($accounts, $opportunities, $products, $owners);
        $this->targets($accounts, $products, $brands, $owners);
        $this->activities($opportunities, $admin, $rep, $manager);
    }

    private function user(string $name, string $email, string $role): User
    {
        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password'],
        );

        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * @return array{0: Collection<string, Product>, 1: Collection<string, Brand>}
     */
    private function catalog(): array
    {
        $standard = Pricebook::create(['name' => 'Standard Price Book', 'is_standard' => true, 'is_active' => true]);
        $partner = Pricebook::create(['name' => 'Enterprise Partner Price Book', 'is_standard' => false, 'is_active' => true]);
        $legacy = Pricebook::create(['name' => 'Legacy FY24 Price Book', 'is_standard' => false, 'is_active' => false]);

        /** @var Collection<string, Brand> $brands */
        $brands = collect([
            'Nimbus Platform' => Brand::create(['name' => 'Nimbus Platform']),
            'Nimbus Add-ons' => Brand::create(['name' => 'Nimbus Add-ons']),
            'Nimbus Services' => Brand::create(['name' => 'Nimbus Services']),
        ]);

        $rows = [
            ['CRM-CORE-10', 'Nimbus CRM Core — 10 seats', 'Core sales automation licence for small teams.', 6000, 'Nimbus Platform'],
            ['CRM-CORE-25', 'Nimbus CRM Core — 25 seats', 'Core sales automation licence for growing teams.', 13500, 'Nimbus Platform'],
            ['CRM-ENT-50', 'Nimbus CRM Enterprise — 50 seats', 'Enterprise licence with sandbox and audit trail.', 32000, 'Nimbus Platform'],
            ['ADD-ANALYTICS', 'Revenue Analytics Add-on', 'Forecast, pipeline and win-rate analytics.', 4800, 'Nimbus Add-ons'],
            ['ADD-INTEGRATION', 'ERP Integration Pack', 'Bi-directional SAP Business One connector.', 7500, 'Nimbus Add-ons'],
            ['SVC-IMPL', 'Implementation Services (per week)', 'Solution design, migration and go-live support.', 3500, 'Nimbus Services'],
            ['SVC-TRAINING', 'Onsite Sales Training (per day)', 'Enablement workshop for the sales organisation.', 1800, 'Nimbus Services'],
            ['SUP-PREMIUM', 'Premium Support — Annual', '24/7 support with a 1 hour response SLA.', 9800, 'Nimbus Services'],
        ];

        /** @var Collection<string, Product> $products */
        $products = collect();

        foreach ($rows as [$sku, $name, $description, $price, $brandName]) {
            $product = Product::create([
                'brand_id' => $brands[$brandName]->id,
                'sku' => $sku,
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ]);

            $products->put($sku, $product);

            $standard->entries()->create(['product_id' => $product->id, 'list_price' => $price]);

            // The partner book excludes the ERP pack on purpose: quoting it
            // from this book must be rejected by the engine.
            if ($sku !== 'ADD-INTEGRATION') {
                $partner->entries()->create([
                    'product_id' => $product->id,
                    'list_price' => round($price * 0.9, 2),
                ]);
            }
        }

        $legacy->entries()->createMany([
            ['product_id' => $products['CRM-CORE-10']->id, 'list_price' => 5200],
            ['product_id' => $products['SUP-PREMIUM']->id, 'list_price' => 8400],
        ]);

        return [$products, $brands];
    }

    /**
     * @return array{0: array<string, Account>, 1: array<string, Contact>}
     */
    private function accounts(): array
    {
        $rows = [
            ['Meridian Manufacturing', 'Manufacturing', 42000000, 'meridian.example', 'Dana Whitfield', 'VP Operations', 'dana@meridian.example', '+1 415 555 0142'],
            ['Northwind Logistics', 'Logistics', 18500000, 'northwind.example', 'Marcus Lee', 'Head of Commercial', 'marcus@northwind.example', '+1 206 555 0119'],
            ['Bluepeak Health', 'Healthcare', 76000000, 'bluepeak.example', 'Priya Raman', 'CIO', 'priya@bluepeak.example', '+1 617 555 0188'],
            ['Cobalt Financial', 'Financial Services', 132000000, 'cobalt.example', 'Elena Duarte', 'COO', 'elena@cobalt.example', '+1 212 555 0173'],
            ['Helios Energy', 'Energy', 54000000, 'helios.example', 'Tom Becker', 'Director of Sales', 'tom@helios.example', '+1 713 555 0155'],
            ['Kestrel Retail Group', 'Retail', 9700000, 'kestrel.example', 'Aisha Bello', 'Category Director', 'aisha@kestrel.example', '+44 20 7946 0122'],
        ];

        $accounts = [];
        $contacts = [];

        foreach ($rows as [$name, $industry, $revenue, $domain, $contactName, $title, $email, $phone]) {
            // Two customers carry a credit position so the approval demo has
            // something to show: Bluepeak is close to its limit, Helios is over
            // it *and* has an overdue invoice.
            $credit = match ($name) {
                'Bluepeak Health' => ['credit_limit' => 60000, 'balance_due' => 12500, 'overdue_balance' => 0],
                'Helios Energy' => ['credit_limit' => 40000, 'balance_due' => 8500, 'overdue_balance' => 4000],
                default => ['credit_limit' => null, 'balance_due' => 0, 'overdue_balance' => 0],
            };

            $account = Account::create([
                'name' => $name,
                'industry' => $industry,
                'annual_revenue' => $revenue,
                ...$credit,
                'phone' => $phone,
                'website' => "https://{$domain}",
                'billing_address' => "100 {$name} Way, Suite 400",
                'shipping_address' => "100 {$name} Way, Dock B",
            ]);

            [$firstName, $lastName] = explode(' ', $contactName, 2);

            $contact = Contact::create([
                'account_id' => $account->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'title' => $title,
                'email' => $email,
                'phone' => $phone,
                'is_primary' => true,
            ]);

            $accounts[$name] = $account;
            $contacts[$name] = $contact;
        }

        return [$accounts, $contacts];
    }

    private function leads(): void
    {
        $rows = [
            ['Nora', 'Fitzgerald', 'Arcadia Robotics', 'Head of Automation', 'nora@arcadia.example', 'Web', LeadStatus::New],
            ['Ibrahim', 'Sayed', 'Delta Freight', 'Operations Manager', 'ibrahim@deltafreight.example', 'Event', LeadStatus::Contacted],
            ['Hannah', 'Kim', 'Solstice Media', 'Revenue Operations Lead', 'hannah@solstice.example', 'Referral', LeadStatus::Qualified],
            ['Peter', 'Vogel', 'Rhineland Chemie', 'Procurement Director', 'peter@rhineland.example', 'Outbound', LeadStatus::Qualified],
            ['Lucia', 'Moretti', 'Adriatic Shipping', 'VP Sales', 'lucia@adriatic.example', 'Partner', LeadStatus::Contacted],
            ['Owen', 'Brennan', 'Granite Insurance', 'CTO', 'owen@granite.example', 'Web', LeadStatus::New],
            ['Mei', 'Tanaka', 'Kansai Electronics', 'GM Sales Operations', 'mei@kansai.example', 'Event', LeadStatus::Unqualified],
            ['Carlos', 'Mendes', 'Pampa Agritech', 'Commercial Director', 'carlos@pampa.example', 'Outbound', LeadStatus::New],
            ['Fatima', 'Al-Rashid', 'Gulf Bridge Capital', 'Head of Platforms', 'fatima@gulfbridge.example', 'Referral', LeadStatus::Contacted],
            ['Jonas', 'Berg', 'Nordic Cold Chain', 'COO', 'jonas@nordiccold.example', 'Partner', LeadStatus::Qualified],
        ];

        foreach ($rows as [$first, $last, $company, $title, $email, $source, $status]) {
            Lead::create([
                'first_name' => $first,
                'last_name' => $last,
                'company' => $company,
                'title' => $title,
                'email' => $email,
                'phone' => '+1 555 0100',
                'lead_source' => $source,
                'status' => $status,
                'notes' => "Inbound enquiry about replacing the incumbent CRM at {$company}.",
            ]);
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     * @param  array<string, Contact>  $contacts
     * @param  array<int, User>  $owners
     * @return array<int, Opportunity>
     */
    private function opportunities(array $accounts, array $contacts, array $owners): array
    {
        $rows = [
            ['Meridian Manufacturing', 'Meridian — Global CRM Rollout', OpportunityStage::Negotiation, 148000, 75, 18],
            ['Northwind Logistics', 'Northwind — Fleet Sales Automation', OpportunityStage::Proposal, 86000, 50, 30],
            ['Bluepeak Health', 'Bluepeak — Patient Referral Pipeline', OpportunityStage::Qualification, 54000, 25, 45],
            ['Cobalt Financial', 'Cobalt — Enterprise Licence Renewal', OpportunityStage::Proposal, 132000, 50, 25],
            ['Helios Energy', 'Helios — Field Sales Enablement', OpportunityStage::Prospecting, 39000, 10, 60],
            ['Kestrel Retail Group', 'Kestrel — Store Ops CRM', OpportunityStage::Negotiation, 72000, 75, 14],
            ['Meridian Manufacturing', 'Meridian — Analytics Add-on', OpportunityStage::Qualification, 18500, 25, 40],
            ['Northwind Logistics', 'Northwind — ERP Integration', OpportunityStage::Prospecting, 24500, 10, 75],
        ];

        $opportunities = [];

        foreach ($rows as $index => [$accountName, $name, $stage, $amount, $probability, $days]) {
            $opportunities[] = Opportunity::create([
                'account_id' => $accounts[$accountName]->id,
                'owner_id' => $owners[$index % count($owners)]->id,
                'primary_contact_id' => $contacts[$accountName]->id,
                'name' => $name,
                'stage' => $stage,
                'amount' => $amount,
                'probability_pct' => $probability,
                'close_date' => now()->addDays($days)->toDateString(),
            ]);
        }

        return $opportunities;
    }

    /**
     * @param  array<int, Opportunity>  $opportunities
     * @param  Collection<string, Product>  $products
     */
    private function quotations(array $opportunities, Collection $products, User $rep, User $manager, User $gm): void
    {
        $service = app(QuotationService::class);
        $standard = Pricebook::where('is_standard', true)->firstOrFail();
        $partner = Pricebook::where('name', 'Enterprise Partner Price Book')->firstOrFail();

        // Clean quote, stays in Draft.
        $service->create($opportunities[1], $standard, [
            'lines' => [
                ['product_id' => $products['CRM-CORE-25']->id, 'quantity' => 2, 'discount_pct' => 0],
                ['product_id' => $products['SVC-IMPL']->id, 'quantity' => 3, 'discount_pct' => 0],
            ],
            'discount_type' => 'none',
            'discount_value' => 0,
            'tax_rate' => 11,
            'expiration_date' => now()->addDays(21)->toDateString(),
        ], $rep);

        // 18% discount is outside the Manager layer (10%), so it escalates to
        // the General Manager layer (20%) before it can be approved.
        $reviewed = $service->create($opportunities[0], $partner, [
            'lines' => [
                ['product_id' => $products['CRM-ENT-50']->id, 'quantity' => 3, 'discount_pct' => 5],
                ['product_id' => $products['ADD-ANALYTICS']->id, 'quantity' => 2, 'discount_pct' => 0],
            ],
            'discount_type' => 'percent',
            'discount_value' => 18,
            'tax_rate' => 11,
            'expiration_date' => now()->addDays(14)->toDateString(),
        ], $rep);

        app(ApproveQuotationAction::class)->execute($reviewed, $gm);
        $service->markSent($reviewed->refresh(), $manager);

        // High-value clean quote: over the amount threshold, also in review.
        $highValue = $service->create($opportunities[3], $standard, [
            'lines' => [
                ['product_id' => $products['CRM-ENT-50']->id, 'quantity' => 2, 'discount_pct' => 0],
                ['product_id' => $products['SUP-PREMIUM']->id, 'quantity' => 1, 'discount_pct' => 0],
            ],
            'discount_type' => 'none',
            'discount_value' => 0,
            'tax_rate' => 11,
        ], $rep);

        // A revision of the high-value quote, showing the versioning trail.
        $service->revise($highValue, $rep);
    }

    /**
     * @param  array<int, Opportunity>  $opportunities
     */
    private function wonDeals(array $opportunities, User $manager): void
    {
        $meridian = $opportunities[5];

        Contract::create([
            'account_id' => $meridian->account_id,
            'opportunity_id' => $meridian->id,
            'contract_number' => 'CT-'.now()->format('Y').'-00001',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addYear()->startOfMonth()->subDay()->toDateString(),
            'status' => ContractStatus::Active,
            'terms_and_conditions' => 'Standard Nimbus Master Services Agreement. 12 months, billed annually in advance.',
        ]);

        // Closing these two runs the Closed-Won automation, producing Draft
        // sales orders ready for the ERP connector.
        app(CloseWonOpportunityAction::class)->execute($meridian);
        app(CloseWonOpportunityAction::class)->execute($opportunities[6]);

        Activity::create([
            'subject' => 'Contract signature received',
            'type' => ActivityType::Task,
            'status' => ActivityStatus::Completed,
            'due_date' => now()->toDateString(),
            'relatable_type' => $meridian->getMorphClass(),
            'relatable_id' => $meridian->id,
            'user_id' => $manager->id,
        ]);
    }

    /**
     * Books revenue month by month for the current year so the target vs
     * achievement views have a realistic trend to report on.
     *
     * @param  array<string, Account>  $accounts
     * @param  array<int, Opportunity>  $opportunities
     * @param  Collection<string, Product>  $products
     * @param  array<int, User>  $owners
     */
    private function revenueHistory(array $accounts, array $opportunities, Collection $products, array $owners): void
    {
        $skus = $products->keys()->values()->all();
        $accountNames = array_keys($accounts);
        $sequence = 1;

        foreach (range(1, max(1, (int) now()->month)) as $month) {
            foreach ([0, 1] as $slot) {
                $account = $accounts[$accountNames[($month + $slot * 3) % count($accountNames)]];
                $product = $products[$skus[($month * 2 + $slot) % count($skus)]];
                $owner = $owners[($month + $slot) % count($owners)];
                $opportunity = collect($opportunities)->firstWhere('account_id', $account->id);

                $quantity = 1 + (($month + $slot) % 3);
                $unitPrice = (float) $product->pricebookEntries()
                    ->whereHas('pricebook', fn ($query) => $query->where('is_standard', true))
                    ->value('list_price');

                $lineTotal = round($quantity * $unitPrice, 2);

                /** @var SalesOrder $order */
                $order = SalesOrder::create([
                    'account_id' => $account->id,
                    'opportunity_id' => $opportunity->id,
                    'user_id' => $owner->id,
                    'order_number' => sprintf('SO-%s-H%04d', now()->format('Y'), $sequence++),
                    'order_date' => Carbon::create(now()->year, $month, 5 + $slot * 8)->toDateString(),
                    'subtotal' => $lineTotal,
                    'total_amount' => round($lineTotal * 1.11, 2),
                    'status' => SalesOrderStatus::Confirmed,
                ]);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ]);
            }
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     * @param  Collection<string, Product>  $products
     * @param  Collection<string, Brand>  $brands
     * @param  array<int, User>  $owners
     */
    private function targets(array $accounts, Collection $products, Collection $brands, array $owners): void
    {
        $year = now()->year;

        // Company-wide plan: annual, quarterly and monthly.
        SalesTarget::create(['year' => $year, 'target_amount' => 900000]);

        foreach (range(1, 4) as $quarter) {
            SalesTarget::create(['year' => $year, 'quarter' => $quarter, 'target_amount' => 225000]);
        }

        foreach (range(1, 12) as $month) {
            SalesTarget::create(['year' => $year, 'month' => $month, 'target_amount' => 75000]);
        }

        // Per-rep quotas.
        foreach ([[$owners[0], 400000], [$owners[1], 300000], [$owners[2], 200000]] as [$user, $amount]) {
            SalesTarget::create(['user_id' => $user->id, 'year' => $year, 'target_amount' => $amount]);
        }

        // Dimension targets.
        $brandTargets = ['Nimbus Platform' => 500000, 'Nimbus Add-ons' => 180000, 'Nimbus Services' => 220000];

        foreach ($brandTargets as $name => $amount) {
            SalesTarget::create(['brand_id' => $brands[$name]->id, 'year' => $year, 'target_amount' => $amount]);
        }

        foreach ($accounts as $account) {
            SalesTarget::create(['account_id' => $account->id, 'year' => $year, 'target_amount' => 150000]);
        }

        foreach ($products as $product) {
            SalesTarget::create(['product_id' => $product->id, 'year' => $year, 'target_amount' => 110000]);
        }
    }

    /**
     * @param  array<int, Opportunity>  $opportunities
     */
    private function activities(array $opportunities, User $admin, User $rep, User $manager): void
    {
        $rows = [
            [0, 'Discovery call with procurement', ActivityType::Call, ActivityStatus::Completed, -6, $rep],
            [0, 'Send revised commercial terms', ActivityType::Email, ActivityStatus::Open, 2, $rep],
            [0, 'Executive alignment meeting', ActivityType::Meeting, ActivityStatus::Open, 5, $manager],
            [1, 'Technical scoping workshop', ActivityType::Meeting, ActivityStatus::Completed, -3, $rep],
            [1, 'Follow up on security questionnaire', ActivityType::Task, ActivityStatus::Open, 1, $rep],
            [3, 'Renewal kickoff call', ActivityType::Call, ActivityStatus::Open, -1, $manager],
            [3, 'Share benchmark deck', ActivityType::Email, ActivityStatus::Completed, -8, $admin],
            [5, 'Post-signature handover to delivery', ActivityType::Task, ActivityStatus::Open, 4, $admin],
            [4, 'Qualify budget owner', ActivityType::Call, ActivityStatus::Open, 3, $rep],
        ];

        foreach ($rows as [$index, $subject, $type, $status, $offset, $user]) {
            $opportunity = $opportunities[$index];

            Activity::create([
                'subject' => $subject,
                'type' => $type,
                'status' => $status,
                'due_date' => now()->addDays($offset)->toDateString(),
                'relatable_type' => $opportunity->getMorphClass(),
                'relatable_id' => $opportunity->id,
                'user_id' => $user->id,
            ]);
        }
    }
}
