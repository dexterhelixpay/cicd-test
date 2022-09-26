<?php

namespace App\Models;

use App\Traits\HasAssets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Attachment extends Model
{
    use HasAssets, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscription_id',

        'file_path',
        'name',
        'size',
        'is_invoice',
    ];

    /**
     * The attributes that are considered assets.
     *
     * @var array
     */
    protected $assets = [
        'file_path',
    ];

     /**
     * Get the path to the file
     *
     * @return string
     */
    public function getFilePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the orders.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function orders(): BelongsToMany
    {
        return $this
            ->belongsToMany(Order::class, OrderAttachment::class)
            ->withTimestamps();
    }

    /**
     * Get the subscription
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Upload the given file.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return self
     */
    public function uploadFile($file)
    {
        $directory = "files/subscriptions/{$this->subscription_id}/attachments";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $fileExtension = $file->getClientOriginalExtension();

        Storage::putFileAs($directory, $file, "{$fileRoot}.{$fileExtension}");

        return $this->fill([
            'file_path' => "{$directory}/{$fileRoot}.{$fileExtension}",
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Upload the given attachment
     *
     * @param  \Illuminate\Http\UploadedFile|string  $attachment
     * @return self
     */
    public function uploadAttachment($attachment)
    {
        $directory = "images/subscription/{$this->subscription_id}/attachments";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());

        Storage::putFileAs($directory, $attachment, "{$fileRoot}.pdf");

        return $this->setAttribute('file_path', "{$directory}/{$fileRoot}.pdf");
    }
}
