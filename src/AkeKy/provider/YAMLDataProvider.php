<?php

namespace AkeKy\provider;

use pocketmine\IPlayer;
use pocketmine\utils\Config;
use AkeKy\ICore;

class YAMLDataProvider implements DataProvider{

	/** @var ICore */
	protected $plugin;

	public function __construct(ICore $plugin){
		$this->plugin = $plugin;
		if(!file_exists($this->plugin->getDataFolder() . "players/")){
			@mkdir($this->plugin->getDataFolder() . "players/");
		}
	}

	public function getPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		if($name === ""){
			return null;
		}
		$path = $this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml";
		if(!file_exists($path)){
			return null;
		}else{
			$config = new Config($path, Config::YAML);
			return $config->getAll();
		}
	}

	public function isPlayerRegistered(IPlayer $player){
		$name = trim(strtolower($player->getName()));

		return file_exists($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
	}

	public function unregisterPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		@unlink($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
	}

	public function registerPlayer(IPlayer $player, $hash){
		$name = trim(strtolower($player->getName()));
		@mkdir($this->plugin->getDataFolder() . "players/" . $name{0} . "/");
		$data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
		$data->set("lastip", null);
		$data->set("hash", $hash);
		$data->save();

		return $data->getAll();
	}

	public function savePlayer(IPlayer $player, array $config){
		$name = trim(strtolower($player->getName()));
		$data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
		$data->setAll($config);
		$data->save();
	}

	public function updatePlayer(IPlayer $player, $lastIP = null){
		$data = $this->getPlayer($player);
		if($data !== null){
			if($lastIP !== null){
				$data["lastip"] = $lastIP;
			}
			$this->savePlayer($player, $data);
		}
	}

	public function close(){

	}
}