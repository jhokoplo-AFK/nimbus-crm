<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'Draft';
    case Confirmed = 'Confirmed';
    case SentToErp = 'Sent_To_ERP';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
            self::SentToErp => 'Sent to ERP',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Confirmed => 'sky',
            self::SentToErp => 'emerald',
        };
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
