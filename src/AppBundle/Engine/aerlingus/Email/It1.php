<?php

namespace AwardWallet\Engine\aerlingus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1 extends \TAccountChecker
{
    public $mailFiles = "aerlingus/it-1.eml, aerlingus/it-182796482.eml, aerlingus/it-183254468.eml, aerlingus/it-1AerLingus.eml, aerlingus/it-2482500.eml, aerlingus/it-5217173.eml, aerlingus/it-5225084.eml, aerlingus/it-5262795.eml, aerlingus/it-6024749.eml, aerlingus/it-6024758.eml, aerlingus/it-6138820.eml, aerlingus/it-6567512.eml, aerlingus/it-6567525.eml, aerlingus/it-96416574.eml";

    public $subjects = [
        '#Aer Lingus Confirmation#i',
        '#Aer Lingus Travel Advisory#i',
        '#Aer Lingus Schedule Change Notification#i',
        '#Aer\s+Lingus\s+AerMail\s+-\s+Booking Ref#i',
        '#Aer Lingus Select Seats - Booking Ref#i',
        '/Aer\s+Lingus\s+Deposit\s+Confirmation\s+-\s+Booking\s+Ref/i',
    ];

    public $lang = 'en';
    public $textEmail;

    public static $dictionary = [
        "en" => [
            'Departure:'                 => ['Departure:', 'Departs:'],
            'Arrival:'                   => ['Arrives:', 'Arrival:'],
            'Status/Class:'              => ['Status/Class:', 'Status:'],
            'Booking reference:'         => ['Booking reference:', 'Booking Reference:'],
            'Booking confirmation date:' => ['Booking confirmation date:', 'Date:'],
            'Operated by:'               => ['Operated by:', 'Operated By:', 'Airline:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aerlingus.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href,'aerlingus.com')] | //img[contains(@src,'aerlingus.com')] | //text()[contains(normalize-space(),'Thank you for booking your flight with Aer Lingus') or contains(normalize-space(),'Thank you for your group booking with Aer Lingus')]")->length === 0) {
            return false;
        }

        return $this->http->XPath->query("//text()[starts-with(normalize-space(),'Flight')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aerlingus.com$/', $from) > 0;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confs = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Booking reference:'))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{6})/")));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $dateRes = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference:'))}]/following::text()[{$this->starts($this->t('Booking confirmation date:'))}][1]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Booking confirmation date:'))}\s*(.+)/");

        if (!empty($dateRes)) {
            $f->general()
                ->date(strtotime($dateRes));
        }

        $pattern = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]'; // Mr. Hao-Li Huang
        $pax = array_filter($this->http->FindNodes("//text()[normalize-space()='Passenger(s)']/following::text()[normalize-space()][position()<15][not(normalize-space()='Flight')][not(preceding::text()[normalize-space()='Flight'])]", null, "/^{$pattern}$/u"));

        if (count($pax) === 0) {
            $pax = array_filter(array_map('trim', explode(",", clear("#\n#", re("#\n\s*Passenger\(s\)\s+(.*?)\s+Flight#is"), ', '))),
                function ($item) use ($pattern) {
                    return preg_match("/^{$pattern}$/u", $item) > 0;
                }
            );
        }

        $f->general()
            ->travellers($pax, true);

        $accounts = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Frequent Flyer No:')]/ancestor::tr[1]", null, "/{$this->opt($this->t('Frequent Flyer No:'))}\s*([A-Z\-\d]{5,})$/"));

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $tickets = array_filter($this->http->FindNodes("//text()[normalize-space()='Ticket Numbers']/ancestor::tr[1]/following::tr[1]/td[1]", null, "/^([\d\-]{12,})$/"));

        if (count($tickets) > 0) {
            $ticketArray = [];

            foreach ($tickets as $ticket) {
                $ticketArray = array_merge($ticketArray, explode('-', $ticket));
            }
            $f->setTicketNumbers(array_unique($ticketArray), false);
        }

        $result = [];

        $paymentRoots = $this->http->XPath->query("//tr[ *[2][normalize-space()='Fare p.p.'] and *[3][normalize-space()='Taxes & Charges' or normalize-space()='Taxes, Charges and Carrier Imposed Fees' or normalize-space()='Taxes, Charges and Carrier Imposed fees']  ]");
        $paymentRoot = $paymentRoots->length > 0 ? $paymentRoots->item(0) : null;

        $totalPrice = $this->http->FindSingleNode("following::tr[ count(*)=2 and *[1][normalize-space()='TOTAL'] ]/*[2]", $paymentRoot, true, "/^.*\d.*$/");

        if ($totalPrice === null) {
            $totalPrice = $this->http->FindSingleNode("following::tr[ count(*)=2 and *[1][normalize-space()='Total Amount'] ]/*[2]", $paymentRoot, true, "/^.*\d.*$/");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
        || preg_match("/^(?<amount>\d[,.\'\d ]*)$/", $totalPrice, $matches)) {
            // it-96416574.eml
            // USD 1222.23
            if (isset($matches['currency']) && !empty($matches['currency'])) {
                $result['Currency'] = $matches['currency'];
            } else {
                $result['Currency'] = $this->http->FindSingleNode("//text()[normalize-space()='Total Amount']/preceding::tr[1]/descendant::td[2]", null, true, "/^([A-Z]{3})\s/");
            }

            $result['TotalCharge'] = PriceHelper::parse($matches['amount'], $result['Currency']);

            $feeRows = $this->http->XPath->query("./following::tr[ count(*)=4 and following::text()[normalize-space()='TOTAL'] and preceding::text()[normalize-space()='Total Amount']]", $paymentRoot);

            $discount = 0.0;

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('./*[4]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($result['Currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('./*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $f->price()
                        ->fee($feeName, PriceHelper::parse($m['amount'], $matches['currency']));
                } elseif (preg_match('/^(?:' . preg_quote($result['Currency'], '/') . ')?[ ]*-(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $discount += PriceHelper::parse($m['amount'], $matches['currency']);
                }
            }

            if (!empty($discount)) {
                $f->price()
                    ->discount($discount);
            }

            $result['BaseFare'] = 0.0;

            $fareTaxes = 0.0;

            for ($i = 1; $i < 7; $i++) {
                $paxCount = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][{$i}]/*[1]", $paymentRoot, true, "/^(\d{1,2})\s*[[:alpha:]]{3,}\D*:\s*$/i");

                if (empty($paxCount)) {
                    break;
                }
                $fare = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][{$i}]/*[2]", $paymentRoot, true, "/^.*\d.*$/");

                if (preg_match('/^(?:' . preg_quote($result['Currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $fare, $m)
                ) {
                    $result['BaseFare'] += PriceHelper::parse($m['amount'], $matches['currency']) * $paxCount;
                }
                $taxes = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][$i]/*[3]", $paymentRoot, true, "/^.*\d.*$/");

                if (preg_match('/^(?:' . preg_quote($result['Currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $taxes, $m)
                ) {
                    $fareTaxes += PriceHelper::parse($m['amount'], $matches['currency']) * $paxCount;
                }
            }

            if (!empty($fareTaxes)) {
                $feeName = $this->http->FindSingleNode("*[3]", $paymentRoot);
                $f->price()
                    ->fee($feeName, $fareTaxes);
            }
        }

        if (!empty($result['TotalCharge']) && !empty($result['Currency'])) {
            $f->price()
                ->total($result['TotalCharge'])
                ->currency($result['Currency']);
        }

        if (!empty($result['Tax'])) {
            $f->price()
                ->tax($result['Tax']);
        }

        if (!empty($result['BaseFare'])) {
            $f->price()
                ->cost($result['BaseFare']);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Flight']");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/\-\s+(.+)/");

            $s->airline()
                ->name($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])/"))
                ->number($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/"));

            $depInfo = str_replace(['Â', ';-;'], ['', '-'], $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Departure:'))}][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root));

            if (
                preg_match("/^(?<name>.+)(\-.*Terminal\s*)(?<terminal>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/", $depInfo, $m) //Newark New Jersey-Terminal B (EWR) 15:50
                || preg_match("/^(?<name>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/", $depInfo, $m) //Newark New Jersey (EWR) 18:00
            ) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($date . ', ' . $m['time']));

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            $arrInfo = str_replace(['Â', ';-;'], ['', '-'], $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Arrival:'))}][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root));

            if (
                preg_match("/^(?<name>.+)(\-.*Terminal\s*)(?<terminal>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/u", $arrInfo, $m) //Newark New Jersey-Terminal B (EWR) 15:50
                || preg_match("/^(?<name>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/u", $arrInfo, $m) //Newark New Jersey (EWR) 18:00
            ) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($date . ', ' . $m['time']));

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }

                $operator = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[4]/descendant::text()[{$this->eq($this->t('Operated by:'))}]/ancestor::tr[1]/descendant::td[2]", $root);

                if (!empty($operator)) {
                    $s->airline()
                        ->operator(str_replace("Operated By", "", $operator));
                }
            }

            $cabinInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[3]/descendant::text()[{$this->eq($this->t('Status/Class:'))}]/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/^(?<bookingCode>[A-Z]{1,2})\/(?<cabin>.+) Class\s*(?<status>\w+)$/", $cabinInfo, $m)) {
                $s->extra()
                    ->bookingCode($m['bookingCode'])
                    ->cabin($m['cabin'])
                    ->status($m['status']);
            }

            $seats = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[5]/descendant::text()[{$this->eq($this->t('Seats:'))}][1]/ancestor::tr[1]/descendant::td[2][not(contains(normalize-space(), 'seat') or contains(normalize-space(), 'Seat'))]", $root);

            if (!empty($seats) && preg_match_all("/(\d+[A-Z])/", $seats, $m)) {
                $s->extra()
                    ->seats($m[1]);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->textEmail = $parser->getPlainBody();
        $this->textEmail = str_replace(['Â', ';-;'], ['', '-'], $this->textEmail);

        $this->parseFlight($email);

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
