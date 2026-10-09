# Mini ERP: Inventory & Purchase Order Management

A Laravel 13 module for managing **suppliers**, a **product / inventory catalogue**, and
**purchase orders** (POs) through a strict lifecycle. Stock is booked into inventory only when a PO is
received, inside a single row-locked transaction. The module includes:

- A Bootstrap 5 web back office: dashboard, inventory, PO list, create/edit, and PO details with lifecycle actions.
- A Sanctum-protected REST API: login, inventory, low stock, PO creation, status transitions, and a supplier spend report.
- Business rules enforced in the domain/service layer (not just in the UI): lifecycle transitions,
  immutability of RECEIVED/CANCELLED orders, totals always derived from line items on the server, and stock that can never go negative.
- A test suite run against MySQL, including a real two-process concurrency test of the receive operation.

## Requirements

| Component | Version                                                                   |
| --------- | ------------------------------------------------------------------------- |
| PHP       | 8.3+ (developed on 8.5) with `pdo_mysql`                                  |
| Composer  | 2.x                                                                       |
| MySQL     | 8.0.16+ (CHECK constraints are enforced from 8.0.16; developed on 8.0.46) |
| Laravel   | 13.x                                                                      |

Node.js is **not** required. Bootstrap 5.3 is loaded from the jsDelivr CDN with Subresource Integrity
hashes, and the PO form script is a plain static file in `public/js/`.

---

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env` (these are the defaults in `.env.example`):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=machine_test_db
DB_USERNAME=my_app_user
DB_PASSWORD=root
```

Create the application and test databases and grant access (run as a MySQL administrator):

```sql
CREATE DATABASE machine_test_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE machine_test_db_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON machine_test_db.* TO 'my_app_user'@'localhost';
GRANT ALL PRIVILEGES ON machine_test_db_testing.* TO 'my_app_user'@'localhost';
FLUSH PRIVILEGES;
```

Run the migrations and seed sample data:

```bash
php artisan migrate --seed
# or, to rebuild from scratch:
php artisan migrate:fresh --seed
```

Optional settings (`.env`):

| Variable                        | Default | Meaning                                                  |
| ------------------------------- | ------- | -------------------------------------------------------- |
| `INVENTORY_LOW_STOCK_THRESHOLD` | `10`    | Threshold applied to new products that don't specify one |
| `INVENTORY_PER_PAGE`            | `20`    | Default inventory page size                              |
| `SANCTUM_TOKEN_EXPIRATION`      | `1440`  | API token lifetime in minutes                            |

## Running the application

```bash
php artisan serve
```

Open <http://127.0.0.1:8000> and sign in. If port 8000 is used, use `php artisan serve --port=8010` and adjust the URLs.

## Default credentials

Created by `DatabaseSeeder`. Passwords are stored as bcrypt hashes. Both accounts work for the **web UI** and the **API**.

| Role   | Email                | Password   | Permissions                                                             |
| ------ | -------------------- | ---------- | ----------------------------------------------------------------------- |
| Admin  | `admin@example.com`  | `password` | Everything: create/edit POs, approve, receive, cancel                   |
| Viewer | `viewer@example.com` | `password` | Read-only: dashboard, inventory, POs, reports. Mutations return **403** |

Seeded data: 3 suppliers, 10 products (several below their low-stock threshold, one inactive), and
5 purchase orders, one or more in each status. The sample orders are created and transitioned **through
the real services**, so seeded stock levels match the business rules exactly.

---

## Testing

The suite runs against the dedicated MySQL database `machine_test_db_testing` (configured in
`phpunit.xml`; host, port and credentials come from `.env`). Using MySQL rather than SQLite means row locks,
CHECK constraints, UNSIGNED columns and generated columns behave exactly as in production.

```bash
php artisan test
# or
./vendor/bin/phpunit
```

