<?php

namespace AwardWallet\Engine\canvas\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TwoColumns extends \TAccountChecker
{
    public $mailFiles = "canvas/it-680228278.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'headerMain'     => ['Your Reservation'],
            'headerFirst'    => ['RESERVATION DETAILS', 'Reservation Details', 'Reservation details'],
            'statusPhrases'  => ['has been'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]undercanvas\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers) && preg_match('/Under Canvas .{2,} Confirmation - [A-Z\d]{4}/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".undercanvas.com/") or contains(@href,"www.undercanvas.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"info@undercanvas.com")]')->length === 0
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
        $email->setType('TwoColumns' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('headerMain'))}]/ancestor::*[1]", null, true, "/^{$this->opt($this->t('headerMain'))}\s+([-A-Z\d]{5,30})(?:\s|$)/");
        $h->general()->confirmation($confirmation);

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $nameAddress = $this->http->FindSingleNode("//text()[ normalize-space() and preceding::*[{$this->starts($this->t('headerMain'))}] and following::tr[{$this->eq($this->t('headerFirst'))}] ]");

        if (preg_match("/^(?<name>.{2,}?)\s*,\s*(?<address>.{3,})$/", $nameAddress, $m)
            && $this->http->XPath->query("//text()[{$this->contains($m['name'])}]")->length > 1
        ) {
            $h->hotel()->name($m['name'])->address($m['address']);

            $phones = array_filter($this->http->FindNodes("//text()[{$this->eq($m['name'])}]/following::text()[normalize-space()][1]", null, "/^{$patterns['phone']}$/"));

            if (count(array_unique($phones)) === 1) {
                $phone = array_shift($phones);
                $h->hotel()->phone($phone);
            }
        }

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Reservation Name'), 'translate(.,":","")')}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $h->general()->traveller($traveller, true);

        $dateCheckIn = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Arrival Date'), 'translate(.,":","")')}] ]/*[normalize-space()][2]", null, true, "/^.{3,}\b\d{4}$/"));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Departure Date'), 'translate(.,":","")')}] ]/*[normalize-space()][2]", null, true, "/^.{3,}\b\d{4}$/"));

        $xpathTimes = "//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Check-In/Out Times'), 'translate(.,":","")')}] ]/*[normalize-space()][2]";

        $timeCheckIn = $this->http->FindSingleNode($xpathTimes . "/descendant::text()[{$this->eq($this->t('Check-In'), 'translate(.,":","")')}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['time']}$/");
        $timeCheckOut = $this->http->FindSingleNode($xpathTimes . "/descendant::text()[{$this->eq($this->t('Check-out'), 'translate(.,":","")')}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['time']}$/");

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Room Type'), 'translate(.,":","")')}] ]/*[normalize-space()][2]");

        if ($roomType) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $cancellation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Cancellation'), 'translate(.,":","")')}] ]/*[normalize-space()][2]");
        $h->general()->cancellation($cancellation);

        if (preg_match("/^Reservations may be cancell?ed up until\s+(?<prior>\d{1,3} days?)\s+prior to your arrival date for a 100% refund\./i", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Total'), 'translate(.,":","")')}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // USD 1,494.50
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Subtotal'), 'translate(.,":","")')}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->FindNodes("//tr[ count(*[normalize-space()]) and *[normalize-space()][{$this->eq($this->t('Tax'), 'translate(.,":","")')}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]");

            foreach ($feeRows as $fRow) {
                if (preg_match("/^(?<name>.+?)\s*[:]+\s*(?<charge>.*\d.*)/", $fRow, $mm)) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $mm['charge'], $m)) {
                        $h->price()->fee($mm['name'], PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
        }

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['headerMain']) || empty($phrases['headerFirst'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->starts($phrases['headerMain'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($phrases['headerFirst'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
