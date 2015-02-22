<?php
use Jenssegers\Mongodb\Model as Eloquent;
use GuzzleHttp\Client;

class SolatUser extends Eloquent {
	
	protected $table = 'solat_user';

	protected $fillable = array('phone_number', 'state', 'zone');

	public function getStateCodeMsg()
	{
		$states = DB::collection('solat_state_zone')->get();

		$msg = "\n----------\n";

		$msg .= implode("\n", array_map(function($state){

			return $state['code'] .' => '.$state['name'];
		}, $states));

		$msg .= "\n----------";

		return $msg;
	}

	public function getZoneCodeMsg($stateCode)
	{
		$state = DB::collection('solat_state_zone')->where('code', $stateCode)->first();

		$msg = "\n----------\n";

		$msg .= implode("\n", array_map(function($zone){

			return $zone['code'] .' => '.$zone['name'];
		}, $state['zone']));

		$msg .= "\n----------";

		return $msg;

	}

	public function isSolatStateValid($stateCode)
	{
		return !is_null(DB::collection('solat_state_zone')->where('code',strtoupper($stateCode))->first());
	}

	public function isSolatZoneValid($stateCode, $zoneCode)
	{
		return !is_null(DB::collection('solat_state_zone')->where('code',$stateCode)->where('zone.code',$zoneCode)->first());
	}

	public function getWaktuSolatMsg($stateCode, $zoneCode)
	{
		$msg = "eSolat JAKIM : Waktu Solat Hari Ini\n";

		$client = new Client();

		$response = $client->get('http://www2.e-solat.gov.my/xml/today/?zon='.strtoupper($stateCode).$zoneCode);

		$xml = $response->xml();

		if(!isset($xml->channel) || $xml->channel->link == "")
			return 'Maaf terdapat ralat pada sistem JAKIM. Sila cuba sekali lagi.';

		$msg .= "Kawasan: ".$xml->channel->link."\n";

		foreach($xml->channel->item as $item)
		{
			$msg.=$item->title .": ".$item->description."\n";
		}

		$msg .="\nHantar 'SOLAT SET' utk menukar kawasan\n";

		$msg .="\nDeveloped by sulaiman@derp.com.my";

		return $msg;
	}
}