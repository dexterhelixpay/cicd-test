<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Bepsvpt\SecureHeaders\SecureHeaders;

class AllowShopifyXFrame
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->has('shop')) {
            $shopDomain = $request->input('shop');
            $hmac = $request->input('hmac');

            $merchant = Merchant::where('shopify_domain', $shopDomain)->first();

            if (!$merchant) return $response;

            $params = array_diff_key($request->all(), array('hmac' => ''));
            ksort($params);

            $computed_hmac = hash_hmac('sha256', http_build_query($params), $merchant->shopify_secret_key);

            if (hash_equals($hmac, $computed_hmac)) {
                $response->headers->set('Content-Security-Policy', "frame-ancestors {$shopDomain}", true);
            }
        }

        return $response;
    }
}
