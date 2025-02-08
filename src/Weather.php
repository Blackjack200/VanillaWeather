<?php

declare(strict_types=1);

namespace PrograMistV1\Weather;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\world\WorldInitEvent;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\BinaryStream;
use pocketmine\world\World;
use PrograMistV1\Weather\commands\WeatherCommand;
use PrograMistV1\Weather\events\WeatherChangeEvent;

class Weather extends PluginBase implements Listener{
    public const CLEAR = 0;
    public const RAIN = 1;
    public const THUNDER = 2;
    public const COMMAND_WEATHER = "vanillaweather.weather.command";
	/**
	 * @var array<int, array<int,string>>
	 */
	private static array $packets = [];

	private static function makeCache(int $protocolId) : void {
		if (!isset(self::$packets[$protocolId])) {
			foreach ([self::CLEAR, self::RAIN, self::THUNDER] as $weather) {
				$packets = match ($weather) {
					self::RAIN => [LevelEventPacket::create(LevelEvent::START_RAIN, 65535, null)],
					self::THUNDER => [LevelEventPacket::create(LevelEvent::START_THUNDER, 65535, null)],
					default => [
						LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null),
						LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null),
					]
				};
				$encoder = new BinaryStream();
				PacketBatch::encodePackets($encoder, $protocolId, $packets);
				$compressor = ZlibCompressor::getInstance();
				$protocolAddition = $protocolId >= ProtocolInfo::PROTOCOL_1_20_60 ? chr($compressor->getNetworkId()) : '';
				self::$packets[$protocolId][$weather] = $protocolAddition . $compressor->compress($encoder->getBuffer());
			}
		}
	}

	protected function onEnable() : void {
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
	        self::THUNDER => 1.0,
	        default => 0.0
        });
	    $worldData->save();

	    foreach ($world->getPlayers() as $player) {
		    $session = $player->getNetworkSession();
		    $protocolId = $session->getProtocolId();
		    self::makeCache($protocolId);
		    $session->queueCompressed(self::$packets[$protocolId][$weather] ?? self::$packets[$protocolId][0]);
	    }
    }

    public static function changeWeatherForPlayer(Player $player, ?World $world = null) : void{
        $world ?? $world = $player->getWorld();
	    $level = $world->getProvider()->getWorldData()->getRainLevel();
	    $weather = match ($level) {
		    0.5 => self::RAIN,
		    1.0 => self::THUNDER,
		    default => self::CLEAR,
	    };
	    $session = $player->getNetworkSession();
	    $protocolId = $session->getProtocolId();
	    self::makeCache($protocolId);
	    $session->queueCompressed(self::$packets[$protocolId][$weather] ?? self::$packets[$protocolId][0]);
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