<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Holder;

class HolderController extends Controller
{
    public function getHolders(Request $request)
    {
        $page = ($request->query("page") ? $request->query("page") : 1);
        $query = ($request->query("q") ? $request->query("q") : "");
        $itemsPerPage = 50;
        $totalHolders = 0;

        $betweenFrom = ($page-1)*$itemsPerPage;
        $betweenTo = ($page)*$itemsPerPage;

        $priceUrl = "https://api.coingecko.com/api/v3/simple/price?ids=casper-network&vs_currencies=usd";
        $price = json_decode(file_get_contents($priceUrl), true);

        $supplyUrl = env("CSPR_LIVE_API")."/supply";
        $supply = json_decode(file_get_contents($supplyUrl), true);

        $holders = Holder::selectRaw("IFNULL(position,999999) position,publicKey,IFNULL(staking,0) as staking, IFNULL(balance,0) as balance, (IFNULL(staking,0) + IFNULL(balance, 0)) as total")->whereRaw('LOWER(`publicKey`) like ?', ['%'.strtolower($query).'%'])->orderBy("position", "asc")->skip($betweenFrom)->take($itemsPerPage)->get();

        if ($query != "") {
            $totalHolders = count($holders);
        } else {
            $totalHolders =Holder::count('publicKey');
        }
        $totalPages = round($totalHolders/$itemsPerPage);
        foreach ($holders as $h) {
            $h['staking_price'] = $price['casper-network']['usd'] * $h->staking;
            $h['balance_price'] = $price['casper-network']['usd'] * $h->balance;
            $h['total_price'] = $price['casper-network']['usd'] * $h->total;
            $h['percentage'] = (100 / $supply["data"]["total"])*$h->total;

            $h['current_price'] = $price['casper-network']['usd'];
        }
        $list = array("data" => $holders, "pagination" => array("currentPage" => $page, "totalPages" => $totalPages, "totalHolders" => $totalHolders));
        return $list;
    }
}
