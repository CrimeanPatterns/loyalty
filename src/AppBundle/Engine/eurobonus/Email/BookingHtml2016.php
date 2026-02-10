<?php

namespace AwardWallet\Engine\eurobonus\Email;

class BookingHtml2016 extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "eurobonus/it-4930797.eml, eurobonus/it-4931771.eml, eurobonus/it-5988095.eml, eurobonus/it-6135976.eml, eurobonus/it-8666720.eml";

    private $lang = '';

    private $dict = [
        'en' => [
            'Booking reference' => ['Booking reference', 'Booking reference:'],
            'Stopping'          => ['Stopping', 'Layover'],
            'Operator'          => 'Operated by',
            'FirstName'         => 'First and middle name',
            'Total'             => 'TOTAL',
            //            'Points'             => '',
        ],
        'sv' => [
            'Booking reference'  => 'Bokningsreferens',
            'Departure'          => ['Avgång', 'Från', 'Departure'],
            'Arrival'            => ['Ankomst', 'Till'],
            'Stopping'           => ['Anslutningsstopp', 'Layover'],
            'Operator'           => 'Trafikeras av',
            'FirstName'          => 'mellannamn',
            'Last name'          => 'Efternamn',
            'Class'              => 'Bokningsklass',
            'Total'              => 'TOTALSUMMA',
            'Points'             => 'Poäng',
            'Ticket number(s)'   => 'Biljettnummer',
            'EuroBonus nr.'      => 'EuroBonus-nummer',
        ],
        'da' => [
            'Booking reference' => ['Reservationsnummer', 'Bookingreference'],
            'Departure'         => 'Afgang',
            'Arrival'           => ['Arrival', 'Ankomst'],
            'Stopping'          => 'Ophold',
            'Operator'          => ['Opereret af', 'Drives af'],
            'FirstName'         => 'og mellemnavn',
            'Last name'         => 'Efternavn',
            'Class'             => 'Reserveringsklasse',
            'Total'             => 'I ALT',
            //            'Points'             => '',
            'Ticket number(s)'  => ['Billetnummer(-numre)', 'Billetnummer'],
            'EuroBonus nr.'     => 'EuroBonus-nr.',
        ],
        'no' => [
            'Booking reference' => 'Bestillingsreferanse',
            'Departure'         => ['Avgang', 'Fra'],
            'Arrival'           => ['Arrival', 'Ankomst', 'Til'],
            'Stopping'          => ['Mellomlanding', 'Tid mellom fly'],
            'Operator'          => 'Drevet av',
            'FirstName'         => 'og mellomnavn',
            'Last name'         => 'Etternavn',
            'Class'             => 'Bestillingsklasse',
            'Total'             => 'TOTALT',
            //            'Points'             => '',
            'Ticket number(s)'  => ['Billettnummer(-numre)', 'Billettnummer'],
            'EuroBonus nr.'     => 'EuroBonusnr.',
        ],
        'de' => [
            'Booking reference' => 'Buchungsreferenz',
            'Departure'         => ['Abflug'],
            'Arrival'           => ['Ankunft'],
            'Stopping'          => 'Zwischenstopp',
            'Operator'          => 'Durchgeführt von',
            'FirstName'         => 'und Zweitname(n)',
            'Last name'         => 'Nachname',
            'Class'             => 'Buchungsklasse',
            'Total'             => 'GESAMT',
            //            'Points'             => '',
            'Ticket number(s)'  => ['Ticketnummer(n)', 'E-Ticket-Nummer(n)'],
            'EuroBonus nr.'     => 'EuroBonus-Nr.',
        ],
    ];

    private static $detectBody = [
        'sv' => [
            'du måste ange den om du kontaktar SAS',
            'Din bokning är klar. Trevlig resa',
        ],
        'da' => 'du skal oplyse det, hvis du kontakter SAS',
        'no' => [
            'du må oppgi denne hvis du kontakter SAS',
            'Ta vare på bestillingsreferansen da du trenger den ved kontakt med SAS',
        ],
        'en' => [
            'Save your booking reference, you\'ll need to mention it if you contact SAS',
            'Save your booking reference, you will need to mention it if you contact SAS',
            'At time of travel with SAS you can print and scan the barcode',
        ],
        'de' => [
            'Speichern Sie Ihre Buchungsreferenz. Sie benötigen diese, wenn Sie SAS kontaktieren wollen.',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => 'BookingHtml2016' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'no-reply@flysas.com') !== false
            || stripos($headers['subject'], 'Your SAS Flight') !== false
            || stripos($headers['subject'], 'Din SAS-flyvning') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysas.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode('(//*[' . $this->starts($this->t('Booking reference')) . ']/following-sibling::*[normalize-space(.)!=""][1])[1]', null, false, '/[A-Z\d]{5,7}/');

        $xpathDep = '//text()[' . $this->containsXpath($this->t('Departure')) . ']/ancestor::tr[1][contains(.,\'Scandinavian Airlines\') or contains(., \'Lufthansa\') or contains(., \'Widerøe\') or ' . $this->containsXpath($this->t('Operator')) . ' or contains(translate(normalize-space(.), "0123456789", "++++++++++"), "+:++")]';

        if ($this->http->XPath->query($xpathDep)->length === 0) {
            $xpathDep = '//text()[' . $this->containsXpath($this->t('Departure')) . ']/ancestor::table[contains(.,\'Scandinavian Airlines\') or contains(., \'Lufthansa\') or contains(., \'Widerøe\') or ' . $this->containsXpath($this->t('Operator')) . ' or contains(translate(normalize-space(.), "0123456789", "++++++++++"), "+:++")][1]';
        }

        $departureRows = $this->http->XPath->query($xpathDep);

        if ($departureRows->length === 0) {
            $this->logger->info('Segments not found by: ' . $xpathDep);

            return false;
        }

        foreach ($departureRows as $departureRow) {
            $xpathArr = './following-sibling::tr[.//text()[' . $this->containsXpath($this->t('Arrival')) . ' or ' . $this->containsXpath($this->t('Stopping')) . ']][1]';

            if ($this->http->XPath->query($xpathArr, $departureRow)->length === 0) {
                $xpathArr = './following-sibling::*[name() = \'table\' or name() = \'div\'][' . $this->containsXpath($this->t('Arrival')) . ' or ' . $this->containsXpath($this->t('Stopping')) . '][1]';
            }

            $arriveRows = $this->http->XPath->query($xpathArr, $departureRow);

            if ($arriveRows->length === 0) {
                $this->logger->info('Invalid segment by 1: ' . $xpathDep . '/' . $xpathArr);

                return false;
            }
            $it['TripSegments'][] = $this->parseSegment($departureRow, $arriveRows->item(0));
            //			$this->logger->info($arriveRows->item(0)->nodeValue);
        }

        //dop segments. if depSegment = stopsegment
        $xpathDopSeg = '//text()[' . $this->containsXpath($this->t('Stopping')) . ']/ancestor::*[name() = \'tr\' or name() = \'div\']/following-sibling::*[name() = \'tr\' or name() = \'div\' or name() = \'table\'][normalize-space(.)][1][' . $this->starts($this->t('Arrival')) . '][not(' . $this->contains($this->t('Last name')) . ')]';
        $arriveRows = $this->http->XPath->query($xpathDopSeg);

        foreach ($arriveRows as $arriveRow) {
            $xpathDep = './preceding-sibling::*[name() = \'tr\' or name() = \'div\'][normalize-space(.)][1][.//text()[' . $this->containsXpath($this->t('Stopping')) . ']]';
            $departureRows = $this->http->XPath->query($xpathDep, $arriveRow);

            if ($departureRows->length === 0) {
                $this->logger->info('Invalid segment by 2: ' . $xpathDep . '/' . $xpathDopSeg);

                return false;
            }
            $it['TripSegments'][] = $this->parseSegment($departureRows->item(0), $arriveRow, true);
        }

        $it['Passengers'] = $this->parsePassengers();

        $it['AccountNumbers'] = array_filter($this->http->FindNodes('//text()[' . $this->starts($this->t('EuroBonus nr.')) . ' or starts-with(normalize-space(.), "EuroBonus nr.")]/following::text()[normalize-space(.)][1]', null, '/^\s*([A-Z]{0,3}\d{5,})\s*$/'));

        $it['TicketNumbers'] = array_map('trim', explode(",", $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Ticket number(s)')) . ']', null, true, "#" . $this->preg_implode($this->t('Ticket number(s)')) . "[\s:]*([\d ,\-]+)#")));

        if (empty($it['TicketNumbers'][0])) {
            $it['TicketNumbers'] = array_map('trim', explode(",", $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Ticket number(s)')) . ']/ancestor::tr[1]', null, true, "#" . $this->preg_implode($this->t('Ticket number(s)')) . "[\s:]*([\d ,\-]{10,})#")));
        }

        $payment = $this->http->FindSingleNode('//text()[contains(.,"' . $this->t('Total') . '")]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=""][1]/td[normalize-space(.)!=""][1]',
            null, true, "/.*\d.*/");

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Total')) . ']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=""][1]/td[normalize-space(.)!=""][last() - 1]',
                null, true, "/.*\d.*(?:[A-Z]{3}|" . $this->preg_implode($this->t("Points")) . ")\s*$/");
        }

        if (preg_match('/^\s*([,.\d\s]+)\s+([A-Z]{3})\s*$/', $payment, $matches)) {
            $it['TotalCharge'] = $this->priceNormalize($matches[1]);
            $it['Currency'] = $matches[2];
        }

        if (preg_match("/^\s*([,.\d\s]+\s+" . $this->preg_implode($this->t("Points")) . ")\s*$/", $payment, $matches)) {
            $it['SpentAwards'] = $matches[1];
        }

        return [$it];
    }

    private function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function t($str)
    {
        if (!isset($this->dict[$this->lang]) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }

    private function containsXpath($str, $haystack = 'normalize-space(.)')
    {
        $res = null;

        if (empty($str) || (is_array($str) && count($str) === 0)) {
            return false;
        }

        if (is_array($str)) {
            $r = array_map(function ($value) use ($haystack) {
                return "contains({$haystack}, '{$value}')";
            }, $str);
            $res = implode(' or ', $r);
        } elseif (is_string($str)) {
            $res = "contains(" . $haystack . ", '" . $str . "')";
        }

        return $res;
    }

    private function containsRE($str)
    {
        if (is_array($str)) {
            return '(?:' . implode('|', $str) . ')';
        }

        return $str;
    }

    private function parseSegment($rootDep, $rootArr, $dopSeg = false)
    {
        $segment = [];

        $xpaths = [
            'nameAndCode' => '(descendant::tr[count(td)>=3]/td[1]/descendant-or-self::*[name() = "div" or name() = "tr"][2] | ./td[1]/*[normalize-space(.)!=""][2])',
            'terminal'    => '(descendant::tr[count(td)>=3]/td[1]/descendant-or-self::*[name() = "div" or name() = "tr"][position()>2 and contains(.,"Terminal")][1] | ./td[1]/*[position()>2 and contains(.,"Terminal")][1])',
            'date'        => '(descendant::tr[count(td)>=3]/td[2]/descendant-or-self::*[name() = "div" or name() = "tr"][string-length(normalize-space(.))>7 and not(contains(.,":"))][1] | ./td[2]/*[string-length(normalize-space(.))>7 and not(contains(.,":"))][1])',
            'time'        => '(descendant::tr[count(td)>=3]/td[2]/descendant-or-self::*[name() = "div" or name() = "tr"][position()>1 and contains(.,":") and string-length(normalize-space(.))>3][1] | ./td[2]/*[position()>1 and contains(.,":") and string-length(normalize-space(.))>3][1])',
        ];

        $patterns = [
            'nameAndCode' => '/^(.+)([A-Z]{3})\s*$/',
            'terminal'    => '/[Tt]erminal\s*([A-Z\d]{1,2})/',
            'date'        => '/\d{1,2}\s+[^\d]{3,}\s+\d{2,4}/',
            'time'        => $dopSeg ? '/\d{1,2}:\d{2}\s*$/' : '/\d{1,2}:\d{2}/',
        ];

        // Departure
        $depNameAndCode = $this->http->FindSingleNode($xpaths['nameAndCode'], $rootDep);

        if (preg_match($patterns['nameAndCode'], $depNameAndCode, $matches)) {
            $segment['DepName'] = trim($matches[1]);
            $segment['DepCode'] = $matches[2];
        }
        $depTerminal = $this->http->FindSingleNode($xpaths['terminal'], $rootDep);

        if (preg_match($patterns['terminal'], $depTerminal, $matches)) {
            $segment['DepartureTerminal'] = $matches[1];
        }
        $depDate = $this->http->FindSingleNode($xpaths['date'], $rootDep, true, $patterns['date']);
        $depTime = $this->http->FindSingleNode($xpaths['time'], $rootDep, true, $patterns['time']);

        if ($depDate && $depTime) {
            $depDate = strtotime($this->dateStringToEnglish($depDate));
            $segment['DepDate'] = strtotime($depTime, $depDate);
        }

        if ($duration = $this->http->FindSingleNode('descendant-or-self::tr[count(td)>=3]/td[2]/descendant::*[name() = "div" or name() = "tr"][normalize-space()][last()]', $dopSeg ? $rootArr : $rootDep, true, '/\d{1,2}h[\s\.]?\d{1,2}m/')) {
            $segment['Duration'] = $duration;
        }
        $flight = $this->http->FindSingleNode('(descendant::tr[count(td)>=3]/td[3]/descendant-or-self::*[name() = "div" or name() = "tr"][string-length(normalize-space(.))>2][1] | ./td[3]/*[string-length(normalize-space(.))>2][1])', $dopSeg ? $rootArr : $rootDep);

        if (preg_match('/^\s*([A-Z\d]{2})\s*(\d+)\s*$/', $flight, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        if ($operator = $this->http->FindSingleNode('(descendant::tr[count(td)>=3]/td[3]/descendant-or-self::*[name() = "div" or name() = "tr"][position()>1 and (' . $this->contains($this->t('Operator')) . ')][1] | ./td[3]/*[position()>1 and (' . $this->contains($this->t('Operator')) . ')][1])', $dopSeg ? $rootArr : $rootDep, true, '/^\s*' . $this->containsRE($this->t('Operator')) . '\s+(.+)/')) {
            $segment['Operator'] = $operator;
        }

        if ($aircraft = $this->http->FindSingleNode('(descendant::tr[count(td)>=3]/td[3]/descendant-or-self::*[name() = "div" or name() = "tr"][position()>2 and ./preceding-sibling::*[' . $this->containsXpath($this->t('Operator')) . '] and descendant-or-self::*[name() = "div" or name() = "tr"][' . $this->containsXpath($this->t('Class')) . ']][1] | ./td[3]/*[position()>2 and ./preceding-sibling::*[' . $this->containsXpath($this->t('Operator')) . '] and ./following-sibling::*[' . $this->containsXpath($this->t('Class')) . ']][1])', $dopSeg ? $rootArr : $rootDep)) {
            $segment['Aircraft'] = $aircraft;
        } elseif ($aircraft = $this->http->FindSingleNode('descendant::tr[count(td)>=3]/td[3]/descendant-or-self::*[name() = "div" or name() = "tr"][position()>2 and ' . $this->contains(['Airbus', 'Boeing']) . '][1] |./td[3]/*[position()>2 and ' . $this->contains(['Airbus', 'Boeing']) . '][1]', $dopSeg ? $rootArr : $rootDep)) {
            $segment['Aircraft'] = $aircraft;
        }

        if ($bookingClass = $this->http->FindSingleNode('(descendant::tr[count(td)>=3]/td[3]/descendant-or-self::*[name() = "div" or name() = "tr"][position()>1 and ' . $this->containsXpath($this->t('Class')) . '][1] | ./td[3]/*[position()>1 and ' . $this->containsXpath($this->t('Class')) . '][1])', $dopSeg ? $rootArr : $rootDep, true, '/^\s*' . $this->t('Class') . '\s+([A-Z]+)/')) {
            $segment['BookingClass'] = $bookingClass;
        }

        if ($seats = $this->http->FindSingleNode('(descendant::tr[count(td)>=3]/td[4]//td[.//img[contains(@src,"seat")] and not(.//td)][1] | ./td[4]//td[.//img[contains(@src,"seat")] and not(.//td)][1])', $dopSeg ? $rootArr : $rootDep, true, '/^\s*(\d{1,3}[A-Z](\s*,\s*\d{1,3}[A-Z])*)(\s+\w|$)/')) {
            $segment['Seats'] = array_map('trim', explode(', ', $seats));
        }

        // Arrival
        $arrNameAndCode = $this->http->FindSingleNode($xpaths['nameAndCode'], $rootArr);

        if (preg_match($patterns['nameAndCode'], $arrNameAndCode, $matches)) {
            $segment['ArrName'] = trim($matches[1]);
            $segment['ArrCode'] = $matches[2];
        }
        $arrTerminal = $this->http->FindSingleNode($xpaths['terminal'], $rootArr);

        if (preg_match($patterns['terminal'], $arrTerminal, $matches)) {
            $segment['ArrivalTerminal'] = $matches[1];
        }
        $arrDate = $this->http->FindSingleNode($xpaths['date'], $rootArr, true, $patterns['date']);
        $arrTime = $this->http->FindSingleNode($xpaths['time'], $rootArr, true, $patterns['time']);

        if ($arrDate && $arrTime) {
            $arrDate = strtotime($this->dateStringToEnglish($arrDate));
            $segment['ArrDate'] = strtotime($arrTime, $arrDate);
        }

        return $segment;
    }

    private function parsePassengers()
    {
        $passengers = [];
        $xpath = '//text()[contains(.,"' . $this->t('FirstName') . '")]/ancestor::tr[.//text()[contains(.,"' . $this->t('Last name') . '")] and not(.//tr)]';
        $passengerRows = $this->http->XPath->query($xpath);

        if ($passengerRows->length === 0) {
            $this->logger->info("Passengers not found by: {$xpath}");
        }

        foreach ($passengerRows as $passengerRow) {
            $firstName = $this->http->FindSingleNode('(.//*[contains(.,"' . $this->t('FirstName') . '") and name()!="td" and name()!="th"])[1]/following-sibling::*[normalize-space(.)!=""][1]', $passengerRow);
            $lastName = $this->http->FindSingleNode('(.//*[contains(.,"' . $this->t('Last name') . '") and name()!="td" and name()!="th"])[1]/following-sibling::*[normalize-space(.)!=""][1]', $passengerRow);
            $passengers[] = $firstName . ' ' . $lastName;
        }

        if (count($passengers) === 0) {
            $nodes = $this->http->FindNodes('//text()[contains(., "' . $this->t('FirstName') . '")]/ancestor::tr[contains(.,"' . $this->t('Last name') . '") and count(td)>3]/td[string-length(normalize-space(.))>2][position() = 1 or position() = 2]/descendant::tr[2]');

            if (is_array($nodes) && count($nodes) > 0) {
                $passengers = array_map(function ($el) {
                    if (is_array($el)) {
                        return implode(' ', $el);
                    }
                }, array_chunk($nodes, 2));
            }
        }

        return $passengers;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detectBody as $lang => $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            } elseif (is_array($detect)) {
                foreach ($detect as $dt) {
                    if (stripos($body, $dt) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
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

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
