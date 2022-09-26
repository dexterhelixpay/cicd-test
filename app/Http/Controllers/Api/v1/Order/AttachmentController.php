<?php

namespace App\Http\Controllers\Api\v1\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Attachment;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;

class AttachmentController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:merchant');
        $this->middleware('permission:MC: Products');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Order $order)
    {
        $this->authorizeRequest($request, $order);

        $attachments = QueryBuilder::for($order->attachments()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($attachments);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Order $order)
    {
        $this->authorizeRequest($request, $order);
        $this->validateRequest($request);

        return DB::transaction(function () use ($request, $order) {
            $attachments = collect($request->allFiles()['data'])
                ->pluck('attributes.file')
                ->map(function (UploadedFile $file) use ($order) {
                    ($attachment = $order->subscription->attachments()->make())
                        ->uploadFile($file)
                        ->save();

                    return $attachment->fresh();
                });

            $order->attachments()->syncWithoutDetaching(
                $attachments->pluck('id')->toArray()
            );

            return new ResourceCollection($order->attachments()->get());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @param  int  $attachment
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Order $order, $attachment)
    {
        $this->authorizeRequest($request, $order);

        if (!$attachment = $order->attachments()->find($attachment)) {
            throw (new ModelNotFoundException)->setModel(Attachment::class);
        }

        return DB::transaction(function () use ($order, $attachment) {
            if ($attachment->is_invoice) {
                return response()->json(['message' => 'Not allowed to delete invoice attachment'],422);
            };

            $order->attachments()->detach($attachment);

            if ($attachment->orders()->doesntExist()) {
                $attachment->delete();
            }

            return response()->json([], 204);
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest($request, $order)
    {
        if ($request->isFromMerchant()) {
            $merchant = optional($order->subscription)->merchant;

            if (!$merchant) {
                throw new UnauthorizedException;
            }

            $hasUser = $merchant->users()
                ->whereKey($request->userOrClient()->getKey())
                ->exists();

            if (!$hasUser) {
                throw new UnauthorizedException;
            }
        }
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest($request)
    {
        $request->validate([
            'data' => 'required',
            'data.*.attributes.file' => [
                'required',
                'mimes:jpg,png,pdf',
            ],
        ]);
    }
}
