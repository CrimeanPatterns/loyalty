<?php

namespace AwardWallet\Engine\advantagecar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RentalItinerary extends \TAccountChecker
{
    public $mailFiles = "advantagecar/it-45998453.eml, advantagecar/it-56215591.eml";

    public $reFrom = ["@tripactions.com", "@advantage.com"];
    public $reSubject = [
        'en' => 'Reservation Confirmed',
    ];
    public $keywordProv = 'ADVANTAGE';
    public $reBody = [
        'en' => ['To verify your age, you must provide acceptable'],
    ];

    public static $dictionary = [
        'en' => [
            'TOTAL DUE:' => ['TOTAL DUE:', 'TOTAL PAID:'],
            //            'traveller' => ['Passenger:'],
        ],
    ];

    public $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseRental($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->keywordProv)}]")->length > 0) {
            foreach ($this->reBody as $re) {
                if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseRental(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//*[contains(text(),'Confirmation Number:')]/ancestor::tr[1]/following-sibling::tr[1]"), 'Confirmation Number:')
            ->traveller($this->http->FindSingleNode("//*[contains(text(),'Thank you, ')]", null, false, '/,\s*(.+?)!/'));
        $r->program()->code('advantagecar');

        $r->car()->image($this->http->FindSingleNode("//img[contains(@alt,'Car Reserved')]/@src"));
        $r->car()->type($this->http->FindSingleNode("//img[contains(@alt,'Car Reserved')]/ancestor::tr[1]/following-sibling::tr[string-length( .) > 6][1]"));

        $r->pickup()
            ->date(strtotime(str_replace([',', ' |'], [' ', ','], $this->http->FindSingleNode("//span[contains(text(),'PICK UP:')]/following-sibling::span[1]", null, false, '/-\s*(.+)/'))))
            ->location($this->http->FindSingleNode("//span[contains(text(),'PICK UP:')]/following-sibling::address[1]"))
            ->phone($this->http->FindSingleNode("//text()[contains(.,'Call us directly at')]/following-sibling::a[contains(@href,'tel:')]"));

        $r->dropoff()
            ->date(strtotime(str_replace([',', ' |'], [' ', ','], $this->http->FindSingleNode("//span[contains(text(),'RETURN:')]/following-sibling::span[1]", null, false, '/-\s*(.+)/'))))
            ->location($this->http->FindSingleNode("//span[contains(text(),'RETURN:')]/following-sibling::address[1]"));

        $r->general()->cancellation($this->http->FindSingleNode("//span[contains(text(),'CANCELLATION POLICY:')]/following-sibling::span[1]"));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//span[{$this->contains($this->t('TOTAL DUE:'))}]/following-sibling::text()[normalize-space()]"));
        $r->price()
            ->total($tot['Total'])
            ->currency($tot['Currency']);

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'Taxes and Fees')]/ancestor::td[1]/following-sibling::td"));

        if (!empty($tot['Total'])) {
            $r->price()->fee('Taxes and Fees', $tot['Total']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'Extras')]/ancestor::td[1]/following-sibling::td"));

        if (!empty($tot['Total'])) {
            $r->price()->fee('Taxes and Fees', $tot['Total']);
        }
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
    private function splitter($pattern, $text)
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function htmlToText($string)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        $string = str_replace('-->', '', html_entity_decode($string));
        $string = preg_replace('/<[^>]+>/', "\n", $string);
        $string = preg_replace(['/\n{2,}\s{2,}/'], "\n", $string);

        return $string;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return null;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);

        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*?\-?(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*?\-?(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function normalizeDate($str)
    {
//        $in = [
//            "#^(\w+)\s+(\d+),\s+(\d{4})$#",
//            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s*[ap]m)$#i",
//        ];
//        $out = [
//            "$2 $1 $3",
//            "$2 $1 $3, $4",
//        ];
//        return strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));
        return strtotime($str, false);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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
