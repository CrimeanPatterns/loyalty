<?php

namespace AwardWallet\Engine\citybank\Email;

class HConfirmation extends \TAccountChecker
{
    public $mailFiles = "citybank/it-5783686.eml";

    public $reBody = [
        'en' => ['Thank you for contacting Citi Prestige Concierge', 'Confirmation Details'],
    ];
    public $reSubject = [
        '#\s*Citi\s+Prestige\s+Concierge\s+-\s+Case\s+Id:?\s+\#?\s+[A-Z\d]+\s*$#i',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
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
    private $tot;
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "HConfirmation",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'citibank')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src,'citibank') and contains(translate(@src,'PRESTIGE','prestige'),'prestige')]")->length > 0
        ) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@travelcenter") !== false;
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

    protected function AssignLang($body)
    {
        $this->lang = "";

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->lang)) {
            return false;
        }

        return true;
    }

    private function parseEmail()
    {
        $its = [];

        //#HOTEL##
        $xpath = "//text()[contains(.,'Confirmation Details')]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = $this->getValues('Confirmation', $root);
            $it['GuestNames'][] = $this->getValues('Reservation Name', $root);
            $it['HotelName'] = $this->getValues('Hotel', $root);
            $it['Address'] = $this->getValues('Address', $root);
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->getValues('Check-In', $root)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->getValues('Check-Out', $root)));
            $it['RoomType'] = $this->getValues('Room Type', $root);

            $this->tot = $this->getTotalCurrency(str_replace("$", "USD", str_replace("€", "EUR", $this->http->FindSingleNode("//text()[contains(.,'Total Cost of Stay')]/following::text()[normalize-space(.)][1]"))));
            $it['Total'] = $this->tot['Total'];
            $it['Currency'] = $this->tot['Currency'];
            $its[] = $it;
        }

        return $its;
    }

    private function getValues($field, $root = null)
    {
        return implode(' ', $this->http->FindNodes("./descendant::tr[contains(.,'{$field}')]/td[position()>1]", $root));
    }

    private function normalizeDate($date)
    {
        $str = $date;
        $in = [
            '#^\s*Date\s*:\s*(\d{2})\s+(\D{3,})\s*(\d+)\s+Time\s*:\s*(\d+:\d+)\s*$#',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $str = preg_replace($in, $out, $date);
        $str = $this->dateStringToEnglish($str);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
