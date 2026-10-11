<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Layout;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Serafim\Doom\Render\Layout\FrameData;
use Serafim\Doom\Render\Layout\QuadInstance;
use Serafim\Doom\Render\Layout\SpriteInstance;
use Serafim\Doom\Render\Layout\SpriteViewTable;
use Serafim\Doom\Render\Mesh\LevelMesh;
use Serafim\Doom\Render\SectorTable;
use Serafim\Doom\Tests\TestCase;

/**
 * The sizes that PHP writes into the buffers are the sizes of the structs that the shaders declare.
 */
#[CoversNothing]
final class ShaderLayoutTest extends TestCase
{
    private const array SCALARS = ['float' => 4, 'int' => 4, 'uint' => 4];

    /**
     * @return iterable<non-empty-string, array{non-empty-string, non-empty-string, int}>
     */
    public static function structProvider(): iterable
    {
        yield 'the uniform block of the frame' => ['level.glsl', 'Frame', FrameData::SIZE];
        yield 'the sector' => ['level.glsl', 'Sector', SectorTable::SECTOR_SIZE];
        yield 'the wall part' => ['wall.vert', 'Wall', LevelMesh::WALL_SIZE];
        yield 'the sprite view' => ['sprite.vert', 'SpriteView', SpriteViewTable::SIZE];
        yield 'the sprite instance' => ['sprite.vert', 'SpriteInstance', SpriteInstance::SIZE];
        yield 'the hud quad' => ['hud.vert', 'HudQuad', QuadInstance::SIZE];
    }

    /**
     * @param non-empty-string $file
     * @param non-empty-string $name
     */
    #[DataProvider('structProvider')]
    public function testStructOfTheShaderHasTheSizeThatPhpWrites(string $file, string $name, int $size): void
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . '/resources/shaders/' . $file);
        self::assertIsString($source);

        self::assertSame($size, $this->measure($source, $name));
    }

    /**
     * The size of a struct or of a uniform block in the layout of the GPU: members of 4, 8 and 16 bytes aligned to
     * their size, the whole rounded up to the widest member.
     */
    private function measure(string $source, string $name): int
    {
        $found = \preg_match('/(?:struct|uniform)\s+' . $name . '\s*\{([^}]*)\}/', $source, $matches);
        self::assertSame(1, $found, 'the shader declares ' . $name);

        $offset = 0;
        $widest = 4;

        $body = \preg_replace('#//[^\n]*#', '', $matches[1]);

        foreach (\explode(';', $body) as $declaration) {
            $declaration = \trim($declaration);

            if ($declaration === '') {
                continue;
            }

            [$type] = \preg_split('/\s+/', $declaration);
            [$bytes, $alignment] = $this->describeType($type);

            $offset = (int) (\ceil($offset / $alignment) * $alignment) + $bytes;
            $widest = \max($widest, $alignment);
        }

        return (int) (\ceil($offset / $widest) * $widest);
    }

    /**
     * @return array{int, int} size and alignment of a type in bytes
     */
    private function describeType(string $type): array
    {
        if (isset(self::SCALARS[$type])) {
            return [4, 4];
        }

        if ($type === 'mat4') {
            return [64, 16];
        }

        if (\preg_match('/^[iu]?vec([234])$/', $type, $matches) === 1) {
            $components = (int) $matches[1];

            return [4 * $components, $components === 2 ? 8 : 16];
        }

        self::fail('unknown type of a shader member: ' . $type);
    }
}
