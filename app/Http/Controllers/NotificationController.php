<?php

namespace App\Http\Controllers;

use App\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    //
    public function addNotification(Request $request)
    {
        if (!isset($request->notificationToken)) {
            return array("error" => true, "message" => "no_notification_token_found");
        }
        if (isset($request->notificationToken) && $request->notificationToken == null) {
            return array("error" => true, "message" => "notification_token_null");
        }
        if (!isset($request->condition)) {
            return array("error" => true, "message" => "no_condition_found");
        }
        if (!isset($request->condition["function"])) {
            return array("error" => true, "message" => "no_function_found");
        }
        if (!isset($request->condition["parameters"])) {
            return array("error" => true, "message" => "no_parameters_found");
        }
        if (!isset($request->condition["parameters"]["validator"]) && $request->condition["function"] != "casper_dump" && $request->condition["function"] != "casper_pump") {
            return array("error" => true, "message" => "no_parameter_validator_found");
        }
        if (!isset($request->condition["parameters"]["percentage"]) && ($request->condition["function"] == "casper_dump" || $request->condition["function"] == "casper_pump")) {
            return array("error" => true, "message" => "no_parameter_percentage_found");
        }
        if (!isset($request->condition["parameters"]["delegator"]) && $request->condition["function"] == "rewards") {
            return array("error" => true, "message" => "no_parameter_delegator_found");
        }
        if (!Notification::where(array("id" => $request->id))->exists()) {
            Notification::create(array("notificationToken" => $request->notificationToken, "condition" => json_encode($request->condition)));
            return array("error" => false, "message" => "notification_created");
        } else {
            Notification::where(array("id" => $request->id))->update(array("condition" => $request->condition, "lastExecution" => null, "result" => null));
            return array("error" => false, "message" => "notification_updated");
        }
    }
    public function registerToken(Request $request)
    {
        if (!isset($request->notificationToken)) {
            return array("error" => true, "message" => "no_notification_token_found");
        }
        $data = explode(":", $request->notificationToken);
        Notification::where('notificationToken', 'like', $data[0].'%')->update(array("notificationToken" => $request->notificationToken));
        return array("error" => false, "message" => "token_registered");
    }
    public function deleteToken($notificationToken, $id, Request $request)
    {
        if (!Notification::where(array("notificationToken" => $request->notificationToken, "id" => $id))->exists()) {
            return array("error" => true, "message" => "notification_not_exists");
        } else {
            Notification::where(array("notificationToken" => $request->notificationToken, "id" => $id))->delete();
            return array("error" => false, "message" => "notification_removed");
        }
    }
    public function getNotifications(String $token, Request $request, Response $response)
    {
        return Notification::where(array("notificationToken" => $token))->get();
    }
}
