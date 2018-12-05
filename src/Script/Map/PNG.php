<?php

declare (strict_types = 1);

namespace Script\Map;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PNG
{
    const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    const FONTBOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    private $key;
    private $slug;

    private $image;
    private $height;
    private $width;

    private $currentY = 0;

    public function __construct(string $key, string $slug)
    {
        $this->key = $key;
        $this->slug = $slug;

        $this->renderMap();
        $this->renderScale();

        $this->image = imagecreatefrompng(
            sprintf('data/maps/%s/temp/%s-src.png', $this->key, $this->slug)
        );

        $this->height = imagesy($this->image);
        $this->width = imagesx($this->image);

        imagealphablending($this->image, false);
        imagesavealpha($this->image, true);
    }

    public function __destruct()
    {
        imagedestroy($this->image);
    }

    private function renderMap() : void
    {
        $process = new Process(
            sprintf(
                'shp2img -m %s -o %s -l %s',
                escapeshellarg($this->slug . '.map'),
                escapeshellarg($this->slug . '-src.png'),
                escapeshellarg('land landusegreen water majorroad province ' . $this->slug . ' city')
            ),
            sprintf('data/maps/%s/temp/', $this->key)
        );

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > ' . $buffer;
            } else {
                echo 'OUT > ' . $buffer;
            }
        });
    }

    private function renderScale() : void
    {
        $process = new Process(
            sprintf(
                'scalebar %s %s',
                escapeshellarg($this->slug . '.map'),
                escapeshellarg($this->slug . '-scale.png')
            ),
            sprintf('data/maps/%s/temp/', $this->key)
        );

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > ' . $buffer;
            } else {
                echo 'OUT > ' . $buffer;
            }
        });
    }

    public function addScale() : void
    {
        $scaleImage = imagecreatefrompng(
            sprintf('data/maps/%s/temp/%s-scale.png', $this->key, $this->slug)
        );

        $scaleHeight = imagesy($scaleImage);
        $scaleWidth = imagesx($scaleImage);

        imagecopymerge($this->image, $scaleImage, 5, ($this->height - $scaleHeight - 5), 0, 0, $scaleWidth, $scaleHeight, 75);

        imagedestroy($scaleImage);
    }

    public function addCopyright() : void
    {
        $copyright = 'Map Data © OpenStreetMap contributors, Statistics Belgium © ' . date('Y') . ' GEO-6';

        $bbox = imageftbbox(7, 90, self::FONT, $copyright);

        $copyrightHeight = abs($bbox[1]) + abs($bbox[5]) + 10;
        $copyrightWidth = abs($bbox[0]) + abs($bbox[2]) + 10;

        $copyrightImage = imagecreatetruecolor($copyrightWidth, $copyrightHeight);

        imagefilledrectangle($copyrightImage, 0, 0, $copyrightWidth, $copyrightHeight, imagecolorallocate($copyrightImage, 255, 255, 255));

        imagefttext($copyrightImage, 7, 90, ($copyrightWidth - 3 - $bbox[1]), ($copyrightHeight - 5), imagecolorexact($copyrightImage, 80, 80, 80), self::FONT, $copyright);

        imagecopymerge($this->image, $copyrightImage, ($this->width - $copyrightWidth - 5), ($this->height - $copyrightHeight - 5), 0, 0, $copyrightWidth, $copyrightHeight, 50);

        imagedestroy($copyrightImage);
    }

    public function addTitle(string $title, array $color) : void
    {
        $bbox = imageftbbox(10, 0, self::FONTBOLD, $title);

        $titleHeight = abs($bbox[1]) + abs($bbox[5]) + 6;
        $titleWidth = abs($bbox[0]) + abs($bbox[2]) + 6;

        $titleImage = imagecreatetruecolor($titleWidth, $titleHeight);

        imagefilledrectangle($titleImage, 0, 0, $titleWidth, $titleHeight, imagecolorallocate($titleImage, 255, 255, 255));

        $height = $titleHeight - 3 - $bbox[1] - 1;

        imagefttext($titleImage, 10, 0, 3, $height, imagecolorexact($titleImage, $color[0], $color[1], $color[2]), self::FONTBOLD, $title);

        imagecopymerge($this->image, $titleImage, 5, 5, 0, 0, $titleWidth, $titleHeight, 75);

        $this->currentY += 5 + $height;

        imagedestroy($titleImage);
    }

    public function addListMunicipalities(array $municipalities, array $color) : void
    {
        $text = implode(', ', $municipalities);
        $text = wordwrap($text, 55);

        $bbox = imageftbbox(8, 0, self::FONT, $text);

        $municipalitiesHeight = abs($bbox[1]) + abs($bbox[5]) + 6;
        $municipalitiesWidth = abs($bbox[0]) + abs($bbox[2]) + 6;

        $municipalitiesImage = imagecreatetruecolor($municipalitiesWidth, $municipalitiesHeight);

        imagefilledrectangle($municipalitiesImage, 0, 0, $municipalitiesWidth, $municipalitiesHeight, imagecolorallocate($municipalitiesImage, 255, 255, 255));

        $height = $municipalitiesHeight - 3 - $bbox[1] - 1;

        imagefttext($municipalitiesImage, 8, 0, 3, $height, imagecolorexact($municipalitiesImage, $color[0], $color[1], $color[2]), self::FONT, $text);

        imagecopymerge($this->image, $municipalitiesImage, 5, $this->currentY + 10, 0, 0, $municipalitiesWidth, $municipalitiesHeight, 75);

        $this->currentY += 10 + $height;

        imagedestroy($municipalitiesImage);
    }

    public function save() : bool
    {
        return imagepng($this->image, sprintf('data/maps/%s/%s.png', $this->key, $this->slug));
    }
}
