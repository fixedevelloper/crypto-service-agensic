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
    ) {
        $this->validator = $validator;
        $this->nowPaymentsService = $nowPaymentsService;
    }

    public function execute($userId, $quoteId, $address)
    {
        $quote = Quote::findOrFail($quoteId);
     
        // 1. Validations métier avant toute action SQL
        $this->validatePreConditions($quote, $address);
        // 1. On vérifie le solde disponible pour l'USDT (par exemple)
        $balanceInfo = $this->nowPaymentsService->getBalance($quote->crypto , $quote->network);

        logger($balanceInfo);
        if (!$balanceInfo['status'] || $balanceInfo['amount'] < $quote->amount_crypto) {
            throw new Exception("Solde insuffisant sur le compte marchand (Disponible: {$balanceInfo['amount']})");
        }

        // 2. TRÈS IMPORTANT : Vérifier aussi le gaz (ex: TRX ou BNB)
/*         $gasCurrency = ($quote->network === 'trc20') ? 'trx' : 'bsc';
        $gasBalance = $this->nowPaymentsService->getBalance($gasCurrency,);

        if ($gasBalance['amount'] <= 0) {
            throw new Exception("Alerte technique: Pas de Gaz ($gasCurrency) pour payer les frais de réseau.");
        } */
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
            // 3. Appel au service Blockchain
            $result = $this->nowPaymentsService->payout(
                $address,
                $quote->total_crypto,
                $quote->crypto.$quote->network
            );

            logger("Réponse Payout brute :", $result);

            // Extraction du premier retrait (withdrawal)
            $withdrawal = $result['withdrawals'][0] ?? null;

            if (!$withdrawal) {
                throw new Exception("Aucun retrait trouvé dans la réponse du fournisseur.");
            }

            // 4. Mise à jour de la transaction
            // Note : Le hash est NULL au début, on stocke l'ID du retrait en attendant l'IPN
            $transaction->update([
                'tx_hash' => $withdrawal['hash'] ?? null, // Sera NULL au début
                'provider_id' => $withdrawal['id'], // ID du retrait NOWPayments (ex: 5006250540)
                'status' => 'processing', // On reste en processing car le hash n'est pas encore généré
                'provider_response' => json_encode($result),
                'processed_at' => now()
            ]);

            $this->log($transaction->id, 'processing', 'Payout initié (Status: CREATING)', $result);

        } catch (Exception $e) {
            $transaction->update(['status' => 'failed']);
            $this->log($transaction->id, 'failed', $e->getMessage());
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

        if (!$this->validator->validate($quote->crypto, $address, $quote->network)) {
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
