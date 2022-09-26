<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class MainController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return response()->json();
    }
}
