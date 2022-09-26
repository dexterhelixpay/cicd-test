<?php

namespace App\Models;

use Altek\Accountant\Contracts\Identifiable;
use Altek\Accountant\Contracts\Recordable;
use App\Traits\HasAssets;
use App\Traits\TracksChanges;
use Illuminate\Foundation\Auth\User;

class RecordableUser extends User implements Identifiable, Recordable
{
    use HasAssets, TracksChanges;
}
