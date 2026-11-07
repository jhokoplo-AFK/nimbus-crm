<?php

namespace App\Enums;

/**
 * Credit sign-off travels with the quotation so the ERP connector can refuse
 * an order until the exposure and any overdue balance have been approved.
 */
enum CreditStatus: string
{
    case NotRequired = 'Not Required';
    case Pending = 'Pending';
    case Approved = 'Approved';
    case Rejected = 'Rejected';

    public function label(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::NotRequired => 'slate',
            self::Pending => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
        };
    }

    public function isBlocking(): bool
    {
        return in_array($this, [self::Pending, self::Rejected], true);
    }
}
