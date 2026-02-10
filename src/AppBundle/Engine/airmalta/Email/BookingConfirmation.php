<?php

namespace AwardWallet\Engine\airmalta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "airmalta/it-115879435.eml, airmalta/it-153863004.eml, airmalta/it-154635415.eml";
    public $subjects = [
        'AirMalta booking confirmation',
    ];

    public $lang = '';

    public $detectLang = [
        'de' => ['Flüge'],
        'nl' => ['Vluchten'],
        'en' => ['Flight'],
    ];

    public static $dictionary = [
        "en" => [
            'Name'                                                                  => ['Name', 'NAME'],
            'For enquiries regarding your reservation, please contact Air Malta at' => [
                'For enquiries regarding your reservation, please contact Air Malta at',
                'Please review this booking confirmation carefully as it includes some important and helpful information about your next trip.',
            ],
        ],
        "de" => [
            'Flights'                                                               => 'Flüge',
            'For enquiries regarding your reservation, please contact Air Malta at' => 'Danke, dass Sie sich für Air Malta entschieden haben',
            'Extras'                                                                => 'Extras',
            'Document summary'                                                      => 'Zusammenfassung',
            'Booking reference'                                                     => 'Buchungsreferenz',
            'Amount paid'                                                           => 'Bezahlter Betrag',
            'Name'                                                                  => 'Name',
            //            'Terminal:'                                                             => '',
        ],
        "nl" => [
            'Flights'                                                               => 'Vluchten',
            'For enquiries regarding your reservation, please contact Air Malta at' => 'Bedankt dat u Air Malta hebt gekozen',
            'Extras'                                                                => 'Extras',
            'Document summary'                                                      => 'Document samenvatting',
            'Booking reference'                                                     => 'Boeking referentie',
            'Amount paid'                                                           => 'Bedrag betaald',
            'Name'                                                                  => 'Naam',
            //            'Terminal'                                                              => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airmalta.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains(['Air Malta', 'KM Malta Airlines'])}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('For enquiries regarding your reservation, please contact Air Malta at'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Extras'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Document summary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airmalta.com') !== false;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Booking reference'))}\s*([A-Z\d]{5,})/"))
            ->travellers(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Document summary'))}]/following::text()[{$this->eq($this->t('Name'))}]/ancestor::tr[1]/following-sibling::tr/td[{$this->contains($this->t('FLIGHT'))}]/preceding::td[1]")), true);

        $ticketNode = $this->http->XPath->query("//text()[{$this->eq($this->t('Document summary'))}]/following::text()[{$this->eq($this->t('Name'))}]/ancestor::tr[1]/following-sibling::tr/td[{$this->contains($this->t('FLIGHT'))}]");

        foreach ($ticketNode as $ticketRoot) {
            $pax = $this->http->FindSingleNode("./preceding::td[1]", $ticketRoot);
            $ticket = $this->http->FindSingleNode("./preceding::td[2]", $ticketRoot);

            if (!empty($ticket)) {
                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        // Price
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Document summary'))}]/following::text()[{$this->eq($this->t('Name'))}]/ancestor::tr[1]/td[{$this->starts($this->t('Amount paid'))}]",
            null, true, "/\(([A-Z]{3})\)\s*$/");

        if (!empty($currency)) {
            $total = 0.0;
            $totals = $this->http->FindNodes("//text()[{$this->eq($this->t('Document summary'))}]/following::text()[{$this->eq($this->t('Name'))}]/ancestor::tr[1]/following-sibling::tr/td[4]");

            foreach ($totals as $t) {
                $v = PriceHelper::parse($t, $currency);

                if (is_numeric($v)) {
                    $total += $v;
                } else {
                    $total = 0;

                    break;
                }
            }

            if (!empty($total)) {
                $f->price()
                    ->total($total)
                    ->currency($currency);
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Flights'))}]/ancestor::tr[1]/descendant::tr[normalize-space()]/descendant::td[5][contains(normalize-space(), ':')]")->length == 0) {
            $this->ParseSegment($f);
        } else {
            $this->ParseSegment2($f);
        }
    }

    public function ParseSegment(\AwardWallet\Schema\Parser\Common\Flight $f)
    {
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flights'))}]/ancestor::tr[1]/descendant::tr[normalize-space()]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][2]", $root);

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[5]/descendant::text()[normalize-space()][2]", $root, true, "/^([A-Z\d]{2})\s*\d{2,4}/"))
                ->number($this->http->FindSingleNode("./descendant::td[5]/descendant::text()[normalize-space()][2]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

            // Departure
            $depTime = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][2]", $root, true, "/([\d\:]+)/");
            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date($this->normalizeDate($date . ', ' . $depTime))
                ->terminal($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][3]", $root, true, "/{$this->opt($this->t('Terminal:'))}\s*(\w.*)/"), true, true)
            ;

            // Arrival
            $arrTime = $this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()][2]", $root, true, "/([\d\:]+)/");
            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date($this->normalizeDate($date . ', ' . $arrTime))
                ->terminal($this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()][3]", $root, true, "/{$this->opt($this->t('Terminal:'))}\s*(\w.*)/"), true, true)
            ;

            // Extra
            $s->extra()
                ->duration(trim($this->http->FindSingleNode("./descendant::td[5]/descendant::text()[normalize-space()][1]", $root), ','));

            $flightName = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][1]", $root);
            $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Extras'))}]/following::text()[normalize-space()='{$flightName}'][1]/following::table[1]/descendant::tr/td[normalize-space()][2]", null, "/^\s*(\d+[A-Z])\s*$/");

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }
        }
    }

    public function ParseSegment2(\AwardWallet\Schema\Parser\Common\Flight $f)
    {
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flights'))}]/ancestor::tr[1]/descendant::img/ancestor::tr[1]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flights'))}]/ancestor::tr[1]/descendant::text()[contains(., '---------')]/ancestor::tr[1]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), '20')][not(contains(normalize-space(), 'h '))][1]", $root);

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{2})\s*\d{2,4}/"))
                ->number($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

            // Departure
            $depTime = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[contains(normalize-space(), ':')][1]", $root, true, "/([\d\:]+)/");

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[1]/descendant::text()[contains(normalize-space(), '(')][1]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date($this->normalizeDate($date . ', ' . $depTime))
                ->terminal($this->http->FindSingleNode("./descendant::td[1]/descendant::text()[contains(normalize-space(), 'Terminal:')][1]", $root, true, "/{$this->opt($this->t('Terminal:'))}\s*(\w.*)/"), true, true)
            ;

            // Arrival
            $arrTime = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[contains(normalize-space(), ':')][1]", $root, true, "/([\d\:]+)/");
            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::td[3]/descendant::text()[contains(normalize-space(), '(')][1]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date($this->normalizeDate($date . ', ' . $arrTime))
                ->terminal($this->http->FindSingleNode("./descendant::td[3]/descendant::text()[contains(normalize-space(), 'Terminal:')][1]", $root, true, "/{$this->opt($this->t('Terminal:'))}\s*(\w.*)/"), true, true)
            ;

            // Extra
            $s->extra()
                ->duration(trim($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][last()]", $root), ','));

            $flightName = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), '20')][1]/ancestor::td[1]/descendant::text()[normalize-space()][1]", $root);
            $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Extras'))}]/following::text()[normalize-space()='{$flightName}'][1]/following::table[1]/descendant::tr/td[normalize-space()][3]", null, "/^\s*(\d+[A-Z])\s*$/");

            foreach ($seats as $seat) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Extras'))}]/following::text()[normalize-space()='{$flightName}'][1]/following::text()[{$this->eq($seat)}][1]/ancestor::tr[2]/descendant::text()[normalize-space()][1]");

                if (!empty($pax)) {
                    $s->extra()
                        ->seat($seat, true, true, $pax);
                } else {
                    $s->extra()
                        ->seat($seat);
                }
            }

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($this->t($word))}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        $in = [
            //Mi., 15 Juni 2022, 17:40
            "#^\D+\,\s*(\d+)\s*(\w+)[.]?\s*(\d{4})\,\s*([\d\:]+)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        $str = preg_replace($in, $out, $str);
//        $this->logger->debug($str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
