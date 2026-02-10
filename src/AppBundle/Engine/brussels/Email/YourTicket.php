<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// PDF-version: brussels/ETicketConfirmation

class YourTicket extends \TAccountChecker
{
    public $mailFiles = "brussels/it-52565155.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['booking reference is'],
            'Your flights' => ['Your flights'],
        ],
        'nl' => [
            'confNumber'   => ['boekingsreferentie van Brussels Airlines is'],
            'Your flights' => ['Je vluchten'],
        ],
        'es' => [
            'confNumber'   => ['La referencia de tu reserva en Brussels Airlines es'],
            'Your flights' => ['Tus vuelos'],
        ],
        'it' => [
            'confNumber'   => ['Il tuo riferimento di prenotazione Brussels Airlines è'],
            'Your flights' => ['I tuoi voli'],
        ],
        'fr' => [
            'confNumber'   => ['Votre référence de réservation Brussels Airlines :'],
            'Your flights' => ['Vos vols'],
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation of your booking - Booking reference'],
        'nl' => ['Bevestiging van jouw boeking - Boekingsreferentie:'],
        'es' => ['Confirmación de la reserva - Código de reserva:'],
        'it' => ['Conferma della prenotazione - Numero di prenotazione:'],
        'fr' => ['Confirmation de votre réservation - Numéro de réservation:'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]brusselsairlines\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".brusselsairlines.com/") or contains(@href,"www.brusselsairlines.com") or contains(@href,"web.brusselsairlines.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Brussels Airlines is firmly committed to respecting your privacy") or contains(normalize-space(),"For more information please read the Brussels Airlines")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $pdfs = $parser->searchAttachmentByName(".*pdf");

        foreach ($pdfs as $pdf) {
            if (($html = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
             && preg_match("#^(.*\n){2,5}.*[ ]{2,}\d{13}\n#", $html)) {
                $this->logger->debug('go to pdf parser brussels/ETicketConfirmation');

                return false;
            } else {
                continue;
            }
        }

        $this->parseFlight($email);
        $email->setType('YourTicket' . ucfirst($this->lang));

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

    private function parseFlight(Email $email)
    {
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"dd:dd")';

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->http->XPath->query("//tr[ *[normalize-space()][2][{$xpathTime}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $airports = $this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $segment);

            if (preg_match('/^.+\(\s*([A-Z]{3})\s*\)\s*-.+\(\s*([A-Z]{3})\s*\)$/', $airports, $m)) {
                // Frankfurt ( FRA ) - Brussels ( BRU )
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            $date = $this->http->FindSingleNode("*[normalize-space()][1]", $segment);
            $timeDep = $this->http->FindSingleNode("*[normalize-space()][2]", $segment, true, '/^\d{1,2}[:]+\d{2}.*/');

            if ($date && $timeDep) {
                $s->departure()->date2($this->normalizeDate($date) . ' ' . $timeDep);
                $s->arrival()->noDate();
            }

            $flight = $this->http->FindSingleNode("*[normalize-space()][3]", $segment);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $class = $this->http->FindSingleNode("*[normalize-space()][4]", $segment);
            $s->extra()->cabin($class);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Your flights'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Your flights'])}]")->length > 0
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            '#^\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s*$#u', //10 Feb 2019
        ];
        $out = [
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
    }
}
