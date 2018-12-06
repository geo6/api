<?php

declare(strict_types=1);

namespace Script\Map;

use Symfony\Component\Process\Process;
use ErrorException;

class PNG
{
    const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    const FONTBOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    /** @var string */
    private $key;

    /** @var string */
    private $slug;

    /** @var resource */
    private $image;

    /** @var int */
    private $height;

    /** @var int */
    private $width;

    /** @var int */
    private $currentY = 0;

    public function __construct(string $key, string $slug)
    {
        $this->key = $key;
        $this->slug = $slug;

        $this->renderMap();
        $this->renderScale();

        $path = sprintf('data/maps/%s/temp/%s-src.png', $this->key, $this->slug);

        $image = imagecreatefrompng($path);

        if ($image === false) {
            throw new ErrorException(
                sprintf('Unable to imagecreatefrompng("%s").', $path)
            );
        }

        $this->image = $image;

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
                escapeshellarg($this->slug.'.map'),
                escapeshellarg($this->slug.'-src.png'),
                escapeshellarg('land landusegreen water majorroad province '.$this->slug.' city')
            ),
            sprintf('data/maps/%s/temp/', $this->key)
        );

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > '.$buffer;
            } else {
                echo 'OUT > '.$buffer;
            }
        });
    }

    private function renderScale() : void
    {
        $process = new Process(
            sprintf(
                'scalebar %s %s',
                escapeshellarg($this->slug.'.map'),
                escapeshellarg($this->slug.'-scale.png')
            ),
            sprintf('data/maps/%s/temp/', $this->key)
        );

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > '.$buffer;
            } else {
                echo 'OUT > '.$buffer;
            }
        });
    }

    public function addScale() : void
    {
        $path = sprintf('data/maps/%s/temp/%s-scale.png', $this->key, $this->slug);

        $image = imagecreatefrompng($path);

        if ($image === false) {
            throw new ErrorException(
                sprintf('Unable to imagecreatefrompng("%s").', $path)
            );
        }

        $height = imagesy($image);
        $width = imagesx($image);

        imagecopymerge($this->image, $image, 5, ($this->height - $height - 5), 0, 0, $width, $height, 75);

        imagedestroy($image);
    }

    public function addCopyright() : void
    {
        $copyright = 'Map Data © OpenStreetMap contributors, Statistics Belgium © '.date('Y').' GEO-6';

        $bbox = imageftbbox(7, 90, self::FONT, $copyright);

        $height = (int) round(abs($bbox[1]) + abs($bbox[5]) + 10);
        $width = (int) round(abs($bbox[0]) + abs($bbox[2]) + 10);

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new ErrorException('Unable to imagecreatetruecolor() for copyright.');
        }

        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 255, 255, 255));

        imagefttext($image, 7, 90, ($width - 3 - $bbox[1]), ($height - 5), imagecolorexact($image, 80, 80, 80), self::FONT, $copyright);

        imagecopymerge($this->image, $image, ($this->width - $width - 5), ($this->height - $height - 5), 0, 0, $width, $height, 50);

        imagedestroy($image);
    }

    public function addTitle(string $title, array $color) : void
    {
        $bbox = imageftbbox(10, 0, self::FONTBOLD, $title);

        $height = (int) round(abs($bbox[1]) + abs($bbox[5]) + 6);
        $width = (int) round(abs($bbox[0]) + abs($bbox[2]) + 6);

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new ErrorException('Unable to imagecreatetruecolor() for title.');
        }

        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 255, 255, 255));

        $y = (int) round($height - 3 - $bbox[1] - 1);

        imagefttext($image, 10, 0, 3, $y, imagecolorexact($image, $color[0], $color[1], $color[2]), self::FONTBOLD, $title);

        imagecopymerge($this->image, $image, 5, 5, 0, 0, $width, $height, 75);

        $this->currentY += 5 + $y;

        imagedestroy($image);
    }

    public function addListMunicipalities(array $municipalities, array $color) : void
    {
        $text = implode(', ', $municipalities);
        $text = wordwrap($text, 55);

        $bbox = imageftbbox(8, 0, self::FONT, $text);

        $height = (int) round(abs($bbox[1]) + abs($bbox[5]) + 6);
        $width = (int) round(abs($bbox[0]) + abs($bbox[2]) + 6);

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new ErrorException('Unable to imagecreatetruecolor() for municipalities.');
        }

        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 255, 255, 255));

        $y = (int) round($height - 3 - $bbox[1] - 1);

        imagefttext($image, 8, 0, 3, $y, imagecolorexact($image, $color[0], $color[1], $color[2]), self::FONT, $text);

        imagecopymerge($this->image, $image, 5, $this->currentY + 10, 0, 0, $width, $height, 75);

        $this->currentY += 10 + $y;

        imagedestroy($image);
    }

    public function save() : bool
    {
        return imagepng($this->image, sprintf('data/maps/%s/%s.png', $this->key, $this->slug));
    }
}
