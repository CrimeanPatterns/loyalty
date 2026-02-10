<?php

namespace AwardWallet\Engine\fnt\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "fnt/it-10090288.eml, fnt/it-10105773.eml, fnt/it-10113297.eml, fnt/it-12053820.eml, fnt/it-12259380.eml, fnt/it-156102923.eml, fnt/it-64499833.eml";

    public $reSubject = [
        'en'  => 'Flight Booking',
        'en2' => 'Electronic Ticket',
        'en3' => 'Airfares Booking Confirmation',
    ];

    public $providerCode = '';
    public $lang = '';
    public $total = [];

    public static $dictionary = [
        'en' => [
            'tripNumber'        => ['Your Flight Network Booking ID', 'FlightNetwork® Booking ID', 'Your FlyFar.ca Booking ID'],
            'passengerPrefixes' => ['Adult', 'Child', 'Infant'],
            'routeTitle'        => ['Depart Flight', 'Return Flight', 'Outbound Flight', 'Inbound Flight'],
            'baseFare'          => ['Base Price', 'Fare'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return null;
        }

        $its = $this->parseEmail();
        $result = [
            'providerCode' => $this->providerCode,
            'emailType'    => 'FlightBooking' . ucfirst($this->lang),
            'parsedData'   => ['Itineraries' => $its],
        ];

        if (!empty(array_filter($this->total))) {
            $result['TotalCharge']['Amount'] = $this->total['TotalCharge'];
            $result['TotalCharge']['Currency'] = $this->total['Currency'];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], 'FlightNetwork') === false
            && stripos($headers['subject'], 'Flyfar.ca') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'sales@flightnetwork.com') !== false;
    }

    public static function getEmailProviders()
    {
        return ['fnt', 'flyfar'];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    public function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    public function amount($s)
    {
        $amount = (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));

        if ($amount == 0) {
            $amount = null;
        }

        return $amount;
    }

    private function parseEmail(): ?array
    {
        $its = [];
        //RecordLocator
        $rlDefault = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'Flight Confirmation number is')]/following::text()[normalize-space()][1])[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        //TripNumber
        $bookingIdHtml = $this->http->FindHTMLByXpath("//td[not(.//td) and {$this->contains($this->t('tripNumber'))}]");
        $bookingIdText = $this->htmlToText($bookingIdHtml);
        $TripNumber = preg_match("/^[ ]*{$this->opt($this->t('tripNumber'))}[:\s]+([A-Z\d]{5,})[ ]*$/m", $bookingIdText, $m) ? $m[1] : null;

        //Passengers
        $Passengers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('passengerPrefixes'))} and contains(normalize-space(),':')]/ancestor::td[1]/following::td[1]", null, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*\(|$)/u'));

        //TicketNumbers
        $ticketNumbersDefault = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your electronic ticket(s) number(s)')]/following::text()[normalize-space()][1]", null, true, "#^[\d ,]+$#");

        if (!empty($ticketNumbersDefault)) {
            $ticketNumbersDefault = array_filter(array_map('trim', explode(",", $ticketNumbersDefault)));
        }

        //TotalCharge
        $TotalCharge = $this->amount($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Grand Total') or starts-with(normalize-space(), 'Total Trip cost')]/ancestor::tr[1]", null, true, "#\D(\d[\d,. ]+)(\s+|$)#"));

        //Currency
        $Currency = $this->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Grand Total') or starts-with(normalize-space(), 'Total Trip cost')]/ancestor::tr[1]", null, true, "#(?:Grand Total|Total Trip cost)\s*(.+)#"));

        //TripSegments
        $xpath = "//text()[starts-with(normalize-space(), 'Departs:')]/ancestor::tr[contains(.,'Arrives:')][1]/following::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[contains(normalize-space(), 'Departs from:')]/ancestor::tr[contains(.,'Arrives:')][1]/following::tr[contains( normalize-space(./td[3]), '#')]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($nodes as $root) {
            $seg = [];

            //FlightNumber
            //AirlineName
            //Operator
            $node = $this->http->FindSingleNode(".//td[3]", $root);

            if (preg_match("#(.+)\s*\#\s*(\d{1,5})(?:\s*Operated by:\s(.+))?#", $node, $m)) {
                $seg['FlightNumber'] = $m[2];
                $seg['AirlineName'] = trim($m[1]);

                if (isset($m[3])) {
                    $seg['Operator'] = trim($m[3]);
                }
            }

            $date = strtotime($this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[{$this->starts($this->t('routeTitle'))}][1]", $root, true, "/{$this->opt($this->t('routeTitle'))}[\s:]+(.{6,})$/i"));
            $year = $date ? getdate($date)['year'] : null;

            $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

            /*
                Los Cabos - Los Cabos Intl Airport (SJD)
                Sun Mar 10 3:55pm
            */
            $patterns['nameCodeDate'] = "/^\s*"
                . "(?<name>.+?)[ ]*\(\s*(?<code>[A-Z]{3})\s*\)"
                . "\s*(?<dateTime>.{6,}?)?"
                . "\s*$/";

            // 8:27pm Sat Dec 19
            $patterns['dateTime1'] = "/^(?<time>{$patterns['time']})\s+(?<date>.{6,})$/u";
            // Sun Mar 10 3:55pm
            $patterns['dateTime2'] = "/^(?<date>.{6,}?)\s+(?<time>{$patterns['time']})$/u";

            // Sat Jan 02
            $patterns['wdayMonth'] = "/^(?<wday>[-[:alpha:]]+)[,\s]+(?<date>.{4,})$/u";

            //DepName
            //DepCode
            //DepDate
            $node = $this->http->FindSingleNode(".//td[1]", $root);

            if (preg_match($patterns['nameCodeDate'], $node, $m)) {
                $seg['DepName'] = $m['name'];
                $seg['DepCode'] = $m['code'];

                if ($year && !empty($m['dateTime'])
                    && (preg_match($patterns['dateTime1'], $m['dateTime'], $m2)
                        || preg_match($patterns['dateTime2'], $m['dateTime'], $m2)
                    )
                    && preg_match($patterns['wdayMonth'], $m2['date'], $m3)
                ) {
                    if (!preg_match('/\b\d{4}\s*$/', $m3['date'])) {
                        $m3['date'] .= ' ' . $year;
                    }
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($m3['date'], WeekTranslate::number1($m3['wday']));
                    $seg['DepDate'] = strtotime($m2['time'], $dateDep);
                } elseif (empty($m['dateTime'])) {
                    $seg['DepDate'] = MISSING_DATE;
                }
            }

            //ArrName
            //ArrCode
            //ArrDate
            $node = $this->http->FindSingleNode(".//td[2]", $root);

            if (preg_match($patterns['nameCodeDate'], $node, $m)) {
                $seg['ArrName'] = $m['name'];
                $seg['ArrCode'] = $m['code'];

                if ($year && !empty($m['dateTime'])
                    && (preg_match($patterns['dateTime1'], $m['dateTime'], $m2)
                        || preg_match($patterns['dateTime2'], $m['dateTime'], $m2)
                    )
                    && preg_match($patterns['wdayMonth'], $m2['date'], $m3)
                ) {
                    if (!preg_match('/\b\d{4}\s*$/', $m3['date'])) {
                        $m3['date'] .= ' ' . $year;
                    }
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($m3['date'], WeekTranslate::number1($m3['wday']));
                    $seg['ArrDate'] = strtotime($m2['time'], $dateArr);
                } elseif (empty($m['dateTime'])) {
                    $seg['ArrDate'] = MISSING_DATE;
                }
            }

            //Cabin
            $cabin = explode(" | ", $this->http->FindSingleNode("./following-sibling::tr[1]", $root));
            $cabin = array_pop($cabin);

            if (preg_match("#\S+.+:\s*\S+#", $cabin) == false) {
                $seg['Cabin'] = $cabin;
            }

            //Duration
            $seg['Duration'] = trim($this->http->FindSingleNode("./following-sibling::tr[1]", $root, null, "#Flight\s+Duration\s*:\s*([^|]+)#"));

            //Stops
            if (!empty($seg['AirlineName'])) {
                $RecordLocator = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), '" . $seg['AirlineName'] . "') and contains(normalize-space(), ':')]/following::text()[normalize-space()][1])[1]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#");

                if (empty($RecordLocator)) {
                    $RecordLocator = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your " . $seg['AirlineName'] . " Booking ID')]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#");
                }
            }

            // Seats
            if (!empty($seg['DepCode']) && !empty($seg['ArrCode'])) {
                $col = count($this->http->FindNodes("//text()[normalize-space() = '".$seg['DepCode'] . ' | ' . $seg['ArrCode']."']/ancestor::td/preceding-sibling::td"));
                if (!empty($col)) {
                    $seg['Seats'] = array_filter($this->http->FindNodes("//text()[normalize-space() = '".$seg['DepCode'] . ' | ' . $seg['ArrCode']."']/ancestor::tr/following-sibling::tr/td[".($col + 1)."]", null, "/^\s*(\d{1,3}[A-Z])\s*$/"));
                }
            }


            if (empty($RecordLocator)) {
                if (!empty($rlDefault)) {
                    $RecordLocator = $rlDefault;
                } else {
                    $RecordLocator = CONFNO_UNKNOWN;
                }
            }

            if (!empty($seg['AirlineName'])) {
                $ticketNumbers = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '" . $seg['AirlineName'] . " Electronic ticket(s) number(s)')]/following::text()[normalize-space()][1]", null, true, "#^\s*([\d ,]+)\s*$#");

                if (!empty($ticketNumbers)) {
                    $ticketNumbers = array_filter(array_map('trim', explode(",", $ticketNumbers)));
                } elseif (!empty($ticketNumbersDefault) && empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), '" . $seg['AirlineName'] . " Electronic ticket(s) number(s)')][1]"))) {
                    $ticketNumbers = $ticketNumbersDefault;
                }
            }

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return null;
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (!empty($seg['DepDate']) && $seg['DepDate'] === MISSING_DATE) {
                        foreach ($it['TripSegments'] as $key2 => $value) {
                            if (!empty($seg['AirlineName']) && $seg['AirlineName'] === $value['AirlineName']
                                && !empty($seg['FlightNumber']) && $seg['FlightNumber'] === $value['FlightNumber']
                                && !empty($seg['DepCode']) && $seg['DepCode'] === $value['ArrCode']
                                && !empty($seg['ArrDate']) && $value['ArrDate'] === MISSING_DATE
                            ) {
                                $its[$key]['TripSegments'][$key2]['ArrName'] = $seg['ArrName'];
                                $its[$key]['TripSegments'][$key2]['ArrCode'] = $seg['ArrCode'];
                                $its[$key]['TripSegments'][$key2]['ArrDate'] = $seg['ArrDate'];

                                if (!empty($seg['Cabin'])) {
                                    $its[$key]['TripSegments'][$key2]['Cabin'] = $seg['Cabin'];
                                }
                                $its[$key]['TripSegments'][$key2]['Duration'] = $seg['Duration'];
                                $its[$key]['TripSegments'][$key2]['Stops'] = (isset($its[$key]['TripSegments'][$key2]['Stops'])) ? $its[$key]['TripSegments'][$key2]['Stops'] + 1 : 1;

                                continue 3;
                            }
                        }
                    }
                    $its[$key]['TripSegments'][] = $seg;
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (!empty($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (!empty($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }

                if (!empty($Passengers)) {
                    $it['Passengers'] = $Passengers;
                }

                if (!empty($ticketNumbers)) {
                    $it['TicketNumbers'] = $ticketNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        if (count($its) == 1) {
            if (!empty($TotalCharge)) {
                $its[0]['TotalCharge'] = $TotalCharge;
            }

            if (!empty($Currency)) {
                $its[0]['Currency'] = $Currency;
            }

            //BaseFare
            //Tax
            $xpath = "//tr[ *[3][{$this->starts($this->t('baseFare'))}] and *[4][{$this->starts($this->t('Taxes & Fees'))}] ]/following::tr[ *[5] ]";
            $nodes = $this->http->XPath->query($xpath);
            $BaseFare = 0.0;
            $Tax = 0.0;

            foreach ($nodes as $root) {
                $passNum = $this->http->FindSingleNode("./td[2]", $root, true, "#^\s*(\d{1,2})\s*$#");
                $BaseFare += $passNum * $this->amount($this->http->FindSingleNode("./td[3]", $root, true, "#(\d[\d\., ]+)#"));
                $Tax += $passNum * $this->amount($this->http->FindSingleNode("./td[4]", $root, true, "#(\d[\d\., ]+)#"));
            }

            if (!empty($BaseFare)) {
                $its[0]['BaseFare'] = $BaseFare;
            }

            if (!empty($Tax)) {
                $its[0]['Tax'] = $Tax;
            }
        } elseif (count($its) > 1) {
            if (!empty($TotalCharge)) {
                $this->total['TotalCharge'] = $TotalCharge;
                $this->total['Currency'] = $Currency;
            }
        }

        return $its;
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@flightnetwork.com') !== false
            || stripos($headers['subject'], 'FlightNetwork') !== false
            || $this->http->XPath->query('//a[contains(@href,".flightnetwork.com/") or contains(@href,"www.flightnetwork.com")]')->length > 0
            || $this->http->XPath->query('//text()[contains(normalize-space(),"Thank you for booking with FlightNetwork") or contains(.,"www.flightnetwork.com") or contains(.,"@flightnetwork.com")]')->length > 0
        ) {
            $this->providerCode = 'fnt';

            return true;
        }

        if (stripos($headers['from'], '@flyfar.ca') !== false
            || stripos($headers['subject'], 'Flyfar.ca') !== false
            || $this->http->XPath->query('//a[contains(@href,".flyfar.ca/") or contains(@href,"www.flyfar.ca")]')->length > 0
            || $this->http->XPath->query('//text()[contains(normalize-space(),"please contact Flyfar.ca") or contains(normalize-space(),"Thank you for booking with Flyfar.ca") or contains(.,"www.flyfar.ca")]')->length > 0
        ) {
            $this->providerCode = 'flyfar';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['routeTitle'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['routeTitle'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
