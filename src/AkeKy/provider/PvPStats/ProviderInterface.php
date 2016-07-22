<?php

namespace AkeKy\provider\PvPStats;

use pocketmine\IPlayer;

interface ProviderInterface {

    public function getPlayer(IPlayer $player);
    
    public function getData($player);
    
    public function playerExists(IPlayer $player);
    
    public function addPlayer(IPlayer $player);
    
    public function removePlayer(IPlayer $player);
    
    public function updatePlayer(IPlayer $player, $type);
    
    public function close();

}
