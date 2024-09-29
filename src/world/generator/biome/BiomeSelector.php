<?php

namespace pocketmine\world\generator\biome;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\biome\UnknownBiome;
use pocketmine\world\generator\noise\Simplex;
use RuntimeException;
use SplFixedArray;

class BiomeSelector {
	private Simplex $temperature;
	private Simplex $rainfall;
	private SplFixedArray $biomeMap;
	private array $cache = [];

	public function __construct(Random $random) {
		$this->temperature = new Simplex($random, 2, 1 / 16, 1 / 512);
		$this->rainfall = new Simplex($random, 2, 1 / 16, 1 / 512);
		$this->recalculate();
	}

	public function recalculate(): void {
		$this->biomeMap = new SplFixedArray(64 * 64);
		$biomeRegistry = BiomeRegistry::getInstance();

		for ($i = 0; $i < 64; ++$i) {
			for ($j = 0; $j < 64; ++$j) {
				$biome = $biomeRegistry->getBiome($this->lookup($i / 63, $j / 63));
				if ($biome instanceof UnknownBiome) {
					throw new RuntimeException("Unknown biome returned by selector with ID " . $biome->getId());
				}
				$this->biomeMap[$i + ($j << 6)] = $biome;
			}
		}
	}

	protected function lookup(float $temperature, float $rainfall): int {
		if ($rainfall < 0.25) {
			return $temperature < 0.7 ? BiomeIds::OCEAN : BiomeIds::RIVER;
		}
		if ($rainfall < 0.60) {
			return $temperature < 0.25 ? BiomeIds::ICE_PLAINS : BiomeIds::PLAINS;
		}
		return $temperature < 0.75 ? BiomeIds::FOREST : BiomeIds::BIRCH_FOREST;
	}

	public function pickBiome(float $x, float $z): Biome {
		$cacheKey = $x . ":" . $z;
		if (isset($this->cache[$cacheKey])) {
			return $this->cache[$cacheKey];
		}

		$temperature = (int)($this->getTemperature($x, $z) * 63);
		$rainfall = (int)($this->getRainfall($x, $z) * 63);

		$biome = $this->biomeMap[$temperature + ($rainfall << 6)];
		$this->cache[$cacheKey] = $biome;

		return $biome;
	}

	private function getTemperature(float $x, float $z): float {
		return ($this->temperature->noise2D($x, $z, true) + 1) / 2;
	}

	private function getRainfall(float $x, float $z): float {
		return ($this->rainfall->noise2D($x, $z, true) + 1) / 2;
	}
}
