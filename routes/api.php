<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\StatsPerEra;
use App\Delegation;
use App\Validator;
use App\Reward;
use App\DelegationRate;
use App\BlocksProcessed;
use App\Peer;
use App\Notification;
use App\Holder;

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DelegatorController;
use App\Http\Controllers\HolderController;
use App\Http\Controllers\ValidatorController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/holders', [HolderController::class, 'getHolders']);

Route::get('/validator/delegations/{validator}', [ValidatorController::class, 'getDeployments']);
Route::get('/validator/infos/{validator}', [ValidatorController::class, 'getInfos']);
Route::get('/validators', [ValidatorController::class, 'getValidators']);
Route::get('/validators/charts', [ValidatorController::class,'getValidatorsCharts']);
Route::get('/validator/charts/{validator}', [ValidatorController::class,'getValidatorCharts']);

Route::post('/notification', [NotificationController::class, 'addNotification']);
Route::get('notification/{token}', [NotificationController::class, 'getNotifications']);
Route::post('/notification/register-token', [NotificationController::class, 'registerToken']);
Route::delete('/notification/{notificationToken}/{id}', [NotificationController::class, 'deleteToken']);

Route::get('validators/list', [ValidatorController::class, 'getValidatorsList']);
Route::get('delegators/list', [DelegatorController::class, 'getDelegatorsList']);
