<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real concurrency: two separate PHP processes, each with its own MySQL
 * connection, race to receive the same purchase order.
 *
 * Data must be committed for the worker processes to see it, so this test
 * uses DatabaseTruncation instead of RefreshDatabase's wrapping transaction.
 */
class ReceiveConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        // Leave the shared test database empty for the transactional tests that follow.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_concurrent_receive_requests_increment_stock_exactly_once(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $order = $this->approvedOrderFor($product, 5);

        // Simulate an in-flight request holding the order's row lock, so that both
        // workers are started and end up queued on the same lock before either can proceed.
        DB::beginTransaction();

        try {
            PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $workers = [$this->startWorker($order->id), $this->startWorker($order->id)];

            usleep(2_000_000);
        } finally {
            DB::rollBack();
        }

        $results = array_map(function (Process $worker): array {
            $worker->wait();

            return json_decode($worker->getOutput(), true) ?? ['result' => 'error', 'message' => $worker->getErrorOutput()];
        }, $workers);

        $outcomes = array_column($results, 'result');
        sort($outcomes);

        $this->assertSame(['received', 'rejected'], $outcomes, 'Worker output: '.json_encode($results));

        $rejection = collect($results)->firstWhere('result', 'rejected');
        $this->assertSame('Purchase order cannot transition from RECEIVED to RECEIVED.', $rejection['message']);

        $this->assertSame(15, $product->fresh()->stock_quantity, 'Stock must be incremented exactly once.');
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
    }

    private function startWorker(int $purchaseOrderId): Process
    {
        $process = new Process(
            [(new PhpExecutableFinder)->find(false), base_path('tests/Support/receive-purchase-order.php'), (string) $purchaseOrderId],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => config('database.default'),
                'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
            ],
        );
        $process->setTimeout(60);
        $process->start();

        return $process;
    }
}
