<?php
require_once __DIR__ . '/libs/worker_boot.php';

$payload = decryptPayload(getPayload());
fire($payload);

function fire($payload)
{
    $msg_id = $payload->msg_id;
    $time = $payload->time;

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
			$bot = ChatterBotFactory::create(ChatterBotType::CLEVERBOT);
    		$botSession = $bot->createSession();
    		$chat_msg = ChatterBotThought::make($body);
    		$msg = $botSession->think($chat_msg->message());
    		$msg = $msg->__toString();
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
}