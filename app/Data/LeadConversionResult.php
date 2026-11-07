<?php

namespace App\Data;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Opportunity;

final readonly class LeadConversionResult
{
    public function __construct(
        public Account $account,
        public Contact $contact,
        public Opportunity $opportunity,
    ) {}
}
