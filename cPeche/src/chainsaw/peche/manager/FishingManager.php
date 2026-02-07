<?php
declare(strict_types=1);

namespace chainsaw\peche\manager;

use pocketmine\player\Player;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;
use pocketmine\world\particle\WaterParticle;
use pocketmine\world\sound\WaterSplashSound;
use chainsaw\peche\Main;
use chainsaw\peche\entity\FishingHook;

class FishingManager {

    private array $activeSessions = [];

    public function __construct(private Main $plugin) {}

    public function startFishing(Player $player): void {
        $name = $player->getName();
        if (isset($this->activeSessions[$name])) return;

        $config = $this->plugin->getConfig();
        $waitTime = mt_rand($config->getNested("fishing.time.min", 5), $config->getNested("fishing.time.max", 15));

        $this->activeSessions[$name] = [
            "startTime" => time(),
            "waitTime" => $waitTime,
            "stage" => "waiting"
        ];

        $msg = $config->getNested("messages.waiting");
        if ($msg !== null && $msg !== "") $player->sendMessage($msg);
    }

    public function tick(): void {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $name = $player->getName();
            if (!isset($this->activeSessions[$name])) continue;

            $session = &$this->activeSessions[$name];
            if ($session["stage"] === "waiting") {
                if (time() - $session["startTime"] >= $session["waitTime"]) {
                    $this->startPopupChallenge($player);
                }
            } elseif ($session["stage"] === "popup") {
                $this->updatePopup($player);
            }
        }
    }

    private function startPopupChallenge(Player $player): void {
        $rarity = $this->selectRarity();
        $config = $this->plugin->getConfig()->getNested("fishing.popup.rarities.$rarity");
        if (!$config) {
            $this->failFishing($player);
            return;
        }

        $hook = null;
        foreach($player->getWorld()->getEntities() as $entity){
            if($entity instanceof FishingHook && $entity->getOwner()?->getName() === $player->getName()){
                $hook = $entity;
                break;
            }
        }

        if($hook !== null){
            $world = $hook->getWorld();
            $pos = $hook->getPosition();
            for($i = 0; $i < 10; $i++) $world->addParticle($pos->add(mt_rand(-1, 1) / 10, 0.1, mt_rand(-1, 1) / 10), new WaterParticle());
            $world->addSound($pos, new WaterSplashSound(1.0));
        }

        $total = (int)$config["total_squares"];
        $count = (int)$config["target_count"];
        $start = mt_rand(0, $total - $count);

        $this->activeSessions[$player->getName()] = array_merge($this->activeSessions[$player->getName()], [
            "stage" => "popup",
            "rarity" => $rarity,
            "color" => $config["color"],
            "speed" => (float)$config["speed"],
            "total_squares" => $total,
            "position" => 0,
            "direction" => 1,
            "lastUpdate" => microtime(true),
            "targets" => range($start, $start + $count - 1)
        ]);

        $msg = $this->plugin->getConfig()->getNested("messages.bite");
        if ($msg !== null && $msg !== "") $player->sendMessage($msg);
    }

    public function updatePopup(Player $player): void {
        $name = $player->getName();
        if (!isset($this->activeSessions[$name])) return;
        $session = &$this->activeSessions[$name];

        if (microtime(true) - $session["lastUpdate"] >= $session["speed"]) {
            $session["position"] += $session["direction"];
            if ($session["position"] >= $session["total_squares"] - 1 || $session["position"] <= 0) {
                $session["direction"] *= -1;
            }
            $session["lastUpdate"] = microtime(true);
        }

        $popup = "";
        for ($i = 0; $i < $session["total_squares"]; $i++) {
            if ($i === $session["position"]) $popup .= "§a■";
            elseif (in_array($i, $session["targets"], true)) $popup .= $session["color"] . "■";
            else $popup .= "§8□";
        }
        $player->sendPopup($popup);
    }

    public function handleClick(Player $player): bool {
        $name = $player->getName();
        if (!isset($this->activeSessions[$name])) return false;

        $session = &$this->activeSessions[$name];

        if ($session["stage"] === "waiting") {
            $msg = $this->plugin->getConfig()->getNested("messages.cancelled");
            if ($msg !== null && $msg !== "") $player->sendMessage($msg);
            $this->cancelFishing($player);
            $this->plugin->getFishingListener()->removeHook($name);
            return false;
        }

        if ($session["stage"] !== "popup") return false;

        if (in_array($session["position"], $session["targets"], true)) {
            $this->successFishing($player);
            return true;
        }

        $this->failFishing($player);
        return false;
    }

    private function successFishing(Player $player): void {
        $rarity = $this->activeSessions[$player->getName()]["rarity"];
        $loot = $this->selectLoot($rarity);
        if ($loot !== null) {
            $player->getInventory()->canAddItem($loot) ? $player->getInventory()->addItem($loot) : $player->getWorld()->dropItem($player->getPosition(), $loot);

            $msg = $this->plugin->getConfig()->getNested("messages.success");
            if ($msg !== null && $msg !== "") $player->sendMessage(str_replace("{item}", $loot->getName(), $msg));
        }
        $this->cancelFishing($player);
        $this->plugin->getFishingListener()->removeHook($player->getName());
    }

    private function failFishing(Player $player): void {
        $msg = $this->plugin->getConfig()->getNested("messages.failed");
        if ($msg !== null && $msg !== "") $player->sendMessage($msg);

        $this->cancelFishing($player);
        $this->plugin->getFishingListener()->removeHook($player->getName());
    }

    private function selectRarity(): string {
        $loots = $this->plugin->getConfig()->getNested("fishing.loots", []);
        $total = 0;
        foreach($loots as $data) $total += $data["chance"] ?? 10;
        $rand = mt_rand(1, max(1, $total));
        $current = 0;
        foreach ($loots as $rarity => $data) {
            $current += $data["chance"] ?? 10;
            if ($rand <= $current) return $rarity;
        }
        return "common";
    }

    private function selectLoot(string $rarity): ?Item {
        $items = $this->plugin->getConfig()->getNested("fishing.loots.$rarity.items", []);
        if (empty($items)) return null;
        $itemData = $items[array_rand($items)];
        $item = StringToItemParser::getInstance()->parse($itemData["item"]);
        return $item?->setCount($itemData["amount"] ?? 1);
    }

    public function cancelFishing(Player $player): void {
        unset($this->activeSessions[$player->getName()]);
    }

    public function hasActiveSession(Player $player): bool {
        return isset($this->activeSessions[$player->getName()]);
    }
}