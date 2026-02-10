<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1619933 extends \TAccountCheckerExtended
{
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amadeus#i";
    public $typesCount = "1";
    public $langSupported = "nl";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1619933.eml, amadeus/it-18.eml, amadeus/it-19.eml, amadeus/it-4687462.eml, amadeus/it-4696874.eml, amadeus/it-4705573.eml, amadeus/it-4712667.eml, amadeus/it-4721865.eml, amadeus/it-1619933.eml, amadeus/it-4687462.eml, amadeus/it-4696874.eml, amadeus/it-4705573.eml, amadeus/it-4712667.eml, amadeus/it-4721865.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getDate());

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Booking\s+code|Boekingscode|Code\s+de\s+réservation|"
                                . "Código\s+de\s+reserva|Prenotazione\s+code|Buchungscode):\s*([\d\w\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = xPath("//*[contains(text(), 'Passenger Name') or contains(text(), 'Naam passagier') "
                                . "or contains(normalize-space(text()), 'Nom du passager') "
                                . "or contains(normalize-space(text()), 'Nombre del pasajero') "
                                . "or contains(normalize-space(text()), 'Nome del passeggero') "
                                . "or contains(normalize-space(text()), 'Name des Passagiers')]/ancestor-or-self::tr[1]/following-sibling::tr");

                        $array = [];

                        foreach ($xpath as $root) {
                            $array['Passengers'][] = node('td[1]', $root);
                            $array['TicketNumbers'][] = node('td[2]', $root);
                            $array['AccountNumbers'][] = node('td[3]', $root);
                        }

                        return $array;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(orval(
                                cell("Total amount:", +1),
                                cell("Totaalbedrag:", +1),
                                cell("Montant total:", +1),
                                cell("Importo totale:", +1),
                                cell("Gesamtsumme:", +1)));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(cell("Fare amount:", +1),
                                cell("Ticketprijs:", +1),
                                cell("Montant du billet:", +1),
                                cell("Precio de tarifa:", +1),
                                cell("Tariffa per l'importo di:", +1),
                                cell("Ticketpreis:", +1)));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $tax = orval(cell("Tax & Carrier Fees/Charges:", +1),
                                cell("Belasting/kosten/toeslagen:", +1),
                                cell("Taxes/frais:", +1),
                                cell("Impuesto/tasa/gastos:", +1),
                                cell("Tassa/imposta/addebiti:", +1),
                                cell("Steuer / Gebühr / Zuschlag:", +1));

                        $r = filter(preg_split("#\s+#", clear("#[^\d.]#", $tax, ' ')));
                        $tax = 0;

                        foreach ($r as $item) {
                            $tax += cost($item);
                        }

                        return $tax;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//*[contains(normalize-space(text()), "Itinerary Information") '
                                . 'or contains(normalize-space(text()), "Reisinformatie") '
                                . 'or contains(normalize-space(text()), "Informations sur l\'itinéraire") '
                                . 'or contains(normalize-space(text()), "Informacion de itinerario") '
                                . 'or contains(normalize-space(text()), "Informazioni sul percorso") '
                                . 'or contains(normalize-space(text()), "Flugdaten")]/'
                                . 'ancestor-or-self::tr[1]/following-sibling::tr[position()>1 and position() mod 2=0]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#([A-Z\d]{2})\s*(\d+)#", node('td[2]')),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return node('following-sibling::tr[1]/td[3]');
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return node('following-sibling::tr[1]/td[4]');
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('td[3]');
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('td[4]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return $this->increaseDate($this->date,
                                    en(node('td[1]')) . ',' . node('following-sibling::tr[1]/td[1]'),
                                    en(node('td[5]')) . ',' . node('following-sibling::tr[1]/td[5]'));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node("td[6]");
                        },
                    ],
                ],
            ],
        ];
    }

    public function increaseDate($dateLetter, $dateSegment1, $dateSegment2)
    {
        $depDate = strtotime($dateSegment1, $dateLetter);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }

        $arrDate = strtotime($dateSegment2, $dateLetter);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    public static function getEmailTypesCount()
    {
        return 6;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'nl', 'fr', 'es', 'it', 'de'];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
