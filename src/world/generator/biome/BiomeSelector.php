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

	/**
     * @var SplFixedArray<Biome>
     */
    private SplFixedArray $biomeMap;
	/**
     * @var array<string, Biome> 
     */
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
