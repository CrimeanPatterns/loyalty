<?php

namespace AwardWallet\Engine\avani\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "avani/it-35279246.eml";

    public $reFrom = ["avaniplus.bangkok@avanihotels.com"];
    public $reBody = [
        'en' => ['Your booking is confirmed', 'Thank you for your reservation'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Room and Rate Details' => 'Room and Rate Details',
            'Booking Number'        => 'Booking Number',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='AVANI Hotels & Resorts'] | //a[contains(@href,'avanihotels.com')]")->length > 0
            && $this->detectBody($parser->getHTMLBody())
            && $this->assignLang()
        ) {
            return true;
        }

        return false;
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
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Number'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"),
                $this->t('Booking Number'))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Name'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"),
                true)
            ->status($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Your booking is'))}])[1]", null,
                false, "#{$this->opt($this->t('Your booking is'))}\s+(\w+)#"))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy:'))}]/following::*[normalize-space()!=''][1]"));

        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address:'))}]/preceding::text()[normalize-space()!=''][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"))
            ->fax($this->http->FindSingleNode("//text()[{$this->eq($this->t('Fax:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));

        $r->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]")))
            ->guests($this->http->FindSingleNode("//text()[({$this->contains($this->t('Adults'))}) and ({$this->contains($this->t('Nights'))})]/ancestor::td[1]",
                null, false, "#(\d+)\s+{$this->opt($this->t('Adults'))}#"))
            ->rooms($this->http->FindSingleNode("//text()[({$this->contains($this->t('Adults'))}) and ({$this->contains($this->t('Nights'))})]/ancestor::td[1]",
                null, false, "#(\d+)\s+{$this->opt($this->t('Room'))}#"));

        $room = $r->addRoom();
        $room
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room and Rate Details'))}]/following::text()[normalize-space()!=''][1]"));
        $nodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Room and Rate Details'))}]/following::text()[string-length(normalize-space())>2][1]/following-sibling::*/descendant::ul/descendant::text()[normalize-space()!='']");

        if (count($nodes) > 0) {
            $room
                ->setRateType(implode("; ", $nodes));
        }
        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total price for this hotel'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $r->price()
            ->total($total['Total'])
            ->currency(($total['Currency']));

        $tax = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Taxes & Fees'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $r->price()
            ->tax($tax['Total']);
        $cost = $this->getTotalCurrency($this->http->FindSingleNode("//text()[({$this->contains($this->t('Adults'))}) and ({$this->contains($this->t('Nights'))})]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $r->price()
            ->cost($cost['Total']);

        $this->detectDeadLine($r);

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Monday 24 Jun 2019 check-in from 15:00  | Wednesday 26 Jun 2019 check-out until 12:00
            '#^[\-\w]+,?\s+(\d+)\s+(\w+)\s+(\d{4})\s+.+?\s+(\d+:\d+)\s*$#u',
            //24 Jun 2019, 12:00
            '#^(\d+)\s+(\w+)\s+(\d{4}),\s+(\d+:\d+)\s*$#u',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#Cancel by (?<time>\d+:\d+) on (?<date>.+? \d{4}) to avoid a penalty charge of [A-Z]{3} \d.+#ui",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Room and Rate Details"], $words["Booking Number"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Room and Rate Details'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Booking Number'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
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
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
