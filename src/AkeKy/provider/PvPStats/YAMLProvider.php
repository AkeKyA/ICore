<?php

namespace AkeKy\provider\PvPStats;

use pocketmine\IPlayer;
use pocketmine\utils\Config;

use CrazedMiner\Main;

class YAMLProvider implements ProviderInterface{
    
    protected $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
        @mkdir($this->plugin->getDataFolder()."playerspvpstats/");
        $this->plugin->getLogger()->info("§r§aData PvPStats Provider set to §r§6YAML§r§a!");
    }
    
    public function getPlayer(IPlayer $player){
        $name = strtolower($player->getName());
        if($this->playerExists($player)){
            return (new Config($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml", Config::YAML))->getAll();
        }
        return null;
    }
    
    public function getData($player){
        $name = strtolower($player);
        if(file_exists($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml")){
            return (new Config($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml", Config::YAML))->getAll();
        }
        return null;
    }
    
    public function playerExists(IPlayer $player){
        $name = strtolower($player->getName());
        return file_exists($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml");
    }
    
    public function addPlayer(IPlayer $player){
        $name = strtolower($player->getName());
            return new Config($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml", Config::YAML, array(
                "name" => $name,
                "kills" => 0,
                "deaths" => 0
            ));
    }
    
    public function removePlayer(IPlayer $player){
        $name = strtolower($player->getName());
        if($this->playerExists($player)){
            @unlink($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml");
        }else {
            return null;
        }
    }
    
    public function updatePlayer(IPlayer $player, $type){
        $name = strtolower($player->getName());
        if($this->playerExists($player)) {
            @mkdir($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml");
            $data = new Config($this->plugin->getDataFolder()."playerspvpstats/".$name.".yml");
            $data->set($type, $data->getAll()[$type] + 1);
            return $data->save();
        }else{
            $this->addPlayer($player);
        }
    }
    
    public function close(){
        
    }

}
