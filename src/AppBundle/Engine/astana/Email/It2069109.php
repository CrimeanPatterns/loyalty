<?php

namespace AwardWallet\Engine\astana\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2069109 extends \TAccountChecker
{
    public $subjects = [
        '/Подтверждение Вашего посадочного талона/i',
    ];

    public $lang = 'ru';
    public $from = [
        '@amadeus.com',
        '@airastana.com',
    ];

    public static $dictionary = [
        "ru" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->from as $fromEmail) {
            if (isset($headers['from']) && stripos($headers['from'], $fromEmail) !== false) {
                foreach ($this->subjects as $subject) {
                    if (preg_match($subject, $headers['subject'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Эйр Астана')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Подробности брони'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Время окончания посадки:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]amadeus\.com/', $from) > 0 || preg_match('/[@.]airastana\.com/', $from) > 0;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//*[contains(text(), 'Номер брони')]/following-sibling::span[1]"))
            ->traveller($this->http->FindSingleNode("//*[contains(text(), 'Пассажир')]/following-sibling::span[1]"), true);

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Мы подтверждаем, что вы были успешно зарегистрированы.')]")->length > 0) {
            $f->general()
                ->status('confirmed');
        }

        $nodes = $this->http->XPath->query("//*[contains(text(), 'Рейс')]/ancestor-or-self::td[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/Рейс\:\n\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{2,4})\s*\-\s*(?<cabin>.*)\n/u", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()='Откуда:']/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Откуда\:\n(?<depName>.+)\n(?:Терминал\s*(?<terminal>.+))?\n*(?<depDate>.+\d+\:\d+)/", $depInfo, $m)) {
                $date = str_replace(" -", ",", $m['depDate']);
                $s->departure()
                    ->noCode()
                    ->date(strtotime($date))
                    ->name($m['depName']);

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()='Куда:']/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Куда\:\n(?<arrName>.+)\n(?:Терминал\s*(?<terminal>.+))?\n*(?<arrDate>.+\d+\:\d+)/", $arrInfo, $m)) {
                $date = str_replace(" -", ",", $m['arrDate']);
                $s->arrival()
                    ->noCode()
                    ->date(strtotime($date))
                    ->name($m['arrName']);

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}

/*class It2069109 extends \TAccountCheckerExtended
{
    public $rePlain = "#Ваши дорожные документы#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your Boarding Pass Confirmation#i";
    public $langSupported = "ru";
    public $typesCount = "1";
    public $reFrom = "";
    public $reProvider = "#astana#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "astana/it-383788268.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Номер брони')]/following-sibling::span[1]");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Пассажир')]/following-sibling::span[1]");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#Мы подтверждаем, что вы были успешно зарегистрированы.#")) {
                            return "confirmed";
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Рейс')]/ancestor-or-self::td[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            //$node = node("./ancestor-or-self::tr/following-sibling::tr[3]");
                            $node = node("//span[contains(., 'Рейс')]/following-sibling::span[1]");

                            return uberAir($node);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./ancestor-or-self::tr/following-sibling::tr[1]//span[2]");

                            return $node;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $node = node("./ancestor-or-self::tr/following-sibling::tr[1]//span[4]");

                            if ($node == null) {
                                $node = $node = node("./ancestor-or-self::tr/following-sibling::tr[1]//span[3]");
                            }

                            return totime(uberDatetime($node));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./ancestor-or-self::tr/following-sibling::tr[3]//span[2]");

                            return $node;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $node = node("./ancestor-or-self::tr/following-sibling::tr[3]//span[4]");

                            if ($node == null) {
                                $node = node("./ancestor-or-self::tr/following-sibling::tr[3]//span[3]");
                            }

                            return totime(uberDatetime($node));
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["ru"];
    }
}*/
