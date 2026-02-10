<?php

namespace AwardWallet\Engine\drury\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "drury/it-1643050.eml, drury/it-2522311.eml, drury/it-2597675.eml, drury/it-2615930.eml, drury/it-3692150.eml, drury/it-56716451.eml, drury/it-56765587.eml";

    private $reSubject = [
        "Drury Hotels Reservation Confirmation",
        "Drury Hotels Reservation Cancellation",
        "Drury Inn & Suites ",
    ];

    private $formats = [
        1 => 'Room Information',
        2 => 'Stay Information',
        3 => 'FOLIO',
    ];
    private $lang = 'en';

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@druryhotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains(['Drury Hotels Company', '@druryhotels.com'])}]")->length === 0) {
            return false;
        }
        $body = $parser->getHTMLBody();

        foreach ($this->formats as $format) {
            if (stripos($body, $format) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->formats as $type => $text) {
            if (stripos($body, $text) !== false) {
                $this->logger->debug($text);
                $email->setType('Itinerary' . $type);

                switch ($type) {
                    case 1:
                        $this->parseEmail_1($email);

                        break;

                    case 2:
                        $this->parseEmail_2($email);

                        break;

                    case 3:
                        $this->parseEmail_3($email);

                        break;
                }

                break;
            }
        }

        return $email;
    }

    private function parseEmail_1(Email $email): void
    {
        $text = implode("\n",
            $this->http->FindNodes("//text()[normalize-space()='Hotel Information']/ancestor::table[2]//text()"));

        // Hotel information
        $htext = $this->re("#\nHotel Information\n(.*?)\nTravel Information#s", $text);
        // Travel information
        $ttext = $this->re("#\nTravel Information\n\s+(.*?)\nRoom Information#s", $text);
        // Room information
        $rtext = $this->re("#\nRoom Information\n\s+(.*?)\n(?:Guest Preferences|Rate Information|Cancellation Policy:)\n#s", $text);

        if (empty($rtext)) {
            $rtext = $this->re("#\nRoom Information\n\s+(.+)#s", $text);
        }

        if (empty($rtext) || empty($ttext) || empty($htext)) {
            $this->logger->debug("other format");

            return;
        }

        $GuestNames = $this->reAll("#(GUEST|Guest):\s+(.*?)\n#ms", $rtext, 2);

        foreach ($GuestNames as $key => $val) {
            $GuestNames[$key] = $val;
        }

        $h = $email->add()->hotel();

        $h->general()
            ->travellers($GuestNames);

        $account = $this->re("#Membership Number: (\d+)#", $text);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $confNos = $this->reAll("#Confirmation Number:\s+(\d+)#ms", $rtext);

        foreach ($confNos as $confNo) {
            $h->general()
                ->confirmation($confNo);
        }

        $cancelNumber = $this->re('/Cancelled\s+[-]\s+Cancellation\s*[#](\d{7})/', $rtext);

        if (!empty($cancelNumber)) {
            $h->general()
                ->status('Cancelled')
                ->cancelled()
                ->cancellationNumber($cancelNumber);
        }

        $h->hotel()
            ->name(trim($this->re("#^\s*(\D+)#ms", $htext)))
            ->address(trim(str_replace("\n", "", $this->re("#^\s*\D+(.*?)\d+-\d+-\d+#ms", $htext))))
            ->phone(trim($this->re("#^\s*\D+.*?(\d+-\d+-\d+)#ms", $htext)));

        $h->booked()
            ->checkIn(strtotime($this->re("#Arrival:\s+([^\n]+)#", $ttext)))
            ->checkOut(strtotime($this->re("#Departure:\s+([^\n]+)#", $ttext)))
            ->rooms($this->re("#(\d+) Room\(s\)#", $ttext))
            ->guests($this->re("#(\d+) Adult#i", $ttext))
            ->kids($this->re("#(\d+) child#i", $ttext), false, true);

        if (!empty($time = $this->re("#Check-in time is\s+(\d+:\d+\s+[AP]M)#", $text))) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        if (!empty($time = re("#check-out time is\s+(\d+:\d+\s+[AP]M)#", $text))) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        $Rate = $this->reAll("#Rate Information\n(.*?)\s+-#ms", $rtext);
        $RoomType = $this->reAll("#(GUEST|Guest):\s+.*?\n([^.:]+)(\.|:).*?(Total Room|Room \d+ Rate Information)#ms",
            $rtext, 2);
        $RoomTypeDescription = $this->reAll("#(GUEST|Guest):\s+.*?\n[^.:]+(\.|:)(.*?)(\.|EXTRAS:|Total Room|Room \d+ Rate Information)#ms",
            $rtext, 3);

        if ((count($Rate) == count($RoomType)) && (count($Rate) == count($RoomTypeDescription))) {
            foreach ($Rate as $key => $val) {
                $room = $h->addRoom();
                $room->setRate(preg_replace("#\s+#", " ", $val));
                $room->setType(trim(preg_replace("#\s+#ms", " ", $RoomType[$key])));
                $room->setDescription(trim(preg_replace("#\s+#ms", " ", $RoomTypeDescription[$key])));
            }
        }

        $Currency = [];
        $Cost = $this->reAll("#([0-9.]+ per night plus taxes - Starting \d+/\d+/\d+\n\D+[0-9.]+ USD Total before Taxes\*|\n\D+[0-9.]+ per night plus taxes - Starting \d+/\d+/\d+\n\*Local Taxes will apply)#ms",
            $rtext);

        foreach ($Cost as $key => $val) {
            if (strpos($val, "Total before Taxes") !== false) {
                $Cost[$key] = re("#\D+([0-9.]+) USD Total before Taxes#ms", $val);
                $Currency[$key] = re("#\D+[0-9.]+ (\S+) Total before Taxes#ms", $val);
            } else {
                $Cost[$key] = re("#\n\S+\s+([0-9.]+) per night plus taxes#ms", $val);
                $Currency[$key] = re("#\n(\S+)\s+[0-9.]+ per night plus taxes#ms", $val);
            }
        }

        foreach ($Currency as $key => $val) {
            $Currency[$key] = $val == '$' ? 'USD' : $val;
        }

        if (count($Cost) > 0) {
            $h->price()
                ->cost(array_sum($Cost))
                ->currency($Currency[0]);
        }

        $cancelText = $this->re("#Cancellation Policy(?::\n?|\s+-\s+)\s*(.+)#", $htext);

        if (empty($cancelText)) {
            $cancelText = $this->re("#Cancellation Policy(?::\n?|\s+-\s+)\s*(.+)#", $rtext);
        }

        if (!empty($cancelText)) {
            $h->general()->cancellation($cancelText);
            $this->detectDeadLine($h, $cancelText);
        }
    }

    private function parseEmail_2(Email $email): void
    {
        $text = implode("\n",
            $this->http->FindNodes("//text()[normalize-space()='Hotel Information']/ancestor::table[2]//text()"));

        // Hotel information
        $htext = $this->re("#\nHotel Information\n(.*?)\nStay Information#s", $text);
        // Stay information
        $sttext = $this->re("#\nStay Information\n\s+(.*?)\n\n\n#s", $text);
        // Hotel Policies
        $hptext = $this->re("#\nHotel Policies\n\s*(.*?)\n\n\n#s", $text);

        if (empty($htext) || empty($sttext) || empty($hptext)) {
            $this->logger->debug("other format");

            return;
        }

        $GuestNames = $this->reAll("#(GUEST|Guest):\s+(.*?)\n#ms", $sttext, 2);

        foreach ($GuestNames as $key => $val) {
            $GuestNames[$key] = $val;
        }

        $h = $email->add()->hotel();

        $h->general()
            ->travellers($GuestNames);

        $account = $this->re("#Membership Number: (\d+)#", $text);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $confNos = $this->reAll("#Confirmation Number:\s+(\d+)#ms", $sttext);

        foreach ($confNos as $confNo) {
            $h->general()
                ->confirmation($confNo);
        }
        $name = trim($this->re("#^\s*(?!\s*Whether)(\D+)#ms", $htext));

        if (strlen($name) > 100) {
            $name = $this->re("/\.\n(\D+)$/u", $name);
        }

        $h->hotel()
            ->name($name)
            ->address(trim(str_replace("\n", "", $this->re("#^\s*(?!\s*Whether)\D+(.*?)\d+-\d+-\d+#ms", $htext))))
            ->phone(trim(re("#^\s*(?!\s*Whether)\D+.*?(\d+-\d+-\d+)#ms", $htext)));

        $h->booked()
            ->checkIn(strtotime($this->re("#Arrival:\s+([^\n]+)#ms", $sttext)))
            ->checkOut(strtotime($this->re("#Departure:\s+([^\n]+)#ms", $sttext)))
            ->rooms($this->re("#(\d+) Room(?:\(s\))?\s*(?:Reserved|)#ms", $sttext))
            ->guests($this->re("#(\d+)\s+Adult#msi", $sttext))
            ->kids($this->re("#(\d+)\s+child#msi", $sttext), false, true);

        if (!empty($time = $this->re("#Check-in time:\s+(\d+:\d+\s+[AP]M)#u", $hptext))) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        if (!empty($time = re("#Check-out time:\s+(\d+:\d+\s+[AP]M)#u", $hptext))) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        $room = $h->addRoom();

        $room->setType($this->re("#Room Description:\s+([^\.]+)\.#", $sttext));
        $room->setDescription($this->re("#Room Description:\s+[^\.]+\.\s*([^\n]*)#", $sttext));

        $h->general()
            ->cancellation($this->re("#Cancellation Policy(?::\n?|\s+-\s+)(.*?)\n#ms", $hptext));

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }
    }

    private function parseEmail_3(Email $email): void
    {
        $h = $email->add()->hotel();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for choosing')]", null, true, '/(\d{3}\s+[-].+)/');

        $infoText = implode(' ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Arrival Date')]/preceding::text()[starts-with(normalize-space(), 'drury@druryhotels.com')][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<address>.+)\s+(?<phone>{$this->patterns['phone']})\s+\D+$/", $infoText, $m)) {
            $h->hotel()
                ->name($name)
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $guestText = $this->htmlToText($this->http->FindHTMLByXpath("//td[starts-with(normalize-space(),'Arrival Date')]/preceding::td[starts-with(normalize-space(),'Guest')]"));
        $guestName = $this->re("/^\s*Guest[ ]*\n+[ ]*({$this->patterns['travellerName']})[ ]*\n+.+/i", $guestText);

        $confDesc = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Reservation confirmation')]", null, true, '/Reservation\s+(\D+)[A-Z\d]{9}$/');
        $h->general()
            ->confirmation($this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Reservation confirmation')]", null, true, '/([A-Z\d]{9})$/'), $confDesc)
            ->traveller($guestName, true);

        $checkIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date')]/ancestor::td[1]/descendant::text()[normalize-space()][2]");
        $checkOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure Date')]/ancestor::td[1]/descendant::text()[normalize-space()][2]");
        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut))
            //->rooms()
            ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Adults')]", null, true, '/(\d+)/'))
            ->kids($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Children')]", null, true, '/(\d+)/'));

        $room = $h->addRoom();
        $room->setType($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room type')]/ancestor::td[1]/descendant::text()[normalize-space()][2]", null, true, '/(\D+)\s+\//'));
        $room->setDescription($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room type')]/ancestor::td[1]/descendant::text()[normalize-space()][2]", null, true, '/\D+\s+\/(.+)/'));
        $room->setRateType(implode('/night, ', $this->http->FindNodes("//text()[contains(normalize-space(), 'Occupancy Tax')]/preceding::tr[contains(normalize-space(), 'Nightly Room Charge')]/descendant::td[3]")) . '/night');

        $xpathPrice = "//tr[ count(*)=3 and *[3][normalize-space()='AMOUNT' or normalize-space()='Amount'] ]/following::tr[normalize-space()][1]/following-sibling::tr[normalize-space()]";

        $totalPrice = $this->http->FindSingleNode($xpathPrice . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Total:')] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $140.99
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode($xpathPrice . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Charges:')] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode($xpathPrice . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Taxes:')] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $taxes, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("/Cancell? (?i)by (?<hour>{$this->patterns['time']}) (?:the )?day of arrival/", $cancellationText, $m)
            || preg_match("/Reservations (?i)cancell?ed after (?<hour>{$this->patterns['time']}) on day of arrival will incur a one night room and tax penalty/", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('0 days', $m['hour']);
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            '#^(\w+)\s+(\d{1,2})\s+(\d{4})$#', //Jan 30 2020
        ];
        $out = [
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function reAll($reg, $text, $index = 1)
    {
        preg_match_all($reg, $text, $result, PREG_PATTERN_ORDER);

        return $result[$index];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
