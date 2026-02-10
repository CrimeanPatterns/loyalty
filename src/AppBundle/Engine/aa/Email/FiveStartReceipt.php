<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FiveStartReceipt extends \TAccountChecker
{
    public $mailFiles = "aa/it-628745982.eml";
    public $subjects = [
        'Five-Star Receipt for Booking',
    ];

    public $lang = 'en';

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fivestarservice@aa.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'American Airlines Five Star Service') !== false
                    && stripos($text, 'Connection Assistance') !== false
                    && stripos($text, 'Order Summary') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                $this->ParseEventPDF($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEventPDF(Email $email, string $text)
    {
        $event = $email->add()->event();

        $event->setEventType(EVENT_EVENT);

        $event->general()
            ->confirmation($this->re("/Booking[#\:\s]*([\d\-]{5,})/", $text));

        $price = $this->re("/{$this->opt($this->t('Total:'))} *(\S{1,3} +\d[\d\.\,]*)\n/", $text);

        if (preg_match("/^\s*(?<currency>\S{1,3})\s*(?<total>\d[\d\.\,]*)\s*$/", $price, $m)) {
            $event->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $travellerText = $this->re("/\n(\s*Passenger Details[\s\:]+(?:.*\n){1,8}?)\s*Airport Details/u", $text);
        $paxTable = $this->splitCols($travellerText, [0, 50]);
        $event->general()
            ->travellers(array_filter(explode("\n", $this->re("/Passenger Details[\:\s]+(.+)/s", $paxTable[0]))));

        $segText = $this->re("/(\w+ Assistance[\s\:]+.+\n *Departure Time[\s\:]*.+)/s", $text);

        if (stripos($segText, 'Connection Assistance') !== false) {
            $arrDate = $this->re("/Assistance[\s\:]+\n.+\b[ ]{5,}(\d+\-\w+\-\d{4})/", $segText);
            $arrTime = $this->re("/Arrival Time[\:\s]*([\d\:]+ *[AP]M)/", $segText);

            $event->setStartDate(strtotime($arrDate . ', ' . $arrTime));

            $depDate = $this->re("/Assistance[\s\:]+\n.+\b[ ]{5,}(\d+\-\w+\-\d{4})/", $segText);
            $depTime = $this->re("/Departure Time[\:\s]*([\d\:]+ *[AP]M)/", $segText);

            $event->setEndDate(strtotime($depDate . ', ' . $depTime));

            $event->setName('American Airlines Five Star Service');

            $guestCount = $this->re("/Five Star Service Desk at.*\n\s*Adults?\:\s*(\d+)/", $text);

            if ($guestCount == null) {
                $guestCount = $this->re("/Five Star Service.*\n.*Adults?\:\s*(\d+)/", $text);
            }
            $event->setGuestCount($guestCount);

            if (preg_match("/Assistance[\s\:]+\n(?<arrCode>[A-Z]{3})\s*\-\s*(?<arrName>.+)\b[ ]{5,}/", $segText, $m)
                || preg_match("/Assistance[\s\:]+\n(?<arrName>.+)\b[ ]{5,}/", $segText, $m)) {
                if (empty($m['arrCode'])) {
                    $connectionCode = $this->re("/Airport\s*Code\:\s*([A-Z]{3})\nAirport Type[\s\:]*Connect/", $segText);
                    $m['arrCode'] = $connectionCode;
                }

                $event->setAddress($m['arrCode'] . ', ' . $m['arrName']);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
