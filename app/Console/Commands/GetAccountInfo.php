<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Holder;

class GetAccountInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:GetAccountInfo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get account hash and main purse of each account in the holders table';

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

    public function generateAccountHash($publicKey)
    {
        $digestSize = 32;
        $separator = array_map('hexdec', str_split("0", 2));
        $publicKeyRaw = array_map('hexdec', str_split(substr($publicKey, 2), 2));
        $signerAlgo = substr($publicKey, 0, 2);
        $signerByte = [];
        if ($signerAlgo == "01") {
            $signerByte = unpack('C*', "ed25519");
        } else {
            $signerByte = unpack('C*', "secp256k1");
        }
        $byteArray = array_merge($signerByte, $separator, $publicKeyRaw);
        $byteHash = sodium_crypto_generichash(implode(array_map("chr", $byteArray)), null, $digestSize);
        $accountHash = bin2hex($byteHash);

        return "account-hash-".$accountHash;
    }

    /**
     * Execute the console command.
     * Update the accountHash and the mainPurse of unknown accounts
     *
     * @return mixed
     */
    public function handle()
    {
        $stateRootHash = $this->sendRPCCommand("chain_get_state_root_hash", array());
        $stateRootHash = $stateRootHash["result"]["state_root_hash"];
        $accounts = Holder::where("accountHash", "=", null)->get();
        foreach ($accounts as $a) {
            $accountHash = $this->generateAccountHash($a->publicKey);
            $stateItem = $this->sendRPCCommand("state_get_item", array($stateRootHash,$accountHash));

            if (isset($stateItem["result"]["stored_value"]["Account"])) {
                Holder::where("publicKey", "=", $a->publicKey)->update(array("accountHash" => $accountHash,"mainPurse" => $stateItem["result"]["stored_value"]["Account"]["main_purse"]));
            }
        }
    }
}
