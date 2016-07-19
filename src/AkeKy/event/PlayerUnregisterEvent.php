<?php

namespace AkeKy\event;

use pocketmine\event\Cancellable;
use pocketmine\IPlayer;
use AkeKy\ICore;

class PlayerUnregisterEvent extends SimpleAuthEvent implements Cancellable{
	public static $handlerList = null;

	/** @var IPlayer */
	private $player;

	/**
	 * @param ICore $plugin
	 * @param IPlayer    $player
	 */
	public function __construct(ICore $plugin, IPlayer $player){
		$this->player = $player;
		parent::__construct($plugin);
	}

	/**
	 * @return IPlayer
	 */
	public function getPlayer(){
		return $this->player;
	}
}