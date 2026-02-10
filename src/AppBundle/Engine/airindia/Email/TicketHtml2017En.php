<?php

namespace AwardWallet\Engine\airindia\Email;

class TicketHtml2017En extends \TAccountChecker
{
    public $mailFiles = "airindia/it-151099345.eml, airindia/it-6160294.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its[] = $this->parseAir();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TicketHtml2017En',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@airindia.') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Air India Mobile - ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'airindia') !== false
                && strpos($parser->getHTMLBody(), 'PASSENGER E-TICKET') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airindia.') !== false;
    }

    protected function parseAir()
    {
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(., "Booking Reference")]/ancestor::td[1]/following-sibling::td[1]', null, false, '/[A-Z\d]{5,6}/');
        $result += $this->parseAdditionally();

        $sXpath = "//text()[starts-with(normalize-space(), 'Seat number')]";
        $lastCode = null;
        $seats = [];
        foreach ($this->http->XPath->query($sXpath) as $sRoot) {
            $code = $this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(), 'Your flight to')][1]", $sRoot, true, "/\(([A-Z]{3})\)\s*$/");
            $this->logger->debug('$code = '.print_r( $code,true));
            $this->logger->debug('$lastCode = '.print_r( $lastCode,true));
            if (empty($code)) {
                $seats = [];
                break;
            }
            if (empty($lastCode) || $code == $lastCode || !isset($seats[$code])) {
                $lastCode = $code;
                $seats[$code][] = $this->http->FindSingleNode(".", $sRoot, true, "/Seat number (\d{1,3}[A-Z])\s*$/");;
            } else {
                $seats = [];
                break;
            }
        }

        $nodes = $this->http->XPath->query('//text()[contains(., "Flight Number/Date")]/ancestor::tr[1]/following-sibling::tr[1]');
        if (count($seats) !== $nodes->length) {
            $seats = [];
        }
        foreach ($nodes as $root) {
            $i = [];

            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s+(\d+ \w+ \d+)/', $root->nodeValue, $matches)) {
                $i['AirlineName'] = $matches[1];
                $i['FlightNumber'] = $matches[2];
                $date = $matches[3];
            }

            $table = $this->http->FindSingleNode('following::table[1][count(.//text()[contains(., "From")]) = 1 and contains(., "To")]', $root);
            //  Delhi (DEL) Terminal 3 17:45 Bengaluru (BLR) 20:25
            $table = preg_replace("/^From\s*To\s*/", '', $table);
            $pattern = '([\w\s]+)\s+\(([A-Z]{3})\)(?:\s+Terminal (\w+))?\s+([\d:]+(?:\s[ap.]m\b)?)';

            if (isset($date) && preg_match_all("/{$pattern}/", $table, $matches, PREG_SET_ORDER)) {
                $i['DepName'] = $matches[0][1];
                $i['DepCode'] = $matches[0][2];
                $i['DepartureTerminal'] = $matches[0][3];
                $i['DepDate'] = strtotime($date . ',' . $matches[0][4], false);

                if (isset($matches[1])) {
                    $i['ArrName'] = trim($matches[1][1]);
                    $i['ArrCode'] = $matches[1][2];
                    $i['ArrivalTerminal'] = $matches[1][3];
                    $i['ArrDate'] = strtotime($date . ',' . $matches[1][4], false);

                    if (isset($seats[$i['ArrCode']])) {
                        $i['Seats'] = $seats[$i['ArrCode']];
                    }
                }
            }

            $result['TripSegments'][] = $i;
        }

        return $result;
    }

    protected function parseAdditionally()
    {
        $result = [];

        $pCol = $this->http->XPath->query("//td[not(.//td)][normalize-space() = 'Name']/preceding-sibling::td")->length + 1;
        $result['Passengers'] = $this->http->FindNodes("//td[not(.//td)][normalize-space() = 'Name']/ancestor::tr[1]/following-sibling::tr[normalize-space()]/td[{$pCol}]", null, "/^\s*\D+\s*$/");
        $tCol = $this->http->XPath->query("//td[not(.//td)][normalize-space() = 'E-Ticket No']/preceding-sibling::td")->length + 1;
        $result['TicketNumbers'] = $this->http->FindNodes("//td[not(.//td)][normalize-space() = 'E-Ticket No']/ancestor::tr[1]/following-sibling::tr[normalize-space()]/td[{$tCol}]", null, "/^\s*[\d\- \\/]+\s*$/");
        $aCol = $this->http->XPath->query("//td[not(.//td)][normalize-space() = 'FF No']/preceding-sibling::td")->length + 1;
        $result['AccountNumbers'] = array_filter(preg_replace("/^\s*(\-|FF#\s*INVALID)\s*$/", '',
            $this->http->FindNodes("//td[not(.//td)][normalize-space() = 'FF No']/ancestor::tr[1]/following-sibling::tr[normalize-space()]/td[{$aCol}]")));


        $sum = $this->http->FindSingleNode('//text()[contains(., "Total")]/following-sibling::strong[1]');

        $result['TotalCharge'] = preg_replace('/[^\d.]+/', '', $sum);
        $result['Currency'] = preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/'], ['', 'EUR', 'USD'], $sum);

        return $result;
    }
}
