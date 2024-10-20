<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\object\TreeType;

class Tree implements Populator {
	private int $baseAmount = 1;
	private int $randomAmount = 3;

	private ?TreeType $treeType; // unused rn.

	public function __construct(?TreeType $var = TreeType::OAK()) {
		$this->treeType = $var;
	}
	public function setBaseAmount(int $baseAmount): void {
		$this->baseAmount = $baseAmount;
	}

	public function setRandomAmount(int $randomAmount): void {
		$this->randomAmount = $randomAmount;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random): void {
		$totalTrees = $this->baseAmount + $random->nextBoundedInt($this->randomAmount);

		for ($i = 0; $i < $totalTrees; ++$i) {
			$x = ($chunkX << 4) + $random->nextBoundedInt(16);
			$z = ($chunkZ << 4) + $random->nextBoundedInt(16);
			$y = $this->getHeightAt($world, $x, $z);
			if ($y > 0) {
				$this->generateTree($world, new Vector3($x, $y, $z));
			}
		}
	}

	private function generateTree(ChunkManager $world, Vector3 $position): void {
		// Generate trunk
		for ($dy = 0; $dy < 5; ++$dy) {
			$x = $position->getX();
			$y = (int)$position->getY() + $dy;
			$z = $position->getZ();
			$world->setBlockAt($x, $y, $z, VanillaBlocks::OAK_LOG());
		}

		$leafPositions = [
			$position->add(-1, 5, -1),
			$position->add(1, 5, -1),
			$position->add(-1, 5, 1),
			$position->add(1, 5, 1),
			$position->add(0, 5, 0),
			$position->add(0, 6, 0),
			$position->add(0, 5, 1),
			$position->add(0, 5, -1),
			$position->add(1, 5, 0),
			$position->add(-1, 5, 0),
		];

		foreach ($leafPositions as $leafPos) {
			$world->setBlockAt($leafPos->getX(), $leafPos->getY(), $leafPos->getZ(), VanillaBlocks::OAK_LEAVES());
		}
	}

	private function getHeightAt(ChunkManager $world, int $x, int $z): int {
		for ($y = 255; $y >= 0; --$y) {
			if ($world->getBlockAt($x, $y, $z)->getTypeId() !== VanillaBlocks::AIR()->getTypeId()) {
				return $y + 1;
			}
		}
		return -1;
	}
}
