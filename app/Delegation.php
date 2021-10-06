<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{
    //
    protected $table = 'delegations';
    protected $primaryKey = ['blockHash', 'deployHash'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['blockHash', 'deployHash','method','delegator','validator','amount','deploymentDate','message'];
}
