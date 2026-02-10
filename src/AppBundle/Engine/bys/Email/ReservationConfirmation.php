<?php

namespace AwardWallet\Engine\bys\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "bys/it-99984270.eml";
    public $subjects = [
        '/Reservation Confirmation \| /u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Children' => ['Children', 'Children-R (2-12yrs)'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bookyoursite.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'BookYourSite.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Sitetype:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departure:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bookyoursite\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Res. ID')]/following::text()[normalize-space()][1]"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Created:')]/following::text()[normalize-space()][1]")))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Payment Auth. Code')]/preceding::text()[starts-with(normalize-space(), 'P:')][1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]"));

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Payment Auth. Code')]/preceding::text()[starts-with(normalize-space(), 'P:')][1]/ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][1]");
        $h->hotel()
            ->name($name);

        $hotelInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Payment Auth. Code')]/preceding::text()[starts-with(normalize-space(), 'P:')][1]/ancestor::tr[1]/descendant::td[1]");

        if (preg_match("/{$name}\s*(.+)\s*P\:([\(\)\d\s\-]+)/msu", $hotelInfo, $m)) {
            $h->hotel()
                ->address(str_replace("\n", " ", $m[1]))
                ->phone($m[2]);
        }

        $adult = $this->http->FindSingleNode("//text()[normalize-space()='Charge Name']/following::text()[normalize-space()='Adults']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Adults'))}\s*(\d+)/us");

        if (!empty($adult)) {
            $h->booked()
                ->guests($adult);
        }

        $kids = $this->http->FindSingleNode("//text()[normalize-space()='Charge Name']/following::text()[starts-with(normalize-space(), 'Children')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Children'))}\s*(\d+)/us");

        if (!empty($kids)) {
            $h->booked()
                ->kids($kids);
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival:')]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure:')]/following::text()[normalize-space()][1]")));

        $h->price()
            ->cost($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Subtotal')]/following::text()[normalize-space()][1]", null, true, "/^\D(.+)/"))
            ->total($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total')]/following::text()[normalize-space()][1]", null, true, "/^\D(.+)/"))
            ->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total')]/following::text()[normalize-space()][1]", null, true, "/^(\D)/"));

        $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tax Total')]/following::text()[normalize-space()][1]", null, true, "/^\D(.+)/");

        if (!empty($tax)) {
            $h->price()
                ->tax($tax);
        }

        $account = $this->http->FindSingleNode("//text()[normalize-space()='Member ID:']/following::text()[normalize-space()][1]");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
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
}
