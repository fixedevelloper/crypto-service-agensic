<?php


namespace App\Http\Controllers;

use App\Http\Services\TransferService;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    protected $transferService;

    public function __construct(
        TransferService $transferService
    )
    {
        $this->transferService = $transferService;
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
            'data' => $transactions
        ]);
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'quote_id' => 'required|integer',
            'address' => 'required|string'
        ]);

        $userId = $request->header('X-User-Id');

        $transaction = $this->transferService->execute(
            $userId,
            $request->quote_id,
            $request->address
        );

        return response()->json([
            'status' => true,
            'data' => $transaction
        ]);
    }

}
