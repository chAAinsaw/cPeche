<?php
declare(strict_types=1);

namespace chainsaw\peche\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\block\Water;

class FishingHook extends Entity {
    private ?Player $owner = null;
    private bool $inWater = false;

    public static function getNetworkTypeId(): string {
        return EntityIds::FISHING_HOOK;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.25, 0.25);
    }

    protected function getInitialDragMultiplier(): float {
        return 0.02;
    }

    protected function getInitialGravity(): float {
        return 0.04;
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null, ?Player $owner = null) {
        parent::__construct($location, $nbt);
        if ($owner !== null) {
            $this->setOwner($owner);
        }
        $this->setNoClientPredictions();
    }

    public function setOwner(?Player $owner): void {
        $this->owner = $owner;
        if ($owner !== null) {
            $this->getNetworkProperties()->setLong(EntityMetadataProperties::OWNER_EID, $owner->getId());
        }
    }

    public function getOwner(): ?Player {
        return $this->owner;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if ($this->isFlaggedForDespawn()) return $hasUpdate;

        $pos = $this->getPosition();
        $block = $this->getWorld()->getBlock($pos);
        $blockAbove = $this->getWorld()->getBlock($pos->add(0, 1, 0));

        if ($block instanceof Water || $blockAbove instanceof Water) {
            $this->inWater = true;
            $waterSurfaceY = floor($pos->y) + 0.9; 
            if ($pos->y < $waterSurfaceY) {
                $this->motion->y = 0.05;
            } else {
                $this->motion->y = 0;
            }
            $this->motion->x *= 0.9;
            $this->motion->z *= 0.9;
            $hasUpdate = true;
        } else {
            $this->inWater = false;
            if (!$block->isTransparent() && !$block instanceof Water) {
                $this->motion->x = 0;
                $this->motion->y = 0;
                $this->motion->z = 0;
            }
        }

        if ($this->owner !== null && ($this->owner->isClosed() || $pos->distance($this->owner->getPosition()) > 32)) {
            $this->flagForDespawn();
        }

        return $hasUpdate;
    }

    public function attack(EntityDamageEvent $source): void {
        $source->cancel();
    }

    public function isInWater(): bool {
        return $this->inWater;
    }
}
