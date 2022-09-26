<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\ProductDeepLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class DeepLinkController extends Controller
{
    /**
     * Redirect a deep link
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function redirect(Request $request, $urlKey)
    {
        $deepLink = ProductDeepLink::where('url_key', $urlKey)->first();

        if ($deepLink) {
            return Redirect::away($deepLink->to_url);
        }

        return abort(404);
    }

}
