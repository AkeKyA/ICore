<?php
namespace AkeKy;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;


class icore extends PluginBase implements Listener{

	public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->canclebp = $this->getConfig()->get("block-CancleBP");
		$this->blocknoupdate = $this->getConfig()->get("block-NoUpdate");
		$this->worldnopvp = $this->getConfig()->get("world-NoPvP");
        $this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        $this->getLogger()->info("§aPvPWorlds loaded!");
        $this->getLogger()->info("§aBlockFreezer loaded!");
        $this->getLogger()->info("§aKillForMoney loaded!");
        $this->getLogger()->info("§aSimpleAuth loaded!");
        $this->getLogger()->info("§aVIPSlots loaded!");
        $this->getLogger()->info("§aBroadcaster loaded!");
        $this->getLogger()->info("§aChatDefender loaded!");
        $this->getLogger()->info("§aLevelManager loaded!");
        $this->getLogger()->info("§cE§6v§ee§ar§by§dt§ch§6i§en§ag §floaded!");
	}

	public function onDisable(){

	}

    public function onPlayerLogin(PlayerLoginEvent $event){
        $event->getPlayer()->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
    }

	public function onPlayerMove(PlayerMoveEvent $event){
        if($event->isCancelled() or $event->getPlayer()->isCreative() or $event->getPlayer()->isSpectator() or $event->getPlayer()->getAllowFlight() or $event->getPlayer()->hasEffect(Effect::JUMP)){
            return;
        }else{
            if($event->getPlayer()->getInAirTicks() >= 60){
                $event->getPlayer()->kick('§l§cYou have been kicked for fly hacks!§r §o§6Please disable mods to play.§r', false);
            }
        }
    }

	public function onBlockBreak(BlockBreakEvent $event){
        if(in_array($event->getPlayer()->getLevel()->getFolderName(), $this->canclebp)){
            if(!$event->getPlayer()->isOp()){
                $event->setCancelled(true);
            }
        }
	}

	public function onBlockPlace(BlockPlaceEvent $event){
        if(in_array($event->getPlayer()->getLevel()->getFolderName(), $this->canclebp)){
            if(!$event->getPlayer()->isOp()){
                $event->setCancelled(true);
            }
        }
	}

    public function onBlockUpdate(BlockUpdateEvent $event){
        if(in_array($event->getBlock()->getId(), $this->blocknoupdate)){
            $event->setCancelled(true);
        }
    }

    public function onPvP(EntityDamageEvent $event){
    	if($event instanceof EntityDamageByEntityEvent){
    		if($event->getEntity() instanceof Player && $event->getDamager() instanceof Player){
    			if(in_array($event->getEntity()->getLevel()->getFolderName(), $this->worldnopvp)){
    				$event->setCancelled(true);
    			}
    		}
    	}
    }

    public function onPlayerDeath(PlayerDeathEvent $event){
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent){
            $killer = $cause->getDamager();
            if($killer instanceof Player){
                $this->economy->addMoney($killer->getName(), 100);
                $killer->sendMessage('§b- §eYou earn §a100 §bCoins§e.');
                $killer->setHealth(20);
            }
        }
    }

    public function playerBlockTouch(PlayerInteractEvent $event){
        if($event->getBlock()->getID() === 323 || $event->getBlock()->getID() === 63 || $event->getBlock()->getID() === 68){
            $sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
            if(!($sign instanceof Sign)){
                return;
            }
            $sign = $sign->getText();
            if($sign[0] === '§l§aWORLD'){
                if(empty($sign[1]) !== true){
                    $mapname = $sign[1];
                    if($this->getServer()->loadLevel($mapname) !== false){
                        $event->getPlayer()->sendMessage('§aTeleporting...');
                        $event->getPlayer()->teleport($this->getServer()->getLevelByName($mapname)->getSafeSpawn());
                    }else{
                        $event->getPlayer()->sendMessage("[SignPortal] World '".$mapname."' not found.");
                    }
                }
            }
        }
    }

    public function tileupdate(SignChangeEvent $event){
        if($event->getBlock()->getID() === 323 || $event->getBlock()->getID() === 63 || $event->getBlock()->getID() === 68){
            $sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
            if(!($sign instanceof Sign)){
                return true;
            }
            $sign = $event->getLines();
            if($sign[0] === '§l§aWORLD'){
                if($event->getPlayer()->isOp()){
                    if(empty($sign[1]) !== true){
                        if($this->getServer()->loadLevel($sign[1]) !== false){
                            $event->getPlayer()->sendMessage("[SignPortal] Portal to world '".$sign[1]."' created");
                            return true;
                        }
                        $event->getPlayer()->sendMessage("[SignPortal] World '".$sign[1]."' does not exist!");
                        $event->setLine(0,"[BROKEN]");
                        return false;
                    }
                    $event->getPlayer()->sendMessage("[SignPortal] World name not set");
                    $event->setLine(0,"[BROKEN]");
                    return false;
                }
                $event->getPlayer()->sendMessage("[SignPortal] You must be an OP to make a portal");
                $event->setLine(0,"[BROKEN]");
                return false;
            }
        }
        return true;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        switch($command->getName()){
            case "rank":
                $sender->sendMessage(' ');
                $sender->sendMessage('§fยศทั้งหมดที่มีในเซิฟ');
                $sender->sendMessage('§aVIP          §6100 True   §f/tp');
                $sender->sendMessage('§aVIP§b+       §6200 True   §f/tp  /fly');
                $sender->sendMessage('§aUltraVIP     §6300 True   §f/tp  /fly  /repair');
                $sender->sendMessage('§aUltraVIP§b+  §6400 True   §f/tp  /fly  /repair  /enchant');
                $sender->sendMessage('§bยศทุกยศสามารถเข้าตอนเต็มได้น่ะคับ ^3^');
                $sender->sendMessage('§a**§eหากโดนแบนเพราะทำผิดหรือใช้มอดในการเล่น');
                $sender->sendMessage('§e  จะไม่มีการคืนเงินใดๆทั้งสิ้น');
                $sender->sendMessage('§aหากเติมไม่ครบตามราคา จะไม่ได้ยศใดๆ ทั้งสิ้น');
                break;
            case "vip":
                $sender->sendMessage(' ');
                $sender->sendMessage(' ');
                $sender->sendMessage('§a=========================================');
                $sender->sendMessage('§b                       วิธีการเติม §6VIP                        ');
                $sender->sendMessage('§a1. §fเข้าเว็บ §aj.mp/orpetrue');
                $sender->sendMessage('§a2. §fช่องแรกให้ใส่รหัสบัตร §cTrue§6Money §fซึ่งจะมีทั้งหมด §e14 §fหลัก');
                $sender->sendMessage('   §fราคาบัตรต้องให้ครบตามราคายศที่ระบุไว้');
                $sender->sendMessage('§a3. §fช่องสองให้ใส่ชื่อในเกมที่ใช้ในเซิฟ เช่น §bMrAkeKyYT §fเป็นต้น');
                $sender->sendMessage('§a4. §fช่องสามให้ใส่ยศที่ต้องการจะซื้อ เช่น ถ้าต้องการยศ VIP ก็ให้ใส่ยศ §bVIP §fลงไปในช่อง');
                $sender->sendMessage('§a5. §fช่องสี่ให้ใส่ IP และ Port ของเซิฟที่ต้องการเติม');
                $sender->sendMessage('§a6. §fโปรดตรวจสอบข้อมูลที่ใส่ ว่าถูกต้องหรือไม่ §bจากนั้นให้กด §eเติมเงิน');
                $sender->sendMessage('   §aใครอ่านไม่หมดให้ปรับ §bGUI Scale §aในตั้งค่าให้เล็กที่สุด');
                $sender->sendMessage('   §fหลังจากกดเติมเงินไปกรุณารอ 7-8 ชม.');
                $sender->sendMessage('§a=========================================');
                break;
            case "ru":
                $sender->sendMessage(' ');
                $sender->sendMessage('§a=========================================');
                $sender->sendMessage('§b                       กฏของเซิฟ                        ');
                $sender->sendMessage('§a1. §fห้ามใช้มอดหรือโปรแกรมช่วยเล่น');
                $sender->sendMessage('§a2. §fห้ามก่อความลำคาญ หรือก่อกวนผู้อื่น');
                $sender->sendMessage('§a2. §fห้ามฟลัดข้อความหรือส่งข้อความรัวๆ');
                $sender->sendMessage('§a3. §fห้ามขอยศหรือขอ op');
                $sender->sendMessage('§a4. §fห้ามพูดคำหยาบ ด่าคนอื่น');
                $sender->sendMessage('§a5. §fห้ามโปรโมทเซิฟอื่น');
                $sender->sendMessage('§a** ถ้าทำผิดแบนถาวร VIP ทำผิดก็แบนถาวรน่ะครับ^^');
                $sender->sendMessage('§a=========================================');
                break;
            case "spawn":
                $sender->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
                $sender->sendMessage('§aTeleporting.....');
                break;
            case "fe":
                $sender->setAllowFlight(true);
                $sender->sendMessage('§aYou have enabled fly mode!');
                break;
            case "fd":
                $sender->setAllowFlight(false);
                $sender->sendMessage('§cYou have disabled fly mode!');
                break;
            case "ve":
                $effect = Effect::getEffect(Effect::INVISIBILITY);
                $effect->setDuration(0x7fffffff);
                $effect->setAmplifier(0);
                $effect->setVisible(false);
                $sender->addEffect($effect);
                $sender->sendMessage('§eHidden §aEnable§f!');
                break;
            case "vd":
                $sender->removeEffect(Effect::INVISIBILITY);
                $sender->sendMessage('§eHidden §cDisable§f!');
                break;
        }
        return false;
    }
}
