<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbSeeder extends Model
{
  protected $table = 'db_seeders';

  protected $fillable = ["name"];

  public function scopebyName($query, $name)

  {

    return $query->where('name', $name)->count() > 0;

  }
}
