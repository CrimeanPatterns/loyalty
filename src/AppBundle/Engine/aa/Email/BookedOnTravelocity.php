<?php

namespace AwardWallet\Engine\aa\Email;

class BookedOnTravelocity extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-17901765.eml";

    public $reFrom = [
        "americanairlines@email.aa.com", "no-reply@notify.email.aa.com", "non-aadvantage@mail.ms.aa.com",
    ];
    public $reBody = [
        'en'  => ['the trip you booked on', 'Record locator'],
        'en2' => ["the American Airlines trip you booked", 'Record locator'],
        'en3' => ['We rebooked your trip', 'Record locator'],
    ];
    public $reSubject = [
        'The trip you booked on',
        'Confirm or change flight',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BookedOnTravelocity',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".aa.com/") or contains(@href,"link.aa.com") or contains(@href,"ms.aa.com")]')->length === 0
            && $this->http->XPath->query('//img[normalize-space(@alt)="Thanks for choosing American Airlines"]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Get the American Airlines app") or contains(normalize-space(),"American Airlines, Inc. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectByDict();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) !== false && isset($this->reSubject)) {
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
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Record locator')]",
            null, false, "#Record locator: *([A-Z\d]{5,})#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Record locator')]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^([A-Z\d]{5,})$#");
        }

        $it['AccountNumbers'][] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'AAdvantage #')]/following::text()[normalize-space(.)!=''][1]");
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Hello ')]", null,
            false, "#Hello (.+?)(?:,|$)#");

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') and translate(translate(substring(normalize-space(.),string-length(normalize-space(.))-1),'APM','apm'),'apm','ddd')='dd'";
//        $xpath = "//text()[{$ruleTime}]/ancestor::td[1]/following-sibling::td[1][{$ruleTime}]/ancestor::table[1]";
        $xpath = "//text()[{$ruleTime}]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1][{$ruleTime}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);
        $lastDate = null;

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $date = strtotime($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root));

            if (!empty($date)) {
                $lastDate = $date;
            } else {
                $date = $lastDate;
            }
            $seg['DepCode'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]",
                $root, false, "#[A-Z]{3}#");
            $seg['ArrCode'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][2]",
                $root, false, "#[A-Z]{3}#");
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][1]",
                $root), $date);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][2]",
                $root), $date);
            $seg['DepName'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][3]/td[normalize-space(.)!=''][1]",
                $root);
            $seg['ArrName'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][3]/td[normalize-space(.)!=''][2]",
                $root);

            $node = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("/([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)/", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = implode("\n",
                $this->http->FindNodes("./following::table[1]//text()[normalize-space(.)!='']", $root));

            if (!isset($seg['AirlineName']) && preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)\b/", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#Aircraft\s*:\s*(.+)\s+Class\s*:\s*(.+)#", $node, $m)) {
                $seg['Aircraft'] = $m[1];
                $seg['Cabin'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function detectByDict(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
