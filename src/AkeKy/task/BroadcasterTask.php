<?php

namespace AkeKy\task;

use pocketmine\scheduler\PluginTask;
use AkeKy\ICore;

class BroadcasterTask extends PluginTask{
    private $messages;

    public function __construct(ICore $plugin){
        parent::__construct($plugin);
        $this->plugin = $plugin;
		$this->length = -1;
        $this->messages = $this->plugin->getConfig()->get("messages");
    }

    public function onRun($currentTick){
    	$this->length = $this->length + 1;
    	$messages = $this->messages;
    	$messagekey = $this->length;
    	$message = $messages[$messagekey];
    	if($this->length == count($messages) - 1){
            $this->length = -1;
        }
        $this->plugin->getServer()->broadcastMessage($message);
    }
}