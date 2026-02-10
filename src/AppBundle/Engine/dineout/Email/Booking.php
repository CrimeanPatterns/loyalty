<?php

namespace AwardWallet\Engine\dineout\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "dineout/it-621903485.eml, dineout/it-621896158.eml, dineout/it-618866710-cancelled.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'GUESTS'          => ['GUESTS'],
            'statusPhrases'   => ['Booking', 'has been'],
            'statusVariants'  => ['Confirmed', 'Cancelled', 'Canceled'],
            'cancelledStatus' => ['Cancelled', 'Canceled'],
            'travellerStart'  => ['Thanks', 'Hi'],
            'confNumber'      => ['Your confirmation number is', 'Booking'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmed', 'Booking Cancelled', 'Booking Canceled'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@dineout\.is$/i', $from) > 0;
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//*[contains(normalize-space(),"sent to you by Dineout")] | //text()[starts-with(normalize-space(),"©") and contains(.,"Dineout")]')->length === 0
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
        $email->setType('Booking' . ucfirst($this->lang));

        $xpathBold = '(self::b or self::strong or self::h2 or contains(translate(@style," ",""),"font-weight:bold"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $ev = $email->add()->event();
        $ev->type()->restaurant();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $ev->general()->status($status);
        }

        if ($ev->getStatus() && preg_match("/^{$this->opt($this->t('cancelledStatus'))}$/", $ev->getStatus())) {
            $ev->general()->cancelled();
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('travellerStart'))} and {$this->contains($this->t('confNumber'))}]", null, true, "/{$this->opt($this->t('travellerStart'))}[,\s]+({$patterns['travellerName']})\s*[.;!?]\s*{$this->opt($this->t('confNumber'))}/u");
        $ev->general()->traveller($traveller);

        $confirmationTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, '/^#\s*([-A-Z\d]{5,})$/'));

        if (count(array_unique($confirmationTexts)) === 1) {
            $confirmation = array_shift($confirmationTexts);
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[ {$this->contains($this->t('confNumber'))} and following::text()[normalize-space()][1][{$this->contains($confirmation)}] ][1]", null, true, "/{$this->opt($this->t('confNumber'))}/");
            $ev->general()->confirmation($confirmation, preg_replace(["/^{$this->opt($this->t('Your'))}\s+/", "/\s+{$this->opt($this->t('is'))}$/"], '', $confirmationTitle));
        }

        $guests = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('GUESTS'))}] ]/*[normalize-space()][2]", null, true, "/^\d{1,3}$/");
        $ev->booked()->guests($guests);

        $date = strtotime($this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('DAY'))}] ]/*[normalize-space()][2]", null, true, "/^.{3,}\b\d{4}$/"));

        $timeStart = $timeEnd = null;

        $xpathTimeCell = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('TIME'))}]";
        $timeVal = $this->http->FindSingleNode("//*[{$xpathTimeCell}]/*[normalize-space()][2]", null, true, "/^{$patterns['time']}.*$/");

        if (preg_match("/^({$patterns['time']})\s*[-–]+\s*({$patterns['time']})$/", $timeVal, $m)) {
            $timeStart = $m[1];
            $timeEnd = $m[2];
        } elseif (preg_match("/^{$patterns['time']}$/", $timeVal)) {
            $timeStart = $timeVal;
        }

        if ($date) {
            if ($timeStart) {
                $ev->booked()->start(strtotime($timeStart, $date));
            }

            if ($timeEnd) {
                $ev->booked()->end(strtotime($timeEnd, $date));
            } elseif ($ev->getStartDate()) {
                $ev->booked()->noEnd();
            }
        }

        $restaurantName = $this->http->FindSingleNode("//*[{$xpathTimeCell}]/following::text()[normalize-space()][1]/ancestor::h2");
        $ev->place()->name($restaurantName);

        $address = $this->http->FindSingleNode("//*[{$xpathTimeCell}]/following::text()[{$this->starts($this->t('Address:'))}]", null, true, "/^{$this->opt($this->t('Address:'))}[:\s]*(.{3,})$/");

        if ($address) {
            $ev->place()->address($address);
        }

        $note = $this->http->FindSingleNode("//h2[{$this->eq($this->t('Note from the restaurant'))}]/following-sibling::node()[not(self::br)][1][count(descendant::node())=0]", null, false)
        ?? $this->http->FindSingleNode("//h2[{$this->eq($this->t('Note from the restaurant'))}]/following-sibling::*[not(self::br)][1]/descendant::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]");

        if ($note) {
            $ev->general()->notes($note);
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
            if (!is_string($lang) || empty($phrases['GUESTS'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->eq($phrases['GUESTS'])}]")->length > 0) {
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
