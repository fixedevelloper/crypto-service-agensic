<?php

namespace App\Http\Services;


use App\Models\Quote;
use App\Models\Transaction;
use App\Models\TransferLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class TransferService
{
    protected $validator;
    protected $nowPaymentsService;

    public function __construct(
        AddressValidatorService $validator,
        NowPaymentsService $nowPaymentsService
    )
    {
        $this->validator = $validator;
        $this->nowPaymentsService = $nowPaymentsService;
    }

    public function execute($userId, $quoteId, $address)
    {
        $quote = Quote::findOrFail($quoteId);

        // 1. Validations métier avant toute action SQL
        $this->validatePreConditions($quote, $address);

        // 2. Création de la transaction en base de données
        // On utilise une transaction DB pour s'assurer que l'enregistrement est bien créé
        $transaction = DB::transaction(function () use ($userId, $quote, $address) {
            return Transaction::create([
                'reference' => 'TX-' . strtoupper(Str::random(10)),
                'user_id' => $userId,
                'quote_id' => $quote->id,
                'crypto' => $quote->crypto,
                'network' => $quote->network,
                'amount_xaf' => $quote->amount_xaf,
                'rate' => $quote->rate,
                'amount_crypto' => $quote->amount_crypto,
                'fee' => $quote->fee,
                'total_crypto' => $quote->total_crypto,
                'recipient_address' => $address,
                'status' => 'processing'
            ]);
        });

        try {
            // 3. Appel au service Blockchain (Sortie de fonds)
            // Note: On ne met PAS cet appel dans DB::transaction pour éviter les locks longs
        /*    $result = $this->blockchain->send(
                $quote->crypto,
                $quote->network,
                $address,
                $quote->total_crypto
            );*/
            $result = $this->nowPaymentsService->payout(
                $address,
                $quote->total_crypto,
                $quote->network === 'TRON' ? 'USDTTRC20' : $quote->crypto
            );

            $transaction->update([
                'tx_hash' => $result['payouts'][0]['id'] ?? null,
                'status' => 'processing',
                'provider_response' => $result
            ]);
            logger($result);
            // 4. Mise à jour du succès
            $transaction->update([
                'tx_hash' => $result['tx_hash'],
                'status' => 'success',
                'provider_response' => json_encode($result['raw']),
                'processed_at' => now()
            ]);

            $this->log($transaction->id, 'success', 'Transaction envoyée avec succès', $result);

        } catch (Exception $e) {
            // 5. Gestion critique des erreurs
            $transaction->update(['status' => 'failed']);

            $this->log($transaction->id, 'failed', $e->getMessage());

            // On relance l'exception pour que le Controller puisse l'afficher à l'utilisateur
            throw new Exception("Le transfert a échoué : " . $e->getMessage());
        }

        return $transaction;
    }

    protected function validatePreConditions(Quote $quote, string $address): void
    {
        if ($quote->isExpired()) {
            throw new Exception("Le devis (quote) a expiré. Veuillez recommencer.");
        }

        if ($quote->status === 'used') {
            throw new Exception("Ce devis a déjà été utilisé pour une transaction.");
        }

        if (!$this->validator->validate($quote->crypto, $quote->network, $address)) {
            throw new Exception("L'adresse de destination est invalide pour ce réseau.");
        }
    }

    private function log($transactionId, $status, $message, $data = null)
    {
        TransferLog::create([
            'transaction_id' => $transactionId,
            'status' => $status,
            'message' => $message,
            'data' => $data ? json_encode($data) : null
        ]);
    }
}
