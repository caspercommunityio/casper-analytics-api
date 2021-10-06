<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\StatsPerEra;
use App\Validator;
use App\Holder;

class GetValidators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:GetValidators';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Validators last bid';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function storeHolder($publicKey, $rewards)
    {
        if (!Holder::where(array("publicKey" => $publicKey))->exists()) {
            Holder::create(array("publicKey" => $publicKey, "staking" =>$rewards));
        } else {
            Holder::where("publicKey", "=", $publicKey)->update(array("staking" =>$rewards));
        }
    }

    public function sendRPCCommand($method, $params)
    {
        $ch = curl_init(env("RPC_ENDPOINT"));
        $payload = json_encode(array( "id" => 1,"jsonrpc" => "2.0", "method" => $method, "params" => $params ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($result, true);
        return $data;
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = $this->sendRPCCommand("state_get_auction_info", []);//json_decode($result, true);
        $currentEra = $data['result']['auction_state']['era_validators'][0]['era_id'];

        $stakedAmount = array();
        foreach ($data['result']['auction_state']['bids'] as $bid) {
            $totalDelegators = count($bid['bid']['delegators']);

            foreach ($bid['bid']['delegators'] as $d) {
                if (!isset($stakedAmount[$d["public_key"]])) {
                    $stakedAmount[$d["public_key"]] = 0;
                }
                $stakedAmount[$d["public_key"]] +=  round(($d["staked_amount"]/1000000000), 5);
            }

            //Update current Era
            if (StatsPerEra::where(array('eraId' => $currentEra,'validator' => $bid['public_key']))->exists()) {
                StatsPerEra::where(array('eraId' => $currentEra,'validator' => $bid['public_key']))->update(array("delegators"=>$totalDelegators));
            }

            if (Validator::where(array('publicKey' => $bid['public_key']))->exists()) {
                Validator::where(array('publicKey' => $bid['public_key']))->update(array("delegationRate"=>$bid['bid']['delegation_rate'], "active" => !$bid["bid"]["inactive"]));
            } else {
                Validator::create(array('publicKey' => $bid['public_key'],"delegationRate"=>$bid['bid']['delegation_rate'], "active" => !$bid["bid"]["inactive"]));
            }
        }

        foreach ($stakedAmount as $delegator=>$amount) {
            $this->storeHolder($delegator, $amount);
        }
    }
}
