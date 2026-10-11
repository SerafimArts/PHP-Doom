<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Raytracing;

use Local\GPU\Device;
use Local\GPU\Frame\FrameLoop;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Raytracing\AccelerationStructure;
use Local\GPU\Raytracing\AccelerationStructureBarrier;
use Local\GPU\Raytracing\AccelerationStructureBuilder;
use Local\GPU\Raytracing\BuildableStructure;
use Local\GPU\Resource\Buffer;
use Local\GPU\Resource\Transfer;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;
use Serafim\Doom\Render\Mesh\LevelMesh;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkPipelineStageFlagBits2;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Vulkan;

/**
 * What the fragment shaders trace rays against, once for every frame in flight: the level, the surfaces of the moving
 * sectors at the heights they have now, and a box for every solid thing. The top level structure is rebuilt in the
 * recorded commands of the frame from the instances that the host writes before it.
 */
final class ShadowScene
{
    /**
     * Bytes of a light: the position with the radius and the color
     */
    public const int LIGHT_SIZE = 32;

    /**
     * Bytes of a caster in the scene: x, y, z of the feet, the radius and the height of the box
     */
    public const int CASTER_SIZE = 20;

    /**
     * Instances of a top level structure: the level, the moving sectors and the boxes of the things
     */
    public const int MAX_INSTANCES = 256;

    private const int INSTANCE_SIZE = 64;

    private const int FIRST_THING = 2;

    /**
     * What a ray of a surface sees: the level and the things
     */
    private const int LEVEL_MASK = 0x01;

    private const int THING_MASK = 0x02;

    /**
     * The instance does not cull triangles by their facing
     */
    private const int INSTANCE_NO_CULL = 1;

    /**
     * The indices that the rebuilt surfaces of every frame are read with
     */
    public ?Buffer $dynamicIndices = null;

    /** @var list<Buffer> */
    private array $vertexBuffers = [];

    /** @var list<Buffer> */
    private array $instanceBuffers = [];

    /** @var list<Buffer> */
    private array $lightBuffers = [];

    /** @var list<BuildableStructure> */
    private array $rebuiltLevels = [];

    /** @var list<BuildableStructure> */
    private array $topLevels = [];

    /**
     * What the vertices of every frame were written for
     *
     * @var array<int, string>
     */
    private array $signatures = [];

    /**
     * Instances that every frame has in its buffer
     *
     * @var array<int, int>
     */
    private array $instanceCounts = [];

    /**
     * @param list<int> $dynamicSectors
     */
    private function __construct(
        private readonly DynamicTriangles $dynamic,
        private readonly array $dynamicSectors,
        private readonly AccelerationStructure $level,
        private readonly AccelerationStructure $box,
        /**
         * Number of lights the shaders read
         *
         * @var int<1, max>
         */
        private readonly int $maxLights,
    ) {}

