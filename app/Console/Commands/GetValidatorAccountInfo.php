<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Validator;
use App\Holder;

class GetValidatorAccountInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:GetValidatorAccountInfo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the infos from the validator via the casper-account-info-contract';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    /**
    * Send Notification to the specified user
    *
    */
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
     * Browse all the notifications registered by the users and send a notification if needed
     *
     * @return mixed
     */
    public function handle()
    {
        $contractHash = "fb8e0215c040691e9bbe945dd22a00989b532b9c2521582538edb95b61156698";
        if (env("CASPER_CHAIN") == "casper-test") {
            $contractHash = "2f36a35edcbaabe17aba805e3fae42699a2bb80c2e0c15189756fdc4895356f8";
        }
        $stateRootHash = $this->sendRPCCommand("chain_get_state_root_hash", array());
        $stateRootHash = $stateRootHash["result"]["state_root_hash"];

        $contractInfo = $this->sendRPCCommand("state_get_item", array($stateRootHash, "hash-".$contractHash));
        if (isset($contractInfo["result"]["stored_value"]["Contract"]["named_keys"])) {
            foreach ($contractInfo["result"]["stored_value"]["Contract"]["named_keys"] as $key) {
                if ($key["name"] == "account-info-urls") {
                    $accountInfoUrls = $key["key"];
                }
            }
        }

        $arrContextOptions=array(
            "ssl"=>array(
               "verify_peer"=>false,
               "verify_peer_name"=>false,
            ),
            'http'=>
              array(
                  'timeout' => 10,
              )
        );

        foreach (Validator::get() as $validator) {
            // if ($validator->publicKey == '018fb709e6a4f9076fab0445a6b0cb4cf7dee6c6711c279ede1ebcbac3c3c7f0ac') {
            $account = Holder::where("publicKey", "=", $validator->publicKey)->first();
            if ($account != null) {
                $accountHash = str_replace("account-hash-", "", $account->accountHash);
                $url = $this->sendRPCCommand("state_get_dictionary_item", array($stateRootHash,array("URef" => array("dictionary_item_key" => $accountHash, "seed_uref" =>  $accountInfoUrls))));
                if (isset($url["result"]["stored_value"])) {
                    $link = $url["result"]["stored_value"]["CLValue"]["parsed"];

                    $result = @file_get_contents($link."/.well-known/casper/account-info.".env("CASPER_CHAIN").".json", false, stream_context_create($arrContextOptions));

                    if ($result == null) {
                        $link = str_replace("https", "http", $link);
                        $result = @file_get_contents($link."/.well-known/casper/account-info.".env("CASPER_CHAIN").".json", false, stream_context_create($arrContextOptions));
                    }
                    if ($result != null) {
                        $json = json_decode($result, true);
                        $updated = Validator::where("publicKey", "=", $validator->publicKey)->update(array("infos" => $json));
                        echo "Update - ".$validator->publicKey."-".$json["owner"]["name"]."-".$link."/.well-known/casper/account-info.".env("CASPER_CHAIN").".json"."\n";
                    } else {
                        echo "error - ".$validator->publicKey."-".$link."/.well-known/casper/account-info.".env("CASPER_CHAIN").".json"."\n";
                    }
                }
            }
            // }
        }
    }
}
