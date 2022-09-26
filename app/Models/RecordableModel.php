<?php

namespace App\Models;

use Altek\Accountant\Contracts\Recordable;
use App\Traits\HasAssets;
use App\Traits\TracksChanges;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExtendsModel;

class RecordableModel extends Model implements Recordable
{
    use ExtendsModel, HasAssets, TracksChanges;
}
