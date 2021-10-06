<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\StatsPerEra;
use App\Reward;
use App\BlocksProcessed;
use App\Holder;

class GetEraRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:GetEraRewards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the Era\'s rewards';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function order($a, $b)
    {
        if ($a['nextEraStaked'] == $b['nextEraStaked']) {
            return 0;
        }
        return ($a['nextEraStaked'] > $b['nextEraStaked']) ? -1 : 1;
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

    public function storeHolder($publicKey)
    {
        if (!Holder::where(array("publicKey" => $publicKey))->exists()) {
            Holder::create(array("publicKey" => $publicKey));
        }
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $maxEraToRetrieveFullRewards = (BlocksProcessed::max("eraId")-40); //One week 3 * 12eras
        $eras = BlocksProcessed::where(array("switchBlock" => true,"processed" => 0))->orderBy('eraId', 'asc')->limit(2000)->get();
        Reward::where("eraId", '<', $maxEraToRetrieveFullRewards)->where("delegator", "<>", "")->delete();
        foreach ($eras as $eraToProceed) {
            $era=$eraToProceed->eraId;
            $lastHash = $eraToProceed->blockHash;

            $finalData = array();

            $data = $this->sendRPCCommand("chain_get_era_info_by_switch_block", array(array("Hash"=> $lastHash)));//json_decode($result, true);
            $eraRewards = 0;
            $eraDelegators = 0;
            if (isset($data['result']['era_summary']['era_id'])) {
                Reward::where(array("eraId" => $data['result']['era_summary']['era_id']))->delete();
                if (isset($data['result']['era_summary']['stored_value']['EraInfo']['seigniorage_allocations'])) {
                    foreach ($data['result']['era_summary']['stored_value']['EraInfo']['seigniorage_allocations'] as $delegatorRewards) {
                        if (isset($delegatorRewards['Delegator']) &&  $data['result']['era_summary']['era_id'] > $maxEraToRetrieveFullRewards) {
                            $this->storeHolder($delegatorRewards['Delegator']['delegator_public_key']);
                            Reward::create(array("eraId" => $data['result']['era_summary']['era_id'], "delegator" =>$delegatorRewards['Delegator']['delegator_public_key'], "validator" => $delegatorRewards['Delegator']['validator_public_key'], "rewards" => round(($delegatorRewards['Delegator']['amount']/1000000000), 2)  ));
                        }
                        if (isset($delegatorRewards['Validator'])) {
                            $this->storeHolder($delegatorRewards['Validator']['validator_public_key']);
                            Reward::create(array("eraId" => $data['result']['era_summary']['era_id'], "delegator" =>'', "validator" => $delegatorRewards['Validator']['validator_public_key'], "rewards" => round(($delegatorRewards['Validator']['amount']/1000000000), 2)  ));
                        }
                        if (isset($delegatorRewards['Delegator']['validator_public_key']) && !isset($finalData[$delegatorRewards['Delegator']['validator_public_key']])) {
                            $finalData[$delegatorRewards['Delegator']['validator_public_key']] = array("era"=>$era,"validator" =>$delegatorRewards['Delegator']['validator_public_key'], "rewards" => 0, "delegators" => 0,"nextEraStaked" => 0);
                        }

                        if (isset($delegatorRewards['Validator']['validator_public_key']) && !isset($finalData[$delegatorRewards['Validator']['validator_public_key']])) {
                            $finalData[$delegatorRewards['Validator']['validator_public_key']] = array("era"=>$era,"validator" => $delegatorRewards['Validator']['validator_public_key'], "rewards" => 0, "delegators" => 0,"nextEraStaked" => 0);
                        }

                        if (isset($delegatorRewards['Delegator']['validator_public_key'])) {
                            $finalData[$delegatorRewards['Delegator']['validator_public_key']]["rewards"] += round(($delegatorRewards['Delegator']['amount']/1000000000), 2);
                            $finalData[$delegatorRewards['Delegator']['validator_public_key']]["delegators"]++;
                        }


                        if (isset($delegatorRewards['Validator']['validator_public_key'])) {
                            $finalData[$delegatorRewards['Validator']['validator_public_key']]["rewards"] += round(($delegatorRewards['Validator']['amount']/1000000000), 2);
                        }
                    }
                }
            }

            $data = $this->sendRPCCommand("chain_get_block", array(array("Hash"=> $lastHash)));//json_decode($result, true);

            if (isset($data['result']['block']['header']['era_end']['next_era_validator_weights'])) {
                foreach ($data['result']['block']['header']['era_end']['next_era_validator_weights'] as $validatorStaked) {
                    if (!isset($finalData[$validatorStaked['validator']])) {
                        $finalData[$validatorStaked['validator']] = array("era"=>$era,"validator" => $validatorStaked['validator'], "rewards" => 0, "delegators" => 0,"nextEraStaked" => 0);
                    }
                    $finalData[$validatorStaked['validator']]["nextEraStaked"]+= round(($validatorStaked['weight']/1000000000), 2);
                }
            }

            usort($finalData, array($this,'order'));

            foreach ($finalData as $index=>$v) {
                if (StatsPerEra::where(array('eraId' => $v["era"],'validator' => $v["validator"]))->exists()) {
                    $eraExists = StatsPerEra::where(array('eraId' => $v["era"],'validator' => $v["validator"]))->first();
                    $APY = 0;
                    if ($eraExists->csprStaked !=0) {
                        $APY = (((100/$eraExists->csprStaked) * $v["rewards"]) * 12 * 365);
                    }
                    StatsPerEra::where(array('eraId' => $v["era"],'validator' => $v["validator"]))->update(array("apy"=>$APY, "delegators" => $v["delegators"], "rewards" => $v["rewards"]));
                } else {
                    StatsPerEra::create(array("eraId" => $v["era"], "validator" => $v["validator"], "apy" => 0, "rewards" => $v["rewards"], "delegators" => $v["delegators"],"csprStaked" => 0,"message" => ""));
                }

                if (StatsPerEra::where(array('eraId' => ($v["era"]+1),'validator' => $v["validator"]))->exists()) {
                    $eraExists = StatsPerEra::where(array('eraId' => ($v["era"]+1),'validator' => $v["validator"]))->first();
                    if ($eraExists->csprStaked !=0) {
                        $APY = (((100/$eraExists->csprStaked) * $v["rewards"]) * 12 * 365);
                    }
                    StatsPerEra::where(array('eraId' => ($v["era"]+1),'validator' => $v["validator"]))->update(array("csprStaked"=> (isset($v["nextEraStaked"]) ? $v["nextEraStaked"] : 0), "position" => ($index+1)));
                } else {
                    StatsPerEra::create(array("eraId" =>($v["era"]+1), "validator" => $v["validator"], "apy" => 0, "rewards" => 0, "delegators" => $v["delegators"],"csprStaked" => (isset($v["nextEraStaked"]) ? $v["nextEraStaked"] : 0),"message" => "","position" => ($index+1)));
                }
            }
            BlocksProcessed::where(array("switchBlock" => true, "eraId"=> $era))->update(["processed" => true]);
        }
    }
}
