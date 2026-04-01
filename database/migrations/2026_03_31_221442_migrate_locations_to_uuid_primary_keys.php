<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            DB::statement('DROP TABLE IF EXISTS "__temp__locations"');
            DB::statement('DROP TABLE IF EXISTS "__temp__shipments"');
            DB::statement('DROP TABLE IF EXISTS "__temp__rates"');
            DB::statement('DROP TABLE IF EXISTS "__temp__trailers"');
            DB::statement('DROP TABLE IF EXISTS "__temp__location_distances"');

            $tempTables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '__temp__%'");

            foreach ($tempTables as $tempTable) {
                if (isset($tempTable->name) && Schema::hasTable($tempTable->name)) {
                    Schema::drop($tempTable->name);
                }
            }
        }

        Schema::disableForeignKeyConstraints();

        Schema::table('locations', function (Blueprint $table) {
            if (! Schema::hasColumn('locations', 'legacy_id')) {
                $table->unsignedBigInteger('legacy_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('locations', 'id_uuid')) {
                $table->uuid('id_uuid')->nullable()->after('legacy_id');
            }
        });

        // Ensure all locations have a guid before backfill
        $locationsWithoutGuid = DB::table('locations')->whereNull('guid')->get(['id']);

        foreach ($locationsWithoutGuid as $loc) {
            DB::table('locations')->where('id', $loc->id)->update(['guid' => (string) \Illuminate\Support\Str::uuid()]);
        }

        DB::statement('UPDATE locations SET legacy_id = id, id_uuid = guid');

        if ($isSqlite) {
            // Update all FK columns in related tables: integer → UUID string
            DB::statement('UPDATE shipments SET pickup_location_id = (SELECT id_uuid FROM locations WHERE locations.legacy_id = shipments.pickup_location_id LIMIT 1) WHERE pickup_location_id IS NOT NULL AND pickup_location_id IN (SELECT legacy_id FROM locations)');
            DB::statement('UPDATE shipments SET dc_location_id = (SELECT id_uuid FROM locations WHERE locations.legacy_id = shipments.dc_location_id LIMIT 1) WHERE dc_location_id IS NOT NULL AND dc_location_id IN (SELECT legacy_id FROM locations)');
            DB::statement('UPDATE rates SET pickup_location_id = (SELECT id_uuid FROM locations WHERE locations.legacy_id = rates.pickup_location_id LIMIT 1) WHERE pickup_location_id IS NOT NULL AND pickup_location_id IN (SELECT legacy_id FROM locations)');
            DB::statement('UPDATE trailers SET current_location_id = (SELECT id_uuid FROM locations WHERE locations.legacy_id = trailers.current_location_id LIMIT 1) WHERE current_location_id IS NOT NULL AND current_location_id IN (SELECT legacy_id FROM locations)');
            DB::statement('UPDATE location_distances SET from_location_id = (SELECT id_uuid FROM locations WHERE locations.legacy_id = location_distances.from_location_id LIMIT 1) WHERE from_location_id IS NOT NULL AND from_location_id IN (SELECT legacy_id FROM locations)');
            DB::statement('UPDATE location_distances SET to_location_id = (SELECT id_uuid FROM locations WHERE locations.legacy_id = location_distances.to_location_id LIMIT 1) WHERE to_location_id IS NOT NULL AND to_location_id IN (SELECT legacy_id FROM locations)');
            // Self-referential: recycling_location_id on locations itself
            DB::statement('UPDATE locations SET recycling_location_id = (SELECT id_uuid FROM locations AS l2 WHERE l2.legacy_id = locations.recycling_location_id LIMIT 1) WHERE recycling_location_id IS NOT NULL AND recycling_location_id IN (SELECT legacy_id FROM locations)');

            if (Schema::hasTable('locations_uuid')) {
                Schema::drop('locations_uuid');
            }

            Schema::create('locations_uuid', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('guid')->unique()->index();
                $table->string('short_code', 20);
                $table->string('name')->nullable();
                $table->text('address');
                $table->string('city')->nullable();
                $table->string('state', 2)->nullable();
                $table->string('zip', 10)->nullable();
                $table->string('country', 2)->default('US');
                $table->enum('type', ['pickup', 'distribution_center', 'recycling'])->default('pickup');
                $table->uuid('recycling_location_id')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->text('emails')->nullable();
                $table->datetime('expected_arrival_time')->nullable();
            });

            DB::statement('INSERT INTO locations_uuid (id, guid, short_code, name, address, city, state, zip, country, type, recycling_location_id, latitude, longitude, is_active, created_at, updated_at, emails, expected_arrival_time) SELECT id_uuid, guid, short_code, name, address, city, state, zip, country, type, recycling_location_id, latitude, longitude, is_active, created_at, updated_at, emails, expected_arrival_time FROM locations');

            Schema::drop('locations');
            Schema::rename('locations_uuid', 'locations');

            Schema::enableForeignKeyConstraints();

            return;
        }

        // ── Non-SQLite (MySQL/Postgres) path ────────────────────────────────────────

        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'pickup_location_uuid')) {
                $table->uuid('pickup_location_uuid')->nullable()->after('pickup_location_id');
            }

            if (! Schema::hasColumn('shipments', 'dc_location_uuid')) {
                $table->uuid('dc_location_uuid')->nullable()->after('dc_location_id');
            }
        });

        Schema::table('rates', function (Blueprint $table) {
            if (! Schema::hasColumn('rates', 'pickup_location_uuid')) {
                $table->uuid('pickup_location_uuid')->nullable()->after('pickup_location_id');
            }
        });

        Schema::table('trailers', function (Blueprint $table) {
            if (! Schema::hasColumn('trailers', 'current_location_uuid')) {
                $table->uuid('current_location_uuid')->nullable()->after('current_location_id');
            }
        });

        Schema::table('location_distances', function (Blueprint $table) {
            if (! Schema::hasColumn('location_distances', 'from_location_uuid')) {
                $table->uuid('from_location_uuid')->nullable()->after('from_location_id');
            }

            if (! Schema::hasColumn('location_distances', 'to_location_uuid')) {
                $table->uuid('to_location_uuid')->nullable()->after('to_location_id');
            }
        });

        Schema::table('locations', function (Blueprint $table) {
            if (! Schema::hasColumn('locations', 'recycling_location_uuid')) {
                $table->uuid('recycling_location_uuid')->nullable()->after('recycling_location_id');
            }
        });

        // Backfill UUID FK values
        DB::statement('UPDATE shipments SET pickup_location_uuid = (SELECT id_uuid FROM locations WHERE locations.legacy_id = shipments.pickup_location_id LIMIT 1) WHERE pickup_location_id IS NOT NULL');
        DB::statement('UPDATE shipments SET dc_location_uuid = (SELECT id_uuid FROM locations WHERE locations.legacy_id = shipments.dc_location_id LIMIT 1) WHERE dc_location_id IS NOT NULL');
        DB::statement('UPDATE rates SET pickup_location_uuid = (SELECT id_uuid FROM locations WHERE locations.legacy_id = rates.pickup_location_id LIMIT 1) WHERE pickup_location_id IS NOT NULL');
        DB::statement('UPDATE trailers SET current_location_uuid = (SELECT id_uuid FROM locations WHERE locations.legacy_id = trailers.current_location_id LIMIT 1) WHERE current_location_id IS NOT NULL');
        DB::statement('UPDATE location_distances SET from_location_uuid = (SELECT id_uuid FROM locations WHERE locations.legacy_id = location_distances.from_location_id LIMIT 1) WHERE from_location_id IS NOT NULL');
        DB::statement('UPDATE location_distances SET to_location_uuid = (SELECT id_uuid FROM locations WHERE locations.legacy_id = location_distances.to_location_id LIMIT 1) WHERE to_location_id IS NOT NULL');
        DB::statement('UPDATE locations SET recycling_location_uuid = (SELECT l2.id_uuid FROM locations AS l2 WHERE l2.legacy_id = locations.recycling_location_id LIMIT 1) WHERE recycling_location_id IS NOT NULL');

        // Drop foreign keys
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['pickup_location_id']);
            $table->dropForeign(['dc_location_id']);
        });

        Schema::table('rates', function (Blueprint $table) {
            if (Schema::hasIndex('rates', 'rates_unique_lane')) {
                $table->dropUnique('rates_unique_lane');
            }

            $table->dropForeign(['pickup_location_id']);
        });

        Schema::table('trailers', function (Blueprint $table) {
            $table->dropForeign(['current_location_id']);
        });

        Schema::table('location_distances', function (Blueprint $table) {
            $table->dropForeign(['from_location_id']);
            $table->dropForeign(['to_location_id']);

            if (Schema::hasIndex('location_distances', 'location_distances_from_location_id_to_location_id_unique')) {
                $table->dropUnique('location_distances_from_location_id_to_location_id_unique');
            }
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['recycling_location_id']);
        });

        // Drop old integer FK columns
        if (Schema::hasColumn('shipments', 'pickup_location_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('pickup_location_id');
            });
        }

        if (Schema::hasColumn('shipments', 'dc_location_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('dc_location_id');
            });
        }

        if (Schema::hasColumn('rates', 'pickup_location_id')) {
            Schema::table('rates', function (Blueprint $table) {
                $table->dropColumn('pickup_location_id');
            });
        }

        if (Schema::hasColumn('trailers', 'current_location_id')) {
            Schema::table('trailers', function (Blueprint $table) {
                $table->dropColumn('current_location_id');
            });
        }

        if (Schema::hasColumn('location_distances', 'from_location_id')) {
            Schema::table('location_distances', function (Blueprint $table) {
                $table->dropColumn('from_location_id');
            });
        }

        if (Schema::hasColumn('location_distances', 'to_location_id')) {
            Schema::table('location_distances', function (Blueprint $table) {
                $table->dropColumn('to_location_id');
            });
        }

        if (Schema::hasColumn('locations', 'recycling_location_id')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropColumn('recycling_location_id');
            });
        }

        // Rename UUID columns to original names
        if (Schema::hasColumn('shipments', 'pickup_location_uuid') && ! Schema::hasColumn('shipments', 'pickup_location_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->renameColumn('pickup_location_uuid', 'pickup_location_id');
            });
        }

        if (Schema::hasColumn('shipments', 'dc_location_uuid') && ! Schema::hasColumn('shipments', 'dc_location_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->renameColumn('dc_location_uuid', 'dc_location_id');
            });
        }

        if (Schema::hasColumn('rates', 'pickup_location_uuid') && ! Schema::hasColumn('rates', 'pickup_location_id')) {
            Schema::table('rates', function (Blueprint $table) {
                $table->renameColumn('pickup_location_uuid', 'pickup_location_id');
            });
        }

        if (Schema::hasColumn('trailers', 'current_location_uuid') && ! Schema::hasColumn('trailers', 'current_location_id')) {
            Schema::table('trailers', function (Blueprint $table) {
                $table->renameColumn('current_location_uuid', 'current_location_id');
            });
        }

        if (Schema::hasColumn('location_distances', 'from_location_uuid') && ! Schema::hasColumn('location_distances', 'from_location_id')) {
            Schema::table('location_distances', function (Blueprint $table) {
                $table->renameColumn('from_location_uuid', 'from_location_id');
            });
        }

        if (Schema::hasColumn('location_distances', 'to_location_uuid') && ! Schema::hasColumn('location_distances', 'to_location_id')) {
            Schema::table('location_distances', function (Blueprint $table) {
                $table->renameColumn('to_location_uuid', 'to_location_id');
            });
        }

        if (Schema::hasColumn('locations', 'recycling_location_uuid') && ! Schema::hasColumn('locations', 'recycling_location_id')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->renameColumn('recycling_location_uuid', 'recycling_location_id');
            });
        }

        // Rebuild the locations table with UUID primary key
        if (! Schema::hasTable('locations_uuid')) {
            Schema::create('locations_uuid', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('guid')->unique()->index();
                $table->string('short_code', 20);
                $table->string('name')->nullable();
                $table->text('address');
                $table->string('city')->nullable();
                $table->string('state', 2)->nullable();
                $table->string('zip', 10)->nullable();
                $table->string('country', 2)->default('US');
                $table->enum('type', ['pickup', 'distribution_center', 'recycling'])->default('pickup');
                $table->uuid('recycling_location_id')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->text('emails')->nullable();
                $table->datetime('expected_arrival_time')->nullable();
            });
        }

        DB::statement('DELETE FROM locations_uuid');
        DB::statement('INSERT INTO locations_uuid (id, guid, short_code, name, address, city, state, zip, country, type, recycling_location_id, latitude, longitude, is_active, created_at, updated_at, emails, expected_arrival_time) SELECT id_uuid, guid, short_code, name, address, city, state, zip, country, type, recycling_location_id, latitude, longitude, is_active, created_at, updated_at, emails, expected_arrival_time FROM locations');

        if (Schema::hasTable('locations') && Schema::hasTable('locations_uuid')) {
            Schema::drop('locations');
            Schema::rename('locations_uuid', 'locations');
        }

        // Restore FK constraints
        Schema::table('locations', function (Blueprint $table) {
            $table->foreign('recycling_location_id')->references('id')->on('locations')->nullOnDelete();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreign('pickup_location_id')->references('id')->on('locations')->restrictOnDelete();
            $table->foreign('dc_location_id')->references('id')->on('locations')->nullOnDelete();
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->foreign('pickup_location_id')->references('id')->on('locations')->nullOnDelete();
            $table->unique(
                ['name', 'carrier_id', 'pickup_location_id', 'destination_city', 'destination_state', 'destination_country'],
                'rates_unique_lane'
            );
        });

        Schema::table('trailers', function (Blueprint $table) {
            $table->foreign('current_location_id')->references('id')->on('locations')->nullOnDelete();
        });

        Schema::table('location_distances', function (Blueprint $table) {
            $table->foreign('from_location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('to_location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->unique(['from_location_id', 'to_location_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        throw new \RuntimeException('This migration is not safely reversible.');
    }
};
