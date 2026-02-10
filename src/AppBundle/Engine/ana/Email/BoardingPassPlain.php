<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPlain extends \TAccountChecker
{
    public $mailFiles = "ana/it-22850903.eml, ana/it-384602149.eml, ana/it-8937149.eml, ana/it-9081905.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['We will inform you of your boarding pass for', 'We recommend the online check-in', 'You can check in online'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    private $emailDate;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@121.ana.co.jp') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false && strpos($headers['subject'], '[From ANA]') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Information about Boarding Pass') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = stripos($textBody, 'www.ana.co.jp') === false && strpos($textBody, "ANA's Privacy") === false && strpos($textBody, 'ANA/ALL NIPPON AIRWAYS') === false;
        $condition2 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if ($condition1 && $condition2 === false) {
            return false;
        }

        return $this->assignLang($textBody);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        if ($this->assignLang($textBody) === false) {
            return false;
        }

        $this->emailDate = strtotime($parser->getDate());

        $this->parseEmail($email, $textBody);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail(Email $email, $text)
    {
        $start = strpos($text, 'We will inform you of your boarding pass for');

        if ($start !== false) {
            $text = substr($text, $start);
        }

        $end = strpos($text, 'ANA/ALL NIPPON AIRWAYS');

        if ($end !== false) {
            $text = substr($text, 0, $end);
        }

        $f = $email->add()->flight();

        if (preg_match('/-\s*ANA Reservation Number\s*$\s+^\s*([A-Z\d]{5,})$/m', $text, $matches)) {
            $f->general()
                ->confirmation($matches[1]);
        }

        if (preg_match('/-\s*Passenger Name\s*$\s+^\s*(.{2,})/m', $text, $matches)) {
            $f->general()
                ->travellers(preg_replace("/\s(?:Mrs|Mr|Ms)$/", "", [$matches[1]]), true);
        }

        if (preg_match('/-\s*e-Ticket number\s*$\s+^\s*(\d[- \d]*\d{4}[- \d]*)$/miu', $text, $matches)) {
            $f->setTicketNumbers([$matches[1]], false);
        }

        $s = $f->addSegment();

        // your boarding pass for Flight NH0009 on
        if (preg_match('/your Boarding Pass for Flight ([A-Z\d]{2})(\d+) on/iu', $text, $matches)
        || preg_match('/\-Flight Information\s*([A-Z\d]{2})(\d+)/iu', $text, $matches)) {
            $s->airline()
                ->name($matches[1])
                ->number($matches[2]);
        }

        // from New York(JFK) to Tokyo(Narita) as follow
        if (preg_match('/from (.+) to (.+) as follow/', $text, $matches)
        || preg_match('/(.+)\s+-\s+(.+)\s*\n*Date/', $text, $matches)) {
            $s->departure()
                ->name($matches[1])
                ->noCode();

            $s->arrival()
                ->name($matches[2])
                ->noCode();
        }

        $flightInfo = $this->sliceText($text, '- Flight Information', '- About Boarding');

        if (empty($flightInfo)) {
            $flightInfo = $this->sliceText($text, '- Flight Information', '- Online Check-in');
        }

        if (empty($flightInfo)) {
            $flightInfo = $this->sliceText($text, '- Flight Information', '- Boarding');
        }

        if (empty($flightInfo)) {
            $flightInfo = $this->sliceText($text, '-Flight Information', '* ');
        }

        if (empty($flightInfo)) {
            return false;
        }

        $year = date('Y', $this->emailDate);

        if (preg_match('/- Flight Information\s*$\s+^\s*(?<weekDay>[^,.\d\s]{2,})\.?,\s+(?<month>[^,.\d\s]{3,})\.?\s+(?<day>\d{1,2}),\s+(?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?)'
                . '\s+(?<al>[A-Z\d]{2})(?<fn>\d+)\s*\n(?:\s*[A-Z\d]{2}\d{1,5}.*\n)?\s*(?<dep>.+?) - (?<arr>.+)\n/mi', $flightInfo, $matches)) {
            if ($weekDayNumber = WeekTranslate::number1($matches['weekDay'])) {
                $dateDep = EmailDateHelper::parseDateUsingWeekDay($matches['day'] . $matches['month'] . ' ' . $year, $weekDayNumber);
                $s->departure()
                    ->date(strtotime($matches['time'], $dateDep));

                $s->arrival()
                    ->noDate();
            }

            if (empty($s->getFlightNumber())) {
                $s->airline()
                    ->name($matches['al'])
                    ->number($matches['fn']);
            }

            if (empty($s->getDepName())) {
                $s->departure()
                    ->name($matches['dep'])
                    ->noCode();

                $s->arrival()
                    ->name($matches['arr'])
                    ->noCode();
            }

            $this->setBoardingPass($email, $f, $s, $text);
        } elseif (preg_match("/Date\s*\:\s*(?<day>\d+)(?<month>\w+)\s*\n*\s*Scheduled\s*Time\s*\:\s*(?<time>[\d\:]+)/ui", $flightInfo, $m)) {
            $s->departure()
                ->date(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $year . ', ' . $m['time']));

            $s->arrival()
                ->noDate();

            $this->setBoardingPass($email, $f, $s, $text);
        }

        // (C) 05F
        if (preg_match('/^\s*\(([A-Z]{1,2})\)\s+(\d{1,2}[A-Z])\s*$/m', $flightInfo, $matches)) {
            $s->extra()
                ->bookingCode($matches[1]);

            $s->setSeats([$matches[2]]);
        }
    }

    protected function setBoardingPass(Email $email, Flight $f, FlightSegment $s, $text)
    {
        $bp = $email->add()->bpass();
        $bp->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber());
        $bp->setRecordLocator($f->getConfirmationNumbers()[0][0]);
        $url = $this->re("/\-\s*Please confirm the latest information on your flight here\.\n(https\:\/\/www\.ana\.co\..+)/", $text);

        if (empty($url)) {
            $url = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Please confirm the latest information on your flight')]/following::a[normalize-space()='here']/@href");
        }
        $bp->setUrl($url);
        $bp->setDepDate($s->getDepDate());
        $bp->setTraveller($f->getTravellers()[0][0]);
    }

    protected function sliceText($textSource = '', $textStart = '', $textEnd = '')
    {
        if (empty($textSource) || empty($textStart)) {
            return false;
        }
        $start = strpos($textSource, $textStart);

        if (empty($textEnd)) {
            return substr($textSource, $start);
        }
        $end = strpos($textSource, $textEnd, $start);

        if ($start === false || $end === false) {
            return false;
        }

        return substr($textSource, $start, $end - $start);
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
