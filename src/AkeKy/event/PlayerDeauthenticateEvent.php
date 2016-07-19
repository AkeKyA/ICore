<?php

namespace AkeKy\event;

use pocketmine\event\Cancellable;
use pocketmine\Player;
use AkeKy\ICore;

class PlayerDeauthenticateEvent extends SimpleAuthEvent implements Cancellable{
	public static $handlerList = null;


	/** @var Player */
	private $player;

	/**
	 * @param ICore $plugin
	 * @param Player     $player
	 */
	public function __construct(ICore $plugin, Player $player){
		$this->player = $player;
		parent::__construct($plugin);
	}

	/**
	 * @return Player
	 */
	public function getPlayer(){
		return $this->player;
	}
}