| Area                                                                                                  | Test file(s)                                                                                             |
| ----------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| Line subtotal (5 x 10.50 = 52.50)                                                                     | `tests/Unit/PurchaseOrderItemSubtotalTest.php`                                                           |
| PO grand total (50 + 50 = 100.00)                                                                     | `tests/Unit/PurchaseOrderTotalTest.php`                                                                  |
| Invalid (zero or negative) quantities                                                                 | `tests/Unit/PurchaseOrderQuantityValidationTest.php`, `tests/Feature/Api/CreatePurchaseOrderApiTest.php` |
| Invalid stock mutations / no negative stock                                                           | `tests/Unit/InventoryServiceTest.php`                                                                    |
| Exact money arithmetic                                                                                | `tests/Unit/MoneyTest.php`                                                                               |
| Full lifecycle transition matrix (16 cases)                                                           | `tests/Unit/PurchaseOrderStatusTest.php`                                                                 |
| Receiving (10 + 5 = 15), double receive, RECEIVED to APPROVED gives 422 with no mutation, 401/403/404 | `tests/Feature/Api/PurchaseOrderStatusApiTest.php`                                                       |
| Rollback on mid-transaction failure, stale-model protection                                           | `tests/Feature/ReceivePurchaseOrderTest.php`                                                             |
| **Real concurrency**: two OS processes race to receive one PO, stock incremented once                 | `tests/Feature/ReceiveConcurrencyTest.php`                                                               |
| CANCELLED / RECEIVED immutability (API, service, model, web)                                          | `tests/Feature/PurchaseOrderImmutabilityTest.php`                                                        |
| PO creation: 201/DRAFT, ignored client totals, 401, 403, 422 cases                                    | `tests/Feature/Api/CreatePurchaseOrderApiTest.php`                                                       |
| Sanctum login / logout / invalid token                                                                | `tests/Feature/Api/AuthTest.php`                                                                         |
| Inventory & low-stock API                                                                             | `tests/Feature/Api/InventoryApiTest.php`                                                                 |
| Supplier spend report (single SQL aggregate, RECEIVED only)                                           | `tests/Feature/Api/SupplierSpendReportTest.php`                                                          |
| Web auth, dashboard, inventory, PO screens & actions                                                  | `tests/Feature/Web/*`                                                                                    |
| N+1 regression guard (query count independent of row count)                                           | `tests/Feature/QueryCountTest.php`                                                                       |

---

## Architecture

```
app/
├── Actions/ReceivePurchaseOrderAction.php   Critical receive transition (locks + atomic stock increment)
├── Data/                                    Immutable input DTOs with domain invariants
│   ├── PurchaseOrderData.php                (non-empty, no duplicate products, derived total)
│   └── PurchaseOrderLineData.php            (quantity > 0, unit price >= 0)
├── Enums/PurchaseOrderStatus.php            Lifecycle: single source of truth for allowed transitions
├── Exceptions/                              DomainRuleException (rendered as 422) and subclasses
├── Http/
│   ├── Controllers/                         Thin web controllers (validate, call service, respond)
│   │   ├── Auth/LoginController.php
│   │   └── Api/                             Thin API controllers returning API Resources
│   ├── Requests/                            Form Requests (all validation is centralized here)
│   └── Resources/                           ProductResource, PurchaseOrderResource, SupplierSpendResource, ...
├── Models/                                  Supplier, Product, PurchaseOrder, PurchaseOrderItem, User
├── Policies/PurchaseOrderPolicy.php         Who may act (admin vs. viewer)
├── Queries/                                 Read-side query objects
│   ├── InventoryQuery.php
│   ├── PurchaseOrderQuery.php
│   └── SupplierSpendReport.php              Single SQL aggregate
├── Services/
│   ├── PurchaseOrderService.php             Create / edit draft (transactional)
│   ├── PurchaseOrderStatusService.php       approve / cancel / receive entry point
│   ├── InventoryService.php                 The only code that mutates stock (atomic increment)
│   └── DashboardService.php                 Aggregate metrics
└── Support/Money.php                        Exact decimal arithmetic in integer cents
```

- **Controllers** only validate (via Form Requests), delegate, and shape the response.
- **Form Requests**: `LoginRequest`, `StorePurchaseOrderRequest`, `UpdatePurchaseOrderRequest`,
  `UpdatePurchaseOrderStatusRequest`, `InventoryFilterRequest`, `PurchaseOrderIndexRequest`.
  `authorize()` delegates to `PurchaseOrderPolicy`.
