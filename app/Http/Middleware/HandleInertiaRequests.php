<?php

namespace App\Http\Middleware;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\ContractStatus;
use App\Enums\DiscountType;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root Blade template rendered on the first page load.
     */
    protected $rootView = 'app';

    /**
     * Asset versioning so Inertia reloads after a deploy.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every page: the authenticated identity with its role,
     * the enum dictionaries used to render badges and selects, and the
     * commercial policy the quotation builder previews.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'appName' => config('app.name', 'Nimbus CRM'),

            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->primaryRole(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ],
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'enums' => [
                'leadStatuses' => LeadStatus::options(),
                'opportunityStages' => OpportunityStage::options(),
                'quotationStatuses' => QuotationStatus::options(),
                'contractStatuses' => ContractStatus::options(),
                'salesOrderStatuses' => SalesOrderStatus::options(),
                'discountTypes' => DiscountType::options(),
                'activityTypes' => ActivityType::options(),
                'activityStatuses' => ActivityStatus::options(),
            ],

            'settings' => [
                'currency' => config('crm.currency', 'USD'),
                'taxRate' => (float) config('crm.tax_rate', 11),
                'approval' => [
                    'discountThresholdPct' => (float) config('crm.approval.discount_threshold_pct', 15),
                    'amountThreshold' => (float) config('crm.approval.amount_threshold', 50000),
                ],
            ],
        ];
    }
}
