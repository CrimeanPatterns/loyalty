<?php

namespace AwardWallet\Engine\eurobonus\Email;

class CheckNewspaper2 extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-5347412.eml, eurobonus/it-9653073.eml";

    public $reFrom = '@sas.se';
    public $reSubject = [
        'SAS Check-in',
    ];

    public $lang = '';

    public $reBody = 'sas.se';
    public $langDetectors = [
        'da' => ['Nu kan du checke ind og downloade din digitale avis'],
        'sv' => ["'Checka in'-knappen nedan och välj sittplats"],
        'en' => ["'Check-in' button below and select your seat"],
        'ja' => ["て、お座席をお選びください。"],
    ];

    public $date;

    public static $dict = [
        'da' => [
            'Booking reference' => 'Bookingreference',
            'Departure'         => 'Afgang',
            'PASSENGER'         => 'Passager(er)',
            //			'Frequent Flyer Program:' => '',
        ],
        'sv' => [
            'Booking reference'       => 'Bokningsreferens',
            'Departure'               => 'Avgång',
            'PASSENGER'               => 'Passagerare:',
            'Frequent Flyer Program:' => 'Bonusprogram:',
        ],
        'ja' => [
            'Booking reference'       => '予約番号',
            'Departure'               => '出発::',
            'PASSENGER'               => '旅客氏名',
            'Frequent Flyer Program:' => 'フリークエント　フライヤー　プログラム:',
        ],
        'en' => [],
    ];

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t('Booking reference') . "')]/following::text()[normalize-space(.)][1]");

        $segments = $this->http->XPath->query('//text()[contains(normalize-space(.),"' . $this->t('Departure') . '")]/ancestor::table[1]');

        foreach ($segments as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode(".//tr[1]/td[1]", $root)));

            $seg = [];

            $flight = $this->http->FindSingleNode('.//tr[1]/td[4]', $root);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            $departure = $this->http->FindSingleNode('.//tr[2]/td[2]', $root);

            if (preg_match('/(.+)\s+([A-Z]{3})/', $departure, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepCode'] = $matches[2];
            }

            $timeDep = $this->http->FindSingleNode('.//tr[3]/td[2]', $root, true, "#\d{4}#");

            if (preg_match("#(\d{2})(\d{2})#", $timeDep, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ":" . $m[2], $date);
            }

            $arrival = $this->http->FindSingleNode('.//tr[2]/td[3]', $root);

            if (preg_match('/(.+)\s+([A-Z]{3})/', $arrival, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrCode'] = $matches[2];
            }

            $timeArr = $this->http->FindSingleNode('.//tr[3]/td[3]', $root, true, "#\d{4}#");

            if (preg_match("#(\d{2})(\d{2})#", $timeArr, $m)) {
                $seg['ArrDate'] = strtotime($m[1] . ":" . $m[2], $date);
            }

            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }

        $xpathFragment1 = '//text()[contains(normalize-space(.),"' . $this->t('PASSENGER') . '")]/ancestor::tr[1]/following::table[contains(.,"E-Ticket:")]';

        // Passengers
        $passengers = $this->http->FindNodes($xpathFragment1 . '/descendant::td/descendant::text()[string-length(normalize-space(.))>1][1][ ./ancestor::*[name()="b" or name()="strong"] ]');

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        // TicketNumbers - need example!

        // AccountNumbers
        $accountNumbers = $this->http->FindNodes($xpathFragment1 . '/descendant::tr[starts-with(normalize-space(.),"' . $this->t('Frequent Flyer Program:') . '")]/following-sibling::tr[1]/td', null, '/^([A-Z\d]{5,})$/');
        $accountNumberValues = array_values(array_filter($accountNumbers));

        if (!empty($accountNumberValues[0])) {
            $it['AccountNumbers'] = array_unique($accountNumberValues);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $NBSP = chr(194) . chr(160);
        $this->http->SetEmailBody(str_replace($NBSP, " ", $this->http->Response['body']), true); // bad fr char " :"

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'CheckNewspaper2_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d{2})\s*(\S+)\s*(\d{2,4})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
        $str = $this->dateStringToEnglish($str);

        return $str;
    }
}
