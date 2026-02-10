<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Engine\MonthTranslate;

class It5045542 extends \TAccountChecker
{
    public $mailFiles = "airasia/it-5045542.eml, airasia/it-12664497.eml, airasia/it-12634036.eml";

    public $reSubject = [
        'en' => ['Your flight is Now Change Flight Number', 'Your flight is Now Reschedule Later'],
    ];

    public $langDetectors = [
        'en' => ['We regret to inform you that flight'],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = '';

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'code' => '[A-Z]{3}',
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re('/^([A-Z\d]{5,})$/', $this->nextText('Your booking number:'));

        // Passengers
        $passenger = $this->http->FindSingleNode('//text()[normalize-space(.)="NEW FLIGHT TIME"]/preceding::*[starts-with(normalize-space(.),"Dear ") and contains(.,",")][1]', null, true, '/^Dear\s+([A-z][-.\'A-z\s]*[.A-z])\s*,/');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        // $xpath = "//*[normalize-space(text())='Depart']/ancestor::tr[1]/..";
        // $nodes = $this->http->XPath->query($xpath);
        // if($nodes->length == 0){
        // $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        // }

        // foreach($nodes as $root){
        $itsegment = [];

        $date = strtotime($this->normalizeDate($this->nextText('Departure date')));

        // AirlineName
        // FlightNumber
        $flight = $this->nextText('Flight number');

        if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
            if (!empty($matches['airline'])) {
                $itsegment['AirlineName'] = $matches['airline'];
            }
            $itsegment['FlightNumber'] = $matches['flightNumber'];
        }

        // DepCode
        $itsegment['DepCode'] = $this->re('/(' . $patterns['code'] . ')/', $this->nextText('Depart from'));

        // DepDate
        $timeDep = $this->re('/(' . $patterns['time'] . ')/', $this->nextText('Depart from', null, 2));

        if ($timeDep && $date) {
            $itsegment['DepDate'] = strtotime($this->normalizeTime($timeDep), $date);
        }

        // ArrCode
        $itsegment['ArrCode'] = $this->re('/(' . $patterns['code'] . ')/', $this->nextText('Arrive in'));

        // ArrDate
        $timeArr = $this->re('/(' . $patterns['time'] . ')/', $this->nextText('Arrive in', null, 2));

        if ($timeArr && $date) {
            $itsegment['ArrDate'] = strtotime($this->normalizeTime($timeArr), $date);
        }

        $it['TripSegments'][] = $itsegment;
        // }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airasia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'AirAsia Notification') === false) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Regards, AirAsia") or contains(normalize-space(.),"travel with AirAsia") or contains(.,"@airasia.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//eform.airasia.com") or contains(@href,"//support.airasia.com") or contains(@href,"//www.airasia.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'NewFlightTime' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[starts-with(normalize-space(.), '{$field}')])[1]/following::text()[string-length(normalize-space(.))>1][{$n}]", $root);
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

    private function normalizeDate($string = '')
    {
        if (preg_match('/^(\d{1,2})\s*([^\d\W]{3,})\s*(\d{4})$/u', $string, $matches)) { // 05 Nopember 2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/([^\d\W]{3,})\s*(\d{1,2})\s*,\s*(\d{4})$/u', $string, $matches)) { // Tuesday, April 18, 2017
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . ($year ? '.' . $year : '');
            }

            if (mb_strtolower($month) === 'nopember') { // it-5045542.eml
                $month = 'November';
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            } else { // it-12664497.eml
                $remainingLangs = array_diff(array_keys(MonthTranslate::$MonthNames), [$this->lang]);

                foreach ($remainingLangs as $lang) {
                    if (($monthNew = MonthTranslate::translate($month, $lang)) !== false) {
                        $month = $monthNew;

                        break;
                    }
                }
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    private function normalizeTime($string = '')
    {
        return preg_replace('/^(0{1,2}:\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
