<?php

namespace pocketmine\world;

use pocketmine\network\mcpe\protocol\StructureBlockUpdatePacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\types\StructureSettings;
use pocketmine\network\mcpe\protocol\ClientboundPacket;

class BoundingBox
{
    private float $minX;
    private float $minY;
    private float $minZ;
    private float $maxX;
    private float $maxY;
    private float $maxZ;

    public function __construct(float $minX, float $minY, float $minZ, float $maxX, float $maxY, float $maxZ)
    {
        $this->minX = $minX;
        $this->minY = $minY;
        $this->minZ = $minZ;
        $this->maxX = $maxX;
        $this->maxY = $maxY;
        $this->maxZ = $maxZ;
    }

    public function isInBounds(float $x, float $y, float $z): bool
    {
        return $x >= $this->minX && $x <= $this->maxX &&
            $y >= $this->minY && $y <= $this->maxY &&
            $z >= $this->minZ && $z <= $this->maxZ;
    }

    public function visualizeBoundingBox(Player $player, int $blockType): void
    {
        $this->createLine($player, $this->minX, $this->minY, $this->minZ, $this->maxX, $this->minY, $this->minZ, $blockType);
        $this->createLine($player, $this->minX, $this->minY, $this->maxZ, $this->maxX, $this->minY, $this->maxZ, $blockType);
        $this->createLine($player, $this->minX, $this->minY, $this->minZ, $this->minX, $this->minY, $this->maxZ, $blockType);
        $this->createLine($player, $this->maxX, $this->minY, $this->minZ, $this->maxX, $this->minY, $this->maxZ, $blockType);
        $this->createLine($player, $this->minX, $this->maxY, $this->minZ, $this->maxX, $this->maxY, $this->minZ, $blockType);
        $this->createLine($player, $this->minX, $this->maxY, $this->maxZ, $this->maxX, $this->maxY, $this->maxZ, $blockType);
        $this->createLine($player, $this->minX, $this->maxY, $this->minZ, $this->minX, $this->maxY, $this->maxZ, $blockType);
        $this->createLine($player, $this->maxX, $this->maxY, $this->minZ, $this->maxX, $this->maxY, $this->maxZ, $blockType);
        $this->createLine($player, $this->minX, $this->minY, $this->minZ, $this->minX, $this->maxY, $this->minZ, $blockType);
        $this->createLine($player, $this->maxX, $this->minY, $this->minZ, $this->maxX, $this->maxY, $this->minZ, $blockType);
        $this->createLine($player, $this->minX, $this->minY, $this->maxZ, $this->minX, $this->maxY, $this->maxZ, $blockType);
        $this->createLine($player, $this->maxX, $this->minY, $this->maxZ, $this->maxX, $this->maxY, $this->maxZ, $blockType);
    }

    private function createLine(Player $player, float $x1, float $y1, float $z1, float $x2, float $y2, float $z2, int $blockType): void
    {
        $blockPosition1 = new BlockPosition((int) $x1, (int) $y1, (int) $z1);
        $blockPosition2 = new BlockPosition((int) $x2, (int) $y2, (int) $z2);

        $this->sendStructureBlockUpdate($player, $blockPosition1, $blockType);
        $this->sendStructureBlockUpdate($player, $blockPosition2, $blockType);
    }

    private function sendStructureBlockUpdate(Player $player, BlockPosition $blockPosition, int $blockType): void
{
    $structureSettings = new StructureSettings();
    $structureData = new StructureEditorData("ExampleName", "ExampleDataField", false, true, 0, $structureSettings, 0);

    $packet = StructureBlockUpdatePacket::create(
        $blockPosition,
        $structureData,
        false,
        false
    );

    if ($packet instanceof ClientboundPacket) {
        $player->getNetworkSession()->sendDataPacket($packet);
    } else {
        throw new \InvalidArgumentException("The created packet is not a valid ClientboundPacket.");
    }
}


    public function clearBoundingBox(Player $player): void
    {

        $blockType = 0;

        $this->resetLine($player, $this->minX, $this->minY, $this->minZ, $this->maxX, $this->minY, $this->minZ, $blockType);
        $this->resetLine($player, $this->minX, $this->minY, $this->maxZ, $this->maxX, $this->minY, $this->maxZ, $blockType);
        $this->resetLine($player, $this->minX, $this->minY, $this->minZ, $this->minX, $this->minY, $this->maxZ, $blockType);
        $this->resetLine($player, $this->maxX, $this->minY, $this->minZ, $this->maxX, $this->minY, $this->maxZ, $blockType);
        $this->resetLine($player, $this->minX, $this->maxY, $this->minZ, $this->maxX, $this->maxY, $this->minZ, $blockType);
        $this->resetLine($player, $this->minX, $this->maxY, $this->maxZ, $this->maxX, $this->maxY, $this->maxZ, $blockType);
        $this->resetLine($player, $this->minX, $this->maxY, $this->minZ, $this->minX, $this->maxY, $this->maxZ, $blockType);
        $this->resetLine($player, $this->maxX, $this->maxY, $this->minZ, $this->maxX, $this->maxY, $this->maxZ, $blockType);
        $this->resetLine($player, $this->minX, $this->minY, $this->minZ, $this->minX, $this->maxY, $this->minZ, $blockType);
        $this->resetLine($player, $this->maxX, $this->minY, $this->minZ, $this->maxX, $this->maxY, $this->minZ, $blockType);
        $this->resetLine($player, $this->minX, $this->minY, $this->maxZ, $this->minX, $this->maxY, $this->maxZ, $blockType);
        $this->resetLine($player, $this->maxX, $this->minY, $this->maxZ, $this->maxX, $this->maxY, $this->maxZ, $blockType);
    }

    private function resetLine(Player $player, float $x1, float $y1, float $z1, float $x2, float $y2, float $z2, int $blockType): void
    {
        $this->sendStructureBlockUpdate($player, new BlockPosition((int) $x1, (int) $y1, (int) $z1), $blockType);
        $this->sendStructureBlockUpdate($player, new BlockPosition((int) $x2, (int) $y2, (int) $z2), $blockType);
    }
}
