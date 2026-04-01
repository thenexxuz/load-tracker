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
            DB::statement('DROP TABLE IF EXISTS "__temp__shipments"');
            DB::statement('DROP TABLE IF EXISTS "__temp__rates"');
            DB::statement('DROP TABLE IF EXISTS "__temp__trailers"');
            DB::statement('DROP TABLE IF EXISTS "__temp__users"');
            DB::statement('DROP TABLE IF EXISTS "__temp__carrier_shipment_offers"');

            $tempTables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '__temp__%'");

            foreach ($tempTables as $tempTable) {
                if (isset($tempTable->name) && Schema::hasTable($tempTable->name)) {
                    Schema::drop($tempTable->name);
                }
            }
        }

        Schema::disableForeignKeyConstraints();

        Schema::table('carriers', function (Blueprint $table) {
            if (! Schema::hasColumn('carriers', 'legacy_id')) {
                $table->unsignedBigInteger('legacy_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('carriers', 'id_uuid')) {
                $table->uuid('id_uuid')->nullable()->after('legacy_id');
            }
        });

        DB::statement('UPDATE carriers SET legacy_id = id, id_uuid = guid');

        if ($isSqlite) {
            DB::statement('UPDATE shipments SET carrier_id = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = shipments.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL AND carrier_id IN (SELECT legacy_id FROM carriers)');
            DB::statement('UPDATE rates SET carrier_id = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = rates.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL AND carrier_id IN (SELECT legacy_id FROM carriers)');
            DB::statement('UPDATE trailers SET carrier_id = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = trailers.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL AND carrier_id IN (SELECT legacy_id FROM carriers)');
            DB::statement('UPDATE users SET carrier_id = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = users.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL AND carrier_id IN (SELECT legacy_id FROM carriers)');
            DB::statement('UPDATE carrier_shipment_offers SET carrier_id = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = carrier_shipment_offers.carrier_id LIMIT 1) WHERE carrier_id IN (SELECT legacy_id FROM carriers)');

            if (Schema::hasTable('carriers_uuid')) {
                Schema::drop('carriers_uuid');
            }

            Schema::create('carriers_uuid', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('guid')->unique()->index();
                $table->string('short_code', 50)->unique()->index();
                $table->string('wt_code', 50)->nullable()->index();
                $table->string('name', 255);
                $table->text('emails')->nullable()->comment('Semicolon-separated list of email addresses');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::statement('INSERT INTO carriers_uuid (id, guid, short_code, wt_code, name, emails, is_active, created_at, updated_at) SELECT id_uuid, guid, short_code, wt_code, name, emails, is_active, created_at, updated_at FROM carriers');

            Schema::drop('carriers');
            Schema::rename('carriers_uuid', 'carriers');

            Schema::enableForeignKeyConstraints();

            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'carrier_uuid')) {
                $table->uuid('carrier_uuid')->nullable()->after('carrier_id');
            }
        });

        Schema::table('rates', function (Blueprint $table) {
            if (! Schema::hasColumn('rates', 'carrier_uuid')) {
                $table->uuid('carrier_uuid')->nullable()->after('carrier_id');
            }
        });

        Schema::table('trailers', function (Blueprint $table) {
            if (! Schema::hasColumn('trailers', 'carrier_uuid')) {
                $table->uuid('carrier_uuid')->nullable()->after('carrier_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'carrier_uuid')) {
                $table->uuid('carrier_uuid')->nullable()->after('carrier_id');
            }
        });

        Schema::table('carrier_shipment_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('carrier_shipment_offers', 'carrier_uuid')) {
                $table->uuid('carrier_uuid')->nullable()->after('carrier_id');
            }
        });

        DB::statement('UPDATE shipments SET carrier_uuid = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = shipments.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL');
        DB::statement('UPDATE rates SET carrier_uuid = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = rates.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL');
        DB::statement('UPDATE trailers SET carrier_uuid = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = trailers.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL');
        DB::statement('UPDATE users SET carrier_uuid = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = users.carrier_id LIMIT 1) WHERE carrier_id IS NOT NULL');
        DB::statement('UPDATE carrier_shipment_offers SET carrier_uuid = (SELECT id_uuid FROM carriers WHERE carriers.legacy_id = carrier_shipment_offers.carrier_id LIMIT 1)');

        if (! $isSqlite) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropForeign(['carrier_id']);
            });
        }

        Schema::table('rates', function (Blueprint $table) use ($isSqlite) {
            if (Schema::hasIndex('rates', 'rates_unique_lane')) {
                $table->dropUnique('rates_unique_lane');
            }

            if (! $isSqlite) {
                $table->dropForeign(['carrier_id']);
            }
        });

        Schema::table('trailers', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['carrier_id']);
            }

            if (Schema::hasIndex('trailers', 'trailers_carrier_id_index')) {
                $table->dropIndex('trailers_carrier_id_index');
            }
        });

        if (! $isSqlite) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['carrier_id']);
            });
        }

        Schema::table('carrier_shipment_offers', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['carrier_id']);
            }

            if (Schema::hasIndex('carrier_shipment_offers', 'carrier_shipment_offers_shipment_id_carrier_id_unique')) {
                $table->dropUnique('carrier_shipment_offers_shipment_id_carrier_id_unique');
            }
        });

        if (Schema::hasColumn('shipments', 'carrier_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('carrier_id');
            });
        }

        if (Schema::hasColumn('rates', 'carrier_id')) {
            Schema::table('rates', function (Blueprint $table) {
                $table->dropColumn('carrier_id');
            });
        }

        if (Schema::hasColumn('trailers', 'carrier_id')) {
            Schema::table('trailers', function (Blueprint $table) {
                $table->dropColumn('carrier_id');
            });
        }

        if (Schema::hasColumn('users', 'carrier_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('carrier_id');
            });
        }

        if (Schema::hasColumn('carrier_shipment_offers', 'carrier_id')) {
            Schema::table('carrier_shipment_offers', function (Blueprint $table) {
                $table->dropColumn('carrier_id');
            });
        }

        if (Schema::hasColumn('shipments', 'carrier_uuid') && ! Schema::hasColumn('shipments', 'carrier_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->renameColumn('carrier_uuid', 'carrier_id');
            });
        }

        if (Schema::hasColumn('rates', 'carrier_uuid') && ! Schema::hasColumn('rates', 'carrier_id')) {
            Schema::table('rates', function (Blueprint $table) {
                $table->renameColumn('carrier_uuid', 'carrier_id');
            });
        }

        if (Schema::hasColumn('trailers', 'carrier_uuid') && ! Schema::hasColumn('trailers', 'carrier_id')) {
            Schema::table('trailers', function (Blueprint $table) {
                $table->renameColumn('carrier_uuid', 'carrier_id');
            });
        }

        if (Schema::hasColumn('users', 'carrier_uuid') && ! Schema::hasColumn('users', 'carrier_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('carrier_uuid', 'carrier_id');
            });
        }

        if (Schema::hasColumn('carrier_shipment_offers', 'carrier_uuid') && ! Schema::hasColumn('carrier_shipment_offers', 'carrier_id')) {
            Schema::table('carrier_shipment_offers', function (Blueprint $table) {
                $table->renameColumn('carrier_uuid', 'carrier_id');
            });
        }

        if (! Schema::hasTable('carriers_uuid')) {
            Schema::create('carriers_uuid', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('guid')->unique()->index();
                $table->string('short_code', 50)->unique()->index();
                $table->string('wt_code', 50)->nullable()->index();
                $table->string('name', 255);
                $table->text('emails')->nullable()->comment('Semicolon-separated list of email addresses');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        DB::statement('DELETE FROM carriers_uuid');
        DB::statement('INSERT INTO carriers_uuid (id, guid, short_code, wt_code, name, emails, is_active, created_at, updated_at) SELECT id_uuid, guid, short_code, wt_code, name, emails, is_active, created_at, updated_at FROM carriers');

        if (Schema::hasTable('carriers') && Schema::hasTable('carriers_uuid')) {
            Schema::drop('carriers');
            Schema::rename('carriers_uuid', 'carriers');
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->nullOnDelete();
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->nullOnDelete();
            $table->unique(
                ['name', 'carrier_id', 'pickup_location_id', 'destination_city', 'destination_state', 'destination_country'],
                'rates_unique_lane'
            );
        });

        Schema::table('trailers', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->restrictOnDelete();
            $table->index('carrier_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->nullOnDelete();
        });

        Schema::table('carrier_shipment_offers', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->cascadeOnDelete();
            $table->unique(['shipment_id', 'carrier_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        throw new RuntimeException('This migration is not safely reversible.');
    }
};
