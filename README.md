
<p align="center"><a href="https://analytics.caspercommunity.io" target="_blank"><img src="https://analytics.caspercommunity.io/assets/icon/android-chrome-512x512.png" width="150"></a></p>

## About Casper Analytics API

With this API you can retrieve informations from the Casper's blockchain.
It's build with [Laravel](https://laravel.com/docs) and follow the Laravel's standards.

You'll find :
- Database Scripts
- Jobs
- Routes

## How to install

Step by step guide of How-To install the API


### Install dependecies
```
composer install
```

### .env

Copy the file **.env.example** to **.env**.
First define the MySQL's connection :
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```
Then specify the parameters of the Casper's blockchain :
```
CASPER_CHAIN="casper"
RPC_ENDPOINT="https://..../rpc"
CSPR_LIVE_API="https://api.cspr.live"
CORS_URL="https://..."
FIREBASE_SERVER_KEY="..."
NOTIFICATION_ICON="https://analytics.caspercommunity.io/assets/icon/favicon-32x32.png"
NOTIFICATION_URL_LINK="https://analytics.caspercommunity.io"
```
##### CASPER_CHAIN

It can be "casper" of "casper-test" depending if you want to connect to the Mainnet of the Testnet

##### RPC_ENDPOINT

Specify an RPC endpoint to retrieve the info from the casper's blockchain.
It should be a valid domain name otherwise you'll have CORS issues.

##### CSPR_LIVE_API

The API of [CSPR.live](https://cspr.live) to retrieve the actual supply of the CSPR's token

##### CORS_URL

If you have CORS issues while retrieving the peers, you can setup [CORS Anywhere](https://github.com/Rob--W/cors-anywhere) and deploy it on [Heroku](https://heroku.com/).
Then specify the URL in the .env file.

##### FIREBASE_SERVER_KEY

If you want to use the notifications's system, check the Firebase documentation.

##### NOTIFICATION_ICON

Notification icon when a notification is send

##### NOTIFICATION_URL_LINK

Notification URL when a notification is send

### Build the Database
If the configuration parameters of the database are correctly set, these commands will create the necessary tables.
```
cd /path/to/api
php artisan migrate
```

### Configure the CORS

When you deploy the API on the web, configure the CORS file to allow the requests from your domain.
Check the file **/config/cors.php**

### Activate scheduling

Check the [Task Scheduling](https://laravel.com/docs/8.x/scheduling#running-the-scheduler) documentation to activate the jobs and start retrieve data from the Casper's blockchain.

## Run the API

```
cd /path/to/api
php artisan serve
```
## Jobs

### api:GetBlockInfo
Get the last blocks from the blockchain and retrieve the data

### api:ProcessHistory
Similar to the the GetBlockInfo's job, it retrieve historical data from the last block found to the block 0. It's running by block of 5000 block to not overload the server.

### api:GetEraRewards
When there is a switch-block, we retrieve informations of the Era for each validators

### api:CheckNotifications
Check if a notification should be send

### api:GetAccountInfo
Retrieve the information of the account (Account Hash and mainpurse)

### api:GetAccountBalance
Retrieve the balance of the account not yet processed and also the balance of the account not updated since more than a week.

### api:GetPeers
Retrieve all the peers informations

### api:GetValidators
Retrieve the validators informations

## Testing

Once you have run at least each jobs one time, you can run the tests via the command line

```
php artisan test
```

## Routes

The routes where specially define for the associated application "Casper Analytics"
```
GET /holders

GET /validator/delegations/{validator}
GET /validator/infos/{validator}
GET /validators
GET /validators/charts
GET /validator/charts/{validator}

POST /notification
GET /notification/{token}
POST /notification/register-token
DELETE /notification/{notificationToken}/{id}

GET /validators/list
GET /delegators/list
```

## Configure the notifications service

Check the [Firebase documentation](http://firebase.google.com/)

## License

The Casper Analytics API is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
