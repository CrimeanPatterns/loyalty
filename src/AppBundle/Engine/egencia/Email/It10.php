<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It10 extends \TAccountChecker
{
    public $mailFiles = "egencia/it-1.eml, egencia/it-10.eml, egencia/it-11276780.eml, egencia/it-1639224.eml, egencia/it-1654073.eml, egencia/it-1726320.eml, egencia/it-4.eml, egencia/it-4560478.eml, egencia/it-4886904.eml, egencia/it-6.eml";

    private $detectSubjects = [
        // en
        'Egencia  booking confirmation for',
        'Egencia flight held until',
    ];

    private $langDetectors = [
        'en' => ['View this itinerary online for the most up-to-date information'],
    ];

    private static $dict = [
        'en' => [],
    ];

    private $lang = '';

    public function parseRental(Email $email)
    {
    }

    public function parseEmail(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $conf = $this->http->FindSingleNode("//text()[{$this->eq('Itinerary number:')}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");

        $email->ota()
            ->confirmation($conf);

        // FLIGHT

        $xpath = "//text()[{$this->eq($this->t("Depart"))}]/ancestor::*[.//text()[{$this->eq($this->t("Arrive"))}]][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();
        }
        $travellers = [];

        foreach ($nodes as $root) {
            $travellers[] = $this->http->FindSingleNode("(.//preceding::text()[{$this->starts('Flight (')}])[last()]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root);

            $date = $this->normalizeDate($this->http->FindSingleNode("(.//preceding::text()[{$this->starts('Flight (')}])[last()]/ancestor::tr[1]/preceding::tr[normalize-space()][1]", $root));

            $s = $f->addSegment();

            $table = $this->http->FindNodes(".//text()[{$this->eq($this->t("Depart"))}]/ancestor::tr[1][*[normalize-space()][1][{$this->eq($this->t("Depart"))}]]/*");

            if ($date && preg_match("/^\s*(?<time>\d+:\d+(?:\s*[ap]m)?)(?<overnight>\s*[-+] ?\d+\s*days?)?\s*$/i", $table[1] ?? '', $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], $date));

                if ($s->getDepDate() && !empty($m['overnight'])) {
                    $s->departure()
                        ->date(strtotime($m['overnight'], $s->getDepDate()));
                }
            }

            if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $table[2] ?? '', $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name']);
            }

            if (preg_match("/\bterminal\b/i", $table[3] ?? '') && !empty($table[4])) {
                $s->departure()
                    ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', $table[3])));
            }

            if (count($table) >= 3) {
                $name = $table[count($table) - 1];
                $s->airline()
                    ->name($name);

                if (!empty($name)) {
                    $confs = array_unique($this->http->FindNodes("//text()[{$this->starts(preg_replace("/^(.+)$/", $name . ' $1', $this->t('confirmation code:')))}]",
                        null, "/{$this->opt($this->t('confirmation code:'))}\s*([A-Z\d]{5,7})\s*/"));

                    if (count($confs)) {
                        $s->airline()
                            ->confirmation($confs[0]);
                    }
                }
            }

            $table = $this->http->FindNodes(".//text()[{$this->eq($this->t("Arrive"))}]/ancestor::tr[1][*[normalize-space()][1][{$this->eq($this->t("Arrive"))}]]/*");

            if ($date && preg_match("/^\s*(?<time>\d+:\d+(?:\s*[ap]m)?)(?<overnight>\s*[-+] ?\d+\s*days?)?\s*$/i", $table[1] ?? '', $m)) {
                $s->arrival()
                    ->date(strtotime($m['time'], $date));

                if ($s->getArrDate() && !empty($m['overnight'])) {
                    $s->arrival()
                        ->date(strtotime($m['overnight'], $s->getArrDate()));
                }
            }

            if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $table[2] ?? '', $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name']);
            }

            if (preg_match("/\bterminal\b/i", $table[3] ?? '') && !empty($table[4])) {
                $s->departure()
                    ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', $table[3])));
            }

            if (preg_match("/^\s*Flight (\d{1,5}) - /i", $table[count($table) - 1] ?? '', $m)) {
                $s->airline()
                    ->number($m[1]);
            }

            if (preg_match("/Operated by:\s*(.+)/i", $table[count($table) - 1] ?? '', $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            $extra = $this->http->FindSingleNode("following::text()[normalize-space()][1]/ancestor::tr[normalize-space()][1]", $root);

            if (preg_match("/^\s*Seat (\d{1,3}[A-Z])\s*[\W\S]/", $extra, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }

            if (preg_match("/[,.] ?(?<cabin>[^,]+?)\s*\((?<class>[A-Z]{1,2})\)(?:\s*,\s*(?<meal>[^,]+?))?\s*,\s*(?<duration>( ?\d+ ?(?:hr|mn))+)\s*,\s*(?<aircraft>[^,]+)\s*$/", $extra, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['class'])
                    ->meal($m['meal'] ?? null, true, true)
                    ->duration($m['duration'])
                    ->aircraft($m['aircraft'])
                ;
            }
        }

        if ($f) {
            $f->general()
                ->travellers(array_unique($travellers));
        }
        /*
                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("/[Cc]onfirmation [Cc]ode:\s*([A-Z\d]{5,})/", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passenger = re("#\n\s*Passenger\(s\):\s*([^\n]+)#", $this->text());

                        return [$passenger];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(Flight(?:\s+Change)* Details\s+\w+,\s*\w+\s+\d+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberFlight();
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})(?:\-[^\)]+)*\)#");
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re("/From:\s*\([A-Z]{3}\)\s*([^\n]+)/"), // From: (SEA) Seattle/Tacoma WA, USA
                                re("/From:\s*.*?\s*\([A-Z]{3}\s*-\s*([^)\n]+)\)/") // From: San Francisco, CA (SFO-San Francisco Intl.)
                            );
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dep = strtotime(uberDate() . ', ' . uberTime(2));
                            $arr = strtotime(uberDate() . ', ' . uberTime(3));

                            if ($arr < $dep) {
                                $arr += 24 * 3600;
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})(?:\-[^\)]+)*\)#", 2);
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re("/To:\s*\([A-Z]{3}\)\s*([^\n]+)/"),
                                re("/To:\s*.*?\s*\([A-Z]{3}\s*-\s*([^)\n]+)\)/")
                            );
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return uberAirline();
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return uberName("Equipment");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return uberName("Class");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = re("/\s+Seat:\s*(\d{1,5}[A-Z])\b/");

                            return [$seat];
                        },
                    ],
                ],
            ],
        ];
        */
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]egencia\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//egencia.com/") or contains(@href,"//www.egencia.com/")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->lang) {
//            $result = parent::ParsePlanEmail($parser);
            $this->parseEmail($email);

            return $email;
        } else {
            $this->logger->debug('Can\'t determine a language!');
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

//    public function ParsePlanEmail(\PlancakeEmailParser $parser)
//    {
//        // Detecting Language
//        if ($this->lang) {
//            $this->logger->alert("Can't determine a language!");
//
//            $result = parent::ParsePlanEmail($parser);
//            return null;
//        }
//
//        $result['providerCode'] = $this->providerCode;
//        $result['emailType'] = 'YourFlight' . ucfirst($this->lang);
//
//        return $result;
//    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            if ($this->http->XPath->query('//node()[' . $this->contains($phrases) . ']')->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date in = ' . print_r($date, true));

        $in = [
            // Saturday 14-Jan-2017
            "/^\s*[[:alpha:]]+\s+(\d{1,2})\s*-\s*([[:alpha:]]+)\s*-\s*(\d{4})\s*$/u",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date out = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($str);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }
}
