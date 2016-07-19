<?php

namespace AkeKy\provider;

use pocketmine\IPlayer;
use AkeKy\ICore;

class SQLite3DataProvider implements DataProvider{

	/** @var ICore */
	protected $plugin;

	/** @var \SQLite3 */
	protected $database;


	public function __construct(ICore $plugin){
		$this->plugin = $plugin;
		if(!file_exists($this->plugin->getDataFolder() . "players.db")){
			$this->database = new \SQLite3($this->plugin->getDataFolder() . "players.db", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
			$resource = $this->plugin->getResource("sqlite3.sql");
			$this->database->exec(stream_get_contents($resource));
			fclose($resource);
		}else{
			$this->database = new \SQLite3($this->plugin->getDataFolder() . "players.db", SQLITE3_OPEN_READWRITE);
		}
	}

	public function getPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));

		$prepare = $this->database->prepare("SELECT * FROM players WHERE name = :name");
		$prepare->bindValue(":name", $name, SQLITE3_TEXT);

		$result = $prepare->execute();

		if($result instanceof \SQLite3Result){
			$data = $result->fetchArray(SQLITE3_ASSOC);
			$result->finalize();
			if(isset($data["name"]) and $data["name"] === $name){
				unset($data["name"]);
				$prepare->close();
				return $data;
			}
		}
		$prepare->close();

		return null;
	}

	public function isPlayerRegistered(IPlayer $player){
		return $this->getPlayer($player) !== null;
	}

	public function unregisterPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		$prepare = $this->database->prepare("DELETE FROM players WHERE name = :name");
		$prepare->bindValue(":name", $name, SQLITE3_TEXT);
		$prepare->execute();
	}

	public function registerPlayer(IPlayer $player, $hash){
		$name = trim(strtolower($player->getName()));
		$data = [
			"lastip" => null,
			"hash" => $hash
		];
		$prepare = $this->database->prepare("INSERT INTO players (name, lastip, hash) VALUES (:name, :lastip, :hash)");
		$prepare->bindValue(":name", $name, SQLITE3_TEXT);
		$prepare->bindValue(":lastip", null, SQLITE3_TEXT);
		$prepare->bindValue(":hash", $hash, SQLITE3_TEXT);
		$prepare->execute();

		return $data;
	}

	public function savePlayer(IPlayer $player, array $config){
		$name = trim(strtolower($player->getName()));
		$prepare = $this->database->prepare("UPDATE players SET lastip = :lastip, hash = :hash WHERE name = :name");
		$prepare->bindValue(":name", $name, SQLITE3_TEXT);
		$prepare->bindValue(":lastip", $config["lastip"], SQLITE3_TEXT);
		$prepare->bindValue(":hash", $config["hash"], SQLITE3_TEXT);
		$prepare->execute();
	}

	public function updatePlayer(IPlayer $player, $lastIP = null){
		$name = trim(strtolower($player->getName()));
		if($lastIP !== null){
			$prepare = $this->database->prepare("UPDATE players SET lastip = :lastip WHERE name = :name");
			$prepare->bindValue(":name", $name, SQLITE3_TEXT);
			$prepare->bindValue(":lastip", $lastIP, SQLITE3_TEXT);
			$prepare->execute();
		}
	}

	public function close(){
		$this->database->close();
	}
}
