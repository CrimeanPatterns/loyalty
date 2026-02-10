<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;

class AirBooking extends \TAccountChecker
{
    public $mailFiles = "egencia/it-10168261.eml, egencia/it-10232962.eml, egencia/it-10237639.eml, egencia/it-10237658.eml, egencia/it-10237684.eml, egencia/it-10291462.eml, egencia/it-10324096.eml, egencia/it-1921379.eml, egencia/it-2118006.eml, egencia/it-2130924.eml, egencia/it-2413051.eml, egencia/it-8729047.eml, egencia/it-8729075.eml, egencia/it-9933055.eml, egencia/it-9933109.eml";

    public $reFrom = "@egencia.";
    public $reBody = [
        'en' => ['Summary', 'Itinerary'],
        'es' => ['Resumen', 'Itinerario'],
        'fr' => ['Récapitulatif', 'Itinéraire'],
        'de' => ['Zusammenfassung', 'Reiseplan'],
    ];
    public $reSubject = [
        'air booking for',
        'hotel booking for',
        'car booking for',
        'hotel haciendo la reserva para',
        'vuelo haciendo la reserva para',
        'Hotel Buchung für',
        'Flug Buchung für',
        'Mietwagen Buchung für',
        'réservation avion pour',
        'réservation avion/hôtel/voiture pour',
        'réservation avion/hôtel pour',
        'réservation hôtel pour',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
        'es' => [
            'flight'                => 'vuelo',
            'Duration'              => 'Duración',
            'Reference'             => 'Referencia',
            'Estimated price'       => 'Precio estimado',
            'Total estimated price' => 'Precio total estimado',
            //hotel
            'description'           => 'Descripción',
            'night\(s\)'            => 'noche\(s\)',
            'Agency reference'      => 'Referencia de la agencia',
            'Room reference number' => 'Número de referencia de la habitación',
            'Check-in date'         => 'Fecha de entrada',
            'Room description'      => 'Descripción de la habitación',
            'Rooms:'                => 'Habitaciones:',
            'Adults per room'       => 'Adultos por habitación',
            //			'RATE TYPE' => 'TIPO DE TARIFA',
            'CANCELLATION POLICY' => "DIRECTIVA DE CANCELACIÓN",

            //car TODO need extended
        ],
        'fr' => [
            'flight'   => 'vol',
            'Duration' => 'Durée',
            //			'operated by' => '',
            'Reference'             => 'Référence',
            'Estimated price'       => 'Prix total estimé:',
            'Total estimated price' => 'Prix total estimé:',
            //hotel
            'hotel'                 => 'hôtel',
            'description'           => 'description',
            'night\(s\)'            => 'nuit\(s\)',
            'Tel'                   => 'Tél',
            'Agency reference'      => "Référence de l'agence",
            'Room reference number' => 'Référence de la chambre',
            'Check-in date'         => "Date d'arrivée",
            'Room description'      => 'Description de la chambre',
            'Rooms:'                => 'Chambres:',
            'Adults per room'       => "Nombre d'adultes par chambre",
            //			'RATE TYPE' => 'TIPO DE TARIFA',
            'CANCELLATION POLICY' => "POLITIQUE D'ANNULATION",
            //car
            'car'                  => 'voiture',
            'Reservation for'      => 'Réservation pour',
            'Confirmation Number'  => 'Numéro de confirmation',
            'Pick-up'              => 'Prise du véhicule',
            'Address'              => 'Adresse',
            'Car agency phone'     => "Téléphone de l'agence de location",
            'Drop-off'             => 'Retour du véhicule',
            'Vehicle'              => 'Véhicule',
            'Type (not guaranted)' => 'Type (non garanti)',
            'Category'             => 'Catégorie',
        ],
        'de' => [
            'flight'                => 'Flug',
            'Duration'              => 'Dauer',
            'operated by'           => 'durchgeführt von',
            'Reference'             => 'Buchungscode',
            'Estimated price'       => 'Voraussichtlicher Preis',
            'Total estimated price' => 'Voraussichtlicher Gesamtpreis',
            //hotel
            'hotel'                 => 'Hotel',
            'description'           => 'Beschreibung',
            'night\(s\)'            => 'Übernachtung\(en\)',
            'Agency reference'      => 'Buchungsnummer',
            'Room reference number' => 'Referenznummer', // Zimmer 1 Referenznummer
            'Check-in date'         => 'Anreisedatum',
            'Room description'      => 'Zimmerbeschreibung',
            'Rooms:'                => 'Zimmer:',
            'Adults per room'       => 'Erwachsene pro Zimmer',
            //			'RATE TYPE' => 'HOTEL-RATE',
            'CANCELLATION POLICY' => 'STORNIERUNGSRICHTLINIEN',
            //car
            'car'                  => 'Mietwagen',
            'Reservation for'      => 'Reservierung für',
            'Confirmation Number'  => 'Bestätigungsnummer',
            'Pick-up'              => 'Abholung',
            'Address'              => 'Adresse',
            'Car agency phone'     => "Telefonnummer der Mietwagenagentur",
            'Drop-off'             => 'Abgabe',
            'Vehicle'              => 'Fahrzeug',
            'Type (not guaranted)' => 'Fahrzeugtyp (nicht garantiert)',
            'Category'             => 'Kategorie',
        ],
    ];
    private $subject;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();
        $this->AssignLang();

