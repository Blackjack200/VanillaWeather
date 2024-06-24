<?php

declare(strict_types=1);

namespace PrograMistV1\Weather;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\world\WorldInitEvent;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use PrograMistV1\Weather\commands\WeatherCommand;
use PrograMistV1\Weather\events\WeatherChangeEvent;

class Weather extends PluginBase implements Listener{
    public const CLEAR = 0;
    public const RAIN = 1;
    public const THUNDER = 2;
    public const COMMAND_WEATHER = "vanillaweather.weather.command";

    protected function onEnable() : void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register("vanillaweather", new WeatherCommand($this));
    }

    public function onPlayerJoin(PlayerJoinEvent $event) : void{
        self::changeWeatherForPlayer($event->getPlayer());
    }

    public static function changeWeather(World $world, int $weather, int $time = 6000) : void{
        $ev = new WeatherChangeEvent($world);
        $ev->call();
        if($ev->isCancelled()){
            return;
        }
        $worldData = $world->getProvider()->getWorldData();
        $worldData->setRainTime($time);
        $worldData->setRainLevel(match ($weather) {
            self::RAIN => 0.5,
            self::THUNDER => 1,
            default => 0
        });
        if($weather === self::RAIN){
            $packets = [LevelEventPacket::create(LevelEvent::START_RAIN, 65535, null)];
        }elseif($weather === self::THUNDER){
            $packets = [LevelEventPacket::create(LevelEvent::START_THUNDER, 65535, null)];
        }else{
            $packets = [
                LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null),
                LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null)
            ];
        }
	    NetworkBroadcastUtils::broadcastPackets($world->getPlayers(), $packets);
    }

    public static function changeWeatherForPlayer(Player $player, ?World $world = null) : void{
        $world ?? $world = $player->getWorld();
        $level = $world->getProvider()->getWorldData()->getRainLevel();
        if($level == 0.5){
            $packets = [LevelEventPacket::create(LevelEvent::START_RAIN, 65535, null)];
        }elseif($level == 1){
            $packets = [LevelEventPacket::create(LevelEvent::START_THUNDER, 65535, null)];
        }else{
            $packets = [
                LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null),
                LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null)
            ];
        }
        NetworkBroadcastUtils::broadcastPackets([$player], $packets);
    }

    public function onPlayerTeleport(EntityTeleportEvent $event) : void{
        if(!($player = $event->getEntity()) instanceof Player){
            return;
        }
	    if ($event->getTo()->getWorld() === $event->getFrom()->getWorld()) {
		    return;
	    }
	    self::changeWeatherForPlayer($player, $event->getTo()->getWorld());
    }

    public function onWorldInit(WorldInitEvent $event) : void{
        $world = $event->getWorld();
        self::changeWeather($world, self::CLEAR, 18000);
    }
}