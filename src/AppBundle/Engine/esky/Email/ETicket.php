<?php

namespace AwardWallet\Engine\esky\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "esky/it-11180201.eml, esky/it-12232710.eml, esky/it-12232711.eml, esky/it-16317021.eml, esky/it-16458889.eml, esky/it-181907499.eml, esky/it-18755436.eml, esky/it-21732726.eml, esky/it-32951582.eml, esky/it-33444780.eml, esky/it-49298029.eml, esky/it-53483398.eml, esky/it-76867266.eml, esky/it-8810383.eml, esky/it-9118904.eml";

    public static $dictionary = [
        "en" => [
            "eSky order number" => ["eSky order number", "eSky booking number"],
            //			"Flight booking number" => "",
            //			"Flight:" => "",
            "Total price:"    => ["Total price:", "Total due today:"],
            "Tickets numbers" => ["Tickets numbers", "Ticket number"],
            //			"Seat selection" => "",
            "Depart:" => ["Depart:", "Departure:"],
            //			"Airline:" => "",
            //			"Flight no.:" => "",
            "Ticket class:" => ["Ticket class:", "Class:"],
            "Duration:"     => ["Duration:", "Flight time:"],
            //			"In case of any doubts" => "",
            //            "Booking no. for airline:" => "",
            //            "Operated by:" => "",
            //            "Passengers" => "",
        ],
        "pl" => [
            "eSky order number"        => ["eSky order number", "Numer rezerwacji eSky"],
            "Flight booking number"    => "Numer rezerwacji lotniczej",
            "Flight:"                  => "Lot:",
            "Total price:"             => ["Do zapłaty teraz:", "Kwota całkowita:", "Razem", "Cena całkowita:"],
            "Tickets numbers"          => "Numer biletu",
            "Seat selection"           => "Wybór miejsca",
            "Depart:"                  => "Wylot:",
            "Airline:"                 => ["Linia lotnicza:", "Linia obsługująca lot:"],
            "Flight no.:"              => "Lot:",
            "Ticket class:"            => "Klasa:",
            "Duration:"                => "Czas lotu:",
            "In case of any doubts"    => "W przypadku pytań lub",
            "Booking no. for airline:" => "Numer rezerwacji przewoźnika:",
            "Operated by:"             => "Linia obsługująca lot:",
            "Passengers"               => "Pasażerowie",
        ],
        "hu" => [
            //			"eSky order number" => "",
            "Flight booking number" => "Repülőjegy foglalási száma",
            //			"Flight:" => "",
            "Total price:"          => "Teljes összeg:",
            "Tickets numbers"       => "A jegy száma",
            "Seat selection"        => "Seat selection",
            "Depart:"               => "Indulás:",
            "Airline:"              => "Repülőjárat:",
            "Flight no.:"           => "Járatszám:",
            "Ticket class:"         => "Osztály:",
            "Duration:"             => "Repülési idő:",
            "In case of any doubts" => "Amennyiben kérdése merülne fel",
            "Passengers"            => "Utasok",
        ],
        "es" => [
            "eSky order number"     => ["eDestinos número de pedido", "eDestinos número de reserva"],
            "Flight booking number" => "Número de la reserva de vuelo",
            "Flight:"               => "Vuelo:",
            "Total price:"          => ["Total a pagar hoy:", "Valor total:"],
            "Tickets numbers"       => "Nro. de pasaje",
            //			"Seat selection" => "",
            "Depart:"                  => "Salida",
            "Airline:"                 => "Aerolínea:",
            "Flight no.:"              => ["Vuelo nro.:", "Vuelo N°"],
            "Ticket class:"            => "Clase:",
            "Duration:"                => ["Tiempo de vuelo:", "Duración del vuelo:"],
            "In case of any doubts"    => ["En caso de dudas", "¿Tienes dudas? ¡Contáctanos"],
            "Booking no. for airline:" => "N° de reserva de la aerolínea:",
            "Operated by:"             => "Operado por:",
            "No. of passage:"          => "Nro. de pasaje:",
            "Passengers"               => "Pasajeros",
        ],
        "pt" => [ // it-76867266.eml
            "eSky order number"     => "Reserva eDestinos",
            "Flight booking number" => "Código de reserva de voo",
            "Flight:"               => "Voo:",
            "Total price:"          => ["Preço total:", "A pagar agora:"],
            "Tickets numbers"       => "Código do bilhete",
            "Seat selection"        => "Escolha de assento",
            "Depart:"               => "Origem:",
            "Airline:"              => ["Companhia aérea:", "Linha aérea:"],
            "Flight no.:"           => "N° do voo:",
            "Ticket class:"         => "Classe:",
            "Duration:"             => "Duração do voo:",
            "In case of any doubts" => "Telefone para passagens aéreas",
            "Passengers"            => "Passageiros",
        ],
        "hr" => [
            //			"eSky order number" => "",
            "Flight booking number" => "Broj avionske rezervacije",
            //			"Flight:" => "",
            "Total price:"    => ["Ukupan iznos:"],
            "Tickets numbers" => "Broj karte",
            //			"Seat selection" => "",
            "Depart:"                  => "Odlazak:",
            "Airline:"                 => "Avio kompanija:",
            "Flight no.:"              => "Let:",
            "Ticket class:"            => "Razred:",
            "Duration:"                => "Vreme trajanja leta:",
            "In case of any doubts"    => "U slučaju pitanja ili nedoumica kontaktirajte nas",
            "Booking no. for airline:" => "Broj rezervacije pružaoca putovanja:",
            "Operated by:"             => "Avio kompanije koje upravlja letom:",
            "Passengers"               => "Putnici",
        ],
        "ro" => [
            "eSky order number"     => "eSky numărul comenzii",
            "Flight booking number" => "Rezervare pentru bilet de avion",
            //			"Flight:" => "",
            "Total price:"    => ["Total plată astăzi:"],
            "Tickets numbers" => "Numărul biletului",
            //			"Seat selection" => "",
            "Depart:"                  => "Plecare:",
            "Airline:"                 => "Compania aeriană:",
            "Flight no.:"              => "Nr. zborului:",
            "Ticket class:"            => "Clasa:",
            "Duration:"                => "Timpul de zbor:",
            "In case of any doubts"    => "Serviciul Clienţi vă stă la dispoziţie la",
            "Booking no. for airline:" => "Numărul de rezervare al companiei aeriene:",
            "Operated by:"             => "Operat de:",
            "Passengers"               => "Pasageri",
        ],
        "sk" => [
            //			"eSky order number" => "",
            "Flight booking number" => "Číslo rezervácie letu",
            //			"Flight:" => "",
            "Total price:"    => ["Výsledná cena:"],
            "Tickets numbers" => "Čísla leteniek",
            //			"Seat selection" => "",
            "Depart:"               => "Odlet:",
            "Airline:"              => "Letecká spoločnosť:",
            "Flight no.:"           => "Let č.:",
            "Ticket class:"         => "Trieda:",
            "Duration:"             => "Čas letu:",
            "In case of any doubts" => "V prípade akýchkoľvek pochybností neváhajte nás kontaktovať",
            "Passengers"            => "Cestujúci",
        ],
        "fr" => [
            //			"eSky order number" => "",
            "Flight booking number" => "Référence de réservation du vol",
            //			"Flight:" => "",
            "Total price:"    => ["Montant total :"],
            "Tickets numbers" => "Numéro de billet ",
            //			"Seat selection" => "",
            "Depart:"               => "Départ :",
            "Airline:"              => "Compagnie aérienne :",
            "Flight no.:"           => "Vol :",
            "Ticket class:"         => "Classe :",
            "Duration:"             => "Durée de vol :",
            "In case of any doubts" => "Si vous avez des doutes ou des questions",
            //            "Booking no. for airline:" => "",
            "Operated by:"=> "La compagnie aérienne effectuant le vol :",
            "Passengers"  => "Passagers",
        ],
        "it" => [
            //            "eSky order number" => "",
            "Flight booking number" => "Numero di prenotazione aerea",
            "Flight:"               => "Volo:",
            "Total price:"          => ["Importo complessivo:"],
            "Tickets numbers"       => "Numero del biglietto:",
            //			"Seat selection" => "",
            "Depart:"               => "Andata",
            "Airline:"              => "Compagnia aerea che gestisce il volo:",
            "Flight no.:"           => "Volo:",
            "Ticket class:"         => "Classe:",
            "Duration:"             => "Tempo di volo:",
            "In case of any doubts" => "In caso di domande",
            //            "Booking no. for airline:" => "",
            //            "Operated by:" => "",
            // "Passengers" => "",
        ],

        "da" => [
            //            "eSky order number" => "",
            "Flight booking number" => "eSky bookingnummer",
            "Flight:"               => "Volo:",
            "Total price:"          => ["Totalpris:"],
            "Tickets numbers"       => "Billetnummer:",
            //			"Seat selection" => "",
            "Depart:"               => "Afgang:",
            "Airline:"              => "Flyselskab:",
            "Flight no.:"           => "Flynr.:",
            "Ticket class:"         => "Klasse:",
            "Duration:"             => "Flytid:",
            "In case of any doubts" => "",
            //            "Booking no. for airline:" => "",
            //            "Operated by:" => "",
            "Passengers" => "Passagerer",
        ],
    ];

    private $detectFrom = [
        "edestinos" => "edestinos.",
        "esky"      => "@esky.",
        "lucky"     => "lucky2go.",
    ];

    private $detectSubject = [
        "pl"  => "Twój bilet elektroniczny i polisa ubezpieczeniowa",
        "pl2" => "oczekuje na płatność",
        "hu"  => "Az Ön elektronikus jegye",
        "es"  => "Tiquete electrónico de la reserva",
        "es2" => "a la espera del pago",
        "pt"  => "Seu bilhete eletrônico para a reserva",
        "en"  => "Your itinerary for flight booking no",
        "en2" => "is waiting for payment",
        "en3" => "Your electronic ticket -",
        "hr"  => "Vaša elektronska karta",
        "ro"  => "se așteaptă confirmarea de plată",
        "sk"  => "Toto je Vaša elektronická letenka",
        "fr"  => "La réservation du vol no",
        'it'  => 'Il Tuo biglietto elettronico',
        'da'  => 'Din elektroniske billet',
    ];

    private $detectCompany = [
        "edestinos" => ["eDestinos"],
        "esky"      => ["eSky"],
        "lucky"     => ["lucky2go"],
    ];

    private $detectBody = [
        "pl"  => "Wylot:",
        "hu"  => "Indulás:",
        "es"  => "Salida",
        "pt"  => "Origem:",
        "en"  => "Depart:",
        "en2" => "Departure:",
        "hr"  => "Odlazak:",
        "ro"  => "Plecare:",
        "sk"  => "Odlet:",
        "fr"  => "Départ",
        'it'  => 'Andata',
        'da'  => 'Afgang',
    ];

    private $lang = "en";

    private $provider;

    public static function getEmailProviders()
    {
        return ["esky", "edestinos", "lucky"];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        // Provider
        if (!empty($this->provider)) {
            $codeProvider = $this->provider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        }
        $phone = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("In case of any doubts")) . "])[1]/following::text()[normalize-space(.)!=''][1]");

        if (strlen($phone) < 25 && strlen(preg_replace("#[^\d]+#", '', $phone)) > 7) {
            $email->ota()->phone($phone);
        }
        $confNo = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("eSky order number")) . "]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");

        if (!empty($confNo)) {
            $email->ota()->confirmation($confNo);
        }

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $code => $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                $this->provider = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->detectCompany as $code => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($body, $phrase) !== false) {
                    $finded = true;
                    $this->provider = $code;

                    break 2;
                }
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $recLocDirections = [];
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Flight booking number")) . "]/ancestor::td[1]/following-sibling::td[1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($conf)) {
            $trip = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Flight booking number")) . "]/ancestor::td[1]");

            if (preg_match_all("#([A-Z]{3})[ ]+\-[ ]+([A-Z]{3})#", $trip, $m, PREG_SET_ORDER)) {
                foreach ($m as $t) {
                    $recLocDirections[$t[1] . '-' . $t[2]][] = $conf;
                }
            }
            $f->general()->confirmation($conf, $trip);
        } else {
            $nodes = $this->http->XPath->query("//text()[" . $this->starts($this->t("Flight booking number")) . "]/ancestor::tr[1]/following-sibling::tr");

            foreach ($nodes as $rootRL) {
                $trip = $this->http->FindSingleNode("./td[1]", $rootRL);
                $conf = $this->http->FindSingleNode("./td[2]", $rootRL);

                if (preg_match_all("#([A-Z]{3})[ ]+\-[ ]+([A-Z]{3})#", $trip, $m, PREG_SET_ORDER)) {
                    foreach ($m as $t) {
                        $recLocDirections[$t[1] . '-' . $t[2]][] = $conf;
                        $descrConf[$conf][] = $t[1] . '-' . $t[2];
                    }
                }
            }
            $confs = $this->http->FindNodes("//text()[" . $this->starts($this->t("Flight booking number")) . "]/ancestor::tr[1]/following-sibling::tr/td[2]");

            foreach ($confs as $conf) {
                if (!empty($this->re('/([;])/u', $conf))) { //Bogotá - Cartagena, Cartagena - Bogotá	XE8E4X;UFZC2U
                    $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('No. of passage:'))}]");

                    foreach ($nodes as $root) {
                        $confNumber = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);
                        $descrConf = $this->http->FindSingleNode("./preceding::div[1]", $root);
                        $f->general()->confirmation($confNumber, $descrConf);
                    }
                } else {
                    if (isset($descrConf[$conf])) {
                        $f->general()->confirmation($conf, implode(', ', $descrConf[$conf]));
                    } else {
                        $f->general()->confirmation($conf);
                    }
                }
            }

            if (empty($confs)) {
                $f->general()
                    ->noConfirmation();
            }
        }

        $nodes = $this->http->XPath->query("//tr/*[{$this->starts($this->t("Passengers"))}]/following-sibling::*/descendant::*[contains(@class,'qa-first-name')]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//tr/*[{$this->starts($this->t("Passengers"))}]/following-sibling::*/descendant::text()[{$this->contains($this->t("Tickets numbers"))}]/preceding::*[self::div or self::p][position() < 3][not(contains(., ':'))][count(span) = 2 or count(span) = 3]/span[last() - 1]");
        }

        foreach ($nodes as $root) {
            $travellers[] = $this->http->FindSingleNode(".", $root) . ' ' . $this->http->FindSingleNode("./following-sibling::span[1]", $root);
            $ticketsText = implode("\n", $this->http->FindNodes("./ancestor::div[2][descendant::text()[{$this->contains($this->t("Tickets numbers"))}]]//text()[normalize-space(.)!='']", $root));
            $tickets = $tickets ?? [];

            if (preg_match("#{$this->preg_implode($this->t("Tickets numbers"))}\s*:\s*([\d\-A-Z]{5,}(?:[\s,]+[\d\-]{9,})*)\s*(?:\n|$)#", $ticketsText, $m)) {
                $tickets = array_merge($tickets,
                    array_map("trim", explode(',', $m[1])));
            }
        }

        if (!empty($travellers)) {
            $f->general()->travellers($travellers, true);
        }

        if (isset($tickets) && !empty(array_filter($tickets))) {
            $f->issued()->tickets(array_values(array_unique(array_filter($tickets))), false);
        }

        // Price
        $total = $this->http->FindSingleNode("(.//text()[{$this->eq($this->t("Total price:"))}])[1]/following::text()[normalize-space(.)!=''][1]/ancestor::*[self::div or self::td][1]");

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)
            || preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)) {
            if (preg_match("/^\s*(\d{1,3}(?:[ ,\.]\d{3})*)[.,](\d{2})\s*$/", $m['amount'], $mat)) {
                $m['amount'] = preg_replace("/\D/", '', $mat[1]) . '.' . $mat[2];
            }
            $email->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->currency($m['curr']));
        } elseif (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)) {
            $email->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->currency($m['curr']));
        }
        $base = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total price:")) . "]/ancestor::table[2]//text()[" . $this->eq($this->t("Flight:")) . "]/following::text()[normalize-space()][1]"));

        if (!empty($base)) {
            $email->price()->cost(PriceHelper::cost($base));
        }

        if (!empty($sum = $this->amount($this->nextText("Flight no.:")))) {
            $base = $sum;
        }

        // Segments
        $xpath = "//*[self::th or self::td][" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/ancestor::*[self::tbody or self::table][1][count(./tr)=3]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->alert("Segments did not found by xpath: {$xpath}");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $airline = $this->nextText($this->t("Airline:"), $root);

            if (!empty($airline)) {
                $s->airline()
                    ->name($airline);
            }

            $flight = $this->nextText($this->t("Flight no.:"), $root);

            if (preg_match("#^\s*([A-Z]\d|\d[A-Z]|[A-Z]{2})\s*(\d{1,5})\b#", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } elseif (preg_match("#^\s*(\d{1,5})\b#", $flight, $m)) {
                $s->airline()
                    ->number($m[1]);
            }
            $operator = $this->nextText($this->t("Operated by:"), $root);

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            if (empty($s->getAirlineName()) && empty($this->http->FindSingleNode("(.//*[" . $this->contains($this->t("Airline:")) . "])[1]", $root))) {
                $s->airline()->noName();
            }

            if (empty($s->getFlightNumber()) && empty($this->http->FindSingleNode("(.//*[" . $this->contains($this->t("Flight no.:")) . "])[1]", $root))) {
                $s->airline()->noNumber();
            }

            // Daparture
            $s->departure()
                ->code($this->http->FindSingleNode("./tr[1]/*[self::th or self::td][2]/*[2]", $root, true, "#\(\s*([A-Z]{3})\s*\)#"))
                ->name($this->http->FindSingleNode("./tr[1]/*[self::th or self::td][2]/*[2]", $root, true, "#(.*?)\s*,\s*\(\s*[A-Z]{3}\s*\)#"))
                ->terminal($this->http->FindSingleNode("./tr[1]/*[self::th or self::td][2]/*[3][contains(.,'Terminal')]", $root, true, "#Terminal[\s:]+(.+)#"), true, true)
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[1]/*[self::th or self::td][2]/*[1]", $root))));

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./tr[2]/*[self::th or self::td][2]/*[2]", $root, true, "#\(\s*([A-Z]{3})\s*\)#"))
                ->name($this->http->FindSingleNode("./tr[2]/*[self::th or self::td][2]/*[2]", $root, true, "#(.*?)\s*,\s*\(\s*[A-Z]{3}\s*\)#"))
                ->terminal($this->http->FindSingleNode("./tr[2]/*[self::th or self::td][2]/*[3][contains(.,'Terminal')]", $root, true, "#Terminal[\s:]+(.+)#"), true, true)
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/*[self::th or self::td][2]/*[1]", $root))));

            // Extra
            $s->extra()
                ->duration($this->nextText($this->t("Duration:"), $root));

            $cabin = $this->nextText($this->t("Ticket class:"), $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = array_filter($this->http->FindNodes("//text()[contains(., '" . $s->getDepCode() . "')]/ancestor::*[contains(normalize-space(), '" . $s->getDepCode() . " → " . $s->getArrCode() . "')][1]/following::text()[normalize-space()][1][" . $this->contains($this->t("Seat selection")) . "]/ancestor::*[1]", null, "#" . $this->preg_implode($this->t("Seat selection")) . "\s*(\d{1,3}[A-Z])\b#"));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }

                if (!$s->getConfirmation() && !empty($confAir = $this->nextText($this->t("Booking no. for airline:"), $root))) {
                    $s->airline()->confirmation($confAir);
                }
            }
        }

        return $email;
    }

    private function getProvider()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectCompany as $code => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (strpos($body, $dCompany) !== false) {
                    return $code;
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+:\d+)\s*,\s*(\d+ [^\s\d]+?)\.? (\d{4}) \(.*\)\s*$#", //06:25 , 10 paź 2017 (wt.)
        ];
        $out = [
            "$2 $3, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                foreach (['cs', 'sr'] as $lang) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
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

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            'US$'=> 'USD',
            '£'  => 'GBP',
            'zł' => 'PLN',
            '$'  => 'USD',
            'R$' => 'BRL',
            'R'  => 'ZAR',
            'kr' => 'SEK',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
