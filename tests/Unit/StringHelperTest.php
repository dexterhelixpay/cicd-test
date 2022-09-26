<?php

use Illuminate\Support\Str;

it('formats mobile numbers', function () {
    /** @var \Tests\TestCase $this */

    $this->assertEquals(null, Str::mobileNumber(''));
    $this->assertEquals(null, Str::mobileNumber(null));

    $this->assertEquals('9123456789', Str::mobileNumber('9123456789'));
    $this->assertEquals('9123456789', Str::mobileNumber('09123456789'));
    $this->assertEquals('9123456789', Str::mobileNumber('639123456789'));
    $this->assertEquals('9123456789', Str::mobileNumber('+639123456789'));

    // $this->assertEquals('+639123456789', Str::mobileNumber('9123456789', true));
    $this->assertEquals('+639123456789', Str::mobileNumber('09123456789', true));
    $this->assertEquals('+639123456789', Str::mobileNumber('639123456789', true));
    $this->assertEquals('+639123456789', Str::mobileNumber('+639123456789', true));
});

it('splits a full name to first/last name', function () {
    /** @var \Tests\TestCase $this */

    $this->assertEquals(null, Str::splitName(0));
    $this->assertEquals(null, Str::splitName(null));
    $this->assertEquals(null, Str::splitName(''));
    $this->assertEquals(null, Str::splitName(' '));

    $firstLast = fn ($firstName, $lastName) => compact('firstName', 'lastName');

    $this->assertEquals($firstLast('John', 'John'), Str::splitName('John'));
    $this->assertEquals($firstLast('John', 'Doe'), Str::splitName('John Doe'));
    $this->assertEquals($firstLast('John', 'Richard Doe'), Str::splitName('John Richard Doe'));

    $this->assertEquals($firstLast('John', null), Str::splitName('John', false));
    $this->assertEquals($firstLast('John', 'Doe'), Str::splitName('John Doe', false));
    $this->assertEquals($firstLast('John', 'Richard Doe'), Str::splitName('John Richard Doe', false));
});

it("returns the email if it's valid", function () {
    /** @var \Tests\TestCase $this */

    $this->assertFalse(Str::validEmail(0));
    $this->assertFalse(Str::validEmail(null));
    $this->assertFalse(Str::validEmail(''));
    $this->assertFalse(Str::validEmail('example.com'));

    $this->assertEquals('email@example.com', Str::validEmail('email@example.com'));
});
