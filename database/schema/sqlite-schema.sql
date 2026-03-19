CREATE TABLE "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE "carriers"(
  "id" integer primary key autoincrement not null,
  "guid" varchar not null,
  "short_code" varchar not null,
  "wt_code" varchar,
  "name" varchar not null,
  "emails" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "carriers_guid_unique" on "carriers"("guid");
CREATE UNIQUE INDEX "carriers_short_code_unique" on "carriers"("short_code");
CREATE INDEX "carriers_wt_code_index" on "carriers"("wt_code");
CREATE TABLE "activity_log"(
  "id" integer primary key autoincrement not null,
  "log_name" varchar,
  "description" text not null,
  "subject_type" varchar,
  "subject_id" integer,
  "causer_type" varchar,
  "causer_id" integer,
  "properties" text,
  "created_at" datetime,
  "updated_at" datetime,
  "event" varchar,
  "batch_uuid" varchar
);
CREATE INDEX "subject" on "activity_log"("subject_type", "subject_id");
CREATE INDEX "causer" on "activity_log"("causer_type", "causer_id");
CREATE INDEX "activity_log_log_name_index" on "activity_log"("log_name");
CREATE TABLE "rates"(
  "id" integer primary key autoincrement not null,
  "carrier_id" integer not null,
  "pickup_location_id" integer not null,
  "dc_location_id" integer not null,
  "rate" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  "name" varchar,
  "type" varchar check("type" in('flat', 'per_mile')) not null default 'per_mile',
  "effective_from" datetime,
  "effective_to" datetime,
  foreign key("carrier_id") references "carriers"("id") on delete cascade,
  foreign key("pickup_location_id") references "locations"("id") on delete cascade,
  foreign key("dc_location_id") references "locations"("id") on delete cascade
);
CREATE UNIQUE INDEX "rates_unique_combination" on "rates"(
  "carrier_id",
  "pickup_location_id",
  "dc_location_id"
);
CREATE TABLE "templates"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "subject" varchar,
  "message" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "templates_model_type_model_id_index" on "templates"(
  "model_type",
  "model_id"
);
CREATE UNIQUE INDEX "templates_name_unique" on "templates"("name");
CREATE TABLE "__temp__shipments"(
  "id" integer primary key autoincrement not null,
  "guid" varchar not null,
  "shipment_number" varchar,
  "bol" varchar,
  "po_number" varchar,
  "status" varchar not null default('Pending'),
  "pickup_location_id" integer not null,
  "dc_location_id" integer not null,
  "carrier_id" integer,
  "drop_date" date,
  "pickup_date" datetime,
  "delivery_date" datetime,
  "rack_qty" integer not null default('0'),
  "load_bar_qty" integer not null default('0'),
  "strap_qty" integer not null default('0'),
  "trailer" varchar,
  "drayage" varchar,
  "on_site" tinyint(1) not null default '0',
  "shipped" tinyint(1) not null default '0',
  "crossed_border" datetime,
  "recycling_sent" tinyint(1) not null default '0',
  "paperwork_sent" tinyint(1) not null default '0',
  "delivery_sent" datetime,
  "consolidation_number" varchar,
  "notes" text,
  "other" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("pickup_location_id") references locations("id") on delete restrict on update no action,
  foreign key("dc_location_id") references locations("id") on delete restrict on update no action,
  foreign key("carrier_id") references carriers("id") on delete set null on update no action
);
CREATE TABLE "location_distances"(
  "id" integer primary key autoincrement not null,
  "from_location_id" integer not null,
  "to_location_id" integer not null,
  "distance_km" numeric,
  "distance_miles" numeric,
  "duration_text" varchar,
  "duration_minutes" integer,
  "route_coords" text,
  "calculated_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("from_location_id") references "locations"("id") on delete cascade,
  foreign key("to_location_id") references "locations"("id") on delete cascade
);
CREATE UNIQUE INDEX "location_distances_from_location_id_to_location_id_unique" on "location_distances"(
  "from_location_id",
  "to_location_id"
);
CREATE TABLE "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "two_factor_secret" text,
  "two_factor_recovery_codes" text,
  "two_factor_confirmed_at" datetime,
  "carrier_id" integer,
  foreign key("carrier_id") references "carriers"("id") on delete set null
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE "notes"(
  "id" integer primary key autoincrement not null,
  "notable_type" varchar not null,
  "notable_id" integer not null,
  "content" text not null,
  "is_admin" tinyint(1) not null default '0',
  "user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "notes_notable_type_notable_id_index" on "notes"(
  "notable_type",
  "notable_id"
);
CREATE INDEX "notes_notable_id_notable_type_index" on "notes"(
  "notable_id",
  "notable_type"
);
CREATE INDEX "notes_user_id_index" on "notes"("user_id");
CREATE TABLE "notifications"(
  "id" varchar not null,
  "type" varchar not null,
  "data" text not null,
  "read_at" datetime,
  "notifiable_type" varchar not null,
  "notifiable_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);
CREATE INDEX "notifications_new_notifiable_type_notifiable_id_index" on "notifications"(
  "notifiable_type",
  "notifiable_id"
);
CREATE TABLE "shipments"(
  "id"	integer NOT NULL,
  "guid"	varchar NOT NULL,
  "shipment_number"	varchar,
  "bol"	varchar,
  "po_number"	varchar,
  "status"	varchar NOT NULL DEFAULT('Pending'),
  "pickup_location_id"	integer NOT NULL,
  "dc_location_id"	integer NOT NULL,
  "carrier_id"	integer,
  "drop_date"	date,
  "pickup_date"	datetime,
  "delivery_date"	datetime,
  "rack_qty"	integer NOT NULL DEFAULT('0'),
  "load_bar_qty"	integer NOT NULL DEFAULT('0'),
  "strap_qty"	integer NOT NULL DEFAULT('0'),
  "trailer"	varchar,
  "drayage"	varchar,
  "on_site"	datetime,
  "shipped"	datetime,
  "crossed"	datetime,
  "recycling_sent"	datetime,
  "paperwork_sent"	datetime,
  "delivery_alert_sent"	datetime,
  "consolidation_number"	varchar,
  "other"	text,
  "created_at"	datetime,
  "updated_at"	datetime,
  "deleted_at"	datetime,
  "confirmed"	tinyint(1) NOT NULL DEFAULT '0',
  "seal_number"	TEXT,
  "drivers_id"	TEXT,
  PRIMARY KEY("id" AUTOINCREMENT),
  FOREIGN KEY("carrier_id") REFERENCES "carriers"("id") on delete set null on update no action,
  FOREIGN KEY("dc_location_id") REFERENCES "locations"("id") on delete restrict on update no action,
  FOREIGN KEY("pickup_location_id") REFERENCES "locations"("id") on delete restrict on update no action
);
CREATE UNIQUE INDEX "shipments_guid_unique" on "shipments"("guid");
CREATE UNIQUE INDEX "shipments_shipment_number_unique" on "shipments"(
  "shipment_number"
);
CREATE TABLE "locations"(
  "id"	integer NOT NULL,
  "guid"	varchar,
  "short_code"	varchar NOT NULL,
  "name"	varchar,
  "address"	text NOT NULL,
  "city"	varchar,
  "state"	varchar,
  "zip"	varchar,
  "country"	varchar NOT NULL DEFAULT('US'),
  "type"	varchar NOT NULL DEFAULT('pickup'),
  "recycling_location_id"	integer,
  "latitude"	numeric,
  "longitude"	numeric,
  "is_active"	tinyint(1) NOT NULL DEFAULT('1'),
  "created_at"	datetime,
  "updated_at"	datetime,
  "emails"	text,
  "expected_arrival_time"	datetime,
  PRIMARY KEY("id" AUTOINCREMENT),
  FOREIGN KEY("recycling_location_id") REFERENCES "locations"("id") on delete set null on update no action
);
CREATE UNIQUE INDEX "locations_guid_unique" on "locations"("guid");

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_08_14_170933_add_two_factor_columns_to_users_table',1);
INSERT INTO migrations VALUES(5,'2026_01_15_044344_create_permission_tables',1);
INSERT INTO migrations VALUES(6,'2026_01_15_093103_create_locations_table',1);
INSERT INTO migrations VALUES(7,'2026_01_15_100227_create_carriers_table',1);
INSERT INTO migrations VALUES(8,'2026_01_19_083949_create_activity_log_table',1);
INSERT INTO migrations VALUES(9,'2026_01_19_083950_add_event_column_to_activity_log_table',1);
INSERT INTO migrations VALUES(10,'2026_01_19_083951_add_batch_uuid_column_to_activity_log_table',1);
INSERT INTO migrations VALUES(11,'2026_01_19_095128_create_shipments_table',1);
INSERT INTO migrations VALUES(12,'2026_01_19_144407_add_email_and_expected_arrival_time_to_locations_table',1);
INSERT INTO migrations VALUES(13,'2026_01_30_172621_create_rates_table',2);
INSERT INTO migrations VALUES(14,'2026_01_31_061300_create_templates_table',2);
INSERT INTO migrations VALUES(15,'2026_02_02_180918_alter_shipments_add_nullable_datetimes_and_columns',3);
INSERT INTO migrations VALUES(17,'2026_02_04_001528_create_location_distances_table',4);
INSERT INTO migrations VALUES(20,'2026_02_09_220422_add_carrier_id_to_users_table',5);
INSERT INTO migrations VALUES(21,'2026_02_10_170627_add_carrier_confirmed_to_shipments',5);
INSERT INTO migrations VALUES(22,'2026_02_15_234026_remove_notes_from_shipments',6);
INSERT INTO migrations VALUES(23,'2026_02_16_000109_create_notes_table',6);
INSERT INTO migrations VALUES(24,'2026_02_20_234230_make_rates_locations_nullable',7);
INSERT INTO migrations VALUES(25,'2026_02_21_190158_create_notifications_table',8);
INSERT INTO migrations VALUES(26,'2026_03_05_062433_alter_notifications_id_to_uuid',8);
INSERT INTO migrations VALUES(27,'2026_03_11_064502_alter_rates_to_add_name_and_type',8);
INSERT INTO migrations VALUES(28,'2026_03_11_072109_add_effective_dates_to_rates_table',8);
INSERT INTO migrations VALUES(30,'2026_03_13_210235_rename_email_to_emails_on_locations_and_change_to_json',9);
INSERT INTO migrations VALUES(31,'2026_03_13_201108_relax_short_code_unique_on_locations',10);
