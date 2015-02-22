<?php
use Jenssegers\Mongodb\Model as Eloquent;

class OutboxMessage extends Eloquent {
	
	protected $table = 'outbox_message';

	protected $fillable = array('to', 'message', 'is_sent', 'inbox_id');

}