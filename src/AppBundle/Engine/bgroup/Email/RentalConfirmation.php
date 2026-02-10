<?php

namespace AwardWallet\Engine\bgroup\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalConfirmation extends \TAccountChecker
{
    public $mailFiles = "bgroup/it-185583398.eml";
    public $subjects = [
        'Confirmation of your car rental booking',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bookinggroup.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'EconomyBookings.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('for booking a car rental with'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View Voucher'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('CANCELLATION POLICY'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bookinggroup\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseRental($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $traveller = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Thank you')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Thank you'))}\s*(\w+)\,/");

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller, false);
        }

        $confInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), '. Your booking')]");

        if (preg_match("/{$this->opt($this->t('Your booking'))}\s*(?<conf>[A-Z\d]{6,})\s*{$this->opt($this->t('is'))}\s*(?<status>\w+)/", $confInfo, $m)) {
            $r->general()
                ->confirmation($m['conf'])
                ->status($m['status']);
        }

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Pick-up')]/ancestor::td[1]/following::td[string-length()>3][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Pick-up')]/ancestor::td[1]/following::td[string-length()>3][2]")));

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Drop-off')]/ancestor::td[1]/following::td[string-length()>3][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Drop-off')]/ancestor::td[1]/following::td[string-length()>3][2]")));

        $r->car()
            ->image($this->http->FindSingleNode("//text()[contains(normalize-space(), 'or similar')]/following::img[1]/@src"))
            ->model(str_replace("\n", " ", implode("\n", $this->http->FindNodes("//text()[contains(normalize-space(), 'or similar')]/ancestor::td[1]/descendant::text()[normalize-space()]"))));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='To pay on arrival:']/following::text()[string-length()>2][1][contains(normalize-space(), '0.00 ')]/following::text()[contains(normalize-space(), ')')][1]");

        if (preg_match("/\((?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\)/", $price, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        } else {
            $currency = $this->http->FindSingleNode("//text()[normalize-space()='To pay on arrival:']/following::text()[string-length()>2][1]", null, true, "/^\s*[\d\.\,]+\s*([A-Z]{3})/");
            $priceOne = $this->http->FindSingleNode("//text()[normalize-space()='To pay on arrival:']/following::text()[string-length()>2][1]", null, true, "/^\s*([\d\.\,]+)/");
            $priceOne = PriceHelper::parse($priceOne, $currency);

            $priceTwo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'You have made a prepayment in amount of')]/ancestor::tr[1]", null, true, "/You have made a prepayment in amount of\s*([\d\.]+)\s+/");
            $priceTwo = PriceHelper::parse($priceTwo, $currency);

            if (!empty($priceOne) && !empty($priceTwo)) {
                $r->price()
                    ->currency($currency)
                    ->total($priceOne + $priceTwo);
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        if ($this->lang == 'en' && stripos($str, 'Okt.') !== false) {
            $str = str_replace('Okt.', 'Oct.', $str);
        }

        $in = [
            "/^(\d+)\s*(\w+)\.?\s*(\d{4})\,\s*([\d\:]+)$/u", //21 Okt. 2022, 17:00
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
