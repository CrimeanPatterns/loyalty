<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripInformation extends \TAccountChecker
{
    // delta itinerary email, with 'Your Forwarded Itinerary'
    // subject: Delta.com Trip Information from:
    // it-6.eml

    public $mailFiles = "delta/it-6.eml, delta/it-60174755.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseItinerary($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Delta.com Trip Information from') !== false
            || isset($headers['from']) && stripos($headers['from'], 'noreply@delta.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Delta is glad to forward this itinerary along') !== false || $this->http->XPath->query('//*[contains(., "Delta is glad to forward this itinerary along")]')->length > 0;
    }

    private function parseItinerary(Email $email)
    {
        // FLIGHT
        if (!empty($this->http->FindSingleNode("//text()[normalize-space() = 'Our Flights']"))) {
            $f = $email->add()->flight();

            $f->general()->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number :']/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#"));
            $passengers = $this->http->FindNodes('//*[normalize-space(.) = "Who\'s Coming Along" and parent::*[not(normalize-space(.) = "Who\'s Coming Along")]]/following-sibling::*[1]//td');

            foreach ($passengers as $passenger) {
                $text = str_replace('&', ',', $passenger);
                $f->general()->travellers(array_map('trim', explode(',', $text)));
            }

            $rows = $this->http->XPath->query("//tr[td[contains(., 'Flight Number:') and not(.//td)]]");

            foreach ($rows as $row) {
                $s = $f->addSegment();

                // Airline
                $flight = $this->http->FindSingleNode("following-sibling::tr[1]/td[4]", $row);

                if (preg_match("/\b([A-Z\d]{2}) (\d+)$/", $flight, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2])
                    ;
                }
                $operator = $this->http->FindSingleNode("following-sibling::tr[2]/td[4]", $row, false, '/operated by (.+?)(?:\s*DBA|$)/');

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }

                // Departure
                $s->departure()
                    ->code($this->http->FindSingleNode("following-sibling::tr[1]/td[1]", $row, false, '/\(([A-Z]{3})\)$/'))
                    ->name($this->http->FindSingleNode("following-sibling::tr[1]/td[1]", $row, false, '/^(.+) \([A-Z]{3}\)$/'))
                    ->date(strtotime(str_replace('@', '', $this->http->FindSingleNode("following-sibling::tr[2]/td[1]/div[2]", $row))))
                ;

                // Arrival
                $s->arrival()
                    ->code($this->http->FindSingleNode("following-sibling::tr[1]/td[3]", $row, false, '/\(([A-Z]{3})\)$/'))
                    ->name($this->http->FindSingleNode("following-sibling::tr[1]/td[3]", $row, false, '/^(.+) \([A-Z]{3}\)$/'))
                    ->date(strtotime(str_replace('@', '', $this->http->FindSingleNode("following-sibling::tr[2]/td[3]/div[2]", $row))))
                ;
            }
        }

        // RENTAL
        if (!empty($this->http->FindSingleNode("//text()[normalize-space() = 'Car Reservation']"))) {
            $email->ota()
                ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'CONFIRMATION #:']/following::text()[normalize-space()][1]"));

            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'CONFIRMATION #:']/preceding::text()[normalize-space()][1]", null, true, "#\(([A-Z\d]{5,})\)#"));

            // Pick up
            $r->pickup()
                ->location('Airport ' . $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'PICK UP(')]", null, true, "#\(([A-Z]{3})\)#"))
                ->date($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'PICK UP(')]/ancestor::tr[1]", null, true, "#:\s*(.+)#")))
            ;

            // Drop off
            $r->dropoff()
                ->location('Airport ' . $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'RETURN(')]", null, true, "#\(([A-Z]{3})\)#"))
                ->date($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'RETURN(')]/ancestor::tr[1]", null, true, "#:\s*(.+)#")))
            ;

            // Extra
            $r->extra()->company($this->http->FindSingleNode("//text()[normalize-space() = 'CONFIRMATION #:']/preceding::text()[normalize-space()][1]", null, true, "#^\s*(.+?)\s*\([A-Z\d]{5,}\)#"));
        }

        return true;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*\w+,\s*(\d+)\s+([^\d\s]+)\s+(\d{4})\s*\((\d+:\d+(\s*[ap]m))\)\s*$#ui", //MON, 15 JUN 2020 (10:00 AM)
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], 'en')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
