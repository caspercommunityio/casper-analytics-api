<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    //
    protected $table = 'rewards';
    protected $primaryKey = ['eraId', 'delegator','validator'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['eraId', 'delegator','validator','rewards'];
}
