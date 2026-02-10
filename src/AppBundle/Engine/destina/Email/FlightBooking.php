<?php

namespace AwardWallet\Engine\destina\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "destina/it-620224745.eml, destina/it-621214427.eml, destina/it-622880866.eml, destina/it-633526347.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            "Booking PNR" => ['Booking PNR', 'Trip ID'],
            "Cabin Class" => 'Cabin Class',
        ],
    ];

    private $detectFrom = ['support@destinaholidays.com', 'support@airfareexperts.com', 'support@traveli.com'];
    private $detectSubject = [
        // en
        'flight booking PNR:',
        'flight booking with Trip ID:',
    ];
    private $detectBody = [
        'en' => [
            'Your trip confirmation and receipt',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:destinaholidays|airfareexperts|traveli)\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        foreach ($this->detectFrom as $dfrom) {
            if (stripos($headers["from"], $dfrom) !== false) {
                $detectedFrom = true;

                break;
            }
        }

        if ($detectedFrom === false
            && strpos($headers["subject"], 'Destina Holidays') === false
            && strpos($headers["subject"], 'AirfareExperts') === false
            && strpos($headers["subject"], 'Traveli') === false
        ) {
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
            $this->http->XPath->query("//a[{$this->contains(['destinaholidays.com', 'airfareexperts.com', '/traveli.com', '.traveli.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['destinaholidays.com', 'airfareexperts.com', 'traveli.com'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Booking PNR"], $dict["Cabin Class"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking PNR'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Cabin Class'])}]")->length > 0
                ) {
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking PNR'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"));

        // Flight

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Name'))}]/ancestor::tr[1]/following-sibling::*[not({$this->starts($this->t('Infant'))})]/*[2]"), true)
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booked on:'))}]/following::text()[normalize-space()][1]")), true)
        ;
        $infants = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Name'))}]/ancestor::tr[1]/following-sibling::*[{$this->starts($this->t('Infant'))}]/*[2]");

        if (!empty($infants)) {
            $f->general()
                ->infants($infants, true);
        }

        // Segments
        $xpath = "//text()[{$this->eq($this->t('Cabin Class'))}]/ancestor::*[contains(normalize-space(), '#')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<al>.+?)\s*#\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            } elseif (preg_match("/^\s*#\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->noName()
                    ->number($m['fn']);
            }
            $operator = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Operated by'))}]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $re = "/^\s*(?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+(?<date>.+)\n\s*(?<code>[A-Z]{3})\s*\n\s*(?<name>.+)(?<terminal>\n.*terminal.*)\s*$/ui";

            // Departure
            $node = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()][not({$this->starts($this->t('Operated by'))})][2]/ancestor::tr[1]//text()[normalize-space()]", $root));

            if (preg_match($re, $node, $m)) {
                $s->departure()
                    ->name(trim($m['name']))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->terminal(trim(preg_replace('/\s*\bTerminal\b\s*/i', '', $m['terminal'] ?? '')), true, true)
                ;
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()][not({$this->starts($this->t('Operated by'))})][2]/ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::tr[1]//text()[normalize-space()]", $root));

            if (preg_match($re, $node, $m)) {
                $s->arrival()
                    ->name(trim($m['name']))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->terminal(trim(preg_replace('/\s*\bTerminal\b\s*/i', '', $m['terminal'] ?? '')), true, true)
                ;
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Cabin Class'))}]/ancestor::tr[1]", $root, null, "/{$this->opt($this->t('Cabin Class'))}\s*(.+)/"))
            ;
        }

        // Price
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('All fares are quoted in'))}]", null, true, "/{$this->opt($this->t('All fares are quoted in'))}\s*([A-Z]{3})\s*$/");
        $f->price()
            ->total(PriceHelper::parse($this->http->FindSingleNode("//td[{$this->eq($this->t('Total Charge'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^\s*\D*(\d.*?)\D*\s*$/"), $currency))
            ->tax(PriceHelper::parse($this->http->FindSingleNode("//td[{$this->eq($this->t('Taxes & Fee'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^\s*\D*(\d.*?)\D*\s*$/"), $currency))
            ->currency($currency);

        $cost = 0.0;
        $costRow = $this->http->XPath->query("//tr[not(.//tr)][count(*[normalize-space()]) = 2][preceding::text()[{$this->eq($this->t('Flight Price Details'))}] and following::text()[{$this->eq($this->t('Taxes & Fee'))}]]");

        foreach ($costRow as $row) {
            if (!empty($this->http->FindSingleNode("*[normalize-space()][1]", $row, true, "/^\s*\d+ [[:alpha:]]+\s*$/u"))) {
                $cost += PriceHelper::parse($this->http->FindSingleNode("*[normalize-space()][2]", $row, true, "/^\s*\D*(\d.*?)\D*\s*$/"), $currency);
            } else {
                $cost = null;

                break;
            }
        }

        if (!empty($cost)) {
            $f->price()
                ->cost($cost);
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
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*\([[:alpha:]]+\)\s*(?:,|\bat\b)\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
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