- **Services/Actions** contain all business logic and can be tested without HTTP.
- **Domain errors** (`InvalidStatusTransitionException`, `PurchaseOrderImmutableException`, ...) extend
  `DomainRuleException`. `bootstrap/app.php` renders them as `422 {"message": ...}` for the API and as a
  flashed error message for web requests.
- **Routes**: `routes/web.php` (session auth, CSRF, named routes, PATCH for state changes) and
  `routes/api.php` (Sanctum bearer tokens, throttled). `{purchaseOrder}` uses route-model binding and is
  constrained to digits.
- **Blade views**: `layouts/app.blade.php` plus reusable components `x-alert`, `x-status-badge`,
  `x-validation-errors`, `x-metric-card`, `x-pagination`, `x-purchase-order-actions`.

### Request flow for a status change

```
PATCH /api/purchase-orders/{id}/status
  -> auth:sanctum, throttle:api
  -> UpdatePurchaseOrderStatusRequest   (authorize: policy changeStatus; rules: status in APPROVED|RECEIVED|CANCELLED)
  -> Api\PurchaseOrderStatusController  (thin)
  -> PurchaseOrderStatusService::transition()
       APPROVED / CANCELLED -> lock row, validate, set timestamp
       RECEIVED             -> ReceivePurchaseOrderAction (locks + stock)
  -> PurchaseOrderResource              ({"message": ..., "data": {...}})
```

---

## Database design

### `suppliers`

| Column                  | Type               | Notes      |
| ----------------------- | ------------------ | ---------- |
| id                      | BIGINT UNSIGNED PK |            |
| name                    | VARCHAR(150)       | **UNIQUE** |
| email                   | VARCHAR(255)       | **UNIQUE** |
| phone                   | VARCHAR(30)        |            |
| address                 | TEXT NULL          |            |
| created_at / updated_at | TIMESTAMP          |            |

### `products`

| Column                  | Type                            | Notes                                           |
| ----------------------- | ------------------------------- | ----------------------------------------------- |
| id                      | BIGINT UNSIGNED PK              |                                                 |
| sku                     | VARCHAR(64)                     | **UNIQUE**                                      |
| name                    | VARCHAR(150)                    | indexed (prefix search)                         |
| description             | TEXT NULL                       |                                                 |
| unit_price              | DECIMAL(12,2)                   | baseline unit cost, `CHECK (unit_price >= 0)`   |
| stock_quantity          | INT **UNSIGNED**                | default 0; the database rejects negative values |
| low_stock_threshold     | INT UNSIGNED                    | default 10 (configurable)                       |
| is_active               | TINYINT(1)                      | default 1                                       |
| is_low_stock            | TINYINT(1) **STORED GENERATED** | `stock_quantity < low_stock_threshold`          |
| created_at / updated_at | TIMESTAMP                       |                                                 |

Stock is stored on the product row rather than in a separate inventory table. This module has a single
stock location and no lot tracking, so a separate table would add a join to every inventory read without
any normalization benefit. If multi-warehouse support were needed, a `stock_levels(product_id, warehouse_id, quantity)`
table would be the natural next step.

### `purchase_orders`

| Column                                   | Type                                            | Notes                                                                         |
| ---------------------------------------- | ----------------------------------------------- | ----------------------------------------------------------------------------- |
| id                                       | BIGINT UNSIGNED PK                              | Shown as `PO-000042` (accessor, not stored)                                   |
| supplier_id                              | BIGINT UNSIGNED FK                              | `suppliers.id`, **ON DELETE RESTRICT** (purchasing history is never orphaned) |
| created_by                               | BIGINT UNSIGNED FK NULL                         | `users.id`, ON DELETE SET NULL                                                |
| status                                   | ENUM('DRAFT','APPROVED','RECEIVED','CANCELLED') | default DRAFT; mapped to the `PurchaseOrderStatus` PHP enum                   |
| order_date                               | DATE                                            |                                                                               |
| approved_at / received_at / cancelled_at | TIMESTAMP NULL                                  | set by the corresponding transition                                           |
| notes                                    | TEXT NULL                                       |                                                                               |
| created_at / updated_at                  | TIMESTAMP                                       |                                                                               |

### `purchase_order_items`

