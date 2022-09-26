<?php

namespace App\Exceptions;

use App\Exceptions\MerchantAmountLimitException;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Exceptions\OAuthServerException as PassportOAuthServerException;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        AccessDeniedHttpException::class,
        BadRequestException::class,
        InsufficientStockException::class,
        MerchantAmountLimitException::class,
        MultipleSessionException::class,
        OAuthServerException::class,
        PassportOAuthServerException::class,
        PasswordAlreadyUsedException::class,
        PasswordExpiredException::class,
        UnauthorizedException::class,
        VoucherApplicationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (AccessDeniedHttpException $e) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '403',
                        'detail' => $e->getMessage() ?: 'This action is unauthorized.',
                    ],
                ],
            ], 403);
        });

        $this->renderable(function (BadRequestException $e) {
            return response()->json([
                'errors' => [
                    collect([
                        'status' => (string) $e->getStatusCode(),
                        'code' => $e->getCode(),
                        'detail' => $e->getMessage(),
                    ])->filter()->all(),
                ],
            ], $e->getStatusCode());
        });

        $this->renderable(function (InsufficientStockException $e) {
            return response()->json([
                'errors' => [
                    [
                        'status' => 400,
                        'code' => $e->getCode(),
                        'detail' => $e->getMessage(),
                        'meta' => $e->getMeta(),
                    ],
                ],
            ], 400);
        });

        $this->renderable(function (MerchantAmountLimitException $e) {
            return response()->json([
                'errors' => [
                    collect([
                        'status' => 400,
                        'code' => $e->getCode(),
                        'detail' => $e->getMessage(),
                    ])->filter()->all(),
                ],
            ], 400);
        });

        $this->renderable(function (VoucherApplicationException $e) {
            return response()->json([
                'errors' => [
                    collect([
                        'status' => 400,
                        'code' => $e->getCode(),
                        'detail' => $e->getMessage(),
                    ])->filter()->all(),
                ],
            ], 400);
        });

        $this->renderable(function (BadResponseException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);

            return response()->json([
                'errors' => [
                    collect([
                        'status' => (string) $response->getStatusCode(),
                        'code' => $body['code'] ?? null,
                        'detail' => $body['message'] ?? null,
                    ])->filter()->all(),
                ],
            ], $response->getStatusCode());
        });

        $this->renderable(function (NotFoundHttpException $e) {
            $e = $e->getPrevious();

            if (!$e instanceof ModelNotFoundException) {
                return;
            }

            $model = Str::snake(class_basename($e->getModel()));
            $model = ucfirst(str_replace('_', ' ', $model));

            return response()->json([
                'errors' => [
                    [
                        'status' => '404',
                        'detail' => ucfirst($model) . ' not found.',
                    ],
                ],
            ], 404);
        });

        $this->renderable(function (DiscordUserOverrideException $e) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '400',
                        'detail' => $e->getMessage(),
                    ],
                ],
            ], 400);
        });


        $this->renderable(function (PasswordAlreadyUsedException $e) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '400',
                        'code' => $e->getCode(),
                        'detail' => $e->getMessage(),
                    ],
                ],
            ], 400);
        });

        $this->renderable(function (DiscordUsedLinkException $e) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '400',
                        'detail' => $e->getMessage(),
                    ],
                ],
            ], 400);
        });


        $this->renderable(function (PasswordExpiredException $e) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '401',
                        'code' => $e->getCode(),
                        'detail' => $e->getMessage(),
                    ],
                ],
            ], 401);
        });

        $this->renderable(function (UnauthorizedException $e) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '403',
                        'detail' => $e->getMessage() ?: 'Unauthorized to perform this action.',
                    ],
                ],
            ], 403);
        });

        $this->renderable(function (MultipleSessionException $e) {
            return response()->json([
                'errors' => [
                    collect([
                        'status' => 400,
                        'code' => $e->getCode(),
                        'detail' => $e->getMessage(),
                    ])->filter()->all(),
                ],
            ], 400);
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->shouldReturnJson($request, $exception)) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '401',
                        'detail' => $exception->getMessage(),
                    ],
                ],
            ], 401);
        }

        abort(401, $exception->getMessage());
    }

    /**
     * {@inheritdoc}
     */
    protected function invalidJson($request, ValidationException $exception)
    {
        $errors = collect($exception->errors())
            ->flatMap(function ($errors, $key) use ($exception) {
                return array_map(function ($error) use ($key, $exception) {
                    $error = [
                        'status' => (string) $exception->status,
                        'detail' => $error,
                    ];

                    if (is_numeric($key)) {
                        return $error;
                    }

                    return array_merge($error, [
                        'source' => ['pointer' => $key],
                    ]);
                }, $errors);
            });

        return response()->json(compact('errors'), $exception->status);
    }

    /**
     * {@inheritdoc}
     */
    protected function convertExceptionToArray(Throwable $e)
    {
        $error = config('app.debug')
            ? [
                'detail' => $e->getMessage(),
                'meta' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())
                        ->map(function ($trace) {
                            return Arr::except($trace, 'args');
                        })
                        ->all(),
                ],
            ] : [
                'detail' => $this->isHttpException($e)
                    ? $e->getMessage()
                    : 'Server Error',
            ];

        if ($code = $e->getCode()) {
            $error = ['code' => (string) $code] + $error;
        }

        if (method_exists($e, 'getStatusCode')) {
            $error = ['status' => (string) call_user_func([$e, 'getStatusCode'])] + $error;
        }

        return ['errors' => [$error]];
    }
}
