<?php

use App\Models\ImportBatch;
use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('shopify-import.{batchId}', function ($batchId) {
    return ImportBatch::find($batchId);
});

Broadcast::channel('xendit-payment-paid.{orderId}', function ($orderId) {
    return Order::find($orderId);
});
