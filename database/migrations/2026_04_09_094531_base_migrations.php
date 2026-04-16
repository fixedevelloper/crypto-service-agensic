<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crypto_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tether USD
            $table->string('code'); // USDT
            $table->string('network'); // TRON, ERC20
            $table->string('icon')->nullable(); // URL API
            $table->string('icon_res')->nullable(); // local (Android)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();

            // user externe
            $table->unsignedBigInteger('user_id')->index();

            $table->string('name');
            $table->string('phone')->nullable();

            $table->string('crypto');
            $table->string('network');

            $table->string('recipient_address');

            $table->timestamps();
        });
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            $table->uuid('reference')->unique();

            // user externe (optionnel)
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('crypto');
            $table->string('network');

            $table->decimal('amount_xaf', 15, 2);
            $table->decimal('rate', 18, 6);
            $table->decimal('amount_crypto', 18, 8);
            $table->decimal('fee', 18, 8);
            $table->decimal('total_crypto', 18, 8);

            $table->timestamp('expires_at');

            $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->uuid('reference')->unique();

            // user externe
            $table->unsignedBigInteger('user_id')->index();

            // relations internes OK
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();

            // INPUT
            $table->string('crypto');
            $table->string('network');
            $table->decimal('amount_xaf', 15, 2);

            // CALCUL
            $table->decimal('rate', 18, 6);
            $table->decimal('amount_crypto', 18, 8);
            $table->decimal('fee', 18, 8);
            $table->decimal('total_crypto', 18, 8);

            // DESTINATION
            $table->string('recipient_address');

            // BLOCKCHAIN
            $table->string('tx_hash')->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'success',
                'failed'
            ])->default('pending');

            $table->json('provider_response')->nullable();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });

        Schema::create('transfer_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();

            $table->enum('status', [
                'pending',
                'processing',
                'success',
                'failed'
            ]);

            $table->text('message')->nullable();
            $table->json('data')->nullable();

            $table->timestamps();
        });
        Schema::create('fee_configs', function (Blueprint $table) {
            $table->id();

            $table->string('crypto');
            $table->string('network');

            $table->decimal('percent_fee', 5, 2);
            $table->decimal('fixed_fee', 18, 8)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
