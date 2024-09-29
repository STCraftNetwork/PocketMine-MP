<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\utils\Random;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function count;
use function min;

class GroundCover implements Populator {
	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void {
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");
		$factory = RuntimeBlockStateRegistry::getInstance();
		$biomeRegistry = BiomeRegistry::getInstance();

		for ($x = 0; $x < Chunk::EDGE_LENGTH; ++$x) {
			for ($z = 0; $z < Chunk::EDGE_LENGTH; ++$z) {
				$biome = $biomeRegistry->getBiome($chunk->getBiomeId($x, 0, $z));
				$cover = $biome->getGroundCover();

				if (empty($cover)) continue;

				$diffY = !$cover[0]->isSolid() ? 1 : 0;

				$startY = 127;
				while ($startY > 0 && $factory->fromStateId($chunk->getBlockStateId($x, $startY, $z))->isTransparent()) {
					--$startY;
				}
				$startY = min(127, $startY + $diffY);
				$endY = $startY - count($cover);

				for ($y = $startY; $y > $endY && $y >= 0; --$y) {
					$blockToPlace = $cover[$startY - $y];
					$currentBlock = $factory->fromStateId($chunk->getBlockStateId($x, $y, $z));

					if ($currentBlock->getTypeId() === BlockTypeIds::AIR && $blockToPlace->isSolid()) {
						break;
					}
					if ($blockToPlace->canBeFlowedInto() && $currentBlock instanceof Liquid) {
						continue;
					}

					$chunk->setBlockStateId($x, $y, $z, $blockToPlace->getStateId());
				}
			}
		}
	}
}
