<?php
declare(strict_types=1);

namespace chainsaw\peche\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\block\Water;
use pocketmine\block\VanillaBlocks;

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

    public function setOwner(?Player $owner): void {
        $this->owner = $owner;
        if($owner !== null){
            $this->getNetworkProperties()->setLong(EntityMetadataProperties::OWNER_EID, $owner->getId());
        }
    }

    public function getOwner(): ?Player {
        return $this->owner;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if ($this->isFlaggedForDespawn()) return $hasUpdate;

        $block = $this->getWorld()->getBlock($this->getPosition());
        $blockAbove = $this->getWorld()->getBlock($this->getPosition()->add(0, 1, 0));

        if ($block instanceof Water || $blockAbove instanceof Water) {
            if (!$this->inWater) {
                $this->inWater = true;
            }

            $waterLevel = $this->getWorld()->getBlock($this->getPosition())->getPosition()->y + 1;
            $hookY = $this->getPosition()->y;

            if ($hookY < $waterLevel) {
                $this->motion->y = 0.1;
            } else {
                $this->motion->y = 0.0;
            }

            $this->motion->x *= 0.95;
            $this->motion->z *= 0.95;
            $hasUpdate = true;
        } else {
            $this->inWater = false;
        }

        if($this->owner !== null && ($this->owner->isClosed() || $this->getPosition()->distance($this->owner->getPosition()) > 32)){
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