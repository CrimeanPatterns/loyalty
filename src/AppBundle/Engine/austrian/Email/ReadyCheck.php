<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ReadyCheck extends \TAccountChecker
{
    public $mailFiles = "austrian/it-174475915.eml, austrian/it-57290942.eml";
    private static $detectors = [
        'en' => [
            'If you have not checked in already, choose your preferred seat now using the Austrian',
            'check-in now for your flight to',
            'Due to the current situation we have updated the most important information about your flight',
        ],
        'de' => [
            'jetzt online für Ihren Flug nach Wien einchecken ',
            'Auch in der aktuellen Situation, haben wir Ihnen alle wichtigen Informationen zu Ihrem Flug',
        ],
    ];
    private static $dictionary = [
        'en' => [
//            'Dear'  => '',
//            'Booking code:'  => '',
            'Flight details for'  => ['Flight details for'],
//            'Flight number'  => '',
        ],
        'de' => [
            'Dear'  => ['Liebe Frau / Lieber Herr', 'Lieber Herr'],
            'Booking code:'  => 'Buchungscode:',
            'Flight details for'  => ['Flugdetails vom'],
            'Flight number'  => 'Flugnummer',
            'Booking class'  => 'Buchungsklasse',
        ],
    ];
    private $reFrom = "austrian@smile.austrian.com";
    private $date;
    private $lang;
    private $reSubject = [
        // en
        'is now ready for check-in',
        // de
        'Wien ist nun zum Einchecken bereit'
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".austrian.com/") or contains(@href,"smile.austrian.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Best wishes, Your Austrian Team")]')->length === 0
        ) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseEmail(Email $email): void
    {
        if (!self::detectBody()) {
            return;
        }

        $r = $email->add()->flight();

        $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Flight details for'))}][1]", null, true, "/" . $this->opt($this->t('Flight details for')) . "(.+)/");

        if (!empty($date)) {
            $this->date = $date;
        }

        $recordLocator = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking code:'))}]");

        if (preg_match("/^({$this->opt($this->t('Booking code:'))})[\s:]*([A-Z\d]{5,})$/", $recordLocator, $m)) {
            $r->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $traveller = $this->http->FindNodes("//text()[{$this->contains($this->t('Dear'))}]", null, "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;!?]|$)/u");
        $r->addTraveller($traveller[0]);

        $this->parseSegments($r);
    }

    private function parseSegments(Flight $r): void
    {
        $xpathAirportCode = "descendant::text()[normalize-space()][1][string-length()=3]";
        $xpath = "//tr[ *[1][{$xpathAirportCode}] and *[2][descendant::img] and *[3][{$xpathAirportCode}] ]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("[XPATH]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $codeDep = $this->http->FindSingleNode("*[1]//tr[not(.//tr) and normalize-space()][1]", $root, true, '/^[A-Z]{3}$/');
            $codeArr = $this->http->FindSingleNode("*[3]//tr[not(.//tr) and normalize-space()][1]", $root, true, '/^[A-Z]{3}$/');

            $nameDep = $nameArr = $dateDep = $dateArr = null;

            /*
                Vienna
                03.02.2020    08:45 AM
            */
            $pattern = "/^"
                . "\s*(?<airport>.{3,}?)[ ]*\n"
                . "[ ]*(?<date>.{6,}?)?[ ]*(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[ ]*"
                . "$/";

            $nameDateDepHtml = $this->http->FindHTMLByXpath("*[1]//tr[not(.//tr) and normalize-space()][2]", null, $root);
            $nameDateDep = $this->htmlToText($nameDateDepHtml);

            if (preg_match($pattern, $nameDateDep, $m)) {
                $nameDep = $m['airport'];

                if (!empty($m['date'])) {
                    $dateDep = strtotime($m['date'] . ' ' . $m['time']);
                } elseif ($this->date) {
                    $dateDep = strtotime($this->date . ' ' . $m['time']);
                }
            } elseif (preg_match("/^\s*(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[ ]*$/", $nameDateDep, $m)) {
                if ($this->date) {
                    $dateDep = strtotime($this->date . ' ' . $m['time']);
                }
            }

            $nameDateArrHtml = $this->http->FindHTMLByXpath("*[3]//tr[not(.//tr) and normalize-space()][2]", null, $root);
            $nameDateArr = $this->htmlToText($nameDateArrHtml);

            if (preg_match($pattern, $nameDateArr, $m)) {
                $nameArr = $m['airport'];

                if (!empty($m['date'])) {
                    $dateArr = strtotime($m['date'] . ' ' . $m['time']);
                } elseif ($dateDep) {
                    $dateArr = strtotime($m['time'], $dateDep);
                }
            } elseif (preg_match("/^\s*(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[ ]*$/", $nameDateArr, $m)) {
                if ($this->date) {
                    $dateArr = strtotime($this->date . ' ' . $m['time']);
                }
            }

            if (count($r->getSegments()) === 0
                || empty($s->getDepCode()) || empty($codeDep) || $s->getDepCode() !== $codeDep
                || empty($s->getArrCode()) || empty($codeArr) || $s->getArrCode() !== $codeArr
                || empty($s->getDepDate()) || empty($dateDep) || $s->getDepDate() !== $dateDep
                || empty($s->getArrDate()) || empty($dateArr) || $s->getArrDate() !== $dateArr
            ) {
                $s = $r->addSegment();
            }

            $flight = implode("\n", $this->http->FindNodes("ancestor::tr[following-sibling::tr][1]/following-sibling::tr//td[not(.//td)][normalize-space()]", $root));
            if (preg_match("/^\s*{$this->opt($this->t('Flight number'))}\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)(?:\n|$)/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            if (preg_match("/(?:^|\n)\s*{$this->opt($this->t('Booking class'))} *(.+)(?:\n|$)/", $flight, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }

            $s->departure()
                ->code($codeDep)
                ->date($dateDep);
            if (!empty($nameDep)) {
                $s->departure()
                    ->name($nameDep);
            }

            $s->arrival()
                ->code($codeArr)
                ->date($dateArr);
            if (!empty($nameArr)) {
                $s->arrival()
                    ->name($nameArr);
            }
        }
    }

    private function detectBody()
    {
        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Flight details for"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight details for'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
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
}
