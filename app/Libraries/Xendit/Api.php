<?php

namespace App\Libraries\Xendit;

use App\Libraries\Xendit;

abstract class Api
{
    /**
     * Create a new Xendit API instance.
     *
     * @param  \App\Libraries\Xendit  $xendit
     * @return void
     */
    public function __construct(Xendit $xendit)
    {
        //
    }
}
