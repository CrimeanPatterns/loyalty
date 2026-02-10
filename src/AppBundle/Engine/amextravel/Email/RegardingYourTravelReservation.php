<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RegardingYourTravelReservation extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Departing:'        => 'Departing:',
            'Arriving:'         => 'Arriving:',
            'Response By Email' => ['Response By Email', 'Customer By Email'],
        ],
    ];

    private $detectFrom = "ScheduleChangeCX@amextravel.com";
    private $detectSubject = [
        // en
        'Regarding your American Express Travel reservation',
    ];
    private $detectBody = [
        'en' => [
            'There has been a change to your travel itinerary ',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]amextravel\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'American Express Travel') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains(['Valued American Express Customer'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Departing:"], $dict["Arriving:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Departing:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Arriving:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $text = implode("\n", $this->http->FindNodes("//text()[count(preceding::text()[{$this->starts($this->t('Response By Email'))}]) = 1]"));

        if (preg_match("/\n\s*OPTION 2\s*\n/", $text)) {
            return false;
        }

        // travel Agency
        $conf = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Regarding your American Express Travel reservation'))}])[1]",
            null, true, "/{$this->opt($this->t('Regarding your American Express Travel reservation'))}\s+(\d{4}-\d{4})\b/");

        $email->ota()
            ->confirmation($conf);

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $year = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date Created'))}]",
            null, true, "/:\s*\d{1,2}\W\d{1,2}\W(\d{4})\b/");
        $segments = $this->split("/(\n[^-\n]+?\s*-\s*[^-\n]+(?:\s*[A-Z])?\s*-\s*[^-\n]+(?:\s*-\s*[^-\n]+)?\s+{$this->opt($this->t('Departing:'))})/", $text);
        // $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $date = null;
            $re = "/^\s*(?<date>[^-\n]+)\s*-\s*(?<al>.+) (?<fn>\d{1,5})(?:\s+[A-Z])?(?<cabin>\s*-\s*[^-\n]+)?\s*-\s*(?<duration>[^-\n]+)\s+{$this->opt($this->t('Departing:'))}/";

            if (preg_match($re, $sText, $m)) {
                $date = $this->normalizeDate($m['date'] . ' ' . $year);

                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->extra()
                    ->duration($m['duration']);

                if (!empty($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }

            if (preg_match("/{$this->opt($this->t('Departing:'))}\s*(?<name>.+?)[\s,]*\((?<code>[A-Z]{3})\)\s*at\s+(?<time>\d+\s*:\s*\d+(\s*[ap]m)?)\s*(?:\n|\()/", $sText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($date ? strtotime(preg_replace('/\s+/', '', $m['time']), $date) : null)
                ;
            }

            if (preg_match("/{$this->opt($this->t('Arriving:'))}\s*(?<name>.+?)[\s,]*\((?<code>[A-Z]{3})\)\s*at\s+(?<time>\d+:\d+( *[ap]m)?)\s*(?:\n|\()/", $sText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($date ? strtotime(preg_replace('/\s+/', '', $m['time']), $date) : null)
                ;
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1($m['week']);
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }
        // $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
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
}
