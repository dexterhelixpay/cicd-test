<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class ClientController extends Controller
{
    /**
     * The client repository instance.
     *
     * @var \Laravel\Passport\ClientRepository
     */
    protected $clients;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct(ClientRepository $clients)
    {
        $this->clients = $clients;

        $this->middleware('auth:user,merchant');
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
        $this->authorizeRequest($request, $merchant);

        try {
            return $this->show($request, $merchant);
        } catch (ModelNotFoundException $e) {
            $client = $this->clients->create(
                $merchant->owner()->first()->getKey(),
                "{$merchant->name} Client",
                'http://localhost',
                'merchant_users'
            );

            return new Resource($client->makeVisible('secret'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        if (!$owner = $merchant->owner()->first()) {
            throw (new ModelNotFoundException)->setModel(Client::class);
        }

        $client = QueryBuilder::for(Client::class)
            ->where('provider', 'merchant_users')
            ->where('user_id', $owner->getKey())
            ->latest()
            ->first();

        if (!$client) {
            throw (new ModelNotFoundException)->setModel(Client::class);
        }

        return new Resource($client->makeVisible('secret'));
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
            && $merchant->getKey() != $request->userOrClient()->merchant_id
        ) {
            throw new UnauthorizedException;
        }
    }
}
