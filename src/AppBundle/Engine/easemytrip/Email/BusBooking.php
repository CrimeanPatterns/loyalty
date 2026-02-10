<?php

namespace AwardWallet\Engine\easemytrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BusBooking extends \TAccountChecker
{
    public $mailFiles = "easemytrip/it-224738310.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'ticketNo'     => ['TICKET NO:', 'TICKET NO :', 'Ticket No:', 'Ticket No :'],
            'confNumber'   => ['PNR No:', 'PNR No :', 'PNR no:', 'PNR no :'],
            'boardingTime' => ['Boarding Time'],
        ],
    ];

    private $subjects = [
        'en' => ['Bus Booking Confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".easemytrip.com/") or contains(@href,"www.easemytrip.com") or contains(@href,"delivery.easemytrip.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"booking online at: mybookings.easemytrip.com") or contains(normalize-space(),"Email us: care@easemytrip.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BusBooking' . ucfirst($this->lang));

        $this->parseBus($email);

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

    private function parseBus(Email $email): void
    {
        $xpathNoDisplay = 'not(ancestor-or-self::*[contains(translate(@style," ",""),"display:none")])';
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $bus = $email->add()->bus();

        $bookingOn = strtotime(
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking on -'))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking on -'))}]", null, true, "/{$this->opt($this->t('Booking on -'))}\s*(.*\d.*)$/")
        );
        $bus->general()->date($bookingOn);

        $ticketNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ticketNo'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, '/^[_A-z\d]{5,}$/');
        $bus->addTicketNumber($ticketNo, false);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, '/^[_A-z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $bus->general()->confirmation($confirmation, $confirmationTitle);
        }

        $s = $bus->addSegment();

        $nameDepParts = $nameArrParts = [];

        $nameDepParts[] = implode(' ', array_unique($this->http->FindNodes("//*[{$this->eq($this->t('Boarding Point Details'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]")));
        $nameArrParts[] = implode(' ', array_unique($this->http->FindNodes("//*[{$this->eq($this->t('Dropping Point Details'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]")));

        $cities = $this->http->XPath->query("//tr[ count(*)=3 and *[1][normalize-space()] and *[2][normalize-space()='']/descendant::img and *[3][normalize-space()] ]");

        if ($cities->length === 1) {
            $root = $cities->item(0);
            $nameDepParts[] = $this->http->FindSingleNode('*[1]', $root);
            $nameArrParts[] = $this->http->FindSingleNode('*[3]', $root);
        }

        $s->departure()->name(implode(', ', array_filter($nameDepParts)));
        $s->arrival()->name(implode(', ', array_filter($nameArrParts)));

        $patterns['timeDate'] = "/^(?<time>{$patterns['time']})\s+(?<date>.{6,})$/";

        $timeDateDep = implode(' ', $this->http->FindNodes("//tr/*[ not(.//tr) and descendant::text()[normalize-space()][1][{$this->eq($this->t('Boarding Time'))}] ]/descendant::text()[normalize-space()][position()>1]"));

        if (preg_match($patterns['timeDate'], $timeDateDep, $m)) {
            $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
        }

        $timeDateArr = implode(' ', $this->http->FindNodes("//tr/*[ not(.//tr) and descendant::text()[normalize-space()][1][{$this->eq($this->t('Dropping Time'))}] ]/descendant::text()[normalize-space()][position()>1]"));

        if (preg_match($patterns['timeDate'], $timeDateArr, $m)) {
            $s->arrival()->date(strtotime($m['time'], strtotime($m['date'])));
        }

        $travellers = $seats = [];

        $travellerRows = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('Name'))}] and *[4][{$this->eq($this->t('Seat No'))}] ]/following-sibling::tr[ normalize-space() and *[4] ]");

        foreach ($travellerRows as $tRow) {
            $traveller = $this->http->FindSingleNode('*[2]', $tRow, true, "/^{$patterns['travellerName']}$/u");

            if ($traveller) {
                $travellers[] = $traveller;
            }

            $seat = $this->http->FindSingleNode('*[4]', $tRow, true, "/^[-_A-z\d]+$/"); // B1    |    6LB

            if ($seat) {
                $seats[] = $seat;
            }
        }

        if (count($seats) > 0) {
            $s->extra()->seats($seats);
        }

        if (count($travellers) > 0) {
            $bus->general()->travellers($travellers);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Fare'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // Rs. 1600
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $bus->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//td[{$this->eq($this->t('Basic Fare'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)) {
                $bus->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discounts = [];

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[normalize-space()][1][{$this->eq($this->t('Basic Fare'))}]] and following-sibling::tr[*[normalize-space()][1][{$this->eq($this->t('Total Fare'))}]] and *[2][normalize-space()] and {$xpathNoDisplay} ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    // fee
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $bus->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                } elseif (preg_match('/^\([ ]*-[ ]*\)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    // discount
                    $discounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($discounts) > 0) {
                $bus->price()->discount(array_sum($discounts));
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['boardingTime'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['boardingTime'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'INR' => ['Rs.'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
