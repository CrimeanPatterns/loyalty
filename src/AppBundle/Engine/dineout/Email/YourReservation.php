<?php

namespace AwardWallet\Engine\dineout\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "dineout/it-622777106.eml, dineout/it-623032765.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'links'          => ['See Menu', 'Get directions', 'Contact Manager'],
            'statusPhrases'  => ['reservation has been', 'Your booking reservation is'],
            'statusVariants' => ['confirmed', 'pending', 'received'],
        ],
    ];

    private $subjects = [
        'en' => ['Your Reservation is CONFIRMED at ', 'We RECEIVED your Reservation for '],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@dineout\.co\.in$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".dineout.co.in/") or contains(@href,"tracking.dineout.co.in")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Get Dineout App for")]')->length === 0
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
        $email->setType('YourReservation' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $ev = $email->add()->event();
        $ev->type()->restaurant();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$|\s+{$this->opt($this->t('at'))}\s)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $ev->general()->status($status);
        } elseif (preg_match("/Your Reservation is\s+({$this->opt($this->t('statusVariants'))})\s+at /i", $parser->getSubject(), $m)
            || preg_match("/We\s+({$this->opt($this->t('statusVariants'))})\s+your Reservation for /i", $parser->getSubject(), $m)
        ) {
            $ev->general()->status($m[1]);
        }

        $xpathLinks = "{$this->starts($this->t('links'))} and count(descendant::a[{$this->eq($this->t('links'))}])=3";
        $restaurantName = $this->http->FindSingleNode("//tr[{$xpathLinks}]/preceding-sibling::tr[normalize-space()][2]", null, true, "/^(.{2,}?)\s*(?:\d[\d.]*)?$/");
        $address = $this->http->FindSingleNode("//tr[{$xpathLinks}]/preceding-sibling::tr[normalize-space()][1]");
        $ev->place()->name($restaurantName)->address($address);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        $ev->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $ev->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathTime = 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';
        $timeDate = $this->http->FindSingleNode("//tr/*[normalize-space()='' and descendant::img]/following-sibling::*[normalize-space()][1][{$xpathTime}]");

        if (preg_match("/^(?<time>{$patterns['time']})(?:\s*,\s*)+(?<date>.{3,}\b\d{4})$/", $timeDate, $m)) {
            // 8:00 PM, 16 July 2022
            $ev->booked()->start(strtotime($m['time'], strtotime($m['date'])))->noEnd();
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Table'))}]/following::text()[normalize-space()][1][{$this->starts($this->t('for'))}]", null, true, "/^{$this->opt($this->t('for'))}\s+(\d{1,3})$/");
        $ev->booked()->guests($guests);

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
            if (!is_string($lang) || empty($phrases['links'])) {
                continue;
            }

            if ($this->http->XPath->query("//a[{$this->eq($phrases['links'])}]")->length > 0) {
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
