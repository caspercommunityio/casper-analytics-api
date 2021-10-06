<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StatsPerEra extends Model
{
    //
    protected $table = 'stats_per_era';
    protected $primaryKey = ['validator','eraId'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['eraId', 'validator','csprStaked','rewards','delegators','apy','message','position'];
}
