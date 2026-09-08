<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->create('transaction_source_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('before_hash', 64)->nullable();
            $table->string('after_hash', 64)->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['transaction_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });

        if (DB::connection('school')->getDriverName() !== 'sqlite') {
            return;
        }

        DB::connection('school')->unprepared(<<<'SQL'
CREATE TRIGGER transactions_source_changed_event
AFTER UPDATE OF source_hash ON transactions
FOR EACH ROW
WHEN OLD.source_hash IS NOT NULL
 AND NEW.source_hash IS NOT OLD.source_hash
BEGIN
    INSERT INTO transaction_source_events (
        transaction_id, sync_run_id, event_type, before_hash, after_hash,
        before_snapshot, after_snapshot, created_at
    ) VALUES (
        NEW.id,
        NEW.last_seen_sync_run_id,
        'SOURCE_CHANGED',
        OLD.source_hash,
        NEW.source_hash,
        json_object(
            'transaction_date', OLD.transaction_date,
            'description', OLD.description,
            'activity_code', OLD.activity_code,
            'activity_name', OLD.activity_name,
            'account_code', OLD.account_code,
            'account_name', OLD.account_name,
            'recipient_name', OLD.recipient_name,
            'gross_amount', OLD.gross_amount,
            'ppn', OLD.ppn,
            'pph21', OLD.pph21,
            'pph22', OLD.pph22,
            'pph23', OLD.pph23,
            'pph4', OLD.pph4,
            'sspd', OLD.sspd,
            'tax_total', OLD.tax_total,
            'net_amount', OLD.net_amount,
            'is_siplah', OLD.is_siplah
        ),
        json_object(
            'transaction_date', NEW.transaction_date,
            'description', NEW.description,
            'activity_code', NEW.activity_code,
            'activity_name', NEW.activity_name,
            'account_code', NEW.account_code,
            'account_name', NEW.account_name,
            'recipient_name', NEW.recipient_name,
            'gross_amount', NEW.gross_amount,
            'ppn', NEW.ppn,
            'pph21', NEW.pph21,
            'pph22', NEW.pph22,
            'pph23', NEW.pph23,
            'pph4', NEW.pph4,
            'sspd', NEW.sspd,
            'tax_total', NEW.tax_total,
            'net_amount', NEW.net_amount,
            'is_siplah', NEW.is_siplah
        ),
        CURRENT_TIMESTAMP
    );
END;
SQL);

        DB::connection('school')->unprepared(<<<'SQL'
CREATE TRIGGER transactions_source_status_event
AFTER UPDATE OF source_status ON transactions
FOR EACH ROW
WHEN COALESCE(OLD.source_status, '') <> COALESCE(NEW.source_status, '')
BEGIN
    INSERT INTO transaction_source_events (
        transaction_id, sync_run_id, event_type, before_hash, after_hash,
        before_snapshot, after_snapshot, created_at
    ) VALUES (
        NEW.id,
        NEW.last_seen_sync_run_id,
        CASE WHEN NEW.source_status = 'SOURCE_MISSING' THEN 'SOURCE_MISSING' ELSE 'SOURCE_RETURNED' END,
        OLD.source_hash,
        NEW.source_hash,
        json_object('source_status', OLD.source_status, 'source_missing_since', OLD.source_missing_since),
        json_object('source_status', NEW.source_status, 'source_missing_since', NEW.source_missing_since),
        CURRENT_TIMESTAMP
    );
END;
SQL);

        DB::connection('school')->unprepared(<<<'SQL'
CREATE TRIGGER transaction_items_source_changed_event
AFTER UPDATE OF description, quantity, unit, unit_price, amount ON transaction_items
FOR EACH ROW
WHEN (
    COALESCE(OLD.description, '') <> COALESCE(NEW.description, '')
    OR COALESCE(OLD.quantity, 0) <> COALESCE(NEW.quantity, 0)
    OR COALESCE(OLD.unit, '') <> COALESCE(NEW.unit, '')
    OR COALESCE(OLD.unit_price, 0) <> COALESCE(NEW.unit_price, 0)
    OR COALESCE(OLD.amount, 0) <> COALESCE(NEW.amount, 0)
)
AND EXISTS (
    SELECT 1 FROM spj_packages WHERE transaction_id = NEW.transaction_id
)
BEGIN
    UPDATE transactions
    SET requires_reconciliation = 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.transaction_id;

    INSERT INTO transaction_source_events (
        transaction_id, sync_run_id, event_type, before_hash, after_hash,
        before_snapshot, after_snapshot, created_at
    )
    SELECT
        t.id,
        NEW.last_seen_sync_run_id,
        'SOURCE_ITEM_CHANGED',
        t.source_hash,
        t.source_hash,
        json_object(
            'source_item_id', OLD.source_item_id,
            'description', OLD.description,
            'quantity', OLD.quantity,
            'unit', OLD.unit,
            'unit_price', OLD.unit_price,
            'amount', OLD.amount
        ),
        json_object(
            'source_item_id', NEW.source_item_id,
            'description', NEW.description,
            'quantity', NEW.quantity,
            'unit', NEW.unit,
            'unit_price', NEW.unit_price,
            'amount', NEW.amount
        ),
        CURRENT_TIMESTAMP
    FROM transactions t
    WHERE t.id = NEW.transaction_id;
END;
SQL);
    }

    public function down(): void
    {
        if (DB::connection('school')->getDriverName() === 'sqlite') {
            DB::connection('school')->unprepared('DROP TRIGGER IF EXISTS transaction_items_source_changed_event');
            DB::connection('school')->unprepared('DROP TRIGGER IF EXISTS transactions_source_status_event');
            DB::connection('school')->unprepared('DROP TRIGGER IF EXISTS transactions_source_changed_event');
        }

        Schema::connection('school')->dropIfExists('transaction_source_events');
    }
};
