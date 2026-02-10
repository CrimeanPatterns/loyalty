<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

// $dictionary used in tcase/It5045494
class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-10705830.eml, aeroflot/it-11715974.eml, aeroflot/it-2760661.eml, aeroflot/it-27815929.eml, aeroflot/it-2820622.eml, aeroflot/it-33694676.eml, aeroflot/it-44621234.eml, aeroflot/it-5587431.eml, aeroflot/it-6721468.eml, aeroflot/it-6996591.eml";

    public $reSubject = [
        'es' => ['Confirmación de Viaje'],
        'en' => ['Electronic ticket receipt', 'AIR CONFIRMATION'],
    ];
    public static $reBody = [
        //        'mta' => ['MTA Travel', '@mtatravel.com.au'], // DON'T ADD, while mta is ignoreTraxo
        'powertravel'  => ['POWER TRAVEL', '@POWERTRAVEL.NET'],
        'vietnam'      => ['Vietnam Airline', 'www.vietnamairlines.com'],
        'tamair'       => ['LATAM Airlines Group', 'Visit LATAM.com'],
        'ctmanagement' => ['Corporate Travel Management', 'travelctm.com'],
        'flightcentre' => ['Flight Centre Group Travel'],
        'mabuhay'      => ['www.philippineairlines.com'],
        'checkmytrip'  => ['skytravelagent.de'],
        'wagonlit'     => ['CWT HOLIDAY TOURS'],
        'oman'         => ['OMAN AIR', 'OMANAIR.COM'],
        'swissair'     => ['SWISS INTERNATIONAL AIR'],
        'etihad'       => ['ETIHAD AIRWAYS Contact', 'Etihad.com'],
        'ethiopian'    => ['Ethiopian Airlines'],
        'hoggrob'      => ['HRGWORLDWIDE.COM'],
        'trplace'      => ['TRAVEL PLACE'],
        'aeroflot'     => ['aeroflot', 'CAMBODIAANGKOR', 'AEROLINEAS GALAPAGOS S.A'],
        'aeromexico'   => ['aeromexico'],
        'kulula'       => ['kulula'],
        'bcd'          => ['BCD Travel', 'Philscan Travel and Tours, Inc./BCD Travel'],
        'westjet'      => ['WestJet', 'WESTJET'],
        'airmaroc'     => ['ROYALAIR MAROC'],
        'tedge'        => ['Travel Edge'],
        'frosch'       => ['Frosch Travel'],
        'tcase'        => [// is not tcase. just for no skip
            'ARGOTRAVEL - GENEVE', '@ARGOTRAVEL.CH',
            'METROPOLITAN TOURING',
            'Continental Travel',
            'Valerie Wilson Travel',
            'AIR CLUB',
            'HOLIDAY TOURS AND TRAVEL',
            'STRONG TRAVEL SERVICES, INC', '@STRONGTRAVEL.COM',
            'BARCELO VIAJES',
            'Protravel International',
            'Marsman Drysdale Travel, Inc',
            'BROWNELL', 'www.brownelltravel.com',
            'LARGAY TRAVEL INC',
            'American Express Global',
        ],
    ];
    public $langDetectors = [
        'es' => ['Información De Vuelo', 'Detalles Del Itinerario'],
        'en' => ['Itinerary Details'],
        'ru' => ['Сведения О Маршруте'],
        'pt' => ['Detalhes Do Itinerário'],
        'pl' => ['Szczegóły Planu Podróży'],
    ];

    // example: name="Recibo de pasaje electrónico, 21 octubre para NICOLE AMERENA SERRANO.pd"
    // "Electronic ticket receipt, May 12 for MR OCTAVIAN CUCU.pdf"
    public $pdfPattern = '\w.+?pdf?';

    public static $dictionary = [
        'ru' => [
            'Departure'                   => ['ОТПРАВЛЕНИЕ', 'ОТ ПРАВЛЕНИЕ'],
            'RESERVATION CODE'            => 'КОД БРОНИРОВАНИЯ',
            'Prepared For'                => ['Подготовлен для', 'Под готовлен д ля'],
            'TICKET NUMBER'               => 'НОМЕР БИЛЕТА',
            'NÚMERO DE VIAJERO FRECUENTE' => 'НОМЕР УЧАСТНИКА ПРОГРАММЫ ЛОЯЛЬНОСТИ',
            'ISSUE DATE'                  => ['ДАТА ВЫДАЧИ БИЛЕТА', 'Д АТА ВЫД АЧИ БИЛЕТА'],
            'Total Amount'                => ['Итого по тарифу/сборам', 'Общая стоимость'],
            'Fare'                        => 'Тариф',
            'Equivalent'                  => 'Эквивалент',
            'Taxes/Fees/Carrier'          => ['Сборы', 'Налоги / пошлины / сборы'],
            'Itinerary Details'           => ['Сведения О Маршруте'],
            'Allowances'                  => ['Нормы Провоза Багажа'],
            //			'Operated by:' => '',
            'Time'        => 'Время',
            'Class'       => 'Класс',
            'Seat Number' => ['Номер места', 'Но мер места'],
        ],
        'pl' => [
            'Departure'                   => ['WYLOT'],
            'RESERVATION CODE'            => 'KOD REZERWACJI',
            'Prepared For'                => ['Wystawiony dla'],
            'TICKET NUMBER'               => 'NUMER BILETU',
            'NÚMERO DE VIAJERO FRECUENTE' => 'NUMER KLIENTA',
            'ISSUE DATE'                  => ['DATA WYSTAWIENIA BILETU'],
            'Total Amount'                => ['Итого по тарифу/сборам', 'Ито го по тариф у/сб о рам'],
            //			'Fare' => '',
            // 'Equivalent'                  => '',
            //			'Taxes/Fees/Carrier' => '',
            'Itinerary Details' => ['Szczegóły Planu Podróży'],
            'Allowances'        => ['Szczegóły Płatności'],
            //			'Operated by:' => '',
            'Time'  => 'Go dzina',
            'Class' => 'Klasa',
            //			'Seat Number' => [''],
        ],
        'es' => [
            'Departure'                   => 'Salida',
            'RESERVATION CODE'            => 'CÓDIGO DE RESERVACIÓN',
            'Prepared For'                => 'Preparado para',
            'TICKET NUMBER'               => 'NÚMERO DE BOLETO',
            'NÚMERO DE VIAJERO FRECUENTE' => 'NÚMERO DE VIAJERO FRECUENTE',
            'ISSUE DATE'                  => 'FECHA DE EMISIÓN',
            'Total Amount'                => ['Importe Total', 'Tarifa total'],
            'Fare'                        => 'Tarifa',
            // 'Equivalent'                  => '',
            'Taxes/Fees/Carrier'          => ['Impuestos / comisiones / cargos', 'Impuestos/comisiones/cargos'],
            'Itinerary Details'           => ['Información De Vuelo', 'Detalles Del Itinerario'],
            'Allowances'                  => ['Detal es De Pago', 'Detalles De Pago', 'Detalles Del Pago', 'Límites De Equipaje'],
            'Operated by:'                => 'Operado por:',
            'Time'                        => ['Hora', 'Ho ra'],
            'Class'                       => ['Clase', 'Cabina'],
            'Seat Number'                 => 'Número de asiento',
        ],
        'en' => [
            'Total Amount'                => ['Total Amount', 'Total Fare', 'Total'],
            'Allowances'                  => ['Allowances', 'Payment/Fare Details', 'Receipt And Payment Details', 'Please contact your travel arranger for fare details', 'Positive identification required for airport check in', 'Please contact Etihad for further information on fare details'],
            'NÚMERO DE VIAJERO FRECUENTE' => 'FREQUENT FLYER NUMBER',
            'Class'                       => ['Class', 'Cabin'],
            'RESERVATION CODE'            => ['RESERVATION CODE', 'BOOKING REFERENCE'],
            'Prepared For'                => ['Prepared For', 'preparedFor'],
        ],
        'pt' => [
            'Departure'                   => 'SAÍDA',
            'RESERVATION CODE'            => 'CÓDIGO DE RESERVA',
            'Prepared For'                => 'Preparado para',
            'TICKET NUMBER'               => 'NÚMERO DO BILHETE',
            'NÚMERO DE VIAJERO FRECUENTE' => 'NÚMERO DO PASSAGEIRO FREQÜENTE',
            'ISSUE DATE'                  => 'DATA DE EMISSÃO DO BILHETE',
            'Total Amount'                => ['Tarifa To t al e Out ras Co branças', 'Tarifa total'],
            'Fare'                        => 'Tarifa',
            'Equivalent'                  => ['Valor equivalent e pago', 'Valo r e quivale nt e pago'],
            'Taxes/Fees/Carrier'          => 'Tarifas / taxas / encargos',
            'Itinerary Details'           => ['Detalhes Do Itinerário'],
            'Allowances'                  => ['Franquias', 'Detalhes Do Pagamento'],
            'Operated by:'                => 'Operado por:',
            'Time'                        => ['Ho rário Lo cal', 'Hora', 'Ho ra'],
            'Class'                       => ['Classe', 'Cabine'],
            'Seat Number'                 => 'Número do assento',
        ],
    ];

    public $lang = 'en';
    public $text = '';

    private $patterns = [
        'date'      => '\b\d{1,2}\s*[[:alpha:]]+\s*(?:\d{2}|\d{4})\b', // 07Feb23    |    07Feb2023
        'dateShort' => '\b\d{1,2}\s*[[:alpha:]]+\b', // 07Feb
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aeromexico.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
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
        $pdfs = $parser->searchAttachmentByName("(?:Recibo de pasaje electrónico, .+ para |Electronic ticket receipt, .+ for ).+\.pdf");
        // example: name="Recibo de pasaje electrónico, 21 octubre para NICOLE AMERENA SERRANO.pd"
        // "Electronic ticket receipt, May 12 for MR OCTAVIAN CUCU.pdf"

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        } else {
            $pdfs = array_slice($pdfs, 0, 3);
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->getProvider($textPdf) === null) {
                continue;
            }

            if ($this->assignLang($textPdf) === true) {
                $this->logger->warning('Assign');

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, 'icon-air') or contains(@id, '-segment-icon')]")->length > 0
            && $this->http->XPath->query("//img[contains(@src, 'arrow-right') or contains(@id, 'arrow-right')]")->length > 0
            && $this->http->XPath->query("//a[contains(@href, '.etihad.com') or contains(@href, '.voegol.com.br')]")->length > 0
        ) {
            $this->logger->debug('Go to golair/FlightConfirmation');

            return false;
        }

        $provider = '';

        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf) === true) {
                $this->text = $textPdf;
                $itineraries = [];

                if ($this->parsePdf($itineraries) !== false) {
                    $its = array_merge($its, $itineraries);
                } else {
                    $this->logger->debug('parsing error');

                    break;
                }
            }
        }

        if ($providerNew = $this->getProvider($this->text)) {
            $provider = $providerNew;
        }

        $result = [
            'providerCode' => $provider,
            'emailType'    => 'ETicketPdf' . ucfirst($this->lang),
            'parsedData'   => [
                'Itineraries' => $its,
            ],
        ];

        return $result;
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
        return array_keys(self::$reBody);
    }

    protected function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parsePdf(&$itineraries): bool
    {
        $text = $this->text;

        $it = [];
        $itTrain = [];
        $it['Kind'] = "T";

        // RecordLocator
        $reservationCode = $this->re("#" . $this->opt($this->t("RESERVATION CODE")) . "\s+(.+)#", $text);

        if ($reservationCode) {
            $it['RecordLocator'] = str_replace(' ', '', $reservationCode);
        }

        // Passengers
        $traveller = $this->re("/{$this->opt($this->t("Prepared For"))}.*\n(.*?)(?:\s*\[|\n)/", $text);
        $it['Passengers'] = [preg_replace("/^(.{2,})\s+(?:MISS|MRS|MR|MS)$/i", '$1', $traveller)];

        // TicketNumbers
        $it['TicketNumbers'] = [$this->re("#" . $this->opt($this->t("TICKET NUMBER")) . "\s+(.+)#", $text)];

        // AccountNumbers
        $ffNumber = $this->re("#" . $this->opt($this->t('NÚMERO DE VIAJERO FRECUENTE')) . "\s+(.+)#", $text);

        if ($ffNumber) {
            $it['AccountNumbers'] = [$ffNumber];
        }

        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#" . $this->opt($this->t('ISSUE DATE')) . "\s+(.+)#", $text)));

        $flights = preg_replace("#\nFECHA.+$#s", "", $this->re("#" . $this->opt($this->t('Itinerary Details')) . "\n(.*?)" . $this->opt($this->t('Allowances')) . "#ms", $text));
        // QR QATAR AIRWAYS(Q.C.S.C) Contact (DOHA HAMAD INTL, QATAR) — 974 449 6666
        $flights = preg_replace("/.*{$this->addSpacesWord('Contact')}.+[-—][ ]*[+(\d][-. \d)(]{5,}[\d)].*/i", '', $flights);
        $segments = $this->split("#\n(\s{0,5}\d)#", $flights);

        $trains = $flights = [];

        foreach ($segments as $stext) {
            $pos = $this->colsPos($this->re("/(.+)/", $stext), 4);

            if (count($pos) != 5) {
                $this->logger->debug("incorrect parse table");

                return false;
            }

            /////--------->>>
            //align block (when new page table could move) BE CAREFUL  IF MODIFY IT
            // TODO: DON'T comments below
            // FE: mta/it-22407752.eml
            $strings = explode("\n", $stext);
            $diff = 0;
            $firstStr = 0; //first move str;

            foreach ($strings as $i => $str) {
                $spaces = $this->re("#^( +)#", $str);
                $start = strlen($spaces);

                if (empty($diff)) {
                    if ($start > $pos[3] && $start < $pos[4]) {
                        $diff = $pos[4] - $start;

                        if (empty($firstStr)) {
                            $firstStr = $i;
                        }

                        break;
                    }
                }

                if (empty($firstStr) && $start > $pos[2] && $start < $pos[3]) {
                    $firstStr = $i;
                }

                if (empty($firstStr) && $start > $pos[1] && $start < $pos[2]) {
                    $firstStr = $i;
                }
            }

            if (!empty($diff)) {
                $newStrings = [];
                $diffStr = str_pad('', $diff, ' ');

                foreach ($strings as $i => $str) {
                    if ($i >= $firstStr) {
                        $newStrings[] = $diffStr . $str;
                    } else {
                        $newStrings[] = $str;
                    }
                }
                $stext = implode("\n", $newStrings);
            }
            /////<<<---------

            $pos[0] = 0;
            $pos[1]--;
            $table = $this->splitCols($stext, $pos);

            if (preg_match('/^[ ]*(?<name>[A-Z] ?[A-Z\d]|[A-Z\d] ?[A-Z]) (?<number>\d[\d ]*)$/m', $table[1], $m)) {
                // ET 335    |    ET 70 6    |    9 B 29 4 4
                $airline = str_replace(' ', '', $m['name']);
                // FE: it-44621234.eml
                if ($airline == '9B'
                    && preg_match("/\b{$this->addSpacesWord('ACCESRAIL')}\b/i", $table[1]) > 0
                ) {
                    $trains[$stext] = $table;
                } else {
                    $flights[$stext] = $table;
                }
            } else { // failed parse, but don't stop parsing - for logs
                $flights[$stext] = $table;
            }
        }

        if (!empty($trains)) {
            $itTrain = $it;
            $itTrain['TripCategory'] = TRIP_CATEGORY_TRAIN;
            $this->parseSegments($trains, $itTrain, true);
            $itineraries[] = $itTrain;
        }
        $this->parseSegments($flights, $it);

        // Currency
        // TotalCharge
        if (preg_match("/{$this->opt($this->t('Total Amount'), true)}\s+(?<currency>[A-Z]{3,4})[ ]+(?<amount>\d[,.\'\d ]*)/", $text, $m)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $it['Currency'] = $m['currency'];
            $it['TotalCharge'] = PriceHelper::parse(str_replace(' ', '', $m['amount']), $currencyCode);

            // BaseFare
            if (preg_match("/^[ ]*(?:{$this->opt($this->t('Fare'), true)}|{$this->opt($this->t('Equivalent'), true)})[ ]{2,}" . $this->addSpacesWord(preg_quote($m['currency'], '/')) . "[ ]+(?<amount>\d[,.\'\d ]*)/m", $text, $matches)) {
                $it['BaseFare'] = PriceHelper::parse(str_replace(' ', '', $matches['amount']), $currencyCode);
            }

            // Fees
            if (preg_match("/^([ ]*{$this->opt($this->t('Taxes/Fees/Carrier'), true)}[\s\S]+?)^[ ]*{$this->opt($this->t('Total Amount'), true)}/m", $text, $m2)
                && preg_match_all('/(?:^[ ]*|[ ]{2})' . $this->addSpacesWord(preg_quote($m['currency'], '/')) . '[ ]+(?<charge>\d[,.\'\d ]*?)[ ]*(?<name>[A-Z][A-Z\d ]+?)[ ]*(?:\(|$)/m', $m2[1], $matches, PREG_SET_ORDER)
            ) {
                // USD 10,00 E2 (INFRAST RUCT URE TAX)
                $fees = [];

                foreach ($matches as $fee) {
                    $fees[] = ['Name' => $fee['name'], 'Charge' => PriceHelper::parse(str_replace(' ', '', $fee['charge']), $currencyCode)];
                }
                $it['Fees'] = $fees;
            }
        } else {
            $this->http->Log('aeroflot ETicketPdf missing price', LOG_LEVEL_ERROR, false);
        }

        $itineraries[] = $it;

        return true;
    }

    private function parseSegments(array $segments, array &$reservation, ?bool $isTrain = false): void
    {
        foreach ($segments as $table) {
            $dateText = str_replace(' ', '', $this->re('/^\s*(.+?)(?:\s{5}|$)/s', $table[0]));
            $dateValues = preg_split('/(\s*[-]+\s*)+/', $dateText);

            if (count($dateValues) === 2) {
                $dateDepVal = $this->normalizeDate($dateValues[0]);
                $dateArrVal = $this->normalizeDate($dateValues[1]);
            } elseif (count($dateValues) === 1) {
                $dateDepVal = $dateArrVal = $this->normalizeDate($dateValues[0]);
            } else {
                $dateDepVal = $dateArrVal = null;
            }

            if (preg_match("/^{$this->patterns['date']}$/u", $dateDepVal)) {
                $dateDep = strtotime($dateDepVal);
            } elseif (preg_match("/^{$this->patterns['dateShort']}$/u", $dateDepVal) && !empty($reservation['ReservationDate'])) {
                $dateDep = EmailDateHelper::parseDateRelative($dateDepVal, $reservation['ReservationDate'], true, '%D% %Y%');
            } else {
                $dateDep = null;
            }

            if (preg_match("/^{$this->patterns['date']}$/u", $dateArrVal)) {
                $dateArr = strtotime($dateArrVal);
            } elseif (preg_match("/^{$this->patterns['dateShort']}$/u", $dateArrVal) && !empty($reservation['ReservationDate'])) {
                $dateArr = EmailDateHelper::parseDateRelative($dateArrVal, $reservation['ReservationDate'], true, '%D% %Y%');
            } else {
                $dateArr = null;
            }

            $itsegment = [];

            // AirlineName
            // FlightNumber
            if (preg_match('/^[ ]*(?<name>[A-Z] ?[A-Z\d]|[A-Z\d] ?[A-Z]) (?<number>\d[\d ]*)$/m', $table[1], $m)) {
                // ET 335    |    ET 70 6    |    9 B 29 4 4
                if ($isTrain) {
                    $itsegment['Type'] = str_replace(' ', '', $m['name']);
                } else {
                    $itsegment['AirlineName'] = str_replace(' ', '', $m['name']);
                }
                $itsegment['FlightNumber'] = str_replace(' ', '', $m['number']);
            }

            // Operator
            $cutOperators = ['SOCIEDAD\s+ANONIMA\s+OPERADORA'];
            $cutOperator = "(?:" . implode('|', $cutOperators) . ")";

            if (preg_match("/{$this->opt($this->t("Operated by:"), true)}\s+([^:]+?)(?:\s+.*:|\s*$|\s*\b{$cutOperator}\b)/u", $table[1], $m)) {
                $itsegment['Operator'] = trim(preg_replace('/\s+/', ' ', $m[1]), '/');
            }

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = trim(str_replace("\n", " ", $this->re("#(.*?)" . $this->opt($this->t("Time")) . "#ms", $table[2])));

            if (empty($itsegment['DepName'])) {
                $itsegment['DepName'] = trim(str_replace("\n", " ", $this->re("#(.*?)" . $this->t("Terminal") . "#ims", $table[2])));
            }

            if (empty($itsegment['DepName'])) {
                $itsegment['DepName'] = trim(str_replace("\n", " ", $table[2]));
            }
            $itsegment['DepName'] = preg_replace("/A ?i ?r ?p ?o ?r ?t/", 'Airport', $itsegment['DepName']);

            // DepartureTerminal
            $terminalDep = $this->re("#{$this->opt($this->t("Terminal"))}((?:\n+.*(?:AEROGARE|TERMINAL).*)+)#", $table[2]);

            if ($terminalDep === null) {
                $terminalDep = $this->re("#{$this->opt($this->t("Terminal"))}\n+(.+)#", $table[2]);
            }

            if ($terminalDep) {
                $terminalDep = preg_replace('/\s+/', ' ', trim($terminalDep));
                $terminalDep = preg_replace("/^\s*{$this->opt($this->t("Terminal"))}\s*/i", '', $terminalDep);
                $itsegment['DepartureTerminal'] = preg_replace("/\s*{$this->opt($this->t("Terminal"))}\s*$/i", '', $terminalDep);
            }

            // DepDate
            $timeDep = str_replace(' ', '', $this->re("/{$this->opt($this->t('Time'))}(?:\s*\([^\(]+\))?\s*([\d ]+:[\d ]{2,}(?:[ ]*[AaPp][Mm])?)/", $table[2])); // Time (local time)  07:55

            if ($dateDep && $timeDep) {
                $itsegment['DepDate'] = strtotime($timeDep, $dateDep);
            } elseif (!preg_match("/{$this->opt($this->t('Time'))}/", $table[2])) {
                $itsegment['DepDate'] = MISSING_DATE;
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = trim(str_replace("\n", " ", $this->re("#(.*?)" . $this->opt($this->t("Time")) . "#ms", $table[3])));

            if (empty($itsegment['ArrName'])) {
                $itsegment['ArrName'] = trim(str_replace("\n", " ", $this->re("#(.*?)" . $this->t("Terminal") . "#mis", $table[3])));
            }

            if (empty($itsegment['ArrName'])) {
                $itsegment['ArrName'] = trim(str_replace("\n", " ", $table[3]));
            }
            $itsegment['ArrName'] = preg_replace("/A ?i ?r ?p ?o ?r ?t/", 'Airport', $itsegment['ArrName']);

            // ArrivalTerminal
            $terminalArr = $this->re("#{$this->opt($this->t("Terminal"))}((?:\n+.*(?:AEROGARE|TERMINAL).*)+)#", $table[3]);

            if ($terminalArr === null) {
                $terminalArr = $this->re("#{$this->opt($this->t("Terminal"))}\n+(.+)#", $table[3]);
            }

            if ($terminalArr) {
                $terminalArr = preg_replace('/\s+/', ' ', trim($terminalArr));
                $terminalArr = preg_replace("/^\s*{$this->opt($this->t("Terminal"))}\s*/i", '', $terminalArr);
                $itsegment['ArrivalTerminal'] = preg_replace("/\s*{$this->opt($this->t("Terminal"))}\s*$/i", '', $terminalArr);
            }

            // ArrDate
            $timeArr = str_replace(' ', '', $this->re("/{$this->opt($this->t('Time'))}(?:\s*\([^\(]+\))?\s*([\d ]+:[\d ]{2,}(?:[ ]*[AaPp][Mm])?)/", $table[3]));

            if ($dateArr && $timeArr) {
                $itsegment['ArrDate'] = strtotime($timeArr, $dateArr);
            } elseif (!preg_match("/{$this->opt($this->t('Time'))}/", $table[3])) {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // Cabin
            // BookingClass
            if (preg_match("#{$this->opt($this->t('Class'))}\s+([^\/\n]+?)\s*\/\s*([A-Z]{1,2})$#m", $table[4], $m)) {
                // ECONOMY / N
                $itsegment['Cabin'] = $m[1];
                $itsegment['BookingClass'] = $m[2];
            } elseif (preg_match("#{$this->opt($this->t('Class'))}\s+(.+)#", $table[4], $m)) {
                // ECONOMY
                $itsegment['Cabin'] = $m[1];
            }

            // Seat
            $seat = $this->re('#' . $this->opt($this->t("Seat Number")) . '\s*(\d[\d ]{0,2} ?[A-Z])\b#', $table[4]);

            if ($seat) {
                $itsegment['Seats'] = [str_replace(' ', '', $seat)];
            }

            $reservation['TripSegments'][] = $itsegment;
        }
    }

    private function getProvider($text): ?string
    {
        foreach (self::$reBody as $providerCode => $phrases) {
            foreach ($phrases as $phrase) {
                if (preg_match("/{$this->addSpacesWord($phrase)}/i", $text)) {
                    return $providerCode;
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            '/^(\d{1,2})([[:alpha:]]+)(\d{2})$/u', // 12may16
            '/^(\d{1,2})([[:alpha:]]+)(\d{2})[ ]*-[ ]*\d{1,2}[[:alpha:]]+\d{2}$/u', // 20May17 - 21May17
        ];
        $out = [
            "$1 $2 20$3",
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
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

    private function rowColsPos(?string $row): array
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

    private function colsPos($table, $correct = 5): array
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function addSpacesWord($text): string
    {
        return preg_replace('/(\w)/u', '$1 *', preg_quote($text, '/'));
    }
}
