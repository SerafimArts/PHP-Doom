<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataReader;

/**
 * All the tables of the renderer that come from the data files, loaded once.
 */
final readonly class RenderData
{
    public function __construct(
        public SpriteCatalog $sprites,
        public GraphicsCatalog $graphics,
        public StatusBarLayout $statusBar,
        public FaceRules $face,
        public IntermissionLayout $intermission,
        public LightProfile $lights,
        public Limits $limits,
    ) {}

    /**
     * Loads the tables from the data directory of the project and the limits from the shader directory, or from the
     * given ones.
     *
     * @throws DataFileException in case of a file is missing or invalid
     */
    public static function load(?DataReader $data = null, ?DataReader $shaders = null): self
    {
        $data ??= DataReader::createDefault();
        $shaders ??= new DataReader(\dirname(__DIR__, 3) . '/resources/shaders');

        return new self(
            SpriteCatalog::createFromRecord($data->read('sprites.json')),
            GraphicsCatalog::createFromRecord($data->read('hud/graphics.json')),
            StatusBarLayout::createFromRecord($data->read('hud/statusbar.json')),
            FaceRules::createFromRecord($data->read('hud/face.json')),
            IntermissionLayout::createFromRecord($data->read('hud/intermission.json')),
            LightProfile::createFromRecord($data->read('lights.json')),
            Limits::createFromRecord($shaders->read('limits.json')),
        );
    }
}
