<?php


namespace App\Http\Controllers;

use App\Http\Helpers\Helpers;
use App\Http\Resources\PaymentResource;
use App\Http\Services\microService\UserServiceClient;
use App\Http\Services\NowPaymentsService;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $nowPayments;
    protected $userService;

    public function __construct(NowPaymentsService $nowPayments,UserServiceClient $userServiceClient)
    {
        $this->nowPayments = $nowPayments;
        $this->userService=$userServiceClient;
    }



    public function index(Request $request)
    {
        // 1. Récupérer les paiements normalement
        $payments = Payment::latest()->paginate(15);

        // 2. Extraire tous les IDs utilisateurs uniques de la page actuelle
        $userIds = $payments->pluck('user_id')->unique()->toArray();

        // 3. Appeler le Microservice User (via HTTP ou gRPC)
        // On récupère une liste d'utilisateurs indexée par leur ID
        $usersFromServer = $this->userService->getUsersByIds($userIds);

        // 4. Injecter les données dans chaque modèle de la collection
        $payments->getCollection()->transform(function ($payment) use ($usersFromServer) {
            $payment->user_data = $usersFromServer[$payment->user_id] ?? null;
            return $payment;
        });

        return Helpers::success(PaymentResource::collection($payments));
    }


    public function show($id)
    {
        // 1. Récupérer le paiement dans la BDD locale
        $payment = Payment::find($id);

        if (!$payment) {
            return Helpers::error("Paiement introuvable", 404);
        }

        try {
            // 2. Appel au microservice User pour récupérer le profil complet
            // On suppose que vous avez un service 'UserServiceClient'
            $userData = $this->userService->getUserById($payment->user_id);

            // 3. Injecter dynamiquement la donnée dans le modèle avant de le passer à la Resource
            $payment->user_data = $userData;

        } catch (\Exception $e) {
            // En cas d'échec du microservice User, on laisse user_data à null
            // La Resource gérera le fallback (le mode dégradé)
            Log::error("Impossible de joindre le service User: " . $e->getMessage());
        }

        return Helpers::success(new PaymentResource($payment));
    }
    /**
     * Initie un nouveau dépôt (Achat de crypto)
     * @param Request $request
     * @return JsonResponse
     */
    public function deposit(Request $request)
    {
        logger($request->all());

        // 1. Validation dynamique
        $validator = Validator::make($request->all(), [
            'amount'            => 'required|numeric|min:1',
            'currency'          => 'required|string|size:3', // ex: XAF, XOF, USD, NGN
            //'recipient_address' => 'required|string',
            'network'           => 'required|string', // trc20, erc20, etc.
            'crypto'            => 'required|string',  // usdt, trx, etc.
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        try {
            $userId = $request->header('X-User-Id');
            $currency = strtoupper($request->currency);
            $payCurrency = strtolower($request->crypto . $request->network);

            // 2. Validation de l'adresse (Regex par réseau)
           // $this->validateCryptoAddress($request->network, $request->recipient_address);

            // 3. Référence Unique
            $reference = 'PAY-' . strtoupper(Str::random(10));

            // 4. Appel au Service avec conversion automatique
            $paymentData = $this->nowPayments->createPayment(
                $request->amount,
                $currency,    // Dynamique : XAF, XOF...
                $payCurrency, // Dynamique : usdttrc20...
                $reference
            );
            if ($paymentData['payment_status']=='failed'){
                return Helpers::error($paymentData['message']);
            }

            // 5. Enregistrement
            $payment = Payment::create([
                'user_id'           => $userId,
                'reference'         => $reference,
                'provider_id'       => $paymentData['payment_id'] ?? null,
                'fiat_amount'        => $request->amount, // On peut renommer la colonne en 'amount_fiat'
                'crypto_currency'   => $payCurrency,
                'fiat_currency'   => $currency,
                'crypto_amount'     => $paymentData['pay_amount'] ?? null,
                'pay_address'       => $paymentData['pay_address'] ?? null,
                'recipient_address' => $paymentData['pay_address'] ?? null,
                'status'            => $paymentData['payment_status'] ?? 'waiting',
                'provider_response' => $paymentData,
            ]);

            return response()->json(['success' => true, 'data' => $payment], 201);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Validation basique des adresses par réseau
     */
    private function validateCryptoAddress($network, $address)
    {
        $patterns = [
            'trc20' => '/^T[A-Za-z1-9]{33}$/',         // Tron (Commence par T)
            'erc20' => '/^0x[a-fA-F0-9]{40}$/',       // Ethereum (Commence par 0x)
            'bep20' => '/^0x[a-fA-F0-9]{40}$/',       // Binance Smart Chain
        ];

        $net = strtolower($network);
        if (isset($patterns[$net]) && !preg_match($patterns[$net], $address)) {
            throw new Exception("L'adresse fournie est invalide pour le réseau " . strtoupper($network));
        }
    }

    /**
     * Vérifie le statut actuel d'un paiement (pour le polling Android)
     */
    public function checkStatus($reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        return response()->json([
            'reference' => $payment->reference,
            'status'    => $payment->status, // waiting, confirming, finished, etc.
            'is_success' => $payment->isSuccess(),
        ]);
    }
}
