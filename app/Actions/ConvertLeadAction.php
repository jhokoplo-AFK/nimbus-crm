<?php

namespace App\Actions;

use App\Data\LeadConversionResult;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Exceptions\LeadNotConvertibleException;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Converts a lead into an Account, a primary Contact and an Opportunity in a
 * single atomic unit of work. Any failure rolls back every insert, so the CRM
 * never ends up with half-converted leads.
 */
class ConvertLeadAction
{
    /**
     * @param  array{account_name?: string, industry?: string, website?: string, opportunity_name?: string, amount?: float|int|string, close_date?: string}  $overrides
     */
    public function execute(Lead $lead, array $overrides = [], ?User $actor = null): LeadConversionResult
    {
        return DB::transaction(function () use ($lead, $overrides, $actor): LeadConversionResult {
            /** @var Lead $lead */
            $lead = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            if ($lead->isConverted()) {
                throw LeadNotConvertibleException::alreadyConverted();
            }

            if (! $lead->status->isConvertible()) {
                throw LeadNotConvertibleException::disqualified();
            }

            $account = Account::create([
                'name' => $overrides['account_name'] ?? ($lead->company ?: $lead->fullName()),
                'industry' => $overrides['industry'] ?? null,
                'phone' => $lead->phone,
                'website' => $overrides['website'] ?? null,
            ]);

            $contact = Contact::create([
                'account_id' => $account->id,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'title' => $lead->title,
                'email' => $lead->email,
                'phone' => $lead->phone,
                // The converted lead becomes the buying centre's entry point.
                'is_primary' => true,
            ]);

            $opportunity = Opportunity::create([
                'account_id' => $account->id,
                'owner_id' => $actor?->getKey(),
                'primary_contact_id' => $contact->id,
                'name' => $overrides['opportunity_name'] ?? "{$account->name} - New Opportunity",
                'stage' => OpportunityStage::Prospecting,
                'amount' => $overrides['amount'] ?? 0,
                'probability_pct' => OpportunityStage::Prospecting->defaultProbability(),
                'close_date' => $overrides['close_date'] ?? now()->addDays((int) config('crm.default_close_window_days', 30))->toDateString(),
            ]);

            $lead->forceFill([
                'status' => LeadStatus::Converted,
                'converted_at' => now(),
                'converted_account_id' => $account->id,
                'converted_contact_id' => $contact->id,
                'converted_opportunity_id' => $opportunity->id,
            ])->save();

            if ($actor !== null) {
                Activity::create([
                    'subject' => "Lead converted: {$lead->fullName()} → {$account->name}",
                    'type' => ActivityType::Task,
                    'status' => ActivityStatus::Completed,
                    'due_date' => now()->toDateString(),
                    'relatable_type' => $opportunity->getMorphClass(),
                    'relatable_id' => $opportunity->getKey(),
                    'user_id' => $actor->getKey(),
                ]);
            }

            return new LeadConversionResult($account, $contact, $opportunity);
        });
    }
}
