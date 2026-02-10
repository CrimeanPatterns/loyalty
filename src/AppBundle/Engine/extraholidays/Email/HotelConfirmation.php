<?php

namespace AwardWallet\Engine\extraholidays\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "extraholidays/it-387155679.eml, extraholidays/it-387804302.eml";
    public $subjects = [
        ' - Here is Your Extra Holidays Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@extraholidays.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Extra Holidays')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Vacation At'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Unit Type'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]extraholidays\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*(\d{5,})\s*$/s"))
            ->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), ', thanks for choosing')]", null, true, "/^(\D+){$this->opt($this->t(', thanks for choosing'))}/"))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Resort Amenities']/preceding::table[1]/descendant::tr[1]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Resort Amenities']/preceding::table[1]/descendant::tr[2]", null, true, "/^(.+)\s{$this->opt($this->t('Resort Phone:'))}/su"))
            ->phone($this->http->FindSingleNode("//text()[normalize-space()='Resort Amenities']/preceding::table[1]/descendant::tr[2]", null, true, "/{$this->opt($this->t('Resort Phone:'))}\s*([\d\-]+)/"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check In']/ancestor::table[1]", null, true, "/{$this->opt($this->t('Check In'))}\s*(.+[\d\:]+\s*A?P?M?)\s*$/isu")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check Out']/ancestor::table[1]", null, true, "/{$this->opt($this->t('Check Out'))}\s*(.+[\d\:]+\s*A?P?M?)\s*$/isu")));

        $h->addRoom()->setType($this->http->FindSingleNode("//text()[normalize-space()='Unit Type']/ancestor::table[1]", null, true, "/{$this->opt($this->t('Unit Type'))}\s*(.+)/su"));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Cost']/ancestor::table[1]", null, true, "/{$this->opt($this->t('Total Cost'))}\s*(\D\s*[\.\d\,]+)/su");

        if (preg_match("/(?<currency>\D*)\s*(?<total>[\,\.\d]+)/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
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
}
