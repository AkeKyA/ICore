<?php

namespace AkeKy\provider\PvPStats;

use pocketmine\utils\Config;
use pocketmine\IPlayer;

use AkeKy\ICore;

class MYSQLProvider implements ProviderInterface {

    protected $plugin;

    protected $database;

    public function __construct(ICore $plugin) {
        $this->plugin = $plugin;        
        $settings = $this->plugin->getConfig()->get("mysql-settings");
        
        if(!isset($settings["host"]) or !isset($settings["user"]) or !isset($settings["password"]) or !isset($settings["database"]) or !isset($settings["port"])) {
            $this->plugin->getLogger()->critical("Invalid MySQL Settings!");
            return;
        }
        
        $this->database = new \mysqli($settings["host"], $settings["user"], $settings["password"], $settings["database"], $settings["port"]);
        if($this->database->connect_error) {
            $this->plugin->getLogger()->critical("Couldn't connect to MySQL:" . $this->database->connect_error);
            return;
        }
        
        $resource = $this->plugin->getResource("mysqlpvpstats.sql");
        $this->database->query(stream_get_contents($resource));
        fclose($resource);
        $this->plugin->getLogger()->info("Data Provider set to MySQL!");
    }
    
    public function getPlayer(IPlayer $player){
        $name = strtolower($player->getName());
        $result = $this->database->query("SELECT * FROM pvp_stats WHERE name = '" . $this->database->escape_string($name). "'");
        if($result instanceof \mysqli_result){
            $data = $result->fetch_assoc();
            $result->free();
            if(isset($data["name"]) and strtolower($data["name"] === $name)){
                unset($data["name"]);
                return $data;
            }
        }
        return null;
    }
    
    public function getData($player){
        $name = strtolower($player);
        $result = $this->database->query("SELECT * FROM pvp_stats WHERE name = '" . $this->database->escape_string($name). "'");
        if($result instanceof \mysqli_result){
            $data = $result->fetch_assoc();
            $result->free();
            if(isset($data["name"]) and strtolower($data["name"] === $name)){
                unset($data["name"]);
                return $data;
            }
        }
        return null;
    }
    
    public function playerExists(IPlayer $player){
        if($this->getPlayer($player) !== null){
            return true;
        }
        return null;
    }
    
    public function addPlayer(IPlayer $player){
        $name = strtolower($player->getName());
        $this->database->query("INSERT INTO pvp_stats
            (name, kills, deaths)
            VALUES
            ('" . $this->database->escape_string($name) . "', '0', '0')
            ");
    }
    
    public function removePlayer(IPlayer $player){
        $name = strtolower($player->getName());
        if($this->playerExists($player)){
            $this->database->query("DELETE FROM pvp_stats WHERE name = '" . $name . "'");
        }else {
            return null;
        }
    }
    
    public function updatePlayer(IPlayer $player, $type){
        $name = strtolower($player->getName());
        $this->database->query("UPDATE pvp_stats SET " . $type . " = " . $type . " + 1 WHERE name = '" . $name . "'");
    }
    
    public function close(){
        
    }

}
