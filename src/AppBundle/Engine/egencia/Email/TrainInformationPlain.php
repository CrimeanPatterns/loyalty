<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainInformationPlain extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = ["no-reply@egencia.com", 'noreply@mail.egencia.com'];

    // Subject Example
    //  Elizabeth K Winship - Amtrak, departs 10:00 AM local time

    public $detectProvider = ['.egencia.com', 'If you need to speak to a travel consultant, call'];
    public $detectBody = [
        "en" => ["Train (Booked)", "Train (Cancelled)"],
    ];

    public $emailSubject;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Train (Booked)' => ["Train (Booked)", "Train (Cancelled)"],
        ],
    ];

    public function parseTrain(Email $email)
    {
        $text = $this->http->Response['body'];

//        $this->logger->debug($text);

        $email->ota()
            ->confirmation($this->re("/\n\s*Itinerary number:\s*(\d{5,})[ ]*\n/", $text));

        $t = $email->add()->train();

        $conf = $this->re("/\n *Confirmation number: *(\w+)(?:\n|\s*$)/", $text);

        if (empty($conf)
            && preg_match("/\n *Itinerary number:.+(?:\n+ *Seat|\s*$)/", $text)
        ) {
            $t->general()
                ->noConfirmation();
        } else {
            $t->general()
                ->confirmation($conf);
        }

        if (preg_match("/\n *Train \(Cancelled\)\n/", $text)) {
            $t->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Segment
        $s = $t->addSegment();

        if (preg_match("/\n *{$this->opt($this->t('Train (Booked)'))}\n\s*(?<service>\S.+)\n *{$this->opt('Train / Bus:')} *(?<number>\d+) *\n *Class: *(?<class>.+)/", $text, $m)) {
            $s->extra()
                ->service($m['service'])
                ->number($m['number'])
                ->cabin($m['class'])
            ;

            if (preg_match("/^(?:.*: )?([A-Z][a-z]*([ -][A-Z][a-z]*){1,3}?) - " . preg_quote($m['service'], '/') . ", departs /", $this->emailSubject, $m)) {
                $t->general()
                    ->traveller($m[1], true);
            }
        }

        if (preg_match("/\n *Seat: *([\dA-Z]+)\n/", $text, $m)) {
            $s->extra()
                ->seat($m[1])
            ;
        }

        if (preg_match("/\n *Coach: *([\dA-Z]+) *(?:\n|$)/", $text, $m)) {
            $s->extra()
                ->car($m[1])
            ;
        }

        foreach (['Dep' => ['Departs'], 'Arr' => ['Arrives']] as $prefix => $texts) {
            // 9-Nov-2017,  8:40 AM, San Francisco, CA (SFO-San Francisco Intl.), Terminal 3
            if (preg_match("/\n *{$texts[0]} *\n *(?<Date>.+\,\s+\d+:\d+(?:\s+[AP]M)?) *\n+\s*(?<Name>.+)/iu", $text, $m)) {
                if ($prefix == 'Dep') {
                    $s->departure()
                        ->name($m['Name'])
                        ->date($this->normalizeDate($m['Date']));
                }

                if ($prefix == 'Arr') {
                    $s->arrival()
                        ->name($m['Name'])
                        ->date($this->normalizeDate($m['Date']));
                }
            }
        }

//        $h->hotel()
//            ->name($this->re("/\n *{$this->opt($this->t('Hotel (Booked)'))}\n\s*(\S.+)/", $text))
//            ->address(preg_replace("/\s+/", ' ', trim(
//                $this->re("/\n *{$this->opt($this->t('Hotel (Booked)'))}\n\s*\S.+\n((?:.*\n+){1,7}?) *Phone Number:/", $text))))
//            ->phone($this->re("/\n *Phone Number: (.+)/", $text))
//            ->fax($this->re("/\n *Fax: (.{5,})/", $text), true, true)

//        if (!empty($h->getHotelName()) && preg_match("/^(?:.*: )?([A-Z][a-z]+([ -][A-Z][a-z]+){1,3}?) - " . preg_quote($h->getHotelName(), '/') . "/", $this->emailSubject, $m)) {
//            $h->general()
//                ->traveller($m[1], true);
//        }
//
//        // Booked
//        $h->booked()
//            ->checkIn($this->normalizeDate($this->re("/\n *Check-in: *(.+)/", $text)))
//            ->checkOut($this->normalizeDate($this->re("/\n *Check-out: *(.+)/", $text)))
//            ->rooms($this->re("/\n *# of rooms: *(\d+)\s*\n/", $text))
//            ->guests($h->getRoomsCount() * $this->re("/\n *Adults per room: *(\d+)\s*\n/", $text))
//        ;
//
//        $rooms = $this->re("/\n *Rooms: *(.+)\s*\n/", $text);
//        if (strlen($rooms) < 100) {
//            $h->addRoom()->setType($rooms);
//        } else {
//            $h->addRoom()->setDescription($rooms);
//        }
//
//        // Program
//        $account = $this->re("/\n *Confirmation number:.*\n+[\w ]+ # ([A-Z\d]{5,})\n/", $text);
//        if (!empty($account)) {
//            $h->program()
//                ->account($account, false);
//        }
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->reFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        $detectedProvider = false;

        foreach ($this->detectProvider as $dProvider) {
            if (strpos($body, $dProvider) !== false) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->SetEmailBody($parser->getPlainBody());

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($this->http->Response['body'], $dBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

//        foreach ($this->detectBody as $lang => $re) {
//            if (strpos($this->http->Response['body'], $re) !== false) {
//                $this->lang = $lang;
//
//                break;
//            }
//        }

        $this->emailSubject = $parser->getSubject();

        $this->parseTrain($email);

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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Mar 14, 2023 , 10:00 am
            "/^\s*([[:alpha:]]+)\s+(\d+)\s*,\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(\s*[ap]m)?)$/i",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        if (preg_match("/^\s*\d+\s+([[:alpha:]]+)\s+\d{4}\s*,\s*(\d{1,2}:\d{2}(?: +[ap]m)?)?\s*$/i", $str)) {
            return strtotime($str);
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
