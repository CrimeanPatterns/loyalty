<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class DeltaPlain extends \TAccountChecker
{
    public $mailFiles = "delta/it-224219131.eml, delta/it-229956862.eml, delta/it-231756110.eml, delta/it-671946774.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (preg_match("/Delta(?: Air Lines)? ([A-Z\d]{5,7}) \| [A-Z]{3} to [A-Z]{3}\b/", $parser->getSubject())
        || preg_match("/Delta(?: Air Lines)? ([A-Z\d]{5,7}) \| [A-Z]{3} to [A-Z]{3}\b/", $body)) {
            // Delta GC5ECP | DFW to ATL
            // Delta Air Lines G5VONT | ROC to RAP HOWARD MAY

            if (preg_match("/<[[:alpha:]]{1,5}>/", $body)) {
                $body = preg_replace("/<[^>]*>/s", "\n", $body);
            }

            $posC = strpos($body, 'Confirmation #:');
            $posF = strpos($body, 'Flight #');

            if ($posF === false) {
                $posF = strpos($body, 'Flight#');
            }

            if ($posC !== false && $posF !== false && ($posF - $posC) < 50) {
                return true;
            }
        }

        $pos = strpos($body, 'Confirmation #:');
        $pos = $pos - 50;
        $pos = ($pos < 0) ? 0 : $pos;

        if (preg_match("/^.*Confirmation #:\s*[A-Z\d]+\s+Flight *\d+ of \d+\s*\| DL\d+\s+-{6,}\s+Departs +[A-Z]{3}\s+on (\w+[,.]? *){2,3} *\d{4}/s", substr($body, $pos, 200))) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();

        if (preg_match("/<[[:alpha:]]{1,5}>/", $body)) {
            $body = preg_replace("/<[^>]*>/s", "\n", $body);
        }
        $body = preg_replace("/[^\S\n]/u", " ", $body);

        $this->parseEmail($email, $body);

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

    private function parseEmail(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Confirmation #:\s*([A-Z\d]{5,7})\s+/", $text));

        $segments = preg_split("/Flight *(?:#|\d+ of \d+\s*\|)\s*/", $text);
        array_shift($segments);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*[-]+/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            if (preg_match("/\s+(?:Departing|Departs) (?<code>[A-Z]{3})\s+on\s+(?<date>.+)/", $sText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }

            if (preg_match("/\s+(?:Arriving|Arrives) (?<code>[A-Z]{3})\s+on\s+(?<date>.+)/", $sText, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            // Sunday, Jul 21, 2024 at 3:25 PM
            '/^\s*[[:alpha:]]+\s*,\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*at\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
