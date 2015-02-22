<?php 

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class WhatsAppStart extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'whatsapp:start';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Start WhatsApp.';

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

	public function fire()
	{

		$data = array(
				'username' => getenv('WHATSAPP_USER'),
				'password' => getenv('WHATSAPP_PASS'),
				'identity' => getenv('WHATSAPP_USER'),
				'manager' => getenv('WHATSAPP_ADMIN'),
			);

    	$w = new WhatsProt($data['username'], $data['identity'], 'EloadMy', true);

		$w->connect();
		$w->loginWithPassword($data['password']);

		$w->PollMessage();

		// dont save if msg id already exist
		InboxMessage::creating(function($inbox_msg) {

			return !$inbox_msg->isExist();
		});

		// process inbox message
		InboxMessage::created(function($inbox_msg) {

			$msg_id = (string) $inbox_msg->_id;

			$time = time();

			Queue::push(function($job) use($msg_id, $time) {

				$inbox_msg = InboxMessage::find($msg_id);
				$from = str_replace(array("@s.whatsapp.net","@g.us"), "", $inbox_msg->from);
				$body = trim($inbox_msg->body);
				$kw_cmd = explode(' ',$body);

				// start response timeout
				if($time - $inbox_msg->time > 60)

					$msg = 'Response Timeout: '.$body;

				else {

					// start solat user
					if(strtoupper($kw_cmd[0]) == 'SOLAT') {

						$input = isset($kw_cmd[1]) ? strtoupper($kw_cmd[1]) : false;

						$user = SolatUser::where('phone_number',$from)->first();
					
						if(is_null($user)) {

							$user = new SolatUser;
							$user->phone_number = $from;
							$user->state = 'SGR';
							$user->zone = '03';
							$user->save();
						}
						
						if($input && $input == 'SET') {

							$user->state = null;
							$user->zone = null;
							$user->save();
							$msg = 'Hantar SOLAT<jarak>[kod negeri]'.$user->getStateCodeMsg();

						} else if(is_null($user->state)) {

							$msg = 'Hantar SOLAT<jarak>[kod negeri]'.$user->getStateCodeMsg();

							if($input && $user->isSolatStateValid($input)) {
								$user->state = $input;
								$user->save();
								$msg = 'Hantar SOLAT<jarak>[kod zon]'.$user->getZoneCodeMsg($user->state);
							}

						} else if(is_null($user->zone)) {

							$msg = 'Hantar SOLAT<jarak>[kod zon]'.$user->getZoneCodeMsg($user->state);

							if($input && $user->isSolatZoneValid($user->state, $input)) {
								$user->zone = $input;
								$user->save();
								$msg = $user->getWaktuSolatMsg($user->state,$user->zone);
							}

						} else {

							$msg = $user->getWaktuSolatMsg($user->state,$user->zone);

						}
					}
					// end solat user
					else 
					{
						$msg = false;
						if(Cache::has('chatter_'.$from))
							Cache::put('chatter_reply_'.$from, $body, 5);
						else
							Queue::push('WhatsAppBot@chat', array('user' => $from, 'msg_id' => $msg_id));
					}
				}
				// end response timeout

				// reply inbox msg
				if(isset($msg) && $msg) {
					$outbox_msg = new OutboxMessage;
					$outbox_msg->to = $from;
					$outbox_msg->message = $msg;
					$outbox_msg->is_sent = 0;
					$outbox_msg->inbox_id = $msg_id;
					$outbox_msg->save();
				}

				$job->delete();
			});
		});

		$w->eventManager()->bind("onGetMessage", function($mynumber, $from, $id, $type, $time, $name, $body) {
			$inbox_msg = InboxMessage::create(array(
					'mynumber' => $mynumber,
					'from' => $from,
					'id' => $id,
					'type' => $type,
					'time' => $time,
					'name' => $name,
					'body' => $body
			));
/*
			$outbox_msg = new OutboxMessage;
			$outbox_msg->to = str_replace(array("@s.whatsapp.net","@g.us"), "", $from);;
			$outbox_msg->message = "Sorry we can not reply your message accordingly at the moment because of technical issue.\n- sulaiman@derp.com.my";
			$outbox_msg->is_sent = 0;
			$outbox_msg->save();
*/	
			Log::info('I got your msg, '.$body.' from '. $from. ' at '.$time);
		});

		// forward image to admin
		$w->eventManager()->bind("onGetImage", function($mynumber, $from, $id, $type, $time, $name, $size, $url, $file, $mimetype, $filehash, $width, $height, $preview) use ($w, $data) {

			$w->sendMessageImage($data['manager'], $url, false, $size, $filehash);
		});

		// todo // init something before user send message
		$w->eventManager()->bind("onMessageComposing", function($mynumber, $from, $id, $type, $time) {

			DB::collection('chatstate')->insert(array('time' => date('d/m/Y H:i:s'), 'from' => $from));
		});

		$time = time(); $start_time = time();

		while(true) {

			sleep(1);
			$w->PollMessage();

			$this->sendMessages($w, $start_time);

			if(time() - $time >= 10) {

				$w->sendActiveStatus();
				$time = time();

				// whatsapp command action
				$whatsAppAction = Cache::get('whatsAppAction', false);

				if($whatsAppAction) {

					$whatsAppActionInput = Cache::get('whatsAppActionInput', false);

					Cache::forget('whatsAppAction');
					Cache::forget('whatsAppActionInput');

					switch(strtolower($whatsAppAction))
					{
						case 'updatestatus':
							$w->sendStatusUpdate($whatsAppActionInput);
							break;
						case 'setprofilepic':
							try {
								$w->sendSetProfilePicture($whatsAppActionInput);
							} catch (Exception $e) {
								Log::error($e->getTraceAsString());
							}
							break;
						case 'send':
							$str = explode('=',$whatsAppActionInput,2);
							if(count($str) <= 1)
								break;
							$from = $str[0];
							$msg = $str[1];
							$outbox_msg = new OutboxMessage;
							$outbox_msg->to = $from;
							$outbox_msg->message = $msg;
							$outbox_msg->is_sent = 0;
							$outbox_msg->save();
							break;
						case 'stop':
							$w->disconnect();
							exit('whatsapp is stopped');
					}
				}
				// end whatsapp command action
			}
		}  

	}

	// send all outbox messages
	protected function sendMessages($w, $start_time)
	{
		$outbox_msgs = OutboxMessage::where('is_sent', 0)->get();

		foreach($outbox_msgs as $outbox_msg)
		{
			$to = $outbox_msg->to;
			// todo // sync contact on created outboxmessage event and allow send message only when contact synced
			$user = Contacts::where('phone_number', $to)->first();

			if(is_null($user)) {

				$user = new Contacts;
				$user->phone_number = $to;
				$user->name = 'Unknown';
				if(isset($outbox_msg->inbox_id) && $outbox_msg->inbox_id) {
					$inbox_msg = InboxMessage::find($outbox_msg->inbox_id);
					$user->name = $inbox_msg->name;
				}
				$user->save();

				$w->sendSync(array($to));
				$w->sendPresenceSubscription($to);

			} else if(strtotime($user->updated_at) < $start_time) {

				$w->sendPresenceSubscription($to);
				$user->touch();
			}

			$w->sendMessageComposing($to);

			$outbox_msg->is_sent = 1;
			$outbox_msg->save();

			$w->sendMessagePaused($to);
			$w->sendMessage($to, $outbox_msg->message);
		}
	}
}
