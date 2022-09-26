<?php

namespace App\Traits;

use Altek\Accountant\Recordable;
use Altek\Eventually\Eventually;

trait TracksChanges
{
    use Eventually, Recordable;
}
