<?php

namespace AwardWallet\Engine\dineout\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "dineout/it-456035310.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'          => ['Booking ID:', 'Booking ID :'],
            'Restaurant Details:' => [
                'Restaurant Details:', 'Restaurant details:', 'Restaurant Details :', 'Restaurant details :',
            ],
            'statusPhrases'  => ['RESERVATION'],
            'statusVariants' => ['CONFIRMED'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation CONFIRMED at'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@dineout.co.in') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,".dineout.co.in/") or contains(@href,"tracking.dineout.co.in")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Happy Dining! Team Dineout") or contains(normalize-space(),"Dineout, All rights reserved")]')->length === 0
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
        $email->setType('Reservation' . ucfirst($this->lang));

        $ev = $email->add()->event();
        $ev->type()->restaurant();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $ev->general()->status($status);
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $ev->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $ev->general()->confirmation($confirmation, $confirmationTitle);
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('No of Pax:'))}]/following::text()[normalize-space()][1]", null, true, '/^\d{1,3}$/');
        $ev->booked()->guests($guests);

        $date = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date:'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/'));
        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Time:'))}]/following::text()[normalize-space()][1]", null, true, '/^\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?/');

        $ev->booked()->start(strtotime($time, $date))->noEnd();

        $restaurantDetails = implode("\n", $this->http->FindNodes("//*[*[normalize-space()][1][{$this->eq($this->t('Restaurant Details:'))}] and count(*[normalize-space()])>2]/*[normalize-space()][position()>1]"));

        if (preg_match("/^(?<name>.{2,})\n(?<address>.{3,})(?:\n{$this->opt($this->t('Get Directions'))}|$)/", $restaurantDetails, $m)) {
            $ev->place()->name($m['name'])->address($m['address']);
        }

        if (preg_match("/^{$this->opt($this->t('You can contact the restaurant on'))}\s+([+(\d][-+,. \d)(]{5,}[\d)])[.\s]*$/m", $restaurantDetails, $m)) {
            foreach (preg_split('/(\s*,\s*)+/', $m[1]) as $phone) {
                $ev->place()->phone($phone);

                break;
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Restaurant Details:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Restaurant Details:'])}]")->length > 0
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
