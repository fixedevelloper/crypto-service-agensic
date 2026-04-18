<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Helpers;
use App\Http\Resources\BeneficiaryResource;
use App\Http\Services\microService\UserServiceClient;
use App\Http\Services\NowPaymentsService;
use Illuminate\Http\Request;
use App\Models\Beneficiary;

class BeneficiaryController extends Controller
{
    protected $userService;
    public function __construct(UserServiceClient $userServiceClient)
    {
        $this->userService=$userServiceClient;
    }
    /*
    |--------------------------------------------------------------------------
    | LIST USER BENEFICIARIES
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $userId = $request->header('X-User-Id');

        $beneficiaries = Beneficiary::where('user_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $beneficiaries
        ]);
    }
    public function indexAdmin(Request $request)
    {
        // 1. On récupère les bénéficiaires
        $beneficiaries = Beneficiary::latest()->paginate(20);

        // 2. On extrait les IDs utilisateurs uniques
        $userIds = $beneficiaries->pluck('user_id')->unique();

        // 3. Appel au microservice User (via un service HTTP interne)
        // On imagine que $this->userService->getUsers($ids) renvoie un tableau indexé par ID
        $users = $this->userService->getUsersByIds($userIds);

        // 4. On injecte les données dans la collection
        $beneficiaries->getCollection()->each(function ($b) use ($users) {
            $b->user_data = $users[$b->user_id] ?? null;
        });

        return Helpers::success(BeneficiaryResource::collection($beneficiaries));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE BENEFICIARY
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $userId = $request->header('X-User-Id');

        $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'crypto' => 'required|string',
            'network' => 'required|string',
            'recipient_address' => 'required|string'
        ]);

        $beneficiary = Beneficiary::create([
            'user_id' => $userId,
            'name' => $request->name,
            'phone' => $request->phone,
            'crypto' => $request->crypto,
            'network' => $request->network,
            'recipient_address' => $request->recipient_address,
        ]);

        return response()->json([
            'status' => true,
            'data' => $beneficiary
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW ONE BENEFICIARY
    |--------------------------------------------------------------------------
    */
    public function show(Request $request, $id)
    {
        $userId = $request->header('X-User-Id');

        $beneficiary = Beneficiary::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $beneficiary
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE BENEFICIARY
    |--------------------------------------------------------------------------
    */
    public function destroy(Request $request, $id)
    {
        $userId = $request->header('X-User-Id');

        $beneficiary = Beneficiary::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $beneficiary->delete();

        return response()->json([
            'status' => true,
            'message' => 'Beneficiary deleted'
        ]);
    }
}
