<?php

namespace App\Http\Middleware;

use App\Models\ApiRequest;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\Order;
use App\Models\Subscription;
use App\Resolvers\IpAddressResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LogRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $merchant = $this->detectMerchant($request, $response);

        $apiRequest = ApiRequest::make()
            ->fill([
                'ip_address' => (new IpAddressResolver)->resolve(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->header() ?: null,
                'body' => $request->request->all() ?: null,
                'status' => $response->getStatusCode(),
                'response_headers' => $response->headers->all() ?: null,
                'response_body' => $response->getData(true) ?: null,
                'is_successful' => $response->isSuccessful(),
            ])
            ->user()
            ->associate($request->userOrClient())
            ->merchant()
            ->associate($merchant);

        if ($order = $this->detectOrder($request, $response)) {
            $apiRequest->order()->associate($order);
        }

        if ($subscription = $this->detectSubscription($request, $response, $order)) {
            $apiRequest
                ->setAttribute('reference_id', $subscription->reference_id)
                ->subscription()
                ->associate($subscription);
        }

        $exception = $response->exception;

        if ($exception && !$exception instanceof HttpExceptionInterface) {
            $apiRequest->error_info = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'trace' => collect($exception->getTrace())
                    ->map(function ($trace) {
                        return Arr::except($trace, 'args');
                    })
                    ->all(),
            ];
        }

        $apiRequest->save();

        return $response;
    }

    /**
     * Detect the merchant from the request/response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return \App\Models\Merchant|null
     */
    protected function detectMerchant($request, $response)
    {
        $user = $request->userOrClient();

        if ($user instanceof MerchantUser) {
            return $user->merchant()->first();
        }

        $data = data_get($response->getData(true), 'data');

        if (!empty($data) && Arr::has($data, ['id', 'type'])) {
            return $this->getMerchantFromResource($data);
        }

        $routes = [
            'merchant',
            'order',
            'subscription',
            'product',
        ];

        foreach ($routes as $route) {
            if ($id = $request->route($route)) {
                return $this->getMerchantFromResource([
                    'id' => optional($id)->getKey() ?? $id,
                    'type' => Str::plural($route),
                ]);
            }
        }

        return null;
    }

    /**
     * Detect the order from the request/response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return \App\Models\Order|null
     */
    protected function detectOrder($request, $response)
    {
        $data = data_get($response->getData(true), 'data');

        if (!empty($data) && data_get($data, 'type') === 'orders') {
            return Order::find(data_get($data, 'id'));
        }

        if ($id = $request->route('order')) {
            return Subscription::find(optional($id)->getKey() ?? $id);
        }

        return null;
    }

    /**
     * Detect the subscription from the request/response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @param  \App\Models\Order|null  $order
     * @return \App\Models\Subscription|null
     */
    protected function detectSubscription($request, $response, $order = null)
    {
        if ($order) {
            return $order->subscription()->first();
        }

        $data = data_get($response->getData(true), 'data');

        if (!empty($data) && data_get($data, 'type') === 'subscriptions') {
            return Subscription::find(data_get($data, 'id'));
        }

        if ($id = $request->route('subscription')) {
            return Subscription::find(optional($id)->getKey() ?? $id);
        }

        return null;
    }

    /**
     * Get the related merchant from the given resource.
     *
     * @param  array  $resource
     * @return \App\Models\Merchant|null
     */
    protected function getMerchantFromResource($resource)
    {
        $query = Merchant::query();

        return match ($resource['type']) {
            'merchants' => $query->find($resource['id']),

            'orders' => $query
                ->whereHas('subscriptions.orders', function ($query) use ($resource) {
                    $query->whereKey($resource['id']);
                })
                ->first(),

            'products' => $query
                ->whereHas('products', function ($query) use ($resource) {
                    $query->whereKey($resource['id']);
                })
                ->first(),

            'subscriptions' => $query
                ->whereHas('subscriptions', function ($query) use ($resource) {
                    $query->whereKey($resource['id']);
                })
                ->first(),

            default => null,
        };
    }
}
