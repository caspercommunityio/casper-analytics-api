<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Peer;

class GetPeers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:GetPeers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get peers';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ctx = stream_context_create(array('http'=>
          array(
              'timeout' => 10,
          )
        ));
        $peers = Peer::get();
        $peers = Peer::where(array("deleted" => false))->get();
        foreach ($peers as $peer) {
            $ch = curl_init(env("CORS_URL")."http://".$peer->ip.":8888/status");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'origin: https://test.com'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            $status = json_decode($result, true);

            if (isset($status["peers"]) && isset($status["chainspec_name"]) && $status["chainspec_name"] == env("CASPER_CHAIN")) {
                foreach ($status["peers"] as $remotePeer) {
                    $urlParts = parse_url($remotePeer["address"]);
                    if (!Peer::where(array("ip" => $urlParts["host"]))->exists()) {
                        //echo "Get IP Details\n";
                        Peer::create(array("ip"=>$urlParts["host"]));
                    }
                }
                Peer::where(array("ip" => $peer->ip))->update(array("publicKey" => $status["our_public_signing_key"]));
            } else {
                Peer::where(array("ip" => $peer->ip))->update(array("deleted" =>true));
            }
        }

        $peers = Peer::whereNull("country")->get();
        foreach ($peers as $peer) {
            $ipDetail = json_decode(file_get_contents("https://ipwhois.app/json/".$peer->ip), true);
            echo "Get Location ".$peer->ip."\n";
            if ((isset($ipDetail["message"]) && $ipDetail["message"] != "reserved range") || !isset($ipDetail["message"])) {
                Peer::where(array("ip" => $peer->ip))->update(array("region"=> $ipDetail["region"] ,"country" => $ipDetail["country"], "countryCode" => $ipDetail["country_code"], "latitude"=> $ipDetail["latitude"], "longitude" => $ipDetail["longitude"], "city" => $ipDetail["city"]));
            }
        }
    }
}
