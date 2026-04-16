<?php


namespace App\Http\Services;


class AddressValidatorService
{
    /**
     * Valide une adresse crypto selon le réseau.
     * * @param string $crypto ex: BTC, USDT, ETH
     * @param string $network ex: BTC, TRON, ERC20
     * @param string $address
     * @return bool
     */
    public function validate($crypto, $network, $address)
    {
        $crypto = strtoupper($crypto);
        $network = strtoupper($network);

        // Si le network est "DEFAULT", on lui assigne le réseau natif
        if ($network === 'DEFAULT') {
            $network = match ($crypto) {
            'ETH'   => 'ERC20',
            'USDT'  => 'TRON', // ou ERC20 selon ton business
            'BTC'   => 'BTC',
            default => $network
        };
    }

        $key = "$crypto-$network";

        return match ($key) {
        'USDT-TRON' =>
            (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address),

        'BTC-BTC' =>
            (bool) preg_match('/^(1|3)[1-9A-HJ-NP-Za-km-z]{25,34}$|^(bc1)[a-z0-9]{39,59}$/i', $address),

        'ETH-ERC20' =>
            (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $address),

        default => false
    };
}
}
