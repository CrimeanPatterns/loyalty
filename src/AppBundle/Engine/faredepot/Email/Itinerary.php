<?php

namespace AwardWallet\Engine\faredepot\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "faredepot/it-10115693.eml, faredepot/it-10115773.eml, faredepot/it-10152824.eml";

    public $reFrom = "@faredepot.com";
    public $reBody = [
        'en' => ['Online Itinerary', 'Flight Details'],
    ];
    public $reSubject = [
        '#FareDepot(?:\.com)?[\s\-]+Your\s+Flight\s+Reservation\s+Is\s+Being\s+Processed#i',
        '#FareDepot(?:\.com)?[\s\-]+Here\s+is\s+your\s+Flight\s+Information/e-Ticket#i',
        '#FareDepot(?:\.com)?[\s\-]+Your\s+Flight\s+Reservation\s+is\s+E-Tickets\s+Confirmed#i',
        '#FareDepot(?:\.com)?[\s\-]+Your\s+Flight\s+Reservation\s+is\s+Booking\s+Received#i',
    ];

    public $lang = '';
    public $date;

    public static $dict = [
        'en' => [
            'Departure'             => ['Departure', 'DEPARTURE', 'Return', 'RETURN'],
            'SEAT DETAILS'          => ['SEAT DETAILS', 'Seat Details'],
            'AIR FARE DETAILS'      => ['AIR FARE DETAILS', 'Air fare Details'],
            'TOTAL'                 => ['TOTAL', 'Total'],
            'YOUR BOOKING TOTAL IS' => ['YOUR BOOKING TOTAL IS', 'Your booking total is'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('YOUR BOOKING TOTAL IS'))}]", null, true, "#{$this->opt($this->t('YOUR BOOKING TOTAL IS'))}[\s:]+(.+)#"));

        if (!empty($tot['Total'])) {
            $result['parsedData']['TotalCharge'] = [
                'Amount'   => $tot['Total'],
                'Currency' => $tot['Currency'],
            ];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'faredepot.com')]")->length > 0) {
            return $this->AssignLang();
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            $NBSP = chr(194) . chr(160);
            $subject = str_replace($NBSP, ' ', $headers["subject"]);

            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $its = [];
        $airs = [];
        $tripNum = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Agency Confirmation'))}]", null, true, "#{$this->opt($this->t('Agency Confirmation'))}[\s:\#]+([A-Z\d\-]{5,})#");
        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('SEAT DETAILS'))}]/ancestor::tr[contains(.,'#')][1]/ancestor::table[1]/descendant::tr[position()>1]/descendant::*[1]");

        if (empty($pax)) {
            $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()>1]/descendant::*[1][string-length(normalize-space(.))>2]");
        }

        $xpath = "//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[contains(.,'|')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space(.)!='' and not ({$this->eq($this->t('Departure'))})][1]", $root, true, "#^\s*([A-Z\d]{5,})\s*$#");
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['Passengers'] = $pax;

            foreach ($nodes as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];
                $node = implode("\n", $this->http->FindNodes("./descendant::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]/descendant::td[contains(.,':')][1]//text()", $root));

                if (preg_match("#(\d+:\d+(?:\s*[ap]m)?)\s+(\w+\s+\d+)[\s\-]+(.+)#i", $node, $m)) {
                    $depDate = EmailDateHelper::parseDateRelative($this->normalizeDate($this->nice($m[2])), $this->date, true, EmailDateHelper::FORMAT_SPACE_DATE_YEAR);
                    $seg['DepDate'] = strtotime($this->nice($m[1]), $depDate);
                    $seg['DepName'] = $m[3];
                }
                $node = implode("\n", $this->http->FindNodes("./descendant::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]/descendant::td[contains(.,':')][2]//text()", $root));

                if (preg_match("#(\d+:\d+(?:\s*[ap]m)?)\s+(\w+\s+\d+)[\s\-]+(.+)#i", $node, $m)) {
                    $arrDate = EmailDateHelper::parseDateRelative($this->normalizeDate($this->nice($m[2])), $this->date, true, EmailDateHelper::FORMAT_SPACE_DATE_YEAR);
                    $seg['ArrDate'] = strtotime($this->nice($m[1]), $arrDate);
                    $seg['ArrName'] = $m[3];
                }
                $airline = $this->http->FindSingleNode("./descendant::td[1]/following-sibling::td[string-length(normalize-space(.))>2][2]//img/@src", $root, true, "#\/([A-Z\d]{2})\.gif#");
                $node = implode("\n", $this->http->FindNodes("./descendant::td[1]/following-sibling::td[string-length(normalize-space(.))>2][2]//text()[normalize-space(.)!='|' and normalize-space(.)!='']", $root));

                if (preg_match("#(.+)\s+\#(\d+)\s+{$this->opt($this->t('Terminal'))}\s+([^\n]+)\s+{$this->opt($this->t('Terminal'))}\s+([^\n]+)#is", $node, $m)) {
                    $seg['AirlineName'] = !empty($airline) ? $airline : $this->nice($m[1]);
                    $seg['FlightNumber'] = $m[2];
                    $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SEAT DETAILS'))}]/ancestor::tr[contains(.,'#')][1]/descendant::*[self::td or self::th][{$this->contains($this->t('Flight'))} and contains(translate(normalize-space(.),' ',''),'#{$m[2]}') and substring-after(translate(normalize-space(.),' ',''),'#{$m[2]}')='']");

                    if (preg_match("#([A-Z]{3})[\s\-]+([A-Z]{3})#", $node, $v)) {
                        $seg['DepCode'] = $v[1];
                        $seg['ArrCode'] = $v[2];
                    }
                    $cnt = count($this->http->FindNodes("//text()[{$this->eq($this->t('SEAT DETAILS'))}]/ancestor::tr[contains(.,'#')][1]/descendant::*[self::td or self::th][{$this->contains($this->t('Flight'))} and contains(translate(normalize-space(.),' ',''),'#{$m[2]}') and substring-after(translate(normalize-space(.),' ',''),'#{$m[2]}')='']/preceding-sibling::*"));

                    if ($cnt > 0) {
                        $seg['Seats'] = $this->http->FindNodes("//text()[{$this->eq($this->t('SEAT DETAILS'))}]/ancestor::tr[contains(.,'#')][1]/ancestor::table[1]/descendant::tr[position()>1]/descendant::*[1]/following-sibling::td[{$cnt}]");
                    }

                    if (!empty(trim($m[3], "-"))) {
                        $seg['DepartureTerminal'] = $m[3];
                    }

                    if (!empty(trim($m[4], "-"))) {
                        $seg['ArrivalTerminal'] = $m[4];
                    }
                }
                $seg['Cabin'] = $this->http->FindSingleNode("./descendant::td[1]/following-sibling::td[string-length(normalize-space(.))>2][3]", $root);

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        if (count($its) == 1) {
            if (count($its[0]['Passengers']) == 1) {
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()>1]/descendant::*[1]/following-sibling::*[string-length(normalize-space(.))>2][1]"));

                if (!empty($tot['Total'])) {
                    $its[0]['BaseFare'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()>1]/descendant::*[1]/following-sibling::*[string-length(normalize-space(.))>2][2]"));

                if (!empty($tot['Total'])) {
                    $its[0]['Tax'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()>1]/descendant::*[1]/following-sibling::*[string-length(normalize-space(.))>2][3]"));

                if (!empty($tot['Total'])) {
                    $its[0]['TotalCharge'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
            } else {
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()=last()]/descendant::*[1]/following-sibling::*[string-length(normalize-space(.))>2][2]"));

                if (!empty($tot['Total'])) {
                    $its[0]['BaseFare'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }

                if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()=last()]/descendant::*[1]/following-sibling::*[string-length(normalize-space(.))>2][4]"))) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()=last()]/descendant::*[1]/following-sibling::*[string-length(normalize-space(.))>2][3]"));

                    if (!empty($tot['Total'])) {
                        $its[0]['Tax'] = $tot['Total'];
                        $its[0]['Currency'] = $tot['Currency'];
                    }
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE DETAILS'))}]/ancestor::tr[{$this->contains($this->t('TOTAL'))}][1]/ancestor::table[1]/descendant::tr[position()=last()]/descendant::*[1]/following-sibling::*[string-length(normalize-space(.))>2][last()]"));

                if (!empty($tot['Total'])) {
                    $its[0]['TotalCharge'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
            }
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\w+)\s+(\d+)\s*$#i',
        ];
        $out = [
            '$2 $1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return preg_replace("#\s+#", ' ', $str);
    }
}
