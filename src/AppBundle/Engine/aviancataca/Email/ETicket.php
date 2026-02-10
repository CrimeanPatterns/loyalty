<?php
namespace AwardWallet\Engine\aviancataca\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-10552193.eml, aviancataca/it-10554088.eml, aviancataca/it-10730817.eml, aviancataca/it-8153610.eml";

    private $reFrom = "avianca.com.br";

    private $detects = [
        'pt' => [
            'Segue sua reserva confirmada, qualquer dúvida entre em contato com seu agente de viagens',
        ],
    ];

    private $lang = 'pt';

    private static $dict = [
        'pt' => [],
    ];
    // private $dateFirstFlight; // for aviancataca

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // if(($root = $this->http->XPath->query("//text()[normalize-space(.)='CONFIRMAÇÃO DE RESERVA']/ancestor::table[1]")->item(0))==null) return null;
        // $this->http->SetEmailBody($root->ownerDocument->saveHTML($root));
        // echo $this->http->Response["body"];
        // die();
        $result = [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => 'ETicket' . ucfirst($this->lang),
        ];

        // if (isset($this->dateFirstFlight) && $this->dateFirstFlight >= strtotime(('2019-07-01'))) {
        //     $result['providerCode'] = 'aviancataca';
        // }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'avianca.com.br')] | //img[contains(@src,'avianca.com.br')]")->length > 0) {
            $body = $parser->getHTMLBody();
            foreach ($this->detects as $detects) {
                foreach ($detects as $detect) {
                    if (stripos($body, $detect) !== false) {
                        return true;
                    }
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
        return count(self::$dict);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(): array
    {
        // echo $this->http->Response["body"];
        // die();
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (!$it['RecordLocator'] = $this->http->FindSingleNode("//tr[contains(., 'E-TICKET') and not(.//tr)]/following::tr[1]/td[2]")) {
            $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='LOCALIZADOR']/ancestor::table[1]//tr)[2]/td[1]");
        }

        $it['TicketNumbers'][] = $this->http->FindSingleNode("//tr[contains(., 'E-TICKET') and not(.//tr)]/following::tr[1]/td[1]");

        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Passageiro:']/following::text()[string-length(normalize-space(.))>2][1]", null, "#^(.+?)\s*(\(.+\))?$#");

        if (!$total = $this->http->FindSingleNode("//td[contains(., 'Total Geral')]/following-sibling::td[1]")) {
            $total = $this->http->FindSingleNode("//text()[normalize-space(.)='TOTAL']/ancestor::table[1]/tbody/tr[1]/td[4]");
        }

        if (preg_match('/(\D+)\s*([\d\.\,]+)/', $total, $m)) {
            $it['Currency'] = str_replace(['R$'], ['BRL'], trim($m[1]));
            $it['TotalCharge'] = $this->amount($m[2]);
        }

        $xpath = "(//text()[normalize-space(.)='CHEGADA']/ancestor::table[1]//tr)[.//img]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $depArr = [
                'Dep' => $this->getNode($root),
                'Arr' => $this->getNode($root, 2),
            ];

            $re = '/(.+)\s+\(([A-Z]{3})\)\s*(\d+\/\d+\/\d+)\s+\w+\s+\((\d+:\d+)h?\)/u';
            // GUARULHOS (GRU) 10/12/2017 AS (17:45h)
            array_walk($depArr, function ($val, $key) use (&$seg, $re) {
                if (preg_match($re, $val, $m)) {
                    $seg[$key . 'Name'] = $m[1];
                    $seg[$key . 'Code'] = $m[2];
                    $seg[$key . 'Date'] = $this->normalizeDate($m[3] . ', ' . $m[4]);
                }
            });

            if (!empty($this->http->FindSingleNode("td[3]//img[contains(@src, '.avianca.com.br')]/@src", $root))) {
                $seg['AirlineName'] = 'O6';
            }
//            $seg['AirlineName'] = $this->getNode($root, 3);

            $seg['FlightNumber'] = $this->getNode($root, 4);

            $seg['BookingClass'] = trim($this->getNode($root, 5), ' ()');

            $it['Status'] = $this->getNode($root, 'last()');

            // if (isset($seg['DepDate']) && !empty($seg['DepDate']) && !isset($this->dateFirstFlight)) {
            //     $this->dateFirstFlight = $seg['DepDate'];
            // }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\d+)\/(\d+)\/(\d+),\s+(\d+:\d+)/',
        ];
        $out = [
            '$2/$1/$3, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function getNode(\DOMNode $root, $td = 1, $re = null)
    {
        return $this->http->FindSingleNode("td[" . $td . "]", $root, true, $re);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }
}
