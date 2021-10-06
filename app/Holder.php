<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Holder extends Model
{
    //
    protected $table = 'holders';
    protected $primaryKey = ['publicKey'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['publicKey', 'accountHash','mainPurse','staking','balance','processed','lastProcessed','position'];
}
