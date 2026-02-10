<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-200925517.eml";
    public $subjects = [
        // en
        'Your Flight Is Departing Soon',
        'is departing soon',
        // es
        'Tu vuelo saldrá en breve.',
        // it
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            //            'Booking No.' => '',
            'Your Flight Is Departing Soon' => 'Your Flight Is Departing Soon',
            'On Schedule'                   => 'On Schedule',
            //            'Estimated:' => '',
            //            'Scheduled:' => '',
        ],
        "es" => [
            'Booking No.'                   => 'N.º de reserva',
            'Your Flight Is Departing Soon' => 'Tu vuelo saldrá en breve.',
            'On Schedule'                   => 'Según hora prevista',
            'Estimated:'                    => 'Hora estimada:',
            'Scheduled:'                    => 'Hora programada:',
        ],
        "it" => [
            'Booking No.'                   => 'Prenotazione n.',
            'Your Flight Is Departing Soon' => 'Il tuo volo è in partenza a breve',
            'On Schedule'                   => 'In orario',
            'Estimated:'                    => 'Previsto:',
            'Scheduled:'                    => 'In programma:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('from', $headers) && preg_match('/(?:flt|flight)\S*@trip\.com$/i', rtrim($headers['from'], '> ')) > 0) {
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
        if ($this->http->XPath->query("//a[contains(@href, '.trip.com/') or contains(@href, '.trip.com%2F')]")->length < 5) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your Flight Is Departing Soon']) && !empty($dict['On Schedule'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your Flight Is Departing Soon'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['On Schedule'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]trip\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'dateShort' => '\b[[:alpha:]]+\s+\d{1,2}\b', // Sep 28
            'time'      => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking No.'))}]/following::text()[normalize-space()][1]", null, true, '/^\d+$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking No.'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
            $f->general()->noConfirmation();
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('On Schedule'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]", $root);

            if (preg_match("/\s(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $dateDep = $this->normalizeDate($this->http->FindSingleNode("following::text()[{$this->contains($this->t('Estimated:'))}][1]/ancestor::tr[1]/descendant::td[1]", $root, true, "/{$this->opt($this->t('Estimated:'))}\s*({$patterns['dateShort']})$/u"));
            $timeDep = $this->http->FindSingleNode("following::text()[{$this->contains($this->t('Estimated:'))}][1]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, true, "/^{$patterns['time']}$/");

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep))->noCode();
            }

            $dateArr = $this->normalizeDate($this->http->FindSingleNode("following::text()[{$this->contains($this->t('Estimated:'))}][1]/ancestor::tr[1]/descendant::td[2]", $root, true, "/{$this->opt($this->t('Estimated:'))}\s*({$patterns['dateShort']})$/u"));
            $timeArr = $this->http->FindSingleNode("following::text()[{$this->contains($this->t('Estimated:'))}][1]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root, true, "/^{$patterns['time']}$/");

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr))->noCode();
            }

            $airportDep = $this->http->FindSingleNode("following::text()[{$this->contains($this->t('Scheduled:'))}][1]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root);
            $airportArr = $this->http->FindSingleNode("following::text()[{$this->contains($this->t('Scheduled:'))}][1]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root);

            if (preg_match($pattern = "/^(?<name>.{2,}?)\s+T[-\s]*(?<terminal>[A-Z\d]|\d+[A-Z]?)$/", $airportDep, $m)) {
                // Ibiza Airport T2D
                $s->departure()->name($m['name'])->terminal($m['terminal']);
            } else {
                $s->departure()->name($airportDep);
            }

            if (preg_match($pattern, $airportArr, $m)) {
                $s->arrival()->name($m['name'])->terminal($m['terminal']);
            } else {
                $s->arrival()->name($airportArr);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your Flight Is Departing Soon']) && !empty($dict['On Schedule'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your Flight Is Departing Soon'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['On Schedule'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime("+ 2 day", strtotime($parser->getDate()));

        $this->ParseFlight($email);

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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date = ' . print_r($str, true));
        $year = date("Y", $this->date);
        $in = [
            // Sep 8
            "#^\s*([[:alpha:]]+)\s+(\d+)\s*$#",
            // 8 Sep
            "#^\s*(\d+)\s+([[:alpha:]]+?)[.]\s*$#",
        ];
        $out = [
            "$2 $1 {$year}",
            "$1 $2 {$year}",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        $str = EmailDateHelper::parseDateRelative($str, $this->date, false, '%D% %Y%');

        // $this->logger->debug('$str = ' . print_r($str, true));
        return $str;
    }
}
