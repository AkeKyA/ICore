<?php

namespace AkeKy;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Effect;
use pocketmine\IPlayer;
use pocketmine\utils\Config;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use AkeKy\event\PlayerAuthenticateEvent;
use AkeKy\event\PlayerDeauthenticateEvent;
use AkeKy\event\PlayerRegisterEvent;
use AkeKy\event\PlayerUnregisterEvent;
use AkeKy\provider\DataProvider;
use AkeKy\provider\DummyDataProvider;
use AkeKy\provider\MySQLDataProvider;
use AkeKy\provider\SQLite3DataProvider;
use AkeKy\provider\YAMLDataProvider;

class ICore extends PluginBase{

    /** @var PermissionAttachment[] */
    protected $needAuth = [];

    /** @var EventListener */
    protected $listener;

    /** @var DataProvider */
    protected $provider;

    protected $blockPlayers = 6;
    protected $blockSessions = [];

    /** @var string[] */
    protected $messages = [];

    /**
     * @api
     *
     * @param Player $player
     *
     * @return bool
     */
    public function isPlayerAuthenticated(Player $player){
        return !isset($this->needAuth[spl_object_hash($player)]);
    }

    /**
     * @api
     *
     * @param IPlayer $player
     *
     * @return bool
     */
    public function isPlayerRegistered(IPlayer $player){
        return $this->provider->isPlayerRegistered($player);
    }

