<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EmailController extends Controller
{

    /**
     * Check email DNS
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $order
     */
    public function checkDNS(Request $request)
    {
        $isValid = false;

        if ($email = $request->input('email')) {
            $domain = Arr::last(explode('@', $email));
            $isValid = checkdnsrr($domain, "MX") ? true : false;
        }

        return response()->json(['data' => ['is_valid'=>$isValid]]);
    }
}
