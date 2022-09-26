<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;

class Bank extends Model
{
    use HasFactory;

    /**
     * Constant representing a BDO bank.
     *
     * @var string
     */
    const BDO = 'BDO';

    /**
     * Constant representing a BPI bank.
     *
     * @var string
     */
    const BPI = 'BPI';

    /**
     * Constant representing a METROBANK bank.
     *
     * @var string
     */
    const METROBANK = 'Metrobank';


    /**
     * Constant representing a RCBC bank.
     *
     * @var string
     */
    const RCBC = 'RCBC';


    /**
     * Constant representing a PNB bank.
     *
     * @var string
     */
    const PNB = 'PNB';


    /**
     * Constant representing a PNB bank.
     *
     * @var string
     */
    const UNIONBANK = 'UnionBank';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',

        'image_path',

        'min_value',
        'max_value',

        'daily_limit',
        'fee',

        'no_of_free_transactions',
        'payment_channel',

        'is_enabled',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the image path.
     *
     * @return string
     */
    public function getImagePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }
}
