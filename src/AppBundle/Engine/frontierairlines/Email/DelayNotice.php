<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Schema\Parser\Email\Email;

class DelayNotice extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/it-697237459.eml, frontierairlines/it-701090850.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'with confirmation code' => ['with confirmation code', 'Confirmation Code:'],
            'has been delayed'       => ['is now departing from gate', 'has been delayed'],
        ],
    ];

    private $detectFrom = "info@reservation.flyfrontier.com";
    private $detectSubject = [
        // en
        'Flight delay notice. Confirmation Code',
        ' has been delayed.',
        'Important Travel Information: Your gate has been changed',
    ];
    private $detectBody = [
        'en' => [
            'has been delayed',
            'is now departing from gate',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flyfrontier\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//*[{$this->contains(['www.flyfrontier.com', 'Frontier Airlines. All Rights Reserved'])}]")->length === 0) {
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

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('with confirmation code'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]",
                null, true, "/^\s*{$this->opt($this->t('Hello '))}\s*(.+?),\s*$/"), false)
        ;

        // Segments
        $s = $f->addSegment();
        $segText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('with confirmation code'))}]/ancestor::*[{$this->contains($this->t('has been delayed'))}][1]//text()[normalize-space()]"));
        $re = "/flight\s*#(?<fn>\d{1,5})\s+with confirmation code\s+(?<conf>[A-Z\d]{5,7})\s+from\s+(?<dCode>[A-Z]{3}) *- *(?<dName>.+)\nto\n(?<aCode>[A-Z]{3}) *- *(?<aName>.+)\non\n(?<date>.+)\n\s*has been delayed/u";
        $re2 = "/flight\s*(?<fn>\d{1,5})\s+from\s+(?<dCode>[A-Z]{3}) *- *(?<dName>.+)\nto\n(?<aCode>[A-Z]{3}) *- *(?<aName>.+)\non\s*(?<date>.+?)\s+and is now departing from gate/u";

        if (preg_match($re, $segText, $m) || preg_match($re2, $segText, $m)) {
            // Airline
            $s->airline()
                ->name('Frontier Airlines')
                ->number($m['fn'])
            ;

            // Departure
            $s->departure()
                ->code($m['dCode'])
                ->name($m['dName'])
            ;
            $date = strtotime($m['date']);
            $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated departure time:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date))
                ;
            } elseif (!empty($date) && empty($time)) {
                $s->departure()
                    ->noDate()
                    ->day($date)
                ;
            }

            // Arrival
            $s->arrival()
                ->code($m['aCode'])
                ->name($m['aName'])
                ->noDate()
            ;
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
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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
}
