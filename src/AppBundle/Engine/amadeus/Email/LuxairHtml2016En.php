<?php

namespace AwardWallet\Engine\amadeus\Email;

class LuxairHtml2016En extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-12742627.eml, amadeus/it-4552783.eml";

    private $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            return false;
        }

        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Web checkin number")]/ancestor::td[1]', null, false, '/[A-Z\d]{5,6}/');
        $this->result['Passengers'][] = $this->http->FindSingleNode('//*[contains(text(), "Name and Surname")]/ancestor::td[1]', null, false, '/:\s*(.+?)\s*\(/');
        $this->result['TicketNumbers'][] = $this->http->FindSingleNode('//*[contains(text(), "Ticket number")]/ancestor::td[1]', null, false, '/\d+/');
        $this->result['BaseFare'] = cost($this->http->FindSingleNode('//*[contains(text(), "Air Fare")]/ancestor::tr[1]', null, false, '/:\s*(.+)/'));
        $this->result += total($this->http->FindSingleNode('//*[contains(text(), "Total Amount")]/ancestor::tr[1]', null, false, '/:\s*(.+)/'));

        $this->parseSegments();

        return [
            'emailType'  => 'LuxAirTicketHTML2016En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'eticket@amadeus.com') !== false
                && isset($headers['subject'])
                && stripos($headers['subject'], 'Your Electronic Ticket Receipt') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (strpos($parser->getHTMLBody(), 'Web checkin number') !== false || strpos($parser->getHTMLBody(), 'Ticket Receipt') !== false)
                && strpos($parser->getHTMLBody(), 'At check-in you need to present') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function parseSegments()
    {
        foreach ($this->http->XPath->query('//*[contains(text(), "Operated by")]/ancestor::tr[1]') as $current) {
            $node = $this->http->XPath->query('preceding-sibling::tr[position() = 2]', $current);
            $this->result['TripSegments'][] = $this->parseSegment($node->item(0));
        }
    }

    private function parseSegment(\DOMElement $element)
    {
        $segment = [];

        if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $this->http->FindSingleNode('td[2]', $element), $matches)) {
            $segment['Aircraft'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
            $segment['AirlineName'] = $this->http->FindSingleNode('./following-sibling::tr[2]', $element, true, "#Operated by\s*:\s*(.+)#");
            $date = strtotime($this->http->FindSingleNode('td[4]', $element));
            $segment['DepName'] = $this->http->FindSingleNode('td[6]', $element);
            $segment['ArrName'] = $this->http->FindSingleNode('td[8]', $element);
            $segment['BookingClass'] = $this->http->FindSingleNode('td[14]', $element);
            $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match('/\d+:\d+/', $this->http->FindSingleNode('td[10]', $element), $matches)) {
                $segment['DepDate'] = strtotime($matches[0], $date);
            }

            if (preg_match('/\d+:\d+/', $this->http->FindSingleNode('td[12]', $element), $matches)) {
                $segment['ArrDate'] = strtotime($matches[0], $date);
            }

            if ($this->http->FindSingleNode('./following-sibling::tr[1][contains(normalize-space(), "Terminal")]', $element)) {
                $depcols = array_sum($this->http->FindNodes('td[6]/preceding-sibling::td/@colspan', $element));
                $depcols += count($this->http->FindNodes('td[6]/preceding-sibling::td[not(@colspan)]', $element));
                $deplens = $this->http->FindSingleNode('td[6]/@colspan', $element);

                if (empty($deplens)) {
                    $deplens = 1;
                }

                $arrcols = array_sum($this->http->FindNodes('td[8]/preceding-sibling::td/@colspan', $element));
                $arrcols += count($this->http->FindNodes('td[8]/preceding-sibling::td[not(@colspan)]', $element));
                $arrlens = $this->http->FindSingleNode('td[8]/@colspan', $element);

                if (empty($arrlens)) {
                    $arrlens = 1;
                }

                if (!empty($depcols) || !empty($arrcols)) {
                    $nodes = $this->http->XPath->query('./following-sibling::tr[1]/td', $element);
                    $cols = -1;
                    $cs = null;

                    foreach ($nodes as $key => $node) {
                        $cols += empty($cs) ? 1 : $cs;
                        $value = $this->http->FindSingleNode('.', $node);
                        $cs = $this->http->FindSingleNode('./@colspan', $node);

                        if (empty($value) || stripos($value, 'terminal') === false) {
                            continue;
                        }

                        if (!isset($segment['DepartureTerminal']) && $cols >= $depcols && ($cols < $depcols + $deplens)) {
                            $segment['DepartureTerminal'] = $this->http->FindSingleNode('.', $node, true, "#Terminal (.+)#");

                            continue;
                        }

                        if (!isset($segment['ArrivalTerminal']) && $cols >= $arrcols && ($cols < $arrcols + $arrlens)) {
                            $segment['ArrivalTerminal'] = $this->http->FindSingleNode('.', $node, true, "#Terminal (.+)#");

                            break;
                        }
                    }
                }
            }
        }

        return $segment;
    }
}
