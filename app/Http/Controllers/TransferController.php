<?php


namespace App\Http\Controllers;

use App\Http\Helpers\Helpers;
use App\Http\Resources\TransactionResource;
use App\Http\Services\microService\UserServiceClient;
use App\Http\Services\TransferService;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransferController extends Controller
{
    protected $transferService;
    protected $userService;
    public function __construct(
        TransferService $transferService,UserServiceClient $userServiceClient
    )
    {
        $this->transferService = $transferService;
        $this->userService=$userServiceClient;
    }
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $crypto = $request->query('crypto');

        // 1. Requête de base avec chargement du bénéficiaire local
        $query = Transaction::with(['beneficiary'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($crypto, fn($q) => $q->where('crypto', strtolower($crypto)))
            ->when($search, function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('recipient_address', 'like', "%{$search}%")
                    ->orWhere('tx_hash', 'like', "%{$search}%");
            });

        $transactions = $query->latest()->paginate($request->query('per_page', 15));

        // 2. Hydratation des données utilisateurs (Microservice User)
        $userIds = $transactions->pluck('user_id')->unique()->filter()->toArray();

        if (!empty($userIds)) {
            // On récupère les users via un appel HTTP bulk
            $usersFromServer = $this->userService->getUsersByIds($userIds);

            // On injecte les données dans chaque transaction
            $transactions->getCollection()->transform(function ($transaction) use ($usersFromServer) {
                $transaction->user_data = $usersFromServer[$transaction->user_id] ?? null;
                return $transaction;
            });
        }

        // 3. Retourne la collection via la Resource
        return Helpers::success([
            'items' => TransactionResource::collection($transactions),
            'pagination' => [
                'total'        => $transactions->total(),
                'current_page' => $transactions->currentPage(),
                'per_page'     => $transactions->perPage(),
                'last_page'    => $transactions->lastPage(),
            ]
        ]);
    }

    /**
     * Affiche les détails d'une transaction de sortie (XAF vers Crypto).
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // 1. Récupération de la transaction avec ses relations locales
        // On charge le bénéficiaire car il appartient au même microservice
        $transaction = Transaction::with(['beneficiary', 'quote'])->find($id);

        if (!$transaction) {
            return Helpers::error("Transaction introuvable", 404);
        }

        try {
            // 2. Hydratation Cross-Service : Récupérer l'émetteur (User)
            // On interroge le microservice User via le client HTTP interne
            $userData = $this->userService->getUserById($transaction->user_id);

            // On injecte les données pour que la Resource puisse les traiter
            $transaction->user_data = $userData;

        } catch (\Exception $e) {
            // En cas d'erreur du service User, on logue mais on ne bloque pas l'affichage
            Log::error("Service User inaccessible pour la transaction {$id}: " . $e->getMessage());
        }

        // 3. Retour via la TransactionResource
        return Helpers::success(new TransactionResource($transaction));
    }

    public function userTransactions(Request $request)
    {
        $userId = $request->header('X-User-Id');

        $query = Transaction::where('user_id', $userId);

        // 🔎 filtre status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 🔎 filtre crypto
        if ($request->has('crypto')) {
            $query->where('crypto', $request->crypto);
        }

        $transactions = $query
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => TransactionResource::collection($transactions)
        ]);
    }

public function transfer(Request $request)
{
    try {
        // 1. Validation des entrées
        $validated = $request->validate([
            'quote_id' => 'required|integer',
            'address'  => 'required|string'
        ]);

        // 2. Récupération de l'ID utilisateur (vérifie qu'il existe)
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json([
                'status'  => false,
                'message' => 'Identifiant utilisateur manquant dans les en-têtes (X-User-Id).'
            ], 401);
        }

    
        // 3. Exécution du transfert via le service
        $transaction = $this->transferService->execute(
            $userId,
            $request->quote_id,
            $request->address
        );

        return Helpers::success(new TransactionResource($transaction));

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Erreur si les champs envoyés par le mobile sont incorrects
        return Helpers::error('Données invalides');
        

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        // Erreur si le quote_id n'existe pas dans la base de données

          return Helpers::error('La cotation (Quote) demandée est introuvable ou a expiré.');

    } catch (\Exception $e) {
        // Capture toutes les autres erreurs (Solde insuffisant, API crypto hors ligne, etc.)
        // On log l'erreur pour le développeur
        \Log::error("Erreur de transfert : " . $e->getMessage(), [
            'userId' => $request->header('X-User-Id'),
            'quote_id' => $request->quote_id
        ]);


         return Helpers::error($e->getMessage());
    }
}

}
