<?php

namespace AwardWallet\Engine\finnair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It4618663 extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "finnair/it-4618663.eml, finnair/it-150952276.eml, finnair/it-153157105.eml";

    public $reFrom = "noreply.customerservice@finnair.com";
    public $reSubject = [
        "en" => "Your flight has been rescheduled", "Flight Notification",
    ];
    public $reBody = 'Finnair';
    public $reBody2 = [
        "en" => ["Your new flight details", "YOUR NEW FLIGHT DETAILS", "Flight details", "FLIGHT DETAILS"],
    ];

    public static $dictionary = [
        "en" => [
            "confNo"       => ["Booking reference:", "Your booking reference is:"],
            "flightHeader" => ["Your new flight details", "YOUR NEW FLIGHT DETAILS", "Flight details", "FLIGHT DETAILS"],
            "flight"       => ["Flight", "FLIGHT"],
            "operatedBy"   => ["Operated by:", "OPERATED BY:"],
            "seat"         => ["Seat:", "SEAT:"],
            "from"         => ["From:", "FROM:"],
            "to"           => ["To:", "TO:"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $f->general()->traveller($traveller);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t("confNo"))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $f->general()->confirmation($confirmation);

        $xpath = "//tr[{$this->eq($this->t("flightHeader"))}]/following-sibling::tr[ descendant::text()[{$this->eq($this->t("flight"))}] ]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->debug('segments root not found: ' . $xpath);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightVal = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

            if (preg_match("/^\s*{$this->opt($this->t("flight"))}[ ]*\n+[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)/", $flightVal, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t("operatedBy"))}[ ]*(.{2,}?)[ ]*$/m", $flightVal, $m)) {
                $s->airline()->operator($m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t("seat"))}[ ]*(\d+[A-Z])[ ]*$/m", $flightVal, $m)) {
                $s->extra()->seat($m[1]);
            }

            /*
                ARRIVAL
                27-Nov-22
                15:25
                FROM:
                Helsinki
                Terminal 2
            */
            $pattern = "/^\s*.+"
                . "\n+[ ]*(?<date>.*\d.*?)[ ]*"
                . "\n+[ ]*(?<time>{$patterns['time']})[ ]*"
                . "\n+[ ]*(?:{$this->opt($this->t("from"))}|{$this->opt($this->t("to"))})[ ]*"
                . "\n+[ ]*(?<city>.{2,}?)[ ]*"
                . "(?:\n+[ ]*Terminal[ ]+(?<terminal>.+?))?"
                . "\s*$/";

            $xpathNextRow = "following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[ count(*[normalize-space()])=2 ][1]";
            $departureText = $this->htmlToText($this->http->FindHTMLByXpath($xpathNextRow . "/*[normalize-space()][1]", null, $root));
            $arrivalText = $this->htmlToText($this->http->FindHTMLByXpath($xpathNextRow . "/*[normalize-space()][2]", null, $root));

            if (preg_match($pattern, $departureText, $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], strtotime($m['date'])))
                    ->name($m['city'])
                    ->noCode()
                ;

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            if (preg_match($pattern, $arrivalText, $m)) {
                $s->arrival()
                    ->date(strtotime($m['time'], strtotime($m['date'])))
                    ->name($m['city'])
                    ->noCode()
                ;

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $phrases) {
            foreach ($phrases as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang => $phrases) {
            foreach ($phrases as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $email->setType('FlightUpdate' . ucfirst($this->lang));

        $this->parseHtml($email);

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)-(\w+)-(\d{2}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/[[:alpha:]]/u", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
