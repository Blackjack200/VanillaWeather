<?php
declare(strict_types=1);

namespace PrograMistV1\Weather\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\utils\TextFormat;
use PrograMistV1\Weather\Weather;

class WeatherCommand extends Command implements PluginOwned{
use PluginOwnedTrait;
    public function __construct(Plugin $plugin){
        $this->owningPlugin = $plugin;
        parent::__construct("weather", "changes the weather", "/weather <clear|rain|thunder>");
        DefaultPermissions::registerPermission(
            new Permission(Weather::COMMAND_WEATHER),
            [PermissionManager::getInstance()->getPermission(DefaultPermissionNames::GROUP_OPERATOR)]
        );
        $this->setPermission(Weather::COMMAND_WEATHER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(!$sender instanceof Player){
            return;
        }
        if(count($args) < 1 || count($args) > 2){
            throw new InvalidCommandSyntaxException();
        }
        $world = $sender->getWorld();
        $weather = strtolower((string)array_shift($args));

        switch($weather){
            case "clear":
                Weather::changeWeather($world, Weather::CLEAR, 100 * 20);
                break;
            case "rain":
                Weather::changeWeather($world, Weather::RAIN, 100 * 20);
                break;
            case "thunder":
                Weather::changeWeather($world, Weather::THUNDER, 100 * 20);
                break;
            default:
                $sender->sendMessage(TextFormat::RED."Unknown argument ".$weather);
                break;
        }
        $sender->sendMessage(TextFormat::GREEN."Weather changed to ".TextFormat::YELLOW.$weather.TextFormat::GREEN." forever");
    }
}