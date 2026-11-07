<?php

namespace App\Enums;

enum DiscountType: string
{
    case None = 'none';
    case Percent = 'percent';
    case Flat = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No discount',
            self::Percent => 'Percentage',
            self::Flat => 'Fixed amount',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
