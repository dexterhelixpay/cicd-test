<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEvent extends Model
{

    /**
     * Constant representing Email Blast
     *
     * @var int
     */
    const BLAST = 1;

    /**
     * Constant representing Sibscription Email
     *
     * @var int
     */
    const SUBSCRIPTION = 2;

    /**
     * Constant representing Iport Email
     *
     * @var int
     */
    const IMPORT = 3;


    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email_id',

        'email_address',

        'type',
        'event',

        'ip_address',
        'user_agent',

        'url',
    ];

    /**
     * Get the email associated with the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }


}
