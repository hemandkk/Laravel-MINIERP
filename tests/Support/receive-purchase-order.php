<?php

/**
 * Worker used by ReceiveConcurrencyTest: boots the application in a separate
 * PHP process (its own MySQL connection) and attempts to receive one purchase
 * order, printing the outcome as JSON.
 *
 * Usage: php tests/Support/receive-purchase-order.php {purchaseOrderId}
 */

declare(strict_types=1);

use App\Actions\ReceivePurchaseOrderAction;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$purchaseOrderId = (int) ($argv[1] ?? 0);

try {
    // Plain (non-locking) read: this worker may hold a stale APPROVED status.
    $order = PurchaseOrder::query()->findOrFail($purchaseOrderId);

    $app->make(ReceivePurchaseOrderAction::class)->execute($order);

    echo json_encode(['result' => 'received']);
    exit(0);
} catch (InvalidStatusTransitionException $e) {
    echo json_encode(['result' => 'rejected', 'message' => $e->getMessage()]);
    exit(0);
} catch (Throwable $e) {
    echo json_encode(['result' => 'error', 'message' => $e::class.': '.$e->getMessage()]);
    exit(1);
}
