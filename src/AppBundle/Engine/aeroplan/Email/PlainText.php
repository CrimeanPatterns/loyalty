<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PlainText extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-507000973.eml";
    public $subjects = [
        '[EXTERNAL] Air Canada – ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Fare Quote Details' => ['Fare Quote Details', 'Baggage Allowance and Fees'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aircanada.ca') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Air Canada ticket')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Ticket Status:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger Information'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Routes and Flight Credits'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aircanada\.ca$/', $from) > 0;
    }

    public function ParseFlight(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]{5,})#", $text))
            ->date($this->normalizeDate($this->re("#{$this->opt($this->t('Date Created:'))}\s*(.+)#u", $text)));

        $travellers = [];
        $accounts = [];
        $tickets = [];

        $paxArray = explode("\n", $this->re("/Passenger Information\n*\s*Passenger.*Ticket Number\s*\n*\-+(.*)Routes and Flight Credits/su", $text));

        foreach ($paxArray as $pax) {
            if (preg_match("/^\s*(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]{5,}.*\s+(?<account>\d{5,})\s*(?<ticket>\d{12,})/", $pax, $m)) {
                $travellers[] = $m['traveller'];
                $accounts[] = $m['account'];
                $tickets[] = $m['ticket'];
            }
        }

        if (count($travellers) > 0) {
            $f->setTravellers($travellers, true);
        }

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $segText = $this->re("/{$this->opt($this->t('Routes and Flight Credits'))}\s*\n\s*\-+(.+)\n+\s*{$this->opt($this->t('Fare Quote Details'))}\s*\n/su", $text);
        $segText = preg_replace("/(\s*\n\s*\-+\s*\n\s*.+\n\s*{$this->opt($this->t('These flights are operated by'))}[\s\S]+)/u", '', $segText);
        $segText = preg_replace("/(\s*\n\s*\-+\s*\n\s*.+\n\s*{$this->opt($this->t('These flights are operated by'))}[\s\S]+)/u", '', $segText);

        $segments = array_filter(preg_split("/^([\-]+)/m", trim($segText)));

        foreach ($segments as $seg) {
            $s = $f->addSegment();

            if (preg_match("#{$this->opt($this->t('Flight/Class:'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\/#", $seg, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $operator = $this->re("/Operated by\:\s*(.+)/", $seg);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $date = $this->re("#{$this->opt($this->t('Date:'))}\s*(.+)#", $seg);
            $depTime = $this->re("#{$this->opt($this->t('Depart:'))}\s*([\d\:]+)#", $seg);
            $arrTime = $this->re("#{$this->opt($this->t('Arrival:'))}\s*([\d\:]+)#", $seg);

            if (!empty($date) && !empty($depTime)) {
                $s->departure()
                    ->name($this->re("#{$this->opt($this->t('From/To:'))}\s*(.+)\-\s+#u", $seg))
                    ->date($this->normalizeDate($date . ', ' . $depTime))
                    ->noCode();
            }

            if (!empty($date) && !empty($arrTime)) {
                $s->arrival()
                    ->name($this->re("#{$this->opt($this->t('From/To:'))}\s*.+\-\s+(.+)#u", $seg))
                    ->date($this->normalizeDate($date . ', ' . $arrTime))
                    ->noCode();
            }

            $bookingCode = $this->re("#{$this->opt($this->t('Flight/Class:'))}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}\/([A-Z])#", $seg);

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $seats = $this->re("/{$this->opt($this->t('Seats:'))}\s*(\d+[A-Z].*)/", $seg);

            if (!empty($seats)) {
                $s->setSeats(explode(",", $seats));
            }

            if (empty($s->getDepDate()) && empty($s->getAirlineName()) && empty($s->getArrDate())) {
                $f->removeSegment($s);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();
        $this->ParseFlight($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return preg_quote($s);
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

    private function normalizeDate($str)
    {
        $in = [
            '/^(\w+)\s*(\d+)\,\s*(\d{4})\s*\,\s*([\d\:]+)$/u', // Sep 17, 2023, 16:00
            '/^(\w+)\s*(\d+)\,\s*(\d{4})$/u', // Sep 17, 2023
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
