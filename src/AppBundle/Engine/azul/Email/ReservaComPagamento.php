<?php

namespace AwardWallet\Engine\azul\Email;

class ReservaComPagamento extends \TAccountChecker
{
    public $mailFiles = "azul/it-12165350.eml, azul/it-12264629.eml";

    public $reFrom = ["voeazul.com"];
    public $reBody = [
        'pt' => ['Informações do pacote', 'INFORMAÇÕES DO PASSAGEIRO'],
    ];
    public $reSubject = [
        'Azul Viagens Reserva Pendente de Pagamento',
        'Azul Viagens Reserva com Pagamento',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [
            'Adultos'     => ['Adultos', 'Adulto'],
            'APARTAMENTO' => ['APARTAMENTO', 'Apartamento'],
            'Voo'         => ['Voo', 'VOO'],
            'CARRO'       => ['CARRO', 'Carro'],
        ],
    ];
    private $tripNumber;
    private $reservDate;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pagamentos recebidos'))}]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $result['parsedData']['TotalCharge'] = [
                'Amount'   => $tot['Total'],
                'Currency' => $tot['Currency'],
            ];
        } else {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Saldo devido'))}]/ancestor::td[1]/following-sibling::td[1]"));

            if (!empty($tot['Total'])) {
                $result['parsedData']['TotalCharge'] = [
                    'Amount'   => $tot['Total'],
                    'Currency' => $tot['Currency'],
                ];
            }
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Azul Viagens' or contains(@src,'/azul.')] | //a[contains(@href,'azultravel.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
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
        $types = 3;
        $cnt = count(self::$dict) * $types;

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

    private function parseFlights($roots)
    {
        $its = [];
        $airs = [];

        foreach ($roots as $root) {
            $rl = $this->http->FindSingleNode("./descendant::text()[{$this->starts('Localizador do voo')}]/ancestor::td[1]",
                $root, true, "#{$this->opt($this->t('Localizador do voo'))}[\s:]+([A-Z\d]{5,})#");
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $nodes) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['TripNumber'] = $this->tripNumber;
            $it['ReservationDate'] = $this->reservDate;
            $it['RecordLocator'] = $rl;

            foreach ($nodes as $root) {
                $segRoots = $this->http->XPath->query("./descendant::text()[{$this->starts('Saída')}]/ancestor::tr[1]",
                    $root);
                $seats = [];
                $seatArr = array_map("trim",
                    $this->http->FindNodes("./descendant::tr[contains(.,'(') and contains(.,')') and not({$this->contains($this->t('Saída'))}) and not({$this->contains($this->t('Chegada'))}) and position()>3]",
                        $root, "#\)\s*(\d+[a-zA-Z].*)#"));

                foreach ($seatArr as $p) {
                    $pp = explode(' ', $p);

                    foreach ($pp as $i => $ps) {
                        $seats[$i][] = $ps;
                    }
                }
                $pax = $this->http->FindNodes("./descendant::tr[contains(.,'(') and contains(.,')') and not({$this->contains($this->t('Saída'))}) and not({$this->contains($this->t('Chegada'))}) and position()>3]",
                    $root, "#^(.+?)\s*\(#");
                $it['Passengers'] = array_values(array_filter(array_unique($pax)));

                foreach ($segRoots as $i => $segRoot) {
                    /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                    $seg = [];

                    if (isset($seats[$i])) {
                        $seg['Seats'] = array_values(array_filter(array_unique($seats[$i])));
                    }
                    $node = implode("\n",
                        $this->http->FindNodes("./td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
                            $segRoot));

                    if (preg_match("#(.+)\s+([A-Z\d]{2})\s*(\d+)\s+(.+?)(?:\s+\(\s*([A-Z]{1,2})[\s\*]*\))?(?:\s*\|\s*{$this->opt($this->t('Aeronave'))}[\s:]+(.+))?$#",
                        $node,
                        $m)) {
                        $seg['AirlineName'] = $m[2];
                        $seg['FlightNumber'] = $m[3];
                        $seg['Cabin'] = $m[4];

                        if (isset($m[5]) && !empty($m[5])) {
                            $seg['BookingClass'] = $m[5];
                        }

                        if (isset($m[6]) && !empty($m[6])) {
                            $seg['Aircraft'] = $m[6];
                        }
                    }

                    $node = implode("\n",
                        $this->http->FindNodes("./td[normalize-space(.)!=''][2]//text()[normalize-space(.)!='']",
                            $segRoot));

                    if (preg_match("#(.+?)\s*\-\s*\(\s*([A-Z]{3})\s*\)#", $node,
                        $m)) {
                        $seg['DepName'] = $m[1];
                        $seg['DepCode'] = $m[2];
                    }

                    $node = implode("\n",
                        $this->http->FindNodes("./following-sibling::tr[1]/td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
                            $segRoot));

                    if (preg_match("#(.+?)\s*\-\s*\(\s*([A-Z]{3})\s*\)#", $node,
                        $m)) {
                        $seg['ArrName'] = $m[1];
                        $seg['ArrCode'] = $m[2];
                    }

                    $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][4]//text()[normalize-space(.)!='']",
                        $segRoot));
                    $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)!=''][3]//text()[normalize-space(.)!='']",
                        $segRoot));
                    $seg['Duration'] = $this->http->FindSingleNode("./following-sibling::tr[2]/descendant::text()[{$this->starts('Duração')}]/following::text()[normalize-space(.)!=''][1]",
                        $segRoot);

                    $it['TripSegments'][] = $seg;
                }
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseHotels($roots)
    {
        $its = [];

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\Hotel $it */
            $it = ['Kind' => 'R'];

            $it['TripNumber'] = $this->tripNumber;
            $it['ReservationDate'] = $this->reservDate;
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmação'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);

            if ($this->http->XPath->query("./descendant::text()[normalize-space(.)!=''][2][not({$this->starts($this->t('Confirmação'))})]",
                    $root)->length > 0
            ) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
                $num = 1;
            } else {
                $num = 2;
            }
            $it['HotelName'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[normalize-space(.)!=''][1]",
                $root);
            $node = implode("\n",
                $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[normalize-space(.)!=''][1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!='']",
                    $root));

            if (preg_match("#^(.+?)\s*(?:([\d\-\+\(\) ]{5,})\s*$|$)#s", $node, $m)) {
                $it['Address'] = trim(preg_replace("#\s+#", ' ', $m[1]));

                if (isset($m[2]) && !empty($m[2])) {
                    $it['Phone'] = $m[2];
                }
            }
            $it['Guests'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Ocupantes'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#(\d+)\s+{$this->opt($this->t('Adultos'))}#");
            $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Check-in'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
            $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Check-out'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
            $it['RoomType'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Descrição do Apartamento'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['RoomTypeDescription'] = trim($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Entretenimento'))}]/following::text()[normalize-space(.)!=''][1]",
                $root), " :");

            if (empty($it['RoomTypeDescription'])) {
                $it['RoomTypeDescription'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Tipo de apartamento'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root);
            }
            //otherwise (without class) do not guarantee that will parse the name
            $firstNames = $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::*[@class='traveler_first_name']",
                $root);
            $lastNames = $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::*[@class='traveler_last_name']",
                $root);

            if ((count($firstNames) === count($lastNames)) && (count($firstNames) > 0)) {
                foreach ($firstNames as $i => $firstName) {
                    $it['GuestNames'][] = $firstName . ' ' . $lastNames[$i];
                }
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseCars($roots)
    {
        $its = [];

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\CarRental $it */
            $it = ['Kind' => 'L'];
            $it['TripNumber'] = $this->tripNumber;
            $it['ReservationDate'] = $this->reservDate;
            $it['Number'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmação'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);

            if ($this->http->XPath->query("./descendant::text()[normalize-space(.)!=''][2][not({$this->starts($this->t('Confirmação'))})]",
                    $root)->length > 0
            ) {
                $it['Number'] = CONFNO_UNKNOWN;
                $num = 1;
            } else {
                $num = 2;
            }
            $it['RentalCompany'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[normalize-space(.)!=''][1]",
                $root);
            $it['PickupLocation'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Local de retirada'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['DropoffLocation'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Local de devolução'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $it['CarType'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Tipo de carro'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);

            if (empty($it['DropoffLocation'])) {
                $it['DropoffLocation'] = $it['PickupLocation'];
            }
            $it['PickupDatetime'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Retirada'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
            $it['DropoffDatetime'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[{$num}]/descendant::text()[{$this->starts($this->t('Devolução'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmail()
    {
        $node = $this->http->XPath->query("//text()[{$this->eq($this->t('Informações do pacote'))}]/ancestor::table[{$this->contains($this->t('Número'))}][1]");

        if ($node->length > 0) {
            $this->tripNumber = $this->http->FindSingleNode("./descendant::tr[{$this->contains($this->t('Número'))}]",
                $node->item(0), true, "#{$this->opt($this->t('Número'))}\s+([\dA-Z]+)#");
            $this->reservDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[{$this->contains($this->t('Data'))}]",
                $node->item(0), true, "#{$this->opt($this->t('Data'))}\s+(.+)#"));
        }
        $its = [];
        $xpath = "//text()[{$this->eq($this->t('Voo'))}]/ancestor::table[{$this->contains($this->t('Saída'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $its = array_merge($its, $this->parseFlights($nodes));
        }

        $xpath = "//text()[{$this->eq($this->t('APARTAMENTO'))}]/ancestor::table[{$this->contains($this->t('Check-in'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $its = array_merge($its, $this->parseHotels($nodes));
        }

        $xpath = "//text()[{$this->eq($this->t('CARRO'))}]/ancestor::table[{$this->contains($this->t('Retirada'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $its = array_merge($its, $this->parseCars($nodes));
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Terça-feira 15/11/16 12:00
            '#^\s*[\w\-]+\s+(\d+)\/(\d+)\/(\d{2})\s+(\d+:\d+)\s*$#u',
            //Terça-feira 15/11/16
            '#^\s*[\w\-]+\s+(\d+)\/(\d+)\/(\d{2})\s*$#u',
        ];
        $out = [
            '20$3-$2-$1, $4',
            '20$3-$2-$1',
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

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                $upOne = mb_strtoupper($reBody[0]);
                $lowOne = mb_strtolower($reBody[0]);
                $upTwo = mb_strtoupper($reBody[1]);
                $lowTwo = mb_strtolower($reBody[1]);

                if ($this->http->XPath->query("//*[contains(translate(normalize-space(.),'{$upOne}','{$lowOne}'),'{$lowOne}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(translate(normalize-space(.),'{$upTwo}','{$lowTwo}'),'{$lowTwo}')]")->length > 0
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
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("R$", "BRL", $node);
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

        return ['Total' => (float) $tot, 'Currency' => $cur];
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
