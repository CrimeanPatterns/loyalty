<?php

namespace AwardWallet\Engine\fairmont\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class BookingReminder extends \TAccountChecker
{
    public $mailFiles = "fairmont/it-74571956.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'           => ['Booking reference:', 'Booking reference :'],
            'startDate'            => ['Date:', 'Date :'],
            'Booking reminder for' => ['Booking reminder for', 'Booking confirmation for'],
        ],
    ];

    private $subjects = [
        'en' => ['Reminder of your reservation at', 'Booking Confirmation for'],
    ];

    private $detectors = [
        'en' => ['Booking reminder for', 'Booking confirmation for'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fairmont.com') !== false;
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//*[contains(normalize-space(),"Fairmont &")]')->length === 0
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
        $email->setType('BookingReminder' . ucfirst($this->lang));

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
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $e = $email->add()->event();
        $e->setEventType(Event::TYPE_RESTAURANT);

        $name = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Booking reminder for'))}]", null, true, "/{$this->opt($this->t('Booking reminder for'))}\s+(.{3,})$/");
        $e->place()->name($name);

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*,/u");
        $e->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation, $confirmationTitle);
        }

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $dateStart = $this->http->FindSingleNode("//text()[{$this->starts($this->t('startDate'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, '/^.{6,}$/');
        $timeStart = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Time:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, "/^{$patterns['time']}$/");
        $e->booked()->start2($dateStart . ' ' . $timeStart);

        $timeEnd = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We require your table back by:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, "/^{$patterns['time']}$/");

        if (!empty($timeEnd)) {
            $e->booked()->end(strtotime($timeEnd, $e->getStartDate()));
        } else {
            $e->booked()
               ->noEnd();
        }

        $guestCount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of guests:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, '/^\d+$/');
        $e->booked()->guests($guestCount);

        $addressParts = [];
        $phone = null;
        $addressRows = $this->http->FindNodes("//tr[{$this->eq($this->t('VENUE LOCATION'))} and not(preceding-sibling::tr[normalize-space()])]/following-sibling::tr[normalize-space()]");

        foreach ($addressRows as $aRow) {
            if (preg_match('/^[+(\d][-. \d)(]{5,}[\d)]$/', $aRow)) {
                $phone = $aRow;

                break;
            }
            $addressParts[] = $aRow;
        }
        $e->place()
            ->address(implode(', ', array_unique($addressParts)))
            ->phone($phone, false, true);
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['startDate'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['startDate'])}]")->length > 0
            ) {
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
}
