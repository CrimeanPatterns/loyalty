<?php

namespace AwardWallet\Engine\azul\Email;

class AzulViagensReserva extends \TAccountChecker
{
    public $mailFiles = "azul/it-12337406.eml, azul/it-12337492.eml, azul/it-12337990.eml, azul/it-12338019.eml, azul/it-12338151.eml, azul/it-12338160.eml, azul/it-12338207.eml, azul/it-12338212.eml, azul/it-12338301.eml, azul/it-12338308.eml, azul/it-12338517.eml, azul/it-12338530.eml, azul/it-12338532.eml, azul/it-12338656.eml, azul/it-12338812.eml, azul/it-12338818.eml, azul/it-424187602.eml";

    public $reFrom = ["voeazul.com", "zeostravel.com"];
    public $reBody = [
        'pt' => ['Contato', 'Código da Reserva'],
    ];
    public $reBodyPdf = [
        'pt'  => ['Referência da reserva', 'Thanks for using our services'],
        'pt2' => ['Referência da reserva', 'Obrigado por utilizar nossos serviços'],
    ];
    public $reSubject = [
        'Comprovante de Reserva',
        'Azul Viagens: Reserva',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [
            'CancelledStatus'                                  => ['Cancelada'],
            'Status:'                                          => ['Status:', 'Status da Reserva:'],
            'Referência da reserva / Booking Ref'              => ['Referência da reserva / Booking Ref', 'Referência da reserva'],
            'Estado da reserva / Booking status'               => ['Estado da reserva / Booking status', 'Estado da reserva'],
            'Data / Date'                                      => ['Data / Date', 'Data'],
            'Voo / Flight'                                     => ['Voo / Flight', 'Voo'],
            'Número do vôo / Flight Number'                    => ['Número do vôo / Flight Number', 'Número do vôo'],
            'Saída / Departure'                                => ['Saída / Departure', 'Saída'],
            'Chegada / Arrival'                                => ['Chegada / Arrival', 'Chegada'],
            'VOUCHER DE ALUGUEL DE CARRO / VOUCHER RENT A CAR' => [
                'VOUCHER DE ALUGUEL DE CARRO / VOUCHER RENT A CAR',
                'VOUCHER DE ALUGUEL DE CARRO',
            ],
            'Localizador externo / External reference' => [
                'Localizador externo / External reference',
                'Localizador externo',
            ],
            'Nome do motorista / Driver name'          => ['Nome do motorista / Driver name', 'Nome do motorista'],
            // 'Nome:'          => [''],
            'Localização de partida / Pickup location' => [
                'Localização de partida / Pickup location',
                'Localização de partida',
            ],
            'Data da partida / Pickup date'     => ['Data da partida / Pickup date', 'Data da partida'],
            'Devolução / Drop Off Location'     => ['Devolução / Drop Off Location', 'Devolução'],
            'Data de devolução / Drop Off date' => ['Data de devolução / Drop Off date', 'Data de devolução'],
            'Telefone / Phone'                  => ['Telefone / Phone', 'Telefone'],
            'Código do Hotel'                   => ['Código do Hotel', 'Identificação'],
            'Politica de Cancelamento'          => ['Politica de Cancelamento', 'Política de Cancelamento'],
        ],
    ];
    public $pdfNamePattern = ".*voucher.*pdf";
    private $tripNumber;
    private $statusTrip;
    private $reservDate;
    private static $types = 8;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $type = 'Pdf';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $text = str_ireplace(['&shy;', '&173;', '­'], ' ', $text); // shy
                    $this->assignLang($text);
                    $its = $this->parseEmailPdf($text);
                } else {
                    return null;
                }
            }
        }

        if (empty($its)) {
            $this->assignLang();
            $its = $this->parseEmail();
            $type = 'Html';
        }
        $its = array_values($this->mergeItineraries($its));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total da compra'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]"));

        if (!empty($tot['Total'])) {
            $totalCharge = [
                'Amount'   => $tot['Total'],
                'Currency' => $tot['Currency'],
            ];
        }

        $a = explode('\\', __CLASS__);

        if (isset($totalCharge)) {
            $result = [
                'parsedData' => ['Itineraries' => $its, 'TotalCharge' => $totalCharge],
                'emailType'  => $a[count($a) - 1] . $type . ucfirst($this->lang),
            ];
        } else {
            $result = [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => $a[count($a) - 1] . $type . ucfirst($this->lang),
            ];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if ($this->assignLang($text)) {
                return true;
            }
        }

        if ($this->http->XPath->query("//img[@alt='Azul' or contains(@src,'azulviagens.com')] | //a[contains(@href,'azulviagens.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flagFrom = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flagFrom = true;
                }
            }
        }

        if ($flagFrom && isset($this->reSubject)) {
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
        $cnt = self::$types * count(self::$dict);

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

        if ($this->lang === 'pt') {
            if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
                $monthNameOriginal = $m[0];

                if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, 'es')) {
                    return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
                }
            }
        }

        return $date;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
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

    protected function mergeItineraries($its, $sumTotal = false)//only for transfer in this parser
    {
        $delSums = false;
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if (($it['Kind'] === 'T') && ($it['TripCategory'] === TRIP_CATEGORY_TRANSFER)) {
                if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                    foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                        foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                            if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                                && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                            ) {
                                $new = [];

                                if (isset($tsJ['Seats'])) {
                                    $new = array_merge($new, (array) $tsJ['Seats']);
                                }

                                if (isset($tsI['Seats'])) {
                                    $new = array_merge($new, (array) $tsI['Seats']);
                                }
                                $its[$j]['TripSegments'][$flJ]['Seats'] = array_values(array_filter(array_unique($new)));
                                $its[$i]['TripSegments'][$flI]['Seats'] = array_values(array_filter(array_unique($new)));
                            }
                        }
                    }

                    $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                    $its[$j]['TripSegments'] = array_map('unserialize',
                        array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                    $mergeFields = ['Passengers', 'AccountNumbers', 'TicketNumbers'];

                    foreach ($mergeFields as $mergeField) {
                        if (isset($its[$j][$mergeField]) || isset($its[$i][$mergeField])) {
                            $new = [];

                            if (isset($its[$j][$mergeField])) {
                                $new = array_merge($new, $its[$j][$mergeField]);
                            }

                            if (isset($its[$i][$mergeField])) {
                                $new = array_merge($new, $its[$i][$mergeField]);
                            }
                            $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                            $its[$j][$mergeField] = $new;
                        }
                    }

                    if ($sumTotal) {
                        $sumFields = ['TotalCharge', 'BaseFare', 'Tax'];

                        foreach ($sumFields as $sumField) {
                            if ($sumTotal && (isset($its[$j][$sumField]) || isset($its[$i][$sumField]))) {
                                if (isset($its[$j]['Currency'], $its[$i]['Currency']) && !empty($its[$j]['Currency']) && !empty($its[$i]['Currency']) && $its[$j]['Currency'] !== $its[$i]['Currency']) {
                                    $delSums = true;
                                } else {
                                    $new = 0.0;

                                    if (isset($its[$j][$sumField])) {
                                        $new += $its[$j][$sumField];
                                    }

                                    if (isset($its[$i][$sumField])) {
                                        $new += $its[$i][$sumField];
                                    }
                                    $its[$j][$sumField] = $new;
                                }
                            }
                        }
                    }
                    unset($its[$i]);
                }
            }
        }

        if ($delSums) {
            $its2 = $its;

            foreach ($its2 as $i => $it) {
                $delElements = ['TotalCharge', 'BaseFare', 'Tax', 'Currency'];

                foreach ($delElements as $delElement) {
                    if (isset($it[$delElement])) {
                        unset($its[$i][$delElement]);
                    }
                }
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if (($it['Kind'] === 'T') && ($it['TripCategory'] === TRIP_CATEGORY_TRANSFER)) {
                if ($g_i != $i && $it['RecordLocator'] === $rl) {
                    return $i;
                }
            }
        }

        return -1;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseAir($xpath)
    {
        $its = [];

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'Localizador')]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#([A-Z\d\-]{5,})#");
            $it['TripNumber'] = $this->tripNumber;
            $it['Status'] = $this->statusTrip;

            if (preg_match("#^\s*{$this->opt($this->t('CancelledStatus'))}\s*$#", $this->statusTrip)) {
                $it['Cancelled'] = true;
            }
            $it['TripCategory'] = TRIP_CATEGORY_AIR;
            $paxRoots = $this->http->XPath->query("./descendant::text()[contains(.,'Passageiros:')]/ancestor::td[1]/descendant::table[1]//tr",
                $root);

            foreach ($paxRoots as $paxRoot) {
                $it['Passengers'][] = $this->http->FindSingleNode("./td[1]",
                        $paxRoot) . ' ' . $this->http->FindSingleNode("./td[2]", $paxRoot);
            }
            $airlineName = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root, true,
                "#{$this->opt($this->t('Aéreo:'))}\s*(.+)#");

            $segRoots = $this->http->XPath->query("./descendant::tr[contains(.,':') and count(./td)=5]", $root);

            if (($segRoots->length === 0)
                && ($this->http->XPath->query("./descendant::text()[starts-with(normalize-space(.),'Ida:')]")->length > 0
                    || $this->http->XPath->query("./descendant::text()[starts-with(normalize-space(.),'Data da ida:')]")->length > 0)
            ) {//12338532.eml
                return []; //no flight yet
            } else {
                foreach ($segRoots as $segRoot) {
                    /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                    $seg = [];
                    $node = $this->http->FindSingleNode("./td[1]", $segRoot);

                    if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                        $seg['DepName'] = $m[1];
                        $seg['DepCode'] = $m[2];
                    }
                    $node = $this->http->FindSingleNode("./td[2]", $segRoot);

                    if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                        $seg['ArrName'] = $m[1];
                        $seg['ArrCode'] = $m[2];
                    }
                    $node = $this->http->FindSingleNode("./td[3]", $segRoot);

                    if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    }
                    $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("./td[4]", $segRoot));
                    $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("./td[5]", $segRoot));

                    $it['TripSegments'][] = $seg;
                }
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseTransfer($xpath)
    {
        $its = [];

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->tripNumber;
            $it['Status'] = $this->statusTrip;

            if (preg_match("#^\s*{$this->opt($this->t('CancelledStatus'))}\s*$#", $this->statusTrip)) {
                $it['Cancelled'] = true;
            }
            $it['Passengers'] = $this->http->FindNodes("./descendant::text()[starts-with(normalize-space(.),'Clientes')]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                $root, "#^(?:.+:)?\s*(.+)#");
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['Type'] = implode(' ',
                $this->http->FindNodes("./descendant::text()[starts-with(normalize-space(.),'Serviço')]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                    $root));
            $date = $this->normalizeDate(implode(' ',
                $this->http->FindNodes("./descendant::text()[starts-with(normalize-space(.),'Data')]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                    $root)));

            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[starts-with(normalize-space(.),'Origem')]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                    $root));
            $this->http->Log($node);

            if (preg_match("#(.+)\n(.+)\n\s*(?:Arrival flight time|Outbound hour)[\s:]+(\d+:\d+)#", $node, $m)) {
                $seg['DepName'] = $m[1];

                if (!preg_match("#^\s*[A-Z\d]{2}\s*\d+\s*$#", $m[2])) {
                    $seg['DepName'] .= ' ' . trim($m[2]);
                }
                $seg['DepDate'] = strtotime($m[3], $date);
            } else {
                $seg['DepCode'] = $this->re("#Traslado de ([A-Z]{3})#", $seg['Type']);
            }
            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[starts-with(normalize-space(.),'Localização')]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                    $root));

            if (preg_match("#(.+)\n(.+)\n\s*(?:Inbound approximate hour)[\s:]+(\d+:\d+)#", $node, $m)) {
                $seg['ArrName'] = $m[1] . ' ' . trim($m[2]);
                $seg['ArrDate'] = strtotime($m[3], $date);

                if (isset($seg['DepDate']) && $seg['ArrDate'] < $seg['DepDate']) {
                    $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
                } elseif (!isset($seg['DepDate'])) {//12338151.eml
                    $seg['DepDate'] = MISSING_DATE;
                }
            } else {//12338818.eml
                $seg['ArrName'] = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'Localização')]/ancestor::tr[1]/preceding-sibling::tr[1]/descendant::text()[contains(.,'Hotel:')]/following::text()[normalize-space(.)!=''][1]",
                    $root);
                $seg['ArrDate'] = MISSING_DATE;
            }
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return $its;
    }

    private function parseHotel($xpath)
    {
        $its = [];

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\Hotel $it */
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Código do Hotel'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#([A-Z\d\-]{5,})#");

            if (empty($it['ConfirmationNumber'])) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }
            $it['TripNumber'] = $this->tripNumber;
            $it['Status'] = $this->statusTrip;

            if (preg_match("#^\s*{$this->opt($this->t('CancelledStatus'))}\s*$#", $this->statusTrip)) {
                if ($it['ConfirmationNumber'] === CONFNO_UNKNOWN && !empty($it['TripNumber'])) {
                    $it['ConfirmationNumber'] = $it['TripNumber'];
                }
                $it['Cancelled'] = true;
            }
            $it['GuestNames'] = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Descrição'))}]/ancestor::td[1]//text()[starts-with(normalize-space(.),'*')]",
                $root, "#\*\s*(.+?)\s*(?:\(|$)#");
            $it['Guests'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Adultos'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#(\d+)#");
            $it['Kids'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Crianças'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#(\d+)#");
            $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check-In'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
            $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check-Out'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
            $it['HotelName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root,
                true, "#{$this->opt($this->t('Hotel:'))}\s+(.+)#");
            $it['Address'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Endereço'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['Phone'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Telefone'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#([\d\-\+\(\) ]+)#"));
            $it['CancellationPolicy'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Politica de Cancelamento'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $its[] = $it;
        }

        return $its;
    }

    private function parseCar($xpath)
    {
        $its = [];

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\CarRental $it */
            $it = ['Kind' => 'L'];
            $it['Number'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Identificação'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#([A-Z\d\-]{5,})#");
            $it['TripNumber'] = $this->tripNumber;
            $it['Status'] = $this->statusTrip;

            if (preg_match("#^\s*{$this->opt($this->t('CancelledStatus'))}\s*$#", $this->statusTrip)) {
                $it['Cancelled'] = true;
            }
            $it['RenterName'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Condutor'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['CarModel'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Veiculo'))}]/following::text()[normalize-space(.)!=''][1]",
                $root), " -");
            $it['RentalCompany'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Veiculo'))}]/following::text()[normalize-space(.)!=''][2]",
                $root), " -");
            $it['CarType'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Tipo do veiculo'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['PickupLocation'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Local de retirada'))}]/following::text()[normalize-space(.)!=''][position()<8][{$this->starts('Endereço')}][1]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['PickupPhone'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Local de retirada'))}]/following::text()[normalize-space(.)!=''][position()<8][{$this->starts('Telefone')}][1]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#([\d \-\+\(\)]{5,})#");
            $it['PickupDatetime'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Local de retirada'))}]/following::text()[normalize-space(.)!=''][position()<8][{$this->starts('Horario')}][1]/following::text()[normalize-space(.)!=''][1]",
                $root));
            $it['DropoffLocation'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts('Local de devolução')}]/following::text()[normalize-space(.)!=''][position()<8][{$this->starts('Endereço')}][1]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['DropoffPhone'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts('Local de devolução')}]/following::text()[normalize-space(.)!=''][position()<8][{$this->starts('Telefone')}][1]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#([\d \-\+\(\)]{5,})#");
            $it['DropoffDatetime'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts('Local de devolução')}]/following::text()[normalize-space(.)!=''][position()<8][{$this->starts('Horario')}][1]/following::text()[normalize-space(.)!=''][1]",
                $root));
            //calculate by check-out date
            if (empty($it['DropoffDatetime'])) {
                $it['DropoffDatetime'] = $this->normalizeDate($this->http->FindSingleNode("./preceding::br[1]/preceding-sibling::*[normalize-space(.)!=''][1]/descendant::text()[{$this->starts($this->t('Check-Out'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root));
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmail()
    {
        $its = [];
        $this->tripNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Código da Reserva Azul Viagens'))}]/following::text()[normalize-space(.)!=''][1]");
        $this->statusTrip = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Status:'))}]", null,
            true, "#:\s*(.+)#");

        $xpath = "//text()[starts-with(normalize-space(.),'Transfer:')]/ancestor::table[contains(.,'Clientes')][1]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $its = array_merge($its, $this->parseTransfer($xpath));
        }

        $xpath = "//text()[starts-with(normalize-space(.),'Hotel:') and not(normalize-space(.)='Hotel:')]/ancestor::table[{$this->contains($this->t('Código do Hotel'))}][1]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $its = array_merge($its, $this->parseHotel($xpath));
        }

        $xpath = "//text()[starts-with(normalize-space(.),'Aéreo:')]/ancestor::table[contains(.,'Localizador')][1]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $its = array_merge($its, $this->parseAir($xpath));
        }
        $xpath = "//text()[normalize-space(.)='Condutor:']/ancestor::*[contains(.,'Veiculo')][1]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $its = array_merge($its, $this->parseCar($xpath));
        }

        return $its;
    }

    private function parseAirPdf($text)
    {
        $its = [];

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Referência da reserva / Booking Ref'))}[\.:\s]+([A-Z\d\-]{5,})#",
            $text);
        $it['TripNumber'] = $this->tripNumber;
        $it['Status'] = $this->statusTrip;
        $it['ReservationDate'] = $this->reservDate;

        if (preg_match("#^\s*{$this->opt($this->t('CancelledStatus'))}\s*$#", $this->statusTrip)) {
            $it['Cancelled'] = true;
        }
        $it['TripCategory'] = TRIP_CATEGORY_AIR;

        if (preg_match_all("#^ *{$this->opt($this->t('Pax'))}\s*\d+[:\*\s]+(.+?)\s*(?:\(|$)#m", $text, $m)) {
            $it['Passengers'] = $m[1];
        }

        $sText = $this->findСutSection($text, $this->t('Segmentos'),
            $this->t('Dados do(s) passageiro(s)'));

        $segRoots = $this->splitter("#^({$this->opt($this->t('Voo / Flight'))}:)#m", $sText);

        foreach ($segRoots as $segRoot) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $node = $this->re("#{$this->opt($this->t('Voo / Flight'))}[\s:]+(.+?)\s+{$this->opt($this->t('Cia aérea'))}#s",
                $segRoot);

            if (preg_match("#([A-Z]{3})\s+\((.+?)\)[\-\s]+([A-Z]{3})\s+\((.+?)\)#su", $node, $m)) {
                $seg['DepName'] = trim(preg_replace("#\s+#", ' ', $m[2]));
                $seg['DepCode'] = $m[1];
                $seg['ArrName'] = trim(preg_replace("#\s+#", ' ', $m[4]));
                $seg['ArrCode'] = $m[3];
            }
            $node = $this->re("#{$this->opt($this->t('Número do vôo / Flight Number'))}[\s:]+(.+)#", $segRoot);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['DepDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Saída / Departure'))}[\s:]+(.+)#",
                $segRoot));
            $seg['ArrDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Chegada / Arrival'))}[\s:]+(.+)#",
                $segRoot));

            $it['TripSegments'][] = $seg;
        }
        $its[] = $it;

        return $its;
    }

    private function parseTransferPdf($text)
    {
        $its = [];

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripNumber'] = $this->tripNumber;
        $it['Status'] = $this->statusTrip;

        if (preg_match("#^\s*{$this->opt($this->t('CancelledStatus'))}\s*$#", $this->statusTrip)) {
            $it['Cancelled'] = true;
        }

        if (preg_match_all("#^ *{$this->opt($this->t('Pax'))}\s*\d+[:\*\s]+(.+?)\s*(?:\(|$)#m", $text, $m)) {
            $it['Passengers'] = $m[1];
        }

        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $seg['Type'] = $this->re("#{$this->opt($this->t('VOUCHER DE SERVIÇO'))}\s+(.+)#", $text);
        $date = $this->normalizeDate($this->re("#{$this->opt($this->t('Data do serviço'))}[:\s]+(.+)#u", $text));

        $seg['DepName'] = $this->re("#{$this->opt($this->t('Origem'))}[\s:]+(.+)#", $text);

        if (empty($seg['DepName'])) {//12338517.eml
            return []; // not  enough info
        }

        if (!empty($str = $this->re("#{$this->opt($this->t('Origem'))}[\s:]+.+\n{$this->opt($this->t('Hotel'))}[\s:]+(.+)#",
            $text))
        ) {
            $seg['DepName'] .= ' ' . trim($str);
        }
        $seg['DepDate'] = strtotime($this->re("#(?:{$this->opt($this->t('Tempo estimado de chegada do vôo'))}|{$this->opt($this->t('Horário para buscar o passageiro'))})[\s:]+(.+)#",
            $text), $date);
        $seg['ArrName'] = $this->re("#{$this->opt($this->t('Destino'))}[\s:]+(.+)#",
            $text);

        if (!empty($str = $this->re("#{$this->opt($this->t('Destino'))}[\s:]+.+\n{$this->opt($this->t('Hotel'))}[\s:]+(.+)#",
            $text))
        ) {
            $seg['ArrName'] .= ' ' . trim($str);
        }

        $seg['ArrDate'] = MISSING_DATE;

        $it['TripSegments'][] = $seg;
        $its[] = $it;

        return $its;
    }

    private function parseCarPdf($text)
    {
        $its = [];

        /** @var \AwardWallet\ItineraryArrays\CarRental $it */
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->re("#{$this->opt($this->t('Localizador externo / External reference'))}[:\s]+([A-Z\d\-]{5,})#",
            $text);
        $it['RenterName'] = $this->re("#{$this->opt($this->t('Nome do motorista / Driver name'))}[:\s]+(?:{$this->opt($this->t('Nome:'))} *)?(.+)#", $text);
        $it['TripNumber'] = $this->tripNumber;
        $it['Status'] = $this->statusTrip;
        $it['ReservationDate'] = $this->reservDate;

        $node = $this->re("#{$this->opt($this->t('VOUCHER DE ALUGUEL DE CARRO / VOUCHER RENT A CAR'))}\s+.+\n(.+)#",
            $text);
        $arr = explode('-', $node);

        if (count($arr) === 3) {
            $it['CarType'] = $arr[0] . '-' . $arr[2];
            $it['CarModel'] = trim($arr[1]);
        } else {
            $it['CarType'] = $node;
        }
        $it['RentalCompany'] = $this->re("#{$this->opt($this->t('VOUCHER DE ALUGUEL DE CARRO / VOUCHER RENT A CAR'))}\s+(.+)#",
            $text);

        $it['PickupLocation'] = $this->re("#{$this->opt($this->t('Localização de partida / Pickup location'))}[:\s]+(.+)#",
            $text);
        $it['PickupPhone'] = $this->re("#{$this->opt($this->t('Localização de partida / Pickup location'))}[:\s]+.+\n.*{$this->opt($this->t('Telefone / Phone'))}[:\s]+(([\d \-\+\(\)]{5,})\b)#",
            $text);
        $it['PickupDatetime'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Data da partida / Pickup date'))}[:\s]+(.+)#",
            $text));

        $it['DropoffLocation'] = $this->re("#{$this->opt($this->t('Devolução / Drop Off Location'))}[:\s]+(.+)#",
            $text);
        $it['DropoffPhone'] = $this->re("#{$this->opt($this->t('Devolução / Drop Off Location'))}[:\s]+.+\n.*{$this->opt($this->t('Telefone / Phone'))}[:\s]+(([\d \-\+\(\)]{5,}))\b#",
            $text);
        $it['DropoffDatetime'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Data de devolução / Drop Off date'))}[:\s]+(.+)#",
            $text));

        $its[] = $it;

        return $its;
    }

    private function parseHotelPdf($text)
    {
        $its = [];

        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->re("#{$this->opt($this->t('Localizador externo / External reference'))}[:\s]+([A-Z\d\-]{5,})#",
            $text);
        $it['TripNumber'] = $this->tripNumber;
        $it['Status'] = $this->statusTrip;
        $it['ReservationDate'] = $this->reservDate;

        if (preg_match("#^\s*{$this->opt($this->t('CancelledStatus'))}\s*$#", $this->statusTrip)) {
            $it['Cancelled'] = true;
        }

        if (preg_match("#{$this->opt($this->t('VOUCHER DE HOTEL'))}[^\n]*?\n([^\n]+)\n(.+?)\n(?:Tel[\.: ]*)?([\+\-\d\(\) ]{5,})\n(?:.*\s)?{$this->opt($this->t('Nome do titular'))}#s",
            $text, $m)) {
            $it['HotelName'] = $this->re("#(.+?)\s*(?:\d+\s+{$this->opt($this->t('Estrelas'))}|$)#", $m[1]);
            $it['Address'] = trim(preg_replace("#\s+#", ' ', $m[2]));
            $it['Phone'] = $m[3];
        } elseif (preg_match("#{$this->opt($this->t('VOUCHER DE HOTEL'))}[^\n]*?\n([^\n]+)\n(.+?)\s+{$this->opt($this->t('Nome do titular'))}#s",
            $text, $m)) {
            $it['HotelName'] = $this->re("#(.+?)\s*(?:\d+\s+{$this->opt($this->t('Estrelas'))}|$)#", $m[1]);
            $it['Address'] = trim(preg_replace("#\s+#", ' ', $m[2]));
        }

        if (empty($it['ConfirmationNumber']) && !empty($it['HotelName'])) {//try to find in body
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'')]/ancestor::tr[{$this->contains($this->t('Código do Hotel'))}][1][contains(normalize-space(.),'{$it['HotelName']}')]/descendant::text()[{$this->contains($this->t('Código do Hotel'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, "#([A-Z\d\-]{5,})#");

            if (empty($it['ConfirmationNumber'])) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }
        }

        if (preg_match("#{$this->opt($this->t('Apartamentos'))}[:\s+](.+)\s*\(\s*(\d+)\s*{$this->opt($this->t('Adulto(s) / Adults'))}[\s\+]+(\d+)\s+{$this->opt($this->t('Bebê(s) / Babies'))}\s*\)\s+{$this->opt($this->t('Localizador externo'))}#s",
            $text, $m)) {
            $it['RoomType'] = $m[1];
            $it['Guests'] = $m[2];
            $it['Kids'] = $m[3];

            if (preg_match_all("#^ *\-\s*([^\n]+)#", $m[4], $v)) {
                $it['GuestNames'] = array_map(function ($s) {
                    return trim(preg_replace("#\s+#", ' ', $s));
                }, $v[1]);
            }
        }
        $it['CheckInDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Data de chegada'))}[\s:]+(.+)#",
            $text));
        $it['CheckOutDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Data da saída'))}[\s:]+(.+)#",
            $text));

        $it['CancellationPolicy'] = $this->re("#{$this->opt($this->t('Politica de cancelamento'))}[\s:]+(.+?)(?:{$this->opt($this->t('Otros'))}|\n\n)#s",
            $text);
        $its[] = $it;

        return $its;
    }

    private function parseEmailPdf($textPDF)
    {
        $its = [];
        $this->tripNumber = $this->re("#{$this->opt($this->t('Referência da reserva / Booking Ref'))}[\.:\s]+([A-Z\d\-]{5,})#",
            $textPDF);
        $this->statusTrip = $this->re("#{$this->opt($this->t('Estado da reserva / Booking status'))}[\.:\s]+(.+)#",
            $textPDF);
        $this->reservDate = $this->normalizeDate($this->re("#{$this->opt($this->t('Data / Date'))}[\.:\s]+(.+)#",
            $textPDF));

        $arrs = $this->splitter("#^ *({$this->opt($this->t('Obrigado por utilizar nossos serviços'))})#m", $textPDF);

        foreach ($arrs as $text) {
            if (strpos($text, $this->t('VOUCHER DE VOO')) !== false) {
                $ntext = strstr($text, $this->t('VOUCHER DE VOO'));
                $its = array_merge($its, $this->parseAirPdf($ntext));
            } elseif (strpos($text, $this->t('VOUCHER DE ALUGUEL DE CARRO')) !== false) {
                $ntext = strstr($text, $this->t('VOUCHER DE ALUGUEL DE CARRO'));
                $its = array_merge($its, $this->parseCarPdf($ntext));
            } elseif (strpos($text, $this->t('VOUCHER DE HOTEL')) !== false) {
                $ntext = strstr($text, $this->t('VOUCHER DE HOTEL'));
                $its = array_merge($its, $this->parseHotelPdf($ntext));
            } elseif (mb_strpos($text, $this->t('VOUCHER DE SERVIÇO')) !== false) {
                $ntext = mb_strstr($text, $this->t('VOUCHER DE SERVIÇO'));
                $its = array_merge($its, $this->parseTransferPdf($ntext));
            } elseif (mb_strpos($text, $this->t('VOUCHER DE AJUSTE')) !== false) {//12337406.eml
                continue;
            } else {//specially made broken itinerary
                $its = array_merge($its, [['Kind' => 'R']]);
                $this->http->Log("the parser needs to be modified");
            }
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            //17/12/2017
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*$#',
            //10/DIC./2017 08:50
            '#^\s*(\d+)\/(\D+?)\.?\/(\d+)\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#i',
            //10/12/2017 08:50
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#i',
            //CNF 12/11/2017 - 13:15 h
            '#^\s*(?:[A-Z]{3})?\s*(\d+)\/(\d+)\/(\d+)\s*\-\s*(\d+:\d+(?:\s*[ap]m)?)(?:\s*h)?\s*$#i',
            //03/05/2017 21:14:05
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*(\d+:\d+):\d+((?:\s*[ap]m)?)\s*$#i',
        ];
        $out = [
            '$3-$2-$1',
            '$1 $2 $3 $4',
            '$3-$2-$1, $4',
            '$3-$2-$1, $4',
            '$3-$2-$1, $4 $5',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime(trim($str));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body = '')
    {
        if (empty($body) && isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        } elseif (isset($this->reBodyPdf)) {
            foreach ($this->reBodyPdf as $lang => $reBody) {
                if (mb_stripos($body, $reBody[0]) !== false && mb_stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s*', preg_quote($s));
        }, $field)) . ')';
    }
}
