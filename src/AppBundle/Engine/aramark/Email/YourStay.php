<?php

namespace AwardWallet\Engine\aramark\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourStay extends \TAccountChecker
{
    public $mailFiles = "aramark/it-91273466.eml";
    public $lang = "en";

    public static $dictionary = [
        "en" => [
            //            '' => '',
        ],
    ];

    private $detectFrom = "@aramark.com";

    private $detectSubject = [
        // en
        "Reservation Confirmation",
    ];

    private $detectCompany = [
        'This email was sent by: Aramark',
        '@aramark.com',
        '@Aramark.com',
        '.travelyosemite.com',
        '.olympicnationalparks.com',
        //        '',
    ];

    private $detectBody = [
        "en" => ["Thank you for your reservation and we look forward to seeing you!"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $detectBody){
//            if ($this->http->XPath->query("//text()[".$this->contains($detectBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "] | //*[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
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

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->getField($this->t('Itinerary Number')), 'Itinerary Number')
                ->confirmation($this->getField($this->t('Confirmation Number')), 'Confirmation Number')
            ->traveller($this->getField($this->t('Guest Name:')), true);

        $cancellation = implode(' ', $this->http->FindNodes("//tr[not(normalize-space()) and .//img[@alt = 'CANCELLATION POLICY' or contains(@src, '_Cancellation.gif')]]/following-sibling::tr"));

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        // Hotel
        $name = $this->getField($this->t('Property:'));
        $address = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your reservation confirmation for")) . "]", null, true,
            "/" . $this->preg_implode($this->t("Your reservation confirmation for")) . "\s+(.+)/");

        $h->hotel()
            ->name($name)
            ->address($address)
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->getField($this->t("Check In:"))))
            ->checkOut($this->normalizeDate($this->getField($this->t('Check Out:'))))
            ->guests($this->getField($this->t('Number of Adults:')))
            ->kids($this->getField($this->t('Number of Children:')))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->getField($this->t('Room Type:')))
        ;

        // Price
        $taxes = $this->getField($this->t('Taxes:'));

        if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $taxes, $m)) {
            $h->price()
                ->tax(PriceHelper::cost($m[2]))
                ->currency($m[1]);
        }
        $total = $this->getField($this->t('Total Cost:'));

        if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $total, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m[2]))
                ->currency($m[1]);
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations made (?<priorDay>\d+) or more days? prior to arrival will receive a full refund\./i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorDay'] . " days", "00:00");
        }
    }

    private function getField($field, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(//text()[{$this->eq($field)}]/following::text()[normalize-space(.)][1])[{$n}]", null, true, $regexp);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            //            "#^[\w|\D]+\s+(\d+)\s+(\D+)\s+(\d{4})$#",
        ];
        $out = [
            //            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
