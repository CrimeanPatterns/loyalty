<?php

namespace AwardWallet\Engine\aerlingus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "aerlingus/it-581385256.eml, aerlingus/it-636277945.eml, aerlingus/it-672347189.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Aer Lingus Confirmation - Booking Ref:') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".aerlingus.com/") or contains(@href,"www.aerlingus.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"CONTACT AER LINGUS")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Manage My Trip'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Please check your details below'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aerlingus\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Flight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*?[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Passenger']/ancestor::tr[1]/following-sibling::tr/descendant::td[normalize-space()][1]", null, "/^({$patterns['travellerName']})(?:\s*AerClub|\s*Special Assistance|$)/iu");
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Reference:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Reference:'))}[:\s]*([A-Z\d]{5,8})$/"))
            ->travellers($travellers, true);

        $accounts = array_filter($this->http->FindNodes("//text()[normalize-space()='Passenger']/ancestor::tr[1]/following-sibling::tr/descendant::td[normalize-space()][1]", null, "/\:\s+(\d+)/"));

        if (count($accounts) > 0) {
            $f->setAccountNumbers(array_unique($accounts), false);
        }

        $tickets = [];
        $ticketsText = implode(' ', $this->http->FindNodes("//text()[ normalize-space() and preceding::text()[normalize-space()][normalize-space()='Ticket number(s)'] and following::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Tickets']] ]"));
        $ticketValues = preg_split('/(?:\s*[,;]\s*)+/i', $ticketsText);

        foreach ($ticketValues as $tValue) {
            if (preg_match("/^{$patterns['eTicket']}$/", $tValue)) {
                $tickets[] = $tValue;
            } elseif (preg_match("/^\s*({$patterns['eTicket']})\s+to\s+({$patterns['eTicket']})\s*$/", $tValue, $m) && (int) $m[2] - (int) $m[1] < 20
            ) {
                $leadingZero = '';

                if (preg_match("/^(0+)\d+/", $m[1], $mat)) {
                    $leadingZero = $mat[1];
                }

                for ($i = (int) $m[1]; $i <= (int) $m[2]; $i++) {
                    $tickets[] = $leadingZero . $i;
                }
                $tickets = array_filter($tickets);
            } else {
                $this->logger->debug('Found wrong ticket number!');

                $tickets = [];

                break;
            }
        }

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)\s*$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Tickets']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D{1,3}\s*(\d[\d\.\,]*?)\s*$/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes, charges and carrier imposed fees']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D{1,3}\s*(\d[\d\.\,]*?)\s*$/");

            if ($tax !== null) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }

            $extras = $this->http->FindSingleNode("//text()[normalize-space()='Extras']/ancestor::tr[1]/descendant::td[last()]", null, true, "/^\s*\D{1,3}\s*(\d[\d\.\,]*?)\s*$/");

            if (!empty($extras)) {
                $f->price()
                    ->fee('Extras', PriceHelper::parse($extras, $currency));
            }
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'arrow-head')]/ancestor::table[normalize-space()][2]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $operator = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Operated by')][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $textForSeats = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'to')][1]/ancestor::tr[1]", $root);

            if (!empty($textForSeats)) {
                foreach ($travellers as $traveller) {
                    $seatInfo = $this->http->FindSingleNode("//text()[normalize-space()='Seats'][1]/ancestor::tr[1]/following::text()[{$this->eq($traveller)}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($textForSeats)}]/descendant::td[last()]");

                    if (preg_match("/^(?<seat>\d+[A-Z])\s*(?<cabin>\D+)/", $seatInfo, $m)) {
                        $s->extra()
                            ->seat($m['seat'], false, false, $traveller)
                            ->cabin($m['cabin']);
                    }
                }
            }

            if (empty($s->getCabin())) {
                $cabin = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Fare Type:')][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Fare Type:'))}\s*[A-Z]\/(\w+)/");

                if (!empty($cabin)) {
                    $s->setCabin($cabin);
                }
            }

            $bookingCode = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Fare Type:')][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Fare Type:'))}\s*([A-Z])\//");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $duration = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Flight Duration:')][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Flight Duration:'))}\s*([\d\shm]+)/");

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $status = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Flight Status:')][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Flight Status:'))}\s*(\w+)/us");

            if (!empty($status)) {
                $s->setStatus($status);
            }

            $depDate = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/preceding::tr[2]/descendant::td[normalize-space()][1]", $root);
            $nextDay = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/preceding::tr[1]/descendant::td[normalize-space()][1]", $root, true, "/({$this->opt('Arrives next day')})/");

            $depTime = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/descendant::td[1]", $root, true, "/^{$patterns['time']}$/");
            $arrTime = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/descendant::td[last()]", $root, true, "/^{$patterns['time']}$/");

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root))
                ->date(strtotime($depDate . ', ' . $depTime));

            if (!empty($nextDay)) {
                $s->arrival()
                    ->date(strtotime('+1 day', strtotime($depDate . ', ' . $arrTime)));
            } else {
                $s->arrival()
                    ->date(strtotime($depDate . ', ' . $arrTime));
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root));
        }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
            'CAD' => ['CA$'],
            'AUD' => ['A$'],
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
