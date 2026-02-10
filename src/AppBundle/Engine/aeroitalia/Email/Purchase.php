<?php

namespace AwardWallet\Engine\aeroitalia\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Purchase extends \TAccountChecker
{
    public $mailFiles = "aeroitalia/it-672289948.eml, aeroitalia/it-674582392.eml, aeroitalia/it-681132147.eml, aeroitalia/it-711371834.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking Code' => 'Booking Code',
            // 'Total cost:' => 'Total cost:',
            // 'Including VAT:' => 'Including VAT:',
            // 'Operated by' => 'Operated by',
            // 'ARR' => 'ARR',
            // 'FLIGHT:' => 'FLIGHT:',
            // 'Passenger' => 'Passenger',
            // 'Seat' => 'Seat',
        ],
        'it' => [
            'Booking Code'   => 'Codice di prenotazione',
            'Total cost:'    => 'Totale Importo:',
            'Including VAT:' => 'di cui IVA:',
            // 'Operated by' => 'Operated by',
            'DEP'            => 'PARTENZA',
            'ARR'            => 'ARRIVO',
            'FLIGHT:'        => 'VOLO:',
            'Passenger'      => 'Passeggero',
            'Seat'           => 'Posto',
        ],
    ];

    private $detectFrom = "noreply@aeroitalia.com";
    private $detectSubject = [
        'Aeroitalia Purchase',
    ];
    private $detectBody = [
        'en' => [
            'Your flight booking',
        ],
        'it' => [
            'La tua prenotazione',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]aeroitalia\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // TODO choose case

        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Aeroitalia') === false
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['aeroitalia.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['www.aeroitalia.com', 'choosing Aeroitalia!'])}]")->length === 0
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
            if (!empty($dict["Booking Code"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking Code'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Booking Code'))}]/following-sibling::tr[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
        ;
        $travellers = [];
        $seats = [];
        $flights = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('FLIGHT:'))}]/following::text()[normalize-space()][1]"));
        $travTableXpath = "//tr[*[1][{$this->eq($this->t('Passenger'))}] and *[4][{$this->eq($this->t('Seat'))}]]/ancestor-or-self::tr[{$this->starts($this->t('Passenger'))}][following-sibling::*]";

        foreach ($this->http->XPath->query($travTableXpath) as $travRoot) {
            foreach ($this->http->XPath->query("following-sibling::*[normalize-space()][position() < 20]", $travRoot) as $tRoot) {
                if ($this->http->XPath->query(".//*[{$this->starts($flights)}]", $tRoot)->length === 0) {
                    break;
                } else {
                    $traveller = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $tRoot, null, "/^\s*(?:(?:Mr|Ms|Mrs|Miss|Mstr)[\s\.]\s*)?(.+)/i");
                    $travellers[] = $traveller;

                    $seatText = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()][4]//text()[normalize-space()]", $tRoot));

                    if (preg_match_all("/\n([A-Z\d]{2}\d{1,5})\s*:\s*(\d{1,3}[A-Z])(?=\n)/", "\n" . $seatText . "\n", $m)) {
                        foreach ($m[0] as $i => $v) {
                            $seats[$m[1][$i]][] = ['seat' => $m[2][$i], 'traveller' => $traveller];
                        }
                    }
                }
            }
        }
        $f->general()
            ->travellers(array_unique($travellers));

        // Price
        $total = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Total cost:'))}]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
        ) {
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        } else {
            $f->price()
                ->total(null);
        }
        $tax = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Including VAT:'))}]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $tax, $m)
        ) {
            $f->price()
                ->tax(PriceHelper::parse($m['amount'], $m['currency']))
            ;
        } else {
            $f->price()
                ->total(null);
        }

        // Segments
        $xpath = "//text()[{$this->eq($this->t('DEP'))}]/ancestor::tr[.//text()[{$this->eq($this->t('ARR'))}]][.//text()[{$this->eq($this->t('FLIGHT:'))}]][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('FLIGHT:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if (isset($seats[trim($node)])) {
                    foreach ($seats[trim($node)] as $value) {
                        $s->extra()
                            ->seat($value['seat'], false, false, $value['traveller']);
                    }
                }
            }
            $operator = $this->http->FindSingleNode("preceding::tr[normalize-space()][1][{$this->starts($this->t('Operated by'))}]", $root, true,
                "/{$this->opt($this->t('Operated by'))}\s*(.+)\s*$/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('FLIGHT:'))}]/preceding::text()[normalize-space()][1]", $root));
            // Departure
            $time = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('DEP'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            $code = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('DEP'))}]/following::text()[normalize-space()][2]", $root, true, "/^\s*([A-Z]{3})\s*$/");

            if (!empty($code)) {
                $s->departure()
                    ->code($code);

                $name = $this->http->FindSingleNode("preceding::tr[normalize-space()][not({$this->starts($this->t('Operated by'))})][1]", $root, true,
                    "/^\s*(.+?)\s*\({$code}\)\s*-\s*/");

                if (preg_match("/^\s*(.+?) T(\d)\s*$/", $name, $m)) {
                    $name = $m[1];
                    $s->departure()
                        ->terminal($m[2]);
                }

                if (!empty($name)) {
                    $s->departure()
                        ->name($name);
                }
            }

            // Arrival
            $time = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('ARR'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            $code = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('ARR'))}]/following::text()[normalize-space()][2]", $root, true, "/^\s*([A-Z]{3})\s*$/");

            if (!empty($code)) {
                $s->arrival()
                    ->code($code);

                $name = $this->http->FindSingleNode("preceding::tr[normalize-space()][not({$this->starts($this->t('Operated by'))})][1]", $root, true,
                    "/^\s*.+\s*\([A-Z]{3}\)\s*-\s*(.+?)\s*\({$code}\)\s*$/");

                if (preg_match("/^\s*(.+?) T(\d)\s*$/", $name, $m)) {
                    $name = $m[1];
                    $s->arrival()
                        ->terminal($m[2]);
                }

                if (!empty($name)) {
                    $s->arrival()
                        ->name($name);
                }
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t('ARR'))}]/preceding::text()[normalize-space()][1]",
                    $root, true, "/^\s*(\d+\s*h\s*\d+\s*(min)?)\s*$/"));
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
        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        // $date = preg_replace($in, $out, $date);

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
