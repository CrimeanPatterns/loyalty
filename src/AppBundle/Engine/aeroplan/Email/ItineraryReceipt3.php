<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryReceipt3 extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-619354276.eml, aeroplan/it-622316723.eml, aeroplan/it-636102963.eml";
    public $subjects = [
        '/^Air Canada\s*\-\s*.*\s+\-\s*Itinerary\-Receipt$/',
    ];

    public $lang = '';
    public $lastDate = '';

    public $detectLang = [
        "en" => ['Departure'],
        "fr" => ['Départ', 'Vol'],
    ];

    public $points = [];

    public static $dictionary = [
        "en" => [
            "Operated by" => ["Operated by", "Exploité par"],
        ],
        "fr" => [
            "Booking Confirmation"              => ["Confirmation de réservation", "Confirmation de la réservation"],
            "Thank you for choosing Air Canada" => "Merci d’avoir choisi Air Canada",
            'Passengers'                        => 'Passagers',
            "Ticket #:"                         => "Billet #:",
            'Aeroplan #:'                       => 'Aéroplan #:',
            'Seats'                             => 'Sièges',
            "Flights"                           => "Vols",
            "Flight"                            => "Vol",
            'Return'                            => 'Retour',
            "Departure"                         => "Départ",
            "Terminal"                          => "Aérogare",
            "Operated by"                       => "Exploité par",
            'Duration:'                         => "Durée:",
            'Aircraft:'                         => ['Type d\'appareil:', 'Aircraft:'],
            'Cabin:'                            => ['Cabine:', 'Cabin:'],
            'Meal:'                             => ['Repas:', 'Meal:'],
            "Purchase Summary"                  => ["Sommaire de l'achat", "Purchase Summary"],
            'Taxes, Fees and Charges'           => 'Taxes, frais et droits',
            "Grand total"                       => "Total général",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aircanada.com') !== false) {
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
        $this->detectLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing Air Canada'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flights'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Purchase Summary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aircanada\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Confirmation'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Ticket #:'))}]/preceding::text()[normalize-space()][1]"), true);

        $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket #:'))}]", null, "/^{$this->opt($this->t('Ticket #:'))}\s*(\d+)/");

        if (count($tickets) > 0) {
            foreach ($tickets as $ticket) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::text()[{$this->contains($ticket)}]/preceding::text()[normalize-space()][1]");

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        $accounts = $this->http->FindNodes("//text()[{$this->starts($this->t('Aeroplan #:'))}]", null, "/^{$this->opt($this->t('Aeroplan #:'))}\s*([A-Z\d]+)/");

        if (count($accounts) > 0) {
            foreach ($accounts as $account) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::text()[{$this->contains($account)}]/preceding::text()[normalize-space()][not({$this->contains($tickets)})][1]");
                $name = $this->http->FindSingleNode("(//text()[{$this->contains($account)}])[1]", null, true, "/^({$this->opt($this->t('Aeroplan #:'))})\s*[A-Z\d]+/");

                if (!empty($name)) {
                    $name = trim($name, ':');
                }
                $f->addAccountNumber($account, false, $pax, $name);
            }
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Grand total'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*\D*(?<total>[\d\.\,\s]+)\s*$/", $price, $m)
            || preg_match("/^(?<total>[\d\.\,\s]+)\s*\D*\s*(?<currency>[A-Z]{3})$/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Taxes, Fees and Charges'))}]/ancestor::tr[1]/following-sibling::tr[not({$this->contains($this->t('Grand total'))})][normalize-space()]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $feeRoot);
                $feeSumm = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $feeRoot, true, "/([\d\.\,]+)/");

                if (!empty($feeName) && !empty($feeSumm)) {
                    $f->price()
                        ->fee($feeName, PriceHelper::parse($feeSumm, $m['currency']));
                }
            }
        }

        $nodes = $this->http->XPath->query("//tr[starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'dd:dd')][following::text()[{$this->eq($this->t('Purchase Summary'))}]]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $depPoint = $this->http->FindSingleNode("./preceding::tr[1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<depName>.+)\s+(?<depCode>[A-Z]{3})\s*$/", $depPoint, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->name($m['depName']);

                $depTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/");

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal($depTerminal);
                }
            }

            $arrPoint = $this->http->FindSingleNode("./preceding::tr[1]/descendant::text()[normalize-space()][2]", $root);

            if (preg_match("/^(?<arrName>.+)\s+(?<arrCode>[A-Z]{3})\s*$/", $arrPoint, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->name($m['arrName']);

                $arrTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/");

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }
            }

            $depDate = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Return'))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Return'))}[\s\•]*\s+(.+\d{4})/u");

            if (empty($depDate)) {
                $depDate = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Departure'))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Departure'))}[\s\•]*\s+(.+\d{4})/u");
            }

            if (empty($depDate)) {
                $depDate = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Flight'))}][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Flight'))}\s*\d+\s*[\s\•]*\s+(.+\d{4})/u");
            }

            $depTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);
            $arrTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]/ancestor::td[1]", $root);

            $depDate = $this->normalizeDate($depDate);

            if (!empty($depTime) && !empty($depDate)) {
                $s->departure()
                    ->date(strtotime($depTime, $depDate < $this->lastDate ? $this->lastDate : $depDate));
            }

            if (preg_match("/^([\d\:]+)\s*([+\-]\d)\s*[[:alpha:]]+/u", $arrTime, $m)) {
                $arrTime = $m[1];
                $depDate = strtotime($m[2] . ' day', $depDate);
            }

            if (!empty($arrTime) && !empty($depDate)) {
                $s->arrival()
                    ->date(strtotime($arrTime, $depDate));
            }

            $this->lastDate = $depDate;

            $airInfo = implode("\n", $this->http->FindNodes("./following::tr[3][{$this->contains($this->t('Cabin'))}]/descendant::text()[normalize-space()]", $root));
            $this->logger->debug('$airInfo = ' . print_r($airInfo, true));

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})[\s\•]+{$this->opt($this->t('Operated by'))}\s*(?<operator>.+)\n/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber'])
                    ->operator($m['operator']);
            }

            $aircraft = $this->re("/{$this->opt($this->t('Aircraft:'))}\n*(.+)/", $airInfo);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $cabin = $this->re("/{$this->opt($this->t('Cabin:'))}\n*(.+)/", $airInfo);

            if (preg_match("/\s*(?<cabin>.+)?\((?<bookingCode>[A-Z])\)/", $cabin, $m)) {
                if (isset($m['cabin']) && !empty($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }

                $s->extra()
                    ->bookingCode($m['bookingCode']);
            }

            $meal = $this->re("/{$this->opt($this->t('Meal:'))}\s+(.+)/", $airInfo);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $duration = $this->re("/{$this->opt($this->t('Duration:'))}\s+(.+)/", $airInfo);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seatsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Seats'))}]/ancestor::tr[1]/following-sibling::tr[{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}]/descendant::td[2]",
                    null, "/^\s*(\d+[A-Z])\s*$/");

                foreach ($seatsNodes as $sRoot) {
                    $seat = $this->http->FindSingleNode(".", $sRoot, true, "/^\s*(\d+[A-Z])\s*$/");
                    $name = $this->http->FindSingleNode("preceding::text()[{$this->eq(array_column($f->getTravellers(), 0))}][1]", $sRoot);

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat, true, true, $name);
                    }
                }
            }

            if (in_array($s->getDepCode() . $s->getAirlineName() . $s->getFlightNumber(), $this->points) === true) {
                $f->removeSegment($s);
            } else {
                $this->points[] = $s->getDepCode() . $s->getAirlineName() . $s->getFlightNumber();
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\.?\s*(\d+)\s*(\w+)\.?\,\s*(\d{4})$#u", //Fri 08 Dec, 2023
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