    /**
     * @param int<1, max> $maxLights number of lights the shaders read
     */
    public static function create(
        Device $device,
        Transfer $transfer,
        Level $level,
        LevelMesh $mesh,
        int $maxLights,
    ): self {
        $dynamicSectors = DynamicSectors::find($level);
        $static = ShadowTriangles::createFromLevel($level, $mesh, $dynamicSectors);
        $dynamic = DynamicTriangles::create($level, $mesh, $dynamicSectors);

        $builder = new AccelerationStructureBuilder($device, $transfer);

        $usage = AccelerationStructureBuilder::getInputUsage();

        $vertices = $device->createBuffer(\max(1, \strlen($static->vertices)), $usage, MemoryKind::HOST_VISIBLE);
        $vertices->write($static->vertices);
        $indices = $device->createBuffer(\max(1, \strlen($static->indices)), $usage, MemoryKind::HOST_VISIBLE);
        $indices->write($static->indices);

        $levelStructure = $builder->buildBottomLevel(
            $vertices,
            \max(1, $static->vertexCount),
            $indices,
            \max(1, $static->triangleCount),
        );

        [$boxVertices, $boxIndices] = self::packBox();

        $boxVertexBuffer = $device->createBuffer(\max(1, \strlen($boxVertices)), $usage, MemoryKind::HOST_VISIBLE);
        $boxVertexBuffer->write($boxVertices);
        $boxIndexBuffer = $device->createBuffer(\max(1, \strlen($boxIndices)), $usage, MemoryKind::HOST_VISIBLE);
        $boxIndexBuffer->write($boxIndices);

        $box = $builder->buildBottomLevel($boxVertexBuffer, 8, $boxIndexBuffer, 12);

        $scene = new self($dynamic, \array_keys($dynamicSectors), $levelStructure, $box, $maxLights);

        $dynamicIndices = $dynamic->indices;

        if ($dynamic->isEmpty()) {
            $dynamicIndices = \pack('V3', 0, 1, 2);
        }
        $dynamicIndexBuffer = $device->createBuffer(
            \max(1, \strlen($dynamicIndices)),
            $usage,
            MemoryKind::HOST_VISIBLE,
        );
        $dynamicIndexBuffer->write($dynamicIndices);
        $scene->dynamicIndices = $dynamicIndexBuffer;

        $vertexSize = \max(36, $dynamic->vertexCount * 12);

        for ($frame = 0; $frame < FrameLoop::FRAMES_IN_FLIGHT; ++$frame) {
            $vertexBuffer = $device->createBuffer($vertexSize, $usage, MemoryKind::STREAMING);
            $instanceBuffer = $device->createBuffer(
                self::MAX_INSTANCES * self::INSTANCE_SIZE,
                $usage,
                MemoryKind::STREAMING,
            );
            $lightBuffer = $device->createBuffer(
                $scene->maxLights * self::LIGHT_SIZE,
                VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT,
                MemoryKind::STREAMING,
            );

            $vertexBuffer->write(\str_repeat("\0", $vertexSize));
            $instanceBuffer->write(\str_repeat("\0", self::MAX_INSTANCES * self::INSTANCE_SIZE));
            $lightBuffer->write(\str_repeat("\0", $scene->maxLights * self::LIGHT_SIZE));

            $scene->vertexBuffers[] = $vertexBuffer;
            $scene->instanceBuffers[] = $instanceBuffer;
            $scene->lightBuffers[] = $lightBuffer;
            $scene->rebuiltLevels[] = $builder->createRebuiltBottomLevel(
                $vertexBuffer,
                \max(3, $dynamic->vertexCount),
                $dynamicIndexBuffer,
                \max(1, $dynamic->triangleCount),
            );
            $scene->topLevels[] = $builder->createRebuiltTopLevel($instanceBuffer, self::MAX_INSTANCES);
            $scene->signatures[$frame] = '';
            $scene->instanceCounts[$frame] = self::MAX_INSTANCES;
        }

        return $scene;
    }

    public function getStructure(int $frameIndex): AccelerationStructure
    {
        return $this->topLevels[$frameIndex]->structure;
    }

    public function getLights(int $frameIndex): Buffer
    {
        return $this->lightBuffers[$frameIndex];
    }

