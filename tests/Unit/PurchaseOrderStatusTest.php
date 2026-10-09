<?php

namespace Tests\Unit;

use App\Enums\PurchaseOrderStatus as Status;
use App\Exceptions\InvalidStatusTransitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PurchaseOrderStatusTest extends TestCase
{
    #[DataProvider('transitionMatrix')]
    public function test_lifecycle_transition_matrix(Status $from, Status $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public static function transitionMatrix(): array
    {
        $allowed = [
            'DRAFT->APPROVED', 'DRAFT->CANCELLED',
            'APPROVED->RECEIVED', 'APPROVED->CANCELLED',
        ];

        $cases = [];

        foreach (Status::cases() as $from) {
            foreach (Status::cases() as $to) {
                $key = "{$from->value}->{$to->value}";
                $cases[$key] = [$from, $to, in_array($key, $allowed, true)];
            }
        }

        return $cases;
    }

    public function test_invalid_transition_throws_with_descriptive_message(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);
        $this->expectExceptionMessage('Purchase order cannot transition from RECEIVED to APPROVED.');

        Status::Received->ensureCanTransitionTo(Status::Approved);
    }

    public function test_terminal_and_editable_states(): void
    {
        $this->assertTrue(Status::Received->isTerminal());
        $this->assertTrue(Status::Cancelled->isTerminal());
        $this->assertFalse(Status::Draft->isTerminal());
        $this->assertFalse(Status::Approved->isTerminal());

        $this->assertTrue(Status::Draft->isEditable());
        $this->assertFalse(Status::Approved->isEditable());
    }

    public function test_badge_classes(): void
    {
        $this->assertSame('secondary', Status::Draft->badgeClass());
        $this->assertSame('primary', Status::Approved->badgeClass());
        $this->assertSame('success', Status::Received->badgeClass());
        $this->assertSame('danger', Status::Cancelled->badgeClass());
    }
}
