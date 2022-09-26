<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

it('encodes an id to hash id', function () {
    /** @var \Tests\TestCase $this */

    $id = rand(10, 1000);

    $connections = Arr::except(config('hashids.connections'), 'main');

    collect($connections)
        ->each(function ($value, $connection) use ($id) {
            $hashedId = hashId($id, $connection);

            $this->assertFalse($hashedId == $id);
        });
});

it('decodes an hashed id to id', function () {
    /** @var \Tests\TestCase $this */

    $id = rand(10, 1000);

    $connections = Arr::except(config('hashids.connections'), 'main');

    collect($connections)
        ->each(function ($value, $connection) use ($id) {
            $hashedId = hashId($id, $connection);
            $decodedId = decodeId($hashedId, $connection);
            $fakeDecodedId = decodeId($id, $connection);

            $this->assertEquals(Arr::first($decodedId), $id);
            $this->assertFalse(Arr::first($fakeDecodedId) == $id);
        });
});


it('formats an order/subscription id', function () {
    /** @var \Tests\TestCase $this */

    $id = rand(10, 1000);

    $date = now()->toDateString();
    $year = Carbon::parse($date)->format('y');
    $seconds = Carbon::parse($date)->format('s');

    $formattedId = (string) formatId($date, $id);

    $this->assertStringContainsString($year, $formattedId);
    $this->assertStringContainsString($seconds, $formattedId);
    $this->assertStringContainsString($id, $formattedId);
});
