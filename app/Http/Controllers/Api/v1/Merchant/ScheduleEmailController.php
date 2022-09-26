<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\EmailRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use App\Models\SubscriptionImport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
class ScheduleEmailController extends Controller
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
        $scheduleEmails = QueryBuilder::for($merchant->scheduleEmails()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($scheduleEmails);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(EmailRequest $request, Merchant $merchant)
    {
        return DB::transaction(function () use ($request, $merchant) {
            $scheduleEmail = $merchant->scheduleEmails()->make($request->input('data.attributes'));
            $importId = $request->input('data.relationships.subscription_import.data.id')
                ?? data_get(
                    collect($request->input('data.relationships.subscriptions.data'))->first(),
                    'relationships.subscription_import.data.id'
                );

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $scheduleEmail->uploadImage($request->file('data.attributes.banner_image_path'));
            }

            $scheduleEmail->save();

            if ($request->has('data.relationships.subscriptions.data')) {
               collect($request->input('data.relationships.subscriptions.data') ?? [])
                    ->each(function ($subscription) use ($scheduleEmail){
                        $subscription = Subscription::findOrFail(data_get($subscription, 'id'));
                        $subscription->scheduleEmail()
                            ->associate($scheduleEmail)
                            ->save();
                    });

                $import = SubscriptionImport::findOrFail($importId);
                $import->scheduleEmail()
                    ->associate($scheduleEmail)
                    ->save();

                $scheduleEmail->refresh()->sendInitialEmail();
            }

            return new CreatedResource($scheduleEmail->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $scheduleEmail
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $scheduleEmail)
    {
        $scheduleEmail = QueryBuilder::for($merchant->scheduleEmails()->getQuery())
            ->whereKey($scheduleEmail)
            ->apply()
            ->first();

        if (!$scheduleEmail) {
            throw (new ModelNotFoundException())->setModel(WelcomeEmail::class);
        }

        return new Resource($scheduleEmail);
    }
}
