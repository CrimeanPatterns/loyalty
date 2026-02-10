<?php

namespace AwardWallet\Engine\avelo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "avelo/it-348772671.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Departing'        => ['Departing', 'departing', 'DEPARTING'],
            'Purchase Summary' => 'Purchase Summary',
            'arriving'         => ['ARRIVING', 'arriving', 'Arriving'],
            'Flight'           => ['Flight'],
        ],
    ];

    private $detectFrom = "reservations@mail.aveloair.com";
    private $detectSubject = [
        // en
        'Your reservation confirmation #',
    ];

    private $detectBody = [
        'en' => [
            'Thank you for booking your flight with us',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.aveloair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) !== false) {
            return true;
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.aveloair.com'], '@href')}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0
                && !empty(self::$dictionary[$lang]) && !empty(self::$dictionary[$lang]['Departing']) && !empty(self::$dictionary[$lang]['Purchase Summary'])
                && $this->http->XPath->query("//*[{$this->contains(self::$dictionary[$lang]['Departing'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains(self::$dictionary[$lang]['Purchase Summary'])}]")->length > 0
            ) {
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
        // TODO check count types
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation #'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers(array_unique($this->http->FindNodes("//tr[descendant-or-self::tr[count(*[normalize-space()]) > 3 and *[normalize-space()][1][{$this->eq($this->t('TRAVELERS'))}] and *[normalize-space()][2][{$this->eq($this->t('BASE FARE'))}]]]/following-sibling::tr[normalize-space()][1]/descendant::tr[count(*[normalize-space()]) > 3]/td[normalize-space()][1]/descendant::text()[normalize-space()][1]")), true)
        ;

        // Segments
        $fxpath = "//tr[count(.//td[not(.//td)][normalize-space()]) = 2][descendant::td[not(.//td)][normalize-space()][1][{$this->eq($this->t('Departing'))}] and descendant::td[not(.//td)][normalize-space()][2][{$this->eq($this->t('arriving'))}]][following-sibling::*[2][count(.//td[not(.//td)][normalize-space()]) = 3 and descendant::td[not(.//td)][normalize-space()][2][{$this->starts($this->t('Flight'))}]]]";
//        $this->logger->debug('$fxpath = '.print_r( $fxpath,true));
        $nodes = $this->http->XPath->query($fxpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("following-sibling::*[normalize-space()][2]/descendant::td[not(.//td)][normalize-space()][2]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Flight'))}\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][1]", $root))
                ->code($this->http->FindSingleNode("following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)\s*$/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("following-sibling::*[normalize-space()][2]/descendant::td[not(.//td)][normalize-space()][1]", $root)))
            ;

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][2]", $root))
                ->code($this->http->FindSingleNode("following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][2]", $root, true, "/\(([A-Z]{3})\)\s*$/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("following-sibling::*[normalize-space()][2]/descendant::td[not(.//td)][normalize-space()][3]", $root)))
            ;

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("following-sibling::*[normalize-space()][3]/descendant::td[not(.//td)][normalize-space()][1]",
                    $root, true, "/{$this->opt($this->t('Duration:'))}\s*(.+)/"));

            if ($nodes->length == count($this->http->FindNodes("//text()[{$this->eq($this->t('TRAVELERS'))}]"))) {
                // no examples flights with 2 or more flight in Departing Flight or Return Flight
                $seats = $this->http->FindNodes("following::text()[{$this->eq($this->t('TRAVELERS'))}][1]/ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('TRAVELERS'))}])][last()]//text()[{$this->contains($this->t('RESERVED SEATS'))}]/ancestor::td[1]", $root);

                if (preg_match_all("/RESERVED SEATS\s*(\d{1,3}[A-Z])\s*,/", implode("\n", $seats), $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }

        // Price
        $total = $this->getTotal($this->http->FindSingleNode("//text()[{$this->eq($this->t('PAYMENT DETAILS'))}]/following::td[{$this->eq($this->t('Total:'))}]/following::td[normalize-space()][1]"));
        $f->price()
            ->total($total['amount'])
            ->currency($total['currency'])
        ;

        $cost = $this->getTotal($this->http->FindSingleNode("//text()[{$this->eq($this->t('PAYMENT DETAILS'))}]/following::td[{$this->eq($this->t('Airfare:'))}]/following::td[normalize-space()][1]"));
        $f->price()
            ->cost($cost['amount']);
        $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('PAYMENT DETAILS'))}]/following::td[{$this->eq($this->t('Airfare:'))}]/following::tr[normalize-space()][following::tr/td[{$this->eq($this->t('Total:'))}]]");

        foreach ($feeNodes as $fRoot) {
            $name = trim($this->http->FindSingleNode("*[normalize-space()][1]", $fRoot), ':');
            $amount = $this->getTotal($this->http->FindSingleNode("*[normalize-space()][2]", $fRoot));
            $f->price()
                ->fee($name, $amount['amount']);
        }

        return true;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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

        $in = [
            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r( $date, true));

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
}
