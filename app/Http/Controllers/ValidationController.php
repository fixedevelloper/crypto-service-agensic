<?php


namespace App\Http\Controllers;

use App\Http\Helpers\Helpers;
use App\Http\Services\AddressValidatorService;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    protected  $validator;
    public function __construct(
         AddressValidatorService $validator
    ) {
        $this->validator=$validator;
    }

public function validate(Request $request)
{
    $request->validate([
        'crypto' => 'required|string',
        'network' => 'required|string',
        'address' => 'required|string'
    ]);

    $isValid = $this->validator->validate(
        $request->crypto,
        $request->network,
        $request->address
    );

    return Helpers::success([
        'valid' => $isValid
    ]);
}
}
