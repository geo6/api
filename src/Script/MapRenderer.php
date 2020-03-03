<?php

declare(strict_types=1);

namespace Script;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use ErrorException;
use PDO;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Filter\FilterChain;
use Laminas\Filter\StringToLower;
use Laminas\Filter\Word\CamelCaseToDash;
use Laminas\I18n\Filter\Alnum;

class MapRenderer
{
    /** @var array */
    private $config;

    /** @var Composer */
    private $composer;

    /** @var string Path to this file. */
    private $installerSource;

    /** @var IOInterface */
    private $io;

    /** @var string */
    private $layer;

    /** @var array */
    private $list;

    /** @var string */
    private $projectRoot;

    public static function render(Event $event): void
    {
        $installer = new self($event->getIO(), $event->getComposer());

        $installer->layer = $installer->requestLayer();

        $installer->io->write(sprintf('<warning>Clear folder "data/maps/%s/temp".</warning>', $installer->layer));
        $installer->clearTemp();
        $installer->io->write(sprintf('<warning>Clear folder "data/maps/%s".</warning>', $installer->layer));
        $installer->clear();

        if ($installer->layer === 'municipality') {
            $installer->list = $installer->getListMunicipality();
        } else {
            $installer->list = $installer->getListZone();
        }

        $installer->io->write(sprintf('Count of maps to render: %d', count($installer->list)));

        foreach ($installer->list as $i => $record) {
            $installer->generateMapFile(
                $record['slug'],
                $record['extent'],
                $record['nis5']
            );

            $installer->renderMap(
                $record[$installer->layer],
                $record['slug'],
                $record['municipalities']
            );

            $installer->io->write(
                sprintf(
                    '<info>Rendered: %d/%d - %s</info>',
                    ($i + 1),
                    count($installer->list),
                    $record[$installer->layer]
                )
            );
        }

        $installer->io->write(sprintf('<warning>Clear folder "data/maps/%s/temp".</warning>', $installer->layer));
        $installer->clearTemp();
    }

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;

        // Get composer.json location
        $composerFile = Factory::getComposerFile();

        // Calculate project root from composer.json, if necessary
        $this->projectRoot = realpath(dirname($composerFile)) ?? '';
        $this->projectRoot = rtrim($this->projectRoot, '/\\').'/';

        // Parse the composer.json
        // $this->parseComposerDefinition($composer, $composerFile);

        $this->config = require $this->projectRoot.'config/autoload/local.php';

