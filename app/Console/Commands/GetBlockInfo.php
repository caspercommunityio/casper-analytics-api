<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Delegation;
use App\DelegationRate;
use App\BlocksProcessed;
use App\Holder;

class GetBlockInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:GetBlockInfo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get block info to extract data like delegation/undelegation';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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
        } else {
            Holder::where("publicKey", "=", $publicKey)->update(array("processed" => false));
        }
    }


    public function getBlockDetail($blockHash, $counter, $max)
    {
        if ($counter >= $max) {
            return "end";
        }

        $dataBlock = $this->sendRPCCommand("chain_get_block", array(array("Hash" => $blockHash)));
        $error_message = "";
        // echo $blockHash." ($counter)\n";
        $deploys = $dataBlock['result']['block']['body']['deploy_hashes'];
        $transfers = $dataBlock['result']['block']['body']['transfer_hashes'];

        foreach ($deploys as $deployHash) {
            if (!Delegation::where(array("deployHash"=>$deployHash))->exists()) {
                $dataDeploy = $this->sendRPCCommand("info_get_deploy", array($deployHash));// json_decode($result, true);
                $error_message = "";
                if (isset($dataDeploy['result']['execution_results'][0]['result']['Failure']['error_message'])) {
                    $error_message = $dataDeploy['result']['execution_results'][0]['result']['Failure']['error_message'];
                }

                if (isset($dataDeploy['result']['deploy']['session']['StoredContractByHash']['entry_point']) && ($dataDeploy['result']['deploy']['session']['StoredContractByHash']['entry_point'] == 'delegate' || $dataDeploy['result']['deploy']['session']['StoredContractByHash']['entry_point'] == 'undelegate')) {
                    $validator="";
                    $delegator="";
                    $amount="";
                    $hash=$dataDeploy['result']['deploy']['hash'];
                    $timestamp=$dataDeploy['result']['deploy']['header']['timestamp'];
                    $method = $dataDeploy['result']['deploy']['session']['StoredContractByHash']['entry_point'];
                    //echo "	Deploy : $deployHash\n";
                    foreach ($dataDeploy['result']['deploy']['session']['StoredContractByHash']['args'] as $arg) {
                        if ($arg[0]=="delegator") {
                            $delegator=$arg[1]['parsed'];
                        }
                        if ($arg[0]=="amount") {
                            $amount=round(($arg[1]['parsed']/1000000000), 2);
                        }
                        if ($arg[0]=="validator") {
                            $validator=$arg[1]['parsed'];
                        }
                    }
                    if ($delegator != "" && $amount != "" && $validator != "") {
                        Delegation::create(array("blockHash" => $blockHash, "deployHash" => $hash, "method" => $method, "delegator" => $delegator, "validator" => $validator, "amount" => $amount, "deploymentDate" => substr($timestamp, 0, -6), "message" => $error_message));
                    }
                }


                if (isset($dataDeploy['result']['deploy']['session']["ModuleBytes"])) {
                    $validator="";
                    $delegator="";
                    $amount="";
                    $hash=$dataDeploy['result']['deploy']['hash'];
                    $timestamp=$dataDeploy['result']['deploy']['header']['timestamp'];
                    $method = "";
                    foreach ($dataDeploy['result']['deploy']['session']["ModuleBytes"]['args'] as $arg) {
                        if ($arg[0]=="action") {
                            $method = $arg[1]["parsed"];
                        }
                        if ($arg[0]=="delegator") {
                            $delegator = $arg[1]["parsed"];
                        }
                        if ($arg[0]=="validator") {
                            $validator = $arg[1]["parsed"];
                        }
                        if ($arg[0]=="amount") {
                            $amount=round(($arg[1]['parsed']/1000000000), 2);
                        }
                    }

                    if ($delegator != "" && $amount != "" && $validator != "") {
                        Delegation::create(array("blockHash" => $blockHash, "deployHash" => $hash, "method" => $method, "delegator" => $delegator, "validator" => $validator, "amount" => $amount, "deploymentDate" => substr($timestamp, 0, -6), "message" => $error_message));
                    }
                }
            }
            if (!DelegationRate::where(array("deployHash"=>$deployHash))->exists()) {
                $dataDeploy = $this->sendRPCCommand("info_get_deploy", array($deployHash));//json_decode($result, true);
                $error_message = "";
                if (isset($dataDeploy['result']['execution_results'][0]['result']['Failure']['error_message'])) {
                    $error_message = $dataDeploy['result']['execution_results'][0]['result']['Failure']['error_message'];
                }

                if (isset($dataDeploy['result']['deploy']['session']["ModuleBytes"])) {
                    $addDeploy=false;
                    $validator = "";
                    $delgationRate = -1;
                    foreach ($dataDeploy['result']['deploy']['session']["ModuleBytes"]['args'] as $arg) {
                        if ($arg[0]=="delegation_rate") {
                            $delegationRate = $arg[1]["parsed"];
                            $addDeploy = true;
                        }
                        if ($arg[0]=="public_key") {
                            $validator = $arg[1]["parsed"];
                        }
                    }
                    if ($addDeploy && $error_message == "") {
                        $hash=$dataDeploy['result']['deploy']['hash'];
                        $timestamp=$dataDeploy['result']['deploy']['header']['timestamp'];
                        DelegationRate::create(array("blockHash" => $blockHash, "deployHash" => $hash, "validator" => $validator, "delegationRate" => $delegationRate, "deploymentDate" => substr($timestamp, 0, -6)));
                    }
                }
            }
        }

        foreach ($transfers as $transfer) {
            $dataDeploy = $this->sendRPCCommand("info_get_deploy", array($transfer));
            if (isset($dataDeploy["result"]) &&isset($dataDeploy["result"]["deploy"]) && isset($dataDeploy["result"]["deploy"]["header"])) {
                $this->storeHolder($dataDeploy["result"]["deploy"]["header"]["account"]);
            }
        }

        if (!BlocksProcessed::where(array('blockHash'=>$blockHash))->exists()) {
            BlocksProcessed::create(array("blockHash" => $blockHash ,"switchBlock" => (isset($dataBlock['result']['block']['header']['era_end']) ? true:false), "processed" => (isset($dataBlock['result']['block']['header']['era_end']) ? false : true),"deploys" => count($deploys), "transfers" => count($transfers), "eraId" => $dataBlock['result']['block']['header']['era_id'], "blockHeight" => $dataBlock['result']['block']['header']['height'],"deploymentDate" => substr($dataBlock['result']['block']['header']['timestamp'], 0, -6)));
        } else {
            BlocksProcessed::where(array('blockHash'=>$blockHash))->update(array("switchBlock" => (isset($dataBlock['result']['block']['header']['era_end']) ? true:false), "processed" => (isset($dataBlock['result']['block']['header']['era_end']) ? false : true),"deploys" => count($deploys), "transfers" => count($transfers), "eraId" => $dataBlock['result']['block']['header']['era_id'], "blockHeight" => $dataBlock['result']['block']['header']['height'],"deploymentDate" => substr($dataBlock['result']['block']['header']['timestamp'], 0, -6)));
        }
        $this->getBlockDetail($dataBlock["result"]["block"]["header"]["parent_hash"], ($counter+1), $max);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $counter=0;
        $mostRecentBlock = $this->sendRPCCommand("chain_get_block", array());
        $result = $this->getBlockDetail($mostRecentBlock['result']['block']['hash'], $counter, 30);
    }
}