        $its = $this->parseEmailFlight();
        $hotels = $this->parseEmailHotel();

        foreach ($hotels as $hotel) {
            $its[] = $hotel;
        }
        $cars = $this->parseEmailCar();

        foreach ($cars as $car) {
            $its[] = $car;
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'" . $this->t("Total estimated price") . "')]/following::text()[string-length()>2][1]"));

        if (!empty($tot['Total'])) {
            return [
                'parsedData' => ['Itineraries' => array_values($its), 'TotalCharge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]],
                'emailType'  => 'AirBooking' . ucfirst($this->lang),
            ];
        } else {
            return [
                'parsedData' => ['Itineraries' => array_values($its)],
                'emailType'  => 'AirBooking' . ucfirst($this->lang),
            ];
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'images.egencia')] | //a[contains(@href,'egencia')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $langs = count(self::$dict);
        $cnt = $langs * 3; //3 types - flight, car, hotel

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function parseEmailFlight()
    {
        $its = [];
        $xpath = "//*[contains(normalize-space(text()), '{$this->t('flight')}')]/ancestor::tr[2]/following-sibling::tr[string-length(normalize-space(.))>3][position()<3]//descendant::tr[string-length(normalize-space(.))>3 and count(descendant::td)=5]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $seg = [];
            $node = $this->http->FindNodes("./td[1]//text()[normalize-space(.)!='']", $root);

            if (count($node) > 1) {
                $seg['DepDate'] = $this->normalizeDate($node[0]);

                if (!empty($node[1]) && preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node[1], $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                }

                if (!empty($node[2]) && preg_match("#Terminal\s+(.+)#", $node[2], $m)) {
                    $seg['DepartureTerminal'] = $m[1];
                }
            }
            $node = $this->http->FindNodes("./td[2]//text()[normalize-space(.)!='']", $root);

            if (count($node) > 1) {
                $seg['ArrDate'] = $this->normalizeDate($node[0]);

                if (!empty($node[1]) && preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node[1], $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                }

                if (!empty($node[2]) && preg_match("#Terminal\s+(.+)#", $node[2], $m)) {
                    $seg['ArrivalTerminal'] = $m[1];
                }
            }
            $seg['Cabin'] = $this->http->FindSingleNode("(./td[3]//text()[normalize-space(.)!=''])[1]", $root);

            if (!empty($bc = $this->http->FindSingleNode("(./td[3]//text()[normalize-space(.)!=''])[2]", $root, true, "#\(([A-Z]{1,2})\)#"))) {
                $seg['BookingClass'] = $bc;
            }
            $it['RecordLocator'] = $this->http->FindSingleNode("(./td[5]//text()[normalize-space(.)!=''])[1]", $root);

            if (empty($it['RecordLocator'])) {
                if (!empty($lastRL)) {
                    $it['RecordLocator'] = $lastRL;
                }
            } else {
                $lastRL = $it['RecordLocator'];
            }
            $node = $this->http->FindNodes("./td[4]//text()[normalize-space(.)!='']", $root);

            if ((isset($node[0]) && preg_match("#\s*([A-Z\d]{2})\s*(\d+)$#", $node[0], $m))
                || (isset($node[1]) && preg_match("#\s*([A-Z\d]{2})\s*(\d+)$#", $node[1], $m))) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seg['Duration'] = $this->http->FindSingleNode("./td[4]//text()[starts-with(normalize-space(.), '{$this->t('Duration')}')][1]", $root, true, "#{$this->t('Duration')}\s*(.+)$#");
            $seg['Operator'] = $this->http->FindSingleNode("./td[4]//text()[starts-with(normalize-space(.), '{$this->t('operated by')}')][1]", $root, true, "#{$this->t('operated by')}\s*(.+)$#");

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        $its = $this->mergeItineraries($its);

        foreach ($its as &$it) {
            $tot = $this->http->FindSingleNode("//td[contains(.,'{$this->t('Reference')}')]/following-sibling::td[normalize-space(.)='{$it['RecordLocator']}']/ancestor::tr[1]/preceding-sibling::tr[1]/td[contains(.,'{$this->t('Estimated price')}')]/following-sibling::td[1]");
            $tot = $this->getTotalCurrency($tot);

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $it['Passengers'] = $this->http->FindNodes("//td[contains(.,'{$this->t('Reference')}') and contains(normalize-space(.),'{$it['RecordLocator']}')]/ancestor::td[1]/preceding-sibling::td[1]//text()[not(./ancestor::b) and not(./ancestor::strong)]");
            $it['ReservationDate'] = $this->normalizeDate($this->http->FindSingleNode("//*[contains(normalize-space(text()), 'TICKETING DATE')]/following::text()[1]", null, true, "#(\d+\/\d+\/\d+)#"));

            if (!empty($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Egencia confirms the following cancellation')]")) || (stripos($this->subject, 'CANCELLATION') !== false)) {
                $it['Status'] = 'Cancelled';
                $it['Cancelled'] = true;
            }
        }

        return $its;
    }

    private function parseEmailHotel()
    {
        $its = [];
        $xpath = "//*[contains(normalize-space(text()), '{$this->t('hotel')}')]/ancestor::tr[2][count(descendant::tr)>3 and contains(., '{$this->t('description')}') ]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            $node = $this->http->FindSingleNode("./descendant::table[1]/descendant::tr[1]/following-sibling::tr[string-length()>3][1]/td[1]", $root);

            if (preg_match("#(.+?)\s+(?:\({$this->t('description')}\)\s)*\s*\d+\s*{$this->t('night\(s\)')}\s*(.+)\s*{$this->t('Tel')}\.\s*([\d\(\)\-\+ ]+)#is", $node, $m)) {
                $it['HotelName'] = $m[1];
                $it['Address'] = trim($m[2]);
                $it['Phone'] = $m[3];
            }
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./descendant::table[1]/descendant::tr[1]/following-sibling::tr[string-length()>3][1]/td[contains(.,\"{$this->t('Agency reference')}\")]/following-sibling::td[1]//text()[normalize-space()][1]", $root);
            $it['ConfirmationNumbers'] = $this->http->FindNodes("./descendant::table[1]/descendant::tr[1]/following-sibling::tr[string-length()>3]/td[contains(.,'{$this->t('Room reference number')}')]/following-sibling::td[1]", $root);

            $it['GuestNames'] = $this->http->FindNodes("//td[contains(.,'{$this->t('Reference')}') and contains(normalize-space(.),'{$it['ConfirmationNumber']}')]/ancestor::td[1]/preceding-sibling::td[1]//text()[not(./ancestor::b) and not(./ancestor::strong)]");

            if (empty($it['GuestNames'])) {
                $it['GuestNames'] = array_values(array_filter($this->http->FindNodes("./descendant::table[1]/descendant::tr[1]/following-sibling::tr[string-length()>3]/td[contains(.,'{$this->t('Room reference number')}')]", $root, "#{$this->t('Room reference number')}\s*\((.+)\)#")));
            }
            $node = $this->http->FindNodes("./following-sibling::tr[1]/descendant::td[contains(.,\"{$this->t('Check-in date')}\") and count(descendant::td)=0]/following-sibling::td[1]//text()", $root);

            if (count($node) > 1) {
                $it['CheckInDate'] = $this->normalizeDate($node[0]);
                $it['CheckOutDate'] = $this->normalizeDate($node[1]);
            }
            $it['RoomType'] = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[contains(.,'{$this->t('Room description')}') and count(descendant::td)=0]/following-sibling::td[1]", $root);
            $it['Rooms'] = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[normalize-space() = '{$this->t('Rooms:')}' and count(descendant::td)=0]/following-sibling::td[1]", $root);

            if (!empty($it['Rooms'])) {
                $it['Guests'] = $it['Rooms'] * $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[contains(.,\"{$this->t('Adults per room')}\") and count(descendant::td)=0]/following-sibling::td[1]", $root);
            } else {
                $it['Guests'] = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[contains(.,\"{$this->t('Adults per room')}\") and count(descendant::td)=0]/following-sibling::td[1]", $root);
            }

            $it['CancellationPolicy'] = $this->http->FindSingleNode("./following-sibling::tr[2]//strong[contains(normalize-space(.), \"{$this->t('CANCELLATION POLICY')}\")]/following-sibling::ul[1]", $root);

            $tot = $this->http->FindSingleNode(".//text()[contains(.,'{$this->t('Estimated price')}')]/ancestor::td[1]/following-sibling::td[1]", $root);
            $tot = $this->getTotalCurrency($tot);

            if (!empty($tot['Total'])) {
                $it['Total'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            if (!empty($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Egencia confirms the following cancellation')]")) || (stripos($this->subject, 'CANCELLATION') !== false)) {
                $it['Status'] = 'Cancelled';
                $it['Cancelled'] = true;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailCar()
    {
        $its = [];
        $xpath = "//*[contains(normalize-space(text()), '{$this->t('car')}')]/ancestor::tr[2][contains(.,'{$this->t('Reference')}')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'L'];
            $it['TripNumber'] = $this->http->FindSingleNode(".//text()[contains(.,'{$this->t('Reference')}')]/following::text()[1]", $root);
            $it['RenterName'] = $this->http->FindSingleNode(".//text()[contains(.,'{$this->t('Reservation for')}')]/following::text()[1]", $root);
            $it['RentalCompany'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='{$this->t('car')}']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root);
            $it['Number'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Confirmation Number')}')]//text()[contains(.,'{$this->t('Confirmation Number')}')]/following::text()[1]", $root);
            $it['PickupDatetime'] = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Pick-up')}')]//td[contains(.,'{$this->t('Pick-up')}') and count(descendant::td)=0]/following-sibling::td[string-length(normalize-space(.))>2][1]", $root));
            $it['PickupLocation'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Pick-up')}')]/descendant::text()[contains(.,'{$this->t('Pick-up')}')]/following::text()[contains(.,'{$this->t('Address')}')][1]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]", $root);
            $it['PickupPhone'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Pick-up')}')]/descendant::text()[contains(.,'{$this->t('Pick-up')}')]/following::text()[contains(.,\"{$this->t('Car agency phone')}\")][1]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]", $root);
            $it['DropoffDatetime'] = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Drop-off')}')]//td[contains(.,'{$this->t('Drop-off')}') and count(descendant::td)=0]/following-sibling::td[string-length(normalize-space(.))>2][1]", $root));
            $it['DropoffLocation'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Drop-off')}')]/descendant::text()[contains(.,'{$this->t('Drop-off')}')]/following::text()[contains(.,\"{$this->t('Address')}\")][1]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]", $root);
            $it['DropoffPhone'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Drop-off')}')]/descendant::text()[contains(.,'{$this->t('Drop-off')}')]/following::text()[contains(.,\"{$this->t('Car agency phone')}\")][1]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]", $root);
            $it['CarModel'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Vehicle')}')]/descendant::text()[contains(.,'{$this->t('Type (not guaranted)')}')]/following::text()[string-length(normalize-space(.))>2][1]", $root);
            $it['CarType'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$this->t('Vehicle')}')]/descendant::text()[contains(.,'{$this->t('Category')}')]/following::text()[string-length(normalize-space(.))>2][1]", $root);
            $tot = $this->http->FindSingleNode(".//text()[contains(.,'{$this->t('Estimated price')}')]/ancestor::td[1]/following-sibling::td[1]", $root);
            $tot = $this->getTotalCurrency($tot);

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            if (!empty($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Egencia confirms the following cancellation')]")) || (stripos($this->subject, 'CANCELLATION') !== false)) {
                $it['Status'] = 'Cancelled';
                $it['Cancelled'] = true;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d{2})\/*(\d{2})\/(\d{2})\s+(\d+:\d+)$#',
            '#^(\d{2})\/*(\d{2})\/(\d{2})$#',
        ];
        $out = [
            '$3-$2-$1 $4',
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $node = str_replace("€", "EUR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
