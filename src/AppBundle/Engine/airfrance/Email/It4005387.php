<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It4005387 extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-125478099.eml, airfrance/it-131479264.eml, airfrance/it-13966915.eml, airfrance/it-17539776.eml, airfrance/it-260267182-cancelled.eml, airfrance/it-4005287.eml, airfrance/it-4005288.eml, airfrance/it-4005289.eml, airfrance/it-4005314.eml, airfrance/it-4005387.eml, airfrance/it-4005392.eml, airfrance/it-4005413.eml, airfrance/it-474940664.eml, airfrance/it-696616303-airserbia-bs.eml"; // +1 bcdtravel(pdf)[en]

    public $reSubject = [
        'bs' => ['Potvrda za elektronsku kartu'],
        'es' => ['Flying Blue: Acuse de recibo de su pedido'],
    ];

    public $langDetectorsPdf = [
        'bs' => ['KABINA/MESTO PRTLJAG'],
        'pt' => ['CABINE/ASSENTO BAGAGEM', 'CABINE/ASSENT O BAGAGEM'],
        'es' => ['CLASE/ASIENTO EQUIPAJE'],
        'it' => ['CLASSE/POSTO BAGAGLIO', 'CLASSE/POST O BAGAGLIO'],
        'en' => ['CLASS/SEAT BAGGAGE', 'CABIN/SEAT BAGGAGE', 'CABIN CLASS/SEAT INCLUDED BAGGAGE', 'CABIN CLASS / SEAT INCLUDEDBAGGAGE'],
    ];

    public static $dictionary = [
        'bs' => [
            // 'cancelledPhrases' => [''],
            'Booking Reference'           => 'Oznaka Rezervacije',
            'TICKET NUMBER'               => 'BROJ KARTE',
            'PASSENGER NAME'              => 'IME PUTNIKA',
            // 'ffNumber' => '',
            'ISSUE DATE'                  => 'IZDATO DANA',
            // 'Classes'                     => '',
            // 'seatConfirmed' => '',
            'Operated by:'                => 'Prevoz vrši:',
            'Fare Basis:'                 => 'Fare Family:',
            'Receipt And Payment Details' => 'Informacije O Prijemu I Plaćanju',
            'Total Fare'                  => ['Ukupna cena', 'Ukupnacena'],
        ],
        'pt' => [ // it-125478099.eml
            // 'cancelledPhrases' => [''],
            'Booking Reference'           => 'Referência Da Reserva',
            'TICKET NUMBER'               => ['NÚMERO DO BILHETE', 'NÚMERO DO BILHET E'],
            'PASSENGER NAME'              => 'SOBRENOME DO PASSAGEIRO',
            // 'ffNumber' => '',
            'ISSUE DATE'                  => ['DATA DE EMISSÃO DO BILHETE', 'DATA DE EMISSÃO DO BILHET E'],
            // 'Classes'                     => '',
            // 'seatConfirmed' => '',
            // 'Operated by:'                => '',
            'Fare Basis:'                 => ['Base tarifária:', 'Base t arifária:'],
            'Receipt And Payment Details' => 'Detalhes E Recibo Do Pagamento',
            'Total Fare'                  => ['Tarifa total', 'Tarifatotal'],
        ],
        'es' => [ // it-4005387.eml
            // 'cancelledPhrases' => [''],
            'Booking Reference'           => 'Código De Reserva',
            'TICKET NUMBER'               => 'NÚMERO DE BOLETO',
            'PASSENGER NAME'              => 'APELLIDO DEL PASAJERO',
            // 'ffNumber' => '',
            'ISSUE DATE'                  => 'FECHA DE EMISIÓN',
            'Classes'                     => 'E ?c ?ó ?n ?o ?m ?i ?c ?a',
            'seatConfirmed'               => 'Confirmado',
            'Operated by:'                => 'Operado por:',
            'Fare Basis:'                 => 'Base de tarifa:',
            'Receipt And Payment Details' => 'Detalles Del Pago Y Del Recibo',
            'Total Fare'                  => 'Tarifa total',
        ],
        'it' => [ // it-13966915.eml
            // 'cancelledPhrases' => [''],
            'Booking Reference' => 'Riferimento Prenotazione',
            'TICKET NUMBER'     => ['NUMERO BIGLIETTO', 'NUMERO BIGLIET T O'],
            'PASSENGER NAME'    => 'COGNOME PASSEGGERO',
            // 'ffNumber' => '',
            'ISSUE DATE'        => ['DATA DI EMISSIONE BIGLIETTO', 'DATA DI EMISSIONE BIGLIET T O'],
            'Classes'           => 'ECONOMY',
            // 'seatConfirmed' => '',
            //            'Operated by:' => '',
            'Fare Basis:'                 => ['Base tariffaria:', 'Base t ariffaria:', 'Baset ariffaria:'],
            'Receipt And Payment Details' => 'INFORMAZIONI SUL PAGAMENTO',
            'Total Fare'                  => 'Tariffa totale',
        ],
        'en' => [ // it-17539776.eml, it-131479264.eml
            'cancelledPhrases'            => ['[REFUNDED TICKET/NOT VALID FOR TRAVEL]'],
            'Booking Reference'           => ['Booking Reference', 'Reservation Number', 'Reservat ion Number'],
            'TICKET NUMBER'               => ['TICKET NUMBER', 'T ICKET NUMBER'],
            'PASSENGER NAME'              => ['PASSENGER NAME', 'GUEST NAME'],
            'ffNumber'                    => 'FREQUENT FLYER NUMBER',
            'ISSUE DATE'                  => ['ISSUE DATE', 'ISSUE DAT E'],
            'Classes'                     => 'E ?c ?o ?n ?o ?m ?y',
            'seatConfirmed'               => 'Confirmed',
            'Operated by:'                => ['Operated by:', 'Ope rat e dby:'],
            'Receipt And Payment Details' => ['Receipt And Payment Details', 'Receipt And Payment Det ails', 'Receipt And Tax Invoice Details', 'Receipt And T ax Invoice Det ails'],
            'Total Fare'                  => ['Total Fare', 'Total/Transaction Currency', 'Total/TransactionCurrency', 'Total'],
        ],
    ];

    public $pdf;
    public $providerCode = '';
    public $lang = '';

    private static $companies = [
        'Super Air Jet' => [
            'from' => ['@superairjet.com'],
            'subj' => [
                'en'  => 'FORWARD MY ITINERARY – EY OUTING',
            ],
            'body' => [
                'SUPER AIR JET',
            ],
        ],
    ];
    private $pdfPattern = '.*pdf';

    public function parsePDF(Email $email): void
    {
        $patterns = [
            'dateTime' => '^[ ]*(?<date>[^\n]{6,}?)[ ]*$' // 11/dic/2015
                . '\s+'
                . '^[ ]*(?<time>\d[\d ]{0,2}[ ]*:[ ]*\d[\d ]{1,2}(?:[ ]*[AaPp][Mm])?)[ ]*$', // 1 0 :3 0
            'terminal' => 'TERMINAL ([A-Z\d]+)\b', // TERMINAL C
        ];

        $xpathFragment1 = '[not(./ancestor::*[self::b or self::strong])]';

        $f = $email->add()->flight();

        if ($this->pdf->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-260267182-cancelled.eml
            $f->general()->cancelled();
        }

        $f->general()
            ->confirmation(str_replace(' ', '', $this->nextText($this->t('Booking Reference'))))
            ->traveller(preg_replace('/^(.{2,}?)[.\s]+(?:MISS|MRS|MR|MS)$/', '$1', $this->pdf->FindSingleNode("//text()[{$this->eq($this->t('PASSENGER NAME'))}]/following::text()[normalize-space()][1]" . $xpathFragment1)));

        // TicketNumbers
        $ticketNumber = $this->pdf->FindSingleNode('//text()[' . $this->eq($this->t('TICKET NUMBER')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^(\d[\d ]{5,}\d)$/');

        if ($ticketNumber) {
            if (count($f->getTravellers()) === 1) {
                $travellers = array_column($f->getTravellers(), 0);
                $passengerName = count($f->getTravellers()) === 1 ? array_shift($travellers) : null;
            } else {
                $passengerName = null;
            }
            $f->issued()->ticket(str_replace(' ', '', $ticketNumber), false, $passengerName);
        }

        // accountNumbers
        $ffNumber = $this->pdf->FindSingleNode("//*[{$this->eq($this->t('ffNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^(?:[A-Z]{2}[ ]*)?[\d ]{5,}$/');

        if ($ffNumber) {
            $f->program()->account(str_replace(' ', '', $ffNumber), false);
        }

        // ReservationDate
        $issueDate = $this->pdf->FindSingleNode('//text()[' . $this->eq($this->t('ISSUE DATE')) . ']/following::text()[normalize-space(.)][1]' . $xpathFragment1);

        if ($issueDate) {
            if ($issueDateNormal = $this->normalizeDate($issueDate)) {
                $f->general()
                    ->date(strtotime($issueDateNormal));
            }
        }

        $pdf = implode("\n", $this->pdf->FindNodes('//p[ ./following::p[' . $this->eq($this->t('Receipt And Payment Details')) . '] ]'));
        $segments = $this->splitter('/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d[\d\s]*\s+(?:OK\s+TO\s+FLY|Used\s+to\s+fly|Refunded|Reembo ?lsado|Potvrđeno))/iu', $pdf);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // AirlineName
            // FlightNumber
            if (preg_match('/^\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d[\d\s]*)/', $segment, $matches)) {
                if (!empty($matches['airline'])) {
                    $s->airline()
                        ->name($matches['airline']);
                }
                $s->airline()
                    ->number(preg_replace('/\D/', '', $matches['flightNumber']));
            }

            // DepCode
            // ArrCode
            $airportsText = preg_replace('/^(.+)' . $this->opt($this->t('Fare Basis:')) . '.*$/s', '$1', $segment);

            if (preg_match_all('/\(( ?[A-Z] ?[A-Z] ?[A-Z] ?)\)/', $airportsText, $codes, PREG_PATTERN_ORDER) && count($codes[1]) === 2) {
                $s->departure()
                    ->code(str_replace(' ', '', $codes[1][0]));
                $s->arrival()
                    ->code(str_replace(' ', '', $codes[1][1]));
            }

            // DepDate
            // ArrDate
            if (preg_match_all('/' . $patterns['dateTime'] . '/m', $segment, $dateMatches, PREG_SET_ORDER)) {
                if (count($dateMatches) === 1 && !empty($codes[1][1])) { // it-17539776.eml
                    $dateNormal = $this->normalizeDate($dateMatches[0]['date']);
                    $posDateTime = strpos($segment, $dateMatches[0][0]);
                    $posArrCode = strpos($segment, '(' . $codes[1][1] . ')');

                    if ($dateNormal && $posDateTime !== false && $posArrCode !== false) {
                        if ($posDateTime < $posArrCode) {
                            $s->departure()
                                ->date(strtotime($dateNormal . ', ' . $this->normalizeTime($dateMatches[0]['time'])));
                            $s->arrival()
                                ->noDate();
                        } elseif ($posDateTime > $posArrCode) {
                            $s->departure()
                                ->noDate();
                            $s->arrival()
                                ->date(strtotime($dateNormal . ', ' . $this->normalizeTime($dateMatches[0]['time'])));
                        }
                    }
                } elseif (count($dateMatches) === 2) {
                    $dateDepValue = $this->normalizeDate($dateMatches[0]['date']);

                    if ($dateDepValue) {
                        $dateDep = null;

                        if (!preg_match("/\d{4}$/", $dateDepValue)) {
                            if (!empty($f->getReservationDate())) {
                                $dateDep = EmailDateHelper::parseDateRelative($dateDepValue, $f->getReservationDate(), true, '%D% %Y%');
                            }
                        } else {
                            $dateDep = strtotime($dateDepValue);
                        }
                        $s->departure()
                            ->date(strtotime($this->normalizeTime($dateMatches[0]['time']), $dateDep));
                    }

                    $dateArrValue = $this->normalizeDate($dateMatches[1]['date']);

                    if ($dateArrValue) {
                        $dateArr = null;

                        if (!preg_match("/\d{4}$/", $dateArrValue)) {
                            if (!empty($f->getReservationDate())) {
                                $dateArr = EmailDateHelper::parseDateRelative($dateArrValue, $f->getReservationDate(), true, '%D% %Y%');
                            }
                        } else {
                            $dateArr = strtotime($dateArrValue);
                        }
                        $s->arrival()
                            ->date(strtotime($this->normalizeTime($dateMatches[1]['time']), $dateArr));
                    }
                }
            }

            // DepartureTerminal
            // ArrivalTerminal
            if (preg_match('/(?<departure>.+?)' . $patterns['dateTime'] . '(?<arrival>.+)/ms', $segment, $matches)) {
                if (preg_match('/' . $patterns['terminal'] . '/i', $matches['departure'], $m)) {
                    $s->departure()
                        ->terminal($m[1]);
                }

                if (preg_match('/' . $patterns['terminal'] . '/i', $matches['arrival'], $m)) {
                    $s->arrival()
                        ->terminal($m[1]);
                }
            }

            // Cabin
            $class = $this->re('/(' . $this->t('Classes') . ')/', $segment);

            if ($class) {
                $s->extra()
                    ->cabin(str_replace(' ', '', $class));
            }

            // Seats
            $seat = $this->re("/\n[ ]*(\d{2}[A-z])[ ]*\n+[ ]*\([ ]*{$this->opt($this->t('seatConfirmed'), true)}[ ]*\)[ ]*\n/i", $segment) // 24B
                ?? $this->re("/\n[ ]*(\d ?\d ?[A-z])[ ]*\n+[ ]*\([ ]*{$this->opt($this->t('seatConfirmed'), true)}[ ]*\)[ ]*\n/i", $segment) // 2 4 B
            ;

            if ($seat) {
                $s->extra()
                    ->seats([str_replace(' ', '', $seat)]);
            }

            // Operator
            $operator = $this->re('/' . $this->opt($this->t('Operated by:')) . '\s*(AIR\s*EUROPA|ETIHAD\s*AIRWAYS)/i', $segment);

            if ($operator) {
                $s->airline()
                    ->operator($operator);
            }
        }

        // Currency
        // TotalCharge
        $payment = $this->pdf->FindSingleNode("//*[{$this->eq($this->t('Total Fare'))}]/following::*[normalize-space()][1]");

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)/', $payment, $matches)) {
            // ARS 16269,10
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()
                ->currency($matches['currency'])
                ->total(PriceHelper::parse(preg_replace('/\s+/', '', $matches['amount']), $currencyCode));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flyingblue@airfrance-klm.com') !== false;
    }

    public static function getEmailCompanies()
    {
        return [
            'Super Air Jet' => 1,
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProvider = false;
        $detectLanguage = false;
        $detectCompanies = false;

        $detectProvider = $this->assignProviderHtml($parser->getHeaders());

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) > 0) {
            $this->pdf = clone $this->http;
        }

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if (empty($htmlPdf)) {
                continue;
            }
            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $this->pdf->SetEmailBody($htmlPdf);

            if (!$detectProvider) {
                $detectProvider = $this->assignProviderPdf();
            }

            if (!$detectCompanies) {
                $detectCompanies = $this->assignCompaniesPdf($htmlPdf);
            }

            if (stripos($htmlPdf, 'Please verify flight times') !== false) {
                return false;
            }

            $detectLanguage = $this->assignLangPdf();

            if (($detectProvider && $detectLanguage) || ($detectCompanies && $detectLanguage)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $detectCompanies = false;
        $detectProvider = $this->assignProviderHtml($parser->getHeaders());

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) > 0) {
            $this->pdf = clone $this->http;
        }

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if (empty($htmlPdf)) {
                continue;
            }
            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $this->pdf->SetEmailBody($htmlPdf);

            if (!$detectProvider) {
                $detectProvider = $this->assignProviderPdf();
            }

            if (!$detectCompanies) {
                $detectCompanies = $this->assignCompaniesPdf($htmlPdf);
            }

            if (!empty($this->providerCode)) {
                $email->setProviderCode($this->providerCode);
            }

            if ($this->assignLangPdf()) {
                $this->parsePDF($email);
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

    public static function getEmailProviders()
    {
        return ['airfrance', 'alitalia', 'aerolineas', 'ethiopian', 'hawaiian', 'velocity', 'airserbia'];
    }

    private function nextText($field): ?string
    {
        return $this->pdf->FindSingleNode('//text()[' . $this->eq($field) . ']/following::text()[normalize-space(.)][1]');
    }

    private function assignProviderHtml(array $headers): bool
    {
        $condition1 = $this->http->XPath->query('//*[contains(.,"www.airfrance.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.airfrance.com")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'airfrance';

            return true;
        }

        $condition1 = $this->http->XPath->query('//*[contains(normalize-space(),"thank you for choosing Alitalia service") or contains(normalize-space(),"On Alitalia operated flight you may") or contains(.,"www.alitalia.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.alitalia.com")]')->length > 0;

        $condition3 = (isset($headers['from']) && stripos($headers['from'], '@alitalia.sabre.com') !== false)
            || (isset($headers['subject']) && stripos($headers['subject'], 'ALITALIA ELECTRONIC TICKET RECEIPT') !== false);

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'alitalia';

            return true;
        }

        return false;
    }

    private function assignProviderPdf(): bool
    {
        if ($this->pdf->XPath->query('//a[contains(@href,"//aerolineas.com.ar") or contains(@href,"www.aerolineas.com.ar")]')->length > 0
            || $this->pdf->XPath->query('//*[contains(normalize-space(),"Aerolíneas Argentinas S.A., is exempt")]')->length > 0
        ) {
            $this->providerCode = 'aerolineas';

            return true;
        }

        if ($this->pdf->XPath->query('//a[contains(@href,".alitalia.com/") or contains(@href,"www.alitalia.com")]')->length > 0
            || $this->pdf->XPath->query("//*[contains(normalize-space(),\"via Alitalia's website\")]")->length > 0
        ) {
            $this->providerCode = 'alitalia';

            return true;
        }

        if ($this->pdf->XPath->query('//a[contains(@href,".hawaiianairlines.com/") or contains(@href,"www.hawaiianairlines.com")]')->length > 0) {
            $this->providerCode = 'hawaiian'; // it-131479264.eml

            return true;
        }

        if ($this->pdf->XPath->query('//a[contains(@href,".virginaustralia.com/") or contains(@href,"www.virginaustralia.com")]')->length > 0) {
            $this->providerCode = 'velocity'; // it-260267182-cancelled.eml

            return true;
        }

        if ($this->pdf->XPath->query('//a[contains(@href,".airserbia.com/") or contains(@href,"www.airserbia.com")]')->length > 0) {
            $this->providerCode = 'airserbia';

            return true;
        }

        if ($this->pdf->XPath->query('//a[contains(@href,"//ethiopian.com") or contains(@href,"www.ethiopian.com")]')->length > 0
            || $this->pdf->XPath->query('//*[contains(normalize-space(),"ETHIOPIAN AIRLINES")]')->length > 0
        ) {
            $this->providerCode = 'ethiopian';

            return true;
        }

        return false;
    }

    private function assignLangPdf(): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->pdf->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function normalizeDate(?string $string): ?string
    {
        if (preg_match('/^(\d[\d ]{0,2})[ ]*\/[ ]*([^\d\W][\w ]{2,})[ ]*\/[ ]*(\d[\d ]{2,5}\d)$/u', $string, $matches)) { // 11/dic/2015    |    2 6 /Jun/2 0 1 8
            $day = str_replace(' ', '', $matches[1]);
            $month = str_replace(' ', '', $matches[2]);
            $year = str_replace(' ', '', $matches[3]);
        } elseif (preg_match('/^(\d[\d ]{0,2})[ ]*([^\d\W][\D ]{2,}?)[ ]*(\d[\d ]{2,5}\d)$/u', $string, $matches)) { // 0 2MAY20 18
            $day = str_replace(' ', '', $matches[1]);
            $month = str_replace(' ', '', $matches[2]);
            $year = str_replace(' ', '', $matches[3]);
        } elseif (preg_match('/^(\d[\d ]{0,2})[ ]*\/[ ]*([^\d\W][\w ]{2,})$/u', $string, $matches)) { // 30 /m ar
            $day = str_replace(' ', '', $matches[1]);
            $month = str_replace(' ', '', $matches[2]);
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

    private function normalizeTime($string = ''): string
    {
        return preg_replace('/[ ]+([:\d])/', '$1', $string); // 18 :0 5    ->    18:05
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($re, $text): array
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($s) : preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '/'));
    }

    private function assignCompaniesPdf($textBody): bool
    {
        foreach (self::$companies as $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($textBody, $search) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
