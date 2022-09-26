<?php

namespace App\Http\Controllers\Api\v1;

use App\Facades\Viber;
use App\Models\Customer;
use App\Models\Merchant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Libraries\Viber\Message as ViberMessage;
use App\Models\Email;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use GuzzleHttp\Client;
use Throwable;
use SendGrid\EventWebhook\EventWebhook;
class WebhookController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:user,merchant')->only('index', 'store', 'destroy');
        $this->middleware('auth.client:user,merchant,null')->only('captureViberEvent');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $webhooks = QueryBuilder::for(Webhook::class)
            ->when($request->isFromMerchant(), function ($query) use ($request) {
                $query->where('merchant_id', $request->userOrClient()->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($webhooks);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $webhook
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $webhook)
    {
        $webhook = Webhook::query()
            ->whereKey($webhook)
            ->when($request->isFromMerchant(), function ($query) use ($request) {
                $query->where('merchant_id', $request->userOrClient()->merchant_id);
            })
            ->first();

        if (!optional($webhook)->delete()) {
            throw (new ModelNotFoundException)->setModel(Webhook::class);
        }

        return response()->json([], 204);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validateRequest($request);

        return DB::transaction(function () use ($request) {
            $merchantId = $request->isFromMerchant()
                ? $request->userOrClient()->merchant_id
                : $request->input('data.attributes.merchant_id');

            $webhook = Webhook::make($request->input('data.attributes'))
                ->forceFill(['merchant_id' => $merchantId]);

            $webhook->save();

            return new Resource($webhook->fresh());
        });
    }

    /**
     * Capture webhook events from PayMongo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captureSendgridEvent(Request $request)
    {
        $sendGridWebhook = new EventWebhook;

        $isVerified = $sendGridWebhook->verifySignature(
            $sendGridWebhook->convertPublicKeyToECDSA(config('services.sendgrid.verification_key')),
            $request->getContent(),
            $request->server('HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE'),
            $request->server('HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP')
        );

        if (!$isVerified) return response()->json();

        collect($request)
            ->each(function ($data) {
                $email = Email::find(data_get($data, 'email_id'));

                if (!$email) return;

                $event = $email->events()->make([
                    'email_address' => data_get($data, 'email'),
                    'ip_address' => data_get($data, 'ip'),
                    'user_agent' => data_get($data, 'useragent'),
                    'type' => data_get($data, 'email_type'),
                    'event' => data_get($data, 'event'),
                    'url' => data_get($data, 'url')
                ]);

                $event->save();
            });
    }

    /**
     * Capture webhook events from PayMongo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captureMerchantViberEvent(Request $request)
    {
        switch ($request->input('event')) {
            case 'conversation_started':
                $isSubscribed = $request->input('subscribed');

                if (!$isSubscribed) {
                    $merchant = Merchant::find($request->input('context'));

                    if ($merchant) {
                        Viber::withToken(
                            config('services.viber.merchant_auth_token'),
                            function () use($request) {
                                return ViberMessage::send(
                                    data_get($request->input('user'), 'id'),
                                    "Welcome to HelixPay Merchants notifications! Kindly send here in this chat your code to link your account and get started."
                                );
                            }
                        );
                    }
                }
                break;

            case 'message':
                $message = data_get($request->input('message'), 'text');
                $deobfuscatedMerchant = decodeId($message, 'merchant');

                if (empty($deobfuscatedMerchant[0])) return;

                $merchant = Arr::first(Merchant::find($deobfuscatedMerchant));

                if ($merchant && !$merchant['viber_info']) {
                    $merchant->viber_info = $request->input('sender');
                    $merchant->update();
                }
                break;

            case 'unsubscribed':
                        $merchant = Merchant::where('viber_info->id', $request->input('user_id'))
                            ->update([
                                'viber_info' => null
                            ]);
                    break;
        }

        return response()->json();
    }

    /**
     * Capture webhook events from PayMongo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captureViberEvent(Request $request)
    {
        switch ($request->input('event')) {
            case 'conversation_started':
                $isSubscribed = $request->input('subscribed');

                if (!$isSubscribed) {
                    $partner = 'HelixPay';
                    $customer = Customer::find($request->input('context'));

                    if ($customer) {
                        if ($customer->merchant->viber_key) {
                            Viber::setViberCredentials(
                                $customer->merchant->viber_key,
                                $customer->merchant->name,
                                $customer->merchant->logo_image_path
                            );

                            $partner = $customer->merchant->name;
                        }

                        ViberMessage::send(
                            data_get($request->input('user'), 'id'),
                            "Welcome to {$partner} {$customer->merchant->subscription_term_singular} notifications! Kindly send here in this chat your code to link your account and get started."
                        );
                    }
                }
                break;

            case 'message':
                    $message = data_get($request->input('message'), 'text');
                    $deobfuscatedCustomer = decodeId($message, 'customer');

                    if (empty($deobfuscatedCustomer[0])) return;

                    $customer = Arr::first(Customer::find($deobfuscatedCustomer));

                    if ($customer && !$customer['viber_info']) {
                        $customer->viber_info = $request->input('sender');
                        $customer->update();
                    }
                    break;

            case 'unsubscribed':
                        $customer = Customer::where('viber_info->id', $request->input('user_id'))
                            ->update([
                                'viber_info' => null
                            ]);
                    break;
        }

        return response()->json();
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.merchant_id' => [
                Rule::requiredIf($request->isFromUser()),
                Rule::exists('merchants', 'id')
                    ->withoutTrashed(),
            ],

            'data.attributes.url' => 'required|url',
            'data.attributes.events' => 'required|array',
            'data.attributes.events.*' => Rule::in(Webhook::EVENTS),
        ]);
    }
}
