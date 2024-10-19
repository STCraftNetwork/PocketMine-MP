<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

class Cave implements Populator {
	private float $density = 0.1;
	private int $tunnelingFactor = 2;

	public function setDensity(float $density): void {
		$this->density = $density;
	}

	public function setTunnelingFactor(int $tunnelingFactor): void {
		$this->tunnelingFactor = $tunnelingFactor;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random): void {
		for ($i = 0; $i < 10 * $this->density; ++$i) {
			$x = ($chunkX << 4) + $random->nextBoundedInt(16);
			$y = $random->nextBoundedInt(128);
			$z = ($chunkZ << 4) + $random->nextBoundedInt(16);
			$this->createCave($world, new Vector3($x, $y, $z), $random);
		}
	}

	private function createCave(ChunkManager $world, Vector3 $start, Random $random): void {
		$caveSize = $random->nextBoundedInt(5) + 2;
		$caveHeight = $random->nextBoundedInt(2) + 1;

		for ($dx = -$caveSize; $dx <= $caveSize; ++$dx) {
			for ($dy = -$caveHeight; $dy <= $caveHeight; ++$dy) {
				for ($dz = -$caveSize; $dz <= $caveSize; ++$dz) {
					if (abs($dx) + abs($dy) + abs($dz) <= $caveSize) {
						$blockPos = new Vector3($start->x + $dx, $start->y + $dy, $start->z + $dz);
						$world->setBlockAt($blockPos->getX(), $blockPos->getY(), $blockPos->getZ(), VanillaBlocks::AIR());
					}
				}
			}
		}
	}
}
