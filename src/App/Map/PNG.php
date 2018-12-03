<?php

declare(strict_types=1);

namespace App\Map;

class PNG
{
    const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    private $key;
    private $slug;

    private $image;
    private $height;
    private $width;

    public function __construct(string $key, string $slug)
    {
        $this->key = $key;
        $this->slug = $slug;

        $this->image = imagecreatefrompng(
            sprintf('data/maps/%s/temp/%s-src.png', [$this->key, $this->slug])
        );

        $this->height = imagesy($im);
        $this->width = imagesx($im);

        imagealphablending($this->image, false);
        imagesavealpha($this->image, true);
    }

    public function __destruct()
    {
        imagedestroy($this->image);
    }

    private function addScale() : void
    {
        $scaleImage = imagecreatefrompng(
            sprintf('data/maps/%s/temp/%s-scale.png', [$this->key, $this->slug])
        );

        $scaleHeight = imagesy($scaleImage);
        $scaleWidth = imagesx($scaleImage);

        imagecopymerge($this->image, $scaleImage, 5, ($this->height - $scaleHeight - 5), 0, 0, $scaleWidth, $scaleHeight, 75);

        imagedestroy($scaleImage);
    }

    private function addCopyright() : void
    {
        $copyright = 'Map Data © OpenStreetMap contributors, Statistics Belgium © '.date('Y').' GEO-6';

        $bbox = imageftbbox(7, 90, self::FONT, $copyright);

        $copyrightHeight = abs($bbox[1]) + abs($bbox[5]) + 10;
        $copyrightWidth = abs($bbox[0]) + abs($bbox[2]) + 10;

        $copyrightImage = imagecreatetruecolor($copyrightWidth, $copyrightHeight);

        imagefilledrectangle($copyrightImage, 0, 0, $copyrightWidth, $copyrightHeight, imagecolorallocate($copyrightImage, 255, 255, 255));

        imagefttext($copyrightImage, 7, 90, ($copyrightWidth - 3 - $bbox[1]), ($copyrightHeight - 5), imagecolorexact($copyrightImage, 80, 80, 80), self::FONT, $copyright);

        imagecopymerge($this->image, $copyrightImage, ($this->width - $copyrightWidth - 5), ($this->height - $copyrightHeight - 5), 0, 0, $copyrightWidth, $copyrightHeight, 50);

        imagedestroy($copyrightImage);
    }

    private function addTitle(string $title) : void
    {
        $bbox = imageftbbox(10, 0, self::FONT, $title);

        $titleHeight = abs($bbox[1]) + abs($bbox[5]) + 6;
        $titleWidth = abs($bbox[0]) + abs($bbox[2]) + 6;

        $titleImage = imagecreatetruecolor($titleWidth, $titleHeight);

        imagefilledrectangle($titleImage, 0, 0, $titleWidth, $titleHeight, imagecolorallocate($titleImage, 255, 255, 255));

        imagefttext($titleImage, 10, 0, 3, ($titleHeight - 3 - $bbox[1] - 1), imagecolorexact($titleImage, 255, 128, 0), self::FONT, $title);

        imagecopymerge($this->image, $titleImage, 5, 5, 0, 0, $titleWidth, $titleHeight, 75);

        imagedestroy($titleImage);
    }

    private function addListMunicipalities(array $municipalities, float $top) : void
    {
        $text = implode(', ', $municipalities);
        $text = wordwrap($text, 55);

        $bbox = imageftbbox(8, 0, self::FONT, $text);

        $municipalitiesHeight = abs($bbox[1]) + abs($bbox[5]) + 6;
        $municipalitiesWidth = abs($bbox[0]) + abs($bbox[2]) + 6;

        $municipalitiesImage = imagecreatetruecolor($municipalitiesWidth, $municipalitiesHeight);

        imagefilledrectangle($municipalitiesImage, 0, 0, $municipalitiesWidth, $municipalitiesHeight, imagecolorallocate($municipalitiesImage, 255, 255, 255));

        imagefttext($municipalitiesImage, 8, 0, 3, ($municipalitiesHeight - 3 - $bbox[1] - 1), imagecolorexact($municipalitiesImage, 128, 0, 0), self::FONT, $text);

        imagecopymerge($this->image, $municipalitiesImage, 5, /*5 + $titleHeight + */5, 0, 0, $municipalitiesWidth, $municipalitiesHeight, 75);

        imagedestroy($municipalitiesImage);
    }

    public function save() : bool
    {
        return imagepng($this->image, sprintf('data/maps/%s/%s.png', [$this->key, $this->slug]));
    }
}
