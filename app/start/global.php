<?php

/*
|--------------------------------------------------------------------------
| Register The Laravel Class Loader
|--------------------------------------------------------------------------
|
| In addition to using Composer, you may use the Laravel class loader to
| load your controllers and models. This is useful for keeping all of
| your classes in the "global" namespace without Composer updating.
|
*/

ClassLoader::addDirectories(array(

	app_path().'/commands',
	app_path().'/controllers',
	app_path().'/models',
	app_path().'/database/seeds',

));

/*
|--------------------------------------------------------------------------
| Application Error Logger
|--------------------------------------------------------------------------
|
| Here we will configure the error logger setup for the application which
| is built on top of the wonderful Monolog library. By default we will
| build a basic log file setup which creates a single file for logs.
|
*/

Log::useFiles(storage_path().'/logs/laravel.log');

/*
|--------------------------------------------------------------------------
| Application Error Handler
|--------------------------------------------------------------------------
|
| Here you may handle any errors that occur in your application, including
| logging them or displaying custom views for specific errors. You may
| even register several error handlers to handle different types of
| exceptions. If nothing is returned, the default error view is
| shown, which includes a detailed stack trace during debug.
|
*/

App::error(function(Exception $exception, $code)
{
	Log::error($exception);
});

/*
|--------------------------------------------------------------------------
| Maintenance Mode Handler
|--------------------------------------------------------------------------
|
| The "down" Artisan command gives you the ability to put an application
| into maintenance mode. Here, you will define what is displayed back
| to the user if maintenance mode is in effect for the application.
|
*/

App::down(function()
{
	return Response::make("Be right back!", 503);
});

/*
|--------------------------------------------------------------------------
| Require The Filters File
|--------------------------------------------------------------------------
|
| Next we will load the filters file for the application. This gives us
| a nice separate location to store our route and application filter
| definitions instead of putting them all in the main routes file.
|
*/

require app_path().'/filters.php';

Dotenv::load(base_path());

class WhatsAppBot
{
	public function chat($job, $data)
	{

		$to = $data['user'];
		$msg_id = $data['msg_id'];	
		$sendMessage = function($msg) use($to, $msg_id) {
			$outbox_msg = new OutboxMessage;
			$outbox_msg->to = $to;
			$outbox_msg->message = $msg;
			$outbox_msg->is_sent = 0;
			$outbox_msg->inbox_id = $msg_id;
			$outbox_msg->save();
		};
		$inbox_msg = InboxMessage::find($msg_id);

		Cache::put('chatter_'.$to, 'true', 6);

		Cache::put('chatter_reply_'.$to, $inbox_msg->body, 5);

    	$bot = ChatterBotFactory::create(ChatterBotType::CLEVERBOT);//ChatterBotType::PANDORABOTS, ChatterBotType::PANDORABOTS_DEFAULT_ID
    	$botSession = $bot->createSession();

		$startChatter = true; $startChatterTime = time(); $timeout = 60 * 5;

		while($startChatter) 
		{
			if(Cache::has('chatter_reply_'.$to)) {
				$startChatterTime = time();
				
				$chat_msg = ChatterBotThought::make(Cache::get('chatter_reply_'.$to));
				$msg = $botSession->think($chat_msg->message());
				$sendMessage($msg->__toString());
				
				Cache::forget('chatter_reply_'.$to);
				// while loop is taking alot memory
				$startChatter = false;
			}

			if(time() - $startChatterTime > $timeout) {

				$sendMessage('Okay, I am out of here.');
				$startChatter = false;
			}
		}

		Cache::forget('chatter_'.$to);
		$job->delete();
	}
}