<?php

namespace AwardWallet\Engine\delta\Email\Airport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Resolver
{
    /* @var \Psr\Log\LoggerInterface $logger */
    protected static $logger;

    // numbered array
    // code, name, city
    protected static $world = null;

    protected static $us = null;

    protected static $cache = [
        'Kennedy Intl, New York'                => 'JFK',
        'Chicago-o\'hare Intl, Illinois'        => 'ORD',
        'George Bush Intercontinental/h, Texas' => 'IAH',
        'Paris - Charles De Gaulle, France'     => 'CDG',
        'Cleveland, Ohio'                       => 'CLE',
        'Punta Cana, Dom Rep'                   => 'PUJ',
        'San Juan'                              => 'SJU',
    ];

    public function __construct()
    {
        if (!isset(self::$logger)) {
            self::$logger = new NullLogger();
        }

        if (!isset(self::$world)) {
            self::$world = json_decode(file_get_contents(__DIR__ . '/world.json'), true);
            self::$us = json_decode(file_get_contents(__DIR__ . '/us.json'), true);
        }
    }

    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    public function resolve($name)
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $parts = array_map([$this, 'normalize'], explode(',', $name, 2));
        $match = null;

        switch (count($parts)) {
            case 1:
                foreach (self::$world as $country => $arr) {
                    foreach ($arr as $airport) {
                        if (strcasecmp($airport[1], $parts[0]) === 0 || strcasecmp($airport[2], $parts[0]) === 0) {
                            if (isset($match)) {
                                self::$logger->debug('double match', ['dar' => $name]);
                                $match = null;

                                break 2;
                            } else {
                                $match = $airport[0];
                            }
                        }
                    }
                }

                break;

            case 2:
                $parts[1] = $this->mapStateCountry($parts[1]);

                if (isset(self::$us[$parts[1]])) {
                    $match = $this->findIn($parts[0], self::$us[$parts[1]]);
                } elseif (isset(self::$world[$parts[1]])) {
                    $match = $this->findIn($parts[0], self::$world[$parts[1]]);
                }

                break;

            default:
                break;
        }

        if (!isset($match)) {
            self::$logger->info('couldnt match', ['dar' => $name]);

            return null;
        }
        self::$cache[$name] = $match;

        return $match;
    }

    protected function normalize($s)
    {
        $s = preg_replace('/(\s+Intl|\s+Arpt|\s+Airp|\s+Airport|\s+International|\s+National|\s+Natl)+$/', '', trim($s));
        $s = preg_replace('/^(Airport\s+|Intl\s+|Arpt\s+|Airp\s+|International\s+|National\s+|Natl\s+)+/', '', trim($s));
        $s = str_replace('St.', 'St', $s);

        return str_replace([' - ', '/', '-'], '_', $s);
    }

    protected function mapStateCountry($s)
    {
        $map = [
            'District of Columbia' => 'District Of Columbia',
            'Washington DC'        => 'District Of Columbia',
        ];

        if (isset($map[$s])) {
            return $map[$s];
        }

        return $s;
    }

    protected function findIn($text, $in)
    {
        $match = null;

        foreach ($in as $airport) {
            if (strcasecmp($airport[1], $text) === 0 || strcasecmp($airport[2], $text) === 0) {
                if (isset($match)) {
                    self::$logger->debug('double match', ['dar' => $text]);

                    return null;
                } else {
                    $match = $airport[0];
                }
            }
        }

        return $match;
    }
}
