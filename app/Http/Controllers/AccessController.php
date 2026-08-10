<?php

namespace App\Http\Controllers;

use App\Services\AccessControl\HandleAccessRequest;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function __construct(private readonly HandleAccessRequest $handleAccessRequest) {}

    public function validateCard(Request $request)
    {
        $result = $this->handleAccessRequest->validateCard(
            $request->input('card', ''),
            (string) $request->input('reader', ''),
            $request->ip(),
        );

        if ($result->isGranted()) {
            return response()->json(['message' => 'Valid'], 200);
        }

        return response()->json(['message' => 'Invalid'], 403);
    }

    public function doorbellPressed(Request $request)
    {
        $this->handleAccessRequest->recordDoorbellPress(
            (string) $request->input('reader', ''),
            $request->ip(),
        );

        return response()->json(['message' => 'Acknowledged'], 200);
    }
}
