<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-35400188.eml, bcd/it-35400237.eml, bcd/it-35400273.eml, bcd/it-35400318.eml";

    public $reFrom = ["@bcdtravel."];
    public $reBody = [
        'en' => ['Thank you for choosing BCD Travel', 'Confirmation of'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Surname'            => 'Surname',
            'Forename'           => 'Forename',
            'reservationHeaders' => [
                'Flight ticket confirmation',
                'Hotel booking confirmation',
                'Rail booking confirmation',
            ],
            //flight
            //hotel
            //rail
        ],
    ];
    private $keywordProv = 'BCD Travel';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text)) {
                        if (!$this->assignLang($text)) {
                            $this->logger->debug('can\'t determine a language [' . $i . '-attachment]');

                            continue;
                        }

                        if (!$this->parseEmailPdf($text, $email)) {
                            return null;
                        }
                    }
                }
            }
        } else {
            return null;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->detectBody($text)
                && $this->assignLang($text)
            ) {
                return true;
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

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; // flight | hotel | train
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $reservations = $this->splitter("#^([ ]*{$this->opt($this->t('reservationHeaders'))}(?! for).*$)#m",
            "controlStr\n" . $textPDF);

        foreach ($reservations as $reservation) {
            if (preg_match("#^[ ]*{$this->opt($this->t('Flight ticket confirmation'))}#", $reservation) > 0) {
                if (!$this->parseFlight($reservation, $email)) {
                    return false;
                }
            } elseif (preg_match("#^[ ]*{$this->opt($this->t('Hotel booking confirmation'))}#", $reservation) > 0) {
                if (!$this->parseHotel($reservation, $email)) {
                    return false;
                }
            } elseif (preg_match("#^[ ]*{$this->opt($this->t('Rail booking confirmation'))}#", $reservation) > 0) {
                if (!$this->parseRail($reservation, $email)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function parseHotel($textPDF, Email $email)
    {
        $r = $email->add()->hotel();
        $r->ota()
            ->phone($this->re("#{$this->t('BCD Travel phone number')}[ ]+([\d\-\+\(\) ]{5,})#", $textPDF),
                $this->t('BCD Travel phone number'));

        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('Resevation Nr.'))}[ ]+(\d+)#", $textPDF),
                $this->t('Resevation Nr.'), true)
            ->confirmation($this->re("#{$this->opt($this->t('File No.'))}[ ]+(\d+)#", $textPDF), $this->t('File No.'))
            ->traveller($this->re("#{$this->opt($this->t('Client'))}:[ ]+(.+)#", $textPDF), true)
            ->cancellation($this->nice($this->re("#({$this->opt($this->t('Cancellations and/or amendments to bookings'))}.+?)\n\n#s",
                $textPDF)));

        $r->hotel()
            ->name($this->re("#Hotel:\s+(.+)#", $textPDF))
            ->address($this->re("#Address:\s+(.+)#", $textPDF) . ', ' . $this->re("#City:\s+(.+?)\s*\n\s*Address#", $textPDF))
            ->phone($this->re("#Telephone:\s+([\d\-\+\(\) ]{5,})#", $textPDF));

        $r->booked()
            ->rooms($this->re("#Number of rooms:\s+(\d+)#", $textPDF))
            ->guests($this->re("#Number of people:\s+(\d+)#", $textPDF))
            ->checkIn($this->normalizeDate($this->re("#Arrival date:\s+(.+)#", $textPDF)))
            ->checkOut($this->normalizeDate($this->re("#Departure date:\s+(.+)#", $textPDF)));

        $timeIn = $this->re("#Check-in: from (\d+:\d+)#", $textPDF);
        $timeOut = $this->re("#Check-out: to (\d+:\d+)#", $textPDF);

        if (!empty($timeIn) && $r->getCheckInDate()) {
            $r->booked()
                ->checkIn(strtotime($timeIn, $r->getCheckInDate()));
        }

        if (!empty($timeOut) && $r->getCheckOutDate()) {
            $r->booked()
                ->checkOut(strtotime($timeOut, $r->getCheckOutDate()));
        }

        // cancellations and amendments not permitted

        $room = $r->addRoom();
        $room
            ->setType($this->re("#Room type:\s+(.+)#", $textPDF))
            ->setRate($this->re("#Rate per room per night:\s+(.+)#", $textPDF));
        $this->detectDeadLine($r);

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#Cancellations and\/or amendments to bookings must be communicated to our Agency before (?<time>\d+ pm), (?<priorHours>\d+) hours prior to arrival#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorHours'] . ' hours', $m['time']);
        }
    }

    private function parseRail($textPDF, Email $email)
    {
        $r = $email->add()->train();
        $r->ota()
            ->phone($this->re("#{$this->t('BCD Travel phone number')}[ ]+([\d\-\+\(\) ]{5,})#", $textPDF),
                $this->t('BCD Travel phone number'));

        $ticket = $this->re("#Ticket id:\s+([\d\-]{5,})#", $textPDF);

        if (!empty($ticket)) {
            $r->addTicketNumber($ticket, false);
        }

        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('File No.'))}[ ]+(\d+)#", $textPDF), $this->t('File No.'))
            ->traveller($this->re("#{$this->opt($this->t('Client'))}:[ ]+(.+)#", $textPDF), true);

        $tariff = $this->getTotalCurrency($this->re("#^[ ]*Tariff:\s+(\D+?\d[\d\.\,]+)#um", $textPDF));

        if (isset($tariff['Total'])) {
            $r->price()
                ->cost($tariff['Total'])
                ->currency($tariff['Currency']);
        }

        $class = $this->re("#Class:\s+(.+)#", $textPDF);
        $typeTrain = $this->re("#Type of train departure::\s+(.+)#", $textPDF);

        $segmentText = $this->re("#Departure:\s+(.+?)\n[ ]*Number of seats:#s", $textPDF);
        $segments = $this->splitter("#(^[ ]*\d+\/\d+\/\d+)#m", "controlText\n" . $segmentText);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $s->extra()
                ->cabin($class)
                ->noNumber();

            if (!empty($typeTrain)) {
                $s->extra()
                    ->type($typeTrain);
            }
            $regExp = "#(?<date>\d+\/\d+\/\d+)\s+(.+) at (?<depTime>\d+:\d+) Arriving at (?<arrTime>\d+:\d+) From (?<dep>.+?) To (?<arr>.+)#";

            if (preg_match($regExp, $segment, $m)) {
                $date = $this->normalizeDate($m['date']);
                $s->departure()
                    ->name($m['dep'])
                    ->date(strtotime($m['depTime'], $date));

                $s->arrival()
                    ->name($m['arr'])
                    ->date(strtotime($m['arrTime'], $date));

                if ($s->getDepDate() > $s->getArrDate()) {
                    $s->arrival()->date(strtotime("+1 day", $s->getArrDate()));
                }
            }
        }

        return true;
    }

    private function parseFlight($textPDF, Email $email)
    {
        $r = $email->add()->flight();
        $r->ota()
            ->phone($this->re("#{$this->t('BCD Travel phone number')}[ ]+([\d\-\+\(\) ]{5,})#", $textPDF),
                $this->t('BCD Travel phone number'));

        $ticket = $this->re("#Ticket Number[ ]{3,}([\d\-]{5,})#", $textPDF);

        if (!empty($ticket)) {
            $r->issued()
                ->ticket($ticket, false);
        }

        $confNo = $this->re("#{$this->opt($this->t('Booking code'))}[ ]+(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])?[ ]*([A-Z\d]{5,})#",
            $textPDF);

        if (!empty($confNo)) {
            $r->general()
                ->confirmation(
                    $confNo,
                    $this->re("#({$this->opt($this->t('Booking code'))}[ ]+(?:[A-Z\d][A-Z]|[A-Z][A-Z\d]))?[ ]*[A-Z\d]{5,}#",
                        $textPDF),
                    true
                );
        }

        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('File No.'))}[ ]+(\d+)#", $textPDF), $this->t('File No.'))
            ->traveller($this->re("#{$this->opt($this->t('Client'))}:[ ]+(.+)#", $textPDF), true)
            ->status($this->re("#Status of booking[ ]{3,}(.+)#", $textPDF));

        $cost = $this->getTotalCurrency($this->re("#^[ ]*Flight fare[:\s]+(\D+?\d[\d\.\,]+)#um", $textPDF));

        if (isset($cost['Total'])) {
            $r->price()
                ->cost($cost['Total'])
                ->currency($cost['Currency']);
        }
        $tax = $this->getTotalCurrency($this->re("#^[ ]*Flight fare.+ Tax[:\s]+(\D+?\d[\d\.\,]+)#um", $textPDF));

        if (isset($ax['Total'])) {
            $r->price()
                ->tax($tax['Total'])
                ->currency($tax['Currency']);
        }
        $total = $this->getTotalCurrency($this->re("#^[ ]*Flight fare.+ Total cost[:\s]+(\D+?\d[\d\.\,]+)#um",
            $textPDF));

        if (isset($total['Total'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $segmentText = $this->re("#Flight company[^\n]+\n\s+(.+?)\n\n\n#s", $textPDF);
        $segments = $this->splitter("#(^[ ]*{$this->opt($this->t('Flight No.'))})#m", "controlText\n" . $segmentText);
        $airline = $this->re("#Flight company[ ]+(.+?)[ ]+Flight acronym#", $textPDF);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $regExp = "#{$this->opt($this->t('Flight No.'))}\s+" .
                "(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])?\s*(?<flight>\d+)\s+" .
                "(?<date>\d+\/\d+\/\d+)\s+(?<dep>.+?)\s+(?<depTime>\d+:\d+.*)\s+\-\s+" .
                "(?<arr>.+?)\s+(?<arrTime>\d+:\d+.*)\s+{$this->opt($this->t('Flight class.'))}\s+(?<bc>[A-Z]{1,2})\s+" .
                "(?:AIR=)?\s*(.+?)(?:;|\n|$)#";

            if (preg_match($regExp, $segment, $m)) {
                $date = $this->normalizeDate($m['date']);

                if (isset($m['airline']) && !empty($m['airline'])) {
                    $s->airline()
                        ->name($m['airline']);
                } else {
                    $s->airline()
                        ->name($airline);
                }
                $s->airline()
                    ->number($m['flight']);

                $s->departure()
                    ->noCode()
                    ->name($m['dep'])
                    ->date(strtotime($m['depTime'], $date));

                $s->arrival()
                    ->noCode()
                    ->name($m['arr'])
                    ->date(strtotime($m['arrTime'], $date));
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //21/11/2017
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*$#u',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Surname"], $words["Forename"])) {
                if (stripos($body, $words["Surname"]) !== false && stripos($body, $words["Forename"]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t'], '.', ',');
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
