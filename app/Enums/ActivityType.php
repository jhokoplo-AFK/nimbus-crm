<?php

namespace App\Enums;

enum ActivityType: string
{
    case Call = 'Call';
    case Email = 'Email';
    case Meeting = 'Meeting';
    case Task = 'Task';

    public function label(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Call => 'sky',
            self::Email => 'violet',
            self::Meeting => 'indigo',
            self::Task => 'amber',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'phone',
            self::Email => 'mail',
            self::Meeting => 'users',
            self::Task => 'check-square',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, tone: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'tone' => $case->tone(),
            'icon' => $case->icon(),
        ], self::cases());
    }
}
