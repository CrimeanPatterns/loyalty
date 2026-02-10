<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-105723128.eml, aeroplan/it-16489064.eml, aeroplan/it-35220978.eml, aeroplan/it-670817261-fr.eml";

    public $reFrom = ["aircanada.com"];
    public $reBody = [
        'en' => ['Booking Reference', 'Passengers'],
        'de' => ['Buchungsreferenz', 'Passagiere'],
        'it' => ['Riferimento prenotazione', 'Passeggeri'],
        'fr' => ['Numéro de réservation', 'Passagers'],
    ];
    public $reSubject = [
        'Air Canada Confirmation',
        '(Booking Reference:',
        '(Buchungsreferenz:',
        '(Riferimento prenotazione:',
    ];
    public $lang = '';
//    public $pdfNamePattern = ".*(?:Booking_Confirmation|Buchungsbest_tigung).*pdf";
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'flightSeparators' => ['Depart', 'Return', 'Flight'],
            // 'Booking Reference' => '',
            // 'Date of issue' => '',
            // 'Passengers' => '',
            'Infant (On lap)'  => ['Infant (In seat)', 'Infant (On lap)'],
            'passengersEnd'    => ['Purchase summary', 'Check-in and boarding gate deadlines'],
            // 'Purchase summary' => '',
            // 'Travel Options' => '',
            // 'Seats' => '',
            'Ticket Number'    => ['Ticket Number', 'Ticket number'],
            // 'GRAND TOTAL' => '',
            // 'Check-in and boarding gate deadlines' => '',
            // 'Operated by' => '',
            'hours'            => 'hr',
            'Cabin'            => 'Cabin',
            // 'Terminal' => '',
            // 'Number of passengers' => '',
            // 'per passenger' => '',
            'adult'            => ['adult', 'adults'],
            'airFareHeader'    => ['Air Transportation Charges', 'Air transportation charges'],
            // 'Carrier surcharges' => '',
            'feesHeader'       => ['Taxes, Fees and Charges', 'Taxes, fees and charges'],
            'feesEnd'          => ['Total airfare and taxes before options (per passenger)', 'Total before options (per passenger)'],
            'seatFeesHeader'   => ['Seat selection'],
            'seatFeesEnd'      => ['Total with options and seat selection fee:'],
        ],
        'de' => [
            'flightSeparators'                     => ['Abflug', 'Return'],
            'Booking Reference'                    => 'Buchungsreferenz',
            'Date of issue'                        => 'Ausstellungsdatum',
            'Passengers'                           => 'Passagiere',
            'passengersEnd'                        => 'Buchungsübersicht',
            'Purchase summary'                     => 'Buchungsübersicht',
            // 'Travel Options' => '',
            'Seats'                                => 'Sitzplätze',
            'Ticket Number'                        => 'Ticketnummer',
            'GRAND TOTAL'                          => 'GESAMTSUMME',
            'Check-in and boarding gate deadlines' => 'Meldeschlusszeiten beim Check-in und am Abfluggate',
            'Operated by'                          => 'Durchgeführt von',
            'hours'                                => 'Std.',
            // 'Cabin' => '',
            // 'Terminal' => '',
            // 'Number of passengers' => '',
            // 'per passenger' => '',
            // 'adult' => '',
            // 'airFareHeader'=> '',
            // 'Carrier surcharges' => '',
            // 'feesHeader' => '',
            // 'feesEnd' => '',
            // 'seatFeesHeader' => '',
            // 'seatFeesEnd' => '',
        ],
        'it' => [
            'flightSeparators'                     => ['Parte', 'Ritorno'],
            'Booking Reference'                    => 'Riferimento prenotazione',
            'Date of issue'                        => 'Data di emissione',
            'Passengers'                           => 'Passeggeri',
            'passengersEnd'                        => 'Riepilogo acquisto',
            'Purchase summary'                     => 'Riepilogo acquisto',
            'Travel Options'                       => 'Opzioni di viaggio',
            'Seats'                                => 'Posti',
            'Ticket Number'                        => 'Numero del biglietto',
            'GRAND TOTAL'                          => 'TOTALE COMPLESSIVO',
            'Check-in and boarding gate deadlines' => "Termine per la presentazione al check-in e all'imbarco",
            'Operated by'                          => 'Operato da',
            'hours'                                => 'hr',
            'Cabin'                                => 'Cabina',
            // 'Terminal' => '',
            'Number of passengers'                 => 'Numero di passeggeri',
            'per passenger'                        => 'per ogni passeggero',
            // 'adult' => '',
            'airFareHeader'=> 'Addebiti per il trasporto aereo',
            // 'Carrier surcharges' => '',
            'feesHeader'   => 'Tasse, supplementi e addebiti',
            'feesEnd'      => ['Totale tariffa aerea e tasse, tutti i passeggeri', 'Totale prima dei servizi opzionali (per  '],
            // 'seatFeesHeader' => '',
            // 'seatFeesEnd' => '',
        ],
        'fr' => [
            'flightSeparators'                     => ['Départ', 'Retour', 'Vol'],
            'Booking Reference'                    => 'Numéro de réservation',
            'Date of issue'                        => 'Date de délivrance',
            'Passengers'                           => 'Passagers',
            'passengersEnd'                        => ["Sommaire de l'achat", 'Délais d’enregistrement et d’arrivée à la porte d’embarquement', "Délais d'enregistrement et d'arrivée à la porte d'embarquement"],
            'Purchase summary'                     => "Sommaire de l'achat",
            'Travel Options'                       => 'Options de voyage',
            'Seats'                                => 'Places',
            'Ticket Number'                        => 'Numéro de billet',
            'GRAND TOTAL'                          => 'TOTAL GÉNÉRAL',
            'Check-in and boarding gate deadlines' => ['Délais d’enregistrement et d’arrivée à la porte d’embarquement', "Délais d'enregistrement et d'arrivée à la porte d'embarquement"],
            'Operated by'                          => 'Exploité par',
            'hours'                                => 'h',
            'Cabin'                                => 'Cabine',
            'Terminal'                             => 'Aérogare',
            // 'Number of passengers' => '',
            'per passenger'      => 'par passager',
            'adult'              => 'adulte',
            'airFareHeader'      => 'Frais de transport aérien',
            'Carrier surcharges' => 'Suppléments du transporteur',
            'feesHeader'         => 'Taxes, frais et droits',
            'feesEnd'            => 'Total avant les options (par passager)',
            // 'seatFeesHeader' => '',
            // 'seatFeesEnd' => '',
        ],
    ];

    /** @var \HttpBrowser */
    private $pdf;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug("Can't determine a language!");

                        continue;
                    }
                    $this->pdf = clone $this->http;
                    $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                    $NBSP = chr(194) . chr(160);
                    $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));

                    if (!$this->parseEmail($text, $email)) {
                        return $email;
                    }
                } else {
                    return $email;
                }
            }
        } else {
            return $email;
        }

        $email->setType('ReservationConfirmationPdf' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Air Canada') !== false) || (stripos($text, 'www.aircanada.com') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && strpos($headers['from'], 'aircanada.com') !== false) {
            return true;
        }

        if (isset($headers['subject']) && strpos($headers['subject'], 'Air Canada') !== false) {
            foreach ($this->reSubject as $re) {
                if (strpos($headers['subject'], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($textPDF, Email $email): bool
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/^[ ]*{$this->opt($this->t('Booking Reference'))}[ :]+([A-Z\d]{5,})(?:[ ]{2}|\n)/m", $textPDF))
            ->date($this->normalizeDate($this->re("#{$this->opt($this->t('Date of issue'))}[ :]+(.+)#",
                $textPDF)));

        $passengersText = trim($this->findСutSection($textPDF, preg_replace("/(.+)/", "$1\n", $this->t('Passengers')), $this->t('passengersEnd')), "\n");

        if (empty($passengersText)) {
            $this->logger->debug('other format passengersText');

            return false;
        }

        $tablePos = [0];

        if (preg_match("/^(.+ ){$this->opt($this->t('Travel Options'))}/m", $passengersText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ )(?:\n\s*)?{$this->opt($this->t('Seats'))}/m", $passengersText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->splitCols($passengersText, $tablePos);

        if (count($table) === 0) {
            $this->logger->debug('Wrong passengers table!');

            return false;
        }

        $travellers = [];

        if (preg_match_all("/^[ ]*({$patterns['travellerName']})\s+{$this->opt($this->t('Ticket Number'))}/mu", $table[0], $tMatches)) {
            $f->general()->travellers($travellers = $tMatches[1]);
        } elseif (strpos($passengersText, $this->t('Travel Options')) !== 0
            && preg_match_all("/^[ ]*({$patterns['travellerName']})[ ]+(?:{$this->opt($this->t('Travel Options'))}|{$this->opt($this->t('Seats'))})/mu", $passengersText, $tMatches)
        ) {
            $f->general()->travellers($travellers = $tMatches[1]);
        }

        if (preg_match_all("/^[ ]*({$patterns['travellerName']})\s+{$this->opt($this->t('Infant (On lap)'))}/mu", $table[0], $tMatches)) {
            $f->general()->infants($tMatches[1]);
        }

        if (preg_match_all("/^[ ]*([-A-Z\d]{5,})$/m", $table[0], $accountMatches)) {
            $accounts = array_filter(preg_replace("/^\s*\d{3}(?:\d{10}|X{10})\s*$/", '', $accountMatches[1]));

            foreach ($accounts as $account) {
                if (preg_match("/\s+(?<pax>{$this->opt($travellers)}).*\n(?:.+\n){1,3}\s+{$this->opt($this->t('Ticket Number'))}.*\n(?:.*\n){0,3} *(?<name>.+?)( {3}.*)?\n\s*$account/", $textPDF, $m)) {
                    $f->program()
                        ->account($account, false, $m['pax'], $m['name']);
                }
            }
        }

        $flSeats = [];

        if (preg_match_all("/((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+)[ ]+(-|\d{1,3}[A-z])\b/", $table[count($table) - 1], $seatMatches, PREG_SET_ORDER)) {
            foreach ($seatMatches as $v) {
                $flSeats[$v[1]][] = $v[2];
            }
        }
        $flSeats = array_filter($flSeats, function ($s) {
            return preg_match('/^\d{1,3}[A-z]$/', $s[0]) > 0;
        });

        $ticketNumbers_temp = [];

        if (preg_match_all("/{$this->opt($this->t('Ticket Number'))}.*\n(?:.+\n)?[ ]*(\d{3}[-]*?[X]{5,}[-]*?[X]{1,2})(?:[ ]{2}|\n|$)/", $table[0], $ticketMatches)) {
            foreach ($ticketMatches[1] as $number) {
                if (!in_array($number, $ticketNumbers_temp)) {
                    $pax = $this->re("/({$this->opt($travellers)})\n+\s*{$this->opt($this->t('Ticket Number'))}\n+\s*{$this->opt($number)}/u", $table[0]);

                    $f->addTicketNumber($number, preg_match('/[X]{5,}/', $number) ? true : false, $pax);
                    $ticketNumbers_temp[] = $number;
                }
            }
        }

        if (preg_match_all("/{$this->opt($this->t('Ticket Number'))}.*\n(?:.+\n)?[ ]*(\d{3}[-]*?\d{5,}[-]*?\d{1,2})(?:[ ]{2}|\n|$)/", $table[0], $ticketMatches)) {
            foreach ($ticketMatches[1] as $number) {
                if (!in_array($number, $ticketNumbers_temp)) {
                    $pax = $this->re("/({$this->opt($travellers)})\n*\s*{$this->opt($this->t('Ticket Number'))}\n*\s+$number/u", $table[0]);

                    if (!empty($pax)) {
                        $f->addTicketNumber($number, false, $pax);
                    } else {
                        $f->addTicketNumber($number, false);
                    }
                    $ticketNumbers_temp[] = $number;
                }
            }
        }

        $purchaseText = $this->re("/\n[ ]*{$this->opt($this->t('Purchase summary'))}(?:[ ]{2,}|\n+)(.{2,})/s", $textPDF);
        $purchaseText = preg_replace("/^(.{2,}?)\n+[ ]*{$this->opt($this->t('Check-in and boarding gate deadlines'))}(?:[ ]{2}|\n).*$/s", '$1', $purchaseText);

        $tablePos = [0];

        if (preg_match("/^(.*[ ]{2}){$this->opt($this->t('GRAND TOTAL'))}/m", $purchaseText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($purchaseText, $tablePos);

        if (count($table) === 2) {
            $purchaseText = $table[1];
        }

        $currencyCode = null;
        $currencyRow = $this->re("/^[ ]*{$this->opt($this->t('GRAND TOTAL'))}(.+)$/m", $purchaseText);
        $currencyCodes = [
            'CAD' => 'Canadian dollars',
            'CNY' => 'China - yuan',
            'EUR' => 'Euro',
            'USD' => 'US dollars',
        ];

        foreach ($currencyCodes as $key => $value) {
            if (preg_match("/\(\s*{$this->opt($value)}\s*\)/i", $currencyRow) > 0) {
                $currencyCode = $key;

                break;
            }
        }

        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('GRAND TOTAL'))}.*?[ ]{2,}(.*\d.*)$/m", $purchaseText)
            ?? (preg_match("/^[ ]{30,}(.*\d)\n+[ ]*{$this->opt($this->t('GRAND TOTAL'))}[^\d\n]*$/m", $purchaseText, $m) && !preg_match("/[ ]{2}/", $m[1]) ? $m[1] : null)
            ?? $this->http->FindSingleNode("//text()[normalize-space()='TOTAL CHARGES']/ancestor::tr[1]/descendant::td[2]")
        ;

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<integer>\d[,.‘\'\d]*)[. ]*(?<decimals>\d{2})$/u', $totalPrice, $matches)
            || preg_match('/^\s*(?<points>\d[\d,]* pts)\s*$/u', $totalPrice, $matches)
        ) {
            if (isset($matches['points'])) {
                $f->price()
                    ->spentAwards($matches['points']);
            } else {
                // $30723    |    $307.23    |    $307 23
                if (!$currencyCode) {
                    $currencyCode = $this->normalizeCurrency($matches['currency']);
                }

                $matches['integer'] = preg_replace('/\D*$/', '', $matches['integer']);
                $matches['amount'] = $matches['integer'] . '.' . $matches['decimals'];

                $f->price()
                    ->currency($currencyCode ?? $matches['currency'])
                    ->total(PriceHelper::parse($matches['amount'], $currencyCode));

                if (preg_match("/\n\n {20,}(?<points>\d[\d,]* pts)\n {0,11}{$this->opt($this->t('GRAND TOTAL'))}.*?[ ]{2,}.*\d.*(?:\n|$)/", $purchaseText, $m)) {
                    $f->price()
                        ->spentAwards($m['points']);
                }
            }

            $passengersCount = $this->re("/^[ ]*{$this->opt($this->t('Number of passengers'))}\s*[ ]{2,}[☓x ]*(\d{1,3})$/imu", $purchaseText)
                ?? $this->re("/^[ ]{30,}[☓x ]*(\d{1,3})\n+[ ]*{$this->opt($this->t('Number of passengers'))}$/imu", $purchaseText)
            ;

            if (empty($this->re("/({$this->opt($this->t('Number of passengers'))})/", $purchaseText))
                && empty($this->re("/({$this->opt($this->t('per passenger'))})/", $purchaseText))
            ) {
                // price for all passengers, not per passenger
                $passengersCount = 1;
            } elseif (empty($this->re("/({$this->opt($this->t('Number of passengers'))})/", $purchaseText))
                && !empty($this->re("/({$this->opt($this->t('per passenger'))})/", $purchaseText))
            ) {
                $st = $this->re("/^((.*\n+){1,5})/", $purchaseText);
                $st = $this->re("/.+ {5,}(.*?{$this->opt($this->t('adult'))}[[:alpha:]]{0,3})\n/u", $st);

                if (preg_match_all("/\b(\d+)\b/", $st, $pCountMatches)) {
                    $passengersCount = array_sum($pCountMatches[1]);
                }
            }

            if ($passengersCount !== null) {
                // baseFare
                $baseFare = [];
                $baseFareAward = [];
                $baseFareText = $this->re("/^[ ]*{$this->opt($this->t('airFareHeader'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('feesHeader'))}$/im", $purchaseText);
                preg_match_all("/(.+)[ ]{3,}(.*\d.*)$/m", $baseFareText, $bfMatches);

                foreach ($bfMatches[2] as $i => $bfCharge) {
                    if (preg_match("/\({$this->opt($this->t('in points'))}\)/", $bfMatches[0][$i])) {
                        $baseFareAward[] = $passengersCount * (int) preg_replace('/\D*/', '', $bfCharge);
                    } else {
                        if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d]*)$/u', $bfCharge, $m)) {
                            $bfAmount = PriceHelper::parse($m['amount'], $currencyCode);

                            if (preg_match("/^{$this->opt($this->t('Carrier surcharges'))}/", $bfMatches[1][$i])) {
                                $f->price()->fee($bfMatches[1][$i], $bfAmount * $passengersCount);
                            } else {
                                $baseFare[] = $bfAmount !== null ? $bfAmount * $passengersCount : null;
                            }
                        }
                    }
                }

                if (count($baseFare) > 0 && !in_array(null, $baseFare, true)) {
                    $f->price()->cost(array_sum($baseFare));
                } elseif (count($baseFareAward) > 0 && !in_array(null, $baseFareAward, true)) {
                    $f->price()->spentAwards(array_sum($baseFareAward));
                }

                // fees
                $feesText = $this->re("/^[ ]*{$this->opt($this->t('feesHeader'))}\n+([\s\S]+?)\n+[ ]*(?:{$this->opt($this->t('feesEnd'))}(?:[ ]{2}|$)|\n\n{$this->opt($this->t('GRAND TOTAL'))} *\()/im", $purchaseText);
                preg_match_all("/^[ ]{0,10}(?<name>\S.{0,80}?\S)\n?[ ]{5,}(?<charge>.*\d.*)$/m", $feesText, $feesMatches, PREG_SET_ORDER);

                foreach ($feesMatches as $feeRow) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d]*)$/u', $feeRow['charge'], $m)) {
                        $feeAmount = PriceHelper::parse($m['amount'], $currencyCode);
                        $f->price()->fee(rtrim($feeRow['name'], ': '), $feeAmount !== null ? $feeAmount * $passengersCount : null);
                    }
                }

                $feeSeatText = $this->re("/^[ ]*{$this->opt($this->t('seatFeesHeader'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('seatFeesEnd'))}(?:[ ]{2}|$)/im", $purchaseText);

                preg_match_all("/^(?<name>.+\-)[ ]{10,}(?<fee>\d[\d\.\,]*)\n(?<name2>.+)?/mu", $feeSeatText, $m1);
                preg_match_all("/^(?<name>.+[^\s\-])[ ]{10,}(?<fee>[\d\.\,]+)\n/mu", $feeSeatText, $m2);

                foreach ($m1[1] ?? [] as $key => $rows) {
                    $f->price()->fee($m1['name2'][$key] !== null ? $m1['name'][$key] . ' ' . $m1['name2'][$key] : $m1['name'][$key], $m1['fee'][$key]);
                }

                foreach ($m2[1] ?? [] as $key => $rows) {
                    $f->price()->fee($m2['name'][$key], $m2['fee'][$key]);
                }
            }
        }

        $passengerTextArray = preg_split("/(?:\n\n|$)/", $passengersText);

        $textPDF = str_replace('Passengers:', 'CtrlStrPax:', $textPDF);
        $mainBlock = $this->re("/^(.+?\n+)[ ]*{$this->opt($this->t('Passengers'))}(?:[ ]{2}|\n)/s", $textPDF);
        $arr = $this->splitter("/^([ ]*{$this->opt($this->t('flightSeparators'))})(?: \d{1,3})?(?:[ ]{2}|$)/m", $mainBlock);

        foreach ($arr as $a) {
            $segs = $this->splitter("/(.+ \d+:\d+[ ]+\d+:\d+)/", $a);

            foreach ($segs as $seg) {
                $s = $f->addSegment();
                $table = $this->re("#(.+?)(?:\n\n\n|\n+$)#s", $seg);
                $pos = [
                    0,
                    mb_strlen($this->re("#(.+?)\d+:\d+ +\d+:\d+#", $table)),
                    mb_strlen($this->re("#(.+?\d+:\d+ +)\d+:\d+#", $table)),
                    mb_strlen($this->re("#(^.+? +)(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+ +#m", $table)),
                    mb_strlen($this->re("#(^.+? +(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+ ) +#m", $table)),
                ];
                $table = $this->splitCols($table, $pos);
                // $this->logger->debug('$table = '.print_r( $table,true));

                if (count($table) !== 5) {
                    $this->logger->debug('other format segments');

                    return false;
                }
                $date = $this->normalizeDate(preg_replace('/\s+/', ' ', trim($table[0])));
                array_shift($table);

                if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)\b#", $table[2], $v)) {
                    $s->airline()
                        ->name($v[1])
                        ->number($v[2]);

                    if (!empty($flSeats[$v[1] . $v[2]])) {
                        $seats = $flSeats[$v[1] . $v[2]];

                        foreach ($seats as $seat) {
                            $pax = '';

                            foreach ($passengerTextArray as $passengerText) {
                                if (!empty($pax)) {
                                    continue;
                                }

                                $pax = $this->re("/({$this->opt($travellers)}).+\s$v[1]$v[2]\s+$seat/su", $passengerText);
                            }

                            if (!empty($pax)) {
                                $s->addSeat($seat, false, false, $pax);
                            } else {
                                $s->addSeat($seat);
                            }
                        }
                    }
                }

                if (preg_match("/^\s*(\d{1,3}[ ]*{$this->opt($this->t('hours'))}\s*\d{1,2})?\s+(.*?)\s*{$this->opt($this->t('Operated by'))}[ ]*:\s*([\S\s]+?)\s*\|\s*(\w[-\w ]+)(?:\n+[ ]*([\S\s]{2,}))?/", $table[3], $m)) {
                    if (!empty($m[1])) {
                        $s->extra()->duration($m[1]);
                    }
                    $s->extra()
                        ->aircraft($m[4]);

                    $m[2] = preg_replace("/^\s*{$this->opt($this->t('Cabin'))}\s*:\s*/", '', $m[2] ?? '');

                    if (!empty($m[2]) && (
                        preg_match("#^(.*?)\s*([A-Z]{1,2})\s*$#", $m[2], $v)
                        || preg_match("#^(.*?)\s*\(\s*([A-Z]{1,2})\s*\)\s*$#", $m[2], $v)
                    )) {
                        $s->extra()
                            ->cabin($v[1], true)
                            ->bookingCode($v[2]);
                    } elseif (!empty($m[5]) && preg_match("#\s*(?:\|.+\n+)?\a*(.*?)\s*([A-Z]{1,2})\s*$#", $m[5], $v)) {
                        $s->extra()
                            ->cabin($v[1], true)
                            ->bookingCode($v[2]);
                    } elseif (!empty(trim($m[2]))) {
                        $s->extra()->cabin(trim($m[2]), true);
                    }

                    if (preg_match("#Air Canada Express\s*-\s*(.+)#s", $m[3], $v)) {
                        $s->airline()
                            ->operator($this->nice($v[1]));
                    } else {
                        $s->airline()
                            ->operator($this->nice($m[3]));
                    }
                }

                if (preg_match("#(\d+:\d+)([^\n]*)\s+([^\(]+?)\s*\(([A-Z]{3})\)#s", $table[0], $m)) {
                    $s->departure()
                        ->date(strtotime($m[1], $date))
                        ->name(str_replace("\n", ', ', trim($m[3])))
                        ->code($m[4]);
                }

                if (!empty($terminal = $this->re("/{$this->opt($this->t('Terminal'))}\s+(.+)/", $table[0]))) {
                    $s->departure()->terminal($terminal);
                }

                /*05:50
                Vienna                                 1hr20
                OS384
                Vienna Int. (VIE),                     Economy S
                Terminal 3                             Operated by: Austrian Airlines | E95*/

                if (preg_match("/\d+ *[{$this->t('hours')}]+\s*\d+/", $table[1])) {
                    if (preg_match("/(\d+:\d+)\s*(.+)\s*(\d+ *hr+\s*\d+)\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,6})\s*(.+)\s+\(\s*([A-Z]{3})\s*\),\s+(\w+)\s*([A-Z]{1,2})\s*{$this->opt($this->t('Terminal'))}\s*(.+)\s+{$this->opt($this->t('Operated by'))}[ ]*:\s*(.+)\s*\|\s*(.+)/su", $table[1], $m)) {
                        $s->arrival()
                            ->date(strtotime($m[1], $date))
                            ->name(str_replace("\n", ', ', trim($m[2])))
                            ->code($m[7]);

                        $s->airline()
                            ->name($m[4])
                            ->number($m[5]);

                        $s->extra()
                            ->cabin($m[8])
                            ->bookingCode($m[9]);
                    }
                } else {
                    if (preg_match("/(\d+:\d+)([^\n]*)\s+([^\(]+?)\s*\(\s*([A-Z]{3})\s*\)/s", $table[1], $m)) {
                        $s->arrival()
                            ->date(strtotime($m[1], $date))
                            ->name(str_replace("\n", ', ', trim($m[3])))
                            ->code($m[4]);

                        if (!empty($m[2]) && preg_match("#([\+\-])\s*(\d+)#", $m[2], $v)) {
                            $s->arrival()
                                ->date(strtotime($v[1] . ' ' . $v[2] . ' days', $s->getArrDate()));
                        }
                    }
                }

                if (stripos($table[1], 'Operated') !== false) {
                    if (!empty($terminal = $this->re("/{$this->opt($this->t('Terminal'))}\s+(.+)\s*{$this->opt($this->t('Operated by'))}/", $table[1]))) {
                        $s->arrival()->terminal($terminal);
                        $s->airline()
                            ->operator($this->re("/{$this->opt($this->t('Operated by'))}\s*:(.+)\s*\|/", $table[1]));
                        $s->extra()
                            ->aircraft($this->re("/\|\s*(.+)/", $table[1]));
                    }
                } else {
                    if (!empty($terminal = $this->re("/{$this->opt($this->t('Terminal'))}\s+(.+)/", $table[1]))) {
                        $s->arrival()->terminal($terminal);
                    }
                }

                if (!empty($s->getAircraft()) && strcasecmp($s->getAircraft(), 'TRN') === 0
                    && (!empty($s->getDepName()) && strcasecmp($s->getDepName(), 'Rail&Fly') === 0 | !empty($s->getArrName()) && strcasecmp($s->getArrName(), 'Rail&Fly') === 0)
                ) {
                    // it-670817261-fr.eml
                    $f->removeSegment($s, 'Not flight segment!');
                }
            }
        }

        return true;
    }

    private function normalizeDate($strDate)
    {
        // $this->logger->debug('$strDate = '.print_r( $strDate,true));
        $in = [
            // 8 Mar, 2018    |    24 avr., 2024
            // Monday 17 Mar, 2025
            '/^\s*(?:[[:alpha:]\-]+\s+)?(\d{1,2})\s+([[:alpha:]]+)[,.\s]+(\d{4})$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        // $this->logger->debug('$strDate = '.print_r( preg_replace($in, $out, $strDate),true));
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $strDate));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function nice($str): string
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function normalizeCurrency($string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'CAD' => ['CA $'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
