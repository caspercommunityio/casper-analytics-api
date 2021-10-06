<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DelegationRate extends Model
{
    //
    protected $table = 'delegation_rate';
    protected $primaryKey = ['blockHash', 'deployHash'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['blockHash', 'deployHash','validator','delegationRate','deploymentDate'];
}