| Column                  | Type               | Notes                                                                                                     |
| ----------------------- | ------------------ | --------------------------------------------------------------------------------------------------------- |
| id                      | BIGINT UNSIGNED PK |                                                                                                           |
| purchase_order_id       | BIGINT UNSIGNED FK | `purchase_orders.id`, **ON DELETE CASCADE** (lines are owned by their PO)                                 |
| product_id              | BIGINT UNSIGNED FK | `products.id`, **ON DELETE RESTRICT** (products with history can't be deleted)                            |
| quantity                | INT UNSIGNED       | `CHECK (quantity > 0)`                                                                                    |
| unit_price              | DECIMAL(12,2)      | `CHECK (unit_price >= 0)`; the price agreed on this PO, which may differ from the product's baseline cost |
| created_at / updated_at | TIMESTAMP          |                                                                                                           |

**Totals are never stored.** A line subtotal is `quantity * unit_price`, and a PO total is the sum of its line
subtotals. Every place that shows a total (PO list, detail page, dashboard, supplier report, API)
derives it from the line items with the same SQL expression (`PurchaseOrder::LINE_TOTAL_SQL`) or with the
exact integer-cents arithmetic in `App\Support\Money`. With no stored total, there is nothing that can drift
out of sync, and tests assert that the SQL and PHP calculations agree.

All migrations have working `down()` methods. `php artisan migrate:rollback` drops everything cleanly.

---

## Indexing and query optimization

| Index                                                         | Why                                                                                                                                                                                                                                                                                                                        |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `products.sku` UNIQUE                                         | Uniqueness, plus SKU lookups and prefix search                                                                                                                                                                                                                                                                             |
| `products.name`                                               | Prefix search (`LIKE 'term%'`) and ORDER BY name in the inventory listing                                                                                                                                                                                                                                                  |
| `products (is_active, is_low_stock)`                          | Dashboard counts and the low-stock endpoint. `stock_quantity < low_stock_threshold` compares two columns, so a B-tree index on `stock_quantity` alone **cannot** serve it. The stored generated column makes the predicate indexable. `EXPLAIN` shows the dashboard count is answered from the index alone ("Using index") |
| `purchase_orders.order_date`                                  | Default listing / "latest 5" sort                                                                                                                                                                                                                                                                                          |
| `purchase_orders (status, order_date)`                        | `WHERE status = 'RECEIVED'` for expenditure, and status-filtered listings sorted by date                                                                                                                                                                                                                                   |
| `purchase_orders (supplier_id, status)`                       | Serves the `supplier_id` foreign key (declared before the FK, so MySQL doesn't add a redundant single-column index) and the supplier spend join filtered by status                                                                                                                                                         |
| `purchase_order_items (purchase_order_id, product_id)` UNIQUE | Enforces one line per product per PO, and serves the PO foreign key and the "items of an order" lookups / correlated SUM sub-selects                                                                                                                                                                                       |
| `purchase_order_items.product_id`                             | Product foreign key, plus "which POs contain this product"                                                                                                                                                                                                                                                                 |
| `suppliers.name`, `suppliers.email` UNIQUE                    | Business uniqueness; they double as lookup indexes                                                                                                                                                                                                                                                                         |

Deliberately **not** indexed:

- `products.stock_quantity` on its own. No query filters or sorts on it except the low-stock list, which is
  already narrowed by the composite index.
- `products.is_active` on its own. It is the leading column of the composite index.
- `purchase_orders.status` on its own. It is the leading column of `(status, order_date)`.

### N+1 prevention

- `Model::preventLazyLoading()` is enabled outside production, so any lazy load in a loop **throws** in
  development and in the test suite. `QueryCountTest` also asserts that query counts stay the same as row counts grow.
- **PO list / dashboard "latest 5"**: one query for the page of orders, with `withCount('items')` and a
  correlated `SUM(quantity * unit_price)` sub-select (`withTotal()` scope), plus one eager-load query for
  `supplier:id,name`.
- **PO details**: `load('supplier', 'creator:id,name', 'items', 'items.product:id,sku,name')`. The query count
  is the same for a 1-line PO and a 15-line PO (asserted in `QueryCountTest`).
- **Inventory**: a single paginated query with no relations.
- **Dashboard metrics**: one `COUNT(*) / SUM(is_low_stock)` query, one `SUM(quantity * unit_price)` join for
  RECEIVED orders, and the latest-5 query above. No rows are loaded into PHP for counting.
- **Supplier spend**: one `JOIN ... GROUP BY ... ORDER BY total_spend DESC` query (see `App\Queries\SupplierSpendReport`).
- **PO validation**: product existence/activity for all lines is checked in **one** `WHERE id IN (...)`
  query, instead of one `exists` query per line.
- **Pagination** on the inventory, low-stock and PO listings.

---

## Concurrency and transactions

### Receiving a PO (`App\Actions\ReceivePurchaseOrderAction`)

```php
DB::transaction(function () {
    $order = PurchaseOrder::whereKey($id)->lockForUpdate()->firstOrFail();   // 1. lock the PO row
    $order->status->ensureCanTransitionTo(PurchaseOrderStatus::Received);    // 2-3. verify state from the LOCKED row
    // 4. lock affected product rows in ascending id order (deadlock-free ordering)
    Product::whereKey($productIds)->orderBy('id')->lockForUpdate()->pluck('id');
    // 5. atomic increment: UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?
    $inventory->increment($productId, $quantity);
    // 6-7. update status and received_at
    $order->status = PurchaseOrderStatus::Received; $order->received_at = now(); $order->save();
}, 3);                                                                       // 8. commit (retries on deadlock)
```

Why stock can't be incremented twice:

1. `SELECT ... FOR UPDATE` on the PO row serializes every request trying to receive the same order.
2. The status check uses the row read **under the lock**, never the model passed in by the caller, which may be stale.
3. Request A flips the status to RECEIVED and commits. Request B, which was blocked on the lock, then reads RECEIVED,
   fails the transition check, and gets **422**. Its transaction touches nothing.
4. Stock changes are atomic `UPDATE ... SET stock_quantity = stock_quantity + ?` statements. There is no
   read-modify-write in PHP.
5. Any failure (for example a constraint violation on the second product) rolls back **all** stock changes and the status change.

The test `ReceiveConcurrencyTest` proves this with two real OS processes, each with its own MySQL
connection. The test first holds the PO lock so both workers queue on it, then releases it. One worker
receives the order and the other is rejected, and stock goes from 10 to 15, not 20. `ReceivePurchaseOrderTest` covers
the stale-model case and full rollback.

If MySQL reports a lock-wait timeout or deadlock that survives the retries, the API responds with **409 Conflict**, which signals that the client can safely retry.

### Other transactions

- **PO creation** (header + lines) runs in one transaction.
- **Draft edits** lock the PO row, re-check that it is still DRAFT, then replace the lines in one transaction.
- **Approve / cancel** lock the PO row and validate against the locked state, so two conflicting requests
  (for example approve vs. cancel) can't both succeed.

---

## Business rules

### Lifecycle

```
            ┌──────────► CANCELLED ◄──────────┐
            │                                 │
  DRAFT ────┴──────► APPROVED ────────────────┴──► RECEIVED
```

| From \ To | APPROVED                 | RECEIVED                                 | CANCELLED                 |
| --------- | ------------------------ | ---------------------------------------- | ------------------------- |
| DRAFT     | yes (sets `approved_at`) | no                                       | yes (sets `cancelled_at`) |
| APPROVED  | no                       | yes (sets `received_at`, **adds stock**) | yes (sets `cancelled_at`) |
| RECEIVED  | no                       | no                                       | no                        |
| CANCELLED | no                       | no                                       | no                        |

Exactly the specified lifecycle is implemented, with no extra transitions. Invalid transitions return
`422 {"message": "Purchase order cannot transition from X to Y."}`.

### Inventory

- Stock increases **only** on APPROVED to RECEIVED. Creating, approving and cancelling never touch stock.
- `stock_quantity` is not mass-assignable, and no endpoint accepts a stock value from the client.
- Stock can never go negative: the service rejects non-positive increments, and the column is UNSIGNED as a final safeguard.

### Immutability

RECEIVED and CANCELLED orders are fully immutable: status, supplier, lines, quantities and prices are all frozen. This is enforced at three levels:

1. **Services**: `PurchaseOrderService::updateDraft()` locks the order and only edits DRAFT orders
   (APPROVED orders are also locked for editing, so an approval always refers to the content that was approved).
2. **Model guards**: `PurchaseOrder` (`updating`, `deleting`) and `PurchaseOrderItem` (`saving`, `deleting`)
   throw `PurchaseOrderImmutableException` whenever the stored status is terminal, even if code bypasses the services.
3. **UI**: actions are only shown when valid. This is a convenience, not a safeguard. Forged requests are still rejected (see the tests).

### Purchase order input

- New POs are always created as **DRAFT**. Status, totals and subtotals sent by the client are ignored.
- `supplier_id` must exist. `items` is required, must be a non-empty array, and is capped at 100 lines.
- Each line needs an existing, **active** `product_id`, an integer `quantity` of at least 1 (max 1,000,000), and a numeric
  `unit_price` from 0 to 999,999.99 with at most 2 decimals.
- **Duplicate products**: a product may appear only once per PO. A repeated `product_id` is **rejected** with
  a validation error, rather than silently merged, so the stored order always matches exactly what the client
  submitted. A UNIQUE `(purchase_order_id, product_id)` index enforces this in the database too.

### Authorization

- Web routes require a session login. API routes require a Sanctum bearer token.
- `PurchaseOrderPolicy`: only admins (`users.is_admin`) may create, edit or transition POs. Other authenticated
  users get read-only access, and mutations return **403**.

---

## API documentation

Base URL: `http://127.0.0.1:8000/api`. Send `Accept: application/json` on every request, plus
`Authorization: Bearer {token}` on protected endpoints.

### Response conventions

| Situation                     | Status    | Body                                                                                              |
| ----------------------------- | --------- | ------------------------------------------------------------------------------------------------- |
| Success                       | 200 / 201 | `{"message": "...", "data": {...}}` (paginated responses add `links` and `meta`)                  |
| Validation error              | 422       | `{"message": "The given data was invalid.", "errors": {"field": ["..."]}}`                        |
| Domain / lifecycle error      | 422       | `{"message": "Purchase order cannot transition from RECEIVED to APPROVED."}`                      |
| Missing / invalid token       | 401       | `{"message": "Unauthenticated."}`                                                                 |
| Wrong credentials at login    | 401       | `{"message": "Invalid credentials."}`                                                             |
| Not allowed (viewer)          | 403       | `{"message": "This action is unauthorized."}`                                                     |
| Unknown record                | 404       | `{"message": "Resource not found."}`                                                              |
| Lock contention after retries | 409       | `{"message": "The resource is currently being modified by another request. Please retry."}`       |
| Too many attempts             | 429       | `{"message": "Too Many Attempts."}` (login: 5/min per email+IP; API: 120/min per user)            |
| Unexpected database error     | 500       | `{"message": "A database error occurred while processing the request."}` (no SQL or stack traces) |

### Endpoints

| Method | Path                               | Auth          | Description                                                                     |
| ------ | ---------------------------------- | ------------- | ------------------------------------------------------------------------------- |
| POST   | `/api/login`                       | none          | Exchange email/password for a bearer token                                      |
| POST   | `/api/logout`                      | token         | Revoke the current token                                                        |
| GET    | `/api/inventory`                   | token         | Paginated products (`search`, `low_stock`, `per_page`, `page`)                  |
| GET    | `/api/inventory/low-stock`         | token         | Active products with `stock_quantity < low_stock_threshold`, lowest stock first |
| POST   | `/api/purchase-orders`             | token (admin) | Create a DRAFT PO                                                               |
| GET    | `/api/purchase-orders/{id}`        | token         | PO details with lines and server-computed total                                 |
| PUT    | `/api/purchase-orders/{id}`        | token (admin) | Replace supplier/lines of a **DRAFT** PO (extra endpoint)                       |
| PATCH  | `/api/purchase-orders/{id}/status` | token (admin) | Transition to APPROVED, RECEIVED or CANCELLED                                   |
| GET    | `/api/reports/supplier-spend`      | token         | Expenditure per supplier for RECEIVED orders                                    |

#### `POST /api/login`

Request:

```json
{
    "email": "admin@example.com",
    "password": "password",
    "device_name": "postman"
}
```

`device_name` is optional. Response `200`:

```json
{
    "message": "Login successful.",
    "data": {
        "token": "2|dln5kzyuYsOn0FbrkgJh16YUJBSYm0t1bDe3VblM415aa405",
        "token_type": "Bearer",
        "expires_at": "2026-10-09T18:39:14+00:00",
        "user": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com",
            "is_admin": true
        }
    }
}
```

Wrong credentials return `401 {"message": "Invalid credentials."}`, and missing fields return 422.

#### `GET /api/inventory`

Query parameters: `search` (SKU/name prefix), `low_stock` (`1`/`0`), `per_page` (1–100, default 20), `page`.
Response `200`:

```json
{
    "data": [
        {
            "id": 3,
            "sku": "ELEC-1003",
            "name": "27-inch IPS Monitor",
            "description": "27\" QHD IPS monitor with height-adjustable stand.",
            "stock_quantity": 16,
            "low_stock_threshold": 5,
            "unit_price": "219.00",
            "low_stock": false,
            "is_active": true,
            "updated_at": "2026-10-08T18:31:09+00:00"
        }
    ],
    "links": {
        "first": "...?page=1",
        "last": "...?page=5",
        "prev": null,
        "next": "...?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 2,
        "to": 2,
        "total": 10,
        "path": "...",
        "links": []
    },
    "message": "Inventory retrieved successfully."
}
```

#### `GET /api/inventory/low-stock`

Same shape and parameters (`search`, `per_page`, `page`). Only **active** products below their threshold are
returned, ordered by `stock_quantity` ascending.

#### `POST /api/purchase-orders`

Request:

```json
{
    "supplier_id": 1,
    "order_date": "2026-10-08",
    "notes": "Optional note",
    "items": [
        { "product_id": 1, "quantity": 5, "unit_price": "25.50" },
        { "product_id": 2, "quantity": 10, "unit_price": "15.00" }
    ]
}
```

`order_date` (defaults to today, cannot be in the future) and `notes` are optional. Response `201`:

```json
{
    "data": {
        "id": 6,
        "number": "PO-000006",
        "status": "DRAFT",
        "supplier": {
            "id": 1,
            "name": "Northwind Traders",
            "email": "orders@example.com",
            "phone": "+1-206-555-0142",
            "address": "1200 Harbor Way\nSeattle, WA 98101"
        },
        "order_date": "2026-10-08",
        "approved_at": null,
        "received_at": null,
        "cancelled_at": null,
        "notes": null,
        "allowed_transitions": ["APPROVED", "CANCELLED"],
        "items": [
            {
                "id": 11,
                "product_id": 1,
                "product": {
                    "id": 1,
                    "sku": "ELEC-1001",
                    "name": "Wireless Optical Mouse"
                },
                "quantity": 5,
                "unit_price": "25.50",
                "subtotal": "127.50"
            },
            {
                "id": 12,
                "product_id": 2,
                "product": {
                    "id": 2,
                    "sku": "ELEC-1002",
                    "name": "Mechanical Keyboard"
                },
                "quantity": 10,
                "unit_price": "15.00",
                "subtotal": "150.00"
            }
        ],
        "total": "277.50",
        "created_at": "2026-10-08T18:38:59+00:00",
        "updated_at": "2026-10-08T18:38:59+00:00"
    },
    "message": "Purchase order created successfully."
}
```

Validation failure `422`:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "supplier_id": ["The supplier field is required."],
        "items": ["The items field is required."]
    }
}
```

#### `PATCH /api/purchase-orders/{id}/status`

Request:

```json
{ "status": "RECEIVED" }
```

Allowed values are `APPROVED`, `RECEIVED` and `CANCELLED`. Any other value returns 422 with `errors.status`.
A successful transition returns `200` with the updated PO (same shape as above) and
`"message": "Purchase order status updated to RECEIVED."`.
An invalid transition returns `422`:

```json
{ "message": "Purchase order cannot transition from RECEIVED to RECEIVED." }
```

#### `PUT /api/purchase-orders/{id}`

Same body as creation. Only DRAFT orders can be edited. Otherwise the response is `422`, for example
`{"message": "Purchase order is RECEIVED and can no longer be modified."}`.

#### `GET /api/reports/supplier-spend`

Response `200`:

```json
{
    "data": [
        {
            "supplier_id": 3,
            "supplier_name": "Redington Ltd.",
            "received_orders_count": 1,
            "total_spend": "1377.00"
        },
        {
            "supplier_id": 2,
            "supplier_name": "Contoso Office Supply Co.",
            "received_orders_count": 1,
            "total_spend": "861.50"
        }
    ],
    "message": "Supplier spend report generated successfully.",
    "meta": {
        "status": "RECEIVED",
        "suppliers_count": 2,
        "grand_total": "2238.50"
    }
}
```

The report is computed by a single SQL aggregate:

```sql
SELECT suppliers.id, suppliers.name,
       COUNT(DISTINCT purchase_orders.id) AS received_orders_count,
       SUM(purchase_order_items.quantity * purchase_order_items.unit_price) AS total_spend
