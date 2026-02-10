<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckInForYourFlight extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/it-696053091.eml, frontierairlines/it-699894200.eml, frontierairlines/it-700429499.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    private $detectFrom = "flights@emails.flyfrontier.com";
    private $detectSubject = [
        // en
        'It\'s time to check in for your flight to',
    ];
    private $detectBody = [
        'en' => [
            'check in now for your flight to',
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your confirmation code:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
        ;

        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t(', check in now for your flight'))}]",
            null, true, "/^\s*(.+?)\s*{$this->opt($this->t(', check in now for your flight'))}/");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller, false);
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('Flight Number:'))}]/preceding::text()[normalize-space()][1]/ancestor::*[count(.//text()[normalize-space()]) > 3][position() < 3][.//img][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name('Frontier Airlines')
                ->number($this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Flight Number:'))}][1]",
                    $root, true, "/^\s*{$this->opt($this->t('Flight Number:'))}\s*(\d{1,5})\s*(?:\||$)/"));

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("*[1]/descendant::text()[normalize-space()][2]", $root, true, "/.+\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[1]/descendant::text()[normalize-space()][2]", $root, true, "/(.+)\s*\([A-Z]{3}\)\s*$/"))
            ;

            $date = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Flight Number:'))}][1]",
                $root, true, "/{$this->opt($this->t('Flight Date:'))}\s*(.+?)\s*(?:\||$)/");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 5][{$this->starts($this->t('Trip Time:'))}][1]/preceding::text()[normalize-space()][1]", $root);
            }
            $date = strtotime($date);
            // $date = strtotime($this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 5][{$this->starts($this->t('Trip Time:'))}][1]/preceding::text()[normalize-space()][1]",
            $time = $this->http->FindSingleNode("*[1]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()][2]", $root, true, "/.+\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()][2]", $root, true, "/(.+)\s*\([A-Z]{3}\)\s*$/"))
            ;

            $time = $this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            $duration = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Flight Number:'))}][1]",
                $root, true, "/{$this->opt($this->t('Flight Time:'))}\s*(.+?)\s*(?:\||$)/");

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 5][{$this->starts($this->t('Trip Time:'))}][1]",
                    $root, true, "/^\s*{$this->opt($this->t('Trip Time:'))}\s*(.+)\s*$/");
            }

            $s->extra()
                ->duration($duration);
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
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }
}
