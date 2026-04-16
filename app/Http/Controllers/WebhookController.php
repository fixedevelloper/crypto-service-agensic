<?php


namespace App\Http\Controllers;

use App\Services\WebhookService;
use Illuminate\Http\Request;


class WebhookController extends Controller
{
    protected  $webhookService;
    public function __construct(
        WebhookService $webhookService
    ) {
        $this->webhookService=$webhookService;
}

public function handle(Request $request)
{
    $this->webhookService->handle($request);

    return response()->json(['status' => 'ok']);
}
}
