<?php

namespace AwardWallet\Engine\condor\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationSpecialServices extends \TAccountChecker
{
    public $mailFiles = "condor/it-388078632-fr.eml, condor/it-673728358.eml, condor/it-676411742-de.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'de' => [
            'Confirmed reservation'      => 'Reservierungsbestätigung',
            // 'Cancellation confirmation' => '',
            'for special services'       => 'für Sonderleistungen',
            'Booking No.'                => ['Buchungsnr. | Booking No.', 'Buchungsnr.'],
            'Check-in Reference'         => ['Check-in Referenz | Reference', 'Check-in Referenz'],
            'Passengers (ID)'            => ['Passagiere | Passengers (ID)', 'Passagiere'],
            'Amount of debited services' => ['Gesamtpreis abgerechnete Leistungen | Amount of debited services', 'Gesamtpreis abgerechnete Leistungen'],
        ],
        'fr' => [
            'Confirmed reservation'      => 'Confirmation de réservation',
            // 'Cancellation confirmation' => '',
            'for special services'       => 'pour services spéciaux',
            'Booking No.'                => ['Numéro de réservation | Booking No.', 'Numéro de réservation'],
            'Check-in Reference'         => ["Référence d'enregistrement | Check-in Reference", "Référence d'enregistrement"],
            'Passengers (ID)'            => ['Passagers | Passengers (ID)', 'Passagers'],
            'Amount of debited services' => ['Prix total des services facturés | Amount of debited services', 'Prix total des services facturés'],
        ],
        'en' => [ // always last!
            'Confirmed reservation'     => 'Confirmed reservation',
            'Cancellation confirmation' => 'Cancellation confirmation',
            'for special services'      => 'for special services',
            // 'Booking No.' => '',
            'Check-in Reference'        => ['Check-in Reference', 'Reference'],
            // 'Passengers (ID)' => '',
            // 'Amount of debited services' => '',
        ],
    ];

    private $detectFrom = "no-answer@condor.com";
    private $detectSubject = [
        // en
        'Confirmation Special Services',
        // de
        'Reservierungsbestätigung Sonderleistungen',
        // fr
        'Confirmation Services spéciaux',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]condor\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text): bool
    {
        // detect provider
        if ($this->containsText($text, ['Condor Flugdienst']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if ((!empty($dict['Confirmed reservation'])
                && $this->containsText($text, $dict['Confirmed reservation']) === true
                || !empty($dict['Cancellation confirmation'])
                && $this->containsText($text, $dict['Cancellation confirmation']) === true
                )
                && !empty($dict['for special services'])
                && $this->containsText($text, $dict['for special services']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
        }

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

    private function parseEmailPdf(Email $email, ?string $textPdf = null): void
    {
        $f = $email->add()->flight();

        $transBefore = "(?:[[:alpha:]](?:[ \-']?[[:alpha:]])*\.? ?\|)?";
        // General
        if (preg_match("/\n *{$transBefore} ?({$this->opt($this->t('Booking No.'))}) +{$transBefore} ?({$this->opt($this->t('Check-in Reference'))})\s*\n *(?:CFI|GDS) ?([\dA-Z]{5,}) {3,}([A-Z\d]{5,7})\s*\n/u", $textPdf, $m)) {
            $f->general()->confirmation($m[4], preg_replace('/^([^\/|]+?)\s*[\/|]+\s*[^\/|]+$/', '$1', $m[2]));

            if ($m[4] !== $m[3]) {
                $f->general()->confirmation($m[3], preg_replace('/^([^\/|]+?)\s*[\/|]+\s*[^\/|]+$/', '$1', $m[1]));
            }
        }

        if (preg_match("/\n *{$this->opt($this->t('Cancellation confirmation'))}/", $textPdf)) {
            $f->general()
                ->cancelled()
                ->status('Cancelled');
        }
        $f->general()
            ->travellers(array_filter(array_map('trim', preg_replace("/^\s*(?:(?:MRS|MR|MS|MISS|CHD|U)\s+)?([A-Z][A-Z \-]*?)\s*,\s*([A-Z][A-Z \-]*?)\s*$/i", '$2 $1',
                preg_split("/(\n *| {3,})P\d{1,3} /", "\n" . trim($this->re("/\n *{$transBefore} ?{$this->opt($this->t('Passengers (ID)'))}(\n[\s\S]+?)\n *ID[ ]+Code/", $textPdf)))))), true);

        $segmentsText = $this->re("/\n *ID[ ]+Code.+(\n[\s\S]+?)\n[ ]*{$transBefore} ?{$this->opt($this->t('Amount of debited services'))}/u", "\n\n" . $textPdf);
        $segments = $this->split("/\n( {0,10}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) *\d{1,5} {2,}\d{4}[\d\-]+ +[A-Z]{3}-[A-Z]{3} *\|)/", $segmentsText);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/^\s* {0,10}(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3}) *(?<fn>\d{1,5}) {2,}(?<date>\d{4}[\d\-]+) +(?<dCode>[A-Z]{3})-(?<aCode>[A-Z]{3}) *\| *(?<cabin>.+)/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                $s->departure()
                    ->code($m['dCode'])
                    ->day(strtotime($m['date']))
                    ->noDate()
                ;
                $s->arrival()
                    ->code($m['aCode'])
                    ->noDate()
                ;

                $s->extra()
                    ->cabin($m['cabin']);

                if (preg_match_all("/\n *P\d+ +SEAT *(\d{1,3}[A-Z])(?: {3,}|\n)/", $sText, $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    // additional methods

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function split($re, $text): array
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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
}