FROM suppliers
JOIN purchase_orders      ON purchase_orders.supplier_id = suppliers.id
JOIN purchase_order_items ON purchase_order_items.purchase_order_id = purchase_orders.id
WHERE purchase_orders.status = 'RECEIVED'
GROUP BY suppliers.id, suppliers.name
ORDER BY total_spend DESC, suppliers.name ASC;
```

---

## cURL examples

```bash
BASE=http://127.0.0.1:8000

# 1. Login and capture the token
curl -X POST $BASE/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'

# Copy data.token from the response:
TOKEN="paste-token-here"
# Or extract it automatically:
TOKEN=$(curl -s -X POST $BASE/api/login -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')

# 2. Inventory (paginated)
curl "$BASE/api/inventory?per_page=5" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# 3. Low stock
curl "$BASE/api/inventory/low-stock" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# 4. Create a purchase order (always DRAFT)
curl -X POST $BASE/api/purchase-orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "supplier_id": 1,
    "items": [
      { "product_id": 1, "quantity": 5,  "unit_price": "25.50" },
      { "product_id": 2, "quantity": 10, "unit_price": "15.00" }
    ]
  }'

# 5. Approve, then receive (replace 6 with the id returned above)
curl -X PATCH $BASE/api/purchase-orders/6/status \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status": "APPROVED"}'

