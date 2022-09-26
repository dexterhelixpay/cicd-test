<?php

namespace App\Resolvers;

use Altek\Accountant\Contracts\IpAddressResolver as IpAddressResolverContract;
use Illuminate\Support\Facades\Request;

class IpAddressResolver implements IpAddressResolverContract
{
    /**
     * Resolve the IP Address.
     *
     * @return string
     */
    public static function resolve(): string
    {
        return collect(Request::ips())->last();
    }
}
