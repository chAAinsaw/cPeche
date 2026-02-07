<?php
declare(strict_types=1);

namespace chainsaw\peche\listeners;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMissSwingEvent;
use pocketmine\item\VanillaItems;
use pocketmine\entity\Location;
use pocketmine\world\sound\ThrowSound;
use chainsaw\peche\manager\FishingManager;
use chainsaw\peche\entity\FishingHook;
use pocketmine\scheduler\ClosureTask;
use chainsaw\peche\Main;

class FishingListener implements Listener {
    private array $activeHooks = [];

    public function __construct(
        private Main $plugin,
        private FishingManager $fishingManager
    ) {}

    public function onItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if (!$item->equals(VanillaItems::FISHING_ROD(), true, false)) {
            return;
        }

        $name = $player->getName();

        if (isset($this->activeHooks[$name])) {
            if ($this->fishingManager->hasActiveSession($player)) {
                $this->fishingManager->handleClick($player);
            } else {
                $msg = $this->plugin->getConfig()->getNested("messages.retrieve");
                if ($msg !== null && $msg !== "") $player->sendMessage($msg);
                $this->removeHook($name);
            }
        } else {
            $this->launchHook($player);
        }
    }

    public function onMissSwing(PlayerMissSwingEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if (!$item->equals(VanillaItems::FISHING_ROD(), true, false)) {
            return;
        }

        $name = $player->getName();

        if (isset($this->activeHooks[$name])) {
            $msg = $this->plugin->getConfig()->getNested("messages.cancelled");
            if ($msg !== null && $msg !== "") $player->sendMessage($msg);
            $this->fishingManager->cancelFishing($player);
            $this->removeHook($name);
        } else {
            $this->launchHook($player);
        }
    }

    private function launchHook($player): void {
        $name = $player->getName();
        $location = $player->getLocation();
        $hook = new FishingHook(Location::fromObject($player->getEyePos(), $player->getWorld(), $location->yaw, $location->pitch));
        $hook->setOwner($player);
        $hook->setMotion($player->getDirectionVector()->multiply(0.8));
        $hook->spawnToAll();

        $player->getWorld()->addSound($player->getPosition(), new ThrowSound());
        $this->activeHooks[$name] = $hook;

        $msg = $this->plugin->getConfig()->getNested("messages.line_cast");
        if ($msg !== null && $msg !== "") $player->sendMessage($msg);

        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $hook, $name): void {
            if ($hook->isFlaggedForDespawn()) return;

            if ($hook->isInWater()) {
                $this->fishingManager->startFishing($player);
            } else {
                $msg = $this->plugin->getConfig()->getNested("messages.not_in_water");
                if ($msg !== null && $msg !== "") $player->sendMessage($msg);
                $this->removeHook($name);
            }
        }), 25);
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $this->removeHook($event->getPlayer()->getName());
        $this->fishingManager->cancelFishing($event->getPlayer());
    }

    public function removeHook(string $playerName): void {
        if (isset($this->activeHooks[$playerName])) {
            if (!$this->activeHooks[$playerName]->isFlaggedForDespawn()) {
                $this->activeHooks[$playerName]->flagForDespawn();
            }
            unset($this->activeHooks[$playerName]);
        }
    }
}