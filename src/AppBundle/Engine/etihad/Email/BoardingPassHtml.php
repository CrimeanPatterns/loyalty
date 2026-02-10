<?php

namespace AwardWallet\Engine\etihad\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassHtml extends \TAccountChecker
{
    public $mailFiles = "etihad/it-161123574.eml, etihad/it-161461882.eml, etihad/it-162195773.eml, etihad/it-680254781.eml";

    private $detectSubject = [
        // en
        'You’ve checked in for your flight from', 'Your boarding pass for your flight on',
        // it
        'Check-in eseguito per il volo da',
    ];

    private $lang = '';
    private static $dict = [
        'en' => [
            //            'Hi' => '',
            //            'Your booking reference' => '',
            //            'Your' => '',
            'Boarding information for' => 'Boarding information for',
            //            'Terminal:' => '',
            //            'Flight:' => '',
            //            'Seat:' => '',
            //            'Cabin:' => '',
            //            "Here's your boarding pass" => '',
        ],
        'it' => [
            'Hi'                       => 'Salve',
            'Your booking reference'   => 'Numero di prenotazione',
            // 'Your' => '',
            'Boarding information for'  => 'Informazioni sull\'imbarco per',
            'Terminal:'                 => 'Terminal:',
            'Flight:'                   => 'Volo:',
            'Seat:'                     => 'Posto:',
            'Cabin:'                    => 'Classe di viaggio:',
            "Here's your boarding pass" => "Ecco la tua carta d'imbarco",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.etihad.com") or contains(@href,"digital.etihad.com") or contains(@href,".bookings.etihad.")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]etihad\.[a-z]{2,4}\b/i', $from) > 0;
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
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseFlight(Email $email): void
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        // General

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking reference'))}]")
            ?? $this->http->FindSingleNode("//comment()[{$this->contains($this->t('Your booking reference'))}]")
        ;

        if (preg_match("/(?:^|>\s*)({$this->opt($this->t('Your booking reference'))})[:\s]+([A-Z\d]{5,})(?:\s*<|$)/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], preg_replace("/^{$this->opt($this->t('Your'))}\s+(\S.+)$/", '$1', $m[1]));
        }

        $travellers = [];
        $travellerShort = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            // it-680254781.eml
            $travellerShort = array_shift($travellerNames);
        }

        // Segments

        $xpath = "//tr[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$xpathTime}] and *[normalize-space()][position()>1 and last()][{$xpathTime}] ]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $tNames = $seats = [];

            // Airline
            $flight = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][3][{$this->starts($this->t('Boarding information for'))}]", $root);

            if (preg_match("/{$this->opt($this->t('Boarding information for'))}\s+(?<nameNumber>(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d{1,5}))\s*$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                /*
                    it-161123574.eml
                */

                // Passengers
                $tNames = $this->http->FindNodes("//tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight:'))}] and descendant::text()[normalize-space()][2][{$this->eq($m['nameNumber'])}] and descendant::text()[{$this->eq($this->t('Seat:'))}]]/preceding-sibling::tr[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u");

                // Extra
                $dopInfo = "\n" . implode("\n", $this->http->FindNodes("//tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight:'))}] and descendant::text()[normalize-space()][2][{$this->eq($m['nameNumber'])}] and descendant::text()[{$this->eq($this->t('Seat:'))}]]//text()[normalize-space()]"))
                    . "\n";

                if (preg_match_all("/{$this->opt($this->t('Seat:'))}\s*(\d{1,3}[A-Z])\s*\n/", $dopInfo, $seatMatches)) {
                    $seats = array_merge($seats, $seatMatches[1]);
                }

                if (preg_match_all("/{$this->opt($this->t('Cabin:'))}\s*([\w ]+)\s*\n/", $dopInfo, $mat)) {
                    $s->extra()
                        ->cabin(implode(", ", array_unique($mat[1])));
                }
            }

            if (count($tNames) === 0) {
                $travellerHidden = $this->http->FindSingleNode("preceding::comment()[{$this->contains($this->t('Flight:'))}][1][{$this->contains($travellerShort)}]", $root, true, "/>\s*((?:{$patterns['travellerName']}\s+)?{$this->opt($travellerShort)}(?:\s+{$patterns['travellerName']})?)\s*</iu");

                if ($travellerHidden) {
                    $tNames[] = $travellerHidden;
                }
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[normalize-space(.)][1]/ancestor::td[1]", $root));

            // Departure
            $dInfo = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space(.)]", $root));

            if (preg_match('/^\s*(?<time>\d{1,2}:\d{2}.*)\n\s*(?<name>[\s\S]+?)\s*\((?<code>[A-Z]{3})\)\s*$/', $dInfo, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($date ? strtotime($m['time'], $date) : null)
                ;
            }
            $terminal = $this->http->FindSingleNode("following::text()[normalize-space(.)][1][{$this->eq($this->t('Terminal:'))}]/following::text()[normalize-space(.)][1]", $root, true, "/^[^:]+$/");
            $terminal = preg_replace("/^[\s\-]+$/", '', $terminal);

            if (!empty($terminal)) {
                $s->departure()
                    ->terminal($terminal);
            }

            // it-680254781.eml
            $seat = $this->http->FindSingleNode("ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant::text()[{$this->eq($this->t('Seat:'))}]/following::text()[normalize-space()][1]", $root, true, '/^\d+[A-Z]$/');

            if ($seat) {
                $seats[] = $seat;
            }

            if (count($seats) > 0) {
                $s->extra()->seats(array_unique($seats));
            }

            // Arrival
            $aInfo = implode("\n", $this->http->FindNodes("*[normalize-space()][last()]//text()[normalize-space(.)]", $root));

            if (preg_match('/^\s*(?<time>\d{1,2}:\d{2}.*?)(\s*\(\s*(?<overnight>[-+]\s*\d{1,3})\s*\)\s*)?\n\s*(?<name>[\s\S]+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/', $aInfo, $m)) {
                if (!empty($m['overnight']) && !empty($date)) {
                    $date = strtotime($m['overnight'] . ' days', $date);
                }
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($date ? strtotime($m['time'], $date) : null)
                ;
            }

            if (count($tNames) > 0) {
                $travellers = array_merge($travellers, $tNames);
            }

            $url = $this->http->FindSingleNode("preceding::tr[{$this->eq($this->t("Here's your boarding pass"))} and following-sibling::*[normalize-space() or descendant::img] and not(preceding-sibling::*[normalize-space() or descendant::img])][1]/following-sibling::tr[normalize-space() or descendant::img][1]/descendant::img[normalize-space(@src)]/@src", $root);

            if ($url) {
                $this->parseBP($email, $f, $s, $url, $tNames);
            }
        }

        $f->general()->travellers(array_unique($travellers), true);
    }

    private function parseBP(Email $email, Flight $f, FlightSegment $s, string $url, array $travellers): void
    {
        // examples: it-680254781.eml

        $bp = $email->add()->bpass();

        // attachmentName / URL

        if (preg_match("/^(?:cid\s*[:]+\s*)+(.{3,})$/i", $url, $m)) {
            $bp->setAttachmentName($m[1]);
        } elseif ($url) {
            $bp->setUrl($url);
        }

        // recordLocator

        $confNumbers = $f->getConfirmationNumbers();

        if (count($confNumbers) === 1) {
            $RLs = array_column($confNumbers, 0);
            $recordLocator = array_shift($RLs);
            $bp->setRecordLocator($recordLocator);
        }

        // traveller

        if (count($travellers) === 1) {
            $traveller = array_shift($travellers);
            $bp->setTraveller($traveller);
        }

        // flightNumber + depCode + depDate

        $bp
            ->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
            ->setDepCode($s->getDepCode())
            ->setDepDate($s->getDepDate())
        ;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 01/15/2022, 15:35
            //            "/^(\d+)\/(\d+)\/(\d{4})\,\s*([\d\:]+)$/iu",
        ];
        $out = [
            //            "$2.$1.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        //$this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (!empty($words['Boarding information for'])) {
                if ($this->http->XPath->query("//*[{$this->starts($words['Boarding information for'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
