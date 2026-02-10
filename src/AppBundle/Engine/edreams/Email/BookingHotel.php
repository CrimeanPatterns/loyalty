<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHotel extends \TAccountChecker
{
    public $mailFiles = "edreams/it-702597192.eml";
    public $subjects = [
        '/Your booking is confirmed\!\s*\(Reference\:\s*\d+\)/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Check cancellation policy' => ['Check cancellation policy', 'Check cancelation policy'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mailer.edreams.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'eDreams')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Check-in'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your stay'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Customer details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mailer\.edreams\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'booking reference:')]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));

        $this->Hotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Hotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Great news,'))}]", null, true, "/{$this->opt($this->t('Great news,'))}\s*(.+)\!/"))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check cancellation policy'))}]/following::text()[normalize-space()][string-length()>5][1]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='How to get there']/preceding::text()[normalize-space()][string-length()>5][2]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='How to get there']/preceding::text()[normalize-space()][string-length()>5][1]"));

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Group:']/following::text()[normalize-space()][string-length()>5][1]", null, true, "/^(\d+)\s*{$this->opt($this->t('adult'))}/"))
            ->kids($this->http->FindSingleNode("//text()[normalize-space()='Group:']/following::text()[normalize-space()][string-length()>5][2]", null, true, "/^(\d+)\s*{$this->opt($this->t('children'))}/"), true, true);

        $inText = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::text()[normalize-space()][string-length()>5][1]/ancestor::table[1]");

        if (preg_match("/Check\-in\w+\.?\,\s*(\d+)\s*(\w+)\.?\s*(\d{4})\s*From\s*([\d\:]+\s*A?P?M?)/", $inText, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]));
        }

        $outText = $this->http->FindSingleNode("//text()[normalize-space()='Check-out']/following::text()[normalize-space()][string-length()>5][1]/ancestor::table[1]");

        if (preg_match("/Check\-out\w+\.?\,\s*(\d+)\s*(\w+)\.?\s*(\d{4})\s*Until\s*([\d\:]+\s*A?P?M?)/", $outText, $m)) {
            $h->booked()
                ->checkOut(strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]));
        }

        $this->detectDeadLine($h);

        $rooms = $this->http->FindNodes("//text()[normalize-space()='Room:']/ancestor::tr[1]/following-sibling::tr");

        foreach ($rooms as $roomItem) {
            $room = $h->addRoom();
            $room->setType($roomItem);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Prime price'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Prime price'))}\s*(\D*\s*[\d\.\,]+\D*)/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)
            || preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $discount = $this->http->FindSingleNode("//text()[{$this->contains($this->t('discount'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s([\d\.\,]+)/");

            if (!empty($discount)) {
                $h->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/This booking is not cancellable/", $cancellationText)) {
            $h->booked()->
            nonRefundable();
        }
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'TWD' => ['NT$'],
            'CAD' => ['C$'],
            'GBP' => ['£'],
            'AUD' => ['A$', 'AU$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }
}
