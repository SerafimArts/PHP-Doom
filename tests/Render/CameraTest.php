<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Render\Camera;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(Camera::class)]
final class CameraTest extends TestCase
{
    public function testPointAheadOfTheCameraIsInTheMiddleOfTheScreen(): void
    {
        [$x, $y, $depth] = $this->project(new Camera(100.0, 200.0, 41.0, \M_PI / 2), 100.0, 700.0, 41.0);

        self::assertEqualsWithDelta(0.0, $x, 1.0E-5);
        self::assertEqualsWithDelta(0.0, $y, 1.0E-5);
        self::assertGreaterThan(0.0, $depth);
        self::assertLessThan(1.0, $depth);
    }

    public function testPointOnTheRightIsOnTheRightOfTheScreen(): void
    {
        // The camera looks north, the east is on its right hand.
        [$x] = $this->project(new Camera(0.0, 0.0, 41.0, \M_PI / 2), 100.0, 100.0, 41.0);

        self::assertEqualsWithDelta(1.0, $x, 1.0E-5, 'at 45 degrees the point is at the edge of the 90 degree view');
    }

    public function testPointAboveTheEyeIsInTheUpperPartOfTheScreen(): void
    {
        // In Vulkan the top of the screen is negative.
        [, $y] = $this->project(new Camera(0.0, 0.0, 41.0, 0.0), 100.0, 0.0, 141.0);

        self::assertLessThan(0.0, $y);
        self::assertEqualsWithDelta(-Camera::ASPECT, $y, 1.0E-5);
    }

    public function testPointBehindTheCameraHasNegativeW(): void
    {
        [, , , $w] = $this->project(new Camera(0.0, 0.0, 41.0, 0.0), -100.0, 0.0, 41.0);

        self::assertLessThan(0.0, $w);
    }

    public function testFrameBlockHas112Bytes(): void
    {
        self::assertSame(112, \strlen(new Camera()->packFrame(0.0, 0, 640, 400, 3)));
    }

    public function testFrameBlockTellsTheShadersWhetherRayLightingIsOn(): void
    {
        $camera = new Camera();

        $off = \unpack('f4', \substr($camera->packFrame(0.0, 0, 640, 400, 3), 96));
        $on = \unpack('f4', \substr($camera->packFrame(0.0, 0, 640, 400, 3, 0, true), 96));

        \assert(\is_array($off) && \is_array($on));

        self::assertSame(0.0, $off[4]);
        self::assertSame(1.0, $on[4]);
    }

    /**
     * @return array{float, float, float, float} normalized x and y, depth, w
     */
    private function project(Camera $camera, float $x, float $y, float $z): array
    {
        /** @var list<float> $matrix */
        $matrix = \array_values(\unpack('f16', $camera->packViewProjection()));
        $clip = [];

        for ($row = 0; $row < 4; ++$row) {
            $clip[$row] = $matrix[$row] * $x + $matrix[4 + $row] * $y + $matrix[8 + $row] * $z + $matrix[12 + $row];
        }

        return [$clip[0] / $clip[3], $clip[1] / $clip[3], $clip[2] / $clip[3], $clip[3]];
    }
}
