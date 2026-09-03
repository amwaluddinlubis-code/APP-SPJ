BEGIN TRANSACTION;
DROP TABLE IF EXISTS "account_hierarchies";
CREATE TABLE "account_hierarchies" ("id" integer primary key autoincrement not null, "account_code" varchar not null, "account_name" varchar not null, "level" integer not null, "created_at" datetime, "updated_at" datetime);
DROP TABLE IF EXISTS "account_references";
CREATE TABLE "account_references" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "account_code" varchar not null, "account_name" text, "is_honor" tinyint(1) not null default '0', "is_ppn" tinyint(1) not null default '0', "is_pph21" tinyint(1) not null default '0', "is_pph22" tinyint(1) not null default '0', "is_pph23" tinyint(1) not null default '0', "is_pph4" tinyint(1) not null default '0', "is_sspd" tinyint(1) not null default '0', "is_buku" tinyint(1) not null default '0', "spj_category" varchar, "payload" text, "created_at" datetime, "updated_at" datetime, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP TABLE IF EXISTS "activity_references";
CREATE TABLE "activity_references" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "source_ref_code" varchar, "activity_code" varchar not null, "activity_name" text, "created_at" datetime, "updated_at" datetime, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP TABLE IF EXISTS "arkas_bku_rows";
CREATE TABLE "arkas_bku_rows" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "source_kas_id" varchar not null, "parent_kas_id" varchar, "category" varchar, "no_bukti" varchar, "transaction_date" date, "amount" numeric not null default '0', "payload" text not null, "created_at" datetime, "updated_at" datetime, "fund_source_id" integer, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP TABLE IF EXISTS "arkas_periods";
CREATE TABLE "arkas_periods" ("id" integer primary key autoincrement not null, "source_period_id" varchar not null, "name" varchar not null, "created_at" datetime, "updated_at" datetime);
DROP TABLE IF EXISTS "arkas_rkas_items";
CREATE TABLE "arkas_rkas_items" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "source_rapbs_id" varchar not null, "activity_code" varchar, "activity_name" text, "account_code" varchar, "description" text, "amount" numeric not null default '0', "payload" text not null, "created_at" datetime, "updated_at" datetime, "fund_source_id" integer, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP TABLE IF EXISTS "business_partners";
CREATE TABLE "business_partners" ("id" integer primary key autoincrement not null, "name" varchar not null, "npwp" varchar, "phone" varchar, "address" text, "is_business_entity" tinyint(1) not null default '0', "is_arkas_synced" tinyint(1) not null default '1', "payload" text, "created_at" datetime, "updated_at" datetime);
DROP TABLE IF EXISTS "document_number_sequences";
CREATE TABLE "document_number_sequences" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "format_name" varchar not null, "period_key" varchar not null, "last_number" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP TABLE IF EXISTS "document_templates";
CREATE TABLE "document_templates" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "document_type" varchar not null, "name" varchar not null, "format" varchar not null, "file_path" varchar not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, "applicable_categories" text, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP TABLE IF EXISTS "employees";
CREATE TABLE "employees" ("id" integer primary key autoincrement not null, "source_type" varchar not null, "source_key" varchar not null, "name" varchar not null, "nip" varchar, "nik" varchar, "nuptk" varchar, "gender" varchar, "employment_status" varchar, "staff_type" varchar, "position" varchar, "npwp" varchar, "bank_name" varchar, "bank_account" varchar, "is_active" tinyint(1) not null default '1', "payload" text, "created_at" datetime, "updated_at" datetime);
DROP TABLE IF EXISTS "fiscal_years";
CREATE TABLE "fiscal_years" ("id" integer primary key autoincrement not null, "year" integer not null, "fund_source" varchar not null default 'BOSP', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, "fund_source_id" integer);
DROP TABLE IF EXISTS "fund_sources";
CREATE TABLE "fund_sources" ("id" integer not null, "code" varchar not null, "name" varchar not null, "is_hidden" tinyint(1) not null default '0', "payload" text, "created_at" datetime, "updated_at" datetime, primary key ("id"));
DROP TABLE IF EXISTS "migrations";
CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);
DROP TABLE IF EXISTS "operational_audit_logs";
CREATE TABLE "operational_audit_logs" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer, "entity_type" varchar not null, "entity_id" varchar, "action" varchar not null, "description" varchar not null, "user_id" integer, "created_at" datetime not null default CURRENT_TIMESTAMP, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete set null);
DROP TABLE IF EXISTS "school_profiles";
CREATE TABLE "school_profiles" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "principal_name" varchar, "principal_nip" varchar, "treasurer_name" varchar, "treasurer_nip" varchar, "principal_email" varchar, "principal_phone" varchar, "treasurer_email" varchar, "treasurer_phone" varchar, "payload" text, "created_at" datetime, "updated_at" datetime, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP TABLE IF EXISTS "spj_goods";
CREATE TABLE "spj_goods"
(
    id                  integer not null
        primary key autoincrement,
    transaction_item_id integer not null
        references transaction_items
            on delete cascade,
    order_number        varchar,
    order_date          date,
    bap_number          varchar,
    bap_date            date,
    bast_number         varchar,
    bast_date           date,
    created_at          datetime,
    updated_at          datetime
);
DROP TABLE IF EXISTS "spj_packages";
CREATE TABLE "spj_packages" ("id" integer primary key autoincrement not null, "transaction_id" integer not null, "document_number" varchar, "quarter_code" varchar, "semester_code" varchar, "phase_code" varchar, "status" varchar not null default 'DRAFT', "numbered_at" datetime, "generated_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("transaction_id") references "transactions"("id") on delete cascade);
DROP TABLE IF EXISTS "spj_participants";
CREATE TABLE "spj_participants"
(
    id                  integer             not null
        primary key autoincrement,
    transaction_item_id integer             not null
        references transaction_items
            on delete cascade,
    name                varchar             not null,
    position            varchar,
    portions            numeric default '1' not null,
    created_at          datetime,
    updated_at          datetime
);
DROP TABLE IF EXISTS "spj_travels";
CREATE TABLE "spj_travels"
(
    id                  integer             not null
        primary key autoincrement,
    transaction_item_id integer             not null
        references transactions
            on delete cascade,
    traveler_name       varchar,
    destination         varchar,
    purpose             text,
    departure_date      date,
    return_date         date,
    transport_mode      varchar,
    participant_count   integer,
    amount              numeric default '0' not null,
    notes               text,
    created_at          datetime,
    updated_at          datetime
);
DROP TABLE IF EXISTS "spj_work_order";
CREATE TABLE "spj_work_order"
(
    id                integer not null
        constraint spj_work_order_pk
            primary key,
    transaction_id    integer not null
        constraint spj_work_order_transactions_id_fk
            references transactions,
    work_location     varchar,
    spk_number        varchar,
    spk_date          date,
    rab_number        varchar,
    rab_date          date,
    work_started_at   date,
    work_completed_at date
, created_at datetime, updated_at datetime);
DROP TABLE IF EXISTS "spj_workers";
CREATE TABLE "spj_workers"
(
    id                   integer                not null
        primary key autoincrement,
    work_order_id        integer                not null
        references spj_workers
            on delete cascade,
    name                 varchar                not null,
    nik                  varchar                not null,
    job_description      varchar,
    work_days            numeric    default '0' not null,
    daily_rate           numeric    default '0' not null,
    amount               numeric    default '0' not null,
    is_receipt_recipient tinyint(1) default '0' not null,
    notes                text,
    created_at           datetime,
    updated_at           datetime
);
DROP TABLE IF EXISTS "sqlite_stat4";
CREATE TABLE sqlite_stat4(tbl,idx,neq,nlt,ndlt,sample);
DROP TABLE IF EXISTS "sync_runs";
CREATE TABLE "sync_runs" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer, "source" varchar not null default 'ARKAS', "status" varchar not null, "records_read" integer not null default '0', "records_written" integer not null default '0', "message" text, "started_at" datetime not null, "finished_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete set null);
DROP TABLE IF EXISTS "transaction_items";
CREATE TABLE "transaction_items" ("id" integer primary key autoincrement not null, "transaction_id" integer not null, "source_item_id" varchar, "description" text not null, "item_description" text, "quantity" numeric not null default '1', "unit" varchar, "unit_price" numeric not null default '0', "amount" numeric not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("transaction_id") references "transactions"("id") on delete cascade);
DROP TABLE IF EXISTS "transactions";
CREATE TABLE "transactions" ("id" integer primary key autoincrement not null, "fiscal_year_id" integer not null, "id_kas_umum" varchar, "no_bukti" varchar not null, "transaction_date" date not null, "description" text, "payment_description" text, "spj_category" varchar, "payment_method" varchar, "payment_reference" varchar, "activity_code" varchar, "activity_name" text, "account_code" varchar, "account_name" text, "recipient_name" varchar, "gross_amount" numeric not null default '0', "ppn" numeric not null default '0', "pph21" numeric not null default '0', "pph22" numeric not null default '0', "pph23" numeric not null default '0', "pph4" numeric not null default '0', "sspd" numeric not null default '0', "tax_total" numeric not null default '0', "net_amount" numeric not null default '0', "is_siplah" tinyint(1) not null default '0', "status" varchar not null default 'DRAFT', "created_at" datetime, "updated_at" datetime, "invoice_number" varchar, "invoice_date" date, "invoice_status" varchar, "work_description" text, "work_location" varchar, "work_started_at" date, "work_completed_at" date, "spk_number" varchar, "spk_date" date, "signatory_name" varchar, "signatory_role" varchar, "manual_description" text, "fund_source_id" integer, "order_number" varchar, "order_date" date, "bap_number" varchar, "bap_date" date, "bast_number" varchar, "bast_date" date, "event_name" varchar, "event_location" varchar, "participant_count" integer, "vendor_name" varchar, "vendor_owner" varchar, "vendor_npwp" varchar, "ppn_rate" numeric, "pph21_rate" numeric, "pph22_rate" numeric, "pph23_rate" numeric, "spj_recipient_name" varchar, foreign key("fiscal_year_id") references "fiscal_years"("id") on delete cascade);
DROP INDEX IF EXISTS "account_hierarchies_account_code_unique";
CREATE UNIQUE INDEX "account_hierarchies_account_code_unique" on "account_hierarchies" ("account_code");
DROP INDEX IF EXISTS "account_references_fiscal_year_id_account_code_unique";
CREATE UNIQUE INDEX "account_references_fiscal_year_id_account_code_unique" on "account_references" ("fiscal_year_id", "account_code");
DROP INDEX IF EXISTS "activity_references_fiscal_year_id_activity_code_unique";
CREATE UNIQUE INDEX "activity_references_fiscal_year_id_activity_code_unique" on "activity_references" ("fiscal_year_id", "activity_code");
DROP INDEX IF EXISTS "arkas_bku_rows_fiscal_year_id_fund_source_id_index";
CREATE INDEX "arkas_bku_rows_fiscal_year_id_fund_source_id_index" on "arkas_bku_rows" ("fiscal_year_id", "fund_source_id");
DROP INDEX IF EXISTS "arkas_bku_rows_fiscal_year_id_source_kas_id_unique";
CREATE UNIQUE INDEX "arkas_bku_rows_fiscal_year_id_source_kas_id_unique" on "arkas_bku_rows" ("fiscal_year_id", "source_kas_id");
DROP INDEX IF EXISTS "arkas_periods_source_period_id_unique";
CREATE UNIQUE INDEX "arkas_periods_source_period_id_unique" on "arkas_periods" ("source_period_id");
DROP INDEX IF EXISTS "arkas_rkas_items_fiscal_year_id_fund_source_id_index";
CREATE INDEX "arkas_rkas_items_fiscal_year_id_fund_source_id_index" on "arkas_rkas_items" ("fiscal_year_id", "fund_source_id");
DROP INDEX IF EXISTS "arkas_rkas_items_fiscal_year_id_source_rapbs_id_unique";
CREATE UNIQUE INDEX "arkas_rkas_items_fiscal_year_id_source_rapbs_id_unique" on "arkas_rkas_items" ("fiscal_year_id", "source_rapbs_id");
DROP INDEX IF EXISTS "business_partners_name_npwp_unique";
CREATE UNIQUE INDEX "business_partners_name_npwp_unique" on "business_partners" ("name", "npwp");
DROP INDEX IF EXISTS "document_number_sequences_fiscal_year_id_format_name_period_key_unique";
CREATE UNIQUE INDEX "document_number_sequences_fiscal_year_id_format_name_period_key_unique" on "document_number_sequences" ("fiscal_year_id", "format_name", "period_key");
DROP INDEX IF EXISTS "document_templates_fiscal_year_id_document_type_format_unique";
CREATE UNIQUE INDEX "document_templates_fiscal_year_id_document_type_format_unique" on "document_templates" ("fiscal_year_id", "document_type", "format");
DROP INDEX IF EXISTS "employees_source_type_source_key_unique";
CREATE UNIQUE INDEX "employees_source_type_source_key_unique" on "employees" ("source_type", "source_key");
DROP INDEX IF EXISTS "fiscal_years_year_fund_source_id_index";
CREATE INDEX "fiscal_years_year_fund_source_id_index" on "fiscal_years" ("year", "fund_source_id");
DROP INDEX IF EXISTS "fiscal_years_year_fund_source_unique";
CREATE UNIQUE INDEX "fiscal_years_year_fund_source_unique" on "fiscal_years" ("year", "fund_source");
DROP INDEX IF EXISTS "school_profiles_fiscal_year_id_unique";
CREATE UNIQUE INDEX "school_profiles_fiscal_year_id_unique" on "school_profiles" ("fiscal_year_id");
DROP INDEX IF EXISTS "spj_packages_document_number_unique";
CREATE UNIQUE INDEX "spj_packages_document_number_unique" on "spj_packages" ("document_number");
DROP INDEX IF EXISTS "spj_packages_transaction_id_unique";
CREATE UNIQUE INDEX "spj_packages_transaction_id_unique" on "spj_packages" ("transaction_id");
DROP INDEX IF EXISTS "transaction_items_transaction_id_source_item_id_unique";
CREATE UNIQUE INDEX "transaction_items_transaction_id_source_item_id_unique" on "transaction_items" ("transaction_id", "source_item_id");
DROP INDEX IF EXISTS "transactions_fiscal_year_id_fund_source_id_index";
CREATE INDEX "transactions_fiscal_year_id_fund_source_id_index" on "transactions" ("fiscal_year_id", "fund_source_id");
DROP INDEX IF EXISTS "transactions_fiscal_year_id_no_bukti_unique";
CREATE UNIQUE INDEX "transactions_fiscal_year_id_no_bukti_unique" on "transactions" ("fiscal_year_id", "no_bukti");
DROP INDEX IF EXISTS "transactions_id_kas_umum_index";
CREATE INDEX "transactions_id_kas_umum_index" on "transactions" ("id_kas_umum");
DROP INDEX IF EXISTS "transactions_no_bukti_index";
CREATE INDEX "transactions_no_bukti_index" on "transactions" ("no_bukti");
DROP INDEX IF EXISTS "transactions_transaction_date_index";
CREATE INDEX "transactions_transaction_date_index" on "transactions" ("transaction_date");
COMMIT;
