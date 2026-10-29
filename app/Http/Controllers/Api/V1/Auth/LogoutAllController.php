<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutAllController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return response()->noContent();
    }
}
