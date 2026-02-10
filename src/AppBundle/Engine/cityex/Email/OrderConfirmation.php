<?php

namespace AwardWallet\Engine\cityex\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "cityex/it-102777715.eml, cityex/it-103755534.eml, cityex/it-106052119.eml";
    public $subjects = [
        'City Experiences Order Confirmation',
        'Statue City Cruises Order Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Cruising Time:'    => ['Cruising Time:', 'Cruise Time:'],
            'Confirmation No.:' => ['Confirmation No.:', 'Confirmation No.'],
            'PURCHASE DATE.'    => ['PURCHASE DATE.', 'Purchase Date'],
            'ORDER SUMMARY'     => ['ORDER SUMMARY', 'Order Details'],
            'ADDRESS'           => ['ADDRESS', 'Boarding Location:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@cityexperiences.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'City Experiences') or contains(normalize-space(), 'City Cruises')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Boarding Location:') or contains(normalize-space(), 'Boarding Time:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Disembarking Location:') or contains(normalize-space(), 'Cruising Time:')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cityexperiences\.com$/', $from) > 0;
    }

    public function ParseFerry(Email $email)
    {
        $f = $email->add()->ferry();

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/"))
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation No.:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation No.:'))}\s*(\d+)/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'PURCHASE DATE.')]/following::text()[normalize-space()][1]")));

        $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,]+)/");

        if (!empty($cost)) {
            $f->price()
                ->cost($cost);
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,]+)/");
        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\D)[\d\,]+/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total(PriceHelper::cost($total))
                ->currency($currency);
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Boarding Location:')]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), '/')][1]", $root, true, "/\s*(\d+\/\d+\/\d{4})/");
            $cruiseTime = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Cruising Time:'))}][1]", $root, true, "/{$this->opt($this->t('Cruising Time:'))}\s*(.+)/");

            if (preg_match("/^\s*([\d\:]+\s*A?P?M)[\s\-]+([\d\:]+\s*A?P?M)$/", $cruiseTime, $m) || preg_match("/^\s*([\d\:]+\s*A?P?M)$/", $cruiseTime, $m)) {
                $depTime = $m[1];

                if (isset($m[2])) {
                    $arrTime = $m[2];
                }
            }

            $s->departure()
                ->name($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Boarding Location:'))}\s*(\D+)/"));

            if (!empty(trim($depTime))) {
                $s->departure()
                    ->date(strtotime($date . ' ' . $depTime));
            } else {
                $s->departure()
                    ->noDate();
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Disembarking Location:')][1]", $root, true, "/{$this->opt($this->t('Disembarking Location:'))}\s*(\D+)/"));

            if (!empty($arrTime)) {
                $s->arrival()
                    ->date(strtotime($date . ' ' . $arrTime));
            } else {
                $s->arrival()
                    ->noDate();
            }

            $s->booked()
                ->adults($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Adult -')]/ancestor::tr[1]/descendant::td[2]"));
        }
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(4);

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/"))
        ;

        $confText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation No.:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation No.:'))}\s*([\da-z\/\s]+)/");

        if (stripos($confText, '/') !== false) {
            $confs = explode(" / ", $confText);

            foreach ($confs as $conf) {
                $e->general()
                    ->confirmation($conf);
            }
        } else {
            $e->general()
                ->confirmation($confText);
        }

        $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('PURCHASE DATE.'))}][not(contains(normalize-space(), ':'))]/following::text()[normalize-space()][1]");

        if (preg_match("/(.+\d{1,2}:\d{2}( *[ap]m)?) BST\s*$/i", $date, $m)) {
            // 30/05/2022 7:21 PM BST
            $e->general()->date(strtotime(str_replace('/', '.', $date)));
        } else {
            $e->general()->date(strtotime($date));
        }

        $e->place()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('ORDER SUMMARY'))}]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('ORDER SUMMARY'))}]/following::text()[{$this->eq($this->t('ADDRESS'))}][1]/following::text()[normalize-space()][1]"));

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Boarding Location:')]/preceding::text()[contains(normalize-space(), '/')][1]", null, true, "/\s*(\d+\/\d+\/\d{4})/");

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Boarding Time:')]/preceding::text()[normalize-space()][1]", null, true, "/((?:\d+\/\d+\/\d{4}|\w+\s*\d+\,?\s*\d{4}))/");
        }

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Security Check In:')]/preceding::text()[normalize-space()][1]", null, true, "/(\d+\/\d+\/\d{4})/");
        }

        $depTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Boarding Location:')]/following::text()[{$this->starts($this->t('Cruising Time:'))}][1]", null, true, "/{$this->opt($this->t('Cruising Time:'))}\s*([\d\:]+\s*A?P?M)/");

        if (empty($depTime)) {
            $depTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cruising Time:')]/ancestor::tr[1]", null, true, "/Cruising Time\:\s*([\d\:]+\s*A?P?M?)/");
        }

        if (empty($depTime)) {
            $depTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tour Start Time:')]", null, true, "/Tour Start Time\:\s*([\d\:]+\s*A?P?M)/");
        }

        if (empty($depTime)) {
            $depTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Sailing Time:')]", null, true, "/Sailing Time\:\s*([\d\:]+\s*A?P?M)/");
        }

        $arrTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cruising Time:')]", null, true, "/Cruising Time\:\s*[\d\:]+\s*A?P?M[\s\-]+([\d\:]+\s*A?P?M)/");

        if (!empty($arrTime)) {
            $e->booked()
                ->end(strtotime($date . ' ' . $arrTime));
        } else {
            $e->booked()
                ->noEnd();
        }

        if (!empty($date) && !empty($depTime)) {
            $e->booked()
                ->start(strtotime($date . ' ' . $depTime));
        }
        $adults = $this->http->FindSingleNode("//text()[normalize-space() = 'QTY']/ancestor::tr[1]/following::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");
        $kids = $this->http->FindSingleNode("//text()[normalize-space() = 'QTY']/ancestor::tr[1]/following::tr[2]/descendant::td[1][starts-with(normalize-space(), 'Child')]/following::td[1]", null, true, "/^(\d+)$/");

        if (!empty($kids)) {
            $e->booked()
                ->guests(array_sum([$adults, $kids]));
        } else {
            $e->booked()
                ->guests($adults);
        }

        $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,]+)/");

        if (!empty($cost)) {
            $e->price()
                ->cost(PriceHelper::cost($cost));
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s*\D([\d\,\.]+)/");

        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s*\D[\d\,\.]+\s*([A-Z]{3})/");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s*(\D)[\d\,\.]+/");
        }

        if (!empty($total) && !empty($currency)) {
            $e->price()
                ->total(PriceHelper::cost($total))
                ->currency($currency);
        }

        $discount = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'DISCOUNT'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,]+)/");

        if (!empty($discount)) {
            $e->price()
                ->discount($discount);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Return Trip:'))}]")->length > 0) {
            $this->ParseFerry($email);
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Return Trip:'))}]")->length == 0) {
            $this->ParseEvent($email);
        }

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
