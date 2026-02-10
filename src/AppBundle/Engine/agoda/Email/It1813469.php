<?php

namespace AwardWallet\Engine\agoda\Email;

class It1813469 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]agoda#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "da,es,ca";
    public $typesCount = "1";
    public $reFrom = "#[@.]agoda#i";
    public $reProvider = "#[@.]agoda#i";
    public $xPath = "";
    public $mailFiles = "agoda/it-1813469.eml, agoda/it-1821757.eml, agoda/it-1821759.eml, agoda/it-1821760.eml, agoda/it-1858017.eml, agoda/it-1858018.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:booking nummer|Número de reserva)\s+([A-Z\d\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell([
                            "Hotelnavn:",
                            "Nombre del Hotel:",
                            "Nom de l'hotel:",
                        ], +1, 0);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(cell([
                            "Ankomstdato:",
                            "Fecha de llegada:",
                            "Data d'arribada:",
                        ], +1, 0)));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(cell([
                            "Afrejsedato:",
                            "Fecha de salida:",
                            "Data de sortida:",
                        ], +1, 0)));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return cell([
                            "Adresse:",
                            "Dirección:",
                            "Adreça:",
                        ], +1, 0) . ', ' . cell([
                            "Område / by / land:",
                            "Área / Ciudad / País:",
                            "Zona/ciutat/país:",
                        ], +1);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell([
                            "Ledende Gæst:",
                            "Huésped Principal",
                            "Client principal:",
                        ], +1, 0);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell([
                            "Nro. de Adultos:",
                            "No. d'adults:",
                        ], +1, 0);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return cell("Antal voksne:", +1, 0);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell([
                            "Antal værelser:",
                            "Nro. de Habitaciones:",
                            "No. d'habitacions:",
                        ], +1, 0);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(nodes("//*[
							contains(text(), 'Politik vedrørende annulleringer og ændringer') or
							contains(text(), 'Política de cancel·lacions i de canvis')
						]/ancestor-or-self::tr[1]/following-sibling::tr")));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell([
                            "Værelsestype:",
                            "Tipo de Habitación:",
                            "Tipus d'habitació:",
                        ], +1, 0);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        //return cost(cell("Total/Pris for Værelse:", +1, 0));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $total = cell([
                            'Totalt til Betaling/Opkræves',
                            'Importe Total / Cargo a la Tarjeta de Crédito:',
                            'Cost total a targeta de crèdit',
                        ], +1);

                        return cost(re("#(USD\s+[\d.,]+)#", $total));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(?:Påløbende Points|Puntos canjeados):\s*([,.\d]+)#");
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Indløste Points|Puntos acumulados):\s*([,.\d]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Din bestilling med Agoda er nu ([^.\n]+)#"),
                            re("#su reserva con Agoda se encuentra ([^.\n]+)#"),
                            re("#La vostra reserva amb Agoda s'ha\s+([^.\n]+)#")
                        );
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["da", "es", "ca"];
    }
}
