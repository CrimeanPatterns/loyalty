<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Engine\MonthTranslate;

class TrainReservation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-11225183.eml";

    public $reSubject = [
        'en' => ['Ticket Issued', 'Awaiting Payment'],
    ];

    public $lang = '';

    public $langDetectors = [
        'en' => ['Arrival Time:'],
    ];

    public static $dict = [
        'en' => [
            'Booking Number' => ['Booking Number', 'Booking No'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Ctrip.com") or contains(.,"@ctrip.com") or contains(normalize-space(.), "Thank you for choosing Trip.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//ctrip.com") or contains(@href,"//www.ctrip.com") or contains(@href,"//english.ctrip.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
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
            || stripos($from, '@ctrip.com') !== false;
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\TrainTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Number'))}]/following::text()[normalize-space(.)!=''][1]", null, true, '/^([A-Z\d]{5,})$/');

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Adult x'))}]/ancestor::td[1]/following-sibling::td"));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Fee'))}]/ancestor::td[1]/following-sibling::td"));

        if (!empty($tot['Total'])) {
            $it['Tax'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        $nodes = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::td[1]/following-sibling::td//text()[normalize-space(.)]"));

        if (preg_match_all("#^(.+)\s*\(.*?([A-Z\d\*]{5,})\)\s*\(({$this->opt($this->t('Carriage'))}.+)\)#m", $nodes, $m)) {
            $it['Passengers'] = $m[1];
            $it['AccountNumbers'] = $m[2];
            $seg['Seats'] = $m[3];
        }
        $nodes = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Itinerary'))}]/ancestor::td[1]/following-sibling::td//text()"));

        if (preg_match("#(.+?)\s*\-\s*(.+?)\s*\((Train) Number[\s:]+[A-Z]{1,2}\s*((\d+))\)#", $nodes, $m)) {
            $seg['DepName'] = $m[1];
            $seg['ArrName'] = $m[2];
            $seg['Type'] = $m[3] . ', ' . $m[4];
            $seg['FlightNumber'] = $m[5];
        }

        $seg['DepDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Departure Time'))}[\s:]+(.+)#", $nodes));
        $seg['ArrDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Arrival Time'))}[\s:]+(.+)#", $nodes));

        if (!empty($seg['DepName']) && !empty($seg['ArrName']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+:\d+)\s+(\d+)\s+(\w+),\s+(\d+)\s*$#u', // 16:35 29 Dec, 2017
        ];
        $out = [
            '$4-$3-$2 $1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
}
