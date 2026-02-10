<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: bcd/Itinerary1, bcd/TravelReceiptPdf, bcd/TravelReceiptPdf2 !but different

class TravelPlanPdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-12279453.eml, bcd/it-12999193.eml, bcd/it-12999365.eml, bcd/it-139837760.eml, bcd/it-17.eml, bcd/it-1995087.eml, bcd/it-2000871.eml, bcd/it-2004942.eml, bcd/it-2059465.eml, bcd/it-2074245.eml, bcd/it-2198171.eml, bcd/it-2207497.eml, bcd/it-2459556.eml, bcd/it-2553544.eml, bcd/it-2667477.eml, bcd/it-2723867.eml, bcd/it-2774729.eml, bcd/it-35119172.eml";

    public $reBody2 = [
        "de"  => "Reiseplan",
        "de2" => "Leistung",
        "en"  => "Travel Summary",
        "en2" => "Direct Travel Confirmation",
        "en3" => "Fare and Ticket Summary",
        "en4" => "Free Baggage Allowance",
        "en5" => "Invoice/Itinerary",
        "en6" => "Contact Information",
        "fr"  => "Itinéraire",
        "sv"  => "Leverantör",
    ];

    public static $dictionary = [
        "de" => [ // it-12279453.eml, it-2198171.eml, it-2459556.eml, it-35119172.eml
            // AIR / TRAIN
            "non-stop"=> ["non-stop", "non­stop"],

            // HOTEL
            "Tel."=> ["Tel.", "Tel"],

            // CAR
            "CAR" => "Mietwagen",
        ],
        "en" => [
            "Ticketnummer" => "Ticket Number",

            // AIR / TRAIN
            "Flug"            => "AIR",
            // "Bahn"   => "",
            // "Zugnummer" => "",
            "Abreise"         => "Depart",
            "Ankunft"         => "Arrive",
            "Terminal"        => "TERMINAL",
            "Fluggerät"       => "Equipment",
            "Durchgeführt von"=> "Operated By",
            "Sitzplatz"       => "Seat",
            "Dauer"           => "Duration",
            "non-stop"        => ["Non­stop", "Non-stop"],
            "Mitgliedsnummer" => "FF Number",
            "Distance"        => ["Distance", "Mileage"],

            // HOTEL
            "Hotel"                                      => "HOTEL",
            "Buchungsreferenz:"                          => ["Confirmation:", "Record Locator:", "Reference:", "Confirmation"],
            "Anreise / Abreise"                          => ["Check In/Check Out", "Check In / Check Out"],
            "Adresse"                                    => "Address",
            "Tel."                                       => ["Tel.", "Tel"],
            "Fax"                                        => "Fax",
            "Number of Rooms"                            => ["Number of Rooms", "Number Of Rooms"],
            "Reisende\(r\)[^\n]*"                        => "Travell?er(?:\(s\))?[^\n]*",
            "Preis pro Nacht"                            => ["Rate per night", "Rate"],
            "Stornobedingung"                            => ["Remarks", "Cancellation Policy"],
            "Beschreibung"                               => ["Description", "Room Type"],
            "Gesamtpreis"                                => "Est. Total Rate",
            "Voraussichtlicher Gesamtreisepreis:"        => ["Total Amount:", "Subtotal:"],
            "Folgendes Ticket wurde gerade ausgestellt:" => "The following tickets have just been issued:",

            // CAR
            "CAR"       => "CAR",
            "Anmietung" => "Pick Up",
            "Abgabe"    => "Drop Off",
            "Gesamt"    => "Estimated Total",
        ],
        "fr" => [
            "Ticketnummer" => "Numéro de billet",

            // AIR / TRAIN
            "Flug"   => "AIR",
            // "Bahn"   => "",
            // "Zugnummer" => "",
            "Abreise"=> "Départ",
            "Ankunft"=> "Arrivée",
            //			"Terminal"=>"",
            "Fluggerät"       => "Appareil",
            "Durchgeführt von"=> "Opéré par",
            "Sitzplatz"       => "Siège",
            "Dauer"           => "Temps de trajet",
            //			"non-stop"=>[""],
            //            "Mitgliedsnummer"=>"",
            //            "Distance"=>[""],

            // HOTEL
            "Hotel"            => "HÔTEL",
            "Buchungsreferenz:"=> ["Référence:"],
            "Anreise / Abreise"=> "Arrivée / Départ",
            "Adresse"          => "Adresse",
            "Tel."             => ["Tel.", "Tel"],
            "Fax"              => "Fax",
            //            "Number of Rooms"=>[""],
            "Reisende\(r\)[^\n]*"                => "Voyageur(?:\(s\))?[^\n]*",
            "Preis pro Nacht"                    => ["Tarif par nuit "],
            "Stornobedingung"                    => ["Politique d’annulation"],
            "Beschreibung"                       => ["Descriptif"],
            "Gesamtpreis"                        => "Total",
            "Voraussichtlicher Gesamtreisepreis:"=> ["Estimation du prix total du trajet:"],
            //			"Folgendes Ticket wurde gerade ausgestellt:" => "",

            // CAR
            "CAR"       => "VOITURE",
            "Anmietung" => "Départ",
            "Abgabe"    => "Retour",
            "Gesamt"    => "Total",
        ],
        "sv" => [
            "Ticketnummer" => "Biljettnummer",

            // AIR / TRAIN
            "Flug"      => "FLYG",
            "Bahn"      => "Rail",
            "Zugnummer" => "Tågnummer",
            "Abreise"   => "Avresa",
            "Ankunft"   => "Ankomst",
            // "Terminal"=>"",
            "Fluggerät"       => "Utrustning",
            // "Durchgeführt von" => "",
            "Sitzplatz"       => "Plats",
            "Dauer"           => "Varaktighet",
            // "non-stop" => "",
            // "Mitgliedsnummer" => "",
            // "Distance" => "",

            // HOTEL
            "Hotel"            => "HOTELL",
            "Buchungsreferenz:"=> "Referens:",
            // "Anreise / Abreise" => "",
            // "Adresse" => "",
            "Tel."             => "Telefon",
            // "Fax"=> "",
            // "Number of Rooms" => "",
            "Reisende\(r\)[^\n]*"                => "Resenär(?:\/er)?[^\n]*",
            // "Preis pro Nacht"                    => "",
            // "Stornobedingung"                    => "",
            // "Beschreibung"                       => "",
            "Gesamtpreis"                                => "Totalt",
            "Voraussichtlicher Gesamtreisepreis:"        => "Beräknat totalpris:",
            "Folgendes Ticket wurde gerade ausgestellt:" => "E-biljett nummer:",

            // CAR
            "CAR"       => "BIL",
            // "Anmietung" => "",
            // "Abgabe"    => "",
            // "Gesamt"    => "",
        ],
    ];

    public $lang = "de";
    private $pdfPattern = "(?:MDP Direct Travel Confirmation|Reiseplan & Ticket Receipt f[üüu]+r|Reiseplan f[üüu]+r|Travel Receipt Communication Attachment|Itinerary Communication Attachment|ACHTUNG_ Änderung der Reisedaten für|Itinerary Ticket Receipt for|Itinéraire pour|Billet électronique pour|Invoice -|ATTENTION_ Itinerary Change for|E-ticket kvitto|Detailed Itin and Invoice Email) .+.pdf";

    private $code;
    private $bodies = [
        'bcd' => [
            'BCD Travel',
            'CI Azumano',
        ],
        'directravel' => [
            'Direct Travel',
            "www.dt.ca\n",
        ],
    ];
    private $headers = [
        'bcd' => [
            'from' => ['@bcdtravel.com'],
            'subj' => [
                'Reiseplan & Ticket Receipt für', //de
                'Travel Receipt for', //en
                'Itinéraire pour', //fr
                'Billet électronique pour', //fr
            ],
        ],
        'directravel' => [
            'from' => ['@dt.com'],
            'subj' => [
                'Direct Travel Itinerary', //en
            ],
        ],
    ];
    private $text;
    private $date;
    private $namePrefixes = ['MISS', 'MRS', 'MR', 'MS', 'DR'];

    public static function getEmailProviders()
    {
        return ['bcd', 'directravel'];
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        $code = $this->getProvider($parser, $text);

        if (empty($code)) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $this->logger->notice('PDF-attachments not found!');

            return $email;
        }

        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return $email;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parsePdf($email);
        $email->setProviderCode($this->getProvider($parser, $this->text));

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $total = $this->re("#" . $this->opt($this->t("Voraussichtlicher Gesamtreisepreis:")) . "\s+(\d[\d,.]*\s+[A-Z]{3}|[A-Z]{3}\s+\d[\d,.]*|\S\s*[\d,.]+)#", $this->text);

        if ($total) {
            $email->price()
                ->total($this->amount($total), true, true)
                ->currency($this->currency($total), true, true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 4; //flight | hotel | car | train
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    private function getProvider(PlancakeEmailParser $parser, $text)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($text, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parsePdf(Email $email)
    {
        $text = $this->text;
        $text = str_ireplace(['&shy;', '&173;', '­'], '-', $text); // shy

        // TripNumber
        if (!empty($trip = $this->re("#Direct Travel Confirmation:\s+([A-Z\d]{5,})#", $text))) {
            $email->ota()->confirmation($trip);
        }

        $segments = $this->split("#\n\s*([^\s\d]+(?: - | ­ )(?:[^\s\d]+, \d+ [^\s\d]+ \d{4}|[^\s\d]+, [^\s\d]+ \d+ \d{4}).*?\n)#msu",
            $text);
        $flights = [];
        $hotels = [];
        $cars = [];
        $trains = [];

        foreach ($segments as $stext) {
            $type = strtolower($this->re("#^(\w+)(?: - | ­ )#u", $stext));

            switch ($type) {
                case strtolower($this->t('Flug')):
                    $flights[] = $stext;

                    break;

                case strtolower($this->t('Hotel')):
                    $hotels[] = $stext;

                    break;

                case strtolower($this->t('CAR')):
                    $cars[] = $stext;

                    break;

                case strtolower($this->t('Bahn')):
                    $trains[] = $stext;

                    break;

                default:
                    $this->logger->debug("Unknown type " . $type);

                    return;
            }
        }

        $pax = $this->re("#\n\s*" . $this->t("Reisende\(r\)[^\n]*") . "\n(.*/.*?)(?:\s{2,}|\n)#", $text);

        if (empty($pax)) {
            $pax = $this->re("#^ *([A-Z]+\/[A-Z]+[^\n]+).*?Direct Travel Confirmation:\s+#sm", $text);
        }

        if (empty($pax)) {
            $pax = $this->re("#\s+Passenger\(s\): *([A-Z\\/ \-]+)\n#", $text);
        }

        if (empty($pax)) {
            $paxRow = preg_split("#\s{2,}#", trim($this->re("#\n\s*(" . $this->t("Reisende\(r\)[^\n]*") . ")#i", $text)));

            if (!empty($paxRow[0]) && preg_match("#:[ ]?([A-Z]+(?: [A-Z]+){0,5}\/[A-Z]+(?: [A-Z]+){0,5})$#", $paxRow[0], $m)) {
                $pax = $m[1];
            }
        }

        if (empty($pax)) {
            $pax = $this->re("/Traveller\(s\).+Booking Reference\s[A-Z\d]{6}\n+([[:alpha:]][-.\/'’[:alpha:] ]*[[:alpha:]])\s+Fare and Ticket Details/", $this->text);
        }

        $pax = preg_replace("/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/", '$1', $pax);

        $patterns['eTicket'] = '\d{3}[-\s]*\d{7,}'; // 826-8500019868

        $tickets = [];
        $ticketText = $this->re("#" . $this->t("Folgendes Ticket wurde gerade ausgestellt:") . "[ ]*(.+)#", $text);

        if (!empty($ticketText)) {
            // it-12279453.eml
            $tickets = array_filter(explode(",", $ticketText), function ($v) use ($patterns) {
                if (!empty($v) && preg_match("#^\s*({$patterns['eTicket']})\s*$#", $v, $m)) {
                    return $m[1];
                } else {
                    return false;
                }
            });
        }

        if (
            count($tickets) === 0
            && preg_match_all("/{$this->opt($this->t('Ticketnummer'))}[: ]*$\s+^[\s\S]{1,200}?\D({$patterns['eTicket']})$/m", $text, $ticketMatches)
        ) {
            // it-5422822.eml
            $tickets = array_values(array_unique($ticketMatches[1]));
        }

        $textCodes = $this->cutText('Travel Summary', 'AIR - ', $text);

        if (preg_match_all('/\b(?<date>\d{1,2}\/\d{1,2}\/\d{4})[ ]+(?<codes>[A-Z]{3}[ ]*-[ ]*[A-Z]{3})[ ]+(?<flight>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+).*/', $textCodes, $codesMatches)) {
            // 09/30/2018    ADD-NLA    ET 875
            foreach ($codesMatches['codes'] as $i => $cs) {
                $fn = $codesMatches['date'][$i] . ' ' . preg_replace('/\s+/', '', $codesMatches['flight'][$i]);
                $codes[$fn] = $cs;
            }
        }

        //#################
        //##   FLIGHT   ###
        //#################

        $airs = [];

        foreach ($flights as $stext) {
            if (!$rl = $this->re("#" . $this->opt($this->t("Buchungsreferenz:")) . "\s+\*?([A-Z\d]{5,})#", $stext)) {
                if (!$rl = $this->re("#" . $this->opt($this->t("Status:")) . "\s+.*?([A-Z\d]{5,})\n#", $stext)) {
                    if (!$rl = $this->re("#" . $this->opt($this->t("Agency Record Locator")) . "\s+\*?([A-Z\d]{5,})#",
                        $stext)
                    ) {
                        $this->logger->debug("RL not matched");

                        return;
                    }
                }
            }
            $airs[$rl][] = $stext;
        }

        $accs = [];

        foreach ($airs as $rl => $segments) {
            $f = $email->add()->flight();

            // RecordLocator
            $f->general()->confirmation($rl);

            // Passengers
            $f->general()->traveller($pax);

            if (!empty($tickets)) {
                $f->issued()->tickets($tickets, false);
            }

            foreach ($segments as $stext) {
                $fields = [];

                foreach ($this->split("#\n((?:[^\n\s]+(?:[^\n\S]|:))+)#", $stext) as $ftext) {
                    if (preg_match("#(.*?):\s+(.+)#ms", $ftext, $m)) {
                        $fields[trim($m[1], '* ')] = implode("\n", array_map('trim', explode("\n", $m[2])));
                    }
                }

                if (empty($this->arrval($this->t("Abreise"), $fields)) && empty($this->arrval($this->t("Ankunft"), $fields))) {
                    if (preg_match("#\n( *({$this->opt($this->t("Abreise"))}) .+ +({$this->opt($this->t("Ankunft"))}) .+(?:\n.+)+?)\n\D+:#", $stext, $m)) {
                        $m[1] = str_replace('Weather', '       ', $m[1]);
                        $table = $this->splitCols($m[1], $this->rowColsPos($this->inOneRow($m[1])));
                        $table = array_values(array_filter(preg_replace("/^\s*({$this->opt($this->t("Abreise"))}|{$this->opt($this->t("Ankunft"))})\s*/", '', $table)));

                        if (count($table) == 2) {
                            $fields[$m[2]] = $table[0];
                            $fields[$m[3]] = $table[1];
                        }
                    }
                }

                $date = strtotime($this->normalizedate($this->re("#{$this->t('Flug')}" . "(?: - | ­ )(.*?)\n#i",
                    $stext)));

                if ($date == false) {
                    $date = strtotime($this->normalizedate($this->re("#" . $this->t("Flug") . "(?: - | ­ )(.+?)\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+\*?\s#i",
                        $stext)));
                }

                if ($date) {
                    $this->date = $date;
                }

                $s = $f->addSegment();
                // FlightNumber
                if (preg_match("#^[^\n]+\n([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)\*?(?:\s|\()#", $stext, $m)
                    || preg_match("# Flight ([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)\*?\s#", $stext, $m)
                    || preg_match("#" . $this->t("Flug") . "(?: - | ­ ).+?\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)\*?\s#i", $stext, $m)
                ) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                // DepCode
                $node = $this->re("#\(([A-Z]{3})\)#", $this->arrval($this->t("Abreise"), $fields));

                if (!empty($node)) {
                    $s->departure()->code($node);
                }

                // DepName
                $s->departure()->name(trim(
                    $this->re("#(.+?)(?:, [^,]*" . $this->t("Terminal") . "|\n)#i", $this->arrval($this->t("Abreise"), $fields))
                    . ', ' .
                    $this->re("#.*?(?:, [^,]*" . $this->t("Terminal") . ".*?)?\n(.*\n)?\d{1,2}:#i", $this->arrval($this->t("Abreise"), $fields)), ' ,'));

                // DepartureTerminal
                $node = $this->re("#, ([^,]*?" . $this->t("Terminal") . ".*?)(?:\n|\([A-Z]{3}\))#i",
                    $this->arrval($this->t("Abreise"), $fields));
                $node = preg_replace("#\s*Terminal\s*#i", '', $node);

                if (empty($node)) {
                    $node = $this->re("#, *(" . $this->t("Terminal") . " *\n.+?)(?:\n|\([A-Z]{3}\))#i",
                        $this->arrval($this->t("Abreise"), $fields));
                    $node = preg_replace("#\s*Terminal\s*#i", '', $node);
                }

                if (!empty($node)) {
                    $s->departure()->terminal($node);
                }

                // DepDate
                $node = strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                    $this->arrval($this->t("Abreise"), $fields))), $date);

                if (empty($node)) {
                    $node = strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                        $this->arrval($this->t('Baggage Info'), $fields))), $date);
                }
                $depDate = $node;
                $s->departure()->date($node);

                // ArrCode
                $node = $this->re("#\(([A-Z]{3})\)#", $this->arrval($this->t("Ankunft"), $fields));

                if (!empty($node)) {
                    $s->arrival()->code($node);
                }

                // ArrName
                $s->arrival()->name(trim(
                    $this->re("#(.*?)(?:, [^,]*" . $this->t("Terminal") . "|\n)#i", $this->arrval($this->t("Ankunft"), $fields))
                    . ', ' .
                    $this->re("#.*?(?:, [^,]*" . $this->t("Terminal") . ".*?)?\n(.*\n)?\d{1,2}:#i", $this->arrval($this->t("Ankunft"), $fields)), ' ,'));

                // ArrivalTerminal
                $node = $this->re("#, ([^,]*" . $this->t("Terminal") . ".*?)(?:\n|\([A-Z]{3}\))#i",
                    $this->arrval($this->t("Ankunft"), $fields));
                $node = preg_replace("#\s*Terminal\s*#i", '', $node);

                if (empty($node)) {
                    $node = $this->re("#, *(" . $this->t("Terminal") . " *\n.+?)(?:\n|\([A-Z]{3}\))#i",
                        $this->arrval($this->t("Ankunft"), $fields));
                    $node = preg_replace("#\s*Terminal\s*#i", '', $node);
                }

                if (!empty($node)) {
                    $s->arrival()->terminal($node);
                }

                // ArrDate
                $s->arrival()->date(strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                    $this->arrval($this->t("Ankunft"), $fields))), $date));

                if (empty($s->getDepCode()) && empty($s->getArrCode())
                    && !empty($codes) && !empty($depDate) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                    if ((!empty($codes[date('m/d/Y', $depDate) . ' ' . $s->getAirlineName() . $s->getFlightNumber()])
                            && preg_match('/([A-Z]{3})[ ]*-[ ]*([A-Z]{3})/', $codes[date('m/d/Y', $depDate) . ' ' . $s->getAirlineName() . $s->getFlightNumber()], $m))
                        || (!empty($codes[date('d/m/Y', $depDate) . ' ' . $s->getAirlineName() . $s->getFlightNumber()])
                            && preg_match('/([A-Z]{3})[ ]*-[ ]*([A-Z]{3})/', $codes[date('d/m/Y', $depDate) . ' ' . $s->getAirlineName() . $s->getFlightNumber()], $m))
                    ) {
                        $s->departure()->code($m[1]);
                        $s->arrival()->code($m[2]);
                    }
                }

                if (empty($s->getDepCode()) && !empty($s->getDepName())) {
                    $s->departure()->noCode();
                }

                if (empty($s->getArrCode()) && !empty($s->getArrName())) {
                    $s->arrival()->noCode();
                }

                // Aircraft
                if (!empty($node = $this->arrval($this->t("Fluggerät"), $fields))) {
                    $s->setAircraft($node);
                }

                // TraveledMiles
                if (!empty($node = $this->arrval($this->t("Distance"), $fields))) {
                    $s->setMiles($node);
                }

                // Cabin
                if (!empty($node = $this->re("# Flight (?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+ +([^\n]+?)(?i)(?: {2,}|" . $this->t("Durchgeführt von") . "|\s*-\s*{$this->opt($this->t("Sitzplatz"))}|\n)#u", $stext))) {
                    if (preg_match("#(.+) \W ([A-Z]{1,2})\s*$#", $node, $m)) {
                        $s->extra()
                            ->cabin($m[1])
                            ->bookingCode($m[2]);
                    } else {
                        $s->setCabin($node);
                    }
                }

                // operatedBy
                if (!empty($node = $this->arrval($this->t("Durchgeführt von"), $fields))
                    || ($node = $this->re("#" . $this->t("Durchgeführt von") . " (.+?)(?:[ ]{2,}|\n)#i", $stext))) {
                    // it-2000871.eml
                    $node = preg_replace("/.*{$this->opt($this->t("Durchgeführt von"))}[:\s]+(.+)/is", '$1', $node);
                    $s->setOperatedBy(preg_replace('/\s+/', ' ', $node));
                }

                // Seats
                if (!empty($node = $this->re("#(\d+\w)(?:\s|\(|$)#", $this->arrval($this->t("Sitzplatz"), $fields)))) {
                    $s->addSeat($node);
                }

                // Duration
                if (!empty($node = $this->re("#(.*?)\s+" . $this->opt($this->t("non-stop")) . "#i",
                    $this->arrval($this->t("Dauer"), $fields)))
                ) {
                    $s->setDuration($node);
                }

                // Meal
                if (!empty($node = $this->arrval($this->t("Meal"), $fields))) {
                    $s->addMeal($node);
                }

                // AccountNumbers
                if (!empty($node = $this->re("#^ *([A-Z\d]{5,})#",
                    $this->arrval($this->t("Mitgliedsnummer"), $fields)))
                ) {
                    $accs[] = $node;
                }

                // Smoking
                // Stops
                if (!empty($node = $this->re("#(" . $this->opt($this->t("non-stop")) . ")#",
                    $this->arrval($this->t("Dauer"), $fields)))
                ) {
                    $s->setStops(0);
                }
            }

            $accs = array_values(array_unique(array_filter($accs)));

            if (!empty($accs)) {
                $f->program()->accounts($accs, false);
            }
        }

        //################
        //##   HOTEL   ###
        //################

        foreach ($hotels as $htext) {
            $fields = [];

            foreach ($this->split("#\n((?:[^\n\s]+(?:[^\n\S]|:))+)#", $htext) as $ftext) {
                if (preg_match("#(.*?):\s+(.+)#ms", $ftext, $m)) {
                    $fields[$m[1]] = implode("\n", array_map('trim', explode("\n", $m[2])));
                }
            }
            $h = $email->add()->hotel();

            // ConfirmationNumber
            $h->general()->confirmation($this->re("#" . $this->opt($this->t("Buchungsreferenz:")) . "\s+(.+)#",
                $htext));

            // Hotel Name
            $node = $this->re("#^[^\n]+\n*(?:.*[:?].*\n)?([^:?]*?)(?:\s{2,}|\n)#", $htext);

            if (in_array(trim($node, " :"), (array) $this->t("Adresse"))) {
                $node = $this->re("#^[^\n]+\d{4}\s*([^\n]+)\s+{$this->opt($this->t("Adresse"))}#", $htext);
            }
            $h->hotel()->name($node);

            // CheckInDate
            // CheckOutDate
            $checkIn = strtotime($this->normalizeDate($this->re("#(.*?)(?: - | ­ )#",
                $this->arrval($this->t("Anreise / Abreise"), $fields))));

            $checkOut = strtotime($this->normalizeDate($this->re("#(?: - | ­ )(.+)#",
                $this->arrval($this->t("Anreise / Abreise"), $fields))));

            $this->logger->error("/{$this->opt($this->t("Anreise / Abreise"))}\:\s*(?<checkIn>.+\d{4})\s*\-\s*(?<checkOut>.+\d{4})/u");

            if (empty($checkIn) && empty($checkOut)
                && preg_match("/{$this->opt($this->t("Anreise / Abreise"))}\:\s*(?<checkIn>.+\d{4})\s*\-\s*(?<checkOut>.+\d{4})/u", $htext, $m)) {
                $checkIn = strtotime($this->normalizeDate($m['checkIn']));
                $checkOut = strtotime($this->normalizeDate($m['checkOut']));
            }

            $h->booked()
                ->checkIn($checkIn)
                ->checkOut($checkOut);

            // Address
            // Phone
            // Fax
            $phone = preg_replace("#[^\d \(\)\+\-]#", '', $this->arrval($this->t("Tel."), $fields));

            if (empty($phone)) {
                $phone = $this->re("/Tel\s*(\+[\s\d\(\)\-]+)\n/", $htext);
            }
            $fax = preg_replace("#[^\d \(\)\+\-]#", '', $this->arrval($this->t("Fax"), $fields));

            if (empty($fax)) {
                $fax = $this->re("/Fax\s*(\+[\s\d\(\)\-]+)\n/", $htext);
            }

            if (empty($address) && !empty($phone)) {
                $addressPart = $this->re("/{$this->opt($this->t("Adresse"))}[\s:]+(.+?)\s+{$this->opt($this->t("Anreise / Abreise"))}/s", $htext);
                $address = preg_replace('/\s+/', ' ', preg_replace("/\n\s*(?:Tel|{$this->opt($this->t("Tel."))}|{$this->opt($this->t("Fax"))})[\s\S]+/", '', $addressPart));
            }

            $h->hotel()
                ->address($address)
                ->phone($phone, true)
                ->fax($fax, true, true);

            // GuestNames
            $h->general()->traveller($pax);

            // Guests
            $h->booked()->guests($this->arrval($this->t("Number of Persons"), $fields), false, true);

            // Rooms
            $h->booked()->rooms($this->arrval($this->t("Number of Rooms"), $fields), true, true);

            // Rate
            $r = $h->addRoom();
            $r->setRate($this->arrval($this->t("Preis pro Nacht"), $fields));

            // RateType
            // CancellationPolicy
            if (!empty($cancellation = str_replace("\n", " ", $this->arrval($this->t("Stornobedingung"), $fields)))) {
                $h->setCancellation($cancellation);
            }

            // RoomTypeDescription
            $r->setDescription(trim(str_replace("\n", " ", $this->arrval($this->t("Beschreibung"), $fields))), true, true);

            // Total
            // Currency
            $total = $this->amount($this->re("#^([A-Z]{3} *[\d,.]+|[\d,.]+ [A-Z]{3})#",
                $this->arrval($this->t("Gesamtpreis"), $fields)));

            $currency = $this->currency($this->re("#^([A-Z]{3} *[\d,.]+|[\d,.]+ [A-Z]{3})#",
                $this->arrval($this->t("Gesamtpreis"), $fields)));

            if (!empty($total) && !empty($currency)) {
                $h->price()
                    ->total($total, true, true)
                    ->currency($currency, true, true);
            }
        }

        //###############
        //##   CARS   ###
        //###############
        foreach ($cars as $ctext) {
            $fields = [];

            foreach ($this->split("#\n((?:[^\n\s]+(?:[^\n\S]|:))+)#", $ctext) as $ftext) {
                if (preg_match("#(.*?):\s+(.+)#ms", $ftext, $m)) {
                    $fields[trim($m[1], '* ')] = implode("\n", array_map('trim', explode("\n", $m[2])));
                }
            }

            $r = $email->add()->rental();

            // Number
            $r->general()->confirmation($this->re("#" . $this->opt($this->t("Buchungsreferenz:")) . "\s+[^\n]*?([A-Z\d]{5,})#",
                $ctext));

            // PickupDatetime
            // PickupLocation
            // PickupPhone
            // PickupFax
            $r->pickup()
                ->location(trim($this->re("#^(.*?)(?:;|" . $this->opt($this->t("Tel.")) . ")#",
                    str_replace("\n", " ", $this->arrval($this->t("Anmietung"), $fields)))))
                ->date(strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                    $this->arrval($this->t("Anmietung"), $fields)))))
                ->phone(trim($this->re("#" . $this->opt($this->t("Tel.")) . ":\s+([\d\(\)\+ ­\-]{4,})#",
                    $this->arrval($this->t("Anmietung"), $fields))), true, false)
                ->fax(trim($this->re("#" . $this->opt($this->t("Fax")) . ":\s+([\d\(\)\+ ­\-]{4,})#",
                    $this->arrval($this->t("Anmietung"), $fields))), true, false);

            // DropoffDatetime
            // DropoffLocation
            // DropoffPhone
            // DropoffFax
            $r->dropoff()
                ->location(trim($this->re("#^(.*?)(?:;|" . $this->opt($this->t("Tel.")) . "|\d+:\d+)#",
                    str_replace("\n", " ", $this->arrval($this->t("Abgabe"), $fields)))))
                ->date(strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                    $this->arrval($this->t("Abgabe"), $fields)))))
                ->phone(trim($this->re("#" . $this->opt($this->t("Tel.")) . ":\s+([\d\(\)\+ ­\-]{4,})#",
                    $this->arrval($this->t("Abgabe"), $fields))), true)
                ->fax(trim($this->re("#" . $this->opt($this->t("Fax")) . ":\s+([\d\(\)\+ ­\-]{4,})#",
                    $this->arrval($this->t("Abgabe"), $fields))), true);

            // RentalCompany
            $node = $this->re("#^[^\n]+\n(.*?)(?:\s{2,}|\n)#", $ctext);

            if (in_array(trim($node, " :"), (array) $this->t("Anmietung"))) {
                $node = $this->re("#^[^\n]+\d{4}\s*([^\n]+)\s+{$this->opt($this->t("Anmietung"))}#", $ctext);
            }

            if (empty($node)) {
                $node = $this->re("#^[^\n]+\d{4}\s*([^\n]+?)(?: {2,}|\n)#", $ctext);
            }
            $r->setCompany($node);

            // CarType
            $node = $this->arrval($this->t("Type"), $fields);

            if (empty($node) && !empty($r->getCompany())) {
                $node = trim($this->re("#{$r->getCompany()}\s+(\S.+) {2,}.*?{$this->opt($this->t("Buchungsreferenz:"))}#",
                    $ctext));

                if (empty($node) && !empty($r->getCompany())) {
                    $node = trim($this->re("#{$r->getCompany()}\s+(\S.+) [A-Z]{2} {$this->opt($this->t("Buchungsreferenz:"))}#",
                        $ctext));
                }

                if (!empty($add = $this->re("#" . $this->opt($this->t("Buchungsreferenz:")) . "\s*\n\s*(\S[^\n]*?)[ ]+[A-Z\d]{5,}\s*\n#", $ctext))
                    || !empty($add = $this->re("#" . $this->opt($this->t("Buchungsreferenz:")) . "[ ](?:[A-Z\d]+)\s*\n\s{10,}(\S.+)#", $ctext))
                ) {
                    $node .= ' ' . trim($add);
                }
            }
            $r->car()->model($node);

            // RenterName
            $r->general()->traveller($pax);

            // TotalCharge
            // Currency
            $payment = $this->re("#^([A-Z]{3} *[\d,.]+|[\d,.]+ [A-Z]{3})#", $this->arrval($this->t("Gesamt"), $fields));

            if ($payment !== null) {
                $r->price()
                    ->total($this->amount($payment), true, true)
                    ->currency($this->currency($payment), true, true);
            }

            // accountNumbers
            if (preg_match('/^[-A-Z\d ]{5,}$/', $this->arrval($this->t("Loyalty Number"), $fields), $m)) {
                $r->addAccountNumber($m[0], false);
            }

            // Status
            if (!empty($node = $this->arrval($this->t("Status"), $fields))) {
                $r->general()->status($node);
            }
        }

        //################
        //##   TRAIN   ###
        //################
        $sortTrains = [];

        foreach ($trains as $stext) {
            if (!$rl = $this->re("#" . $this->opt($this->t("Buchungsreferenz:")) . "\s+\*?([A-Z\d]{5,})#", $stext)) {
                if (!$rl = $this->re("#" . $this->opt($this->t("Status:")) . "\s+.*?([A-Z\d]{5,})\n#", $stext)) {
                    if (!$rl = $this->re("#" . $this->opt($this->t("Agency Record Locator")) . "\s+\*?([A-Z\d]{5,})#",
                        $stext)
                    ) {
                        $rl = CONFNO_UNKNOWN;
                    }
                }
            }
            $sortTrains[$rl][] = $stext;
        }

        $accs = [];

        foreach ($sortTrains as $rl => $segments) {
            $t = $email->add()->train();

            // RecordLocator
            if ($rl === CONFNO_UNKNOWN) {
                $t->general()->noConfirmation();
            } else {
                $t->general()->confirmation($rl);
            }

            // Passengers
            $t->general()->traveller($pax);

            foreach ($segments as $stext) {
                $fields = [];

                foreach ($this->split("#\n((?:[^\n\s]+(?:[^\n\S]|:))+)#", $stext) as $ftext) {
                    if (preg_match("#(.*?):\s+(.+)#ms", $ftext, $m)) {
                        $fields[trim($m[1], '* ')] = implode("\n", array_map('trim', explode("\n", $m[2])));
                    }
                }

                $date = strtotime($this->normalizedate($this->re("#" . $this->opt($this->t("Bahn")) . "(?: - | ­ )(.*?)\n#i",
                    $stext)));

                if ($date == false) {
                    $date = strtotime($this->normalizedate($this->re("#" . $this->opt($this->t("Bahn")) . "(?: - | ­ )(.+?)\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+\*?\s#i",
                        $stext)));
                }

                $s = $t->addSegment();

                // Number
                if (preg_match("/\n[ ]*(.+?)[ ]{3,}{$this->opt($this->t('Zugnummer'))}[: ]+([A-Z\d]*?)\s*(\d+)/", $stext, $m)) {
                    // Rail    Tågnummer:712-1    Referens: FXV4382V
                    $s->extra()
                        ->number($m[3])
                        ->service($m[1]);

                    if (!empty($m[2])) {
                        $s->extra()
                            ->type($m[2]);
                    }
                } elseif (preg_match("/\n[ ]*({$this->opt($this->t('Bahn'))})[ ]{3,}{$this->opt($this->t('Buchungsreferenz:'))}/", $stext, $m)) {
                    // Rail        Referens: FXV4382V
                    $s->extra()->service($m[1])->noNumber();
                }

                // DepCode
                $node = $this->re("#\(([A-Z]{3})\)#", $this->arrval($this->t("Abreise"), $fields));

                if (!empty($node)) {
                    $s->departure()->code($node);
                }

                // DepName
                $s->departure()->name($this->re("#(.+?)(?:, " . $this->opt($this->t("Terminal")) . "|\n)#i",
                    $this->arrval($this->t("Abreise"), $fields)));

                // DepDate
                $node = strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                    $this->arrval($this->t("Abreise"), $fields))), $date);

                if (empty($node)) {
                    $node = strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                        $this->arrval($this->t('Baggage Info'), $fields))), $date);
                }
                $s->departure()->date($node);

                // ArrCode
                $node = $this->re("#\(([A-Z]{3})\)#", $this->arrval($this->t("Ankunft"), $fields));

                if (!empty($node)) {
                    $s->arrival()->code($node);
                }

                // ArrName
                $s->arrival()->name($this->re("#(.*?)(?:, " . $this->opt($this->t("Terminal")) . "|\n)#i",
                    $this->arrval($this->t("Ankunft"), $fields)));

                // ArrDate
                $s->arrival()->date(strtotime($this->normalizeDate($this->re("#\n(.+)$#",
                    $this->arrval($this->t("Ankunft"), $fields))), $date));

                // TraveledMiles
                if (!empty($node = $this->arrval($this->t("Distance"), $fields))) {
                    $s->setMiles($node);
                }

                // Seats
                if (preg_match_all("/Wagen[: ]+(\w+)[ \/]+{$this->opt($this->t("Sitzplatz"))}[: ]+(\d+)/", $this->arrval($this->t("Sitzplatz"), $fields), $seatMatches)) {
                    $s->extra()
                        ->car(implode(',', $seatMatches[1]))
                        ->seats($seatMatches[2]);
                }

                // Duration
                if (!empty($node = $this->re("#(.*?)\s+" . $this->opt($this->t("non-stop")) . "#i",
                    $this->arrval($this->t("Dauer"), $fields)))
                ) {
                    $s->setDuration($node);
                }

                // Cabin
                if (!empty($node = $this->arrval($this->t("Klasse"), $fields))) {
                    $s->setCabin($node);
                }

                // Meal
                if (!empty($node = $this->arrval($this->t("Meal"), $fields))) {
                    $s->addMeal($node);
                }

                // AccountNumbers
                if (!empty($node = $this->re("#^ *([A-Z\d]{5,})#",
                    $this->arrval($this->t("Mitgliedsnummer"), $fields)))
                ) {
                    $accs[] = $node;
                }

                // Smoking
                // Stops
                if (!empty($node = $this->re("#(" . $this->opt($this->t("non-stop")) . ")#",
                    $this->arrval($this->t("Dauer"), $fields)))
                ) {
                    $s->setStops(0);
                }
            }

            $accs = array_values(array_unique(array_filter($accs)));

            if (!empty($accs)) {
                $t->program()->accounts($accs, false);
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //Montag, 15 Januar 2018
            "#^(\d+:\d+), (\d+) ([^\s\d]+)$#", //06:50, 16 Januar
            "#^[^\s\d]+, ([^\s\d]+) (\d+) (\d{4})$#", //Montag, 15 Januar 2018
            "#^(\d+:\d+ [AP]M) [^\s\d]+,? ([^\s\d]+) (\d+) (\d{4})$#", //10:30 AM Monday, September 29 2014
            "#^[^\s\d]+, ([^\s\d]+) (\d+) (\d{4})$#", //Monday, September 29 2014
            "#^(\d+:\d+),?\s+\w+,?\s+(\d+)\s+(\w+)\s+(\d+)$#u", //23:00 Dienstag, 06 März 2018
        ];
        $out = [
            "$1",
            "$2 $3 $year, $1",
            "$2 $1 $3",
            "$3 $2 $4, $1",
            "$2 $1 $3",
            "$2 $3 $4 $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function amount($s)
    {
        return str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function arrval($key, $arr)
    {
        if (is_array($key)) {
            foreach ($key as $value) {
                if (isset($arr[$value])) {
                    return $arr[$value];
                }
            }

            return null;
        } else {
            return $arr[$key] ?? null;
        }
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
