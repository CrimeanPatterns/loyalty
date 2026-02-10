<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// similar parser aeroplan:It4104615 (not the same, just similar)
class RevisedItinerary extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-50230073.eml, aeroplan/it-54975114.eml, aeroplan/it-55506309.eml, aeroplan/it-68055943.eml, aeroplan/it-68056330.eml, aeroplan/it-68344312.eml, aeroplan/it-68539041.eml, aeroplan/it-69099150.eml, aeroplan/it-77988378.eml";

    public $lang = '';

    public static $dict = [
        'fr' => [
            'updatedItineraryBelow' => ['Nouvelles place(s):', 'Nouvelles place(s) :'],
            //            'statusVariants' => '',
            //            'cancelledPhrases' => '',
            'Departing'         => 'Départ de',
            'Cabin'             => 'Cabine',
            'Booking Reference' => 'Numéro de réservation',
        ],
        'en' => [
            'updatedItineraryBelow' => ['Your updated itinerary:', 'Revised Itinerary:', 'Revised Seat(s):', 'Revised Flight Time:', 'Revised Departure Gate:'],
            'statusVariants'        => ['revised', 'rebooked', 'changed', 'cancelled', 'canceled'],
            'cancelledPhrases'      => ['cancelled', 'canceled'],
            'has been'              => ['has been', 'have been'],
        ],
    ];
    private $from = [
        '@aircanada.ca',
    ];
    private $subject = [
        'fr' => 'Réattribution de place pour le vol',
        'en' => '- REVISED ITINERARY -',
        'Flight details changed:',
        'has a seat change - Booking Reference',
        'has a revised time - Booking Reference',
        'has changed and you have been rebooked - Booking Reference',
        'has been cancelled - Booking Reference',
        'has been canceled - Booking Reference',
        '- FLIGHT CANCELLATION -',
    ];
    private $body = [
        'fr' => [
            'Nous avons dû vous attribuer une autre place pour une partie de votre voyage.',
        ],
        'en' => [
            'Please note the revised itinerary for your flight to',
            'Please note the revised time for your flight to',
            'we have automatically rebooked you on an alternative flight',
            'your flights with us has a revised',
            'There has been a seat change on part of your journey.',
            'One or more flights in your itinerary cannot be operated as planned.',
            'your flight with us has a revised time',
            'that the following flights have been cancelled',
            'This change also affects any customers you are travelling with on this booking',
            ['Your flight(s) to ', ' has a revised itinerary due to'],
            ['Your flight to ', ' has a revised'],
            ['We regret to inform you that ', ' has been cancelled'],
            ['We regret to inform you that ', ' has been canceled'],
            'Your flight has a new gate and is now departing from',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Air Canada') === false) {
            return false;
        }

        return $this->stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->from)}]")->length === 0
            && $this->http->XPath->query("//text()[contains(.,'Aeroplan') or contains(.,'.aircanada.com/') or contains(.,'//aircanada.com') or contains(.,'www.aircanada.com') or contains(.,'book.aircanada.com')]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getHTMLBody();

        if (empty($text)) {
            $text = $parser->getPlainBody();
            $this->http->SetEmailBody($text);
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }

        if (preg_match_all('/<[Bb][Rr]\b.*?\/?>/', $text, $m) && count($m[0]) > 5
            || preg_match_all('/<[Pp]\b.*?\/?>/', $text, $m) && count($m[0]) > 5
            || preg_match_all('/<[Tt][Rr]\b.*?\/?>/', $text, $m) && count($m[0]) > 5
        ) {
            $text = $this->htmlToText($text, true);
        }

        $text = substr($text, 0, 10000);
        $this->parseFlight($parser, $email, $text);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseFlight(\PlancakeEmailParser $parser, Email $email, string $text): void
    {
//        $this->logger->debug($text);
        $f = $email->add()->flight();

        $travellers = [];

        if (preg_match("/ {$this->opt($this->t('has been'))} ({$this->opt($this->t('statusVariants'))})(?: due |$)/m", $text, $m)) {
            $f->general()->status($m[1]);
        }

        if (preg_match("/^{$this->opt($this->t('cancelledPhrases'))}$/i", $f->getStatus())) {
            // it-68055943.eml
            $f->general()->cancelled();

            if (preg_match("/We regret to inform you that (?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<airlineNumber>\d+) from (?<depName>.{3,}?)[ ]*\([ ]*(?<depCode>[A-Z]{3})[ ]*\) to (?<arrName>.{3,}?)[ ]*\([ ]*(?<arrCode>[A-Z]{3})[ ]*\) on (?<date>.{3,}?\d{4}) has been/", $text, $m)) {
                // We regret to inform you that AC1878 from Toronto, Lester B. Pearson Intl (YYZ) to St Lucia, Hewanorra Intl (UVF) on April 27, 2020 has been
                $s = $f->addSegment();
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['airlineNumber']);
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->day2($m['date'])
                    ->noDate();
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->noDate();
            }
        }

        if (preg_match("/({$this->opt($this->t('Booking Reference'))})[ ]*:[^\w\n]*([A-Z\d]{5,})[^\w\n]*$/m", $text, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        if (($traveller = $this->re("/{$this->opt($this->t('Booking Reference'))}[ ]*:[:\s]*[A-Z\d]{5,}\s+([[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]])(?:\s+[*]{2}|[ ]*[\n]{2})/u", $text))) {
            if (substr_count($traveller, ',') > 1
                || (substr_count($traveller, ',') === 1
                    && preg_match("/^([A-Z] [\w ]+),\s*([A-Z] [\w ]+)$/", $traveller))
            ) {
                $travellers = array_merge($travellers, array_filter(preg_split('/\s*[,]+\s*/', $traveller)));
            } else {
                $travellers[] = $traveller;
            }
        }

        foreach ($this->t('updatedItineraryBelow') as $phrase) {
            // it-54456690.eml
            if (!empty($str = strstr($text, $phrase))) {
                $text = $str;

                break;
            }
        }

        $segments = $this->splitter("/"
            . "(\b(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d{1,6}(?:[ ]+operated by [-_#&\'\/)(,.!A-z\d ]+)?\s*\n"
            . "\s*{$this->opt($this->t('Departing'))}\s+)"
            . "/", $text);

        // it-68344312.eml
        $pattern1 = "\b(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<arNum>\d{1,6})(?:[ ]+operated by (?<ope>[-_#&\'\/)(,.!A-z\d ]+?))?\s*\n"
            . "\s*{$this->opt($this->t('Departing'))}\s+(?<dName>.+?)\s*\((?<dCode>[A-Z]{3})\) on (?<dDate>.+?(?:@|at)\s+\d+:\d+\b)(?:[ ]*\([^)(]*\)|[ *]*)?"
            . "(?:\s*-- Departure(?: Terminal (?<dTerm>[A-Z\d]+))?[, ]*(?:[, ]*Gate [A-Z\d]+)?(?:[ ]*\([^)(]*\))?)?"
            . "\s*Arriving in (?<aName>.+?)\s*\((?<aCode>[A-Z]{3})\) on (?<aDate>.+?(?:@|at)\s+\d+:\d+\b)?(?:[ ]*\([^)(]*\)|[ *]*)?"
            . "(?:\s*-- Arrival Terminal (?<aTerm>[A-Z\d]+))?";

        // it-69099150.eml, it-68539041.eml
        $pattern2 = "\b(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<arNum>\d{1,6})(?:[ ]+operated by (?<ope>[-_#&\'\/)(,.!A-z\d ]+?))?\s*\n"
            . "\s*{$this->opt($this->t('Departing'))}\s+(?<dName>.+?)\s+\((?<dCode>[A-Z]{3})\) on (?<dDate>.+?@\s+\d+:\d+\b)(?:[ ]*\([^)(]*\)|[ *]*)?"
            . "\s*{$this->opt($this->t('Cabin'))}[ ]*:[ ]*(?<cabin>.*)"
            . "(?<seats>(?:[>\n]+[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]][ ]*:[ ]*\d+[A-Z]\b)+)";

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/{$pattern1}/", $sText, $i)) {
                $s->airline()->name($i['arName']);
                $s->airline()->number($i['arNum']);

                if (!empty($i['ope'])) {
                    $s->airline()->operator($i['ope']);
                }

                $s->departure()->name($i['dName']);
                $s->departure()->code($i['dCode']);

                if (count($dateTimeDep = preg_split("/(?:\s*@\s*| at )/", $i['dDate'])) === 2
                    && ($dateDep = $this->normalizeDate($dateTimeDep[0]))
                ) {
                    $s->departure()->date2($dateDep . ' ' . $dateTimeDep[1]);
                }

                if (!empty($i['dTerm'])) {
                    $s->departure()->terminal($i['dTerm']);
                }

                $s->arrival()->name($i['aName']);
                $s->arrival()->code($i['aCode']);

                if (empty($i['aDate'])) {
                    $s->arrival()->noDate();
                } elseif (count($dateTimeArr = preg_split("/(?:\s*@\s*| at )/", $i['aDate'])) === 2) {
                    $s->arrival()->date2($dateTimeArr[0] . ' ' . $dateTimeArr[1]);
                }

                if (!empty($i['aTerm'])) {
                    $s->arrival()->terminal($i['aTerm']);
                }
            } elseif (preg_match("/{$pattern2}/u", $sText, $i)) {
                $s->airline()->name($i['arName']);
                $s->airline()->number($i['arNum']);

                if (!empty($i['ope'])) {
                    $s->airline()->operator($i['ope']);
                }

                $s->departure()
                    ->name($i['dName'])
                    ->code($i['dCode']);

                if (count($dateTimeDep = preg_split("/\s*@\s*/", $i['dDate'])) === 2) {
                    if (($dateDep = $this->normalizeDate($dateTimeDep[0]))) {
                        $s->departure()->date(EmailDateHelper::calculateDateRelative($dateTimeDep[1] . ' ' . $dateDep, $this, $parser, '%D% %Y%'));
                    }
                }

                $s->arrival()
                    ->noCode()
                    ->noDate();

                if (preg_match("/^([A-Z]{1,2})[ ]*-[ ]*(.{3,})$/", $i['cabin'], $matches)) {
                    // L-Economy
                    $s->extra()
                        ->bookingCode($matches[1])
                        ->cabin($matches[2]);
                } else {
                    $s->extra()->cabin($i['cabin']);
                }

                if (preg_match_all("/^[> ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*:[ ]*(\d+[A-Z])$/mu", $i['seats'], $matches)) {
                    $travellers = array_merge($travellers, $matches[1]);
                    $s->extra()->seats($matches[2]);
                }
            }
        }
        /*
        if (preg_match_all("/{$reg}/", $text, $m, PREG_SET_ORDER)) {
            $this->logger->debug(var_export($m, true));

            foreach ($m as $i) {
                $s = $f->addSegment();
                $s->airline()->name($i['arName']);
                $s->airline()->number($i['arNum']);

                $s->departure()->name($i['dName']);
                $s->departure()->code($i['dCode']);
                $s->departure()->date(strtotime($this->normalizeDate($i['dDate'])));
                if (isset($i['dTerm']) && !empty($i['dTerm']))
                    $s->departure()->terminal($i['dTerm']);

                $s->arrival()->name($i['aName']);
                $s->arrival()->code($i['aCode']);
                if (isset($i['aDate']) && !empty($i['aDate']))
                    $s->arrival()->date(strtotime($this->normalizeDate($i['aDate'])));
                else
                    $s->arrival()->noDate();
                if (isset($i['aTerm']) && !empty($i['aTerm']))
                    $s->arrival()->terminal($i['aTerm']);

            }
        }
        */

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers), true);
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->body as $lang => $phrase) {
            foreach ((array) $phrase as $ph) {
                if ($this->http->XPath->query("//node()[{$this->contains($ph, '', 'and')}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function htmlToText($string, $view = false)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        // Multiple spaces and newlines are replaced with a single space.
        $string = trim(preg_replace('/\s\s+/', ' ', $string));
        $text = preg_replace('/<[^>]+>/', "\n", $string);

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }

    private function contains($field, $node = '', $glueImplode = ' or '): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode($glueImplode, array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[-\s]+([[:alpha:]]{3,})[-\s]+(\d{4})$/u', $text, $m)) {
            // 18-Oct-2020
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})$/u', $text, $m)) {
            // October 20
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // March 03, 2020
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