curl -X PATCH $BASE/api/purchase-orders/6/status \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status": "RECEIVED"}'

# Receiving again returns 422 and stock is not incremented a second time
curl -X PATCH $BASE/api/purchase-orders/6/status \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status": "RECEIVED"}'

# 6. Cancel a DRAFT or APPROVED order (the seeded DRAFT order has id 4)
curl -X PATCH $BASE/api/purchase-orders/4/status \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status": "CANCELLED"}'

# 7. Supplier spend report
curl "$BASE/api/reports/supplier-spend" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# 8. Logout (revokes the token)
curl -X POST $BASE/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

## Postman

Import `postman_collection.json`. It defines two collection variables:

- `base_url`: defaults to `http://127.0.0.1:8000`
- `token`: filled in automatically by the **Login** request's test script
- `purchase_order_id`: filled in automatically by **Create PO**

Requests: Login, Inventory, Low Stock, Create PO, Get PO, Approve PO, Receive PO, Cancel PO, Supplier Spend, Logout.

---

## Design decisions

- **Status as a MySQL ENUM plus a PHP backed enum.** The database rejects unknown values, and the PHP
  enum (`PurchaseOrderStatus`) is the single source of truth for transitions, labels and badge colours. The migration
  lists the values literally so it stays a fixed snapshot.
- **DRAFT-only editing.** The specification requires immutability after RECEIVED/CANCELLED. This module also
  locks APPROVED orders' content so an approval always refers to exactly what was approved. To change an
  approved order, cancel it and raise a new one.
- **No stored totals.** Totals are always derived from line items (see [Database design](#database-design)).
- **Exact money handling.** DECIMAL(12,2) in MySQL, decimal strings in PHP/JSON (`"25.50"`), and integer-cent
  arithmetic in `App\Support\Money`. Floats are never used for money, including in the browser form script.
- **Roles.** A minimal `is_admin` flag gives real 403 semantics without a roles package. `is_admin` is not
  mass-assignable.
- **Inactive products** can't be added to new orders, but they remain in the catalogue and in historical
  orders. Product deletion is blocked by the foreign key once a product has order history.
- **Error handling.** Business-rule exceptions aren't logged as errors (they are expected outcomes). Database
  errors in API responses never include SQL, bindings or stack traces.
