<?php

namespace App\Facades\Cloudflare;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array createDnsRecord(string $name, string $content)
 * @method static array deleteDnsRecord(string $id)
 * @method static array listDnsRecords(array $query)
 * @method static array patchDnsRecord(string $id, array $data)
 */
class Zone extends Facade
{
    /**
     * Constant representing the error code for an already existing DNS record.
     *
     * @var int
     */
    const DNS_RECORD_EXISTS = 81053;

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cloudflare.zone';
    }
}
