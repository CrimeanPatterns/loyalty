<?php

namespace AwardWallet\Engine\colombia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "colombia/it-204917227.eml, colombia/it-205219124.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber' => ['Reserva:', 'Reserva :'],
            'outbound'   => ['Ida:', 'Ida :'],
            'return'     => ['Vuelta:', 'Vuelta :'],
            'weWentTo'   => ['Nos fuimos a', '¡Nos fuimos a'],
        ],
    ];

    private $detectors = [
        'es' => ['Nos fuimos a'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]vivaair\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Confirmación reserva Viva') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".vivaair.com/") or contains(@href,"www.vivaair.com") or contains(@href,"links.vivaair.com") or contains(@href,"webapis.vivaair.com")]')->length === 0) {
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
        $email->setType('Reservation' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hola'))}]", null, "/^{$this->opt($this->t('Hola'))}[,\s]+({$patterns['travellerName']})[,;:!?\s]*$/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        } else {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('weWentTo'))}]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        }
        $f->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})\s*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], trim($m[1], ':： '));
        }

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';
        $roots = $this->http->XPath->query("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][2][{$xpathTime}] ]");

        if ($roots->length === 1) {
            $root = $roots->item(0);
        } else {
            $this->logger->debug('Root nodes not found!');

            return $email;
        }

        $airports = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));
        preg_match_all("/^(?:{$this->opt($this->t('outbound'))}|{$this->opt($this->t('return'))})?\s*(?<dep>.{3,}?)[ ]*-[ ]*(?<arr>.{3,})$/im", $airports, $airportMatches, PREG_SET_ORDER);

        $timeDep = implode("\n", $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));
        preg_match_all("/^(?:{$this->opt($this->t('outbound'))}|{$this->opt($this->t('return'))})?\s*({$patterns['time']})$/im", $timeDep, $timeMatches);

        $dateDep = implode("\n", $this->http->FindNodes("*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));
        preg_match_all("/^(?:{$this->opt($this->t('outbound'))}|{$this->opt($this->t('return'))})?\s*(.*\d.*)$/im", $dateDep, $dateMatches);

        foreach ($airportMatches as $i => $m) {
            $s = $f->addSegment();

            if (preg_match('/^[A-Z]{3}$/', $m['dep'])) {
                $s->departure()->code($m['dep']);
            } else {
                $s->departure()->name($m['dep'])->noCode();
            }

            if (preg_match('/^[A-Z]{3}$/', $m['arr'])) {
                $s->arrival()->code($m['arr']);
            } else {
                $s->arrival()->name($m['arr'])->noCode();
            }

            if (!empty($timeMatches[1]) && !empty($timeMatches[1][$i])
                && !empty($dateMatches[1]) && !empty($dateMatches[1][$i])
            ) {
                $s->departure()->date(strtotime($timeMatches[1][$i], strtotime($dateMatches[1][$i])));
                $s->arrival()->noDate();
                $s->airline()->noName()->noNumber();
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
