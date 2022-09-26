<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.key');
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $notification
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    public function show($notification)
    {
        if (!$data = Cache::tags('notifications')->get($notification)) {
            abort(404);
        }

        return response()->json($data);
    }
}
