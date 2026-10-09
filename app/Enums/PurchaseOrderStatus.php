<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidStatusTransitionException;

enum PurchaseOrderStatus: string
{
    case Draft = 'DRAFT';
    case Approved = 'APPROVED';
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';

    //return list<string>

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }


    public static function transitionTargets(): array
    {
        return [self::Approved, self::Received, self::Cancelled];
    }


    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Cancelled],
            self::Approved => [self::Received, self::Cancelled],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }


    public function ensureCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw InvalidStatusTransitionException::between($this, $target);
        }
    }


    public function isTerminal(): bool
    {
        return $this === self::Received || $this === self::Cancelled;
    }


    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Approved => 'primary',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }
}
