<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Validator extends Model
{
    //
    protected $table = 'validators';
    protected $primaryKey = ['publicKey'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['publicKey', 'delegationRate', 'infos','active'];

    public function getInfosAttribute($value)
    {
        return json_decode($value);
    }
}
