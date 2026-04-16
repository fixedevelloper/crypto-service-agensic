<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $blueprint) {
            $blueprint->id();
            // user externe
            $blueprint->unsignedBigInteger('user_id')->index();

// Référence interne (ex: ORDER-ABCDE)
            $blueprint->string('reference')->unique();

// Identifiant unique renvoyé par NOWPayments (payment_id ou id)
            $blueprint->string('provider_id')->nullable()->index();

// Informations financières
            $blueprint->decimal('fiat_amount', 15, 2); // Montant payé par l'utilisateur
            $blueprint->string('fiat_currency')->default('xaf');
            $blueprint->string('crypto_currency')->default('usdttrc20'); // La crypto qu'il reçoit
            $blueprint->decimal('crypto_amount', 18, 8)->nullable(); // Montant exact en crypto

// Coordonnées de paiement
            $blueprint->string('pay_address')->nullable(); // L'adresse générée par NOWPayments
            $blueprint->string('recipient_address'); // L'adresse du client (pour le payout final)

// Statuts : 'waiting', 'confirming', 'finished', 'failed', 'expired'
            $blueprint->string('status')->default('waiting');

// Stockage de la réponse brute pour audit
            $blueprint->json('provider_response')->nullable();

            $blueprint->timestamp('processed_at')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
