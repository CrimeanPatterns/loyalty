<?php

namespace AwardWallet\Engine\banff\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "banff/it-245588660.eml, banff/it-248378931.eml, banff/it-520761792.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectSubject = [
        // en
        'Banff Airporter booking confirmation#',
    ];

    private $detectBody = [
        'en' => [
            'Your booking is confirmed.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'info@banffairporter.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'Banff Airporter') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['banffairporter.com'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['Thank you for choosing Banff Airporter'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $t = $email->add()->transfer();

        $t->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts("Booking Confirmation:")}])[1]",
                null, true, "/Booking Confirmation:\s*(\d{4,})\s*$/"), "Booking Confirmation", true)
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts("Booking Reference ID:")}])[1]",
                null, true, "/Booking Reference ID:\s*([\dA-Z]{4,})\s*$/"), "Booking Reference ID")
            ->traveller($this->http->FindSingleNode("(//text()[{$this->starts("Guest Name:")}])[1]",
                null, true, "/Guest Name:\s*(.+)\s*$/"), true)
        ;

        // Price
        $cost = $this->http->FindSingleNode("(//*[{$this->eq('Booking Total')}]/following-sibling::*[normalize-space()])[1]");

        if (preg_match("/^\s*(?<total>\d[\d\,\.]*)\s*(?<currency>[^\s\d]{1,3})\s*$/u", $cost, $m)
            || preg_match("/^\s*(?<currency>[^\s\d]{1,3})\s*(?<total>\d[\d\,\.]*)\s*$/u", $cost, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);
            $t->price()
                ->cost(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }
        $price = $this->http->FindSingleNode("(//*[{$this->eq('Total')}]/following-sibling::*[normalize-space()])[1]");

        if (preg_match("/^\s*(?<total>\d[\d\,\.]*)\s*(?<currency>[^\s\d]{1,3})\s*$/u", $price, $m)
            || preg_match("/^\s*(?<currency>[^\s\d]{1,3})\s*(?<total>\d[\d\,\.]*)\s*$/u", $price, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);
            $t->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        foreach (['Fuel Surcharge', 'GST'] as $feeName) {
            $price = $this->http->FindSingleNode("(//*[{$this->eq($feeName)}]/following-sibling::*[normalize-space()])[1]");

            if (preg_match("/^\s*(?<total>\d[\d\,\.]*)\s*(?<currency>[^\s\d]{1,3})\s*$/u", $price, $m)
                || preg_match("/^\s*(?<currency>[^\s\d]{1,3})\s*(?<total>\d[\d\,\.]*)\s*$/u", $price, $m)
            ) {
                $currency = $this->normalizeCurrency($m['currency']);
                $t->price()
                    ->fee($feeName, PriceHelper::parse($m['total'], $currency));
            }
        }
        // Segments
        $xpath = "//*[count(*[normalize-space()]) = 2 and *[normalize-space()][1][" . $this->starts($this->t("Departure")) . "] and *[normalize-space()][2][" . $this->starts($this->t("Destination")) . "]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            // Departure
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root)))
                ->name($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][not({$this->starts('Your driver')})][4]", $root));

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root));

            $time = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][3]", $root,
                true, "/:\s*(\d{1,2}:\d{2})(?:\s*-|$)/");

            if (!empty($s->getDepDate()) && !empty($time)) {
                $aDate = strtotime($time, strtotime('00:00', $s->getDepDate()));

                if ($aDate < $s->getDepDate()) {
                    $aDate = strtotime('+1 day', $aDate);
                }
                $s->arrival()
                    ->date($aDate);
            }

            // Extra
            $s->extra()
                ->adults($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][not({$this->starts('Your driver')})][5]",
                    $root, true, "/\b(\d+)\s*(?:adult|Senior)/i"))
                ->kids($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][not({$this->starts('Your driver')})][5]",
                    $root, true, "/\b(\d+)\s*child/i"), true, true);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Dec 28 2022 - 13:30
            '/^\s*(\w+)\s+(\d+)\s+(\d{4})\s*-\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));
        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'CAD'   => ['$'], // not error
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
