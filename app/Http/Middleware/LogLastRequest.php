<?php

namespace App\Http\Middleware;

use App\Models\LastHttpRequest;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class LogLastRequest
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
        if ($request->isFromClient() || $request->isFromCustomer()) {
            return $next($request);
        }

        if ($user = $request->userOrClient()) {
            try {
                DB::beginTransaction();

                $isAdminSpoofingMerchant = data_get($user->token(), 'name')
                    == 'Merchant Users Personal Access Client';

                $lastRequest = $isAdminSpoofingMerchant
                    ? $user->adminLastRequest()->sharedLock()->first()
                    : $user->lastRequest()->sharedLock()->first();

                if (!$lastRequest) {
                    $lastRequest = LastHttpRequest::create([
                        'user_id' => $user->id,
                        'user_type' => get_class($user),
                        'is_revoke' => false
                    ]);
                }

                $lastRequestAt = Carbon::parse($lastRequest->updated_at)->startOfMinute();

                $lastRequest->forceFill([
                    'token' => $user->token() ?? null,
                    'browser' => $request->userAgent(),
                    'ip_address' => trim(
                            shell_exec("dig +short myip.opendns.com @resolver1.opendns.com")
                        ) ?? $request->ip(),
                    'request_uri' => $request->getRequestUri() ?? null,
                ])->update();

                $lastRequest->touch();

                DB::commit();

                if (!$idleMaxLimit = (int) setting('IdleMaxLimit')) {
                    return $next($request);
                }

                if ($lastRequestAt->diffInMinutes(now()->startOfMinute()) >= $idleMaxLimit) {
                    $accessToken = $user->token();

                    DB::table('oauth_refresh_tokens')
                        ->where('access_token_id', $accessToken->getKey())
                        ->delete();

                    $accessToken->revoke();

                    $lastRequest->forceFill(['is_revoke' => true])->update();

                    throw new AuthenticationException("You've been logged out.");
                }
            } catch (Throwable $e) {
                DB::rollBack();
            }
        }

        return $next($request);
    }
}
