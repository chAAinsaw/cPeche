<?php
declare(strict_types=1);

namespace chainsaw\peche;

use pocketmine\plugin\PluginBase;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntityDataHelper;
use pocketmine\world\World;
use pocketmine\nbt\tag\CompoundTag;
use chainsaw\peche\listeners\FishingListener;
use chainsaw\peche\manager\FishingManager;
use chainsaw\peche\tasks\FishingTask;
use chainsaw\peche\entity\FishingHook;

class Main extends PluginBase {

    private FishingManager $fishingManager;
    private FishingListener $fishingListener;

    public function onEnable(): void {
        $this->saveDefaultConfig();

        EntityFactory::getInstance()->register(FishingHook::class, function(World $world, CompoundTag $nbt): FishingHook {
            return new FishingHook(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['FishingHook']);

        $this->fishingManager = new FishingManager($this);
        $this->fishingListener = new FishingListener($this, $this->fishingManager);

        $this->getServer()->getPluginManager()->registerEvents($this->fishingListener, $this);
        $this->getScheduler()->scheduleRepeatingTask(new FishingTask($this->fishingManager), 1);
    }

    public function getFishingManager(): FishingManager {
        return $this->fishingManager;
    }

    public function getFishingListener(): FishingListener {
        return $this->fishingListener;
    }
}
