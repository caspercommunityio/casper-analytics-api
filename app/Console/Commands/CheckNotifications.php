<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\DelegationRate;
use App\Delegation;
use App\BlocksProcessed;
use App\Notification;
use App\StatsPerEra;
use App\Reward;

class CheckNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:CheckNotifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Browse all the notifications and check a notification should be send';

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
    public function sendNotification($title, $message, $link, $user)
    {
        $ch = curl_init("https://fcm.googleapis.com/fcm/send");
        $payload = json_encode(array( "notification" => array("title" => $title, "body"=>$message, "click_action" => $link,"icon" => env("NOTIFICATION_ICON")),"to" =>$user ), JSON_UNESCAPED_SLASHES);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization: key='.env("FIREBASE_SERVER_KEY")));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($result, true);
        if ($data["success"]) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * Search for new deployments (Delegate/Undelegate)
    *
    */
    public function newDeployments($notification, $method)
    {
        $now = \carbon\Carbon::now();
        $lastDeployments = Delegation::where(array("validator" => $notification["condition"]->parameters->validator,"method" => $method))->where("message", '=', '')->where('deploymentDate', '>=', ($notification["lastExecution"] ? $notification["lastExecution"]:$now))->get();
        $totalDeployments = count($lastDeployments);
        $result = array(
          "notificationSend" => false
        );
        if ($notification["result"] != null) {
            $totalAmount = 0;
            foreach ($lastDeployments as $d) {
                $totalAmount += $d["amount"];
            }
            if ($totalDeployments > 0) {
                $resultNotification = $this->sendNotification(($method == 'delegate' ? "New Delegation(s)" : "New Undelegation(s)"), "Your received $totalDeployments ".($method == 'delegate' ? "delegation(s)" : "undelegation(s)") ." for a total of $totalAmount CSPR", env("NOTIFICATION_URL_LINK")."/deployments/".$notification["condition"]->parameters->validator, $notification["notificationToken"]);
                $result["notificationSend"] = $resultNotification;
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            }
        } else {
            $resultNotification = $this->sendNotification(($method == 'delegate' ? "Delegation alert register" : "Undelegation alert register"), "Your notification was successfully registered.", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
            $result["notificationSend"] = $resultNotification;
            Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
        }
    }
    /*
    * Check if a validator is down
    *
    */
    public function validatorDown($notification)
    {
        $lastEra = (StatsPerEra::where(array("validator" => $notification["condition"]->parameters->validator))->max("eraId")-2);
        $lastEraInfos = StatsPerEra::where(array("validator" => $notification["condition"]->parameters->validator, "eraId" => $lastEra))->first();
        $result = array(  "notificationSend" => false, "lastEra" => 0);
        if ($notification["result"] != null) {
            if ($lastEraInfos["apy"] == 0 && $lastEra != $notification["result"]->lastEra) {
                $resultNotification = $this->sendNotification("Validator Down", "Your validator doesnt have an apy at era $lastEra ", env("NOTIFICATION_URL_LINK")."/validator/".$notification["condition"]->parameters->validator, $notification["notificationToken"]);
                $result["notificationSend"] = $resultNotification;
                $result["lastEra"] = $lastEra;
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            }
        } else {
            $resultNotification = $this->sendNotification("Server down alert register", "Your notification was successfully registered.", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
            $result["notificationSend"] = $resultNotification;
            Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
        }
    }
    /**
    * Check if the number of delegators changed
    *
    */
    public function delegatorChanges($notification)
    {
        $lastEra = StatsPerEra::where(array("validator" => $notification["condition"]->parameters->validator))->max("eraId");
        $lastEraInfos = StatsPerEra::where(array("validator" => $notification["condition"]->parameters->validator, "eraId" => $lastEra))->first();
        $result = array(  "notificationSend" => false, "lastEra" => 0, "delegators" => 0);
        if ($notification["result"] != null) {
            if ($lastEraInfos["delegators"] != $notification["result"]->delegators && $lastEra != $notification["result"]->lastEra) {
                $diff = ($lastEraInfos["delegators"]-$notification["result"]->delegators);
                $resultNotification = $this->sendNotification("New number of delegators", "You have now ".$lastEraInfos["delegators"]." (".($diff > 0 ? "+".$diff:$diff)." since last era) ", env("NOTIFICATION_URL_LINK")."/validator/".$notification["condition"]->parameters->validator, $notification["notificationToken"]);
                $result["notificationSend"] = $resultNotification;
                $result["lastEra"] = $lastEra;
                $result["delegators"] = $lastEraInfos["delegators"];
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            }
        } else {
            $resultNotification = $this->sendNotification("Delegators change alert register", "Your notification was successfully registered.", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
            $result["notificationSend"] = $resultNotification;
            Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
        }
    }
    /**
    * Check if the fees of a validator have changed
    *
    */
    public function feesChange($notification)
    {
        $now = \carbon\Carbon::now();
        $lastFees = DelegationRate::where(array("validator" => $notification["condition"]->parameters->validator))->where('deploymentDate', '>=', ($notification["lastExecution"] ? $notification["lastExecution"]:$now))->first();

        $result = array(
        "notificationSend" => false
      );
        if ($notification["result"] != null) {
            if ($lastFees != null) {
                $resultNotification = $this->sendNotification("Delegation Rate Changes", "The validator ".substr($lastFees->validator, 0, 4). "..." .substr($lastFees->validator, -4) ." changes his delegation rate to : ".$lastFees->delegationRate."%", env("NOTIFICATION_URL_LINK")."/dashboard/".$notification["condition"]->parameters->validator, $notification["notificationToken"]);
                $result["notificationSend"] = $resultNotification;
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            }
        } else {
            $resultNotification = $this->sendNotification("Delegation Rate alert register", "Your notification was successfully registered.", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
            $result["notificationSend"] = $resultNotification;
            Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
        }
    }
    /**
    * Check if CSPR pump or dump
    *
    */
    public function casperPumpDump($method, $notification, $coingecko)
    {
        $result = array( "notificationSend" => false);
        $dateLastNotification = \carbon\Carbon::createFromDate($notification["lastExecution"]);
        if ($notification["result"] != null) {
            $resultNotification = false;
            if ($method == "pump" && $coingecko["market_data"]["price_change_percentage_24h"] > $notification["condition"]->parameters->percentage &&  $dateLastNotification->diffInMinutes(\carbon\Carbon::now()) > 10) {
                $resultNotification = $this->sendNotification("Casper $method !", "+".$coingecko["market_data"]["price_change_percentage_24h"]."% over the last 24 hours. Price is now : ".$coingecko["market_data"]["current_price"]["usd"]."$", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
                $result["notificationSend"] =$resultNotification;
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            }
            if ($method == "dump" && $coingecko["market_data"]["price_change_percentage_24h"] < ($notification["condition"]->parameters->percentage*-1) &&  $dateLastNotification->diffInMinutes(\carbon\Carbon::now()) > 10) {
                $resultNotification = $this->sendNotification("Casper $method !", $coingecko["market_data"]["price_change_percentage_24h"]."% over the last 24 hours. Price is now : ".$coingecko["market_data"]["current_price"]["usd"]."$", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
                $result["notificationSend"] =$resultNotification;
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            }
        } else {
            $resultNotification = $this->sendNotification("Casper $method  alert register", "Your notification was successfully registered.", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
            $result["notificationSend"] = $resultNotification;
            Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
        }
    }
    /**
    * Get the rewards earned
    *
    */
    public function casperRewards($notification)
    {
        if (!isset($notification["condition"]->parameters->delegator)) {
            return "";
        }
        $now = \carbon\Carbon::now();
        $maxEraId = Reward::where(array("delegator" => $notification["condition"]->parameters->delegator, "validator" =>$notification["condition"]->parameters->validator ))->max("eraId");
        $result = array(
        "notificationSend" => false,
        "lastEra" => $maxEraId
      );

        $frequence = 1;
        $label = "This era, ";
        if (isset($notification["condition"]->parameters->eraWhen) && $notification["condition"]->parameters->eraWhen =="every_day") {
            $frequence=12;
            $label = "The last day, ";
        } elseif (isset($notification["condition"]->parameters->eraWhen) &&  $notification["condition"]->parameters->eraWhen == "every_3days") {
            $frequence=36;
            $label = "The 3 last days, ";
        }
        if ($notification["result"] != null) {
            if ($maxEraId == ($notification["result"]->lastEra + $frequence)) {
                $total = Reward::where(array("delegator" => $notification["condition"]->parameters->delegator, "validator" =>$notification["condition"]->parameters->validator ))->where("eraId", ">", ($notification["result"]->lastEra))->sum("rewards");
                $resultNotification = $this->sendNotification("Rewards", $label."you've earned ".$total."CSPR from ".substr($notification["condition"]->parameters->validator, 0, 4). "..." .substr($notification["condition"]->parameters->validator, -4), env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
                $result["notificationSend"] = $resultNotification;
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            } elseif (($maxEraId - $notification["result"]->lastEra) > 36) {
                $total = Reward::where(array("delegator" => $notification["condition"]->parameters->delegator, "validator" =>$notification["condition"]->parameters->validator ))->where("eraId", ">", ($notification["result"]->lastEra))->sum("rewards");
                $resultNotification = $this->sendNotification("Rewards", "You've earned ".$total."CSPR from ".substr($notification["condition"]->parameters->validator, 0, 4). "..." .substr($notification["condition"]->parameters->validator, -4)." since the last run of the alert.", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
                $result["notificationSend"] = $resultNotification;
                Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
            }
        } else {
            $resultNotification = $this->sendNotification("Rewards alert register", "Your notification was successfully registered.", env("NOTIFICATION_URL_LINK"), $notification["notificationToken"]);
            $result["notificationSend"] = $resultNotification;
            Notification::where(array("id" => $notification["id"]))->update(array("result" => $result, "lastExecution" =>  \carbon\Carbon::now()));
        }
    }

    /**
     * Execute the console command.
     * Browse all the notifications registered by the users and send a notification if needed
     *
     * @return mixed
     */
    public function handle()
    {
        $allNotifications = Notification::get();
        $coingecko = json_decode(file_get_contents("https://api.coingecko.com/api/v3/coins/casper-network?localization=en&tickers=false&market_data=true&community_data=false&developer_data=false&sparkline=false"), true);
        foreach ($allNotifications as $notification) {
            if ($notification["condition"]->function == "test" && $notification["lastExecution"] == null) {
                $result = $this->sendNotification("Welcome", "Welcome to Casper Community", "https://analytics.caspercommunity.io", $notification["notificationToken"]);
                Notification::where(array("id" => $notification["id"]))->update(array("lastExecution" => now(), "result" => array("success" => $result)));
            } elseif ($notification["condition"]->function == "new_delegation") {
                $this->newDeployments($notification, "delegate");
            } elseif ($notification["condition"]->function == "new_undelegation") {
                $this->newDeployments($notification, "undelegate");
            } elseif ($notification["condition"]->function == "fees_change") {
                $this->feesChange($notification);
            } elseif ($notification["condition"]->function == "validator_down") {
                $this->validatorDown($notification);
            } elseif ($notification["condition"]->function == "delegators_change") {
                $this->delegatorChanges($notification, $coingecko);
            } elseif ($notification["condition"]->function == "casper_pump") {
                $this->casperPumpDump("pump", $notification, $coingecko);
            } elseif ($notification["condition"]->function == "casper_dump") {
                $this->casperPumpDump("dump", $notification, $coingecko);
            } elseif ($notification["condition"]->function == "rewards") {
                $this->casperRewards($notification);
            }
        }
    }
}
