<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge methods `ItineraryFlight::parsePdf` and `FlightTicket::parsePdf2` (in favor of `ItineraryFlight::parsePdf`)

class ItineraryFlight extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-154609804-pt.eml, ctrip/it-65140632.eml, ctrip/it-82296774.eml, ctrip/it-168527143-pdf.eml, ctrip/it-50335551-pdf.eml, ctrip/it-528336582-pdf.eml, ctrip/it-54186305-pdf.eml, ctrip/it-623168107-pdf.eml, ctrip/it-712251578-ja.eml";

    //Format detectors
    private static $detectorsPdf = [
        'it' => [
            "La tua prenotazione è stata confermata e i biglietti elettronici sono stati emessi",
            "Consigliamo di stampare una copia dell'itinerario e portarlo con sé per rendere il viaggio il più semplice possibile",
        ],
        'en' => [
            "As requested, please find your itinerary attached. Below you will find a summary of your booking details.",
            "We advise you print out your itinerary and take it with you to ensure your trip goes as smoothly as possible.",
            "Your flight booking has been confirmed and your tickets have been issued.",
        ],
        'pt' => [
            "Conforme solicitado, seu itinerário está em anexo. Veja abaixo um resumo dos detalhes da sua reserva.",
        ],
    ];

    //Language detectors and dictionary
    private static $dictionary = [
        'ja' => [
            "detectFirst"                 => ["予約番号"],
            "detectLast"                  => ["予約内容"],
            'bookingNo'                   => '予約番号',
            'passengers'                  => ['搭乗者名・eチケット番号', '搭乗者名'],
            'Total'                       => ['Total：', 'Total:'],
            'departing'                   => ['出発：', '出発:'],
            'arriving'                    => ['到着：', '到着:'],

            // PDF
            // 'Airline Booking Reference' => '',
            // 'Booked On' => '',
            // 'Name' => '',
            // 'Ticket Number' => '',
            // 'Flight Details' => '',
            // 'Depart/Arrive Time' => '',
            // 'Airline' => '',
            // 'Flight No.' => '',
            // 'Class' => '',
            // 'Important Information' => '',
            // 'Baggage Allowance' => '',
            // 'Baggage' => '',
            // 'dep' => [''],
            // 'arr' => [''],
        ],
        'pt' => [
            "detectFirst"                 => ["Nº da reserva"],
            "detectLast"                  => ["Detalhes do voo", "Informações do voo"],
            'bookingNo'                   => 'Nº da reserva',
            'passengers'                  => ['Nome do passageiro e número da passagem', 'Passageiro'],
            'Total'                       => 'Total',
            'departing'                   => ['Partida：', 'Partida:'],
            'arriving'                    => ['Chegada：', 'Chegada:'],

            // PDF
            'Airline Booking Reference' => ['Referência de reserva da companhia aérea/Localizador', 'Referência de reserva da companhia aérea'],
            'Booked On'                 => 'Reserva em',
            'Name'                      => 'Nome',
            'Ticket Number'             => 'Número da passagem',
            'Flight Details'            => 'Detalhes do voo',
            'Depart/Arrive Time'        => 'Horário de partida/chegada',
            'Airline'                   => 'Companhia aérea',
            'Flight No.'                => 'N° do voo',
            'Class'                     => 'Classe',
            'Important Information'     => 'Informações importantes',
            'Baggage Allowance'         => 'Franquia de bagagem',
            'Baggage'                   => 'Bagagem',
            'dep'                       => ['Horário de partida', 'Aeroporto de partida', 'partida'],
            'arr'                       => ['chegada'],
        ],
        'it' => [
            "detectFirst"                 => ["Prenotazione n."],
            "detectLast"                  => ["Informazioni sul volo", 'Dettagli del volo'],
            'bookingNo'                   => 'Prenotazione n.',
            'passengers'                  => ['Dettagli del passeggero e del biglietto elettronico', 'Passeggeri'],
            'Total'                       => 'Totale',
            'departing'                   => ['Partida：', 'Partida:'],
            'arriving'                    => ['Chegada：', 'Chegada:'],

            // PDF
            // 'Airline Booking Reference' => '',
            // 'Booked On' => '',
            // 'Name' => '',
            // 'Ticket Number' => '',
            // 'Flight Details' => '',
            // 'Depart/Arrive Time' => '',
            // 'Airline' => '',
            // 'Flight No.' => '',
            // 'Class' => '',
            // 'Important Information' => '',
            // 'Baggage Allowance' => '',
            // 'Baggage' => '',
            // 'dep' => [''],
            // 'arr' => [''],
        ],
        'en' => [
            "detectFirst"  => ["Booking No", "Flight Details", 'Booking Details'],
            "detectLast"   => ["Flight Details", "Flight Info"],
            'bookingNo'    => ['Booking No.', 'Booking number'],
            'passengers'   => ['Passenger Name & Ticket No.', 'Passengers', 'Passenger'],
            'Total'        => ['Total Payment', 'Total'],
            'departing'    => ['Departing：', 'Departing:', 'Departure：'],
            'arriving'     => ['Arriving：', 'Arriving:', 'Arrival：'],

            // PDF
            // 'Airline Booking Reference' => '',
            // 'Booked On' => '',
            // 'Name' => '',
            // 'Ticket Number' => '',
            // 'Flight Details' => '',
            // 'Depart/Arrive Time' => '',
            // 'Airline' => '',
            // 'Flight No.' => '',
            // 'Class' => '',
            // 'Important Information' => '',
            // 'Baggage Allowance' => '',
            // 'Baggage' => '',
            'dep' => ['Departure', 'Depart'],
            'arr' => ['Arrival', 'Arrive'],
        ],
    ];

    private $from = "@trip.com";

    private $confs = [];
    private $subject = [
        "Itinerary", "Flight Payment Successful",
        "Itinerário", "Pagamento do voo efetuado", "Confirmação de reserva de voo",
        "eチケットお客様控え",
    ];

    private $body = ['Trip.com'];
    private $lang = '';
    private $pdfNamePattern = ".*pdf";
    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang    |    COUTO/ISABELA
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $sub) {
            if (stripos($headers["subject"], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Trip.com')]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains(['Booking No.', 'Nº da reserva：', '予約番号'])}]")->length > 0
            && $this->http->XPath->query("//*[{$this->eq(['Flight Info', 'Informações do voo', '予約内容'])}]")->length > 0
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (empty($text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            foreach ($this->body as $word) {
                if (stripos($text, $word) !== false) {
                    if ($this->detectBodyPdf($text)) {
                        return $this->assignLangPdf($text);
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $detectedPdf = false;

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLangPdf($text)) {
                        if (strpos($text, 'This receipt is automatically generated') !== false) {
                            /*
                                + price
                            */
                            $this->parsePdfReceipt($text, $email);
                        } else {
                            $this->logger->debug("Can't determine a language from PDF!");

                            continue;
                        }
                    } else {
                        $detectedPdf = true;
                        $parts = $this->splitText($text, "/\n*\s*({$this->opt($this->t('bookingNo'))})/", true);

                        foreach ($parts as $part) {
                            $this->parsePdf($email, $part);
                        }

                        $type = 'Pdf';
                    }
                }
            }
        }

        if ($detectedPdf === false) {
            $this->assignLang();
            $this->parseHtml($email);
            $type = 'Html';
        }

        $email->setType('ItineraryFlight' . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    private function detectBodyPdf($body): bool
    {
        if (!empty($body)) {
            foreach (self::$detectorsPdf as $phrases) {
                foreach ($phrases as $phrase) {
                    if (strpos($body, $phrase) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (!empty($words["detectFirst"]) && !empty($words["detectLast"])
                && $this->http->XPath->query("//text()[{$this->contains($words["detectFirst"])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($words["detectLast"])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignLangPdf($body): bool
    {
        if (!empty($this->body)) {
            foreach (self::$dictionary as $lang => $words) {
                foreach ($words["detectFirst"] as $word1) {
                    if (strpos($body, $word1) !== false) {
                        foreach ($words["detectLast"] as $word2) {
                            if (strpos($body, $word2 . "\n") !== false) {
                                $this->lang = $lang;

                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        $travellers = $tickets = [];

        // it-712251578-ja.eml
        $travellerRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('passengers'))}] ]/*[normalize-space()][2]/descendant::tr[normalize-space() and not(.//tr[normalize-space()])]");

        if ($travellerRows->length === 0) {
            // it-154609804-pt.eml
            $travellerRows = $this->http->XPath->query("//*[count(*[normalize-space()])>1]/*[normalize-space()][1][{$this->eq($this->t('passengers'))}]/following-sibling::*[normalize-space() and not(.//tr[normalize-space()])]");
        }

        foreach ($travellerRows as $tRow) {
            $passengerName = $ticket = null;
            $rowText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $tRow));

            if (preg_match("/^(?<name>{$this->patterns['travellerName']})(?:\s*\([^)(]*\))?\s+(?<ticket>{$this->patterns['eTicket']})$/u", $rowText, $m)) {
                $passengerName = $this->normalizeTraveller($m['name']);
                $ticket = $m['ticket'];
            } elseif (preg_match("/^(?<name>{$this->patterns['travellerName']})(?:\s*\([^)(]*\))?$/u", $rowText, $m)) {
                $passengerName = $this->normalizeTraveller($m['name']);
            }

            if ($passengerName && !in_array($passengerName, $travellers)) {
                $f->general()->traveller($passengerName, true);
                $travellers[] = $passengerName;
            }

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]", null, true, "/^{$this->opt($this->t('Total'))}[:\s]*(.*\d.*)$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // R$ 5.704,79
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $xpath = "//text()[" . $this->starts($this->t('departing')) . "]/ancestor::table[1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $headText = $this->http->FindSingleNode("descendant::tr[normalize-space()][1]", $root);

            if (preg_match_all('/[-]/', $headText, $separatorMatches)) {
                if (count($separatorMatches[0]) === 1) {
                    $this->parseHtmlSegment1($f, $root);
                } elseif (count($separatorMatches[0]) > 1) {
                    $this->parseHtmlSegment2($f, $root, $headText);
                }
            } else {
                $this->parseHtmlSegment3($f, $root);
            }
        }

        if ($segments->length == 0) {
            $xpath = "//tr[count(descendant::text()[normalize-space()]) = 4][descendant::text()[normalize-space()][1][translate(normalize-space(),'0123456789','%%%%%%%%%%') = '%%:%%']][descendant::text()[normalize-space()][4][contains(normalize-space(), min)]]"
                . "[following-sibling::tr[normalize-space()][1][count(descendant::text()[normalize-space()]) = 2 or count(descendant::text()[normalize-space()]) = 3][descendant::text()[normalize-space()][1][translate(normalize-space(),'0123456789','%%%%%%%%%%') = '%%:%%']]]";
            $segments = $this->http->XPath->query($xpath);

            foreach ($segments as $root) {
                $this->parseHtmlSegment4($f, $root);
            }
        }

        if ($segments->length > 0) {
            $bookingNumbers = array_values(array_filter($this->http->FindNodes("preceding::tr[not(.//tr[normalize-space()]) and {$this->starts($this->t('bookingNo'))}]", $segments->item(0), "/^{$this->opt($this->t('bookingNo'))}[：:\s]*([-A-Z\d]{5,35})$/")));

            if (count(array_unique($bookingNumbers)) === 1) {
                $otaConfirmation = $bookingNumbers[0];
                $otaConfirmationTitle = $this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and {$this->starts($this->t('bookingNo'))}][1]", $segments->item(0), true, "/^({$this->opt($this->t('bookingNo'))})[：:\s]*(?:[-A-Z\d]{5,35})?$/");
            } else {
                $otaConfirmation = $otaConfirmationTitle = null;
            }

            if ($otaConfirmation) {
                $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

                if ($this->http->XPath->query("//text()[{$this->starts($this->t('Airline Booking Reference'))} or {$this->starts($this->t('Confirmation No.'))}]")->length === 0) {
                    $f->general()->noConfirmation();
                } else {
                    $confs = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Airline Booking Reference'))}]/following::text()[normalize-space()][1]",
                        null, "/^\s*([A-Z\d]{5,7})\s*$/"));

                    foreach ($confs as $conf) {
                        $f->general()
                            ->confirmation($conf);
                    }
                }
            }
        }
    }

    private function parseHtmlSegment1(Flight $f, $root): void
    {
        $this->logger->debug(__FUNCTION__);
        $s = $f->addSegment();

        $patternDate = "/(?:"
            . "\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日[,\s]+{$this->patterns['time']}" // 2024年7月13日 23:55
            . "|\d{1,2}\s*(?:de\s+)?[[:alpha:]]+[,\s]+(?:de\s+)?\d{4}\s*,\s*{$this->patterns['time']}" // 16 de julho de 2022, 14:25
            . "|{$this->patterns['time']}[,\s]+[[:alpha:]]+\s*\d{1,2}[,\s]+\d{4}" // 23:55, Jul 13, 2024
        . ")/u";

        $date = $this->http->FindSingleNode("./descendant::tr[normalize-space()][not({$this->contains($this->t('Operated by'))})][3]", $root);

        if (preg_match($patternDate, $date, $m)) {
            $date = $this->normalizeDate($m[0]);
        }

        $s->departure()
            ->name($this->http->FindSingleNode("./descendant::tr[1]", $root, true, "/^(\D+)\s*[-]/"))
            ->date($date)
            ->noCode();

        $depTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][3]", $root, true, "/\s+T?([A-Z\d]+)$/");
        $operator = $this->http->FindSingleNode("./descendant::tr[normalize-space()][3]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

        if (!empty($operator)) {
            $s->airline()
                ->operator(str_replace(' · ', ', ', $operator));
        }

        if (!empty($depTerminal)) {
            $s->departure()
                ->terminal($depTerminal);
        }

        $date = $this->http->FindSingleNode("./descendant::tr[normalize-space()][not({$this->contains($this->t('Operated by'))})][4]", $root);

        if (preg_match($patternDate, $date, $m)) {
            $date = $this->normalizeDate($m[0]);
        }

        $s->arrival()
            ->name($this->http->FindSingleNode("./descendant::tr[1]", $root, true, "/[-]\s*(\D+)$/"))
            ->date($date)
            ->noCode();

        $arrTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][4]", $root, true, "/\s+T?([A-Z\d]+)$/");

        if (!empty($arrTerminal)) {
            $s->arrival()
                ->terminal($arrTerminal);
        }

        $flight = $this->http->FindSingleNode("descendant::tr[normalize-space()][2]", $root);

        if (preg_match("/\s(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:[(（]|\s)/u", $flight, $m)) {
            $s->airline()->name($m['name'])->number($m['number']);
        }

        $cabin = $this->re("/\s(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\s*\S(.+)(?:[(（]|\S)/u", $flight);
        $s->extra()->cabin($cabin);
    }

    private function parseHtmlSegment2(Flight $f, $mainRoot, $headText): void
    {
        $this->logger->debug(__FUNCTION__);
        $cityArray = explode(' - ', $headText);
        $i = 0;

        $roots = $this->http->XPath->query("descendant::tr[{$this->starts($this->t('departing'))}]", $mainRoot);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $airline = $this->http->FindSingleNode("preceding::tr[normalize-space()][not(contains(normalize-space(), 'Operated'))][1]", $root);

            if (preg_match("/^(?<operator>.{2,}?)\s+[·\S]\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{1,9})\s*\(\s*(?<cabin>.{2,}?)\s*\)/u", $airline, $m)) {
                // ITA Airways · AZ674(Classe econômica)
                $s->airline()
                    ->name($m['name'])
                    ->operator($m['operator'])
                    ->number($m['number']);

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $depart = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^{$this->opt($this->t('departing'))}\s*(?<dateTime>.{6,}?{$this->patterns['time']}|{$this->patterns['time']}.+\d{4})\s+(?<airport>.+Airport)\s*(?:T?(?<terminal>\S+)$|.+|$)/u", $depart, $m)) {
                // Departing：23:45, May 2, 2021 Charles de Gaulle International Airport T2B
                $s->departure()
                    ->noCode()
                    ->name($m['airport'] . ', ' . $cityArray[$i])
                    ->date($this->normalizeDate($m['dateTime']));

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            $arrival = $this->http->FindSingleNode("following::tr[normalize-space()][1]", $root);

            if (preg_match("/^{$this->opt($this->t('arriving'))}\s*(?<dateTime>.{6,}?{$this->patterns['time']}|{$this->patterns['time']}.+\d{4})\s+(?<airport>.+Airport)\s*(?:T?(?<terminal>\S+)$|.+|$)/us", $arrival, $m)) {
                // Arriving：2 May 2021, 23:45 Charles de Gaulle International Airport D
                $s->arrival()
                    ->noCode()
                    ->name($m['airport'] . ', ' . $cityArray[$i + 1])
                    ->date($this->normalizeDate($m['dateTime']));

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }

            $i++;
        }
    }

    private function parseHtmlSegment3(Flight $f, $mainRoot): void
    {
        $this->logger->debug(__FUNCTION__);
        // Detalhes do voo
//        São Paulo - Belo Horizonte
//        Partida: 26 de outubro de 2021, 17:40
//        Chegada: 26 de outubro de 2021, 18:55
        $s = $f->addSegment();

        $node = implode("\n", $this->http->FindNodes(".//td[not(.//td)]", $mainRoot));

        if (preg_match("/(?:^|\n)(?<dname>.+) - (?<aname>.+)\n\s*" . $this->opt($this->t("departing")) . "\s*(?<ddate>.+)\s+" . $this->opt($this->t("arriving")) . "(?<adate>.+)/u", $node, $m)) {
            $s->airline()
                ->noName()
                ->noNumber();

            $s->departure()
                ->noCode()
                ->name($m['dname'])
                ->date($this->normalizeDate($m['ddate']));

            $s->arrival()
                ->noCode()
                ->name($m['aname'])
                ->date($this->normalizeDate($m['adate']));
        }
    }

    private function parseHtmlSegment4(Flight $f, $mainRoot): void
    {
        $this->logger->debug(__FUNCTION__);
        // 25 July 2023 | Las Vegas - Denver - Wichita
        //        14:10   Harry Reid International AirportT1      Southwest Airlines WN1428
        //                                                 2 hr 0 mins
        //        17:10   Denver International Airport
        //  25 Jul 2023

        $s = $f->addSegment();

        $date = $this->http->FindSingleNode("(./preceding::tr[not(.//tr)][count(.//text()[normalize-space()]) > 1][descendant::text()[normalize-space()][1][not(contains(., ':'))]])[last()]/descendant::text()[normalize-space()][1][contains(., ' 20')]", $mainRoot);

        if (empty($date)) {
            $date = $this->http->FindSingleNode("(./preceding::tr[not(.//tr)][count(.//text()[normalize-space()]) > 2][descendant::text()[normalize-space()][1][not(contains(., ':'))]])[last()]/descendant::text()[normalize-space()][2][contains(., ' 20')]", $mainRoot);
        }

        $row1 = $this->http->FindNodes("descendant::text()[normalize-space()]", $mainRoot);
        $row2 = $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $mainRoot);

        if (preg_match("/.+ (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*$/u", $row1[2] ?? '', $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
        }

        if (preg_match("/^(.{3,}(?:Airport)?)\s+(?:T|Terminal)\s*(.+)/s", $row1[1] ?? '', $m)) {
            $row1[1] = trim($m[1]);
            $s->departure()
                ->terminal(trim($m[2]));
        }

        $s->departure()
            ->noCode()
            ->name($row1[1] ?? null)
            ->date($this->normalizeDate($date . ', ' . $row1[0]));

        if (count($row2) == 3) {
            $date = $row2[1];
            unset($row2[1]);
            $row2 = array_values($row2);
        }

        if (preg_match("/^(.{3,}(?:Airport)?)\s+(?:T|Terminal)\s*(.+)/s", $row2[1] ?? '', $m)) {
            $row1[1] = trim($m[1]);
            $s->arrival()
                ->terminal(trim($m[2]));
        }
        $s->arrival()
            ->noCode()
            ->name($row2[1] ?? null)
            ->date($this->normalizeDate($date . ', ' . $row2[0]));

        $s->extra()
            ->duration($row1[3]);
    }

    private function parsePdf(Email $email, $text): void
    {
        // merge this method with `FlightTicket::parsePdf2` (in favor of this method)

        $this->logger->debug(__FUNCTION__);
        $r = $email->add()->flight();

        $ABRs = $ABRdescs = [];

        if (preg_match_all("/(?:^|\n|\([ ]*)(?<desc>{$this->opt($this->t('Airline Booking Reference'))})[：: ]*(?<num>[A-Z\d]{5,})(?:[ ]*\)|[ ]{2}|\n|$)/u", $text, $abrMatches, PREG_SET_ORDER)) {
            foreach ($abrMatches as $m) {
                $ABRs[] = $m['num'];
                $ABRdescs[] = $m['desc'];
            }
        }

        if (count(array_unique($ABRs)) === 1 && count(array_unique($ABRdescs)) === 1) {
            $r->general()->confirmation($ABRs[0], $ABRdescs[0]);
        }

        if (preg_match("/(?:^[ ]*|[ ]{2)({$this->opt($this->t('bookingNo'))})[：: ]*([-A-Z\d]{5,})$/mu", $text, $m)) {
            if (count($this->confs) === 0 || !in_array($m[2], $this->confs)) {
                $email->ota()->confirmation($m[2], $m[1]);
                $this->confs[] = $m[2];
            }

            if (count($ABRs) === 0) {
                $r->general()->noConfirmation();
            }
        }

        if (preg_match("/(?:^|[ ]{2}){$this->opt($this->t('Booked On'))}[ ]*:\s?(.*\b\d{4}\b.*)$/m", $text, $m)) {
            $r->general()->date($this->normalizeDate($m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Name'))}\s*{$this->opt($this->t('Ticket Number'))}\s*((?:\n.*?)+){$this->opt($this->t('Flight Details'))}/m",
            $text, $block)) {
            $rows = array_filter(array_map('trim', preg_split("/\s\n/", $block[1])));

            $tickets = [];
            $column2 = [];

            foreach ($rows as $row) {
                $row = preg_replace("/([A-Z])(\s)(\d+)/", "$1$2 $3", $row);
                $column = array_filter(array_map('trim', preg_split("/\s{2,}/", $row)));

                if (!empty($column[1])) {
                    $column2[] = $column[1];
                }

                if (isset($column[1]) && !empty($column[1])) {
                    $tickets[] = $this->re("/^\s*([A-Z\d\-]+)\s*$/u", $column[1]);
                } elseif (preg_match("/\s+([A-Z]*\d+[A-Z]*)$/", $column[0], $m)) {
                    $column[0] = str_replace($m[1], '', $column[0]);
                    $tickets[] = $m[1];
                }

                if (!isset($column[1]) && !empty($column2)) {
                    // PHUNWIPA SUPANTHA    F2IU8F
                    // CELESTE POTTER ROWAN F2IU8F
                    $column[0] = preg_replace("/ {$this->opt(array_unique($column2))}\s*$/", '', $column[0]);
                }
                $r->general()->traveller($column[0], true);
            }

            $tickets = array_filter(preg_replace('/^\s*[A-Z\d]{5,7}\s*$/', '', $tickets));

            if (count(array_filter($tickets)) > 0) {
                $r->issued()
                ->tickets(array_unique(array_filter($tickets)), false);
            }
        }

        $flightDetails = $this->re("/\n[ ]*{$this->opt($this->t('Flight Details'))}\n+((?:.+\n)?[ ]{0,20}{$this->opt($this->t('Depart/Arrive Time'))}[ ]*.+[ ]*{$this->opt($this->t('Flight No.'))}[ ]*{$this->opt($this->t('Class'))}(?:\n.*)+?)\n+[ ]*{$this->opt($this->t('Important Information'))}/", $text);

        if ($flightDetails) {
            $it = [];
            $flightDetails = preg_replace("/^[ ]*({$this->opt($this->t('Baggage Allowance'))}|\[[^\[\]]*{$this->opt($this->t('Baggage'))}[^\[\]]*\]).*/im", '', $flightDetails);
            $flightDetailsHeader = $this->re("/^(\s*(?:.+\n){1,2})/", $flightDetails);
            $tablePos = [0];

            if (preg_match("/^(.{17,}? ){$this->opt($this->t('dep'))} ?\/ ?{$this->opt($this->t('arr'))}(?: .+|$)/m", $flightDetailsHeader, $matches)
                || preg_match("/^(.{26,}? )Aeroporto de$/m", $flightDetailsHeader, $matches) // pt
            ) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.{20,}? ){$this->opt($this->t('Airline'))} .+/m", $flightDetailsHeader, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.{25,}? ){$this->opt($this->t('Flight No.'))} .+/m", $flightDetailsHeader, $matches)) {
                $tablePos[] = mb_strlen($matches[1]) - 1;
            }

            if (preg_match("/^(.{30,}? ){$this->opt($this->t('Class'))}(?:[ ]{2}|$)/m", $flightDetailsHeader, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            $segments = $this->splitCols($flightDetails, $tablePos);

            if (count($segments) < 4) {
                $this->logger->debug('Wrong segment table!');

                return;
            }

            //Depart\/Arrive Time
            $patternDate = "(?:"
                . "\b[[:alpha:]]+\s\d{1,2}\s*,\s*\d{4}\b" // May 1, 2020
                . "|\b\d{1,2}(?:[ ]|\sDe\s)[[:alpha:]]+(?:\s*,\s*|[ ]|\sDe\s)\d{4}\b" // 1 de Maio de 2020
            . ")";

            if (preg_match_all("/{$this->patterns['time']},\s{$patternDate}/iu", $segments[0], $datesMatches)
                || preg_match_all("/{$patternDate}\s*,\s*{$this->patterns['time']}/iu", $segments[0], $datesMatches)
            ) {
                if ((count($datesMatches[0]) % 2) !== 0) {
                    $this->logger->debug("The number of dates must be even");

                    return;
                }

                foreach ($datesMatches[0] as $key => $date) {
                    if (($key % 2) === 0) {
                        $it['depDate'][] = $this->normalizeDate($date);
                    } else {
                        $it['arrDate'][] = $this->normalizeDate($date);
                    }
                }
            }

            //Departure\/Arrival Airport
            $segments[1] = preg_replace("/^\s*{$this->opt($this->t('dep'))} ?\/ ?{$this->opt($this->t('arr'))}(?:\s+Airport)?/", '', $segments[1]);
            $airports = $this->split("/(\s+(?:Airport|Jetport|Sunport)(?:\s+T\s*\d+|\s+Terminal.+)?.*$)/m", $segments[1], true);

            if ((count($airports) % 2) !== 0) {
                $this->logger->debug("The number of airport must be even");

                return;
            }

            foreach ($airports as $key => $name) {
                if (($key % 2) === 0) {
                    if (preg_match("/^(.{3,}(?:Airport)?)\s+(?:T|Terminal)\s*(.+)/s", $name, $m)
                        || preg_match("/(.+(?:Airport|Jetport)?)\s+([A-Z\d]{1,2})\s*$/s", $name, $m)
                    ) {
                        $it['depName'][] = preg_replace("/\s+/", " ", trim($m[1]));
                        $it['depT'][] = trim($m[2]);
                    } else {
                        $it['depName'][] = preg_replace("/\s+/", " ", $name);
                        $it['depT'][] = null;
                    }
                } else {
                    if (preg_match("/(.+(?:Airport)?)\s+(?:T|Terminal)\s*(.+)/s", $name, $m)
                        || preg_match("/(.+(?:Airport|Jetport)?)\s+([A-Z\d]{1,2})\s*$/s", $name, $m)
                    ) {
                        $it['arrName'][] = preg_replace("/\s+/", " ", trim($m[1]));
                        $it['arrT'][] = trim($m[2]);
                    } else {
                        $it['arrName'][] = preg_replace("/\s+/", " ", $name);
                        $it['arrT'][] = null;
                    }
                }
            }

            //Terminal
            if (preg_match_all("/^(?!{$this->opt($this->t('dep'))}|{$this->opt($this->t('arr'))}).+?[\s\n]+Airport[\s]?(?:(?:T|Terminal)[\s]?([A-Z\d]+)|$)/m", $segments[1], $terminalMatches)) {
                /*if ((count($terminalMatches[0]) % 2) !== 0) {
                    $this->logger->debug("The number of airport must be even");

                    return;
                }*/

                foreach ($terminalMatches[1] as $key => $term) {
                    if (($key % 2) === 0) {
                        if (!empty($term)) {
                            $it['depT'][] = $term;
                        } else {
                            $it['depT'][] = null;
                        }
                    } else {
                        if (!empty($term)) {
                            $it['arrT'][] = $term;
                        } else {
                            $it['arrT'][] = null;
                        }
                    }
                }
            }
            //Flight No
            if (preg_match_all($pattern = '/\s(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,9}\s/', $segments[2], $flightMatches)
                || preg_match_all($pattern, $segments[3], $flightMatches)
                || preg_match_all($pattern, $flightDetails, $flightMatches)
            ) {
                foreach ($flightMatches[0] as $fText) {
                    if (preg_match("/\s([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,9})\s/", $fText, $m)) {
                        $it['airName'][] = $m[1];
                        $it['airNum'][] = $m[2];

                        $duration = $this->http->FindSingleNode("//text()[{$this->contains($this->t($m[1] . $m[2]))}]/following::text()[contains(normalize-space(), 'hrs') or contains(normalize-space(), 'min')][1]", null, true, "/^\s*(\d+.+)/");

                        if (!empty($duration)) {
                            $it['duration'][] = $duration;
                        }
                    }
                }
            }

            //Class
            $cabinText = preg_replace("/^\s*{$this->opt($this->t('Class'))}\n/", '', count($segments) > 4 ? $segments[4] : $segments[3]);

            if (preg_match_all("/^([[:upper:]][[:alpha:]]+?)(?:\s?(?i){$this->opt($this->t('Class'))}|$)/mu", $cabinText, $classMatches)
                || preg_match_all("/^(?:{$this->opt($this->t('Class'))}\s?)?([[:alpha:]]+)$/mu", $cabinText, $classMatches)
            ) {
                // Economy class    |    Classe econômica
                foreach ($classMatches[1] as $class) {
                    $it['class'][] = $class;
                }
            }

            $totalPrice = $this->http->FindSingleNode("//*[{$this->contains($this->t("Total"))}]/strong[not({$this->contains($this->t("Total"))})]", null, true, "/^.*\d.*$/");

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
                $currency = $this->normalizeCurrency($matches['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            foreach ($it["airNum"] as $k => $v) {
                $s = $r->addSegment();

                $s->departure()->noCode();
                $s->arrival()->noCode();

                if (!empty($v)) {
                    $s->airline()->number($v);
                }

                if (!empty($it["airName"][$k])) {
                    $s->airline()->name($it["airName"][$k]);
                }

                if (!empty($it["class"][$k])) {
                    $s->extra()->cabin($it["class"][$k]);
                }

                //dep
                if (!empty($it["depDate"][$k])) {
                    $s->departure()->date($it["depDate"][$k]);
                }

                if (!empty($it["depName"][$k])) {
                    $s->departure()->name($it["depName"][$k]);
                }

                if (!empty($it["depT"][$k])) {
                    if (strlen($it["depT"][$k]) < 50) {
                        $s->departure()->terminal($it["depT"][$k]);
                    }
                }
                //arr
                if (!empty($it["arrT"][$k])) {
                    $s->arrival()->terminal($it["arrT"][$k]);
                }

                if (!empty($it["arrName"][$k])) {
                    $s->arrival()->name($it["arrName"][$k]);
                }

                if (!empty($it["arrDate"][$k])) {
                    $s->arrival()->date($it["arrDate"][$k]);
                }

                if (!empty($it["duration"][$k])) {
                    $s->extra()
                        ->duration($it["duration"][$k]);
                }

                if (empty($it["depName"][$k]) && empty($it["arrName"][$k]) && empty($it["depDate"][$k]) && empty($it["arrName"][$k])) {
                    $r->removeSegment($s);
                }
            }
        }
    }

    private function parsePdfReceipt($text, Email $email): void
    {
        $this->logger->debug(__FUNCTION__);

        $cost = 0;
        $tot = 0;

        if (preg_match_all("/\n *Price Summary {3,}Amount\n*((?:.+\n*){1,5}\s*Total {3,}.+)/", $text, $match)) {
            foreach ($match[1] as $priceText) {
                //$priceText = $this->re("/\n *Price Summary {3,}Amount\n*((?:.+\n*){1,5}\s*Total {3,}.+)/", $text);
                $rows = preg_split("/\s*\n/", $priceText);
                $discount = 0.0;

                foreach ($rows as $row) {
                    $values = preg_split("/ {3,}/", trim($row));

                    if (count($values) == 2) {
                        $total = $this->getTotal($values[1]);

                        if (preg_match('/^' . $this->opt($this->t('Total')) . '$/', $values[0])) {
                            $tot += $total['amount'];
                            $email->price()
                                ->total($tot)
                                ->currency($total['currency']);
                        } elseif (preg_match('/^' . $this->opt($this->t('Fare')) . '$/', $values[0])) {
                            $cost += $total['amount'];
                            $email->price()
                                ->cost($cost);
                        } elseif (preg_match('/^' . $this->opt($this->t('Taxes & Fees')) . '$/', $values[0])) {
                            $email->price()
                                ->tax($total['amount']);
                        } elseif (preg_match("/\-([\d\.\,]+)/u", $values[1], $m)) {
                            $discount += PriceHelper::parse($m[1], $total['currency']);
                            $email->price()
                                ->discount($discount);
                        } else {
                            $email->price()
                                ->fee($values[0], $total['amount']);
                        }
                    }
                }
            }
        }
    }

    private function getTotal($text): array
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->normalizeCurrency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        return preg_replace([
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$2 $1',
        ], mb_strtoupper($s));
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 16:35, December 27, 2019
            '/^\s*(\d{1,2}:\d{1,2}),\s([A-z]+)\s(\d{1,2}),\s*(\d{4})\s*$/su',
            //  26 de outubro de 2021, 18:55
            '/^\s*(\d{1,2})\s+de\s+([[:alpha:]]+)\s+de\s+(\d{4}),\s*(\d{1,2}:\d{1,2})\s*$/su',
            // 23 December, 2021, 17:10
            '/^\s*(\d{1,2})\s*([A-z]+)\,\s*(\d{4})\,\s*(\d{1,2}:\d{1,2})$/su',
            // August 30, 2023, 18:40
            '/^\s*([A-z]+)\s*(\d{1,2})\,\s*(\d{4})\,\s*(\d{1,2}:\d{1,2})$/su',
            // 2024年8月13日 23:55
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日[,\s]+({$this->patterns['time']})\s*$/",
        ];

        $out = [
            //27 December 2019 16:35"
            '$3 $2 $4 $1',
            //  26 de outubro de 2021, 18:55
            '$1 $2 $3, $4',
            // 23 December, 2021, 17:10
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
            '$2/$3/$1, $4',
        ];
        $str = preg_replace($in, $out, $date);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match('/\d{1,2}(?:\s[A-z]+\s|\/\d{1,2}\/)\d{4}[\s,]+\d{1,2}:\d{1,2}/', $str)) {
            return strtotime($str);
        } else {
            return false;
        }
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'HKD' => ['HK$'],
            'INR' => ['₹'],
            'BRL' => ['R$'],
            'SGD' => ['S$'],
            'AUD' => ['AU$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{3,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
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

    private function split($re, $text, $beforeRe = false)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if ($beforeRe !== true) {
                array_shift($r);
            }

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
