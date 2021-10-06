<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Peer extends Model
{
    //
    protected $table = 'peers';
    protected $primaryKey = ['ip'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['ip', 'publicKey','country','countryCode','city','latitude','longitude','region','deleted'];
}
