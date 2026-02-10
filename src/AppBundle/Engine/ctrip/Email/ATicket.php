<?php

namespace AwardWallet\Engine\ctrip\Email;

class ATicket extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-10175408.eml, ctrip/it-11752621.eml, ctrip/it-7173569.eml, ctrip/it-22900849.eml, ctrip/it-22922064.eml, ctrip/it-23574093.eml";

    public $reSubject = [
        'es' => ['Billete(s) disponible(s)'],
        'en' => ['Ticket(s) Available', 'Seat reserved', 'Payment Successful', 'Your train ticket has been changed successfully'],
    ];

    public $langDetectors = [
        'es' => ['Recogida de billete'],
        'en' => ['Ticket Pickup', 'Your seat has been reserved', 'Payment Successful'],
    ];

    public static $dictionary = [
        'es' => [
            "Booking"              => "Nº de reserva",
            "Booking no"           => "No de reserva:",
            "Ticket Pickup Number" => "Número de recogida del billete",
            "Adult"                => ["Adulto", "Niño"], // check "Niño"
            "Carriage"             => "Coche",
            "Total"                => "Total",
            "Account:"             => "cuenta:",
        ],
        'en' => [
            //			"Booking" => "",
            //			"Booking no" => "",
            //			"Ticket Pickup Number" => "",
            //			"Adult" => ["Adult", "Kid", "Child"],
            //			"Carriage" => "",
            //			"Total" => "",
            //			"Account:" => "",
        ],
    ];

    public $lang = '';
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }
        $this->date = strtotime($parser->getDate());

        return [
            'emailType'  => 'ATicket' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Ctrip Train Reservation') !== false
            || stripos($from, 'train_reservation@ctrip.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, 'Ctrip') === false) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail()
    {
        $patterns = [
            'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        ];

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripCategory' => TRIP_CATEGORY_TRAIN];

        // TripNumber
        // Status
        $tripNumber = null;
        $statusText = $this->http->FindSingleNode("//td[ ./descendant::img ]/following-sibling::td[normalize-space(.)][1][{$this->contains($this->t("Booking"))}]");

        if (preg_match("/{$this->preg_implode($this->t("Booking"))}\s*(\d{5,})\s*:?\s*(.{2,})/", $statusText, $matches)) {
            $tripNumber = $matches[1];
            $it['Status'] = trim($matches[2], ' .');
        }

        if ($tripNumber === null) {
            $tripNumber = $this->http->FindSingleNode("//td[(" . $this->contains($this->t("Booking no")) . ") and not(descendant::td)]", null, true, '/' . $this->preg_implode($this->t("Booking no")) . '.?\s*(\w+)/');
        }

        $it['RecordLocator'] = $this->http->FindSingleNode("//td[(" . $this->contains($this->t("Ticket Pickup Number")) . ") and not(descendant::td)]/span[1]");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $tripNumber;
        }

        $seats = $this->http->FindNodes("//td[(" . $this->contains($this->t("Adult")) . ") and not(descendant::td)]/following-sibling::td[2]//text()[" . $this->starts($this->t("Carriage")) . "]");

        $cabin = $this->http->FindNodes("//td[(" . $this->contains($this->t("Adult")) . ") and not(descendant::td)]/following-sibling::td[2]//text()[./following::text()[normalize-space()][1][" . $this->starts($this->t("Carriage")) . "]]");

        $total = $this->http->FindSingleNode("//span[" . $this->contains($this->t("Total")) . "]/following-sibling::span[1]");

        if (preg_match('/([A-Z]{3})\s*([\d\.]+)/', $total, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = $m[2];
        }

        if ($tripNumber !== $it['RecordLocator']) {
            $it['TripNumber'] = $tripNumber;
        }

        // AccountNumbers
        $accountNumber = $this->http->FindSingleNode("//td[(" . $this->contains($this->t("Account:")) . ") and not(descendant::td)]/descendant::text()[normalize-space(.)][1]", null, true, '/' . $this->preg_implode($this->t("Account:")) . '[_\s]*(\w*\d{5,}\w*)/');

        if ($accountNumber) {
            $it['AccountNumbers'] = [$accountNumber];
        }

        $xpath = "//img[contains(@src, 'ctrip.com/trains_v2/train-line')]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($segments as $i => $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            // Type 1 examples: it-10175408.eml
            // Type 2 examples: it-23574093.eml
            $segType = $this->http->FindSingleNode("./descendant::img[contains(@src, 'ctrip.com/trains_v2/train-line')]/following::td[normalize-space(.)][1]", $root, true, '/^\d{1,2}:\d{2}/') !== null ? 1 : 2;
            $this->logger->debug("segment-$i: Type $segType");

            $xpathFragmentBeforeRow1 = './preceding::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.) and not(contains(.,"|"))]';

            $date = $segType === 1 ? $this->getNode($root) : $this->http->FindSingleNode($xpathFragmentBeforeRow1 . '[2]', $root);

            $train = $segType === 1 ? $this->getNode($root, 2) : $this->http->FindSingleNode($xpathFragmentBeforeRow1 . '[1]', $root);

            if (preg_match('/([A-Z]{1,2}\s*\d+)/', $train, $m)) {
                $seg['FlightNumber'] = $m[1];
            }

            $timeDep = $segType === 1 ? $this->getNode($root, 4) : $this->getNode($root);
            $seg['DepDate'] = $this->normalizeDate($date . ', ' . $timeDep);

            $seg['DepName'] = $segType === 1 ? $this->getNode($root, 5) : $this->getNode($root, 3);

            $seg['Duration'] = $segType === 1 ? $this->getNode($root, 6, '/\d+h\d+m/') : $this->http->FindSingleNode('./following::tr[normalize-space(.)][1]/td[2]', $root, true, '/\d+h\d+m/');

            $xpathFragmentAfterRow1 = './following::tr[contains(normalize-space(.),":")][1]';

            // ArrDate
            $timeArr = $this->http->FindSingleNode($xpathFragmentAfterRow1 . '/td[1]', $root);

            if (preg_match('/^(' . $patterns['time'] . ')(?:\s*[+]\s*(\d{1,3})|$)/', $timeArr, $matches)) {
                // 09:30 +1
                $seg['ArrDate'] = $this->normalizeDate($date . ', ' . $matches[1]);

                if (!empty($matches[2]) && !empty($seg['ArrDate'])) {
                    $seg['ArrDate'] = strtotime("+{$matches[2]} days", $seg['ArrDate']);
                }
            } else {
                // 09:30
                $seg['ArrDate'] = $this->normalizeDate($date . ', ' . $timeArr);
            }

            // ArrName
            $seg['ArrName'] = $this->http->FindSingleNode($xpathFragmentAfterRow1 . '/td[2]', $root);

            // Seats
            if (count($seats)) {
                $seg['Seats'] = $seats;
            } elseif ($segType === 2) {
                $seg['Seats'] = [$this->http->FindSingleNode($xpathFragmentBeforeRow1 . '[4]', $root)];
            }

            // Cabin
            if (count($cabin)) {
                $seg['Cabin'] = implode(', ', array_unique($cabin));
            } elseif ($segType === 2) {
                $seg['Cabin'] = $this->http->FindSingleNode($xpathFragmentBeforeRow1 . '[3]', $root);
            }

            // DepCode
            // ArrCode
            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        $it['Passengers'] = $this->http->FindNodes("//tr[not(.//tr)]/descendant::text()[" . $this->eq($this->t("Adult")) . "]/preceding::text()[normalize-space(.)][1]");

        return [$it];
    }

    private function getNode(\DOMNode $root, $td = 1, $re = null)
    {
        return $this->http->FindSingleNode('descendant::td[' . $td . ']', $root, true, $re);
    }

    private function assignLang(): bool
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
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return preg_quote($s); }, $field));
    }

    private function normalizeDate($str)
    {
        $in = [
            "/^([^\s\d]+),\s+(\d+)\s+([^\s\d]+),\s+(\d+:\d+)$/", // Sab, 3 Jun, 13:14
            "/^(\d{1,2})-(\d{1,2})-(\d{2,4}),\s+(\d{1,2}:\d{2})$/", // 14-04-2017, 10:02
        ];
        $out = [
            "$1, $4, $2 $3",
            "$2/$1/$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/^\d{1,2}\/\d{1,2}\/\d{2,4}, \d{1,2}:\d{2}$/", $str)) {
            return strtotime($str);
        } elseif (preg_match("/\d+\s+([^\d\s]+)\s+\d{4}/", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);

                return strtotime($str);
            }
        } elseif (preg_match("/\s*([^,]+),\s+(\d+:\d+),\s+(\d+)\s+([^\d\s]+)\s*$/", $str, $m)) {
            if (!($en = \AwardWallet\Engine\MonthTranslate::translate($m[4], $this->lang))) {
                $en = $m[4];
            }
            $dayOfWeekInt = \AwardWallet\Engine\WeekTranslate::number1(trim($m[1]), $this->lang);
            $m[2] .= ' ' . date('Y', $this->date);
            $str = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($m[2] . ', ' . $m[3] . ' ' . $en, $dayOfWeekInt);

            return $str;
        }

        return $str;
    }
}
