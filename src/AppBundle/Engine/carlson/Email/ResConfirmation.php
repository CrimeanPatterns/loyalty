<?php

namespace AwardWallet\Engine\carlson\Email;

class ResConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@bostonparkplaza.com";
    public $reBody = [
        'en' => ['Reservation Confirmation'],
    ];
    public $reSubject = [
        'Boston Park Plaza - Reservation Confirmation #',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Boston Park Plaza')] | //a[contains(@href,'bostonparkplaza.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
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
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("(//text()[contains(.,'Reservation Confirmation')])[1]", null, true, "#[\#:\s]+([A-Z\d]{5,})#");
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'website')]/ancestor::*[1]");

        if (stripos($node, "phone") !== false) {
            $it['Address'] = $this->re("#(.+?)\s+Phone:#", $node);
            $it['Phone'] = $this->re("#Phone:\s+(.+)\s+E\-mail#", $node);

            $it['HotelName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'website')]/ancestor::*[1]/preceding-sibling::*[1]");
        }
        $it['GuestNames'][] = $this->nextText("Guest Name:");
        $addGuests = $this->nextText("Additional Guests:");

        if (stripos($addGuests, "not provided") === false) {
            $pax = array_map("trim", $this->http->FindNodes("(.//text()[{$this->eq('Additional Guests:')}])[1]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)]"));

            foreach ($pax as $p) {
                $it['GuestNames'][] = $p;
            }
        }
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Check-in from:")));
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Check-out by:")));
        $node = $this->nextText("Number of Guests:");
        $it['Guests'] = $this->re("#Adults:\s+(\d+)#", $node);
        $it['Kids'] = $this->re("#Children:\s+(\d+)#", $node);
        $it['RoomType'] = $this->nextText("Room Type:");
        $it['RoomTypeDescription'] = $this->nextText("Smoking Preference:");
        $it['RateType'] = $this->nextText("Rate Plan");
        $tot = $this->getTotalCurrency($this->nextText("Total:"));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Reservation Policies:']/ancestor::td[1]/following::td[1]//text()[normalize-space(.)][contains(.,'Cancellations')][1]");

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //3:00 PM, Wednesday, 17 May, 2017
            '#^\s*(\d+:\d+\s*(?:[ap]m)?),\s+\w+,\s+(\d+)\s+(\w+),\s+(\d{4})\s*$#i',
        ];
        $out = [
            '$2 $3 $4 $1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $this->t($field);

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
