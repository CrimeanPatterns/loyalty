<?php

namespace AwardWallet\Engine\atriis\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookedItems extends \TAccountChecker
{
    public $mailFiles = "atriis/it-382881587.eml, atriis/it-424895701.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "notification@gtp-marketplace.com";
    private $detectSubject = [
        // en
        // E-ticket confirmation for Olivier Ramos, Trip 12386457
        'E-ticket confirmation for',
        // New booking in trip 11462587 created by Dave Rymen
        'New booking in trip',
    ];
    private $detectBody = [
        'en' => [
            'E-ticket confirmation',
            'New Booking Notification',
        ],
    ];
    private static $detectProvider = [
        'travexp' => [
            '@travel-experts.be',
        ],
    ];

    // Main Detects Methods

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]gtp-marketplace\.com$/", $from) > 0;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.gtp-marketplace.com'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['@gtp-marketplace.com'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->eq($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEmailHtml($email);

        foreach (self::$detectProvider as $code => $prDetect) {
            if ($this->http->XPath->query("//node()[{$this->contains($prDetect)}]")->length > 0) {
                $email->setProviderCode($code);

                break;
            }
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Traveller(s) Name"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Traveller(s) Name'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Flights'))}]")->length > 0) {
            $this->parseFlight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Hotels'))}]")->length > 0) {
            //  no examples
            $email->add()->hotel();
        }

        return true;
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->travellers(array_filter(preg_split("/\s*,\s*/", $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveller(s) Name:'))}]/following::text()[normalize-space()][1]"))))
        ;

        // Issued
        $tickets = array_filter(preg_split("/\s*,\s*/", $this->http->FindSingleNode("//text()[{$this->eq($this->t('E-Ticket:'))}]/following::text()[normalize-space()][1]")));

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        // Price
        $priceInfo = implode("\n", $this->http->FindNodes("//tr[count(*) = 2][*[1][{$this->starts($this->t('Flight '))}]]/*[2][{$this->starts($this->t('Price:'))}]//text()[normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('Price:'))}\s*(?<cost>.+)\s+{$this->opt($this->t('Tax:'))}\s*(?<tax>.+)\s*(?:\n+.*)*\n(?<total>.+)\s*$/", $priceInfo, $m)) {
            $f->price()
                ->total($this->getTotal($m['total'])['amount'])
                ->currency($this->getTotal($m['total'])['currency'])
                ->cost($this->getTotal($m['cost'])['amount'])
                ->tax($this->getTotal($m['tax'])['amount'])
            ;
        } else {
            $f->price()
                ->total(null);
        }

        // Segments
        $xpath = "//tr[count(*[normalize-space()]) > 4][{$this->starts($this->t('Flight '))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*{$this->opt($this->t('Flight '))}\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*{$this->opt($this->t('Locator:'))}\s*(?<conf>[A-Z\d]{5,})\s*\n\s*(?<cabin>.+?)\s*\((?<code>[A-Z]{1,2})\)\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                    ->confirmation($m['conf'])
                ;

                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['code']);
            }

            // Departure
            unset($name, $code);
            $name = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*([A-Z]{3})\s*$/u", $name)) {
                $code = $name;
                $name = '';
            } elseif (preg_match("/^\s*(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $name, $m)) {
                $name = $m[1];
                $code = $m[2];
            }
            $s->departure()
                ->code($code)
                ->date($this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root)));

            if (!empty($name)) {
                $s->departure()
                    ->name($name);
            }

            // Arrival
            unset($name, $code);
            $name = $this->http->FindSingleNode("*[normalize-space()][last()-1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*([A-Z]{3})\s*$/", $name)) {
                $code = $name;
                $name = '';
            } elseif (preg_match("/^\s*(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $name, $m)) {
                $name = $m[1];
                $code = $m[2];
            }
            $s->arrival()
                ->code($code)
                ->date($this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][last()-1]/descendant::text()[normalize-space()][2]", $root)));

            if (!empty($name)) {
                $s->arrival()
                    ->name($name);
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("*[normalize-space()][last()]/descendant::text()[normalize-space()][1]", $root));
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
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            '¢æ' => 'EUR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    // additional methods

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
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Wed,21 Jun 23 09:30
            //            // Thu,23­Nov­2023 10:55
            '/^\s*[[:alpha:]\-]+,\s*(\d{1,2})\W?([[:alpha:]]+)\W?(\d{2})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

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
