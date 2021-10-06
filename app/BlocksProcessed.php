<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BlocksProcessed extends Model
{
    //
    protected $table = 'blocks_processed';
    protected $primaryKey = ['blockHash'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['blockHash', 'deploymentDate','blockHeight', 'eraId','deploys','transfers','processed','switchBlock'];
}
