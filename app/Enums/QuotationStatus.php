<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'Draft';
    case InReview = 'In Review';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Sent = 'Sent';

    public function label(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::InReview => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
            self::Sent => 'sky',
        };
    }

    /**
     * Line items may only change while a quote has not been signed off.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::InReview], true);
    }

    /**
     * @return array<int, array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'tone' => $case->tone(),
        ], self::cases());
    }
}
