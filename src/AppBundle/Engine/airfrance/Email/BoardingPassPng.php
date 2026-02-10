<?php

namespace AwardWallet\Engine\airfrance\Email;

class BoardingPassPng extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $search = $parser->searchAttachmentByName('(?:Mobile-boarding-pass|Carte-d‘embarquement-sur-mobile)-AF.+\.png');

        if (count($search) !== 1) {
            $this->http->Log(sprintf('Invalid number of png attachments %d', count($search)));

            return [];
        }
        $search = $search[0];
        $name = $parser->getAttachmentHeader($search, 'Content-Type');

        if (!$name || !preg_match('/name="((?:Mobile-boarding-pass|Carte-d‘embarquement-sur-mobile)-AF\d+-[\d\w]+(?:\s+\w+)?\.png)"/', $name, $m)) {
            $this->http->Log('invalid filename');

            return [];
        }
        $result['AttachmentFileName'] = $m[1];
        $text = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "You are checked in on flight")]');

        if ($text && preg_match('/You are checked in on flight AF (\d+) on (\d+)(\w{3}) to [^.]+\. Attached is your boarding pass/', $text, $m)) {
            $result['FlightNumber'] = $m[1];
        }
        $year = intval(date('Y', strtotime($parser->getHeader('date'))));

        if ($year > 2000 && isset($m[2])) {
            $result['DepDate'] = strtotime(sprintf('%s %s %s', $m[2], $m[3], $year));
        }

        if (!isset($result['FlightNumber'])) {
            $result['FlightNumber'] = $this->http->FindSingleNode("//*[normalize-space(.)='Date de départ']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, "#\w{2}\s+(\d+)#");
        }

        if (!isset($result['DepDate'])) {
            $result['DepDate'] = strtotime(preg_replace("#^(\d+)/(\d+)/(\d{4})\s+à\s+(\d+:\d+)$#", "$2/$1/$3, $4", $this->http->FindSingleNode("//*[normalize-space(.)='Date de départ']/ancestor::tr[1]/following-sibling::tr[1]/td[3]")));
        }

        if (!isset($result['DepCode']) && ($code = $this->http->FindSingleNode("//*[normalize-space(.)='Date de départ']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, true, "#\(([A-Z]{3})\)#"))) {
            $result['DepCode'] = $code;
        }

        if (!isset($result['RecordLocator']) && ($locator = $this->http->FindSingleNode("//text()[normalize-space(.)='Référence de réservation :']/following::text()[normalize-space(.)][1]", null, true, "#^[\w\d]{6}$#"))) {
            $result['RecordLocator'] = $locator;
        }

        return [
            'parsedData' => [
                'BoardingPass' => [$result],
            ],
            'emailType' => 'boardingPassPng',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return count($parser->searchAttachmentByName('Mobile-boarding-pass-AF.+\.png')) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'cartedembarquement@airfrance.fr') !== false
        || isset($headers['subject']) && stripos($headers['subject'], 'Your Air France boarding pass for') !== false
        || isset($headers['subject']) && stripos($headers['subject'], 'Vos documents d‘embarquement Air France') !== false
        ;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airfrance.') !== false;
    }
}
