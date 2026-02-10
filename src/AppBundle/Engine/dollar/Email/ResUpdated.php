<?php

namespace AwardWallet\Engine\dollar\Email;

class ResUpdated extends \TAccountChecker
{
    public $mailFiles = "dollar/it-6602714.eml"; //+ dropbox RentingHtml2017.png

    public $reFrom = "emails.dollar.com";
    public $reBody = [
        'en' => ['Vehicle Pick-up Information', 'Confirmation'],
    ];
    public $reSubject = [
        'Your reservation has been updated',
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
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'dollar.com')]")->length > 0) {
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
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Confirmation #')]", null, true, "#:\s*([A-Z\d]+)#");
        $it['RenterName'] = $this->getTextAfter("Name");
        $it['CarType'] = $this->getTextAfter("Vehicle Type");
        $it['AccountNumbers'][] = $this->re("#.+?\s*([A-Z\d]+)$#", $this->getTextAfter("Rewards Program"));
        $roots = $this->http->XPath->query("//text()[starts-with(normalize-space(.),'Vehicle Pick-up Information')]");

        if ($roots->length > 0) {
            $root = $roots->item(0);
        } else {
            $root = null;
        }
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->getTextAfter("Date/Time", $root)));
        $it['PickupLocation'] = $this->getTextAfter("Location", $root);
        $it['PickupPhone'] = $this->getTextAfter("Phone", $root);
        $roots = $this->http->XPath->query("//text()[starts-with(normalize-space(.),'Vehicle Return Information')]");

        if ($roots->length > 0) {
            $root = $roots->item(0);
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->getTextAfter("Date/Time", $root)));
            $it['DropoffLocation'] = $this->getTextAfter("Location", $root);
            $it['DropoffPhone'] = $this->getTextAfter("Phone", $root);
        } else {
            $this->logger->debug('other format');

            return [];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Approximate Total')]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $currency = $this->http->FindSingleNode("//text()[contains(., 'Currency:')]/ancestor::td[1]/following-sibling::td[1]");

        if ($currency == 'CANADIAN DOLLAR') {
            $it['Currency'] = 'CAD';
        }

        if ($currency == 'EURO') {
            $it['Currency'] = 'EUR';
        }

        if ($currency == 'U.S. DOLLAR') {
            $it['Currency'] = 'USD';
        }

        return [$it];
    }

    private function getTextAfter($field, $root = null)
    {
        return $this->http->FindSingleNode("(.//following::text()[starts-with(normalize-space(.),'{$field}')]/ancestor::td[1]//text()[normalize-space(.)])[2]", $root);
    }

    private function normalizeDate($date)
    {
        $in = [
            //Thursday, June 01, 2017 @ 10:00 AM
            '#^\S+\s+(\w+)\s+(\d+),\s+(\d+)\s+@\s+(\d+:\d+(?:\s*[ap]m)?)$#iu',
        ];
        $out = [
            '$2 $1 $3 $4',
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
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
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
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
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
