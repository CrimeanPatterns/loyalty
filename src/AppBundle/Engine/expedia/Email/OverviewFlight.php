<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class OverviewFlight extends \TAccountChecker
{
    public $mailFiles = "expedia/it-10063558.eml, expedia/it-10084345.eml, expedia/it-12234295.eml, expedia/it-12539604.eml, expedia/it-20947769.eml, expedia/it-30899524.eml, expedia/it-3491967.eml, expedia/it-3511882.eml, expedia/it-3570851.eml, expedia/it-3594374.eml, expedia/it-3600660.eml, expedia/it-3983941.eml, expedia/it-4019202.eml, expedia/it-4638984.eml, expedia/it-4876085.eml, expedia/it-4878829.eml, expedia/it-4884097.eml, expedia/it-4923457.eml, expedia/it-4933053.eml, expedia/it-4935111.eml, expedia/it-4959802.eml, expedia/it-4959804.eml, expedia/it-5026928.eml, expedia/it-5048506.eml, expedia/it-50924698.eml, expedia/it-5144279.eml, expedia/it-5233197.eml, expedia/it-5343745.eml, expedia/it-5400861.eml, expedia/it-5470370.eml, expedia/it-5682086.eml, expedia/it-5687762.eml, expedia/it-57367655.eml, expedia/it-58096013.eml, expedia/it-59306983.eml, expedia/it-6091057.eml, expedia/it-6125799.eml, expedia/it-6180435.eml, expedia/it-6226789.eml, expedia/it-66442820.eml, expedia/it-6692057.eml, expedia/it-8906711.eml, expedia/it-8910344.eml, expedia/it-9864206.eml, expedia/it-9985250.eml";

    public static $headers = [
        'cheaptickets' => [
            'from' => ['cheaptickets.com'],
            'subj' => [
                'CheapTickets travel confirmation',
            ],
        ],
        'ebookers' => [
            'from' => ['ebookers.com'],
            'subj' => [
                'ebookers travel confirmation',
                'ebookers-Reisebestätigung',
                'Votre confirmation de voyage ebookers',
            ],
        ],
        'hotels' => [
            'from' => ['@support-hotels.com'],
            'subj' => [
                'en' => 'Hotels.com travel confirmation',
            ],
        ],
        'hotwire' => [
            'from' => ['noreply@Hotwire.com'],
            'subj' => [
                'en' => 'Hotwire travel confirmation',
            ],
        ],
        'mrjet' => [
            'from' => ['mrjet.se'],
            'subj' => [
                'sv' => 'Resebekräftelse från MrJet',
            ],
        ],
        'orbitz' => [
            'from' => ['orbitz.com'],
            'subj' => [
                'Orbitz travel confirmation',
            ],
        ],
        'rbcbank' => [
            'from' => ['rbcrewardstravel@rbcrewards.com'],
            'subj' => [
                'RBC Travel travel confirmation',
            ],
        ],
        'travelocity' => [
            'from' => ['email@e.travelocity.com'],
            'subj' => [
                'Travelocity travel confirmation',
            ],
        ],
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                "Expedia travel confirmation",
                "Conferma di viaggio Expedia",
                "Confirmación de viaje de Expedia - ",
                'Expedia-Reisebestätigung',
                'Reisbevestiging van Expedia',
                'Votre confirmation de voyage Expedia',
                'Expedia-reisebekreftelse',
                'Rejsebekræftelse fra Expedia - ',
                'Resebekräftelse från Expedia -',
                'Confirmação de viagem da Expedia',
                'Compensation for your delayed flight',
                'エクスペディアの旅行確認通知',
            ],
        ],
    ];

    private $code = '';

    private $date = null;

    private $reBody2 = [
        'es' => ["Duración total"],
        'nl' => ["Vluchtoverzicht"],
        'fr' => ["Aperçu du vol"],
        'no' => ["Flyoversikt"],
        'de' => ["Flug-Übersicht", "Flugübersicht"],
        'it' => ["Durata totale"],
        'da' => ["Flyoversigt"],
        'sv' => ["Flygöversikt"],
        'pt' => ["Duração total"],
        'ja' => ["航空券の概要"],
        'fi' => ["Yhteenveto lennosta"],
        'en' => ["Traveler(s)", "Traveller(s)", 'Travellers', 'Your reservation is booked and confirmed', "Request Compensation", "was delayed", 'was cancelled'],
        'zh' => ['航班槪覽', '行程日期', '总飞行时间'],
    ];

    private $bodies = [
        'chase' => [
            '//img[contains(@src,"chase.com")]',
            'Chase Travel',
        ],
        'cheaptickets' => [
            '//img[contains(@src,"cheaptickets.com")]',
            'cheaptickets.com',
            'Call CheapTickets customer',
        ],
        'ebookers' => [
            '//img[contains(@alt,"ebookers.com")]',
            'Collected by ebookers',
        ],
        'hotels' => [
            '//img[contains(@src,"Hotels.com")]',
            "Hotels.com",
        ],
        'hotwire' => [
            '//img[contains(@alt,"Hotwire.com")]',
            'Hotwire',
        ],
        'mrjet' => [
            '//img[contains(@src,"MrJet.se")]',
            'MrJet.se',
        ],
        'orbitz' => [
            '//img[contains(@alt,"Orbitz.com") or contains(@src,".orbitz.com/") or contains(@src,".orbitz.com%2F")]',
            '//*[contains(.,"Orbitz.com") or contains(normalize-space(),"Call Orbitz customer care") or contains(normalize-space(),"This Orbitz Itinerary was sent from")]',
        ],
        'rbcbank' => [
            '//img[contains(@src,"rbcrewards.com")]',
            'rbcrewards.com',
        ],
        'travelocity' => [
            '//img[contains(@src,"travelocity.com")]',
            'travelocity.com',
            'Collected by Travelocity',
        ],
        'expedia' => [
            '//img[contains(@alt,"expedia.com")]',
            'expedia.com',
        ],
    ];

    private $reBody = [
        'CheapTickets',
        'ebookers',
        'Hotels.com',
        'Hotwire',
        'MrJet',
        'Orbitz',
        'RBC Travel',
        'Travelocity',
        'Expedia',
    ];

    private $lang = '';
    private $subject = '';
    private $emailCurrency = '';
    private $countAirs;

    private static $dictionary = [
        'es' => [
            "Confirmation"    => "Confirmación",
            "Booking ID"      => "ID de reserva",
            "Flight overview" => "Aspectos generales del vuelo",
            "emptyPNR"        => "/boleto\s+aún\s+no\s+está\s+confirmado/i",
            "Itinerary #"     => ["No. de itinerario", "N.º de itinerario"],
            "Passengers"      => ["Pasajero(s)", "Viajeros"],
            "Total:"          => "Total del vuelo:",
            "Taxes and Fees:" => ["Impuestos y cargos:", "Tasas e impuestos:"],
            "Travel dates"    => ["Fechas de viaje", "Fechas del viaje"],
            "Cabin"           => ["Cabina", "Clase"],
            "duration"        => ["de tiempo de vuelo", "Duración"],
            "segment"         => ["Salida", "Ida", "Vuelta", "Departure", "Return", 'Regreso'],
            "operated by"     => "operado por",
            "Seat:"           => "Asiento:",
            "TicketNumber"    => ["Número de billete", "No. de boleto"],
            "#reCurrency#"    => ["#Todos los precios se (?:muestran|indican) en \.?([^\.]+)(?:\.|$)#"],
            "Cost"            => "Vuelo",
        ],
        'zh' => [
            "Confirmation"    => ["確認編號", '确认编号'],
            "Booking ID"      => ["預訂 ID", '预订 ID'],
            "Flight overview" => "航班槪覽",
            //            "emptyPNR" => "/boleto\s+aún\s+no\s+está\s+confirmado/i",
            "Itinerary #"     => ["行程編號", '行程编号'],
            "Passengers"      => ["旅客"],
            "Total:"          => ["合計", "總價"],
            "Taxes and Fees:" => ["稅項及附加費", "稅金和費用", "信用卡手續費："],
            "Travel dates"    => ["旅遊日期", '旅行日期'],
            "Cabin"           => ["客艙：", "艙等：", "舱位："],
            "duration"        => ["飛行時間", "飞行时间"],
            "segment"         => ["出發", "出发"],
            //"Cost" => "",
            "operated by" => "由",
            "Seat:"       => "座位：",
            "TicketNumber"=> ["機票號碼", "票号"],
            //			"#reCurrency#" => "#([^\.]+)(?:\.|$)#",
        ],
        'nl' => [
            "Confirmation"    => "Bevestiging",
            "Booking ID"      => "Boekingsnummer van",
            "Flight overview" => "Vluchtoverzicht",
            //			"emptyPNR" => "",
            "Itinerary #"     => "Reisplannummer",
            "Passengers"      => ["Reiziger(s)"],
            "Total:"          => ["Totaalbedrag vlucht:", "Totaal"],
            "Taxes and Fees:" => ["Belastingen en toeslagen:", "Boekingskosten van Expedia", "Belastingen & toeslagen"],
            "Travel dates"    => "Reisdatums",
            "Cabin"           => ["Klasse", "Boekingsklasse"],
            "duration"        => "Reistijd van",
            "segment"         => ["Heen", "Terug", "Departure", "Return"],
            "operated by"     => "(?:verzorgd door|uitgevoerd door)",
            "Seat:"           => "Stoel:",
            "TicketNumber"    => ["Ticketnummer"],
            "#reCurrency#"    => "#Alle prijzen worden vermeld in ([^\.]+)(?:\.|$)#",
            "Cost"            => "Vlucht",
        ],
        'fr' => [
            "Confirmation"    => "Confirmation",
            "Booking ID"      => "Référence de réservation",
            "Flight overview" => "Aperçu du vol",
            //			"emptyPNR" => "",
            "Itinerary #"     => "N° de voyage",
            "Passengers"      => ["Voyageur(s)"],
            "Total:"          => "Total du vol",
            "Taxes and Fees:" => ["Taxes et frais", "Frais de réservation Expedia", "Frais carte de paiement (appliqués par la compagnie)"],
            "Travel dates"    => "Dates de voyage",
            "Cabin"           => ["Classe", "Cabine"],
            "duration"        => ["Durée de", "Durée de"], // + nbsp
            "segment"         => ["Departure", "Vol aller", "Retour", "Départ", 'Vol retour'],
            "operated by"     => "(?:opéré par|exploité par)",
            "Seat:"           => "Siège :",
            "TicketNumber"    => ["Billet n°"],
            "#reCurrency#"    => "#Tous les prix sont(?: indiqués)? en ([^\.]+)(?:\.|$)#",
            "Cost"            => "Vol",
        ],
        'no' => [
            "Confirmation"    => "Bekreftelse",
            "Booking ID"      => "Bestillings-ID",
            "Flight overview" => "Flyoversikt",
            //			"emptyPNR" => "",
            "Itinerary #"     => "Reiserutenr.",
            "Passengers"      => ["Reisende"],
            "Total:"          => ["Totalpris for flyreise", "Totalt"],
            "Taxes and Fees:" => "Skatter og avgifter",
            "Travel dates"    => "Reisedatoer",
            "Cabin"           => "Klasse",
            "duration"        => "Varighet",
            "segment"         => ["Departure", "Return", "Avreise", "Retur"],
            "operated by"     => "drives av",
            "Seat:"           => "Sete:",
            "TicketNumber"    => ["Billettnr."],
            "#reCurrency#"    => "#Alle priser er oppgitt i ([^\.]+)(?:\.|$)#",
            "Cost"            => "Flyvning:",
        ],
        'de' => [
            "Confirmation"    => ["Bestätigung", "Bestätigungsnummer der Fluglinie"],
            "Booking ID"      => "Buchungsnummer",
            "Flight overview" => "Flug-Übersicht",
            //			"emptyPNR" => "",
            "Itinerary #"      => ["Reiseplannummer", "Reiseplannr."],
            "Passengers"       => ["Reisende(r)"],
            "Total:"           => ["Flug gesamt", "Gesamt", "Gesamtpreis"],
            "Taxes and Fees:"  => ["Steuern und Gebühren", "Expedia-Buchungspauschale", "Kartengebühr der Fluglinie"],
            "Travel dates"     => "Reisedaten",
            "Cabin"            => "Klasse",
            "duration"         => ["Flugdauer", "Dauer"],
            "segment"          => ["Rückflug", "Hinflug", "Departure", "Return", "Abflug", "Hinreise"],
            "operated by"      => "durchgeführt von",
            "Seat:"            => "Sitzplatz:",
            "TicketNumber"     => ["Ticketnummer"],
            "Flight Cancelled" => ['Ihre Fluggesellschaft hat Ihren Flug storniert'],
            "#reCurrency#"     => "#Alle Preise werden in (.+?) angegeben#",
            "Cost"             => "Flug:",
        ],
        'da' => [
            "Confirmation"    => "Bekræftelse",
            "Booking ID"      => "Reservations-ID",
            "Flight overview" => "Flyoversigt",
            //			"emptyPNR" => "",
            "Itinerary #"     => "Rejseplansnummer",
            "Passengers"      => ["Rejsende"],
            "Total:"          => ["Samlet pris for flyrejse:", "I alt"],
            "Taxes and Fees:" => "Skatter og gebyrer:",
            "Travel dates"    => "Rejsedatoer",
            "Cabin"           => ["Klasse", "Kabine"],
            "duration"        => "Varighed",
            "segment"         => ["Udrejse", "Hjemrejse", "Departure", "Return", 'Afrejse'],
            "operated by"     => "flyves af",
            "Seat:"           => "Sæde:",
            "TicketNumber"    => ["Billetnummer"],
            "#reCurrency#"    => "#Alle priser er angivet i ([^\.]+)(?:\.|$)#",
            "Cost"            => "Flyrejse:",
        ],
        'it' => [
            "Confirmation"    => "Conferma",
            "Booking ID"      => "ID prenotazione",
            "Flight overview" => "Riepilogo volo",
            //			"emptyPNR" => "",
            "Itinerary #"     => "N° di itinerario",
            "Passengers"      => ["Viaggiatori"],
            "Total:"          => ["Totale del volo:", "Totale"],
            "Taxes and Fees:" => "Tasse e oneri:",
            "Travel dates"    => "Date di viaggio",
            "Cabin"           => ["Classe", "Cabina"],
            "duration"        => "Durata",
            "segment"         => ["Departure", "Return", 'Partenza', 'Ritorno'],
            "operated by"     => "operato da",
            "Seat:"           => "Posto a sedere:",
            "TicketNumber"    => ["N° biglietto"],
            "#reCurrency#"    => "#Tutti i prezzi sono espressi in ([^\.]+)(?:\.|$)#",
            "Cost"            => "Volo:",
        ],
        'sv' => [
            "Confirmation"    => "Bekräftelse",
            "Booking ID"      => "Boknings-ID",
            "Flight overview" => "Flygöversikt",
            //			"emptyPNR" => "",
            "Itinerary #"     => "Resplansnummer",
            "Passengers"      => ["Resenär(er)"],
            "Total:"          => "Totalt",
            "Taxes and Fees:" => "Skatter och avgifter",
            "Travel dates"    => "Resedatum",
            "Cabin"           => "Kabin",
            "duration"        => "restid",
            "segment"         => ["Avresa", "Utresa", "Tillbakaresa"],
            "operated by"     => "trafikeras av",
            "Seat:"           => "Sittplats:",
            "TicketNumber"    => ["Biljettnr"],
            "#reCurrency#"    => "#Alla priser anges i ([^\.]+?)(?:\.|$)#",
            "Cost"            => "Flyg",
        ],
        'pt' => [
            "Confirmation"    => "Confirmação",
            "Booking ID"      => "NOTTRANSLATED",
            "Flight overview" => "Resumo do voo",
            "emptyPNR"        => "/Emissão\s+da\s+passagem\s+em\s+andamento/i",
            "Itinerary #"     => "Nº do itinerário",
            "Passengers"      => ["Viajante(s)"],
            "Total:"          => "Total",
            "Taxes and Fees:" => ["Taxa de reserva da Expedia", "Impostos e taxas"],
            "Travel dates"    => "Datas de viagem",
            "Cabin"           => "Cabine",
            "duration"        => "de duração",
            "segment"         => ["Partida", "Retorno"],
            "operated by"     => "operado pela",
            "Seat:"           => "Assento:",
            "TicketNumber"    => ["Nº do bilhete"],
            "#reCurrency#"    => "#Todos os preços foram cotados em ([^\.]+)(?:\.|$)#",
            "Cost"            => "Voo",
        ],
        'ja' => [
            "Confirmation"    => "NOTTRANSLATED",
            "Booking ID"      => "予約 ID",
            "Flight overview" => "航空券の概要",
            // "emptyPNR" => "/NOTTRANSLATED/i",
            "Itinerary #"     => "旅程番号",
            "Passengers"      => ["旅行者"],
            "Total:"          => "合計",
            "Taxes and Fees:" => ["税およびサービス料"],
            "Travel dates"    => "旅行日",
            "Cabin"           => ["キャビン", "クラス"],
            "duration"        => "所要時間",
            "segment"         => ["往路 (行き)", "復路 (帰り)", '行きの便', '帰りの便', "往路", "復路"],
            "operated by"     => "運航会社",
            "Seat:"           => "座席 :",
            "TicketNumber"    => ["航空券番号"],
            "#reCurrency#"    => "#料金の通貨単位はすべて(?:\s*となります)?\s*(.+?)\s*となります。#",
            "Cost"            => "フライト",
        ],
        'fi' => [
            "Confirmation"    => "Vahvistus",
            "Booking ID"      => ["Varaustunnus"],
            "Flight overview" => "Yhteenveto lennosta",
            //			"emptyPNR" => "//i",
            "Itinerary #" => "Matkasuunnitelman nro",
            "Passengers"  => ["Matkustaja(t)"],
            //			"Passengers2" => [], // for delay and cancell emails
            "Travel dates" => "Matkustuspäivät",
            "Cabin"        => ["Matkustamoluokka"],
            "duration"     => "Kesto",
            "segment"      => ["Lähtö", "Paluu"],
            "Seat:"        => "Istuinpaikka:",
            "operated by"  => "lennon liikennöi",
            "TicketNumber" => ["Lipun numero"],
            //			"Request Compensation" => "",
            //			"Flight Cancelled" => [""],
            "Total:"           => "Yhteensä",
            "Taxes and Fees:"  => ["Verot ja maksut"],
            "#reSpentAwards#"  => "#^(?<award>\d[\d,. ]*\s*pts)\s+and\s+(?<total>.+)$#i", // 65,000 pts and C$637.73
            "#reSpentAwards2#" => "#^(?<total>.+?)\s+and\s+(?<award>\d[\d,. ]*\s*pts)$#i", // $49.72 and 67,285 PTS
            "#reCurrency#"     => "#Kaikki hinnat on ilmoitettu valuutassa ([^\.]+)(?:\.|$)#",
            "Cost"             => "Lento",
        ],
        'en' => [
            "Confirmation" => ["Confirmation", "Confirmation ", "Airline confirmation"],
            "Booking ID"   => ["Booking ID", "Booking ID ", "Booking Reference ID"],
            "emptyPNR"     => "/(?:ticket\s+is\s+not\s+yet\s+confirmed|Change\s+or\s+cancel\s+this\s+reservation)/i",
            "Passengers"   => ["Traveler(s)", "Traveler(s) ", "Traveller(s)"],
            "Passengers2"  => ["Travelers", "Traveller", "Traveler", "Travellers"], // for delay and cancell emails
            "Cabin"        => ["Cabin", "Class"],
            "segment"      => ["Departure", "Departure  ", "Return", "Return  "],
            "Seat:"        => "Seat:",
            "TicketNumber" => ["Ticket #", "Ticket # "],
            //			"Request Compensation" => "",
            "Flight Cancelled" => ["Flight Cancelled", "Flight Canceled", 'airline has cancelled your flight', 'Your booking has been cancelled', 'You\'ve canceled your flight',
                'Your airline has canceled your flight', ],
            "Taxes and Fees:"  => ["Taxes and Fees:", "Taxes & Fees", "Expedia Booking Fee"],
            "#reSpentAwards#"  => "#^(?<award>\d[\d,. ]*\s*pts)\s+and\s+(?<total>.+)$#i", // 65,000 pts and C$637.73
            "#reSpentAwards2#" => "#^(?<total>.+?)\s+and\s+(?<award>\d[\d,. ]*\s*pts)$#i", // $49.72 and 67,285 PTS
            "#reSpentAwards3#" => "#^[A-Z]+\s*\S(?<total>[\d\,\.]+)$$#i", // CA $2,643.78
            "#reCurrency#"     => ["#All prices are quoted in ([^\.]+?)(?:\.|$)#"],
            "Cost"             => ["Flight:", "Flight"],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
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

        foreach (self::$headers as $code => $arr) {
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

            if ($byFrom || $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectLang($parser);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Example: it-6226789.eml
        $date = $this->http->FindSingleNode("(//text()[" . $this->contains('EMLDTL=DATE') . "])[last()]", null, true, "#EMLDTL=DATE(\d{8})-#");

        if (preg_match("#^\s*(\d{4})(\d{2})(\d{2})\s*$#", $date, $m)) {
            $this->date = strtotime($m[3] . '.' . $m[2] . '.' . $m[1]);
        }

        if (empty($this->date)) {
            $this->date = strtotime($parser->getDate());
        }

        if (stripos($parser->getHTMLBody(), '<META HTTP-EQUIV') !== false) {
            $this->http->SetEmailBody(preg_replace('/<META HTTP\-EQUIV.+?>/i', '', $parser->getHTMLBody()));
        }

        $this->detectLang($parser);
        $this->subject = $parser->getSubject();

        $totalText = implode(" ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Total:")) . " or contains(normalize-space(.),'Total:')][1]/ancestor::table[1]//text()[normalize-space(.)]"));

        if (empty($totalText)) {
            $totalText = implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Total:")) . " or normalize-space(.)='Total'][1]/ancestor::table[1]//text()[normalize-space(.)]"));
        }
        $reCurrency = (array) $this->t("#reCurrency#");

        foreach ($reCurrency as $re) {
            if (preg_match($re, $totalText, $m) && !empty($m[1])) {
                $this->emailCurrency = $this->currency($m[1]);

                break;
            }
        }

        $itineraries = [];
        $this->parseEmail($itineraries);

        $result = [
            'emailType'  => 'OverviewFlight' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        $totals = array_values(array_filter(array_merge(
            $this->http->FindNodes("//text()[{$this->contains($this->t("Total:"))} or contains(normalize-space(),'Total:')][1]", null, '/:\s*(.*\d.*)$/'),
            $this->http->FindNodes("//text()[{$this->eq($this->t("Total:"))} or {$this->eq(['Total'])}]/following::text()[normalize-space()][1]"),
            $this->http->FindNodes("//tr[{$this->starts($this->t("Total due today"))} and not(.//tr)]", null, "/{$this->opt($this->t("Total due today"))}[:\s]*(.*\d.*)$/")
        )));

        if (!empty($totals[0]) & $this->countAirs > 1) {
            $amount = 0.0;

            foreach ($totals as $total) {
                if (preg_match($this->t('#reSpentAwards#'), $total, $m)
                    || preg_match($this->t('#reSpentAwards2#'), $total, $m)
                    || preg_match($this->t('#reSpentAwards3#'), $total, $m)
                ) {
                    if (isset($m['award'])) {
                        $SpentAwards = (isset($SpentAwards)) ? $SpentAwards . ', ' . trim($m['award']) : trim($m['award']);
                    }
                    $total = trim($m['total']);
                }
                $amount += $this->amount($total);

                if (empty($currency)) {
                    $currency = (!empty($this->emailCurrency)) ? $this->emailCurrency : $this->currency($total);
                }
            }

            if ($amount >= 0) {
                $result['parsedData']['TotalCharge']['Amount'] = $amount;
            }

            if (isset($currency)) {
                $result['parsedData']['TotalCharge']['Currency'] = (!empty($this->emailCurrency)) ? $this->emailCurrency : $currency;
            }

            if (isset($SpentAwards)) {
                $result['parsedData']['TotalCharge']['SpentAwards'] = $SpentAwards;
            }

            // BaseFare
            $baseFare = 0;
            $bases = array_values(array_filter(array_merge(
                $this->http->FindNodes("//text()[{$this->eq($this->t('Cost'))}]/following::text()[normalize-space()][1]", null, '/([\d\.?\,?]+)/u')
            )));

            if (empty($bases)) {
                $bases = array_values(array_filter(array_merge(
                    $this->http->FindNodes("//text()[{$this->starts($this->t('Cost'))}][contains(normalize-space(), '.') or contains(normalize-space(), ',')]", null, "/{$this->opt($this->t('Cost'))}\:?\s+(?:\D?|\D+?)([\d\.?\,?\s?]+)/u")
                )));
            }

            foreach ($bases as $base) {
                $baseFare += $this->amount($base);
            }

            if (!empty($baseFare)) {
                $result['parsedData']['TotalCharge']['BaseFare'] = $baseFare;
            }

            // Tax
            $taxTotal = 0;
            $taxes = array_values(array_filter(array_merge(
                $this->http->FindNodes("//text()[" . $this->contains($this->t("Taxes and Fees:")) . "]", null, "#:\s*(.+)#")
            )));

            if (count($taxes) === 0) {
                $taxes = array_values(array_filter(array_merge(
                    $this->http->FindNodes("//text()[" . $this->starts($this->t("Taxes and Fees:")) . "]/following::text()[normalize-space()][1]")
                )));
            }

            foreach ($taxes as $tax) {
                $tax = preg_replace("/^[A-Z]{2}\s+[$]/u", "", $tax);
                $taxTotal += $this->amount($tax);
            }

            if (!empty($taxTotal)) {
                $result['parsedData']['TotalCharge']['Tax'] = $taxTotal;
            }
        }

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

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

    private function detectLang(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) === false) {
                $first = true;
            }
        }

        if (empty($first)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $lines) {
            foreach ($lines as $line) {
                if (stripos($body, $line) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if ($this->code === 'expedia') {
            return null;
        }

        if (!empty($this->code)) {
            return $this->code;
        }

        foreach ($this->bodies as $code => $criteria) {
            foreach ($criteria as $search) {
                if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                ) {
                    continue 2;
                }
            }

            return $code;
        }

        return null;
    }

    private function parseEmail(&$itineraries): void
    {
        if (!is_array($this->t("segment"))) {
            return;
        }

        $terminalTitle = ["Terminal:", "ターミナル :", "Terminaali:", "Aérogare :", "Terminal :", "航廈：", "航站楼："];

        $airs = [];
        $rails = [];

        $ruleDep = $this->eq($this->t("segment"));
        $xpath = "//*[{$ruleDep}]/ancestor::tr[1]/following-sibling::tr[(.//img or .//*[contains(text(),'image')]) and (contains(translate(.,'0123456789','dddddddddd'),'d:d') or contains(., 'uur') or contains(., ' h ') or contains(translate(.,'0123456789','dddddddddd'),'dhd') or contains(., 'kl.') or contains(translate(.,'0123456789','dddddddddd'),'dd.dd'))]//*[self::img or self::*[contains(text(),'image')]]/ancestor::tr[1]";

        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[{$ruleDep}]/ancestor::tr[2]/following-sibling::tr[(.//img[contains(@src,'arrow')]  or .//*[contains(text(),'image')]) and (contains(.,':') or contains(.,'uur') or contains(.,' h ') or contains(translate(.,'0123456789','dddddddddd'),'dhd') or contains(.,'kl.'))]//*[self::img[contains(@src,'arrow')] or self::*[contains(text(),'image')]]/ancestor::tr[1]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            // it-5470370.eml
            $xpath = "//text()[{$ruleDep}]/ancestor::table[.//*[contains(.,':') or contains(.,'uur') or contains(.,' h ') or contains(translate(.,'0123456789','dddddddddd'),'dhd') or contains(.,'kl.')]][1]/descendant::tr[contains(.,':') or contains(.,'uur') or contains(.,' h ') or contains(translate(.,'0123456789','dddddddddd'),'dhd') or contains(.,'kl.')][1]/td[1]";
            $segments = $this->http->XPath->query($xpath);
        }

        if (0 === $segments->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }
        //		$this->logger->alert("XPATH: {$xpath}");

        foreach ($segments as $root) {
            $rl = '';

            $airline = $this->http->FindSingleNode('./preceding::tr[normalize-space(.)][1]', $root, true, '/^(.*?)\s+\d+(?:\s+' . $this->t("operated by") . '\s*.+|$)/u');

            if ($airline) {
                $rl = $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Confirmation")) . '][1]/following::text()[contains(.,"(") and contains(.,")")][contains(normalize-space(.),"' . $airline . '")][1]', $root, true, '/^([A-Z\d]{5,})/');

                if (empty($rl)) {
                    $rl = $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Confirmation")) . '][1]/following::text()[contains(.,"(") and contains(.,")")][normalize-space(.) = "(' . $airline . ')"][1]/preceding::text()[normalize-space()][1]', $root, true, '/^\s*([A-Z\d]{5,})\s*$/');
                }

                if (empty($rl)) {
                    //in case:  Confirmation K4S3CR (Air Canada), Flight Web Fare Air Canada 8949
                    $rls = array_filter($this->http->FindNodes('./preceding::text()[' . $this->eq($this->t("Confirmation")) . '][1]/following::text()[normalize-space()][position()<10][contains(.,"(") and contains(.,")")]', $root, '/^\s*[A-Z\d]{5,}\s*\(.+\)/'));

                    foreach ($rls as $key => $value) {
                        if (preg_match("#^\s*([A-Z\d]{5,7})\s*\((.+)\)\s*$#", $value, $m) && strlen($m[2]) > 5 && strpos($airline, $m[2])) {
                            $rl = $m[1];
                        }
                    }
                }
            }

            if (empty($rl)) {
                $rl = $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Booking ID")) . '][1]/following::text()[normalize-space(.)][1]', $root, true, '/^([A-Z\d]{5,})$/');
            }

            if (empty($rl) && $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Booking ID")) . '][1]/following::text()[normalize-space(.)][1]') == '') {
                $rl = $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Booking ID")) . '][1]/following::text()[normalize-space(.)][2]', $root, true, '/^([A-Z\d]{5,})$/');
            }

            if (empty($rl) && !empty($this->t("emptyPNR")) && $this->t("emptyPNR") !== 'emptyPNR'
                && $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Flight overview")) . '][1]/ancestor::table[1]', $root, null, $this->t("emptyPNR"))) {
                $rl = CONFNO_UNKNOWN;
            }

            if (empty($rl) && empty($this->http->FindSingleNode('./preceding::text()[' . $this->contains($this->t("Confirmation")) . ' or ' . $this->contains($this->t("Booking ID")) . '][1]', $root))) {
                $rl = CONFNO_UNKNOWN;
            }

            if (empty($rl) && empty($this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Confirmation")) . ']/ancestor::table[1][contains(normalize-space(.),"' . $airline . '")]', $root))
                   && empty($this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Booking ID")) . '][1]', $root))
            ) {
                $rl = CONFNO_UNKNOWN;
            }

            $operator = $this->http->FindSingleNode('./preceding::tr[normalize-space(.)][1]', $root, true,
                '/' . $this->t("operated by") . '\s*(.+)$/u');

            if (stripos($operator, 'RAILWAY') !== false) {
                $airline = $operator;
                $rails[$rl][] = $root;
//                $airs[$rl][] = $root;
            } else {
                $airs[$rl][] = $root;
            }
        }

        if (isset($airs)) {
            $this->countAirs = count($airs);
        }

        foreach ($airs as $rl => $roots) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $tripNumber = $this->http->FindSingleNode('./preceding::td[not(.//td) and ' . $this->starts($this->t("Itinerary #")) . '][1]', $roots[0], true, '/(\d{5,})$/');

            if ($tripNumber) {
                $it['TripNumber'] = $tripNumber;
            }

            // TicketNumbers
            $rule = $this->eq($this->t("TicketNumber"));
            $ticketNumberTexts = $this->http->FindNodes('./preceding::td[ not(.//td) and ./descendant::text()[' . $rule . '] ][1]/descendant::text()[normalize-space(.)]',
                $roots[0], '/^([-\d\s]{5,}?)(?:\s*\(|$)/');
            $ticketNumberValues = array_values(array_unique(array_filter($ticketNumberTexts)));

            if (empty($ticketNumberValues)) {
                $ticketNumberTexts = $this->http->FindNodes('//text()[' . $this->starts($this->t("Ticket #")) . ']',
                    null, '/^' . $this->opt($this->t("Ticket #")) . '\s*(\d{13})\s*$/');
                $ticketNumberValues = array_values(array_unique(array_filter($ticketNumberTexts)));
            }

            if (!empty($ticketNumberValues[0])) {
                $it['TicketNumbers'] = $ticketNumberValues;
            }

            // Cancelled
            if (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('Flight Cancelled'))}][1]"))) {
                $it["Status"] = 'cancelled';
                $it["Cancelled"] = true;
            }

            // Passengers
            $rulePassengers = $this->eq($this->t("Passengers"));

            if (isset($it['Cancelled']) && $it['Cancelled'] == true) {
                $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$rulePassengers}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)!=''][1]"))));
            } else {
                $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$rulePassengers}]/ancestor::tr[1]/following-sibling::tr[position()<last()]/descendant::text()[normalize-space(.)!=''][1]"))));
            }

            if ($this->http->FindNodes("//text()[contains(normalize-space(.),'Request Compensation')]")) {
                $rule = $this->eq($this->t("Passengers2"));
                $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$rule}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)!=''][1]"))));
            }

            // AccountNumbers
            // examples: it-6692057.eml
            // KLMFlyingBlue HK 2082666396    |    American AAdvantage 84f8m22
            $accountNumbers = $this->http->FindNodes("//text()[{$rulePassengers}]/ancestor::tr[1]/following-sibling::tr[position()<last()]/td/div[normalize-space(.)][2]", null, '/(?:FlyingBlue|AAdvantage|JetBlue TrueBlue).*\D(\d[\d\s]{4,}|[A-z\d]{7})$/i');
            $accountNumberValues = array_values(array_unique(array_filter($accountNumbers)));

            if (!empty($accountNumberValues[0])) {
                $it['AccountNumbers'] = $accountNumberValues;
            }

            if (count($airs) === 1) {
                // BaseFare
                // TotalCharge
                // Currency
                // SpentAwards
                $totals = array_filter(array_merge(
                    $this->http->FindNodes("//text()[{$this->contains($this->t("Total:"))} or contains(normalize-space(),'Total:')][1]", null, '/:\s*(.*\d.*)$/'),
                    $this->http->FindNodes("//text()[" . $this->eq($this->t("Total:")) . " or normalize-space(.)='Total']/following::text()[normalize-space(.) and normalize-space(.)!=' '][1]"),
                    $this->http->FindNodes("//tr[{$this->starts($this->t("Total due today"))} and not(.//tr)]", null, "/{$this->opt($this->t("Total due today"))}[:\s]*(.*\d.*)$/")
                ));

                if (!empty($totals)) {
                    $it['TotalCharge'] = 0.0;

                    foreach ($totals as $total) {
                        if (preg_match($this->t('#reSpentAwards#'), $total, $m)
                            || preg_match($this->t('#reSpentAwards2#'), $total, $m)
                        ) {
                            $it['SpentAwards'] = (isset($it['SpentAwards'])) ? $it['SpentAwards'] . ', ' . trim($m['award']) : trim($m['award']);
                            $total = trim($m['total']);
                        }
                        $it['TotalCharge'] += $this->amount($total);

                        if (empty($it['Currency'])) {
                            $it['Currency'] = (!empty($this->emailCurrency)) ? $this->emailCurrency : $this->currency($total);

                            if ($cur = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'All prices are quoted in')]/following-sibling::node()[normalize-space(.)][1]", null, true, '/([A-Z]{3})/')) {
                                $it['Currency'] = $cur;
                            }
                        }
                    }
                }
                // EarnedAwards
                $earned = $this->http->FindSingleNode("//img[contains(@src,'/rewards/')]/following::text()[normalize-space()][1][contains(.,'points') or contains(.,'puntos') or contains(.,'Punkte') or contains(.,'poeng')]");

                if (!empty($earned)) {
                    $it['EarnedAwards'] = $earned;
                }
                // Tax
                $taxTotal = 0;
                $taxes = array_values(array_filter(array_merge(
                    $this->http->FindNodes("//text()[" . $this->contains($this->t("Taxes and Fees:")) . "]", null, "#:\s*(.+)#")
                )));

                if (count($taxes) === 0) {
                    $taxes = array_values(array_filter(array_merge(
                        $this->http->FindNodes("//text()[" . $this->starts($this->t("Taxes and Fees:")) . "]/following::text()[normalize-space()][1]")
                    )));
                }

                foreach ($taxes as $tax) {
                    $taxTotal += $this->amount($tax);
                }

                if (!empty($taxTotal)) {
                    $it['Tax'] = $taxTotal;
                }
                // BaseFare
                $baseFare = 0;
                $bases = array_values(array_filter(array_merge(
                    $this->http->FindNodes("//text()[{$this->eq($this->t('Cost'))}]/following::text()[normalize-space()][1]")
                )));

                if (empty($bases)) {
                    $bases = array_values(array_filter(array_merge(
                        $this->http->FindNodes("//text()[{$this->starts($this->t('Cost'))}][contains(normalize-space(), '.') or contains(normalize-space(), ',')]", null, "/{$this->opt($this->t('Cost'))}\:?\s+(?:\D?|\D+?)([\d\.?\,?\s?]+)/u")
                )));
                }

                foreach ($bases as $base) {
                    $baseFare += $this->amount($base);
                }

                $searchPoint = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'pts')]", null, true, "/^([\d\.\,\']+)\s*pts/");

                if (!empty($searchPoint)) {
                    $searchPoint = $this->amount($searchPoint);
                }

                if (!empty($baseFare)) {
                    if ($searchPoint !== $baseFare) {
                        $it['BaseFare'] = $baseFare;
                    }
                }
            }

            $patterns['date'] = '/^('
                . '\b\d{1,2}-\d{2}-\d{4}\b' // 5-03-2020
                . '|\b\d{4}-\d{2}-\d{1,2}\b' // 2020-03-5
                . '|[^-]{6,}?' // other
                . ')(?:\s*-|$)/';

            $dateRelativeStr = $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Travel dates")) . '][1]/following::text()[string-length(normalize-space(.))>2][1]', $roots[0], true, $patterns['date']);

            if (!$dateRelativeStr) {
                $dateRelativeStr = $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Travel dates")) . '][1]/following::text()[string-length(normalize-space(.))>2][1]/ancestor::*[self::p or self::div or self::td][1]', $roots[0], true, $patterns['date']);
            }

            if ($dateRelativeStr && strpos($dateRelativeStr, '/') !== false) { // 3/21/2020
                //Do not use a slash date because of the uncertainty of the month|day definition
                if (preg_match("/-\s*([[:alpha:]]+[. ]+\d{1,2}[ ]*,[ ]*\d{2,4})\s*-/u", $this->subject, $matches)) {
                    // Travel Confirmation - Mar 21, 2020 - Itinerary # 7514256451360
                    $dateRelativeStr = $matches[1];
                } elseif (preg_match("/-\s*(\d{1,2}[. ]+[[:alpha:]]+[.]*|[[:alpha:]]+[ .]+\d{1,2}[.]*)\s*-/u", $this->subject, $ms)
                    && preg_match("/\/\s*(\d{4})/", $dateRelativeStr, $m)
                ) {
                    // ?
                    $dateRelativeStr = $ms[1] . ' ' . $m[1];
                } else {
                    $dateRelativeStr = $this->http->FindSingleNode("preceding::text()[$ruleDep][1]/following::text()[normalize-space()][1]", $roots[0]);
                }
            }

            if (!$dateRelativeStr) {
                $dateRelativeStr = $this->http->FindSingleNode('./preceding::text()[' . $this->starts($this->t("Itinerary #")) . '][1]/following::text()[normalize-space(.)][1]', $roots[0], true, $patterns['date']);
                $dateRelativeStr = $this->re("#(.*\b(?:\d{2}|\d{4})\b.*)#", $dateRelativeStr);
            }

            if (empty($dateRelativeStr)) {
                $emldt = $this->http->FindSingleNode("(//text()[" . $this->contains('EMLDTL=DATE') . "])[last()]", null, true, "#EMLDTL=DATE(\d{8})-#");

                if (preg_match("#^\s*(\d{4})(\d{2})(\d{2})\s*$#", $emldt, $m)) {
                    $dateRelativeStr = $m[3] . '.' . $m[2] . '.' . $m[1];
                }
            }

            $dateRelative = $this->normalizeDate($dateRelativeStr);

            if ($dateRelative) {
                if (!preg_match('/\d{4}$/', $dateRelativeStr)) { // examples: it-6125799.eml, it-6180435.eml
                    $dateRelativeParts = preg_split('/\s+/', $dateRelativeStr);

                    if (count($dateRelativeParts) === 3) {
                        $dateRelativeVriants = [$dateRelativeStr, $dateRelativeParts[2] . ' ' . $dateRelativeParts[1], $dateRelativeParts[1] . ' ' . $dateRelativeParts[2]];
                        $dateRelative .= ' ' . $this->http->FindSingleNode('/descendant::tr[' . $this->contains($dateRelativeVriants) . '][1]', null, true, '/(?:' . implode('|', $dateRelativeVriants) . ')\s*,?\s*(\d{4})/');
                    }
                }

                if (false === strtotime($dateRelative)) {
                    $dateRelative = $this->http->FindSingleNode("//a[contains(normalize-space(.), 'See your itinerary')]/ancestor::tr[3]/preceding-sibling::tr[3]");
                } else {
                    $dateRelative = strtotime('1 days ago', strtotime($dateRelative));
                }
            } else {
                $this->logger->debug('error with relative date!');

                return;
            }

            foreach ($roots as $i => $root) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[count(.//text()[normalize-space()]) = 2 and .//img][1][descendant::text()[normalize-space()][1][normalize-space(.) = 'Departure' or normalize-space(.) = 'Return' or {$this->eq($this->t('segment'))}]]/descendant::text()[normalize-space(.)][2]", $root));

                if (empty($date)) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/../tr[normalize-space(.)][contains(normalize-space(.), 'Departure') or contains(normalize-space(.), 'Return') or {$this->contains($this->t('segment'))}][1]/descendant::text()[{$ruleDep}]/following::text()[string-length(normalize-space(.))>2][1]",
                        $root));
                }

                if (empty($date)) {
                    // it-5470370.eml
                    $date = $this->normalizeDate($this->http->FindSingleNode("./../tr[normalize-space(.)][contains(normalize-space(.), 'Departure') or contains(normalize-space(.), 'Return') or {$this->contains($this->t('segment'))}][1]/descendant::text()[{$ruleDep}]/following::text()[string-length(normalize-space(.))>2][1]", $root));
                }

                if (empty($date)) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[2]/../tr[normalize-space(.)][1]/descendant::text()[{$ruleDep}]/following::text()[string-length(normalize-space(.))>2][1]", $root));
                }

                if ($date) {
                    $dateDep = EmailDateHelper::parseDateRelative($date, $dateRelative);
                    $date = new \DateTime(date('d M Y', $dateDep));
                } else {
                    $this->logger->debug("error with segment-{$i} date!");

                    return;
                }

                $itsegment = [];

                // FlightNumber
                // AirlineName
                // Operator
                $flight = $this->http->FindSingleNode('./ancestor::tr[1]/preceding-sibling::tr[1]', $root);
                // Air France 6792 operated by JET AIRWAYS
                if (preg_match('/^(.*?)\s+(\d+)(?:[\s,]+\(?' . $this->t("operated by") . '\s*(.*?)\)?$|$)/u', $flight, $matches)) {
                    $itsegment['AirlineName'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];

                    if (!empty($matches[3]) && $matches[3] !== 'UNDEFINED') {
                        if (stripos($matches[3], $this->t('FOR')) !== false) {
                            $itsegment['Operator'] = $this->re("/{$this->opt($this->t('OPERATED BY'))}\s*(.+)\s*{$this->opt($this->t('FOR'))}/", $matches[3]);
                        } else {
                            $itsegment['Operator'] = $matches[3];
                        }
                    }
                }

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)#");

                if (empty($itsegment['DepCode'])) {
                    // it-5470370.eml
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./descendant::td[1]", $root, true, "#\(([A-Z]{3})\)#");
                }
                // DepName
                // DepartureTerminal
                $terminalDep = $this->http->FindSingleNode("./td[1]//text()[" . $this->eq($terminalTitle) . "]/following::text()[normalize-space(.)][1]", $root);

                if (empty($terminalDep)) {
                    //if use <span />
                    $terminalDep = $this->http->FindSingleNode("./td[1]//text()[" . $this->eq($terminalTitle) . "]/ancestor::td[1]", $root, true, "#" . $this->opt($terminalTitle) . "\s*(.+)#");
                }

                if (empty($terminalDep)) {
                    $terminalDep = $this->http->FindSingleNode("./td[1]//text()[" . $this->starts(str_replace([':', ' '], '', $terminalTitle)) . "]/ancestor::td[1]", $root, true, "#:\s*(.+)#");
                }

                if (empty($terminalDep)) {
                    // it-5470370.eml
                    $terminalDep = $this->http->FindSingleNode("./descendant::td[1]//text()[" . $this->starts(str_replace([':', ' '], '', $terminalTitle)) . "]/ancestor::td[1]", $root, true, "#:\s*(.+)#");
                }

                if ($terminalDep) {
                    $itsegment['DepartureTerminal'] = $terminalDep;
                }

                // DepDate
                // ArrDate
                $depTime = $this->http->FindSingleNode("./td[1]", $root);

                if (empty($depTime)) {
                    // it-5470370.eml
                    $depTime = $this->http->FindSingleNode("./descendant::td[1]", $root);
                }
                $arrTime = $this->http->FindSingleNode("./td[3]", $root);

                if (empty($arrTime)) {
                    // it-5470370.eml
                    $arrTime = $this->http->FindSingleNode("(.//td[3])[last()]", $root);
                }

                if (empty($arrTime)) {
                    // it-66442820.eml
                    $arrTime = $this->http->FindSingleNode(".//td[normalize-space()][2]", $root);
                }

                $times = [
                    'Dep' => $depTime,
                    'Arr' => $arrTime,
                ];
                $re = '/[\D]*(\d{1,2}\s*[:\.h]*\s*\d{2}\s*(?:PM|AM|a|p)?)(?:Uhr|U:r|uur)?\s*(?:\+(\d{1,2}))?/i'; //after below replace Uhr->U:r
                $res = '';

                foreach ($times as $key => $time) {
                    $time = str_replace(['.', ' h ', 'h'], [':', ':', ':'], $time);
                    $time = preg_replace('/(\d+\s*)([ap])\b/i', '$1$2m', $time);

                    if (preg_match($re, $time, $m) && is_object($date)) {
                        $m[1] = str_replace(' ', '', $m[1]);
                        $date->modify($m[1]);

                        if (!empty($m[2])) {
                            $date->modify("+$m[2] days");
                        }
                        $itsegment[$key . 'Date'] = $date->getTimestamp();
                    }
                }

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

                if (empty($itsegment['ArrCode'])) {
                    // it-5470370.eml
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("(.//td[3])[last()]", $root, true, "#\(([A-Z]{3})\)#");
                }

                if (empty($itsegment['ArrCode'])) {
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[normalize-space()][2]", $root, true, "#\(([A-Z]{3})\)#");
                }

                if (empty($itsegment['ArrCode']) && !empty($itsegment['ArrDate'])) {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                if (empty($itsegment['DepCode']) && !empty($itsegment['DepDate'])) {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                // ArrName
                $terminalArr = $this->http->FindSingleNode("./td[3]//text()[" . $this->eq($terminalTitle) . "]/following::text()[normalize-space(.)][1]", $root);

                if (empty($terminalArr)) {
                    //if use <span />
                    $terminalArr = $this->http->FindSingleNode("./td[3]//text()[" . $this->eq($terminalTitle) . "]/ancestor::td[1]", $root, true, "#" . $this->opt($terminalTitle) . "\s*(.+)#");
                }

                if (empty($terminalArr)) {
                    $terminalArr = $this->http->FindSingleNode("./td[3]//text()[" . $this->starts(str_replace([':', ' '], '', $terminalTitle)) . "]/ancestor::td[1]", $root, true, "#:\s*(.+)#");
                }

                if (empty($terminalArr)) {
                    // it-5470370.eml
                    $terminalArr = $this->http->FindSingleNode("(.//td[3])[last()]//text()[" . $this->starts(str_replace([':', ' '], '', $terminalTitle)) . "]/ancestor::td[1]", $root, true, "#:\s*(.+)#");
                }

                if (empty($terminalArr)) {
                    // it-66442820.eml
                    $terminalArr = $this->http->FindSingleNode("./td[normalize-space()][2]", $root, true, "/Terminal\:\s*([A-Z\d]+)$/");
                }

                if ($terminalArr) {
                    $itsegment['ArrivalTerminal'] = $terminalArr;
                }

                // Cabin
                // BookingClass
                $cabin = $this->http->FindSingleNode('./ancestor::tr[1]/following-sibling::tr[position()<3][' . $this->contains($this->t("Cabin")) . ']', $root);
                // Economy / Coach (H)
                if (preg_match('/' . $this->opt($this->t("Cabin")) . '\s*:?\s*([^)(]+)(?:\s+\(([A-Z]{1,2})\)|$)/u', $cabin, $matches)) {
                    $itsegment['Cabin'] = trim($matches[1]);

                    if (!empty($matches[2])) {
                        $itsegment['BookingClass'] = $matches[2];
                    }
                }

                // Seats
                $seatsText = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[position()<6]//text()[normalize-space(.)='" . $this->t("Seat:") . "']/following::text()[normalize-space(.)][1]", $root, true, '/([,A-Z\s\d]+)\s+\|/');

                if ($seatsText) {
                    $seats = array_filter(preg_split('/\s*,\s*/', $seatsText), function ($item) {
                        return preg_match('/^\d{1,3}[A-z]$/', $item) > 0;
                    });

                    if (!empty($seats)) {
                        $itsegment['Seats'] = $seats;
                    }
                }

                // Duration
                $duration = $this->http->FindSingleNode('./ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<5][' . $this->contains($this->t("duration")) . '][1]', $root, true, '/(\d[\d\shumin.小時分鐘]+)/u');

                if ($duration) {
                    $itsegment['Duration'] = trim($duration);
                }

                if (isset($it['Cancelled']) && $it['Cancelled'] == true && isset($it['TripSegments'])) {
                    foreach ($it['TripSegments'] as $seg) {
                        // delete dublicate segments
                        if ((isset($seg['DepDate']) && isset($itsegment['DepDate']) && abs($seg['DepDate'] - $itsegment['DepDate']) < 60 * 60 * 24)
                            && ((isset($seg['DepCode']) && isset($itsegment['DepCode']) && $seg['DepCode'] == $itsegment['DepCode'])
                                 || (isset($seg['ArrCode']) && isset($itsegment['ArrCode']) && $seg['ArrCode'] == $itsegment['ArrCode']))) {
                            $it['TripSegments'] = [];

                            break 2;
                        }
                    }
                }
                $it['TripSegments'][] = $itsegment;
            }

            unset($dateRelative);

            $itineraries[] = $it;
        }

        foreach ($rails as $rl => $roots) {
            $it = [];
            $it['Kind'] = 'T';
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $tripNumber = $this->http->FindSingleNode('./preceding::td[not(.//td) and ' . $this->starts($this->t("Itinerary #")) . '][1]', $roots[0], true, '/(\d{5,})$/');

            if ($tripNumber) {
                $it['TripNumber'] = $tripNumber;
            }

            if (empty($it['RecordLocator']) && !empty($it['TripNumber'])) {
                $it['RecordLocator'] = $it['TripNumber'];
                unset($it['TripNumber']);
            }

            // TicketNumbers
            $rule = $this->eq($this->t("TicketNumber"));
            $ticketNumberTexts = $this->http->FindNodes('./preceding::td[ not(.//td) and ./descendant::text()[' . $rule . '] ][1]/descendant::text()[normalize-space(.)]',
                $roots[0], '/^([-\d\s]{5,}?)(?:\s*\(|$)/');
            $ticketNumberValues = array_values(array_unique(array_filter($ticketNumberTexts)));

            if (!empty($ticketNumberValues[0])) {
                $it['TicketNumbers'] = $ticketNumberValues;
            }

            // Passengers
            $rulePassengers = $this->eq($this->t("Passengers"));
            $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$rulePassengers}]/ancestor::tr[1]/following-sibling::tr[position()<last()]/descendant::text()[normalize-space(.)!=''][1]"))));

            if ($this->http->FindNodes("//text()[contains(normalize-space(.),'Request Compensation')]")) {
                $rule = $this->eq($this->t("Passengers2"));
                $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$rule}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)!=''][1]"))));
            }

            // AccountNumbers
            // examples: it-6692057.eml
            $accountNumbers = $this->http->FindNodes("//text()[{$rulePassengers}]/ancestor::tr[1]/following-sibling::tr[position()<last()]/td/div[normalize-space(.)][2]", null, '/[\d\s]{5,}/');
            $accountNumberValues = array_values(array_unique(array_filter($accountNumbers)));

            if (!empty($accountNumberValues[0])) {
                $it['AccountNumbers'] = $accountNumberValues;
            }

            // Cancelled
            if (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('Flight Cancelled'))}][1]"))) {
                $it["Status"] = 'cancelled';
                $it["Cancelled"] = true;
            }

            if (count($airs) === 1) {
                // BaseFare
                // TotalCharge
                // Currency
                // SpentAwards

                $totals = array_filter(array_merge(
                    $this->http->FindNodes("//text()[{$this->contains($this->t("Total:"))} or contains(normalize-space(),'Total:')][1]", null, '/:\s*(.*\d.*)$/'),
                    $this->http->FindNodes("//text()[" . $this->eq($this->t("Total:")) . " or normalize-space(.)='Total']/following::text()[normalize-space(.) and normalize-space(.)!=' '][1]")
                ));

                if (!empty($totals)) {
                    $it['TotalCharge'] = 0.0;

                    foreach ($totals as $total) {
                        if (preg_match($this->t('#reSpentAwards#'), $total, $m)) {
                            $it['SpentAwards'] = (isset($it['SpentAwards'])) ? $it['SpentAwards'] . ', ' . trim($m['award']) : trim($m['award']);
                            $total = trim($m['total']);
                        }
                        $it['TotalCharge'] += $this->amount($total);

                        if (empty($it['Currency'])) {
                            $it['Currency'] = (!empty($this->emailCurrency)) ? $this->emailCurrency : $this->currency($total);

                            if ($cur = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'All prices are quoted in')]/following-sibling::node()[normalize-space(.)][1]", null, true, '/([A-Z]{3})/')) {
                                $it['Currency'] = $cur;
                            }
                        }
                    }
                }

                // Tax
                $taxTotal = 0;
                $taxes = array_values(array_filter(array_merge(
                    $this->http->FindNodes("//text()[" . $this->contains($this->t("Taxes and Fees:")) . "]", null, "#:\s*(.+)#"),
                    $this->http->FindNodes("//text()[" . $this->contains($this->t("Taxes and Fees:")) . "]/following::text()[normalize-space(.) and normalize-space(.)!=' '][1]")
                )));

                foreach ($taxes as $tax) {
                    $taxTotal += $this->amount($tax);
                }

                if (!empty($taxTotal)) {
                    $it['Tax'] = $taxTotal;
                }
            }

            $patterns['date'] = '/^('
                . '\b\d{1,2}-\d{2}-\d{4}\b' // 5-03-2020
                . '|\b\d{4}-\d{2}-\d{1,2}\b' // 2020-03-5
                . '|[^-]{6,}?' // other
                . ')(?:\s*-|$)/';

            $dateRelativeStr = $this->http->FindSingleNode('./preceding::text()[' . $this->eq($this->t("Travel dates")) . '][1]/following::text()[normalize-space(.)][1]', $roots[0], true, $patterns['date']);

            if ($dateRelativeStr && strpos($dateRelativeStr, '/') !== false) {
                //Do not use a slash date because of the uncertainty of the month|day definition
                if (!empty($this->subject) && preg_match("#-\s*(\d{1,2}[. ]+[^\d\s\.]+[.]?|[^\d\s\.]+[ .]+\d{1,2}[.]?)\s*-#", $this->subject, $ms)
                        && preg_match("#/(\d{4})#", $dateRelativeStr, $m)) {
                    $dateRelativeStr = $ms[1] . ' ' . $m[1];
                } else {
                    $dateRelativeStr = $this->http->FindSingleNode("./preceding::text()[$ruleDep][1]/following::text()[normalize-space(.)!=''][1]", $roots[0]);
                }
            }

            if (!$dateRelativeStr) {
                $dateRelativeStr = $this->http->FindSingleNode('./preceding::text()[' . $this->starts($this->t("Itinerary #")) . '][1]/following::text()[normalize-space(.)][1]', $roots[0], true, $patterns['date']);
            }
            $dateRelative = $this->normalizeDate($dateRelativeStr);

            if ($dateRelative) {
                if (!preg_match('/\d{4}$/', $dateRelativeStr)) { // examples: it-6125799.eml, it-6180435.eml
                    $dateRelativeParts = preg_split('/\s+/', $dateRelativeStr);

                    if (count($dateRelativeParts) === 3) {
                        $dateRelativeVriants = [$dateRelativeStr, $dateRelativeParts[2] . ' ' . $dateRelativeParts[1], $dateRelativeParts[1] . ' ' . $dateRelativeParts[2]];
                        $dateRelative .= ' ' . $this->http->FindSingleNode('/descendant::tr[' . $this->contains($dateRelativeVriants) . '][1]', null, true, '/(?:' . implode('|', $dateRelativeVriants) . ')\s*,?\s*(\d{4})/');
                    }
                }

                $dateRelative = strtotime('1 days ago', strtotime($dateRelative));
            } else {
                $this->logger->debug('error with relative date!');

                return;
            }

            foreach ($roots as $i => $root) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/../tr[normalize-space(.)][1]/descendant::text()[{$ruleDep}]/following::text()[string-length(normalize-space(.))>2][1]", $root));

                if (empty($date)) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[2]/../tr[normalize-space(.)][1]/descendant::text()[{$ruleDep}]/following::text()[string-length(normalize-space(.))>2][1]", $root));
                }

                if ($date) {
                    $dateDep = EmailDateHelper::parseDateRelative($date, $dateRelative);
                    $date = new \DateTime(date('d M Y', $dateDep));
                } else {
                    $this->logger->debug("error with segment-{$i} date!");

                    return;
                }

                $itsegment = [];

                // FlightNumber
                // AirlineName
                // Operator
                $flight = $this->http->FindSingleNode('./ancestor::tr[1]/preceding-sibling::tr[1]', $root);
                // Air France 6792 operated by JET AIRWAYS
                if (preg_match('/^(.*?)\s+(\d+)(?:\s+\(?' . $this->t("operated by") . '\s*(.*?)\)?$|$)/u', $flight, $matches)) {
                    $itsegment['AirlineName'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];

                    if (!empty($matches[3])) {
                        $itsegment['Operator'] = $matches[3];
                    }
                }

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)#");
                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#(.+?)\s*(?:\(([A-Z]{3})\)|$)#");

                // DepartureTerminal
                $terminalDep = $this->http->FindSingleNode("./td[1]//text()[" . $this->eq($terminalTitle) . "]/following::text()[normalize-space(.)][1]", $root);

                if (empty($terminalDep)) {
                    //if use <span />
                    $terminalDep = $this->http->FindSingleNode("./td[1]//text()[" . $this->eq($terminalTitle) . "]/ancestor::td[1]", $root, true, "#" . $this->opt($terminalTitle) . "\s*(.+)#");
                }

                if (empty($terminalDep)) {
                    $terminalDep = $this->http->FindSingleNode("./td[1]//text()[" . $this->starts(str_replace([':', ' '], '', $terminalTitle)) . "]/ancestor::td[1]", $root, true, "#:\s*(.+)#");
                }

                if ($terminalDep) {
                    $itsegment['DepartureTerminal'] = $terminalDep;
                }

                // DepDate
                // ArrDate
                $depTime = $this->http->FindSingleNode("./td[1]", $root);
                $arrTime = $this->http->FindSingleNode("./td[3]", $root);
                $times = [
                    'Dep' => $depTime,
                    'Arr' => $arrTime,
                ];
                $re = '/[\D]*(\d{1,2}\s*[:\.h]*\s*\d{2}\s*(?:PM|AM)?)(?:Uhr|U:r|uur)?\s*(?:\+(\d{1,2}))?/i'; //after below replace Uhr->U:r
                $res = '';

                foreach ($times as $key => $time) {
                    $time = str_replace(['.', ' h ', 'h'], [':', ':', ':'], $time);

                    if (preg_match($re, $time, $m) && is_object($date)) {
                        $date->modify($m[1]);

                        if (!empty($m[2])) {
                            $date->modify("+$m[2] days");
                        }
                        $itsegment[$key . 'Date'] = $date->getTimestamp();
                    }
                }

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#(.+?)\s*(?:\([A-Z]{3}\)|$)#");
                $terminalArr = $this->http->FindSingleNode("./td[3]//text()[" . $this->eq($terminalTitle) . "]/following::text()[normalize-space(.)][1]", $root);

                if (empty($terminalArr)) {
                    //if use <span />
                    $terminalArr = $this->http->FindSingleNode("./td[3]//text()" . $this->eq($terminalTitle) . "]/ancestor::td[1]", $root, true, "#" . $this->opt($terminalTitle) . "\s*(.+)#");
                }

                if (empty($terminalArr)) {
                    $terminalArr = $this->http->FindSingleNode("./td[3]//text()[" . $this->starts(str_replace([':', ' '], '', $terminalTitle)) . "]/ancestor::td[1]", $root, true, "#:\s*(.+)#");
                }

                if ($terminalArr) {
                    $itsegment['ArrivalTerminal'] = $terminalArr;
                }

                // Cabin
                // BookingClass
                $cabin = $this->http->FindSingleNode('./ancestor::tr[1]/following-sibling::tr[position()<3][' . $this->contains($this->t("Cabin")) . ']', $root);
                // Economy / Coach (H)
                if (preg_match('/' . $this->opt($this->t("Cabin")) . '\s*:\s*([^)(]+)(?:\s+\(([A-Z]{1,2})\)|$)/', $cabin, $matches)) {
                    $itsegment['Cabin'] = trim($matches[1]);

                    if (!empty($matches[2])) {
                        $itsegment['BookingClass'] = $matches[2];
                    }
                }

                // Seats
                $seatsText = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[3]//text()[normalize-space(.)='" . $this->t("Seat:") . "']/following::text()[normalize-space(.)][1]", $root, true, '/([,A-Z\s\d]+)\s+\|/');

                if ($seatsText) {
                    $seats = array_filter(preg_split('/\s*,\s*/', $seatsText), function ($item) {
                        return preg_match('/^\d{1,3}[A-z]$/', $item) > 0;
                    });

                    if (!empty($seats)) {
                        $itsegment['Seats'] = $seats;
                    }
                }

                // Duration
                $duration = $this->http->FindSingleNode('./ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<5][' . $this->contains($this->t("duration")) . '][1]', $root, true, '/(\d[\d\shumin.]+)/');

                if ($duration) {
                    $itsegment['Duration'] = trim($duration);
                }

                $it['TripSegments'][] = $itsegment;
            }

            unset($dateRelative);

            $itineraries[] = $it;
        }
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->logger->debug('$instr in = '.print_r( $instr,true));
        $in = [
            "#(\d{1,2})/(\d{1,2})/(\d{4})#", // 12/31/2017
            "#^\s*(\d{1,2})-(\d{1,2})-(\d{4})\s*$#", // 18-05-2018
            "#^(\d{1,2})\s+([^,.\d\s]{3,})\.?,\s*(\d{2,4})#", // 1 Feb,2018, 28 oct., 2016
            "#(\d{1,2})\s+de\s+([^,.\d\s]{3,})\.?\s+de\s+(\d{2,4})#", // 22 de dic de 2017
            "#(\d{1,2})\.?\s+([^,.\d\s]{3,})\.?\s+(\d{2,4})$#", // 2 okt. 2017, 8. Mai 2018
            '/^(\d{4})\D+(\d{1,2})\D+(\d{1,2})\D+$/u', // 2020 年 6 月 11 日

            "#([^,.\d\s]{3,})\s+(\d{1,2}),?\s+(\d{4})$#", // ago 1, 2018 | ene 15 2020
            "#[^,.\d\s]{2,}\.[,\s]\s*(\d{1,2})\.\s+([^,.\d\s]{3,})\.?$#u", // Mi., 1. März; søn. 7. mai
            "#^[^\d\s]+\.,\s+(\d{1,2})\s+de\s+([^\d\s]+)\.$#",
            '/^(?<week>[-[:alpha:]]+?)\.?[,\s]+([[:alpha:]]{3,})\s+(\d{1,2})$/u', // mié., ago 1    |    Tue, Nov 22
            "#^[^\d\s]+\s+(\d{1,2})\s+([^\d\s]+)\.$#",

            "#^[^\d\s]+,\s+(\d{1,2})\.?\s+([^\d\s]+)$#",
            "#^[^\d\s]+\s+(\d{1,2})\s+([^\d\s]+)$#",
            "#^(\d+)\s+h\s+(\d+)$#",
            "#^[^\d\s]+\.\s+(\d{1,2})\.\s+([^\d\s]+)\.$#",
            "#^[^\d\s]+\.,\s+(\d{1,2})\.\s+([^\d\s]+)\.$#",

            '#\D+\s+(\d{1,2})\s+de\s+(\D+)#',
            '#(?<week>[^,.\d\s]{2,}),\s+(\d{1,2})\s+([^,.\d\s]{3,})#', // Sat, 25 Mar
            "#^(\d+)/(\d+) \((?<week>月|火|水|木|金|土|日)\)$#",
            "#^(?<week>[^\d\s]+?)\s+(\d{1,2}\.\d{1,2}\.)\s*$#", //su 16.9.
            '/^\s*(\d{1,2}) ?\S ?(\d{1,2}) ?\S ?[\(（]\S+[\)）]\s*$/u',
            "#^\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*(?<week>[[:alpha:]]+)\s*$#u",
        ];
        $out = [
            "$2.$1.$3",
            "$1.$2.$3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            '$1-$2-$3',

            "$2 $1 $3",
            "$1 $2",
            "$1 $2",
            "$3 $2 %Y%",
            "$1 $2",

            "$1 $2",
            "$1 $2",
            "$1:$2",
            "$1 $2",
            "$1 $2",

            "$1 $2",
            "$2 $3 %Y%",
            "$1/$2/%Y%",
            "$2%Y%",
            '$1/$2/%Y%',
            '%Y%-$1-$2',
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m) || preg_match("#\d+\s+([^\d\s]+)$#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && !empty($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang) ?? WeekTranslate::number1($m['week'], 'en');

                if (!$dayOfWeekInt) {
                    return null;
                }

                return date('Y-m-d H:i:s', EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt));
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return date('Y-m-d H:i:s', EmailDateHelper::parseDateRelative(str_replace("%Y%", '', $str), $relDate, true, $str));
        }

        return $str;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function currency($s)
    {
        $sym = [
            '$C' => 'CAD',
            '€'  => 'EUR',
            'R$' => 'BRL',
            'C$' => 'CAD',
            '$CA'=> 'CAD',
            'SG$'=> 'SGD',
            'HK$'=> 'HKD',
            'AU$'=> 'AUD',
            '$'  => 'USD',
            '£'  => 'GBP',
            //			'kr'=>'NOK', NOK or SEK
            'RM'   => 'MYR',
            '฿'    => 'THB',
            'MXN$' => 'MXN',
            '円'    => 'JPY',
            'NT$'  => 'TWD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d ]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})\b#", "$1", $this->re("#([\d\,\. ]+)#", $s)));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
