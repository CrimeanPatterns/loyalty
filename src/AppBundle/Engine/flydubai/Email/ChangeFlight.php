<?php

namespace AwardWallet\Engine\flydubai\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangeFlight extends \TAccountChecker
{
    public $mailFiles = "flydubai/it-260379715.eml";
    public $subjects = [
        'Change to the time of your flydubai flight: Booking reference:',
    ];

    public $lang = 'en';
    public $emailDate;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'notifications@flydubai.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'change to the time of your flydubai flight')]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '@flydubai.com') !== false;
    }

    public function ParseEmail(Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('New flight time'))}]")->length == 1
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Original flight time'))}]")->length == 1
        ) {
            $text = implode("\n", $this->http->FindNodes("//text()[preceding::text()[{$this->eq($this->t('New flight time'))}] and following::text()[{$this->eq($this->t('Original flight time'))}]]"));

            $f = $email->add()->flight();

            $f->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference:'))}]",
                    null, true, "/Booking reference:\s*([A-Z\d]{5,7})\s*$/"))
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('First Name:'))}]",
                    null, true, "/First Name:\s*(.+)\s*$/")
                    . ' ' . $this->http->FindSingleNode("//text()[{$this->starts($this->t('Last Name:'))}]",
                    null, true, "/Last Name:\s*(.+)\s*$/"))
            ;

            $s = $f->addSegment();
            $flight = $this->http->FindSingleNode("//text()[{$this->contains($this->t('time of your flydubai flight'))}]",
                null, true, "/flight ([A-Z\d ]+)\./");

            if (preg_match("/^\s*(?<al>[A-Z\d]{2})\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $date = $this->normalizeDate($this->re("/Departure date:\s*(.+)/", $text));
            $time = $this->re("/Departure time:\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*\n/i", $text);
            $s->departure()
                ->code($this->re("/Departure airport:\s*.+?\(([A-Z]{3})\)/", $text))
                ->name($this->re("/Departure airport:\s*(.+?)\s*\([A-Z]{3}\)/", $text))
                ->date((!empty($date) && !empty($time)) ? strtotime($time, $date) : null)
            ;

            $s->arrival()
                ->code($this->re("/Arrival airport:\s*.+?\(([A-Z]{3})\)/", $text))
                ->name($this->re("/Arrival airport:\s*(.+?)\s*\([A-Z]{3}\)/", $text))
            ;

            $date = $this->normalizeDate($this->re("/Arrival date:\s*(.+)/", $text));
            $time = $this->re("/Arrival time:\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*\n/i", $text);

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            } elseif (empty($this->re("/Arrival date/i", $text)) && empty($this->re("/Arrival time/i", $text))) {
                $s->arrival()
                    ->noDate();
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (stripos($parser->getCleanFrom(), 'notifications@flydubai.com') === false) {
            return false;
        }

        $this->emailDate = strtotime("-1 day", strtotime($parser->getDate()));

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
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

    private function strposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = strpos($text, $n);

                if ($pos !== false) {
                    return $pos;
                }
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
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

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // 11 January
            //            '/^\s*[[:alpha:]\-]+[\s,]+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1 $2 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^\s*\d+ ([[:alpha:]]+)\s*$#u", $date, $m)) {
            return EmailDateHelper::parseDateRelative($date, $this->emailDate);
        } elseif (preg_match("#^\s*\d+ ([[:alpha:]]+)\s+\d{4}\s*$#u", $date, $m)) {
            return strtotime($date);
        }

        return null;
    }
}
