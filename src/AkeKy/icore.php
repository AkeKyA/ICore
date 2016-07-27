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
use AkeKy\provider\SimpleAuth\DataProvider;
use AkeKy\provider\SimpleAuth\DummyDataProvider;
use AkeKy\provider\SimpleAuth\MySQLDataProvider;
use AkeKy\provider\SimpleAuth\SQLite3DataProvider;
use AkeKy\provider\SimpleAuth\YAMLDataProvider;

use AkeKy\provider\PvPStats\ProviderInterface;
use AkeKy\provider\PvPStats\YAMLProvider;
use AkeKy\provider\PvPStats\MySQLProvider;
use AkeKy\task\BroadcasterTask;
use AkeKy\task\CallbackTask;

class ICore extends PluginBase{

    public $count_down = 60; //secs
    public $time_count = array();

    /** @var PermissionAttachment[] */
    protected $needAuth = [];

    /** @var EventListener */
    protected $listener;

    /** @var DataProvider */
    protected $psimpleauth;

    protected $ppvpstats;

    protected $othercommandformat;

    protected $selfcommandformat;

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
        return $this->psimpleauth->isPlayerRegistered($player);
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
        $this->psimpleauth->updatePlayer($player, $player->getUniqueId()->toString());
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
            $this->psimpleauth->registerPlayer($player, $this->hash(strtolower($player->getName()), $password));
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
            $this->psimpleauth->unregisterPlayer($player);
        }

        return true;
    }

    /**
     * @api
     *
     * @param DataProvider $psimpleauth
     */
    public function setSimpleAuthDataProvider(DataProvider $psimpleauth){
        $this->psimpleauth = $psimpleauth;
    }

    /**
     * @api
     *
     * @return DataProvider
     */
    public function getSimpleAuthDataProvider(){
        return $this->psimpleauth;
    }

    public function getPvPStatsProvider(){
        return $this->ppvpstats;
    }

    public function playerExists(IPlayer $player) {
        return $this->ppvpstats->playerExists($player);
    }
    
    public function addPlayer(IPlayer $player) {
        return $this->ppvpstats->addPlayer($player);
    }
    
    public function removePlayer(IPlayer $player) {
        return $this->ppvpstats->removePlayer($player);
    }
    
    public function updatePlayer(IPlayer $player, $type) {
        return $this->ppvpstats->updatePlayer($player, $type);
    }

    public function getPlayerVips($name){
        return $this->vips->exists($name);
    }

    /*****************
    *================*
    *==[ Non-APIs ]==*
    *================*
    *****************/

    private function getValidPlayerVips($target){
        $player = $this->getServer()->getPlayer($target);
        return $player instanceof Player ? $player : $this->getServer()->getOfflinePlayer($target);
    }

    /*****************
    *================*
    *==[   APIs   ]==*
    *================*
    *****************/

    public function addPlayerVips($player){
        $target = $this->getValidPlayerVips($player);
        if($target instanceof Player){
            $p = strtolower($target->getName());
        }else{
            $p = strtolower($player);
        }
        if($this->vips->exists($p)) return false;
        $this->vips->set($p, true);
        $this->vips->save();

        return true;
    }

    public function removePlayerVips($player){
        $target = $this->getValidPlayerVips($player);
        if($target instanceof Player){
            $p = strtolower($target->getName());
        }else{
            $p = strtolower($player);
        }
        if(!$this->vips->exists($p)) return false;
        $this->vips->remove($p);
        $this->vips->save();
        return true;
    }

    public function getTimer(){
        if(isset($this->time_count['time'])){
            return $this->time_count['time'];
        }else{
            $this->setTimer($this->restart_time);
            return $this->time_count['time'];
        }
    }
    
    public function setTimer($time){
        if(isset($this->time_count['time'])){
            unset($this->time_count['time']);
            $this->time_count['time'] = "$time";
        }else{
            $this->time_count['time'] = "$time";
        }
    }

    public function initial_start($timer){
        if($timer == 1){
            $this->start($this->restart_time + 1);
            return;
        }else{
            $timer--;
            $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"initial_start" ], [$timer]), 20);
        }
    }
    
    public function start($time_target){
        $time_target--;
        $this->getServer()->broadcastMessage("§7.\n§aServer§7> §fเซิฟเวอร์จะรีสตาร์ทใน§b $time_target §fนาที\n§7.");
        if($time_target == 1){
            $this->count_down($this->count_down + 1);
            return;
        }
        $this->setTimer($time_target);
        $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"start" ], [$time_target]), 1200);
    }

    public function count_down($seconds){
        if($seconds == 2){
            foreach($this->getServer()->getOnlinePlayers() as $p){
                $p->close('', '§bเซิฟเวอร์รีสตาร์ท');
            }
            $this->getServer()->shutdown();
            return;
        }else{
            $seconds--;
            $this->setTimer($seconds);
            if($seconds == 30) $this->getServer()->broadcastMessage("§7.\n§aServer§7> §fเซิฟเวอร์จะรีสตาร์ทใน§b $seconds §fวินาที\n§7.");
            if($seconds == 10) $this->getServer()->broadcastMessage("§7.\n§aServer§7> §fเซิฟเวอร์จะรีสตาร์ทใน§b $seconds §fวินาที\n§7.");
            if($seconds < 6) $this->getServer()->broadcastMessage("§7.\n§aServer§7> §fเซิฟเวอร์จะรีสตาร์ทใน§b $seconds.\n§7.");
            $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"count_down"], [$seconds]), 20);
        }
    }

    /* -------------------------- Non-API part -------------------------- */

    public function closePlayer(Player $player){
        unset($this->needAuth[spl_object_hash($player)]);
    }

    public function sendAuthenticateMessage(Player $player){
        $config = $this->psimpleauth->getPlayer($player);
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
                    if(!$this->isPlayerRegistered($sender) or ($data = $this->psimpleauth->getPlayer($sender)) === null){
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
            case "stats":
                if(isset($args[0])){
                    $name = $args[0];
                    if($this->ppvpstats->getData($name) !== null){
                        if($this->ppvpstats->getData($name)["kills"] >= 1 and $this->ppvpstats->getData($name)["deaths"] >= 1){
                            $sender->sendMessage(str_replace(array("@player", "@kills", "@deaths", "@kdratio"), array($name, $this->ppvpstats->getData($name)["kills"], $this->ppvpstats->getData($name)["deaths"], (round((($this->ppvpstats->getData($name)["kills"]) / ($this->ppvpstats->getData($name)["deaths"])), 3))), $this->othercommandformat));
                        }else{
                            $sender->sendMessage(str_replace(array("@player", "@kills", "@deaths", "@kdratio"), array($name, $this->ppvpstats->getData($name)["kills"], $this->ppvpstats->getData($name)["deaths"], ("§r§cN§r§7/§r§cA§r")), $this->othercommandformat));
                        }
                    }else{
                        $sender->sendMessage('Sorry, stats for '.$name.' dont exist.');
                    }
                }else{
                    if($sender instanceof Player){
                        if($this->ppvpstats->getPlayer($sender)["kills"] >= 1 and $this->ppvpstats->getPlayer($sender)["deaths"] >= 1){
                            $sender->sendMessage(str_replace(array("@kills", "@deaths", "@kdratio"), array($this->ppvpstats->getPlayer($sender)["kills"], $this->ppvpstats->getPlayer($sender)["deaths"], (round(($this->ppvpstats->getPlayer($sender)["kills"] / $this->ppvpstats->getPlayer($sender)["deaths"]), 3))), $this->selfcommandformat));
                        }else{
                            $sender->sendMessage(str_replace(array("@kills", "@deaths", "@kdratio"), array($this->ppvpstats->getPlayer($sender)["kills"], $this->ppvpstats->getPlayer($sender)["deaths"], ("§r§cN§r§7/§r§cA§r")), $this->selfcommandformat));
                        }
                    }else{
                        $sender->sendMessage('§cPlease run this command in-game!');
                    }
                }
                break;
            case "vips":
                if(!isset($args[0]) || count($args) > 2){
                    $sender->sendMessage("Usage: /vips <add/remove/list>");
                    return true;
                }
                switch(strtolower($args[0])){
                    case "add":
                        if(isset($args[1])){
                            $who_player = $this->getValidPlayerVips($args[1]);
                            if($who_player instanceof Player){
                                $target = $who_player->getName();
                            }else{
                                $target = $args[1];
                            }
                            if($this->addPlayerVips($target)){
                                $sender->sendMessage("Successfully added '$target' on VIPSlots!");
                            }else{
                                $sender->sendMessage("$target is already added on VIPSlots!");
                            }
                        }else{
                            $sender->sendMessage("Usage: /vips add <player>");
                        }
                        break;
                    case "remove":
                        if(isset($args[1])){
                            $who_player = $this->getValidPlayerVips($args[1]);
                            if($who_player instanceof Player){
                                $target = $who_player->getName();
                            }else{
                                $target = $args[1];
                            }
                            if($this->removePlayerVips($target)){
                                $sender->sendMessage("Successfully removed '$target' on VIPSlots!");
                            }else{
                                $sender->sendMessage("$target doesn't exist on VIPSlots!");
                            }
                        }else{
                            $sender->sendMessage("Usage: /vips remove <player>");
                        }
                        break;
                    case "list":
                        $file = fopen($this->getDataFolder() . "vip_players.txt", "r");
                        $i = 0;
                        while(!feof($file)){
                            $vips[] = fgets($file);
                        }
                        fclose($file);
                        $sender->sendMessage("-==[ VIPSlots List ]==-");
                        foreach ($vips as $vip){
                            $sender->sendMessage(" - " . $vip);
                        }
                        break;
                    default:
                        $sender->sendMessage("Usage: /vips <add/remove/list>");
                        break;
                }
                break;
            case "restart":
                $time = $this->getTimer();
                $sender->sendMessage("[ASR] The server will restart in $time");
                break;
        }
        return false;
    }

    public function onEnable(){
        //Task
        $this->initial_start(2); //its obviously 1 sec but idk why xD
        $this->saveDefaultConfig();
        $this->reloadConfig();

        $this->restart_time = $this->getConfig()->get("TimeToRestart");
        $this->blockPlayers = (int) $this->getConfig()->get("blockAfterFail", 6);
        $this->time = (int) $this->getConfig()->get("time") * 20;
        $this->othercommandformat = $this->getConfig()->get("other-command-format");
        $this->selfcommandformat = $this->getConfig()->get("self-command-format");
        $this->vips = new Config($this->getDataFolder()."vip_players.txt", Config::ENUM, array());
        $ppvpstats = $this->getConfig()->get("PvPStatsdataProvider");
        unset($this->ppvpstats);
        switch(strtolower($ppvpstats)){
            case "yaml":
                $ppvpstats = new YAMLProvider($this);
                break;
            case "mysql":
                $ppvpstats = new MySQLProvider($this);
                break;
        }
        if(!isset($this->ppvpstats) or !($this->ppvpstats instanceof ProviderInterface)){
            $this->ppvpstats = $ppvpstats;
        }else{
            $this->getLogger()->critical("§cData PvPStats Provider error!");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

        $psimpleauth = $this->getConfig()->get("SimpleAuthdataProvider");
        unset($this->psimpleauth);
        switch(strtolower($psimpleauth)){
            case "yaml":
                $this->getLogger()->info("§aUsing YAML data provider");
                $psimpleauth = new YAMLDataProvider($this);
                break;
            case "sqlite3":
                $this->getLogger()->info("§aUsing SQLite3 data provider");
                $psimpleauth = new SQLite3DataProvider($this);
                break;
            case "mysql":
                $this->getLogger()->info("§aUsing MySQL data provider");
                $psimpleauth = new MySQLDataProvider($this);
                break;
            case "none":
            default:
                $psimpleauth = new SQLite3DataProvider($this);
                break;
        }

        if(!isset($this->psimpleauth) or !($this->psimpleauth instanceof DataProvider)){ //Fix for getting a Dummy provider
            $this->psimpleauth = $psimpleauth;
        }

        $this->listener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
        $this->task = $this->getServer()->getScheduler()->scheduleRepeatingTask(new BroadcasterTask($this), $this->time);

        foreach($this->getServer()->getOnlinePlayers() as $player){
            $this->deauthenticatePlayer($player);
        }

        $this->getLogger()->info("§bEverything loaded!");
    }

    public function onDisable(){
        $this->getServer()->getPluginManager();
        $this->psimpleauth->close();
        $this->blockSessions = [];
    }

    private function hash($salt, $password){
        return bin2hex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
    }
}
