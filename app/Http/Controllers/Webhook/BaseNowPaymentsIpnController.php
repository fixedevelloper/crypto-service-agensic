<?php
namespace App\Http\Controllers\webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

abstract class BaseNowPaymentsIpnController extends Controller
{
    protected function validateSignature(Request $request)
    {
        $ipnSecret = config('services.nowpayments.ipn_secret');
        $receivedSignature = $request->header('x-nowpayments-sig');

        $payload = $request->all();
        ksort($payload);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $calculatedSignature = hash_hmac('sha512', $jsonPayload, $ipnSecret);

        if (!$receivedSignature || !hash_equals($calculatedSignature, $receivedSignature)) {
            Log::error("Signature IPN invalide");
            return false;
        }

        return true;
    }
}
