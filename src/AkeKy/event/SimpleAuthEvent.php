<?php

namespace AkeKy\event;

use pocketmine\event\plugin\PluginEvent;
use AkeKy\ICore;

abstract class SimpleAuthEvent extends PluginEvent{

	/**
	 * @param ICore $plugin
	 */
	public function __construct(ICore $plugin){
		parent::__construct($plugin);
	}
}