<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //
    protected $table = 'notifications';
    public $timestamps = false;

    protected $fillable = ['id', 'condition','result','lastExecution','notificationToken'];

    protected $casts = [
   ];
    public function getConditionAttribute($value)
    {
        return json_decode($value);
    }
    public function getResultAttribute($value)
    {
        return json_decode($value);
    }
}
