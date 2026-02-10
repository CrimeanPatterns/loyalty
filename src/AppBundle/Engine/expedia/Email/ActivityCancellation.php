<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class ActivityCancellation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-109847651.eml";

    public $lang = '';

    private $detectSubject = [
        // en
        'Confirmed: Expedia Activity Cancellation',
    ];
    private $detectBody = [
        // an array of any elements and each should be in a letter
        'en' => [
            ['your reservation was cancelled', 'If you want to cancel additional activities, a car or hotel']
        ],
    ];
    public static $dictionary = [
        'en' => [
            'Itinerary#'    => ['Itinerary#', 'Itinerary #'],
            'Hello' => 'Hello',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@expediamail.com') !== false || stripos($from, '.expediamail.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".expediamail.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Expedia customer support") or contains(normalize-space(),"Expedia, Inc. All rights reserved") or contains(normalize-space(),"Contact Expedia for further") or contains(.,"@expediamail.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEvent($email);

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

    private function parseEvent(Email $email)
    {

        // Travel Agency
        $xpathNo = "//tr[not(.//tr) and {$this->starts($this->t('Itinerary#'))}]";
        $itineraryNo = $this->http->FindSingleNode($xpathNo);
        if (preg_match("/^({$this->opt($this->t('Itinerary#'))})\s*([-A-Z\d]{5,})$/", $itineraryNo, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        // Event
        $e = $email->add()->event();

        $e->setEventType(Event::TYPE_MEETING);
        $e->general()
            ->noConfirmation()
            ->status('Cancelled')
            ->cancelled()
            ->traveller($this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Hello'))}]", null, true, "/^\s*{$this->opt($this->t('Hello'))}\s*([[:alpha:] \-]+), /"), false)
        ;

        $eventName = $this->http->FindSingleNode("//tr[not(.//tr) and following::tr[normalize-space() and not(.//tr)][1][{$this->starts($this->t('Itinerary#'))}] and preceding::tr[normalize-space() and not(.//tr)][1][{$this->starts($this->t('Hello'))}]]");
        $e->place()
            ->name($eventName);

    }

    private function detectBody(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dtBody) {
                    if ($this->http->XPath->query("//node()[{$this->contains($dtBody, '', 'and')}]")->length > 0) {
                        return true;
                    }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['Itinerary#']) || empty($phrases['Hello'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Itinerary#'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Hello'])}]")->length > 0
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

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Dec 25
            '/^([[:alpha:]]{2,})\s+(\d{1,2})$/u',
        ];
        $out = [
            '$2 $1',
        ];

        return preg_replace($in, $out, $text);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function contains($field, string $node = '', $operation = 'or'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }
        if (trim($operation) !== 'or' || trim($operation) !== 'and') {
            $operation = 'or';
        }
        $operation = ' '.trim($operation) .' ';

        return '(' . implode($operation, array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
