<?php
use Jenssegers\Mongodb\Model as Eloquent;

class Contacts extends Eloquent {

	protected $table = 'contacts';

	protected $fillable = array('phone_number', 'name');

}