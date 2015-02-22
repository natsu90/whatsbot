<?php
use Jenssegers\Mongodb\Model as Eloquent;

class InboxMessage extends Eloquent {
	
	protected $table = 'inbox_message';

	protected $fillable = array('mynumber', 'from', 'id', 'type', 'time', 'name', 'body');

	public function isExist()
	{
		return !is_null($this->where('id', $this->id)->first());
	}
}