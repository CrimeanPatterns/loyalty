<?php

namespace AwardWallet\Engine\cebu\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class ItineraryReceiptPdf2 extends \TAccountChecker
{
    public $mailFiles = "cebu/it-10408471.eml, cebu/it-13952558.eml";

    public $reFrom = '/[.@]cebupacific[.]/i';
    public $reBody = [
        'en'  => ['Cebu Pacific', 'ITINERARY RECEIPT'],
        'en2' => ['Booking Details', 'BOOKING REFERENCE NUMBER'],
        'es'  => ['cebupacificair', 'Detalles de reserva'],
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Booking Reference' => ['Booking Reference', 'BOOKING REFERENCE NUMBER'],
            'Adult'             => ['Adult', 'Child'],
            'Base Fare'         => ['Base Fare', 'Fare'],
        ],
        'es' => [
            'Booking Reference' => 'Referencia de reserva:',
            'Status'            => 'Estado:',
            'Booking Date'      => 'Fecha de reserva:',
            'Adult'             => 'Adulto',
            'Base Fare'         => 'Tarifa base',
            //			'Amount' => '',
            'Seat' => ['Seat', 'Asiento'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $this->pdf->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($html)));

                    $body = text($this->pdf->Response['body']);
                    $this->assignLang($body);

                    $this->parseEmailPdf($its, $body);
                } else {
                    continue;
                }
            }
        } else {
            return null;
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ItineraryReceiptPdf2' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        return $this->assignLang($text);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->reFrom, $headers['from']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reFrom, $from) > 0;
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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmailPdf(&$its, $textPDF): void
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Booking Reference'))}[\s:]+([A-Z\d]{5,})#", $textPDF);

        $it['Status'] = $this->re("#{$this->opt($this->t('Status'))}[\s:]+(.+)#", $textPDF);

        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Booking Date'))}[\s:]+(.+)#", $textPDF)));

        $it['Passengers'] = $this->pdf->FindNodes("//text()[{$this->contains($this->t('Adult'))}]", null, "#\d+\.?\s+(.+?)\s+\(#");

        $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[{$this->eq($this->t('Base Fare'))}]/ancestor::p[1]/following::p[1]"));
        // Base Fare PHP 10,299.00
        if (count($tot) === 0 && preg_match("/Base Fare\s+([A-Z]{3}\s+[\d.,]+)/", $textPDF, $m)) {
            $tot = $this->getTotalCurrency($m[1]);
        }

        if (count($tot) !== 0) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        //		$tot = $this->getTotalCurrency(implode(" ", $this->pdf->FindNodes("//text()[{$this->eq($this->t('Amount'))}]/ancestor::p[1]/following-sibling::p[1][{$this->starts($this->t('Status'))}]/following-sibling::p[position()<6]")));
        //		// may be more then 1 row
        //		if( empty($tot['Total']) )
        //		    $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.), 'Total Amount')]/following::text()[normalize-space(.)][1]"));
        //		if (!empty($tot['Total'])) {
        //			$it['TotalCharge'] = $tot['Total'];
        //			if (empty($it['Currency'])) {
        //				$it['Currency'] = $tot['Currency'];
        //			}
        //		}
        $nodes = $this->splitter("/^\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+)\s*$/m", $textPDF);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $pattern = "/"
                . "(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d+)\s+(?<nameD>\S[\S\s]+?)\s+(?:Terminal[ ]+(?<termD>[A-Z\d]+?)\s+)?(?<dateD>[^\n]+\d{4},(?:\s*[[:alpha:]]+)?[.\s]*\d{4})(?:[)(HAMP\d\s]+)"
                . "(?<nameA>\S[\S\s]+?)\s+(?:Terminal\s+(?<termA>[A-Z\d]+)\s+)?(?<dateA>[^\n]+\d{4},(?:\s*[[:alpha:]]+)?[.\s]*\d{4})"
                . "/u";

            if (preg_match($pattern, $root, $m)) {
                $seg['AirlineName'] = $m['al'];
                $seg['FlightNumber'] = $m['fn'];

                if (preg_match("#(.+)[\s|]+([A-Z]{3})\s+(.+)#", $m['nameD'], $v)
                    || preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)\s+(.+)#", $m['nameD'], $v)) {
                    $seg['DepCode'] = $v[2];
                    $seg['DepName'] = $v[3] . ', ' . trim($v[1]);
                    $depName = trim($v[1]);
                }

                if (!empty($m['termD'])) {
                    $seg['DepartureTerminal'] = $m['termD'];
                }
                $seg['DepDate'] = strtotime($this->normalizeDate($m['dateD']));

                if (preg_match("#(.+)[\s|]+([A-Z]{3})\s+(.+)#", $m['nameA'], $v)
                    || preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)\s+(.+)#", $m['nameA'], $v)) {
                    $seg['ArrCode'] = $v[2];
                    $seg['ArrName'] = $v[3] . ', ' . trim($v[1]);
                    $arrName = trim($v[1]);
                }

                if (!empty($m['termA'])) {
                    $seg['ArrivalTerminal'] = $m['termA'];
                }
                $seg['ArrDate'] = strtotime($this->normalizeDate($m['dateA']));
            }

            if ((empty($seg['DepDate']) || empty($seg['DepName']) || empty($seg['ArrDate']) || empty($seg['ArrName']))
                && preg_match('/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s+([\s\S]+?)\s+Terminal\s+(\w+).*?\s{2,}([\S\s]+?)\s+Terminal\s+(\w+)\s*/s', $root, $m)
            ) {
                $seg['DepartureTerminal'] = $m[2];
                $seg['ArrivalTerminal'] = $m[4];

                foreach ([
                    'Dep' => $m[1],
                    'Arr' => $m[3],
                ] as $key => $info) {
                    if (preg_match('/(.+\s*\(\d+:\d+\w+\))\s+(.+)/', $info, $m)) {
                        $seg[$key . 'Date'] = strtotime($this->normalizeDate($m[1]));
                        $seg[$key . 'Name'] = $m[2];
                        $seg[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                    }
                }
            }

            if (!empty($depName) && !empty($arrName) && (
                    preg_match_all("#" . str_replace(' ', '\s*', preg_quote($depName)) . "\s*-\s*" . str_replace(' ', '\s*', preg_quote($arrName)) . "\s+" . $this->opt($this->t('Seat')) . "\s*(\d{1,3}[A-Z])\b#i", $textPDF, $m)
                    || preg_match_all("#" . str_replace(' ', '\s*', preg_quote($depName)) . "\s*-\s+" . $this->opt($this->t('Seat')) . "\s*" . str_replace(' ', '\s*', preg_quote($arrName)) . "\s*(\d{1,3}[A-Z])\b#i", $textPDF, $m))) {
                $seg['Seats'] = $m[1];
            }

            $foundIt = false;

            foreach ($its as $key => $it2) {
                if (isset($it['RecordLocator']) && $it2['RecordLocator'] == $it['RecordLocator']) {
                    if (isset($it['Passengers'])) {
                        $its[$key]['Passengers'] = array_unique(array_merge($its[$key]['Passengers'], $it['Passengers']));
                    }
                    $foundSeg = false;

                    foreach ($it2['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $foundSeg = true;
                        }
                    }

                    if ($foundSeg == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $foundIt = true;

                    break;
                }
            }

            if ($foundIt == false) {
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    private function normalizeDate($date)
    {
        //$this->logger->info("DATE: {$date}");
        $in = [
            //Sun. 14 Jan. 2018, 1725H (05:25PM)
            '#^\s*\S+\s+(\d+)\s+(\w+)\.?\s+(\d{4})\s*,(?:\s*\D{2,4})?\s+.+?H\s+\((.+?)\)\s*$#',
            //03 May 2019, Fri   1950 H (07:50 PM)
            '#^\s*(\d+)\s+(\w+)\.?\s+(\d{4})\s*,(?:\s*\D{2,4})?\s+.+?H\s+\((.+?)\)\s*$#',
            //Sun. 14 Jan. 2018
            '#^\s*\S+\s+(\d+)\s+(\w+)\.?\s+(\d{4})\s*$#',
            //vie. 06 de abril de 2018, 1915 H (07:15 p.m.)
            '#^\s*\S+\s+(\d+)\s+de\s+(\w+)\.?\s+de\s+(\d{4})\s*,\s+.+?[H|h]\s+\((.+?)\)\s*$#us',
            //jue. 22 de marzo de 2018
            '#^\s*\S+\s+(\d+)\s+de\s+(\w+)\.?\s+de\s+(\d{4})\s*$#su',
            '#([\.\n])#',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $3 $4',
            '$1 $2 $3',
            '$1 $2 $3 $4',
            '$1 $2 $3',
            '',
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

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        //$this->logger->debug($node);
        $node = str_replace("€", "EUR", $node);
        //$node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#(?<c>-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];

            return ['Total' => $tot, 'Currency' => $cur];
        }

        return [];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
