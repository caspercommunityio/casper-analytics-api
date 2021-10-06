<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Reward;

class DelegatorController extends Controller
{
    public function getDelegatorsList(Request $request)
    {
        return reward::select("delegator as publicKey")->where("delegator", "<>", "")->distinct()->orderBy("delegator", "asc")->get();
    }
}
