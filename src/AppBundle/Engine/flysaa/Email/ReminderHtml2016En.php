<?php

namespace AwardWallet\Engine\flysaa\Email;

class ReminderHtml2016En extends \TAccountChecker
{
    public $mailFiles = "flysaa/it-5676497.eml";
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Booking ref:")]', null, false, '/[A-Z\d]{5,6}$/');
        $this->result['Passengers'] = $this->http->FindNodes('(//*[contains(text(), "Dear ")])[1]', null, '/Dear\s+(.+?),/');
        $this->parseSegments();

        return [
            'emailType'  => 'ReminderHtml2016En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@mailer.flysaa.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'South African Airways – Online check-in reminder') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Booking ref:') !== false
                && strpos($parser->getHTMLBody(), 'South African Airways') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mailer.flysaa.com') !== false;
    }

    protected function parseSegments()
    {
        foreach ($this->http->XPath->query('//tr[@bgcolor = "#e7d47a" or @bgcolor = "#E7D47A"]') as $root) {
            $it = [];

            if (preg_match('/^\s*(.+?)\s+to\s+(.+?)\s*$/', $root->nodeValue, $matches)) {
                $it['DepName'] = $matches[1];
                $it['ArrName'] = $matches[2];
            }

            $depDate = $this->http->FindSingleNode('following-sibling::tr[2]/td[3]', $root);
            $it['DepDate'] = strtotime($depDate . ', ' . $this->http->FindSingleNode('following-sibling::tr[2]/td[2]', $root));
            $it['DepCode'] = $this->http->FindSingleNode('following-sibling::tr[2]/td[4]', $root, null, '/^[A-Z]{3}$/');

            if (preg_match('/([A-Z]{2})\s*(\d+)/', $this->http->FindSingleNode('following-sibling::tr[2]/td[5]', $root), $matches)) {
                $it['AirlineName'] = $matches[1];
                $it['FlightNumber'] = $matches[2];
            }

            $arrDate = $this->http->FindSingleNode('following-sibling::tr[3]/td[3]', $root);
            $it['ArrDate'] = strtotime($arrDate . ', ' . $this->http->FindSingleNode('following-sibling::tr[3]/td[2]', $root));
            $it['ArrCode'] = $this->http->FindSingleNode('following-sibling::tr[3]/td[4]', $root, null, '/^[A-Z]{3}$/');

            $this->result['TripSegments'][] = $it;
        }
    }
}
