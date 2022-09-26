<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use App\Models\MerchantUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LockAndWaitRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $lockSeconds = 10, $waitSeconds = 10)
    {
        $key = 'route:' . $request->route()->getName();

        if ($merchant = $this->getMerchant($request)) {
            $key .= ':merchant:' . $merchant->getKey();
        }

        return Cache::lock($key, $lockSeconds)
            ->block($waitSeconds, function () use ($request, $next) {
                return $next($request);
            });
    }

    /**
     * Get the merchant from the request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\Merchant
     */
    protected function getMerchant(Request $request): ?Merchant
    {
        $merchant = $request->route('merchant');

        if ($merchant instanceof Merchant) {
            return $merchant;
        }

        $user = $request->user() ?? $request->userOrClient();

        if ($user instanceof MerchantUser) {
            return $user->merchant()->first();
        }

        if ($request->filled('merchant_id')) {
            return Merchant::find($request->input('merchant_id'));
        }

        return null;
    }
}
