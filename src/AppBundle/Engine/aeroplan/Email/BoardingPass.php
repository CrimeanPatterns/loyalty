<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-73568627.eml, aeroplan/it-671623298.eml, aeroplan/it-666482644-fr.eml";
    public $subjects = [
        '/(?:^|.+:\s*)Your boarding pass$/i',
        '/(?:^|.+:\s*)Check-in confirmation$/i',
        "/(?:^|.+:\s*)Carte d'accès à bord$/i", // fr
    ];

    public $lang = '';
    public $year = '';
    public $fileName;

    public static $dictionary = [
        'fr' => [
            // HTML
            'Please print attachment' => ["S'il vous plaît imprimer la pièce jointe."],
            'Check flight status'     => ['Vérifier l’état des vols', "Vérifier l'état des vols"],
            'PASSENGER'               => 'PASSAGER',
            'REFERENCE'               => 'RÉFÉRENCE',
            'TERMINAL'                => 'AÉROGARE',
            'SEAT'                    => 'PLACE',
            'TICKET NUMBER'           => 'NUMÉRO DE BILLET',

            // PDF
            'BOARDING PASS'     => ['CARTE D’ACCÈS À BORD', "CARTE D'ACCÈS À BORD"],
            'FREQUENT FLYER'    => 'VOYAGEUR ASSIDU',
            'BOOKING REFERENCE' => 'NUMÉRO DE RÉSERVATION',
            'CABIN'             => 'CABINE',
            'FLIGHT'            => 'VOL',
            // 'DATE' => '',
            'DEPARTURE TIME' => 'HEURE DE DÉPART',
        ],
        'en' => [
            // HTML
            'Please print attachment' => ['Please print attachment', 'THIS IS NOT A BOARDING PASS'],
            'Check flight status'     => ['Check flight status'],
            // 'PASSENGER' => '',
            // 'REFERENCE' => '',
            // 'TERMINAL' => '',
            // 'SEAT' => '',
            // 'TICKET NUMBER' => '',

            // PDF
            'BOARDING PASS'     => ['BOARDING PASS', 'CHECK-IN CONFIRMATION'],
            'FREQUENT FLYER'    => ['FREQUENT FLYER', 'VOYAGEUR ASSIDU'],
            'BOOKING REFERENCE' => ['BOOKING REFERENCE', 'NUMÉRO DE RÉSERVATION'],
            'CABIN'             => ['CABIN', 'CABINE'],
            // 'FLIGHT' => '',
            // 'DATE' => '',
            // 'DEPARTURE TIME' => '',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aircanada.ca') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Air Canada')]")->count() > 0
            && $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aircanada\.ca$/', $from) > 0;
    }

    public function ParseHtml(Email $email, array $bpsFromPdf): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('PASSENGER'))}] ]/following-sibling::tr[normalize-space()][1][count(*[normalize-space()])=2]/*[1]", null, true, "/^{$this->patterns['travellerName']}$/u");
        $f->general()->traveller($traveller, true);

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('REFERENCE'))}] ]/following-sibling::tr[normalize-space()][1][count(*[normalize-space()])=2]/*[2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][2][{$this->eq($this->t('REFERENCE'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpath = "//text()[{$this->eq($this->t('Check flight status'), 'normalize-space(translate(.,">›",""))')}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();

            $codesVal = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]/td[1]", $root);

            if (preg_match('/^([A-Z]{3})\s*([A-Z]{3})$/', $codesVal, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2])->noDate();
            }

            $depTerminal = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<3][ descendant-or-self::tr[*[2][{$this->starts($this->t('TERMINAL'))}]] ]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[ *[2] ][1]/*[2]", $root, true, "/^[A-Z\d]+$/");
            $s->departure()->terminal($depTerminal, false, true);

            $airlineInfo = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)\s+(?<date>[-[:alpha:]]+\s*\d{1,2}\s*[[:alpha:]]+)$/u", $airlineInfo, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                $date = $time = null;

                foreach ($bpsFromPdf as $bpass) {
                    if (!empty($bpass['depCode']) && !empty($bpass['arrCode']) && !empty($bpass['day']) && !empty($bpass['month'])
                        && $s->getDepCode() == $bpass['depCode'] && $s->getArrCode() == $bpass['arrCode']
                        && preg_match("/\b{$bpass['day']}\b.*\b{$bpass['month']}\b/iu", $m['date'])
                    ) {
                        $time = $bpass['time'];
                    }
                }

                if (preg_match("/^(?<wday>[-[:alpha:]]+)\s*(?<date>\d{1,2}\s*[[:alpha:]]+)$/u", $m['date'], $m2) && $this->year) {
                    $weekDateNumber = WeekTranslate::number1($m2['wday']);
                    $dateNormal = $this->normalizeDate($m2['date']);

                    if ($weekDateNumber !== null && $dateNormal) {
                        $date = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $this->year, $weekDateNumber);
                    }
                }

                if ($date && $time) {
                    $s->departure()->date(strtotime($time, $date));
                } elseif ($date) {
                    $s->departure()->day($date)->noDate();
                }
            }

            $xpathSeat = "following-sibling::tr[normalize-space()][position()<7][ descendant::node()[{$this->eq($this->t('SEAT'))}] ][1]/descendant::*[ count(tr)=2 and tr[1]/*[1][{$this->eq($this->t('SEAT'))}] ]";

            $xpathImg = $xpathSeat . "/ancestor::tr[ preceding-sibling::tr[normalize-space() or descendant::img] ][1]/preceding-sibling::tr[normalize-space() or descendant::img][1]/descendant::img[contains(@src,'barcode')]";
            $url = $this->http->FindSingleNode($xpathImg . "/@src", $root);

            if (!empty($url)) {
                $bp = $email->add()->bpass();
                $bp->setUrl(str_replace(' ', '%20', $url))
                    ->setDepCode($s->getDepCode())
                    ->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
                    ->setTraveller($f->getTravellers()[0][0])
                    ->setRecordLocator($f->getConfirmationNumbers()[0][0])
                ;

                if (!empty($s->getDepDate())) {
                    $bp->setDepDate($s->getDepDate());
                }
            }

            $cabin = $this->http->FindSingleNode($xpathImg . "/following::text()[normalize-space()][1]", $root, true, "/^(?:Business|Premium Economy|Economy|Économique)$/iu");
            $s->extra()->cabin($cabin, false, true);

            $seat = $this->http->FindSingleNode($xpathSeat . "/tr[2]/*[1]", $root, true, "/^\s*(\d{1,5}[A-Z])\b/");
            $seat = preg_replace("/^\s*SBY\s*$/", '', $seat);

            if (empty($seat)) {
                $seat = $bpsFromPdf[$key]['seat'];
            }

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }
        }

        $tickets = array_filter($this->http->FindNodes("//tr[ count(*)=2 and *[2][{$this->eq($this->t('TICKET NUMBER'))}] ]/following-sibling::tr[normalize-space()][1][count(*)=2]/*[2]", null, "/^{$this->patterns['eTicket']}$/"));

        if (count($tickets) > 0) {
            $f->issued()->tickets(array_unique($tickets), false);
        }
    }

    public function ParsePdf(string $text): array
    {
        $this->logger->debug(__FUNCTION__);
        $result = [];
        $bpTexts = $this->splitText("/(^[ ]*{$this->opt($this->tPlusEn('BOARDING PASS'))}(?:$|[ ]{2}|[ ]*\|))/m", $text);

        foreach ($bpTexts as $stext) {
            $bp = [];

            $bp['traveller'] = $this->re("/\n[ ]{0,10}([[:alpha:]][[:alpha:] \-]+?) {3,}{$this->opt($this->tPlusEn('FREQUENT FLYER'))}/u", $stext);
            $bp['ticket'] = $this->re("/\n[ ]{0,10}ETKT *(\d{10,})\s+/", $stext);
            $bp['pnr'] = $this->re("/[ ]{3,}{$this->opt($this->tPlusEn('BOOKING REFERENCE'))} {3,}{$this->opt($this->tPlusEn('CABINE'))}\n(?:.*\n)?.*[ ]{3,}([A-Z\d]{5,7}) {3,}[A-Z]{1,2}\n/", $stext);
            $bp['cabin'] = $this->re("/[ ]{3,}{$this->opt($this->tPlusEn('BOOKING REFERENCE'))} {3,}{$this->opt($this->tPlusEn('CABINE'))}\n(?:.*\n)?.*[ ]{3,}[A-Z\d]{5,7} {3,}([A-Z]{1,2})\n/", $stext);
            $regexp = "/"
                . "\n[ ]{0,10}{$this->opt($this->tPlusEn('FLIGHT'))}\b.*  {$this->opt($this->tPlusEn('DATE'))}  .*\n"
                . "(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fn>\d{1,5})[ ]{3,}(?<day>\d{1,2})(?<month>[[:upper:]]+)(?:\/[\d[:upper:]]+)?[ ]{3,}(?<airportDep>\S.*\S)[ ]{3,}(?<airportArr>\S.*\S)\n"
            . "/u";

            if (preg_match($regexp, $stext, $m)) {
                $bp['airline'] = $m['al'];
                $bp['flightNumber'] = $m['fn'];
                $bp['day'] = $m['day'];
                $bp['month'] = $m['month'];
                $bp['depCode'] = $this->re("/(?:^| )([A-Z]{3})$/", $m['airportDep']) ?? $this->re("/^([A-Z]{3})(?: |$)/", $m['airportDep']);
                $bp['arrCode'] = $this->re("/(?:^| )([A-Z]{3})$/", $m['airportArr']) ?? $this->re("/^([A-Z]{3})(?: |$)/", $m['airportArr']);
            }

            if (preg_match("/\n(.*) {3}{$this->opt($this->tPlusEn('SEAT'))} ?\/.*/", $stext, $m)
                && preg_match("/\n.* {3}{$this->opt($this->tPlusEn('SEAT'))} ?\/.*\n+.{" . mb_strlen($m[1]) . "} *(\d{1,3}[A-Z])(?: {3}|\n)/", $stext, $mat)
            ) {
                $bp['seat'] = $mat[1];
            }

            if (preg_match("/\n[ ]{0,10}{$this->opt($this->tPlusEn('DEPARTURE TIME'))}\b(?:.*\n){1,2}[ ]{0,10}({$this->patterns['time']})(?:[ ]{3}|\n)/", $stext, $m)) {
                $bp['time'] = $m[1];
            }

            $result[] = $bp;
        }

        return $result;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $emailDate = strtotime($parser->getDate());
        $this->year = date('Y', $emailDate ? $emailDate : null);

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        $bpsFromPdf = [];

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $bpsFromPdf = array_merge($bpsFromPdf, $this->ParsePdf($text));
        }

        $files = $parser->getAttachments();

        if (count($files) == 1) {
            $this->fileName = $files[0]['headers']['content-description'] ?? null;
        }

        $this->ParseHtml($email, $bpsFromPdf);

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

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Please print attachment']) || empty($phrases['Check flight status'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Please print attachment'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Check flight status'])}]")->length > 0
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

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[-,. ]*([[:alpha:]]{3,})$/u', $text, $m)) {
            // 08 Aug    |    08AUG
            $day = $m[1];
            $month = $m[2];
            $year = '';
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
