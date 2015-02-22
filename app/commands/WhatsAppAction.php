<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class WhatsAppAction extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'whatsapp:action';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description.';

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
		Cache::forget('whatsAppAction');
		Cache::forget('whatsAppActionInput');

		Cache::put('whatsAppAction', $this->argument('action'), 1);
		Cache::put('whatsAppActionInput', $this->argument('input'), 1);
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('action', InputArgument::REQUIRED, 'An example argument.'),
			array('input', InputArgument::OPTIONAL, 'An example argument.'),
		);
	}

}
