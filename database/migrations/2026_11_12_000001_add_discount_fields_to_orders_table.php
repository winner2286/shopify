<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('discount_code_id')
                ->nullable()
                ->after('shipping_zone_id')
                ->constrained('discount_codes')
                ->nullOnDelete();
            $table->string('discount_code', 50)
                ->nullable()
                ->after('discount_code_id');
            $table->unsignedInteger('discount_amount')
                ->default(0)
                ->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn(['discount_code', 'discount_amount']);
        });
    }
};

