<?php

namespace AwardWallet\Engine\egencia\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "egencia/it-10955975.eml, egencia/it-10994109.eml, egencia/it-11174327.eml, egencia/it-11174329.eml, egencia/it-11174916.eml, egencia/it-3042257.eml, egencia/it-8729072.eml";

    public $reFrom = "@egencia.";
    public $reBody = [
        'en' => ['Traveler Details', 'View trip online'],
        'es' => ['Datos del viajero', 'Ver viaje en línea'],
        'de' => ['Angaben zum Reisenden', 'Reise online ansehen'],
        'fr' => ['Informations voyageur', 'Voir le voyage en ligne'],
        'no' => ['Reisendes detaljer', 'Vis reisen online'],
    ];
    public $reSubject = [
        'RESERVATION',
        'A LA ESPERA DE APROBACIÓN',
        'APROBADO PARCIALMENTE',
        'de' => 'BUCHUNG -',
        'fr' => 'RÉSERVATION -',
        'no' => 'BESTILLING -',
    ];
    public $lang = '';
    public static $dict = [
        'es' => [
            'Esperando aprobación' => ['Esperando aprobación', 'Reservado'],
            'SalidaHotel'          => 'Salida',
        ],
        'en' => [
            'Salida'               => 'Departs',
            'Llegada'              => 'Arrives',
            'Duración'             => 'Duration',
            'Esperando aprobación' => 'Booked',
            'Ref. agencia'         => 'Egencia reference',
            'Datos del viajero'    => 'Traveler Details',
            'Emisión de billetes'  => 'Ticketing',
            'Vuelo'                => 'Flight',
            'SalidaHotel'          => 'Check out',
        ],
        'de' => [
            'Salida'               => 'Abflug',
            'Llegada'              => 'Ankunft',
            'Duración'             => 'Flugdauer',
            'Esperando aprobación' => 'Gebucht',
            'Ref. agencia'         => 'Egencia Buchungsnr',
            'Datos del viajero'    => 'Angaben zum Reisenden',
            //			'Emisión de billetes' => '',
            'Vuelo'       => 'FLUG',
            'Operated by' => 'Betrieben von',
            // Hotel
            //			'SalidaHotel' => '',
        ],
        'fr' => [
            'Salida'               => 'Départ yes',
            'SalidaTrain'          => 'Départ',
            'Llegada'              => 'Arrivée',
            'Duración'             => 'Durée',
            'Esperando aprobación' => 'Réservé',
            'Ref. agencia'         => 'Référence Egencia',
            'Datos del viajero'    => 'Informations voyageur',
            //			'Emisión de billetes' => '',
            'Vuelo' => 'AVION',
            //			'Operated by' => '',
            // Hotel
            //			'SalidaHotel' => '',
            // Car
            'Recogida el' => 'Prise en charge',
            'Entrega el'  => 'retour du véhicule',
        ],
        'no' => [
            'Salida'               => 'Avreise',
            'Llegada'              => 'Ankomst',
            'Duración'             => 'Varighet',
            'Esperando aprobación' => 'Bestilt',
            'Ref. agencia'         => 'Egencias referanse',
            'Datos del viajero'    => 'Reisendes detaljer',
            'Emisión de billetes'  => 'Billettutstedelse',
            'Vuelo'                => 'FLYVNING',
            //			'Operated by' => '',
            // Hotel
            //			'SalidaHotel' => '',
            // Car
            //			'Recogida el' => '',
            //			'Entrega el' => '',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = [];
        $its = array_merge($its, $this->parseEmailFlight());
        $its = array_merge($its, $this->parseEmailTrain());
        $its = array_merge($its, $this->parseEmailCar());
        $its = array_merge($its, $this->parseEmailHotel());

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Reservation' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Egencia')] | //a[contains(@href,'egencia')]")->length > 0) {
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
        $types = 4; // flight + train + hotel + car
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
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
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])) {
                            $new = "";

                            if (isset($tsJ['Seats'])) {
                                $new .= "," . $tsJ['Seats'];
                            }

                            if (isset($tsI['Seats'])) {
                                $new .= "," . $tsI['Seats'];
                            }
                            $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                            $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                            $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
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
        $xpath = "//text()[starts-with(normalize-space(.),'{$this->t('Llegada')}')]/ancestor::tr[1][not(contains(.,'{$this->t('Voiture')}') and contains(.,'{$this->t('Siège')}'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->http->FindSingleNode("./td[2]/div[1]", $root, true, "#{$this->opt($this->t('Esperando aprobación'))}[\s:]+([A-Z\d]+)#");
            $it['TripNumber'] = $this->http->FindSingleNode("./td[2]/div[2]", $root, true, "#{$this->t('Ref. agencia')}[\s\#]+([A-Z\d]+)#");

            if (empty($it['RecordLocator']) && !empty($it['TripNumber'])) {
                $it['RecordLocator'] = $it['TripNumber'];
            }
            $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'{$this->t('Datos del viajero')}')]/following::table[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'d)')]//div[1][count(descendant::div)=0]", null, "#\d+\)\s+(.+)#");
            $it['ReservationDate'] = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./td[2]/div[3]", $root, true, "#{$this->t('Emisión de billetes')}[\s:]+(.+)#")));
            $seg = [];

            if (empty($it['RecordLocator']) && empty($it['TripNumber']) && !empty($this->http->FindSingleNode("./preceding-sibling::tr[1]", $root))) {
                $segLast = $its[count($its) - 1];
                $it['RecordLocator'] = $segLast['RecordLocator'];
                $it['TripNumber'] = $segLast['TripNumber'];
            } else {
                $date = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./preceding::tr[1]/td[last()]", $root)));
            }

            $rowNum = 1;
            $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);

            if (preg_match("#\s*([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $rowNum++;
            $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);

            if (preg_match("#" . $this->t('Operated by') . "\s+(.+)#", $node)) {
                $seg['Operator'] = trim($m[1]);
                $rowNum++;
                $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);
            }

            if (preg_match("#{$this->t('Salida')}\s+(\d+:\d+)\s+(.+)\s+\(([A-Z]{3})\)\s*(?:Terminal\s*(\w+))?$#", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['DepName'] = $m[2];
                $seg['DepCode'] = $m[3];

                if (isset($m[4]) && !empty($m[4])) {
                    $seg['DepartureTerminal'] = $m[4];
                }
            }
            $rowNum++;
            $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);

            if (preg_match("#{$this->t('Llegada')}\s+(\d+:\d+)\s+(.+)\s+\(([A-Z]{3})\)\s*(?:Terminal\s*(\w+))?$#", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $date);
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = $m[3];

                if (isset($m[4]) && !empty($m[4])) {
                    $seg['ArrivalTerminal'] = $m[4];
                }
            }
            $rowNum++;
            $seg['Duration'] = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root, true, "#{$this->t('Duración')}[\s:]+(.+)#");
            $rowNum++;
            $seg['Seats'] = $this->http->FindSingleNode("./td[2]/div[{$rowNum}]", $root, true, "#{$this->t('Seat')}[\s:]+(.+)#");

            $node = $this->http->FindSingleNode("./td[2]/div[4]", $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./td[2]/div[3]", $root);
            }

            if (preg_match("#(.+?)\s*(?:\(([A-Z]{1,2})\))?$#", $node, $m)) {
                $seg['Cabin'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['BookingClass'] = $m[2];
                }
            }

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        $its = $this->mergeItineraries($its);

        if (count($its) == 1) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Vuelo')}')]/following::text()[1]"));

            if (!empty($tot['Total'])) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
        }

        return $its;
    }

    private function parseEmailTrain()
    {
        $its = [];
        $xpath = "//text()[starts-with(normalize-space(.),'{$this->t('Llegada')}')]/ancestor::tr[1][contains(.,'{$this->t('Voiture')}') and contains(.,'{$this->t('Siège')}')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;
            $it['RecordLocator'] = $this->http->FindSingleNode("./td[2]/div[1]", $root, true, "#{$this->opt($this->t('Esperando aprobación'))}[\s:]+([A-Z\d]+)#");
            $it['TripNumber'] = $this->http->FindSingleNode("./td[2]/div[2]", $root, true, "#{$this->t('Ref. agencia')}[\s\#]+([A-Z\d]+)#");

            if (empty($it['RecordLocator']) && !empty($it['TripNumber'])) {
                $it['RecordLocator'] = $it['TripNumber'];
            }
            $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'{$this->t('Datos del viajero')}')]/following::table[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'d)')]//div[1][count(descendant::div)=0]", null, "#\d+\)\s+(.+)#");
            $it['ReservationDate'] = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./td[2]/div[3]", $root, true, "#{$this->t('Emisión de billetes')}[\s:]+(.+)#")));
            $seg = [];

            if (empty($it['RecordLocator']) && empty($it['TripNumber']) && !empty($this->http->FindSingleNode("./preceding-sibling::tr[1]", $root))) {
                $segLast = $its[count($its) - 1];
                $it['RecordLocator'] = $segLast['RecordLocator'];
                $it['TripNumber'] = $segLast['TripNumber'];
            } else {
                $date = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./preceding::tr[1]/td[last()]", $root)));
            }

            $rowNum = 1;
            $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);

            if (preg_match("#(.+?)\s*(\w+)$#", $node, $m)) {
                $seg['Type'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $rowNum++;
            $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);

            if (preg_match("#" . $this->t('Operated by') . "\s+(.+)#", $node)) {
                $seg['Operator'] = trim($m[1]);
                $rowNum++;
                $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);
            }

            if (isset($date) and preg_match("#{$this->t('SalidaTrain')}\s+(\d+:\d+)\s+(.+)\s*$#u", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['DepName'] = $m[2];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }
            $rowNum++;
            $node = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root);

            if (isset($date) and preg_match("#{$this->t('Llegada')}\s+(\d+:\d+)\s+(.+)\s*$#", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $date);
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $rowNum++;
            $seg['Duration'] = $this->http->FindSingleNode("./td[1]/div[{$rowNum}]", $root, true, "#{$this->t('Duración')}[\s:]+(.+)#");
            $rowNum++;
            $seg['Seats'] = $this->http->FindSingleNode("./td[2]/div[{$rowNum}]", $root, true, "#{$this->t('Seat')}[\s:]+(.+)#");

            $node = $this->http->FindSingleNode("./td[2]/div[4]", $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./td[2]/div[3]", $root);
            }

            if (preg_match("#(.+?)\s*(?:\(([A-Z]{1,2})\))?$#", $node, $m)) {
                $seg['Cabin'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['BookingClass'] = $m[2];
                }
            }
            $wg = $this->http->FindSingleNode("./td[2]/descendant::text()[contains(.,'{$this->t('Voiture')}')]", $root,
                false, "#: (.+)#");
            $pl = $this->http->FindSingleNode("./td[2]/descendant::text()[contains(.,'{$this->t('Siège')}')]", $root,
                false, "#: (.+)#");
            $seg['Seats'][] = $wg . '-' . $pl;
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        $its = $this->mergeItineraries($its);

        if (count($its) == 1) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Vuelo')}')]/following::text()[1]"));

            if (!empty($tot['Total'])) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
        }

        return $its;
    }

    private function parseEmailHotel()
    {
        $its = [];
        $xpath = "//text()[starts-with(normalize-space(.),'{$this->t('SalidaHotel')}')]/ancestor::tr[1][not(contains(.,'{$this->t('Llegada')}'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            $it['HotelName'] = $this->http->FindSingleNode("./preceding::tr[1]/td[1]", $root);
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./td[2]/div[1]", $root, true, "#{$this->opt($this->t('Esperando aprobación'))}[\s:]+([A-Z\d]+)#");
            $it['Address'] = $this->http->FindSingleNode("./td[1]/div[1]", $root);
            $it['CheckInDate'] = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./preceding::tr[1]/td[last()]", $root)));
            $it['CheckOutDate'] = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./td[1]/div[2]", $root, true, "#{$this->t('SalidaHotel')}\s+(.+)#")));
            $it['TripNumber'] = $this->http->FindSingleNode("./td[2]/div[2]", $root, true, "#{$this->t('Ref. agencia')}[\s\#]+([A-Z\d]+)#");
            $it['Guests'] = $this->http->FindSingleNode("./td[2]/div[3]", $root, true, "#[\s:]+(\d+)#");
            $it['Rooms'] = $this->http->FindSingleNode("./td[2]/div[4]", $root, true, "#[\s:]+(\d+)#");
            $node = $this->http->FindSingleNode("./td[1]/div[3]", $root);

            if (strlen(trim($node)) < 20 && strlen(preg_replace("#([^\d]+)#", '', $node)) > 7) {
                $it['Phone'] = $node;
                $it['RoomType'] = $this->http->FindSingleNode("./td[1]/div[4]", $root);
            } else {
                $it['RoomType'] = $this->http->FindSingleNode("./td[1]/div[3]", $root);
            }

            $it['GuestNames'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'{$this->t('Datos del viajero')}')]/following::table[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'d)')]//div[1][count(descendant::div)=0]", null, "#\d+\)\s+(.+)#");

            $its[] = $it;

            if (count($its) == 1) {
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Hotel')}')]/following::text()[1]"));

                if (!empty($tot['Total'])) {
                    $its[0]['Total'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
            }
        }

        return $its;
    }

    private function parseEmailCar()
    {
        $its = [];
        $xpath = "//text()[starts-with(normalize-space(.),'{$this->t('Recogida el')}')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'L'];
            $it['Number'] = $this->http->FindSingleNode("./td[2]/div[1]", $root, true, "#{$this->opt($this->t('Esperando aprobación'))}[\s:]+([A-Z\d]+)#");
            $it['TripNumber'] = $this->http->FindSingleNode("./td[2]/div[2]", $root, true, "#{$this->t('Ref. agencia')}[\s\#]+([A-Z\d]+)#");
            $it['CarType'] = $this->http->FindSingleNode("./td[2]/div[3]", $root);
            $pax = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'{$this->t('Datos del viajero')}')]/following::table[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'d)')]//div[1][count(descendant::div)=0]", null, "#\d+\)\s+(.+)#");

            if (count($pax) > 0) {
                $it['RenterName'] = array_shift($pax);
            }
            $date = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./preceding::tr[1]/td[last()]", $root)));
            $it['RentalCompany'] = $this->http->FindSingleNode("./td[1]/div[1]", $root);
            $it['PickupLocation'] = $this->http->FindSingleNode("./td[1]/div[3]", $root);
            $node = $this->http->FindSingleNode("./td[1]/div[contains(.,'{$this->t('Recogida el')}')]", $root);
            $it['PickupDatetime'] = strtotime($this->re("#{$this->t('Recogida el')}\s*:?\s+(\d+:\d+)#", $node), $date);
            $it['DropoffLocation'] = $it['PickupLocation'];
            $node = $this->http->FindSingleNode("./td[1]/div[contains(.,'{$this->t('Entrega el')}')]", $root);
            $it['DropoffDatetime'] = strtotime($this->re("#{$this->t('Entrega el')}\s+(\d+:\d{2})#", $node), strtotime($this->dateStringToEnglish($this->re("#{$this->t('Entrega el')}\s+\d+:\d{2}\s*(\d+\s+\w+\s+\d+)#", $node))));

            $its[] = $it;
        }

        return $its;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
