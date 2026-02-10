<?php

namespace AwardWallet\Engine\dineout\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancelled extends \TAccountChecker
{
    public $mailFiles = "dineout/it-456710643-cancelled.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'cancelledPhrases' => ["We're sad to see that you've had to cancel"],
            'statusVariants'   => ['CANCELLED', 'CANCELED'],
        ],
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

        return preg_match('/\bYour Reservation at .{3,100} was CANCELL?ED/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".dineout.co.in/") or contains(@href,"tracking.dineout.co.in")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Happy Dining, with from dineout")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('ReservationCancelled' . ucfirst($this->lang));

        $roots = $this->http->XPath->query("//text()[{$this->starts($this->t('cancelledPhrases'))}]");

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            return $email;
        }
        $root = $roots->item(0);

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $ev = $email->add()->event();
        $ev->type()->restaurant();
        $ev->general()->cancelled();

        if (preg_match("/\bYour Reservation at .{3,100} was ({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i", $parser->getSubject(), $m)) {
            $ev->general()->status($m[1]);
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $ev->general()->traveller($traveller);

        $date = $time = null;
        $mainText = $this->http->FindSingleNode('.', $root);

        /*
            We're sad to see that you've had to cancel your reservation at Mexicola, Anjuna, North Goa for 19th August at 8:00 PM.
        */

        if (preg_match("/{$this->opt($this->t('your reservation at'))}\s+(?<name>.{3,100}?)\s+{$this->opt($this->t('for'))}\s+(?<date>.{3,40}?)\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})/", $mainText, $m)) {
            $ev->place()->name($m['name']);
            $date = EmailDateHelper::calculateDateRelative($m['date'], $this, $parser, '%D% %Y%');
            $time = $m['time'];
        }

        if ($date && $time) {
            $ev->booked()->start(strtotime($time, $date));
        }

        $notes = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PLEASE NOTE:'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1][not(.//tr)]", null, true, "/^{$this->opt($this->t('PLEASE NOTE:'))}[:\s]*(.{2,})$/");
        $ev->general()->notes($notes);

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
            if (!is_string($lang) || empty($phrases['cancelledPhrases'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['cancelledPhrases'])}]")->length > 0) {
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
