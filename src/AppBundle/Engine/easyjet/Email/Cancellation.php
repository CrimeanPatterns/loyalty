<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-57958468.eml, easyjet/it-196247361.eml";
    private $lang = '';
    private $reFrom = [
        'easyjet.com',
    ];
    private $reProvider = ['easyJet.com'];
    private $reSubject = [
        '/Your booking .+: Your easyJet flight .+ has been cancell?ed/i',
    ];
    private $detectLang = [
        'en' => [
            'We are really sorry to inform you that your easyJet flight',
            'We’re really sorry to tell you that your easyJet flight',
        ],
    ];
    private static $dictionary = [
        'en' => [
            'pnr'              => ['BOOKING REFERENCE:', 'Your Booking:'],
            'statusPhrases'    => 'has been',
            'statusVariants'   => ['cancelled', 'canceled'],
            'cancelledPhrases' => ['has been cancelled', 'has been canceled'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $email->setType('Cancellation' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $f->general()->traveller($traveller);
        }

        $confirmation = $this->http->FindSingleNode("//tr[{$this->contains($this->t('pnr'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $confirmationTitle = $this->http->FindSingleNode("//tr[ {$this->contains($this->t('pnr'))} and following-sibling::tr[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');

        if (!$confirmation && preg_match("/^({$this->opt($this->t('pnr'))})[:\s]*([A-Z\d]{5,})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('pnr'))}]"), $m)) {
            $confirmation = $m[2];
            $confirmationTitle = rtrim($m[1], ': ');
        }

        $f->general()->confirmation($confirmation, $confirmationTitle);

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $roots = $this->findRoot();

        if ($roots->length === 0) {
            $this->logger->debug('Root node not found!');

            return $email;
        }
        $root = $roots->item(0);

        $text = $this->htmlToText($this->http->FindHTMLByXpath('ancestor::tr[1]', null, $root));
        $this->logger->debug("Text: {$text}");

        if (preg_match("/{$this->opt($this->t('cancelledPhrases'))}/i", $text, $m)) {
            $f->general()->cancelled();
        }

        /*
            flight EZY8057 from London Gatwick to Montpellier on 24-04-2020 at 18:55
        */
        $pattern = "/"
            . "flight\s+(?<airName>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(?<airNum>\d+)\s+from\s+(?<depName>.{2,}?)\s*(?:\(\s*(?<depCode>[A-Z]{3})\s*\))?\s+to\s+(?<arrName>.{2,}?)\s*(?:\(\s*(?<arrCode>[A-Z]{3})\s*\))?\s+on\s+(?<depDate>.{6,}?)\s+at\s+(?<depTime>{$patterns['time']})"
            . "/";

        if (preg_match($pattern, $text, $m)) {
            $s = $f->addSegment();
            $s->airline()->name($m['airName']);
            $s->airline()->number($m['airNum']);
            $s->departure()->name($m['depName']);

            if (!empty($m['depCode'])) {
                $s->departure()->code($m['depCode']);
            }
            $s->arrival()->name($m['arrName']);

            if (!empty($m['arrCode'])) {
                $s->arrival()->code($m['arrCode']);
            }
            $s->departure()->date(strtotime($m['depTime'], strtotime($m['depDate'])));
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'easyJet') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (preg_match($re, $headers['subject'], $m)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang() && $this->findRoot()->length > 0) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//*[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrases'))}]");
    }
}