    /**
     * @api
     *
     * @param Player $player
     *
     * @return bool True if call not blocked
     */
    public function authenticatePlayer(Player $player){
        if($this->isPlayerAuthenticated($player)){
            $player->sendMessage('§6- §aYou have been authenticated by §fIP');
            return true;
        }

        $this->getServer()->getPluginManager()->callEvent($ev = new PlayerAuthenticateEvent($this, $player));
        if($ev->isCancelled()){
            return false;
        }

        if(isset($this->needAuth[spl_object_hash($player)])){
            $attachment = $this->needAuth[spl_object_hash($player)];
            $player->removeAttachment($attachment);
            unset($this->needAuth[spl_object_hash($player)]);
        }
        $this->provider->updatePlayer($player, $player->getUniqueId()->toString());
        $player->sendMessage('§6- §aYou have been authenticated.');

        unset($this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())]);

        return true;
    }

    /**
     * @api
     *
     * @param Player $player
     *
     * @return bool True if call not blocked
     */
    public function deauthenticatePlayer(Player $player){
        if(!$this->isPlayerAuthenticated($player)){
            return true;
        }

        $this->getServer()->getPluginManager()->callEvent($ev = new PlayerDeauthenticateEvent($this, $player));
        if($ev->isCancelled()){
            return false;
        }

        $attachment = $player->addAttachment($this);
        $this->needAuth[spl_object_hash($player)] = $attachment;

        $this->sendAuthenticateMessage($player);

        return true;
    }

    public function tryAuthenticatePlayer(Player $player){
        if($this->blockPlayers <= 0 and $this->isPlayerAuthenticated($player)){
            return;
        }

        if(count($this->blockSessions) > 2048){
            $this->blockSessions = [];
        }

        if(!isset($this->blockSessions[$player->getAddress()])){
            $this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())] = 1;
        }else{
            $this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())]++;
        }

        if($this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())] > $this->blockPlayers){
            $player->kick('§cToo many tries!', true);
            $this->getServer()->getNetwork()->blockAddress($player->getAddress(), 600);
        }
    }

    /**
     * @api
     *
     * @param IPlayer $player
     * @param string  $password
     *
     * @return bool
     */
    public function registerPlayer(IPlayer $player, $password){
        if(!$this->isPlayerRegistered($player)){
            $this->getServer()->getPluginManager()->callEvent($ev = new PlayerRegisterEvent($this, $player));
            if($ev->isCancelled()){
                return false;
            }
            $this->provider->registerPlayer($player, $this->hash(strtolower($player->getName()), $password));
            return true;
        }
        return false;
    }

    /**
     * @api
     *
     * @param IPlayer $player
     *
     * @return bool
     */
    public function unregisterPlayer(IPlayer $player){
        if($this->isPlayerRegistered($player)){
            $this->getServer()->getPluginManager()->callEvent($ev = new PlayerUnregisterEvent($this, $player));
            if($ev->isCancelled()){
                return false;
            }
            $this->provider->unregisterPlayer($player);
        }

        return true;
    }

    /**
     * @api
     *
     * @param DataProvider $provider
     */
    public function setDataProvider(DataProvider $provider){
        $this->provider = $provider;
    }

    /**
     * @api
     *
     * @return DataProvider
     */
    public function getDataProvider(){
        return $this->provider;
    }

    /* -------------------------- Non-API part -------------------------- */

    public function closePlayer(Player $player){
        unset($this->needAuth[spl_object_hash($player)]);
    }

    public function sendAuthenticateMessage(Player $player){
        $config = $this->provider->getPlayer($player);
        if($config === null){
            $player->sendMessage("§a===============================\n§a- §bWelcome §3to §6Orange§aCraft §fNetwork§c!\n§a- §cThis account not §6register.\n§a- §bRegister using §e/register <password>\n§a===============================");
        }else{
            $player->sendMessage("§a===============================\n§a- §bWelcome §3to §6Orange§aCraft §fNetwork§c!\n§a- §bThis account §aregister.\n§a- §bLogin using §e/login <password>\n§a===============================");
        }
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        switch($command->getName()){
            case "login":
                if($sender instanceof Player){
                    if(!$this->isPlayerRegistered($sender) or ($data = $this->provider->getPlayer($sender)) === null){
                        $sender->sendMessage('§cThis account is not registered.');
                        return true;
                    }
                    if(count($args) !== 1){
                        $sender->sendMessage('§cUsage: '.$command->getUsage());
                        return true;
                    }

                    $password = implode(" ", $args);

                    if(hash_equals($data["hash"], $this->hash(strtolower($sender->getName()), $password)) and $this->authenticatePlayer($sender)){
                        return true;
                    }else{
                        $this->tryAuthenticatePlayer($sender);
                        $sender->sendMessage('§cIncorrect password!');

                        return true;
                    }
                }else{
                    $sender->sendMessage('§cThis command only works in-game.');

                    return true;
                }
                break;
            case "register":
                if($sender instanceof Player){
                    if($this->isPlayerRegistered($sender)){
                        $sender->sendMessage('§cThis account is already registered.');
                        return true;
                    }

                    $password = implode(" ", $args);
                    if(strlen($password) < 6){
                        $sender->sendMessage('§cYour password is too short!');
                        return true;
                    }

                    if($this->registerPlayer($sender, $password) and $this->authenticatePlayer($sender)){
                        return true;
                    }else{
                        $sender->sendMessage('§cError during authentication.');
                        return true;
                    }
                }else{
                    $sender->sendMessage('§cThis command only works in-game.');

                    return true;
                }
                break;
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

    public function onEnable(){
        $this->saveDefaultConfig();
        $this->reloadConfig();

        $this->blockPlayers = (int) $this->getConfig()->get("blockAfterFail", 6);

        $provider = $this->getConfig()->get("dataProvider");
        unset($this->provider);
        switch(strtolower($provider)){
            case "yaml":
                $this->getLogger()->info("§aUsing YAML data provider");
                $provider = new YAMLDataProvider($this);
                break;
            case "sqlite3":
                $this->getLogger()->info("§aUsing SQLite3 data provider");
                $provider = new SQLite3DataProvider($this);
                break;
            case "mysql":
                $this->getLogger()->info("§aUsing MySQL data provider");
                $provider = new MySQLDataProvider($this);
                break;
            case "none":
            default:
                $provider = new SQLite3DataProvider($this);
                break;
        }

        if(!isset($this->provider) or !($this->provider instanceof DataProvider)){ //Fix for getting a Dummy provider
            $this->provider = $provider;
        }

        $this->listener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);

        foreach($this->getServer()->getOnlinePlayers() as $player){
            $this->deauthenticatePlayer($player);
        }

        $this->getLogger()->info("§bEverything loaded!");
    }

    public function onDisable(){
        $this->getServer()->getPluginManager();
        $this->provider->close();
        $this->blockSessions = [];
    }

    private function hash($salt, $password){
        return bin2hex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
    }
}