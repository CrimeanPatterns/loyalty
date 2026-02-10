<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class EventBooking extends \TAccountChecker
{
    public $mailFiles = "booking/it-141551537.eml";
    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Confirmation number'],
            'statusPhrases'  => ['Your booking is'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    private $subjects = [
        'en' => ['Your booking is confirmed'],
    ];

    private $detectors = [
        'en' => ['Your booking is confirmed', 'Here are your booking details for'],
    ];

    private $year = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.com') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,".booking.com/") or contains(@href,"www.booking.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Booking.com. All rights reserved") or contains(normalize-space(),"This email was sent by Booking.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('EventBooking' . ucfirst($this->lang));

        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

        $this->parseEvent($email);

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

    private function parseEvent(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd")';

        $e = $email->add()->event();
        $e->place()->type(Event::TYPE_EVENT);

        $status = null;
        $statusValues = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/"));

        if (count(array_unique($statusValues)) === 1) {
            $status = array_shift($statusValues);
        }
        $e->general()->status($status);

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $e->general()->traveller($traveller);

        $xpathConfirmation = "//tr/*[not(.//tr) and normalize-space()][1][{$this->starts($this->t('confNumber'))}]";

        $confirmation = $this->http->FindSingleNode($xpathConfirmation . "/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr/*[not(.//tr)][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode($xpathConfirmation, null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation, $confirmationTitle);
        }

        $patterns['time'] = '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?';

        $dateStartValue = $this->http->FindSingleNode($xpathConfirmation . "/ancestor::table[1]/following::tr[ *[1][descendant::img and normalize-space()=''] and *[normalize-space()][1][{$xpathTime}] ][1]");

        if (preg_match("/^(?<date>.{3,}?)\s*,\s*(?<time>{$patterns['time']})/", $dateStartValue, $m)) {
            $m['date'] = $this->normalizeDate($m['date']);

            if (!preg_match('/\b\d{4}$/', $m['date'])) {
                $m['date'] .= ' ' . $this->year;
            }

            if (preg_match("/^(?<wday>[-[:alpha:]]+)[,\s]+(?<date>.{6,})/u", $m['date'], $matches)) {
                $weekDateNumber = WeekTranslate::number1($matches['wday']);
                $dateStart = EmailDateHelper::parseDateUsingWeekDay($matches['date'], $weekDateNumber);
            } else {
                $dateStart = strtotime($m['date']);
            }
            $e->booked()->start(strtotime($m['time'], $dateStart))->noEnd();
        }

        $guests = [];
        $guestsText = implode("\n", $this->http->FindNodes($xpathConfirmation . "/ancestor::table[1]/following::tr[ *[1][descendant::img and normalize-space()=''] and *[normalize-space()][1][{$this->contains($this->t('Adult'))} or {$this->contains($this->t('Child'))}] ][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^\s*(\d{1,3})\s*x\s*{$this->opt($this->t('Adult'))}/im", $guestsText, $m)) {
            $guests[] = (int) $m[1];
        }

        if (preg_match("/^\s*(\d{1,3})\s*x\s*{$this->opt($this->t('Child'))}/im", $guestsText, $m)) {
            $guests[] = (int) $m[1];
        }

        if (count($guests)) {
            $e->booked()->guests(array_sum($guests));
        }

        $name_temp = $this->http->FindSingleNode($xpathConfirmation . "/ancestor::table[1]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/preceding::tr[not(.//tr) and normalize-space()][1]");

        if ($name_temp && $this->http->XPath->query("//text()[{$this->contains($name_temp)}]")->length > 1) {
            $e->place()->name($name_temp);
        }

        $nameRule = empty($e->getName()) ? '' : ' or ' . $this->contains($e->getName());
        $address = $this->http->FindSingleNode("//p[ normalize-space() and preceding::p[normalize-space()][1][{$this->starts($this->t('Meeting point'))}{$nameRule}] and following::p[normalize-space()][1][{$this->contains($this->t('Get directions on Google Maps'))}] ]");
        $e->place()->address($address);

        $totalPrice = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Total'))}]/following-sibling::*[normalize-space()][last()]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // US$75    |    AED 641
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $e->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellation = $this->http->FindSingleNode("//tr[ normalize-space() and not(.//tr) and preceding::tr[normalize-space()][1][{$this->eq($this->t('Cancellation policy'))}] and following::tr[normalize-space()][1][{$this->contains($this->t('Cancel this booking'))}] ]");

        if ($cancellation) {
            $e->general()->cancellation($cancellation);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Mar 4
            '/^([[:alpha:]]+)\s+(\d{1,2})$/u',
            // Fri, Mar 4
            '/^([-[:alpha:]]+)[\s,]+([[:alpha:]]+)\s+(\d{1,2})$/u',
        ];
        $out = [
            '$2 $1',
            '$1, $3 $2',
        ];

        return preg_replace($in, $out, $text);
    }
}
