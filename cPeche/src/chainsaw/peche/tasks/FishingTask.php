<?php
declare(strict_types=1);

namespace chainsaw\peche\tasks;

use pocketmine\scheduler\Task;
use chainsaw\peche\manager\FishingManager;

class FishingTask extends Task {

    public function __construct(private FishingManager $fishingManager) {}

    public function onRun(): void {
        $this->fishingManager->tick();
    }
}