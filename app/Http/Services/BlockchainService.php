<?php


namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class BlockchainService
{
    public function send($crypto, $network, $address, $amount)
    {
        // 1. Convertir le montant en SUN (1 TRX = 1,000,000 SUN)
        // USDT utilise aussi 6 décimales sur TRON
        $sunAmount = (int) ($amount * 1000000);

        try {
            // --- ÉTAPE A : Créer la transaction (Non signée) ---
            $createResponse = Http::post('https://api.trongrid.io/wallet/createtransaction', [
                'to_address'   => $this->toHexAddress($address),
                'owner_address' => $this->toHexAddress(config('services.tron.wallet_address')),
                'amount'       => $sunAmount,
                'visible'      => false // On utilise l'hexadécimal pour plus de sécurité
            ]);

            if (!$createResponse->successful() || isset($createResponse['Error'])) {
                throw new \Exception("Erreur création Tron: " . ($createResponse['Error'] ?? 'Unknown'));
            }

            $transactionData = $createResponse->json();

            // --- ÉTAPE B : Signer la transaction ---
            // Note: Pour la signature, il est recommandé d'utiliser une librairie locale (ex: "iexbase/tron-php")
            // Ne jamais envoyer sa clé privée à une API distante !
            $signedTransaction = $this->signTransactionLocally($transactionData, config('services.tron.private_key'));

            // --- ÉTAPE C : Diffuser la transaction (Broadcast) ---
            $broadcastResponse = Http::post('https://api.trongrid.io/wallet/broadcasttransaction', $signedTransaction);

            if (!$broadcastResponse->successful() || !($broadcastResponse['result'] ?? false)) {
                throw new \Exception("Échec du broadcast Tron: " . json_encode($broadcastResponse->json()));
            }

            return [
                'status'  => 'success',
                'tx_hash' => $broadcastResponse['txid'],
                'raw'     => $broadcastResponse->json()
            ];

        } catch (\Exception $e) {
            logger()->error("Blockchain Send Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convertit une adresse Base58 (T...) en Hexadécimal
     */
    private function toHexAddress($address) {
        // Tu peux utiliser une librairie comme "iexbase/tron-php" pour ça
        // C'est nécessaire pour l'API /wallet/createtransaction
        return $address;
    }
}
