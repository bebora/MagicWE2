<?php

namespace xenialdan\MagicWE2\selection\shape;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

class Pyramid extends Shape
{
    public $width = 5;
    public $height = 5;
    public $depth = 5;
    public $flipped = false;

    /**
     * Pyramid constructor.
     * @param Vector3 $pasteVector
     * @param int $width
     * @param int $height
     * @param int $depth
     * @param bool $flipped
     */
    public function __construct(Vector3 $pasteVector, int $width, int $height, int $depth, bool $flipped = false)
    {
        $this->pasteVector = $pasteVector;
        $this->width = $width;
        $this->height = $height;
        $this->depth = $depth;
        $this->flipped = $flipped;
    }

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return \Generator|Block
     * @throws \Exception
     */
    public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        $reduceXPerLayer = -($this->width / $this->height);
        $reduceZPerLayer = -($this->depth / $this->height);
        $centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());
        for ($x = intval(floor($centerVec2->x - $this->width / 2 - 1)); $x <= floor($centerVec2->x + $this->width / 2 + 1); $x++) {
            for ($y = intval(floor($this->getPasteVector()->y)), $ry = 0; $y < floor($this->getPasteVector()->y + $this->height); $y++, $ry++) {
                for ($z = intval(floor($centerVec2->y - $this->depth / 2 - 1)); $z <= floor($centerVec2->y + $this->depth / 2 + 1); $z++) {
                    $vec2 = new Vector2($x, $z);
                    $vec3 = new Vector3($x, $y, $z);
                    if ($this->flipped) {
                        $radiusLayerX = ($this->width + $reduceXPerLayer * ($this->height - $ry)) / 2;
                        $radiusLayerZ = ($this->depth + $reduceZPerLayer * ($this->height - $ry)) / 2;
                    } else {
                        $radiusLayerX = ($this->width + $reduceXPerLayer * $ry) / 2;
                        $radiusLayerZ = ($this->depth + $reduceZPerLayer * $ry) / 2;
                    }
                    //TODO hollow
                    if (floor(abs($centerVec2->x - $vec2->x)) >= $radiusLayerX or floor(abs($centerVec2->y - $vec2->y)) >= $radiusLayerZ)
                        continue;
                    $block = $manager->getBlockAt($vec3->x, $vec3->y, $vec3->z)->setComponents($vec3->x, $vec3->y, $vec3->z);
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== Block::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === Block::AIR) continue;

                    if ($block->y >= Level::Y_MAX || $block->y < 0) continue;//TODO fuufufufuuu
                    if (empty($filterblocks)) yield $block;
                    else {
                        foreach ($filterblocks as $filterblock) {
                            if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getVariant() === $filterblock->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && ($block->getDamage() === $filterblock->getDamage() || API::hasFlag($flags, API::FLAG_KEEP_META)))))
                                yield $block;
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns a flat layer of all included x z positions in selection
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param int $flags
     * @return \Generator|Vector2
     * @throws \Exception
     */
    public function getLayer(ChunkManager $manager, int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        $centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());
        for ($x = intval(floor($centerVec2->x - $this->width / 2 - 1)); $x <= floor($centerVec2->x + $this->width / 2 + 1); $x++) {
            for ($z = intval(floor($centerVec2->y - $this->depth / 2 - 1)); $z <= floor($centerVec2->y + $this->depth / 2 + 1); $z++) {
                $vec2 = new Vector2($x, $z);
                //TODO hollow
                yield $vec2;
            }
        }
    }

    /**
     * @param ChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws \Exception
     */
    public function getTouchedChunks(ChunkManager $manager): array
    {//TODO optimize to remove "corner" chunks
        $this->validateChunkManager($manager);
        $maxX = $this->getMaxVec3()->x >> 4;
        $minX = $this->getMinVec3()->x >> 4;
        $maxZ = $this->getMaxVec3()->z >> 4;
        $minZ = $this->getMinVec3()->z >> 4;
        $touchedChunks = [];
        for ($x = $minX; $x <= $maxX; $x++) {
            for ($z = $minZ; $z <= $maxZ; $z++) {
                $chunk = $manager->getChunk($x, $z);
                if ($chunk === null) {
                    continue;
                }
                print "Touched Chunk at: $x:$z" . PHP_EOL;
                $touchedChunks[Level::chunkHash($x, $z)] = $chunk->fastSerialize();
            }
        }
        print "Touched chunks count: " . count($touchedChunks) . PHP_EOL;
        return $touchedChunks;
    }

    public function getAABB(): AxisAlignedBB
    {
        return new AxisAlignedBB(
            floor($this->pasteVector->x - $this->width / 2),
            $this->pasteVector->y,
            floor($this->pasteVector->z - $this->depth / 2),
            -1 + floor($this->pasteVector->x - $this->width / 2) + $this->width,
            -1 + $this->pasteVector->y + $this->height,
            -1 + floor($this->pasteVector->z - $this->depth / 2) + $this->depth
        );
    }

    public function getTotalCount(): int
    {
        return ceil((1 / 3) * ($this->width * $this->depth) * $this->height);
    }

    public static function getName(): string
    {
        return "Pyramid";
    }
}