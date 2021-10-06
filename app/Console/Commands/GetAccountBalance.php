<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Holder;

class GetAccountBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:GetAccountBalance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the balance of each account not yet processed';

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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $stateRootHash = $this->sendRPCCommand("chain_get_state_root_hash", array());
        $stateRootHash = $stateRootHash["result"]["state_root_hash"];

        //Get the balance of the accounts not yet processed
        $accounts = Holder::where("processed", "=", false)->where("mainPurse", "<>", null)->get();
        foreach ($accounts as $a) {
            $balance = $this->sendRPCCommand("state_get_balance", array($stateRootHash,$a->mainPurse));
            if (isset($balance["result"]["balance_value"])) {
                Holder::where("publicKey", "=", $a->publicKey)->update(array("balance" => round(($balance["result"]["balance_value"]/1000000000), 5), "processed" => true, "lastProcessed" => \carbon\Carbon::now()));
            }
        }

        // Update the balance of the accounts processed more than 7 days
        $accounts = Holder::where("lastProcessed", "<=", \carbon\Carbon::now()->subDays(7))->limit(200)->get();
        foreach ($accounts as $a) {
            $balance = $this->sendRPCCommand("state_get_balance", array($stateRootHash,$a->mainPurse));
            if (isset($balance["result"]["balance_value"])) {
                Holder::where("publicKey", "=", $a->publicKey)->update(array("balance" => round(($balance["result"]["balance_value"]/1000000000), 5), "processed" => true, "lastProcessed" => \carbon\Carbon::now()));
            }
        }

        // Update the position of the accounts
        $accounts = Holder::selectRaw("position,publicKey,staking, balance, (IFNULL(staking,0) + IFNULL(balance, 0)) as total")->orderBy("total", "desc")->get();
        foreach ($accounts as $index=>$a) {
            Holder::where("publicKey", "=", $a->publicKey)->update(array("position" => ($index+1)));
        }
    }
}
