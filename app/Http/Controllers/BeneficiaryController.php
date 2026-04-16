<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Beneficiary;

class BeneficiaryController extends Controller
{
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
