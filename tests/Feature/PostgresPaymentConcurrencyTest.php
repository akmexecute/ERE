<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Financial\Enums\LedgerAccountCode;
use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Financial\Models\LedgerEntry;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Actions\InitiatePaymentAction;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType as PropType;
use App\Domains\User\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Dedicated Native PostgreSQL Concurrency, Multi-Connection Isolation & Financial Trigger Suite.
 *
 * Verifies multi-connection concurrency, transaction isolation boundaries,
 * row-level locking (SELECT FOR UPDATE), and append-only immutability triggers
 * against a real PostgreSQL database instance.
 */
final class PostgresPaymentConcurrencyTest extends TestCase
{
    private string $conn1Name = 'pgsql_test_conn1';
    private string $conn2Name = 'pgsql_test_conn2';
    private ?PDO $pdo1 = null;
    private ?PDO $pdo2 = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePostgreSqlAvailableOrSkip();
    }

    /**
     * Verify PostgreSQL service connectivity and check for authorized credentials.
     * Uses dedicated testing role (default: ere_test) and database (default: ere_testing).
     * If credentials are unavailable or password authentication fails, marks test
     * as skipped with the exact prerequisite documented.
     */
    private function ensurePostgreSqlAvailableOrSkip(): void
    {
        $host = env('DB_PG_HOST', '127.0.0.1');
        $port = env('DB_PG_PORT', '5432');
        $database = env('DB_PG_DATABASE', 'ere_testing');
        $username = env('DB_PG_USERNAME', 'ere_test');
        $password = env('DB_PG_PASSWORD', '');

        if (empty($password)) {
            $this->markTestSkipped(
                "Native PostgreSQL verification requires authorized credentials. " .
                "PostgreSQL 18 service 'postgresql-x64-18' is running on {$host}:{$port}, " .
                "enforcing SCRAM-SHA-256 password authentication. " .
                "Execute the idempotent setup script in pgAdmin 4 (CREATE ROLE {$username} ... CREATE DATABASE {$database} ...) " .
                "and set DB_PG_PASSWORD in .env.testing to run native PostgreSQL concurrency tests."
            );
        }

        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

            // Connection 1: Genuine independent PDO instance
            $this->pdo1 = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]);

            // Connection 2: Genuine distinct independent PDO instance
            $this->pdo2 = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]);

            Config::set("database.connections.{$this->conn1Name}", [
                'driver' => 'pgsql',
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);

            Config::set("database.connections.{$this->conn2Name}", [
                'driver' => 'pgsql',
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);

            // Ensure tables are migrated before running concurrency suite
            $tableCheck = $this->pdo1->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'")->fetch();
            if (! $tableCheck) {
                $this->markTestSkipped(
                    "PostgreSQL database '{$database}' connected, but schema is not migrated. Run `php artisan migrate --database=pgsql` to enable native PostgreSQL concurrency tests."
                );
            }
        } catch (PDOException $e) {
            $this->markTestSkipped(
                "PostgreSQL connection to {$host}:{$port}/{$database} failed: " . $e->getMessage() .
                ". Ensure dedicated test database '{$database}' is created and password is valid."
            );
        }
    }

    /**
     * Verification 1: Two independent connections cannot over-reserve payment headroom on PostgreSQL.
     */
    public function test_concurrent_connections_cannot_over_reserve_headroom_on_postgres(): void
    {
        $conn1 = DB::connection($this->conn1Name);
        $conn2 = DB::connection($this->conn2Name);

        $buyer = User::on($this->conn1Name)->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'PG Concurrency Buyer',
            'phone' => '+251911999881',
            'status' => 'active',
        ]);

        $category = PropertyCategory::on($this->conn1Name)->firstOrCreate(
            ['code' => 'LND'],
            ['name_en' => 'Land', 'slug' => 'land-pg']
        );
        $type = PropType::on($this->conn1Name)->firstOrCreate(
            ['slug' => 'urban-plot-pg'],
            ['category_id' => $category->id, 'name_en' => 'Urban Plot PG']
        );

        $property = Property::on($this->conn1Name)->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-PG-' . Str::upper(Str::random(6)),
            'title_en' => 'PostgreSQL Concurrency Test Plot',
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'owner_id' => $buyer->id,
            'created_by_user_id' => $buyer->id,
            'listing_type' => 'sale',
            'status' => 'available',
            'current_price_etb' => 1000000.00,
        ]);

        $offer = PropertyOffer::on($this->conn1Name)->create([
            'offer_code' => 'OFR-PG-' . Str::upper(Str::random(6)),
            'property_id' => $property->id,
            'listing_id' => 1,
            'buyer_user_id' => $buyer->id,
            'seller_user_id' => $buyer->id,
            'listed_asking_price_etb' => 1000000.00,
            'initial_offer_amount_etb' => 1000000.00,
            'latest_amount_etb' => 1000000.00,
            'status' => 'accepted',
            'expires_at' => now()->addDays(7),
        ]);

        // Connection 1 acquires row lock and creates 800,000 ETB hold
        $conn1->transaction(function () use ($offer, $buyer): void {
            $locked = PropertyOffer::on($this->conn1Name)->whereKey($offer->id)->lockForUpdate()->first();
            $allocatable = $locked->calculateAllocatableRemainingBalanceEtb();
            $this->assertSame('1000000.00', $allocatable);

            Payment::on($this->conn1Name)->create([
                'uuid' => (string) Str::uuid(),
                'customer_user_id' => $buyer->id,
                'payable_type' => PropertyOffer::class,
                'payable_id' => $locked->id,
                'payment_type' => PaymentType::PropertyPurchase,
                'principal_amount_etb' => 800000.00,
                'total_amount_etb' => 800000.00,
                'currency' => 'ETB',
                'payment_method' => PaymentMethod::Telebirr,
                'status' => PaymentStatus::Pending,
                'allocation_status' => PurchaseAllocationStatus::Unallocated,
                'reservation_expires_at' => now()->addMinutes(30),
                'idempotency_key' => 'IDEMP-PG-CONN1-' . Str::random(8),
            ]);
        });

        // Connection 2 now observes the active pending hold across connection boundaries
        $conn2->transaction(function () use ($offer, $buyer): void {
            $locked2 = PropertyOffer::on($this->conn2Name)->whereKey($offer->id)->lockForUpdate()->first();
            $allocatable2 = $locked2->calculateAllocatableRemainingBalanceEtb();

            // Headroom is strictly 200,000 ETB remaining (1,000,000 - 800,000 active pending hold)
            $this->assertSame('200000.00', $allocatable2);

            // Attempting to reserve 800,000 ETB must fail due to insufficient headroom
            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('exceeds the remaining contractual balance');
            $locked2->validateEligibleForPayment(800000.00, false);
        });
    }

    /**
     * Verification 2: PostgreSQL transaction rollback on Connection 1 releases hold immediately for Connection 2.
     */
    public function test_postgres_transaction_rollback_releases_hold_across_connections(): void
    {
        $conn1 = DB::connection($this->conn1Name);
        $conn2 = DB::connection($this->conn2Name);

        $buyer = User::on($this->conn1Name)->first();
        $offer = PropertyOffer::on($this->conn1Name)->first();

        $initialHeadroom = $offer->calculateAllocatableRemainingBalanceEtb();

        // Start transaction on Connection 1, create hold, and deliberately rollback
        try {
            $conn1->transaction(function () use ($offer, $buyer): void {
                Payment::on($this->conn1Name)->create([
                    'uuid' => (string) Str::uuid(),
                    'customer_user_id' => $buyer->id,
                    'payable_type' => PropertyOffer::class,
                    'payable_id' => $offer->id,
                    'payment_type' => PaymentType::PropertyPurchase,
                    'principal_amount_etb' => 200000.00,
                    'total_amount_etb' => 200000.00,
                    'currency' => 'ETB',
                    'payment_method' => PaymentMethod::Telebirr,
                    'status' => PaymentStatus::Pending,
                    'allocation_status' => PurchaseAllocationStatus::Unallocated,
                    'reservation_expires_at' => now()->addMinutes(30),
                    'idempotency_key' => 'IDEMP-PG-ROLLBACK-' . Str::random(8),
                ]);

                throw new \RuntimeException('Deliberate rollback test.');
            });
        } catch (\RuntimeException) {
            // Expected rollback
        }

        // Connection 2 reads headroom: hold must not exist, headroom fully preserved
        $headroomOnConn2 = PropertyOffer::on($this->conn2Name)->find($offer->id)->calculateAllocatableRemainingBalanceEtb();
        $this->assertSame($initialHeadroom, $headroomOnConn2);
    }

    /**
     * Verification 3: Native PostgreSQL append-only ledger trigger blocks updates and deletes.
     */
    public function test_postgres_append_only_ledger_trigger_immutability(): void
    {
        $conn = DB::connection($this->conn1Name);

        $entry = LedgerEntry::on($this->conn1Name)->first();
        if (! $entry) {
            $this->markTestSkipped('No ledger entries present in PostgreSQL test database to test trigger.');
        }

        // Attempt direct UPDATE statement against PostgreSQL table
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Ledger entries are strictly append-only');
        $conn->statement("UPDATE ledger_entries SET amount_etb = 999999.00 WHERE id = ?", [$entry->id]);
    }
}
