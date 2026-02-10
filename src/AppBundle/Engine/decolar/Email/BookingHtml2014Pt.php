<?php

namespace AwardWallet\Engine\decolar\Email;

class BookingHtml2014Pt extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "decolar/it-11334498.eml, decolar/it-12640652.eml, decolar/it-1805383.eml, decolar/it-4011837.eml, decolar/it-4019522.eml, decolar/it-4310659.eml, decolar/it-4333183.eml, decolar/it-5048732.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parseReservations();

        $totalCharge = [];

        if ($total = $this->http->FindSingleNode("//*[contains(text(), 'TOTAL') or contains(text(), 'Total')]/ancestor::td[1]/following-sibling::td[2]")) {
            $totalCharge['Amount'] = $this->correctSum(preg_replace('/[^\d,.]/', '', $total));
            $totalCharge['Currency'] = preg_replace(['/R\$.+/'], ['BRL'], $total);
        }

        return [
            'emailType'  => 'BookingHotelAirFormatPt',
            'parsedData' => ['Itineraries' => $this->result, 'TotalCharge' => $totalCharge],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'Vendas@decolar.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Decolar.com - Pedido de compra de vôo - Número:') !== false
                || stripos($headers['subject'], 'Obrigado por sua solicitação de compra em Decolar.com!') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'obrigado por escolher') !== false
                || strpos($parser->getHTMLBody(), 'obrigado por nos escolher') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@decolar.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    protected function parseReservations()
    {
        // Air Trip
        if ($this->http->FindNodes("//*[normalize-space(text())='Voo']")) {
            $a = [];
            $a['Kind'] = 'T';
            $a['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Seu número de solicitação de compra é:")]', null, false, '/:\s*([A-Z\d]+)$/');
            $a['Passengers'] = $this->array_concat(
                $this->http->FindNodes('//*[contains(text(), "Nome:")]/following-sibling::text()[1]'),
                $this->http->FindNodes('//*[contains(text(), "Sobrenome:")]/following-sibling::text()[1]')
            );

            foreach ($this->http->XPath->query('//text()[contains(., "Voo:")]/ancestor::td[2]') as $current) {
                $a['TripSegments'][] = $this->parseSegment(implode(' ', $this->http->FindNodes(".//text()", $current)));
            }
            $this->result[] = $a;
        }

        // Hotel
        if ($this->http->FindNodes("//*[normalize-space(text())='Hotel']")) {
            $this->result[] = $this->parseHotel();
        }

        // Cruise
        if ($this->http->FindNodes("//*[normalize-space(text())='Cruzeiro']")) {
            $this->result[] = $this->parseCruise();
        }
    }

    protected function parseHotel()
    {
        $h['Kind'] = 'R';
        $h['ConfirmationNumber'] = $this->http->FindSingleNode('//*[contains(text(), "Seu número de solicitação de compra é:")]', null, false, '/:\s*([A-Z\d]+)$/');
        $h['HotelName'] = $this->http->FindSingleNode("//img[contains(@src, 'icon-star.jpg')]/preceding::text()[normalize-space(.)][1]");

        // Date
        $h['CheckInDate'] = strtotime($this->dateStringToEnglish(str_replace('-', ',', $this->http->FindSingleNode('//text()[contains(., "• Entrada")]/ancestor::table[2]/following-sibling::table[1]', null, false, '/(\d+ \w+ \d+\s*([,-]\s*\d+:\d+)?)/'))));
        $h['CheckOutDate'] = strtotime($this->dateStringToEnglish(str_replace('-', ',', $this->http->FindSingleNode('//text()[contains(., "• Saída")]/ancestor::table[2]/following-sibling::table[1]', null, false, '/(\d+ \w+ \d+\s*([,-]\s*\d+:\d+)?)/'))));

        $h['Address'] = $this->http->FindSingleNode("//img[contains(@src, 'icon-star.jpg')]/following::text()[normalize-space(.)][1]");

        $h['GuestNames'] = array_map(function ($s) {
            return preg_replace("#^Nome:\s+(.*?)\s+Sobrenome:\s+(.+)$#", "$1 $2", $s);
        }, $this->http->FindNodes("//text()[normalize-space(.)='Nome:']/ancestor::ul[1]"));

        $h['Guests'] = array_sum($this->http->FindNodes('//text()[contains(., "• Hóspedes")]/ancestor::table[2]/following-sibling::table[1]', null, '/(\d+)\s+adulto/'));
        $h['Kids'] = array_sum($this->http->FindNodes('//text()[contains(., "• Hóspedes")]/ancestor::table[2]/following-sibling::table[1]', null, '/(\d+)\s+criança/'));
        $h['CancellationPolicy'] = $this->http->FindSingleNode('//text()[contains(., "ancelamento")]/ancestor::table[2]/following-sibling::table[1]');

        if (empty($h['CancellationPolicy'])) {
            $h['CancellationPolicy'] = $this->http->FindSingleNode('//text()[contains(., "Política de cancelamento:")]');
        }

        $h['RoomType'] = join(', ', array_unique($this->http->FindNodes('//text()[contains(., "• Tipo")]/ancestor::table[2]/following-sibling::table[1]')));

        if ($cost = $this->http->FindSingleNode("//text()[contains(., 'Hotel') and contains(., 'quarto')]/following::text()[1]")) {
            $h['Cost'] = (float) preg_replace('/[^\d,.]/', '', $cost);
        }

        if ($tax = $this->http->FindSingleNode("//text()[contains(., 'Hotel') and contains(., 'impostos e taxas')]/following::text()[1]")) {
            $h['Taxes'] = (float) preg_replace('/[^\d,.]/', '', $tax);
        }

        // 4 noites - 2 quartos
        $h['Rooms'] = $this->http->FindSingleNode('//*[contains(text(), "noites") and contains(text(), "quartos")]', null, false, '/(\d+) quartos/');

        return $h;
    }

    protected function parseCruise()
    {
        $it['Kind'] = 'T';
        $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode('//*[contains(normalize-space(text()),"Seu número de solicitação de compra é:")]', null, true, '/:\s*([A-Z\d]{5,})$/');

        if ($it['TripNumber']) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // Passengers
        // AccountNumbers
        $passengers = [];
        $accountNumbers = [];

        $xpathFragment1 = '//tr[normalize-space(.)="Passageiros"]';

        $passengerRows = $this->http->XPath->query($xpathFragment1 . '/following-sibling::tr[starts-with(normalize-space(.),"Passageiro ")]');

        foreach ($passengerRows as $passengerRow) {
            $passengers[] = $this->http->FindSingleNode('./descendant::ul[last()]/li[1]', $passengerRow);
            $accountNumbers[] = $this->http->FindSingleNode('./descendant::ul[last()]/li[2]', $passengerRow, true, '/^([-A-z\d]*\d{4,}[-A-z\d]*)$/'); // FJ461918
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = $passengers;
        }

        if (!empty($accountNumbers[0])) {
            $it['AccountNumbers'] = $accountNumbers;
        }

        $xpathFragment2 = '//tr[normalize-space(.)="Cruzeiro"]';

        // ShipName
        $it['ShipName'] = $this->http->FindSingleNode($xpathFragment2 . '/following::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]', null, true, '/^([^•]{2,})$/');

        $dateArr = $dateDep = 0;
        $departure = $this->http->FindSingleNode($xpathFragment2 . '/following::tr[contains(normalize-space(.),"Saída")][1]/descendant::td[normalize-space(.)][last()]');

        if ($departure) {
            $dateDep = strtotime($this->normalizeDate($departure));
        }

        if ($dateDep) {
            $duration = $this->http->FindSingleNode($xpathFragment2 . '/following::tr[contains(normalize-space(.),"Duração")][1]/descendant::td[normalize-space(.)][last()]', null, true, '/^(\d+)\s*noites/i');

            if ($duration) {
                $dateArr = strtotime("+$duration days", $dateDep);
            }
        }

        $itinerary = $this->http->FindSingleNode($xpathFragment2 . '/following::tr[contains(normalize-space(.),"Itinerário")][1]/descendant::td[normalize-space(.)][last()]');
        $ports = explode('>', $itinerary);
        $portsCount = count($ports);

        // TripSegments
        $it['TripSegments'] = [];

        foreach ($ports as $key => $port) {
            $itsegment = [];

            $itsegment['Port'] = trim($port);

            if ($key === 0 && $dateDep) {
                $itsegment['DepDate'] = $dateDep;
            }

            if ($key === $portsCount - 1 && $dateArr) {
                $itsegment['ArrDate'] = $dateArr;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $converter = new \CruiseSegmentsConverter();
        $it['TripSegments'] = $converter->Convert($it['TripSegments']);

        $xpathFragment3 = $xpathFragment2 . '/following::text()[normalize-space(.)="Cabine"][1]';

        // RoomClass
        $it['RoomClass'] = $this->http->FindSingleNode($xpathFragment3 . '/following::text()[normalize-space(.)][position()<5][contains(normalize-space(.),"Categoria")]/following::td[normalize-space(.)][1]');

        // RoomNumber
        $roomNumber = $this->http->FindSingleNode($xpathFragment3 . '/following::text()[normalize-space(.)][position()<5][contains(normalize-space(.),"Número de cabine")]/following::td[normalize-space(.)][1]', null, true, '/^(\d+)$/');

        if ($roomNumber) {
            $it['RoomNumber'] = $roomNumber;
        }

        return $it;
    }

    protected function parseSegment($text)
    {
        $segment = [];
        //		$this->logger->info('TEXT: '.$text);

        $regular = "(\d+ \w+ \d+)\s*(.+?)\s*";
        $regular .= "Voo:\s*(\d+).*?Sai de:?(.+?)às\s*(\d+:\d+).*?(?:Aeroporto:?\s*(.+?))?\s*";
        $regular .= "Chega a:?(.+?)às\s*(\d+:\d+).*?\s+(?:Aeroporto:?\s*(.+))?$";

        if (preg_match("/{$regular}/s", $text, $matches)) {
            $segment['FlightNumber'] = $matches[3];
            $segment['AirlineName'] = $matches[2];
            $segment['DepName'] = trim(empty($matches[6]) ? $matches[4] : $matches[6]);
            $segment['ArrName'] = trim(empty($matches[9]) ? $matches[7] : $matches[9]);
            $segment += $this->increaseDate($matches[1], $matches[5], $matches[8]);
        }

        $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

        return $segment;
    }

    protected function normalizeDate($string = '')
    {
        $in = [
            '/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/', // 14/01/2018 17:00
        ];
        $out = [
            '$1.$2.$3',
        ];
        $string = preg_replace($in, $out, $string);

        return $string;
    }

    protected function increaseDate($date, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($this->dateStringToEnglish($date)));
        $arrDate = strtotime($arrTime, $depDate);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 days', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    private function correctSum($str)
    {
        $str = preg_replace('/\s+/', '', $str);			// 11 507.00	->	11507.00
        $str = preg_replace('/[,.](\d{3})/', '$1', $str);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $str = preg_replace('/,(\d{2})$/', '.$1', $str);	// 18800,00		->	18800.00

        return $str;
    }

    private function array_concat($a1, $a2)
    {
        // concats the values at identical keys together
        $aRes = $a1;

        foreach (array_intersect_key($a2, $aRes) as $key => $val) {
            $aRes[$key] .= ' ' . $val;
        }

        return $aRes;
    }
}
