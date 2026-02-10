<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use Symfony\Component\Validator\Constraints\Count;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-19801373.eml, alitalia/it-33591470.eml, alitalia/it-33655362.eml, alitalia/it-33727328.eml, alitalia/it-50378357.eml"; // +1 bcdtravel(html)[nl]
    public $lang = '';
    public static $dict = [
        'it' => [
            'Partenza'      => ['Partenza', 'PARTENZA'],
            'Arrivo'        => ['Arrivo', 'ARRIVO'],
            'Gate/Terminal' => ['Gate/Terminal', 'Gate / Terminal'],
        ],
        'nl' => [
            'Partenza'       => ['Vertrek', 'VERTREK'],
            'Arrivo'         => ['Aankomst', 'AANKOMST'],
            'Gate/Terminal'  => ['Gate/Terminal', 'Gate / Terminal'],
            'Volo'           => 'Vlucht',
            'Biglietto'      => 'Ticket',
            'Posto'          => 'Stoel',
            'Stato del volo' => 'Vuchtstatus',
        ],
        'en' => [
            'Partenza'       => ['Departure', 'DEPARTURE'],
            'Arrivo'         => ['Arrival', 'ARRIVAL'],
            'Gate/Terminal'  => ['Gate/Terminal', 'Gate / Terminal'],
            'Volo'           => 'Flight',
            'Biglietto'      => 'Ticket',
            'Posto'          => 'Seat',
            'Stato del volo' => 'Flight status',
        ],
        'pt' => [
            'Partenza'       => ['Partida', 'PARTIDA'],
            'Arrivo'         => ['Chegada', 'CHEGADA'],
            'Gate/Terminal'  => ['Portão/Terminal', 'Portão / Terminal'],
            'Volo'           => 'Voo',
            'Biglietto'      => 'Bilhete',
            'Posto'          => 'Assento',
            'Stato del volo' => 'Estado do voo',
        ],
        'es' => [
            'Partenza'       => ['Salida', 'SALIDA'],
            'Arrivo'         => ['Llegada', 'LLEGADA'],
            'Gate/Terminal'  => ['Puerta/Terminal', 'Puerta / Terminal'],
            'Volo'           => 'Vuelo',
            'Biglietto'      => 'Billete',
            'Posto'          => 'Asiento',
            'Stato del volo' => 'Estado del vuelo',
        ],
    ];

    private $subjects = [
        'it' => ['La tua carta di imbarco'],
        'nl' => ['Uw instapkaart'],
        'en' => ['Your boarding pass', 'Your summary'],
        'pt' => ['o seu resumo'],
        'es' => ['Su resumen'],
    ];

    private $langDetectors = [
        'it' => ['ARRIVO', 'Arrivo'],
        'nl' => ['AANKOMST', 'Aankomst'],
        'en' => ['ARRIVAL', 'Arrival'],
        'pt' => ['CHEGADA', 'Chegada'],
        'es' => ['LLEGADA', 'Llegada'],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"www.alitalia.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.alitalia.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BoardingPass' . ucfirst($this->lang));
        $this->parseEmail($email);

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

    private function parseEmail(Email $email)
    {
        $patterns = [
            'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'time'       => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        ];

        $xpathFragmentCell = '(self::td or self::th)';

        //for check passengers
        $travellersArray = explode(',', $this->http->FindSingleNode("//span[starts-with(normalize-space(),'Gentile') or starts-with(normalize-space(),'Dear') or starts-with(normalize-space(),'Apreciado(a)') or starts-with(normalize-space(),'Caro (a)') ]/following-sibling::span[1]"));

        $flightNodes = $this->http->XPath->query('//text()[' . $this->eq($this->t('Volo')) . ']');

        foreach ($flightNodes as $flightNode) {
            $f = $email->add()->flight();
            $s = $f->addSegment();

            $xpathFragmentPreTable = './ancestor::table[ ./preceding-sibling::table[normalize-space(.)] ][1]/preceding-sibling::table';
            $xpathFragmentPreRow = $xpathFragmentPreTable . '/ancestor::table[ ./preceding-sibling::*[normalize-space(.)] ][1]/preceding-sibling::*[normalize-space(.)]';

            // depCode
            // arrCode
            $routeTexts = $this->http->FindNodes($xpathFragmentPreRow . '[2]/descendant::text()[normalize-space(.)]', $flightNode);
            $routeText = implode(' ', $routeTexts);

            if (empty($routeText)) {
                $routeText = $this->http->FindSingleNode("./ancestor::div[1]/preceding::div[1]/preceding::text()[normalize-space()][string-length(normalize-space())=3][1]/ancestor::*[string-length(normalize-space())!=3][1]", $flightNode);
            }

            // Milano LIN - Catania CTA //old /^.+(?<depCode>[A-Z]{3})\s*-\s*.+(?<arrCode>[A-Z]{3})$/
            if (preg_match('/^(?<depName>.+)\s(?<depCode>[A-Z]{3})\s+-\s+(?<arrName>.+)\s+(?<arrCode>[A-Z]{3})$/', $routeText, $matches)) {
                $s->departure()->code($matches['depCode']);
                $s->departure()->name($matches['depName']);
                $s->arrival()->code($matches['arrCode']);
                $s->arrival()->name($matches['arrName']);
            }

            // traveller
            $traveller = trim($this->http->FindSingleNode($xpathFragmentPreRow . '[1]', $flightNode));

            if (in_array($traveller, $travellersArray)) {
                $f->general()->traveller($traveller);
            } else {
                $traveller = trim($this->http->FindSingleNode("./ancestor::div[1]/preceding::div[1]", $flightNode));

                if (in_array($traveller, $travellersArray)) {
                    $f->general()->traveller($traveller);
                }
            }

            // depDate
            $dateDepTexts = $this->http->FindNodes($xpathFragmentPreTable . '/descendant::text()[' . $this->eq($this->t('Partenza')) . ']/ancestor::*[' . $xpathFragmentCell . '][1]/descendant::text()[normalize-space(.)]', $flightNode);
            $dateDepText = implode(' ', $dateDepTexts);
            // Partenza sab 28 lug 2018 14:35
            if (preg_match('/' . $this->opt($this->t('Partenza')) . '\s+(?<date>.{6,}?)\s+(?<time>' . $patterns['time'] . ')$/', $dateDepText, $matches)) {
                $dateDepNormal = $this->normalizeDate($matches['date']);

                if ($dateDepNormal) {
                    $s->departure()->date(strtotime($dateDepNormal . ' ' . $matches['time']));
                }
            }

            // arrDate
            $dateArrTexts = $this->http->FindNodes($xpathFragmentPreTable . '/descendant::text()[' . $this->eq($this->t('Arrivo')) . ']/ancestor::*[' . $xpathFragmentCell . '][1]/descendant::text()[normalize-space(.)]', $flightNode);
            $dateArrText = implode(' ', $dateArrTexts);
            // Partenza sab 28 lug 2018 14:35
            if (preg_match('/' . $this->opt($this->t('Arrivo')) . '\s+(?<date>.{6,}?)\s+(?<time>' . $patterns['time'] . ')$/', $dateArrText, $matches)) {
                $dateArrNormal = $this->normalizeDate($matches['date']);

                if ($dateArrNormal) {
                    $s->arrival()->date(strtotime($dateArrNormal . ' ' . $matches['time']));
                }
            }

            // ticketNumbers
            $ticketNumber = $this->http->FindSingleNode('./ancestor::tr[ ./descendant::text()[' . $this->eq($this->t('Biglietto')) . '] ][1]/descendant::text()[' . $this->eq($this->t('Biglietto')) . ']/following::text()[normalize-space(.)][1]', $flightNode, true, '/^([-\d ]{7,})$/');
            $f->addTicketNumber($ticketNumber, false);

            // depTerminal
            $terminalDep = $this->http->FindSingleNode('./ancestor::tr[ ./descendant::text()[' . $this->eq($this->t('Gate/Terminal')) . '] ][1]/descendant::text()[' . $this->eq($this->t('Gate/Terminal')) . ']/following::text()[normalize-space(.)][1]', $flightNode, true, '/\|\s*([A-Z\d ]+?)\s*$/');

            if ($terminalDep) {
                $s->departure()->terminal($terminalDep);
            }

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode('./following::text()[normalize-space(.)][1]', $flightNode);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            $xpathFragmentNextTd = './ancestor::*[' . $xpathFragmentCell . '][ ./following-sibling::*[' . $xpathFragmentCell . '] ][1]/following-sibling::*[' . $xpathFragmentCell . ']';

            // confirmationNumbers

            $pnrTitle = $this->http->FindSingleNode($xpathFragmentNextTd . '/descendant::text()[' . $this->eq($this->t('PNR')) . ']', $flightNode);
            $pnr = $this->http->FindSingleNode($xpathFragmentNextTd . '/descendant::text()[' . $this->eq($this->t('PNR')) . ']/following::text()[normalize-space(.)][1]', $flightNode, true, '/^(' . $patterns['confNumber'] . ')$/');
            $f->general()->confirmation($pnr, $pnrTitle);

            // seats
            $seat = $this->http->FindSingleNode($xpathFragmentNextTd . '/descendant::text()[' . $this->eq($this->t('Posto')) . ']/following::text()[normalize-space(.)][1]', $flightNode, true, '/^(\d{1,4}[A-Z])$/');
            $s->extra()->seat($seat, false, true);

            // status
            $status = $this->http->FindSingleNode($xpathFragmentNextTd . '/descendant::text()[' . $this->eq($this->t('Stato del volo')) . ']/following::text()[normalize-space(.)][1]', $flightNode);
            $s->extra()->status($status);
        }
    }

    private function normalizeDate(string $string)
    {
        $in = [
            // sab 28 lug 2018
            "#^[^\d\s]+\s+(\d{1,2})\s+([^\d\W]{3,})\s+(\d{4})$#u",
            // Thu Feb 14 2019
            "#^[^\d\s]+\s+([^\d\W]{3,})\s+(\d{1,2})\s+(\d{4})$#u",
        ];
        $out = [
            "$1 $2 $3",
            "$2 $1 $3",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $string));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
