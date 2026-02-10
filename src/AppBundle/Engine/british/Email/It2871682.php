<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It2871682 extends \TAccountCheckerExtended
{
    public $mailFiles = "british/it-278132687.eml, british/it-2871682.eml";

    public $reFrom = '@email.ba.com';
    public $reSubject = 'Your e-ticket receipt';
    public $reBody = ['booking with British Airways', 'Flight tickets'];

    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (count($this->http->FindNodes("//img[contains(@src, 'http')]")) > 0 |
            count($this->http->FindNodes("//a[contains(@href, 'http')]")) > 0) {
            $this->logger->warning('This format contains HTML');

            return false;
        }

        $text = str_replace('> ', '', strip_tags($this->http->Response['body']));

        $flight = $email->add()->flight();

        //Confirmation
        $flight->general()
            ->confirmation($this->re("#Booking\s+reference:\s*([\w\-]+)#", $text), 'Booking reference');

        //Travellers
        if (preg_match("/\s*(?:MS|MISS|MRS|MR|MASTER|MISTER|MISTER'S)\s+[A-Z\s]+\s?\,?\s/", $text, $match)) {
            $flight->general()
                ->travellers($match, true);
        }

        //Tickets
        if (preg_match_all("/\s+(\d{3}\-\d+)\s*\((?:MS|MISS|MRS|MR|MASTER|MISTER|MISTER'S)/", $text, $match)) {
            $flight->setTicketNumbers($match[1], false);
        }

        //Price
        $paymentTotal = $this->re("/Payment\s+Total\s+([A-Z]+\s+[\d\.\,]+)/", $text);

        if (!empty($paymentTotal)) {
            $flight->price()
                ->total($this->re("/([\d\.\,]+)/", $paymentTotal))
                ->currency($this->re("/([A-Z]+)/", $paymentTotal));
        }

        $spentAwards = $this->re("/\spoints\s+debited\s*(\d+)/i", $text);

        if (!empty($spentAwards)) {
            $flight->price()
                ->spentAwards($spentAwards);
        }

        $tax = $this->re("/Tax\/Fee\/Charge\s[A-Z]+\s([\d\.\,]+)/", $text);

        if (!empty($tax)) {
            $flight->price()
                ->tax($tax);
        }

        $cost = $this->re("/Fare\sDetails\s[A-Z]+\s([\d\.\,]+)/", $text);

        if (!empty($cost)) {
            $flight->price()
                ->cost($cost);
        }

        //Segments
        $pattern = "/(?<flightName>[A-Z]{2})" .
                    "(?<flightNumber>\d{2,4})\s+" .
                    "(?<operatedBy>\D+)[|]\s+[|]\s" .
                    "(?<status>\w+)\s+" .
                    "(?<depDate>\d+\s\w+\s+\d{4}\s+[\d\:]+)\s+" .
                    "(?<depName>\D+)\s+\-\s+\D+Terminal\s" .
                    "(?<depTerminal>\S)\s" .
                    "(?<arrDate>\d+\s+\w+\s+\d{4}\s+[\d\:]+)\s+" .
                    "(?<arrName>\D+)\s+\-\s+\D+Terminal\s+" .
                    "(?<arrTerminal>\S)\s+/";

        $patternDep = "/(?<flightName>[A-Z]{2})" .
                    "(?<flightNumber>\d{2,4})\s+" .
                    "\D*\s(?<operatedBy>\D+)[|]\D*[|]" .
                    "\s*(?<status>\w+)\s+" .
                    "(?<depDate>\d+\s\w+\s+\d{4}\s+[\d\:]+)\s+" .
                    "(?<depName>\D+)\s*\D+" .
                    "(?:Terminal\s+(?<depTerminal>\S+))?\s*\d+\s*[A-z]+/";

        $patternArr = "/\s+(?<arrDate>\d+\s+\w+\s+\d{4}\s+[\d\:]+)\s+" .
                    "(?<arrName>\D+)" .
                    "(?:Terminal\s+(?<arrTerminal>\S+))?\s*$/";

        if (preg_match_all("/([A-Z]{2}\d{2,4}\s+\D+[|]\s+[|]\s\w+\s+\d+\s\w+\s+\d{4}\s+[\d\:]+\s+\D+\s+\-\s+\D+Terminal\s\S\s\d+\s+\w+\s+\d{4}\s+[\d\:]+\s+\D+\s+\-\s+\D+Terminal\s+\S)\s+/", $text, $m)
        || preg_match_all("/([A-Z]{2}\d{2,4}\s+\D+[|]\s\w+\s+\d+\s\w+\s+\d{4}\s+[\d\:]+\s+\D+(?:Terminal\s\S\s)?\d+\s+\w+\s+\d{4}\s+[\d\:]+\s+[A-z\(\)\s]+\s)/", $text, $m)) {
            foreach ($m[0] as $nodes) {
                if (stripos($nodes, 'Passenger') !== false) {
                    $nodes = $this->re("/^(.+)Passenger/", $nodes);
                }

                if (preg_match($pattern, $nodes, $node)) {
                    $segment = $flight->addSegment();
                    //Airline
                    $segment->airline()
                        ->name($node['flightName'])
                        ->number($node['flightNumber'])
                        ->operator($node['operatedBy']);

                    //Depart
                    $segment->departure()
                        ->date($this->normalizeDate($node['depDate']))
                        ->noCode()
                        ->name($node['depName'])
                        ->terminal($node['depTerminal']);

                    //Arrival
                    $segment->arrival()
                        ->date($this->normalizeDate($node['arrDate']))
                        ->noCode()
                        ->name($node['arrName'])
                        ->terminal($node['arrTerminal']);

                    $segment->extra()
                        ->status($node['status']);
                } elseif (preg_match($patternDep, $nodes, $node)) {
                    $segment = $flight->addSegment();
                    //Airline
                    $segment->airline()
                        ->name($node['flightName'])
                        ->number($node['flightNumber'])
                        ->operator($node['operatedBy']);

                    //Depart
                    $segment->departure()
                        ->date($this->normalizeDate($node['depDate']))
                        ->noCode()
                        ->name($node['depName']);

                    if (isset($node['depTerminal'])) {
                        $segment->departure()
                            ->terminal($node['depTerminal']);
                    }

                    $segment->extra()
                        ->status($node['status']);

                    if (preg_match($patternArr, $nodes, $node)) {
                        //Arrival
                        $segment->arrival()
                            ->date($this->normalizeDate($node['arrDate']))
                            ->noCode()
                            ->name($node['arrName']);

                        if (isset($node['arrTerminal'])) {
                            $segment->arrival()
                                ->terminal($node['arrTerminal']);
                        }
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        if (strpos($from, $this->reFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["subject"], $this->reSubject) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (strpos($this->http->Response["body"], $this->reBody[0]) !== false &
           strpos($this->http->Response["body"], $this->reBody[1]) !== false) {
            return true;
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $in = [
            "#^(\d+\s\w+\s\d{4})\s+([\d\:]+)$#", //22 Aug 2015 05:10
        ];
        $out = [
            "$1, $2",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