    /**
     * Writes what the frame traces against: the heights of the moving sectors, the boxes of the solid things and the
     * lights.
     *
     * @param string $casters {@see self::CASTER_SIZE} bytes for every solid thing
     * @param string $lights {@see self::LIGHT_SIZE} bytes for every light
     */
    public function update(int $frameIndex, SectorState $sectors, string $casters, string $lights): void
    {
        $this->updateSurfaces($frameIndex, $sectors);

        $instances = $this->packInstance($this->level->address, self::LEVEL_MASK, 1.0, 1.0, 1.0, 0.0, 0.0, 0.0)
            . $this->packInstance(
                $this->rebuiltLevels[$frameIndex]->structure->address,
                self::LEVEL_MASK,
                1.0,
                1.0,
                1.0,
                0.0,
                0.0,
                0.0,
            );

        $count = self::FIRST_THING;

        foreach (\str_split($casters, self::CASTER_SIZE) as $caster) {
            if ($count >= self::MAX_INSTANCES || \strlen($caster) !== self::CASTER_SIZE) {
                break;
            }

            $instances .= $this->packInstance(
                $this->box->address,
                self::THING_MASK,
                ShadowTriangles::readFloat($caster, 12),
                ShadowTriangles::readFloat($caster, 12),
                ShadowTriangles::readFloat($caster, 16),
                ShadowTriangles::readFloat($caster, 0),
                ShadowTriangles::readFloat($caster, 4),
                ShadowTriangles::readFloat($caster, 8),
            );
            ++$count;
        }

        $previous = $this->instanceCounts[$frameIndex];

        if ($previous > $count) {
            $instances .= \str_repeat("\0", ($previous - $count) * self::INSTANCE_SIZE);
        }

        $this->instanceCounts[$frameIndex] = $count;
        $this->instanceBuffers[$frameIndex]->write($instances);

        $lights = \substr($lights, 0, $this->maxLights * self::LIGHT_SIZE);
        $this->lightBuffers[$frameIndex]->write(\str_pad($lights, $this->maxLights * self::LIGHT_SIZE, "\0"));
    }

    /**
     * Builds the structures of the frame: the surfaces of the moving sectors, then the top level over everything.
     */
    public function record(Vulkan $vk, VkCommandBuffer $commandBuffer, int $frameIndex): void
    {
        $this->rebuiltLevels[$frameIndex]->record($commandBuffer);
        AccelerationStructureBarrier::betweenBuilds($vk, $commandBuffer);
        $this->topLevels[$frameIndex]->record($commandBuffer);
        AccelerationStructureBarrier::beforeShaders(
            $vk,
            $commandBuffer,
            VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_FRAGMENT_SHADER_BIT,
        );
    }

    private function updateSurfaces(int $frameIndex, SectorState $sectors): void
    {
        if ($this->dynamic->isEmpty()) {
            return;
        }

        $signature = '';

        foreach ($this->dynamicSectors as $sector) {
            $signature .= $sectors->floorHeights[$sector] . ',' . $sectors->ceilingHeights[$sector] . ';';
        }

        if ($this->signatures[$frameIndex] === $signature) {
            return;
        }

        $this->signatures[$frameIndex] = $signature;
        $this->vertexBuffers[$frameIndex]->write($this->dynamic->packVertices($sectors));
    }

    /**
     * An instance (`VkAccelerationStructureInstanceKHR`) of a structure: scaled, then moved.
     */
    private function packInstance(
        int $address,
        int $mask,
        float $scaleX,
        float $scaleY,
        float $scaleZ,
        float $x,
        float $y,
        float $z,
    ): string {
        return \pack(
            'g12VVP',
            $scaleX,
            0.0,
            0.0,
            $x,
            0.0,
            $scaleY,
            0.0,
            $y,
            0.0,
            0.0,
            $scaleZ,
            $z,
            $mask << 24,
            self::INSTANCE_NO_CULL << 24,
            $address,
        );
    }

    /**
     * The box of a thing, two units wide and one high around its feet: `float` triples and `uint32_t` indices.
     *
     * @return array{string, string}
     */
    private static function packBox(): array
    {
        $vertices = '';

        foreach ([0.0, 1.0] as $z) {
            foreach ([-1.0, 1.0] as $y) {
                foreach ([-1.0, 1.0] as $x) {
                    $vertices .= \pack('g3', $x, $y, $z);
                }
            }
        }

        $indices = \pack(
            'V*',
            0,
            2,
            1,
            1,
            2,
            3,
            4,
            5,
            6,
            5,
            7,
            6,
            0,
            1,
            4,
            1,
            5,
            4,
            2,
            6,
            3,
            3,
            6,
            7,
            0,
            4,
            2,
            2,
            4,
            6,
            1,
            3,
            5,
            3,
            7,
            5,
        );

        return [$vertices, $indices];
    }
}
