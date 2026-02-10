<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
//use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-2533104.eml, alaskaair/it-4029422.eml, alaskaair/it-4912628.eml, alaskaair/it-5133088.eml, alaskaair/it-56705033.eml, alaskaair/it-60096353.eml, alaskaair/it-6225707.eml, alaskaair/it-68213526.eml";

    public $reBody2 = [
        "en" => "Departing",
    ];

    public $reBodyDetect = [
        "en"  => "The details of the change are below",
        "en2" => "The details of the upgrade are below",
        "en3" => "the details of your itinerary have changed",
        "en4" => "change in service has affected your itinerary",
        "en5" => "your flight has been delayed",
        "en6" => "Delayed flight alert.",
        "en7" => "your flight has been cancelled",
        "en8" => "your flight has been canceled",
        "en9" => "One or more of your flights has changed",
    ];

    public static $dictionary = [
        "en" => [
            'Hello'            => ['Hello', 'Dear'],
            'confNumber'       => ['Confirmation code', 'Confirmation Number', 'confirmation code'],
            'status'           => ['your flight has been', 'Your travel plans have', 'Your partner travel plans have', 'details of your itinerary have'],
            'statusVariants'   => ['changed', 'delayed', 'cancelled', 'canceled'],
            'cancelledPhrases' => ['your flight has been cancelled', 'your flight has been canceled'],
        ],
    ];

    public $lang = "en";

    private $reFrom = "notification.alert@alaskaair.com";
    private $reSubject = [
        'en' => 'ALASKA AIRLINES UPDATE:',
        'Schedule change alert',
        'Your travel plans have changed',
        'Your flight has been delayed',
        'Flight Change - There\'s been a Schedule Change ',
        'Flight has been cancelled', 'Flight has been canceled',
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) !== false) {
            foreach ($this->reSubject as $re) {
                if (stripos($headers['subject'], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.alaskaair.com") or contains(@href,".alaskaair.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Happy Travels, Alaska Airlines") or contains(normalize-space(),"call Alaska Airlines") or contains(normalize-space(),"Alaska Airlines. All rights reserved") or contains(.,"www.alaskaair.com")]')->length === 0
        ) {
            return false;
        }

        $body = $parser->getHTMLBody();

        foreach ($this->reBodyDetect as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

//        foreach ($this->reBody2 as $lang => $re) {
//            if (strpos($this->http->Response["body"], $re) !== false) {
//                $this->lang = $lang;
//                break;
//            }
//        }
        $this->parseHtml($email);

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

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $text = preg_replace("/\n+/", "\n", preg_replace("#<.*?>#", "\n", $this->http->Response["body"]));

        $seats = [];
        $passengers = [];
        preg_match_all("#(?<Passenger>\w+,\s+\w+)\s+-\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<FlightNumber>\d+)\s+-\s+[A-Z]{3}\s+to\s+[A-Z]{3}\s+-\s+\w+\s+\d+\s+-\s+(?<Seat>\d+\w)#", $text, $s, PREG_SET_ORDER);

        foreach ($s as $i) {
            $seats[$i['FlightNumber']][] = $i['Seat'];
            $passengers[] = $i['Passenger'];
        }

        if (count($passengers) === 0) {
            if (preg_match("/^[> ]*({$patterns['travellerName']})[ ]*[,!]+[ ]*$\n+^.+change in service has affected your itinerary/mu", $text, $m)
                || preg_match("/^[> ]*{$this->opt($this->t('Hello'))} ({$patterns['travellerName']}) Party,/mu", $text, $m)
                || preg_match("/^[> ]*{$this->opt($this->t('Hello'))} ({$patterns['travellerName']}),/mu", $text, $m)
            ) {
                $passengers[] = $m[1];
            }
        }

        $passengers = array_unique($passengers);

        $f = $email->add()->flight();

        if (preg_match("/{$this->opt($this->t('status'))} ({$this->opt($this->t('statusVariants'))})(?:[,.;:!]|$)/im", $text, $m)) {
            $f->general()->status($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('cancelledPhrases'))}/i", $text, $m)) {
            $f->general()->cancelled();
        }

        $f->general()->travellers($passengers);

        if (preg_match("/\b({$this->opt($this->t('confNumber'))}[:\s#]+)([A-Z\d]{5,})[,. :]*$/im", $text, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $text = preg_replace('/[\s\S]*Updated flight information for\s*([\s\S]+)/', '$1', $text); //it-56705033.eml
        $text = preg_replace('/([\s\S]+)\s+(?:Previous flight information for|Seat Assignments:|For your reference this was )\s*[\s\S]*/', '$1', $text); //it-56705033.eml

        $segments = $this->split("#(.+ \d{1,5}\s*(?:[\n,]\s*[Oo]perated by.+)?\s*\n\s*Departing)#", $text);

        foreach ($segments as $stext) {


            $s = $f->addSegment();

            if (preg_match("#^[ ]*(?<AirlineName>.{2,}?)(?:,\s+Flight)?[ ]+(?<FlightNumber>\d+)[ ]*[\n,]#", $stext, $seg)) {
                $s->airline()
                    ->name($seg['AirlineName'])
                    ->number($seg['FlightNumber'])
                ;

                if (!empty($seg['FlightNumber']) && !empty($seats[$seg['FlightNumber']])) {
                    $s->extra()
                        ->seats($seats[$seg['FlightNumber']]);
                }
            }

            if (preg_match("#[Oo]perated by\s+(?<Operator>.+?)[ ]*\n#", $stext, $seg)) {
                $s->airline()
                    ->operator($seg['Operator']);
            }

            if (preg_match("#Departing[ ]+(?<DepName>.{3,}?)[ ]*\n\s*(?:[ ]*on[ ]+)?(?<DepDate>.{6,}?)[ ]*\n#", $stext, $seg)) {
                if (preg_match("/(.+?)\s*\(([A-Z]{3})\)\s*$/", $seg["DepName"], $m)) {
                    $s->departure()
                        ->code($m[2])
                        ->name($m[1])
                    ;
                } else {
                    $s->departure()
                        ->noCode()
                        ->name($seg['DepName'])
                    ;
                }
                $s->departure()
                    ->date($this->normalizeDate($seg['DepDate']))
                ;
            }

            if (preg_match("#[ ]*Arriving[ ]+(?<ArrName>.{3,}?)[ ]*\n\s*(?:[ ]*on[ ]+)?(?<ArrDate>.{6,}?)[ ]*(?:\n|$)#", $stext, $seg)) {
                if (preg_match("/(.+?)\s*\(([A-Z]{3})\)\s*$/", $seg["ArrName"], $m)) {
                    $s->arrival()
                        ->code($m[2])
                        ->name($m[1])
                    ;
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($seg['ArrName'])
                    ;
                }
                $s->arrival()
                    ->date($this->normalizeDate($seg['ArrDate']))
                ;
            }
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            "#^\s*([^\d\s]+),\s+([^\d\s]+)\s+(\d+),(?:\s*at)?\s+(\d+:\d+\s+[ap]m)\.?$#",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
        ];
        $date = preg_replace($in, $out, $date);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)){
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $date = str_replace($m[1], $en, $date);
//        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
