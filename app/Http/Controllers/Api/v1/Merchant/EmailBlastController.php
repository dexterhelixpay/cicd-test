<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Carbon\Carbon;
use App\Mail\EmailBlast;
use App\Models\Merchant;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use App\Models\MerchantEmailBlast;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use App\Models\Customer;
use App\Services\PostService;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EmailBlastController extends Controller
{

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant');
        $this->middleware('permission:CP: Merchants - Edit|MC: Customers');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $emailBlasts = QueryBuilder::for($merchant->emailBlasts()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($emailBlasts);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Merchant $merchant)
    {
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $data = Arr::except($request->input('data.attributes'), 'targeted_customer_ids');

            $emailBlast = $merchant->emailBlasts()->make($data);

            if(Arr::first($request->input('data.attributes.targeted_customer_ids')) === '*') {
                $customers = $merchant->customers()->where('is_unsubscribed', false)->get()->unique('email');
            } else if (
                Arr::first($request->input('data.attributes.targeted_customer_ids')) === '*'
                && count($request->input('data.attributes.unselected_customer_ids')) > 0
            ) {
                $customers = $merchant->customers()
                    ->whereNotIn('id', $request->input('data.attributes.unselected_customer_ids'))
                    ->where('is_unsubscribed', false)
                    ->get()
                    ->unique('email');
            } else {
                $customers = [];
                if ($request->input('data.attributes.targeted_customer_ids')) {
                    $customers = $merchant->customers()
                        ->whereIn('id', $request->input('data.attributes.targeted_customer_ids'))
                        ->where('is_unsubscribed', false)
                        ->get()
                        ->unique('email');
                }
            }

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $emailBlast->uploadImage($request->file('data.attributes.banner_image_path'));
            }

            $emailBlast->targeted_customer_ids = collect($customers)->pluck('id') ?? [];
            $emailBlast->targeted_customer_count = count($emailBlast->targeted_customer_ids);

            $emailBlast->published_at = $request->input('data.attributes.is_published')
                ? now()
                : Carbon::parse($request->input('data.attributes.published_at'));

            if (now()->gt($emailBlast->published_at)) {
                $emailBlast->is_published = true;
            }

            $emailBlast->save();

            if ($voucher = $request->input('data.relationships.voucher.data.attributes')) {
                collect($emailBlast->targeted_customer_ids)
                    ->each(function ($id) use($voucher, $emailBlast) {
                        $customer = Customer::find($id);

                        if (!$customer) return;

                        $emailBlast->vouchers()->create(
                            Arr::except($voucher, 'prefix')
                            + [
                                'customer_id' => $customer->id,
                                'merchant_id' => $emailBlast->merchant_id,
                                'code' => $emailBlast->generateVoucherCode($customer, data_get($voucher, 'prefix'))
                            ]
                        );
                    });
            }

            if ($emailBlast->has_limited_availability) {
                $products = collect($request->input('data.relationships.products.data') ?? [])
                    ->mapWithKeys(function ($product) {
                        return [$product['id'] => [
                            'expires_at' => data_get($product, 'attributes.expires_at', null),
                        ]];
                    })
                    ->toArray();

                $emailBlast->products()->sync($products);
            }

            (new PostService)->createFromBlast($emailBlast);

            return new CreatedResource($emailBlast->refresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $emailBlast
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, int $emailBlast)
    {
        $this->validateRequest($request, $merchant, $emailBlast);

        $emailBlast = $merchant->emailBlasts()->findOrFail($emailBlast);

        return DB::transaction(function () use ($request, $merchant, $emailBlast) {
            $data = Arr::except($request->input('data.attributes'), ['targeted_customer_ids','published_at']);

            $emailBlast->update($data);

            if (data_get($request, 'data.type') == 'send') {
                if ($request->input('data.attributes.targeted_customer_ids')) {
                    $customers = $merchant->customers()
                        ->whereIn('id', $request->input('data.attributes.targeted_customer_ids'))
                        ->where('is_unsubscribed', false)
                        ->get()
                        ->unique('email');

                    $emailBlast->targeted_customer_ids = collect($customers)->plucK('id') ?? [];
                    $emailBlast->targeted_customer_count = count($emailBlast->targeted_customer_ids);
                    $emailBlast->save();
                }

                $emailBlast->notifyTargetedCustomers();
                return response()->json(['message' => 'Email blast sent']);
            }

            if(Arr::first($request->input('data.attributes.targeted_customer_ids')) === '*') {
                $customers = $merchant->customers()->where('is_unsubscribed', false)->get()->unique('email');
            } else if (
                Arr::first($request->input('data.attributes.targeted_customer_ids')) === '*'
                && count($request->input('data.attributes.unselected_customer_ids')) > 0
            ) {
                $customers = $merchant->customers()
                    ->whereNotIn('id', $request->input('data.attributes.unselected_customer_ids'))
                    ->where('is_unsubscribed', false)
                    ->get()
                    ->unique('email');
            } else {
                $customers = [];
                if ($request->input('data.attributes.targeted_customer_ids')) {
                    $customers = $merchant->customers()
                        ->whereIn('id', $request->input('data.attributes.targeted_customer_ids'))
                        ->where('is_unsubscribed', false)
                        ->get()
                        ->unique('email');
                }
            }

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $emailBlast->uploadImage($request->file('data.attributes.banner_image_path'));
            }

            $emailBlast->targeted_customer_ids = collect($customers)->plucK('id') ?? [];
            $emailBlast->targeted_customer_count = count($emailBlast->targeted_customer_ids);

            $emailBlast->save();

            if ($voucher = $request->input('data.relationships.voucher.data.attributes')) {
                $emailBlast->vouchers()->get()->each->delete();

                collect($emailBlast->targeted_customer_ids)
                    ->each(function ($id) use($voucher, $emailBlast) {
                        $customer = Customer::find($id);

                        if (!$customer) return;

                        $emailBlast->vouchers()->create(
                            Arr::except($voucher, 'prefix')
                            + [
                                'customer_id' => $customer->id,
                                'merchant_id' => $emailBlast->merchant_id,
                                'code' => $emailBlast->generateVoucherCode($customer, data_get($voucher, 'prefix'))
                            ]
                        );
                    });
            }

            if (!$emailBlast->has_limited_availability) {
                $emailBlast->products()->detach();
            } elseif ($request->filled('data.relationships.products.data')) {
                $products = collect($request->input('data.relationships.products.data') ?? [])
                    ->mapWithKeys(function ($product) {
                        return [$product['id'] => [
                            'expires_at' => data_get($product, 'attributes.expires_at', null),
                        ]];
                    })
                    ->toArray();

                $emailBlast->products()->sync($products);
            }

            (new PostService)->createFromBlast($emailBlast);

            return new CreatedResource($emailBlast->refresh());
        });
    }


    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $emailBlast
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $emailBlast)
    {
        $emailBlast = QueryBuilder::for($merchant->emailBlasts()->getQuery())
            ->whereKey($emailBlast)
            ->apply()
            ->first();

        if (!$emailBlast) {
            throw (new ModelNotFoundException())->setModel(MerchantEmailBlast::class);
        }

        return new Resource($emailBlast);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantEmailBlast|null  $emailBlast
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $emailBlast = null)
    {
        if (data_get($request, 'data.type') == 'send') return;

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.subject' => 'required|string|max:255',
            'data.attributes.title' => 'nullable|string|max:255',
            'data.attributes.subtitle' => 'nullable|string',

            'data.attributes.targeted_customer_ids' => [
                'nullable',
                'array'
            ],
            'data.attributes.banner_image_path' => 'nullable|sometimes|image',
            'data.attributes.banner_image_url' => 'nullable|sometimes|string|max:255',

            'data.relationships.products.data' => [
                'nullable',
                'object_array',
            ],
            'data.relationships.products.data.*.id' => [
                'sometimes',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $merchant->getKey())
                    ->withoutTrashed(),
            ],
            'data.relationships.products.data.*.attributes.expires_at' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d H:i:s',
            ],
        ]);
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $merchant)
    {
        if (
            $request->isFromMerchant()
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
    }
}
