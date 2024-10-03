<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\generator\normal;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\biome\BiomeSelector;
use pocketmine\world\generator\Gaussian;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\populator\Cave;
use pocketmine\world\generator\populator\GroundCover;
use pocketmine\world\generator\populator\Ore;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\generator\populator\Tree;
use pocketmine\world\World;

class Normal extends Generator{

	private const WORLD_MIN_HEIGHT = -64;
	private const WORLD_MAX_HEIGHT = 320;
	private const DEEPSLATE_START = 0;
	private int $waterHeight = 62;
	private array $populators = [];
	private array $generationPopulators = [];
	private Simplex $noiseBase;
	private BiomeSelector $selector;
	private Gaussian $gaussian;

	public function __construct(int $seed, string $preset) {
		parent::__construct($seed, $preset);
		$this->gaussian = new Gaussian(2);
		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 32);
		$this->random->setSeed($this->seed);
		$this->selector = new class($this->random) extends BiomeSelector {
			protected function lookup(float $temperature, float $rainfall): int {
				if ($rainfall < 0.25) {
					return $temperature < 0.7 ? BiomeIds::OCEAN : BiomeIds::DESERT;
				} elseif ($rainfall < 0.60) {
					return $temperature < 0.75 ? BiomeIds::PLAINS : BiomeIds::DESERT;
				} elseif ($rainfall < 0.80) {
					return $temperature < 0.75 ? BiomeIds::FOREST : BiomeIds::BIRCH_FOREST;
				} else {
					return BiomeIds::RIVER;
				}
			}
		};
		$this->selector->recalculate();
		$cover = new GroundCover();
		$this->generationPopulators[] = $cover;

		$ores = new Ore();
		$stone = VanillaBlocks::STONE();
		$ores->setOreTypes([
			new OreType(VanillaBlocks::COAL_ORE(), $stone, 20, 16, 0, 128),
			new OreType(VanillaBlocks::IRON_ORE(), $stone, 20, 8, 0, 64),
			new OreType(VanillaBlocks::REDSTONE_ORE(), $stone, 8, 7, 0, 16),
			new OreType(VanillaBlocks::LAPIS_LAZULI_ORE(), $stone, 1, 6, 0, 32),
			new OreType(VanillaBlocks::GOLD_ORE(), $stone, 2, 8, 0, 32),
			new OreType(VanillaBlocks::DIAMOND_ORE(), $stone, 1, 7, 0, 16),
			new OreType(VanillaBlocks::DIRT(), $stone, 20, 32, 0, 128),
			new OreType(VanillaBlocks::GRAVEL(), $stone, 10, 16, 0, 128)
		]);
		$this->populators[] = $ores;

		$this->addTreePopulators();
		$this->addCavePopulators();
	}

	private function addTreePopulators(): void {
		$trees = new Tree();
		$trees->setBaseAmount(1);
		$trees->setRandomAmount(3);

		$this->populators[] = $trees;
	}

	private function addCavePopulators(): void {
		$caves = new Cave();
		$caves->setDensity(0.1);
		$caves->setTunnelingFactor(2);
		$this->populators[] = $caves;
	}

	private function pickBiome(int $x, int $z): Biome {
		$hash = $x * 2345803 ^ $z * 9236449 ^ $this->seed;
		$hash *= $hash + 223;
		$hash = (int) $hash;
		$xNoise = $hash >> 20 & 3;
		$zNoise = $hash >> 22 & 3;
		$xNoise = $xNoise === 3 ? 1 : $xNoise;
		$zNoise = $zNoise === 3 ? 1 : $zNoise;
		return $this->selector->pickBiome($x + $xNoise - 1, $z + $zNoise - 1);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ): void {
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);
		$noise = $this->noiseBase->getFastNoise3D(
			Chunk::EDGE_LENGTH,
			self::WORLD_MAX_HEIGHT - self::WORLD_MIN_HEIGHT,
			Chunk::EDGE_LENGTH,
			4, 8, 4,
			$chunkX * Chunk::EDGE_LENGTH,
			self::WORLD_MIN_HEIGHT,
			$chunkZ * Chunk::EDGE_LENGTH
		);
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");
		$biomeCache = [];
		$bedrock = VanillaBlocks::BEDROCK()->getStateId();
		$stone = VanillaBlocks::STONE()->getStateId();
		$deepslate = VanillaBlocks::DEEPSLATE()->getStateId();
		$stillWater = VanillaBlocks::WATER()->getStateId();
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		for ($x = 0; $x < Chunk::EDGE_LENGTH; ++$x) {
			$absoluteX = $baseX + $x;
			for ($z = 0; $z < Chunk::EDGE_LENGTH; ++$z) {
				$absoluteZ = $baseZ + $z;
				$biome = $this->pickBiome($absoluteX, $absoluteZ);

				for ($y = self::WORLD_MIN_HEIGHT; $y < self::WORLD_MAX_HEIGHT; $y++) {
					$chunk->setBiomeId($x, $y, $z, $biome->getId());
				}

				$minSum = 0;
				$maxSum = 0;
				$weightSum = 0;
				for ($sx = -$this->gaussian->smoothSize; $sx <= $this->gaussian->smoothSize; ++$sx) {
					for ($sz = -$this->gaussian->smoothSize; $sz <= $this->gaussian->smoothSize; ++$sz) {
						$weight = $this->gaussian->kernel[$sx + $this->gaussian->smoothSize][$sz + $this->gaussian->smoothSize];
						if ($sx === 0 && $sz === 0) {
							$adjacent = $biome;
						} else {
							$index = World::chunkHash($absoluteX + $sx, $absoluteZ + $sz);
							$adjacent = $biomeCache[$index] ??= $this->pickBiome($absoluteX + $sx, $absoluteZ + $sz);
						}
						$minSum += ($adjacent->getMinElevation() - 1) * $weight;
						$maxSum += $adjacent->getMaxElevation() * $weight;
						$weightSum += $weight;
					}
				}
				$minSum /= $weightSum;
				$maxSum /= $weightSum;
				$smoothHeight = ($maxSum - $minSum) / 2;

				for ($y = self::WORLD_MIN_HEIGHT; $y < self::WORLD_MAX_HEIGHT; ++$y) {
					if ($y === self::WORLD_MIN_HEIGHT) {
						$chunk->setBlockStateId($x, $y, $z, $bedrock);
					} else {
						$noiseValue = $noise[$x][$z][$y - self::WORLD_MIN_HEIGHT] - 1 / $smoothHeight * ($y - $smoothHeight - $minSum);
						if ($noiseValue > 0) {
							$chunk->setBlockStateId($x, $y, $z, $y < self::DEEPSLATE_START ? $deepslate : $stone);
						} elseif ($y <= $this->waterHeight) {
							$chunk->setBlockStateId($x, $y, $z, $stillWater);
						}
					}
				}
			}
		}

		foreach ($this->generationPopulators as $populator) {
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ): void {
		foreach ($this->populators as $populator) {
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}
	}
}