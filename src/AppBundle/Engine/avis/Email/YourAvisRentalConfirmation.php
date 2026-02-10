<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourAvisRentalConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "avis/it-1604234.eml, avis/it-1712283.eml, avis/it-1876271.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    private $from = "#no-reply@avis\.com#i";

    private $detects = [
        'Upon paying your rental with a Debit Card, Avis/Budget will generally request an authorization hold against your account for the estimated charges of the rental',
        'Your Avis Rental Confirmation',
        'thank you for choosing Budget',
        'thank you for choosing Avis',
    ];

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation Confirmation Number:']/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]+)/"))
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Name']/following::text()[normalize-space()][1]"));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Estimated Total']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<total>[\d\,\.]+)\s*(?<currency>[A-Z]{3})$/", $price, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Base Rate']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)/");

            if (!empty($cost)) {
                $r->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes and Surcharges']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)/");

            if (!empty($tax)) {
                $r->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        $carInfo = $this->http->FindSingleNode("//text()[normalize-space()='Car Information']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<type>.+)\s+\-\s+(?<model>.+)/u", $carInfo, $m)) {
            $r->car()
                ->type($m['type'])
                ->model($m['model']);
            $image = $this->http->FindSingleNode("//img[contains(@alt, 'Vehicle Image')]/@src[not(contains(normalize-space(), 'cid:'))]");

            if (!empty($image)) {
                $r->car()
                    ->image($this->http->FindSingleNode("//img[contains(@alt, 'Vehicle Image')]/@src"));
            }
        }

        $pickUpInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Pick-up']/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Pick-up'))]"));

        if (preg_match("/^(?<date>.+A?P?M)\n(?<location>.+)\n(?<phone>[+]*[\d\s\-\(\)]+)$/su", $pickUpInfo, $m)
        || preg_match("/^(?<date>.+)\n(?<location>(?:.+\n*){1,4})$/u", $pickUpInfo, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['date']))
                ->location(str_replace("\n", " ", $m['location']));

            if (isset($m['phone']) && !empty($m['phone'])) {
                $r->pickup()
                    ->phone($m['phone']);
            }
        }

        $dropOffInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Return']/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Return'))]"));

        if (preg_match("/^(?<date>.+A?P?M)\n(?<location>.+)\n(?<phone>[+]*[\d\s\-\(\)]+)$/su", $dropOffInfo, $m)
        || preg_match("/^(?<date>.+)\n(?<location>(?:.+\n*){1,4})$/u", $dropOffInfo, $m)) {
            $r->dropoff()
                ->date($this->normalizeDate($m['date']))
                ->location(str_replace("\n", " ", $m['location']));

            if (isset($m['phone']) && !empty($m['phone'])) {
                $r->dropoff()
                     ->phone($m['phone']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseRental($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
        $in = [
            "#^\w+\s*(\w+)\s*(\d+)\,\s*(\d{4})\D+([\d\:]+\s*A?P?M)$#u", //Thu September 08, 2022 at 8:30 PM
        ];
        $out = [
            "$2 $1 $3, $4",
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
