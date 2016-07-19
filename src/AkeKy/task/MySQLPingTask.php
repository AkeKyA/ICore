<?php

namespace AkeKy\task;

use pocketmine\scheduler\PluginTask;
use AkeKy\ICore;

class MySQLPingTask extends PluginTask{

	/** @var \mysqli */
	private $database;

	public function __construct(ICore $owner, \mysqli $database){
		parent::__construct($owner);
		$this->database = $database;
	}

	public function onRun($currentTick){
		$this->database->ping();
	}
}