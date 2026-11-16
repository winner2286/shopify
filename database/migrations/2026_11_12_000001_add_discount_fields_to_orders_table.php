<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'discount_code_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('discount_code_id')
                    ->nullable()
                    ->after('shipping_zone_id')
                    ->constrained('discount_codes')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'discount_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('discount_code', 50)
                    ->nullable()
                    ->after('discount_code_id');
            });
        }

        if (! Schema::hasColumn('orders', 'discount_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('discount_amount')
                    ->default(0)
                    ->after('subtotal');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'discount_code_id')) {
                $table->dropConstrainedForeignId('discount_code_id');
            }
            if (Schema::hasColumn('orders', 'discount_code')) {
                $table->dropColumn('discount_code');
            }
            if (Schema::hasColumn('orders', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });
    }
};

