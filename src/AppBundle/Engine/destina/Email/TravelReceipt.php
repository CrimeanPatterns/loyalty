<?php

namespace AwardWallet\Engine\destina\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelReceipt extends \TAccountChecker
{
    public $mailFiles = "destina/it-621214428.eml, destina/it-624221648.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Flight Number:'    => 'Flight Number:',
            'Class of service:' => 'Class of service:',
        ],
    ];

    private $detectFrom = ['support@destinaholidays.com', 'support@airfareexperts.com', 'support@traveli.com'];
    private $detectSubject = [
        // en
        'Your travel receipt and confirmation for booking',
    ];
    private $detectBody = [
        'en' => [
            'Flight Information',
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
            if (isset($dict["Flight Number:"], $dict["Class of service:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Flight Number:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Class of service:'])}]")->length > 0
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
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your trip pnr is'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (!empty($conf) && $this->http->XPath->query("//text()[{$this->eq($this->t('Airline PNR:'))}]/following::text()[{$this->starts($conf)}]")->length > 0) {
            $email->obtainTravelAgency();
        } else {
            $email->ota()
                ->confirmation($conf);
        }

        // Flight

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Travellers'))}]/ancestor::*[not({$this->eq($this->t('Travellers'))})][1][{$this->starts($this->t('Travellers'))}]//text()[normalize-space()][not({$this->eq($this->t('Travellers'))})]"))
        ;

        // Issued
        $tickets = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Here\'s your status of electronic ticket number(s):'))}]/ancestor::*[not({$this->eq($this->t('Here\'s your status of electronic ticket number(s):'))})][1]",
            null, true, "/{$this->opt($this->t('Here\'s your status of electronic ticket number(s):'))}\s*([ \d\-,]+)(?:---.+)?\s*$/");
        $f->issued()
            ->tickets(preg_split('/\s*,\s*/', trim($tickets)), false);

        // Segments
        $xpath = "//text()[{$this->eq($this->t('Depart:'))}]/ancestor::*[{$this->contains($this->t('Arrive:'))}][{$this->starts($this->t('Depart:'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("preceding::text()[normalize-space()][2]", $root);

            if (preg_match("/^\s*.+ (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }
            $s->airline()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Airline PNR:'))}]/ancestor::tr[1]", $root, null, "/{$this->opt($this->t('Airline PNR:'))}\s*([A-Z\d]{5,7})\s*$/"));

            $re = "/^\s*\w+:\s*(?<name>.+)\((?<code>[A-Z]{3})\),\s*(?<date>[^,]*\d{4}[^,]*\b\d{1,2}:\d{2})hrs(?<terminal>.+)?\s*$/u";

            // Departure
            $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Depart:'))}]/ancestor::tr[1]", $root);

            if (preg_match($re, $node, $m)) {
                $s->departure()
                    ->name(trim($m['name']))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(preg_replace('/^\s*Terminal-\s*/', '', $m['terminal'] ?? ''), true, true)
                ;
            }

            // Arrival
            $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Arrive:'))}]/ancestor::tr[1]", $root);

            if (preg_match($re, $node, $m)) {
                $s->arrival()
                    ->name(trim($m['name']))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(preg_replace('/^\s*Terminal-\s*/', '', $m['terminal'] ?? ''), true, true)
                ;
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Duration:'))}]/ancestor::tr[1]", $root, null, "/{$this->opt($this->t('Duration:'))}\s*(.+)/"))
                ->aircraft($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Aircraft Type:'))}]/ancestor::tr[1]", $root, null, "/{$this->opt($this->t('Aircraft Type:'))}\s*(.+)/"))
                ->bookingCode($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Class of service:'))}]/ancestor::tr[1]", $root, null, "/{$this->opt($this->t('Class of service:'))}\s*(.+)/"))
                ->status($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Flight Status'))}]/ancestor::tr[1]", $root, null, "/{$this->opt($this->t('Flight Status'))}\s*(.+)/"))
            ;
        }

        // Price
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL CHARGE'))}]/following::text()[normalize-space()][2]", null, true, "/^\s*([A-Z]{3})\s*$/");
        $f->price()
            ->total(PriceHelper::parse($this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL CHARGE'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*\D*(\d.*?)\D*\s*$/"), $currency))
            ->currency($currency);

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
            // 4-Jan-2024 (Thursday) at 08:45
            '/^\s*(\d{1,2})-([[:alpha:]]+)-(\d{4})\s*\([[:alpha:]]+\)\s*at\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
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