        // Source path for this file
        $this->installerSource = realpath(__DIR__).'/';
    }

    private function clear(): void
    {
        $directory = sprintf('data/maps/%s', $this->layer);

        if (file_exists($directory) && is_dir($directory)) {
            $glob = glob($directory.'/*');
            foreach ($glob as $g) {
                if (!is_dir($g)) {
                    unlink($g);
                } else {
                    $this->io->write(sprintf('  <comment>Skipped "%s".</comment>', $g));
                }
            }
        }
    }

    private function clearTemp(): void
    {
        $directory = sprintf('data/maps/%s/temp', $this->layer);

        if (file_exists($directory) && is_dir($directory)) {
            $glob = glob($directory.'/*');
            foreach ($glob as $g) {
                if (!is_dir($g)) {
                    unlink($g);
                } else {
                    $this->io->write(sprintf('  <comment>Skipped "%s".</comment>', $g));
                }
            }

            rmdir($directory);
        }
    }

    private function requestLayer(): string
    {
        $query = [
            sprintf(
                "\n  <question>%s</question>\n",
                'Which layer do you want to render?'
            ),
            "  [<comment>1</comment>] Civil Protection\n",
            "  [<comment>2</comment>] Emergency\n",
            "  [<comment>3</comment>] Fire Service\n",
            "  [<comment>4</comment>] Judicial Canton\n",
            "  [<comment>5</comment>] Judicial Distrcit\n",
            "  [<comment>6</comment>] Municipality\n",
            "  [<comment>7</comment>] Police\n",
            '  Make your selection: ',
        ];

        while (true) {
            $answer = $this->io->ask(implode($query));

            switch (true) {
                case $answer === '1':
                    return 'civilprotection';
                case $answer === '2':
                    return 'emergency';
                case $answer === '3':
                    return 'fireservice';
                case $answer === '4':
                    return 'judicialcanton';
                case $answer === '5':
                    return 'judicialdistrict';
                case $answer === '6':
                    return 'municipality';
                case $answer === '7':
                    return 'police';
                default:
                    // @codeCoverageIgnoreStart
                    $this->io->write('<error>Invalid answer</error>');
                    // @codeCoverageIgnoreEnd
            }
        }
    }

    private function getListZone(): array
    {
        $adapter = new Adapter(
            array_merge([
                'driver'         => 'Pdo_Pgsql',
                'driver_options' => [
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ],
            ], $this->config['postgresql'])
        );

        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from(['z' => 'zone'])
            ->columns([
                $this->layer,
                'nis5' => new Expression('array_to_json(array_agg(z.nis5))'),
            ])
            ->join(
                ['m' => 'municipality'],
                'm.nis5 = z.nis5',
                [
                    'mun_name_fr' => new Expression('array_to_json(array_agg(m.name_fr))'),
                    'mun_name_nl' => new Expression('array_to_json(array_agg(m.name_nl))'),
                    'extent'      => new Expression('Box2D(ST_Transform(ST_SetSRID(ST_Extent(m.the_geog::geometry), 4326), 3857))'),
                ]
            )
            ->group([
                $this->layer,
            ])
            ->order([
                $this->layer,
            ]);

        $qsz = $sql->buildSqlString($select);

        $result = $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);

        $list = [];
        foreach ($result as $r) {
            $filterChain = new FilterChain();
            $filterChain
                ->attach(new Alnum())
                ->attach(new CamelCaseToDash())
                ->attach(new StringToLower());

            $slug = $filterChain->filter(self::removeAccents($r->{$this->layer}));

            $mun_name_fr = json_decode($r->mun_name_fr);
            $mun_name_nl = json_decode($r->mun_name_nl);

            if (count($mun_name_fr) !== count($mun_name_nl)) {
                throw new ErrorException('Municipalities name count not coherent !');
            }

            $mun_names = [];
            for ($i = 0; $i < count($mun_name_fr); $i++) {
                $name = ucwords(strtolower($mun_name_fr[$i]), "- \t\r\n\f\v");

                if ($mun_name_nl[$i] !== $mun_name_fr[$i]) {
                    $name .= '/'.ucwords(strtolower($mun_name_nl[$i]), "- \t\r\n\f\v");
                }

                $mun_names[] = $name;
            }
            sort($mun_names);

            $extent = [];
            if (preg_match('/^BOX\(([\-0-9.]+) ([\-0-9.]+),([\-0-9.]+) ([\-0-9.]+)\)$/', $r->extent, $matches) === 1) {
                $extent = [
                    'minx' => round(floatval($matches[1])),
                    'miny' => round(floatval($matches[2])),
                    'maxx' => round(floatval($matches[3])),
                    'maxy' => round(floatval($matches[4])),
                ];
            } else {
                throw new ErrorException(sprintf('Invalid extent for "%s" : %s.', $r->{$this->layer}, $r->extent));
            }

            $list[] = [
                $this->layer     => $r->{$this->layer},
                'slug'           => $slug,
                'nis5'           => json_decode($r->nis5),
                'municipalities' => $mun_names,
                'extent'         => $extent,
            ];
        }

        return $list;
    }

    private function getListMunicipality(): array
    {
        $adapter = new Adapter(
            array_merge([
                'driver'         => 'Pdo_Pgsql',
                'driver_options' => [
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ],
            ], $this->config['postgresql'])
        );

        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from('municipality')
            ->columns([
                'nis5',
                'name_fr',
                'name_nl',
                'extent' => new Expression('Box2D(ST_Transform(the_geog::geometry, 3857))'),
            ])
            ->order([
                'nis5',
            ]);

        $qsz = $sql->buildSqlString($select);

        $result = $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);

        $list = [];
        foreach ($result as $r) {
            $mun_name = ucwords(strtolower($r->name_fr), "- \t\r\n\f\v");

            if ($r->name_nl !== $r->name_fr) {
                $mun_name .= '/'.ucwords(strtolower($r->name_nl), "- \t\r\n\f\v");
            }

            $extent = [];
            if (preg_match('/^BOX\(([\-0-9.]+) ([\-0-9.]+),([\-0-9.]+) ([\-0-9.]+)\)$/', $r->extent, $matches) === 1) {
                $extent = [
                    'minx' => round(floatval($matches[1])),
                    'miny' => round(floatval($matches[2])),
                    'maxx' => round(floatval($matches[3])),
                    'maxy' => round(floatval($matches[4])),
                ];
            } else {
                throw new ErrorException(sprintf('Invalid extent for "%s" : %s.', $r->nis5, $r->extent));
            }

            $list[] = [
                'municipality'   => $mun_name,
                'slug'           => (string) $r->nis5,
                'nis5'           => [$r->nis5],
                'municipalities' => [$mun_name],
                'extent'         => $extent,
            ];
        }

        return $list;
    }

    private function generateMapFile(string $slug, array $extent, array $nis5): void
    {
        $mapfile = new Map\MapFile($this->layer, $slug, $extent);

        $mapfile->addScalebar();

        $mapfile->addLayerLand();
        $mapfile->addLayerLanduseGreen();
        $mapfile->addLayerWater();
        $mapfile->addLayerRoad();
        $mapfile->addLayerProvince();
        $mapfile->addLayerZone(
            $this->config['postgresql']['host'],
            $this->config['postgresql']['port'],
            $this->config['postgresql']['dbname'],
            $this->config['postgresql']['username'],
            $this->config['postgresql']['password'],
            $nis5,
            $this->getColor()
        );
        switch ($this->layer) {
            case 'civilprotection':
                $mapfile->addLayerCity(true, false);
                break;
            case 'emergency':
                $mapfile->addLayerCity(true, false);
                break;
            case 'fireservice':
                $mapfile->addLayerCity(true, true);
                break;
            case 'judicialcanton':
                $mapfile->addLayerCity(true, true);
                break;
            case 'judicialdistrict':
                $mapfile->addLayerCity(true, false);
                break;
            case 'municipality':
                $mapfile->addLayerCity(true, true);
                break;
            case 'police':
                $mapfile->addLayerCity(true, true);
                break;
        }

        $mapfile->save();
    }

    private function getColor(): array
    {
        switch ($this->layer) {
            case 'civilprotection':
                return [255, 128, 0];
            case 'emergency':
                return [255, 0, 255];
            case 'fireservice':
                return [255, 0, 0];
            case 'judicialcanton':
                return [128, 0, 0];
            case 'judicialdistrict':
                return [128, 0, 0];
            case 'municipality':
                return [80, 80, 80];
            case 'police':
                return [0, 0, 255];
            default:
                return [80, 80, 80];
        }
    }

    private function renderMap(string $name, string $slug, array $municipalities): void
    {
        $png = new Map\PNG($this->layer, $slug);

        $png->addScale();
        $png->addCopyright();

        switch ($this->layer) {
            case 'civilprotection':
                $png->addTitle('Civil Protection : '.$name, $this->getColor());
                // $png->addListMunicipalities($municipalities, $this->getColor());
                break;
            case 'emergency':
                $png->addTitle('Emergency : '.$name, $this->getColor());
                // $png->addListMunicipalities($municipalities, $this->getColor());
                break;
            case 'fireservice':
                $png->addTitle('Fire Service : '.$name, $this->getColor());
                $png->addListMunicipalities($municipalities, $this->getColor());
                break;
            case 'judicialcanton':
                $png->addTitle('Judicial Canton : '.$name, $this->getColor());
                $png->addListMunicipalities($municipalities, $this->getColor());
                break;
            case 'judicialdistrict':
                $png->addTitle('Judicial District : '.$name, $this->getColor());
                // $png->addListMunicipalities($municipalities, $this->getColor());
                break;
            case 'municipality':
                $png->addTitle($name, $this->getColor());
                break;
            case 'police':
                $png->addTitle('Police : '.$name, $this->getColor());
                $png->addListMunicipalities($municipalities, $this->getColor());
                break;
        }

        $png->save();
    }

    /**
     * @see https://github.com/WordPress/WordPress/blob/master/wp-includes/formatting.php#L1596
     *
     * @param string $string
     *
     * @return string
     */
    private static function removeAccents(string $string): string
    {
        if (preg_match('/[\x80-\xff]/', $string) !== 1) {
            return $string;
        }

        $chars = [
            // Decompositions for Latin-1 Supplement
            'ª' => 'a', 'º' => 'o',
            'À' => 'A', 'Á' => 'A',
            'Â' => 'A', 'Ã' => 'A',
            'Ä' => 'A', 'Å' => 'A',
            'Æ' => 'AE', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I',
            'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O',
            'Ô' => 'O', 'Õ' => 'O',
            'Ö' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U',
            'Ü' => 'U', 'Ý' => 'Y',
            'Þ' => 'TH', 'ß' => 's',
            'à' => 'a', 'á' => 'a',
            'â' => 'a', 'ã' => 'a',
            'ä' => 'a', 'å' => 'a',
            'æ' => 'ae', 'ç' => 'c',
            'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o',
            'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'þ' => 'th',
            'ÿ' => 'y', 'Ø' => 'O',
            // Decompositions for Latin Extended-A
            'Ā' => 'A', 'ā' => 'a',
            'Ă' => 'A', 'ă' => 'a',
            'Ą' => 'A', 'ą' => 'a',
            'Ć' => 'C', 'ć' => 'c',
            'Ĉ' => 'C', 'ĉ' => 'c',
            'Ċ' => 'C', 'ċ' => 'c',
            'Č' => 'C', 'č' => 'c',
            'Ď' => 'D', 'ď' => 'd',
            'Đ' => 'D', 'đ' => 'd',
            'Ē' => 'E', 'ē' => 'e',
            'Ĕ' => 'E', 'ĕ' => 'e',
            'Ė' => 'E', 'ė' => 'e',
            'Ę' => 'E', 'ę' => 'e',
            'Ě' => 'E', 'ě' => 'e',
            'Ĝ' => 'G', 'ĝ' => 'g',
            'Ğ' => 'G', 'ğ' => 'g',
            'Ġ' => 'G', 'ġ' => 'g',
            'Ģ' => 'G', 'ģ' => 'g',
            'Ĥ' => 'H', 'ĥ' => 'h',
            'Ħ' => 'H', 'ħ' => 'h',
            'Ĩ' => 'I', 'ĩ' => 'i',
            'Ī' => 'I', 'ī' => 'i',
            'Ĭ' => 'I', 'ĭ' => 'i',
            'Į' => 'I', 'į' => 'i',
            'İ' => 'I', 'ı' => 'i',
            'Ĳ' => 'IJ', 'ĳ' => 'ij',
            'Ĵ' => 'J', 'ĵ' => 'j',
            'Ķ' => 'K', 'ķ' => 'k',
            'ĸ' => 'k', 'Ĺ' => 'L',
            'ĺ' => 'l', 'Ļ' => 'L',
            'ļ' => 'l', 'Ľ' => 'L',
            'ľ' => 'l', 'Ŀ' => 'L',
            'ŀ' => 'l', 'Ł' => 'L',
            'ł' => 'l', 'Ń' => 'N',
            'ń' => 'n', 'Ņ' => 'N',
            'ņ' => 'n', 'Ň' => 'N',
            'ň' => 'n', 'ŉ' => 'n',
            'Ŋ' => 'N', 'ŋ' => 'n',
            'Ō' => 'O', 'ō' => 'o',
            'Ŏ' => 'O', 'ŏ' => 'o',
            'Ő' => 'O', 'ő' => 'o',
            'Œ' => 'OE', 'œ' => 'oe',
            'Ŕ' => 'R', 'ŕ' => 'r',
            'Ŗ' => 'R', 'ŗ' => 'r',
            'Ř' => 'R', 'ř' => 'r',
            'Ś' => 'S', 'ś' => 's',
            'Ŝ' => 'S', 'ŝ' => 's',
            'Ş' => 'S', 'ş' => 's',
            'Š' => 'S', 'š' => 's',
            'Ţ' => 'T', 'ţ' => 't',
            'Ť' => 'T', 'ť' => 't',
            'Ŧ' => 'T', 'ŧ' => 't',
            'Ũ' => 'U', 'ũ' => 'u',
            'Ū' => 'U', 'ū' => 'u',
            'Ŭ' => 'U', 'ŭ' => 'u',
            'Ů' => 'U', 'ů' => 'u',
            'Ű' => 'U', 'ű' => 'u',
            'Ų' => 'U', 'ų' => 'u',
            'Ŵ' => 'W', 'ŵ' => 'w',
            'Ŷ' => 'Y', 'ŷ' => 'y',
            'Ÿ' => 'Y', 'Ź' => 'Z',
            'ź' => 'z', 'Ż' => 'Z',
            'ż' => 'z', 'Ž' => 'Z',
            'ž' => 'z', 'ſ' => 's',
            // Decompositions for Latin Extended-B
            'Ș' => 'S', 'ș' => 's',
            'Ț' => 'T', 'ț' => 't',
            // Euro Sign
            '€' => 'E',
            // GBP (Pound) Sign
            '£' => '',
            // Vowels with diacritic (Vietnamese)
            // unmarked
            'Ơ' => 'O', 'ơ' => 'o',
            'Ư' => 'U', 'ư' => 'u',
            // grave accent
            'Ầ' => 'A', 'ầ' => 'a',
            'Ằ' => 'A', 'ằ' => 'a',
            'Ề' => 'E', 'ề' => 'e',
            'Ồ' => 'O', 'ồ' => 'o',
            'Ờ' => 'O', 'ờ' => 'o',
            'Ừ' => 'U', 'ừ' => 'u',
            'Ỳ' => 'Y', 'ỳ' => 'y',
            // hook
            'Ả' => 'A', 'ả' => 'a',
            'Ẩ' => 'A', 'ẩ' => 'a',
            'Ẳ' => 'A', 'ẳ' => 'a',
            'Ẻ' => 'E', 'ẻ' => 'e',
            'Ể' => 'E', 'ể' => 'e',
            'Ỉ' => 'I', 'ỉ' => 'i',
            'Ỏ' => 'O', 'ỏ' => 'o',
            'Ổ' => 'O', 'ổ' => 'o',
            'Ở' => 'O', 'ở' => 'o',
            'Ủ' => 'U', 'ủ' => 'u',
            'Ử' => 'U', 'ử' => 'u',
            'Ỷ' => 'Y', 'ỷ' => 'y',
            // tilde
            'Ẫ' => 'A', 'ẫ' => 'a',
            'Ẵ' => 'A', 'ẵ' => 'a',
            'Ẽ' => 'E', 'ẽ' => 'e',
            'Ễ' => 'E', 'ễ' => 'e',
            'Ỗ' => 'O', 'ỗ' => 'o',
            'Ỡ' => 'O', 'ỡ' => 'o',
            'Ữ' => 'U', 'ữ' => 'u',
            'Ỹ' => 'Y', 'ỹ' => 'y',
            // acute accent
            'Ấ' => 'A', 'ấ' => 'a',
            'Ắ' => 'A', 'ắ' => 'a',
            'Ế' => 'E', 'ế' => 'e',
            'Ố' => 'O', 'ố' => 'o',
            'Ớ' => 'O', 'ớ' => 'o',
            'Ứ' => 'U', 'ứ' => 'u',
            // dot below
            'Ạ' => 'A', 'ạ' => 'a',
            'Ậ' => 'A', 'ậ' => 'a',
            'Ặ' => 'A', 'ặ' => 'a',
            'Ẹ' => 'E', 'ẹ' => 'e',
            'Ệ' => 'E', 'ệ' => 'e',
            'Ị' => 'I', 'ị' => 'i',
            'Ọ' => 'O', 'ọ' => 'o',
            'Ộ' => 'O', 'ộ' => 'o',
            'Ợ' => 'O', 'ợ' => 'o',
            'Ụ' => 'U', 'ụ' => 'u',
            'Ự' => 'U', 'ự' => 'u',
            'Ỵ' => 'Y', 'ỵ' => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin)
            'ɑ' => 'a',
            // macron
            'Ǖ' => 'U', 'ǖ' => 'u',
            // acute accent
            'Ǘ' => 'U', 'ǘ' => 'u',
            // caron
            'Ǎ' => 'A', 'ǎ' => 'a',
            'Ǐ' => 'I', 'ǐ' => 'i',
            'Ǒ' => 'O', 'ǒ' => 'o',
            'Ǔ' => 'U', 'ǔ' => 'u',
            'Ǚ' => 'U', 'ǚ' => 'u',
            // grave accent
            'Ǜ' => 'U', 'ǜ' => 'u',
        ];

        $string = strtr($string, $chars);

        return $string;
    }
}
