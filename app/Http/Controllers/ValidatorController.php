<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Reward;
use App\StatsPerEra;
use App\Delegation;
use App\Validator;
use App\Peer;
use App\DelegationRate;
use App\BlocksProcessed;

class ValidatorController extends Controller
{
    public function getValidatorsList(Request $request)
    {
        return validator::select("publicKey")->where(array("active" => true))->orderBy("publicKey", "asc")->get();
    }

    public function getDeployments(String $validator, Request $request)
    {
        if (!Validator::where(array("publicKey" => $validator))->exists()) {
            return "Unknow validator";
        }
        return Delegation::where(array("validator" => $validator))->where("message", "=", "")->orderBy("deploymentDate", "desc")->limit(50)->get();
    }

    public function getInfos(String $validator, Request $request)
    {
        if (!Validator::where(array("publicKey" => $validator))->exists()) {
            return "Unknow validator";
        }

        $validatorData = Validator::where(array("publicKey" => $validator))->first();

        $out = array();
        $out["publicKey"]= $validatorData->publicKey;
        $out["currentDelegationRate"] = $validatorData->delegationRate;
        $out["contract-info"] = $validatorData->infos;

        $validatorsCount = Validator::where(array("active" => true))->count();
        $stats = StatsPerEra::where(array("validator" => $validator))->orderBy("eraId", "desc")->limit(2)->get();
        $minEra = StatsPerEra::where(array("validator" => $validator))->min("eraId");
        $maxEra = StatsPerEra::where(array("validator" => $validator))->max("eraId");
        $totalCsprStaked = StatsPerEra::where(array("eraId" => $maxEra))->sum("csprStaked");
        $totalDelegations = Delegation::where(array("validator" => $validator, "method"=>"delegate"))->count();
        $totalUndelegations = Delegation::where(array("validator" => $validator, "method"=>"undelegate"))->count();
        $totalDelegationsLastWeek = Delegation::where(array("validator" => $validator, "method"=>"delegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(7))->count();
        $totalUndelegationsLastWeek = Delegation::where(array("validator" => $validator, "method"=>"undelegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(7))->count();
        $totalDelegationsLastMonth = Delegation::where(array("validator" => $validator, "method"=>"delegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(30))->count();
        $totalUndelegationsLastMonth = Delegation::where(array("validator" => $validator, "method"=>"undelegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(30))->count();
        $messages = StatsPerEra::where(array("validator" => $validator))->where('message', '<>', '')->orderBy("eraId", "desc")->get();

        $currentDelegators = StatsPerEra::where(array("validator" => $validator,"eraId" => $maxEra))->first();
        $delegatorLastWeek = StatsPerEra::where(array("validator" => $validator,"eraId" => ($maxEra-84)))->first();

        $out["previousAPY"] = (isset($stats[1]) ? $stats[1]->apy : 0);
        $out["previousEra"] =(isset($stats[1]) ? $stats[1]->eraId : 0);
        $out["currentEra"] =(isset($stats[0]) ? $stats[0]->eraId : 0);
        $out["currentCsprStaked"] = (isset($stats[0]) ? $stats[0]->csprStaked : 0);
        $out["currentDelegators"] =(isset($stats[0]) ? $stats[0]->delegators : 0);
        $out["currentTotalValidators"] =$validatorsCount;
        $out["currentPosition"] = (isset($stats[0]) ? $stats[0]->position : 0);
        $out["minEra"] = $minEra;
        $out["maxEra"] = $maxEra;
        $out["erasProcessed"] = ($maxEra-$minEra);
        $out["totalDelegate"] = $totalDelegations;
        $out["totalUndelegate"] = $totalUndelegations;
        $out["totalDelegations"] = ($totalUndelegations+$totalDelegations);
        $out["totalDelegateLastWeek"] = $totalDelegationsLastWeek;
        $out["totalUndelegateLastWeek"] = $totalUndelegationsLastWeek;
        $out["totalDelegationsLastMonth"] = $totalDelegationsLastMonth;
        $out["totalUndelegationsLastMonth"] = $totalUndelegationsLastMonth;
        $out["messages"] = [];

        if ($delegatorLastWeek != null) {
            $out["validators"][$validator]["totalDelegatorsLastWeek"] = ((isset($currentDelegators->delegators) ? $currentDelegators->delegators : 0)-$delegatorLastWeek->delegators);
        } else {
            $out["validators"][$validator]["totalDelegatorsLastWeek"] = (isset($currentDelegators->delegators) ? $currentDelegators->delegators : 0);
        }

        foreach ($messages as $m) {
            $out["messages"][] = array("eraId" => $m->eraId, "message" => $m->message);
        }

        $priceUrl = "https://api.coingecko.com/api/v3/simple/price?ids=casper-network&vs_currencies=usd";
        $price = json_decode(file_get_contents($priceUrl), true);

        $out["currentPrice"] = $price['casper-network']['usd'];
        $out["currentPriceDate"] = date('d/m/Y H:i:s');

        $supplyUrl = env("CSPR_LIVE_API")."/supply";
        $supply = json_decode(file_get_contents($supplyUrl), true);

        $out["totalCsprStaked"] = $totalCsprStaked;
        $out["totalSupply"] = $supply["data"]["total"];
        $out["totalCirculatingSupply"] = $supply["data"]["circulating"];



        return $out;
    }
    public function getValidators(Request $request)
    {
        $orderBy = ($request->query("orderBy") ? $request->query("orderBy") : "position");
        $method = ($request->query("method") ? $request->query("method") : "asc");
        $validatorsData = Validator::where(array( "active" => true))->get();
        $out = array("validators" => array(), "infos" => array());


        $maxEraId = StatsPerEra::max("eraId");
        $totalCsprStaked = StatsPerEra::where(array("eraId" => $maxEraId))->sum("csprStaked");

        $mapsLocalisation = array();

        foreach ($validatorsData as $validatorData) {
            $validator = $validatorData->publicKey;
            $out["validators"][$validator]=array();
            $out["validators"][$validator]["publicKey"]= $validator;
            $out["validators"][$validator]["currentDelegationRate"] = $validatorData->delegationRate;

            $validatorsCount = Validator::where(array("active" => true))->count();
            $stats = StatsPerEra::where(array("validator" => $validator))->orderBy("eraId", "desc")->limit(2)->get();
            $minEra = StatsPerEra::where(array("validator" => $validator))->min("eraId");
            $maxEra = StatsPerEra::where(array("validator" => $validator))->max("eraId");
            $totalDelegations = Delegation::where(array("validator" => $validator, "method"=>"delegate"))->count();
            $totalUndelegations = Delegation::where(array("validator" => $validator, "method"=>"undelegate"))->count();
            $totalDelegationsLastWeek = Delegation::where(array("validator" => $validator, "method"=>"delegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(7))->count();
            $totalUndelegationsLastWeek = Delegation::where(array("validator" => $validator, "method"=>"undelegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(7))->count();
            $totalDelegationsLastMonth = Delegation::where(array("validator" => $validator, "method"=>"delegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(30))->count();
            $totalUndelegationsLastMonth = Delegation::where(array("validator" => $validator, "method"=>"undelegate"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays(30))->count();
            $messages = StatsPerEra::where(array("validator" => $validator))->where('message', '<>', '')->orderBy("eraId", "desc")->get();


            $currentDelegators = StatsPerEra::where(array("validator" => $validator,"eraId" => $maxEra))->first();
            $delegatorLastWeek = StatsPerEra::where(array("validator" => $validator,"eraId" => ($maxEra-84)))->first();

            if (isset($validatorData->infos)) {
                $out["validators"][$validator]['name']=$validatorData->infos->owner->name;
                if (isset($validatorData->infos->owner->branding->logo)) {
                    if (isset($validatorData->infos->owner->branding->logo->png_256) && $validatorData->infos->owner->branding->logo->png_256 != "") {
                        $out["validators"][$validator]['icon']=$validatorData->infos->owner->branding->logo->png_256;
                    } elseif (isset($validatorData->infos->owner->branding->logo->png_1024) && $validatorData->infos->owner->branding->logo->png_1024 != "") {
                        $out["validators"][$validator]['icon']=$validatorData->infos->owner->branding->logo->png_1024;
                    } elseif (isset($validatorData->infos->owner->branding->logo->svg) && $validatorData->infos->owner->branding->logo->svg != "") {
                        $out["validators"][$validator]['icon']=$validatorData->infos->owner->branding->logo->svg;
                    }
                }
            }


            $localisation = Peer::select('country', 'countryCode', 'city', 'latitude', 'longitude', 'region')->where(array("publicKey" => $validator))->first();

            try {
                $out["validators"][$validator]["previousAPY"] = (isset($stats[1]) ? $stats[1]->apy : 0);
                $out["validators"][$validator]["previousEra"] = (isset($stats[1]) ? $stats[1]->eraId : 0);
                $out["validators"][$validator]["currentEra"] = (isset($stats[0]) ? $stats[0]->eraId : 0);
                $out["validators"][$validator]["currentCsprStaked"] = $this->nice_number((isset($stats[0]) ? $stats[0]->csprStaked : 0));
                $out["validators"][$validator]["currentCsprStakedNumber"] = (isset($stats[0]) ? $stats[0]->csprStaked : 0);
                $out["validators"][$validator]["currentDelegators"] = (isset($stats[0]) ? $stats[0]->delegators : 0);
                $out["validators"][$validator]["currentTotalValidators"] =$validatorsCount;
                $out["validators"][$validator]["currentPosition"] = (isset($stats[0]) ? $stats[0]->position : 0);
                $out["validators"][$validator]["minEra"] = $minEra;
                $out["validators"][$validator]["maxEra"] = $maxEra;
                $out["validators"][$validator]["erasProcessed"] = ($maxEra-$minEra);
                $out["validators"][$validator]["totalDelegate"] = $totalDelegations;
                $out["validators"][$validator]["totalUndelegate"] = $totalUndelegations;
                $out["validators"][$validator]["totalDelegations"] = ($totalUndelegations+$totalDelegations);
                $out["validators"][$validator]["totalDelegateLastWeek"] = $totalDelegationsLastWeek;
                $out["validators"][$validator]["totalUndelegateLastWeek"] = $totalUndelegationsLastWeek;
                $out["validators"][$validator]["totalDelegationsLastMonth"] = $totalDelegationsLastMonth;
                $out["validators"][$validator]["totalUndelegationsLastMonth"] = $totalUndelegationsLastMonth;

                if ($delegatorLastWeek != null) {
                    $out["validators"][$validator]["totalDelegatorsLastWeek"] = ((isset($currentDelegators->delegators) ? $currentDelegators->delegators : 0)-$delegatorLastWeek->delegators);
                } else {
                    $out["validators"][$validator]["totalDelegatorsLastWeek"] =(isset($currentDelegators->delegators) ? $currentDelegators->delegators : 0);
                }

                $out["validators"][$validator]["messages"] = [];

                foreach ($messages as $m) {
                    $out["validators"][$validator]["messages"][] = array("eraId" => $m->eraId, "message" => $m->message);
                }


                // $out["validators"][$validator]["localisation"] = $localisation;
                if ($localisation!=null && !isset($mapsLocalisation[$localisation->countryCode."-".$localisation->city])) {
                    $mapsLocalisation[$localisation->countryCode."-".$localisation->city] = array();
                    $mapsLocalisation[$localisation->countryCode."-".$localisation->city]["latitude"] = $localisation->latitude;
                    $mapsLocalisation[$localisation->countryCode."-".$localisation->city]["longitude"] = $localisation->longitude;
                    $mapsLocalisation[$localisation->countryCode."-".$localisation->city]["validators"] = array();
                }
                if ($localisation!=null) {
                    $mapsLocalisation[$localisation->countryCode."-".$localisation->city]["validators"][] = array("publicKey" => $validator, "position" => intval(isset($stats[0]) ? $stats[0]->position : 0));
                    usort($mapsLocalisation[$localisation->countryCode."-".$localisation->city]["validators"], function ($a, $b) {
                        return $a['position'] <=> $b['position'];
                    });
                }
            } catch (Exception $e) {
                // echo "Exception $validator\n<br />";
              // var_dump($e);
            }
        }

        $out["localisations"] = $mapsLocalisation;

        uasort($out["validators"], array($this,'sortByCSPRDesc'));

        $lowestDelegationRate = array("publicKey" => "OOO","currentDelegationRate" => 110,"currentDelegators" => 0);
        $highestDelegations =  array("totalDelegateLastWeek" => -1);
        $highestDelegators = array("totalDelegatorsLastWeek" => -1);
        $randomNumber = rand(0, (count($out["validators"])-1));
        $cpt = 0;
        foreach ($out["validators"] as $validator) {
            if (floatval($validator["currentDelegationRate"]) < floatval($lowestDelegationRate["currentDelegationRate"])) {
                $lowestDelegationRate = $validator;
            } elseif ($validator["currentDelegationRate"] == $lowestDelegationRate["currentDelegationRate"]) {
                // echo "Compare ". $lowestDelegationRate["currentDelegationRate"]."(".$lowestDelegationRate["publicKey"].") To ".$validator["currentDelegationRate"] ." (".$validator["publicKey"] .")\n";
                if (isset($validator["currentDelegators"]) && isset($lowestDelegationRate["currentDelegators"]) && $validator["currentDelegators"] > $lowestDelegationRate["currentDelegators"]) {
                    $lowestDelegationRate = $validator;
                }
            }
            if (isset($validator["totalDelegateLastWeek"]) && isset($lowestDelegationRate["totalDelegateLastWeek"]) && $validator["totalDelegateLastWeek"] > $highestDelegations["totalDelegateLastWeek"]) {
                $highestDelegations = $validator;
            }
            if (isset($validator["totalDelegatorsLastWeek"]) && isset($lowestDelegationRate["totalDelegatorsLastWeek"]) && $validator["totalDelegatorsLastWeek"] > $highestDelegators["totalDelegatorsLastWeek"]) {
                $highestDelegators = $validator;
            }
            if ($cpt == $randomNumber) {
                $out["infos"]["randomValidator"] = $validator["publicKey"];
            }
            $cpt++;
        }

        // print_r($lowestDelegationRate);
        $out["infos"]["lowestDelegationRate"] = $lowestDelegationRate["publicKey"];
        $out["infos"]["highestDelegations"] = $highestDelegations["publicKey"];
        $out["infos"]["highestDelegators"] = $highestDelegators["publicKey"];

        $supplyUrl = env("CSPR_LIVE_API")."/supply";
        $supply = json_decode(file_get_contents($supplyUrl), true);

        $out["infos"]["totalCsprStaked"] = $totalCsprStaked;
        $out["infos"]["totalSupply"] = $supply["data"]["total"];
        $out["infos"]["totalCirculatingSupply"] = $supply["data"]["circulating"];
        $out["infos"]["era"] = $maxEraId;

        return $out;
    }

    public function getValidatorsCharts(Request $request)
    {
        $nbrOfDays = ($request->query("nbrDays") ? $request->query("nbrDays") : 7);
        $blocksProcessed = BlocksProcessed::select(DB::raw('date(deploymentDate) as date'), DB::raw("SUM(transfers) as total_transfers"), DB::raw("SUM(deploys) as total_deploys"))->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays($nbrOfDays))->groupBy(DB::raw('date(deploymentDate)'))->get();
        $maxEra = StatsPerEra::max("eraId");
        $apys = StatsPerEra::select("eraId", DB::raw('avg(apy) as avg_apy'))->whereBetween("eraId", [(($maxEra-1) - (7*12)),($maxEra-1)])->groupBy("eraId")->get();
        $delegatePerDay = Delegation::select(DB::raw('date(deploymentDate) as date'), DB::raw("count(*) as total"))->where("method", "=", "delegate")->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays($nbrOfDays))->groupBy(DB::raw('date(deploymentDate)'))->get();
        $undelegatePerDay = Delegation::select(DB::raw('date(deploymentDate) as date'), DB::raw("count(*) as total"))->where("method", "=", "undelegate")->whereDate('deploymentDate', '>', \carbon\Carbon::now()->subDays($nbrOfDays))->groupBy(DB::raw('date(deploymentDate)'))->get();

        $tranfers = array();
        $deploys = array();
        $bpDates = array();
        $apyAvg = array();
        $apyEra = array();

        $delegateData = array();
        $delegateLabel = array();
        $undelegateData = array();
        $undelegateLabel = array();

        foreach ($blocksProcessed as $bp) {
            $bpDates[] = $bp->date;
            $transfers[] = $bp->total_transfers;
            $deploys[] = $bp->total_deploys;
        }
        $bpDates = array_reverse($bpDates);
        $transfers = array_reverse($transfers);
        $deploys = array_reverse($deploys);

        foreach ($apys as $apy) {
            $apyEra[] = $apy->eraId;
            $apyAvg[] = $apy->avg_apy;
        }
        $apyEra = array_reverse($apyEra);
        $apyAvg = array_reverse($apyAvg);

        foreach ($delegatePerDay as $delegate) {
            $delegateData[] = $delegate->total;
            $delegateLabel[] = $delegate->date;
        }
        $delegateData = array_reverse($delegateData);
        $delegateLabel = array_reverse($delegateLabel);

        foreach ($undelegatePerDay as $undelegate) {
            $undelegateData[] = $undelegate->total;
            $undelegateLabel[] = $undelegate->date;
        }
        $undelegateData = array_reverse($undelegateData);
        $undelegateLabel = array_reverse($undelegateLabel);

        $result = [];
        $result["transfers"] = array("labels" => $bpDates, "values" => $transfers);
        $result["deploys"] = array("labels" => $bpDates, "values" => $deploys);
        $result["apy"] = array("labels" => $apyEra, "values" => $apyAvg);
        $result["delegations"] = array("labels" => $delegateLabel, "values" => $delegateData);
        $result["undelegations"] = array("labels" => $undelegateLabel, "values" => $undelegateData);
        return $result;
    }
    public function getValidatorCharts(String $validator, Request $request)
    {
        $nbrOfEras = ($request->query("nbrEras") ? $request->query("nbrEras") : 10000);
        if (!Validator::where(array("publicKey" => $validator))->exists()) {
            return "Unknow validator";
        }
        $validatorEras = StatsPerEra::where(array("validator" => $validator))->orderBy("eraId", "desc")->limit($nbrOfEras)->get();
        $fees = DelegationRate::where(array("validator" => $validator))->orderBy("deploymentDate", "desc")->limit($nbrOfEras)->get();
        $delegations = Delegation::select(DB::raw('date(deploymentDate) as date'), DB::raw('count(*) as total'))->where(array("validator" => $validator, "method" => "delegate"))->groupBy(DB::raw('date(deploymentDate)'))->orderBy(DB::raw('date(deploymentDate)'), "desc")->limit($nbrOfEras)->get();
        $undelegations = Delegation::select(DB::raw('date(deploymentDate) as date'), DB::raw('count(*) as total'))->where(array("validator" => $validator, "method" => "undelegate"))->groupBy(DB::raw('date(deploymentDate)'))->orderBy(DB::raw('date(deploymentDate)'), "desc")->limit($nbrOfEras)->get();

        $eras = [];
        $apy = [];
        $rewards = [];
        $delegators = [];
        $csprStaked = [];
        $delegationsCount = [];
        $delegationsDates = [];
        $undelegationsCount = [];
        $undelegationsDates = [];
        $feesValues = [];
        $feesDates = [];


        foreach ($validatorEras as $era) {
            $eras[] = $era->eraId;
            $apy[] = $era->apy;
            $rewards[] = $era->rewards;
            $delegators[] = $era->delegators;
            $csprStaked[] = $era->csprStaked;
        }

        foreach ($delegations as $delegation) {
            $delegationsCount[] = $delegation->total;
            $delegationsDates[] =  $delegation->date;
        }

        foreach ($undelegations as $undelegation) {
            $undelegationsCount[] = $undelegation->total;
            $undelegationsDates[] =  $undelegation->date;
        }

        foreach ($fees as $fee) {
            $feesValues[] = $fee->delegationRate;
            $feesDates[] =  $fee->deploymentDate;
        }

        $erasAPY = $eras;
        $eras = array_reverse($eras);
        $apy = array_reverse($apy);
        $rewards = array_reverse($rewards);
        $delegators = array_reverse($delegators);
        $csprStaked = array_reverse($csprStaked);
        $erasAPY = array_reverse($erasAPY);
        $delegationsCount = array_reverse($delegationsCount);
        $delegationsDates = array_reverse($delegationsDates);
        $undelegationsCount = array_reverse($undelegationsCount);
        $undelegationsDates = array_reverse($undelegationsDates);
        $feesValues = array_reverse($feesValues);
        $feesDates = array_reverse($feesDates);

        array_pop($erasAPY);
        array_pop($apy);
        array_pop($rewards);

        $result = [];
        $result["apy"] = array("labels" => $erasAPY, "values" => $apy);
        $result["rewards"] = array("labels" => $erasAPY, "values" => $rewards);
        $result["delegators"] = array("labels" => $eras, "values" => $delegators);
        $result["csprStaked"] = array("labels" => $eras, "values" => $csprStaked);
        $result["delegations"] = array("labels" => $delegationsDates, "values" => $delegationsCount);
        $result["undelegations"] = array("labels" => $undelegationsDates, "values" => $undelegationsCount);
        $result["fees"] = array("labels" => $feesDates, "values" => $feesValues);

        $price = json_decode(file_get_contents("https://api.coingecko.com/api/v3/coins/casper-network/market_chart?vs_currency=usd&days=15"), true);
        $priceDates = array();
        $priceAmount = array();
        foreach ($price['prices'] as $p) {
            $priceDates[]="'".date('d M Y H:i:s', $p[0]/1000)."'";
            $priceAmount[]=$p[1];
        }
        $result["price"] = array("labels" => $priceDates, "values" => $priceAmount);
        return   $result;
    }

    public function sortMapByPosition($a, $b)
    {
        if ($a['position'] == $b['position']) {
            return 0;
        }
        return  ($a['position'] < $b['position']) ? -1 : 1;
    }

    public function sortByPositionAsc($a, $b)
    {
        if (!isset($a["currentPosition"]) || !isset($b["currentPosition"])) {
            return 0;
        }
        if ($a['currentPosition'] == $b['currentPosition']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['currentPosition'] < $b['currentPosition']) ? -1 : 1;
    }

    public function sortByPositionDesc($a, $b)
    {
        if (!isset($a["currentPosition"]) || !isset($b["currentPosition"])) {
            return 0;
        }
        if ($a['currentPosition'] == $b['currentPosition']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['currentPosition'] > $b['currentPosition']) ? -1 : 1;
    }


    public function sortByDelegationRateAsc($a, $b)
    {
        if (!isset($a["currentDelegationRate"]) || !isset($b["currentDelegationRate"])) {
            return -1;
        }
        if ($a['currentDelegationRate'] == $b['currentDelegationRate']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['currentDelegationRate'] < $b['currentDelegationRate']) ? -1 : 1;
    }


    public function sortByDelegationRateDesc($a, $b)
    {
        if (!isset($a["currentDelegationRate"]) || !isset($b["currentDelegationRate"])) {
            return -1;
        }
        if ($a['currentDelegationRate'] == $b['currentDelegationRate']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['currentDelegationRate'] > $b['currentDelegationRate']) ? -1 : 1;
    }


    public function sortByDelegatorsAsc($a, $b)
    {
        if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
            return -1;
        }
        if ($a['currentDelegators'] == $b['currentDelegators']) {
            return 0;
        }
        return ($a['currentDelegators'] < $b['currentDelegators']) ? -1 : 1;
    }


    public function sortByDelegatorsDesc($a, $b)
    {
        if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
            return -1;
        }
        if ($a['currentDelegators'] == $b['currentDelegators']) {
            return 0;
        }
        return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
    }


    public function sortByAPYAsc($a, $b)
    {
        if (!isset($a["previousAPY"]) || !isset($b["previousAPY"])) {
            return -1;
        }
        if ($a['previousAPY'] == $b['previousAPY']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['previousAPY'] < $b['previousAPY']) ? -1 : 1;
    }


    public function sortByAPYDesc($a, $b)
    {
        if (!isset($a["previousAPY"]) || !isset($b["previousAPY"])) {
            return -1;
        }
        if ($a['previousAPY'] == $b['previousAPY']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['previousAPY'] > $b['previousAPY']) ? -1 : 1;
    }

    public function sortByCSPRAsc($a, $b)
    {
        if (!isset($a["currentCsprStakedNumber"]) || !isset($b["currentCsprStakedNumber"])) {
            return -1;
        }
        if ($a['currentCsprStakedNumber'] == $b['currentCsprStakedNumber']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['currentCsprStakedNumber'] < $b['currentCsprStakedNumber']) ? -1 : 1;
    }


    public function sortByCSPRDesc($a, $b)
    {
        if (!isset($a["currentCsprStakedNumber"]) || !isset($b["currentCsprStakedNumber"])) {
            return -1;
        }
        if ($a['currentCsprStakedNumber'] == $b['currentCsprStakedNumber']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['currentCsprStakedNumber'] > $b['currentCsprStakedNumber']) ? -1 : 1;
    }


    public function sortByDelegators7Asc($a, $b)
    {
        if (!isset($a["totalDelegatorsLastWeek"]) || !isset($b["totalDelegatorsLastWeek"])) {
            return -1;
        }
        if ($a['totalDelegatorsLastWeek'] == $b['totalDelegatorsLastWeek']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['totalDelegatorsLastWeek'] < $b['totalDelegatorsLastWeek']) ? -1 : 1;
    }


    public function sortByDelegators7Desc($a, $b)
    {
        if (!isset($a["totalDelegatorsLastWeek"]) || !isset($b["totalDelegatorsLastWeek"])) {
            return -1;
        }
        if ($a['totalDelegatorsLastWeek'] == $b['totalDelegatorsLastWeek']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['totalDelegatorsLastWeek'] > $b['totalDelegatorsLastWeek']) ? -1 : 1;
    }



    public function sortByDelegations7Asc($a, $b)
    {
        if (!isset($a["totalDelegateLastWeek"]) || !isset($b["totalDelegateLastWeek"])) {
            return -1;
        }
        if ($a['totalDelegateLastWeek'] == $b['totalDelegateLastWeek']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['totalDelegateLastWeek'] < $b['totalDelegateLastWeek']) ? -1 : 1;
    }


    public function sortByDelegations7Desc($a, $b)
    {
        if (!isset($a["totalDelegateLastWeek"]) || !isset($b["totalDelegateLastWeek"])) {
            return -1;
        }
        if ($a['totalDelegateLastWeek'] == $b['totalDelegateLastWeek']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['totalDelegateLastWeek'] > $b['totalDelegateLastWeek']) ? -1 : 1;
    }



    public function sortByUndelegations7Asc($a, $b)
    {
        if (!isset($a["totalUndelegateLastWeek"]) || !isset($b["totalUndelegateLastWeek"])) {
            return -1;
        }
        if ($a['totalUndelegateLastWeek'] == $b['totalUndelegateLastWeek']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['totalUndelegateLastWeek'] < $b['totalUndelegateLastWeek']) ? -1 : 1;
    }


    public function sortByUndelegations7Desc($a, $b)
    {
        if (!isset($a["totalUndelegateLastWeek"]) || !isset($b["totalUndelegateLastWeek"])) {
            return -1;
        }
        if ($a['totalUndelegateLastWeek'] == $b['totalUndelegateLastWeek']) {
            if (!isset($a["currentDelegators"]) || !isset($b["currentDelegators"])) {
                return -1;
            }
            if ($a['currentDelegators'] == $b['currentDelegators']) {
                return 0;
            }
            return ($a['currentDelegators'] > $b['currentDelegators']) ? -1 : 1;
        }
        return ($a['totalUndelegateLastWeek'] > $b['totalUndelegateLastWeek']) ? -1 : 1;
    }




    public function nice_number($n)
    {
        // first strip any formatting;
        $n = (0+str_replace(",", "", $n));

        // is this a number?
        if (!is_numeric($n)) {
            return false;
        }

        // now filter it;
        if ($n > 1000000000000) {
            return round(($n/1000000000000), 2).' trillion';
        } elseif ($n > 1000000000) {
            return round(($n/1000000000), 2).'B';
        } elseif ($n > 1000000) {
            return round(($n/1000000), 2).'M';
        } elseif ($n > 1000) {
            return round(($n/1000), 2).'k';
        }

        return number_format($n);
    }
}
