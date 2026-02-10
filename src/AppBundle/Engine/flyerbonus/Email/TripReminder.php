<?php

namespace AwardWallet\Engine\flyerbonus\Email;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers malaysia/FlightRetiming(object), aviancataca/Air(object), thaiair/Cancellation(object), rapidrewards/Changes, mabuhay/FlightChange(object), lotpair/FlightChange(object) (in favor of malaysia/FlightRetiming)

class TripReminder extends \TAccountChecker
{
    public $mailFiles = "flyerbonus/it-53641168.eml, flyerbonus/it-56732360.eml, flyerbonus/it-56809175.eml";

    public $reFrom = "bangkokair.com";
    public $reSubject = [
        'en' => 'Trip reminder for Booking',
        'Notify Schedule Change of Booking',
    ];

    public $reBody2 = [
        'en' => ['Your Itinerary Summary', 'Original Flight Information'],
    ];

    public static $dictionary = [
        'en' => [
            'cancellationPhrases' => ['FLIGHT CANCELLATION NOTIFICATION', 'Bangkok Airways notifies a cancellation of'],
        ],
    ];

    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseHtml($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Bangkok Airways') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"@bangkokair.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Bangkok Airways gladly welcome") or contains(normalize-space(),"Bangkok Airways notifies") or contains(.,"@bangkokair.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancellationPhrases'))}]")->length > 0) {
            $f->general()->cancelled();
        }

        $confirmation = $this->http->FindSingleNode("//td[{$this->starts($this->t('Booking reference:'))}]/following-sibling::td[normalize-space()]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//td[ {$this->starts($this->t('Booking reference:'))} and following-sibling::td[normalize-space()] ]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $ffNumber = $this->http->FindSingleNode("//td[{$this->contains($this->t('Frequent flyer:'))}]/following-sibling::td[normalize-space()]", null, true, "/(?:^|\/)\s*([-A-Z\d]*\d[-A-Z\d]*)$/");

        if (!empty($ffNumber)) {
            $f->program()->account($ffNumber, false);
        }

        $f->general()->traveller($this->http->FindSingleNode("//p[{$this->starts($this->t('Dear '))}]", null, false,
            "/{$this->opt($this->t('Dear '))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;!?]|$)/"));

        $xpath = "//tr[{$this->starts($this->t('Your Itinerary Summary'))}]/following::tr[ *[{$this->eq($this->t('Departure'))}] and *[{$this->eq($this->t('Arrival'))}] ]/following-sibling::tr[count(*)>10]";
        $this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);

        if (count($segments) == 0) {
            $xpath = "//tr[{$this->starts($this->t('Your Itinerary Summary'))}]/following::text()[normalize-space()='From']/ancestor::table[1]/following::tr/descendant::text()[contains(normalize-space(), ':')]/ancestor::tr[1]";
            $this->logger->debug($xpath);
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0 && $f->getCancelled()) {
            // for cancelled
            $xpath = "//tr[ *[{$this->eq($this->t('Departure'))}] and *[{$this->eq($this->t('Arrival'))}] ]/following-sibling::tr[count(*)>10]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $key => $root) {
//            $this->logger->debug($key.' -> '.$root->nodeValue);
//            $this->logger->debug($this->http->FindSingleNode("(./following-sibling::tr[count(./td) > 4])[1]/td[normalize-space()!=''][1]", $root));
//            $this->logger->debug("\n");

            $dateDep = $this->http->FindSingleNode("./following-sibling::tr[count(./td) > 4][1]/td[normalize-space()!=''][1]",
                $root);
            $dateArr = $this->http->FindSingleNode("./following-sibling::tr[count(./td) > 4][1]/td[normalize-space()!=''][2]",
                $root);

            if (!isset($dateDep, $dateArr)) {
                return;
            }
            $s = $f->addSegment();

            $patterns['time'] = '\d{1,2}[:]+\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?';

            // Bangkok BKK Suvarnabhumi Intl  Phuket HKT Phuket Intl  19:40  21:10  PG279  V
            if (preg_match("/^\s*(.{3,}?)\s{2,}(.{3,}?)\s+({$patterns['time']})\s+({$patterns['time']})\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)\s+([A-Z]{1,2})\s*$/",
                $root->nodeValue, $m)) {
                $patterns['airport'] = '/^(.+)\s+([A-Z]{3})\s+(.+)$/'; // Bangkok BKK Suvarnabhumi Intl

                if (preg_match_all('/\b[A-Z]{3}\b/', $m[1], $matches) && count($matches[0]) === 1
                    && preg_match($patterns['airport'], $m[1], $matches)
                ) {
                    $s->departure()
                        ->name(implode(', ', array_unique([$matches[3], $matches[1]])))
                        ->code($matches[2]);
                } else {
                    $s->departure()
                        ->name($m[1])
                        ->noCode();
                }

                if (preg_match_all('/\b[A-Z]{3}\b/', $m[2], $matches) && count($matches[0]) === 1
                    && preg_match($patterns['airport'], $m[2], $matches)
                ) {
                    $s->arrival()
                        ->name(implode(', ', array_unique([$matches[3], $matches[1]])))
                        ->code($matches[2]);
                } else {
                    $s->arrival()
                        ->name($m[2])
                        ->noCode();
                }

                $s->departure()->date2("{$dateDep}, {$m[3]}");
                $s->arrival()->date2("{$dateArr}, {$m[4]}");

                $s->airline()->name($m[5]);
                $s->airline()->number($m[6]);
                $s->extra()->bookingCode($m[7]);
            }
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }
}
