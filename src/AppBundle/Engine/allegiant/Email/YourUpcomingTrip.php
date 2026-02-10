<?php

namespace AwardWallet\Engine\allegiant\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourUpcomingTrip extends \TAccountChecker
{
    public $mailFiles = "allegiant/it-69495415.eml, allegiant/it-69900376.eml";
    public $subjects = [
        '/Your upcoming trip is only/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.allegiant.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Allegiant Travel Company')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Flight Details'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departure Airport'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.allegiant\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        return $email;
    }

    public function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation #']/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]{6})/"));

        $xpath = "//text()[normalize-space()='Your Return Flight']/preceding::text()[normalize-space()='Departure Date']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $this->parseFlightSegment($f, $root);
        }

        $xpath = "//text()[normalize-space()='Your Return Flight']/following::text()[normalize-space()='Departure Date']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $this->parseFlightSegment($f, $root);
        }

        if (count($nodes) == 0) {
            $xpath = "//text()[normalize-space()='Your Flight Details']/following::text()[normalize-space()='Departure Date']";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $this->parseFlightSegment($f, $root);
            }
        }

        return true;
    }

    public function parseFlightSegment($f, $root)
    {
        $s = $f->addSegment();

        $s->airline()
            ->name('G4')
            ->number($this->http->FindSingleNode("//text()[normalize-space()='Your Flight Details']/preceding::text()[starts-with(normalize-space(), 'Confirmation #')][1]/following::text()[starts-with(normalize-space(), 'Flight')]", null, true, "/Flight\s*(\d{1,4})/"));

        $depDate = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/\w+\,\s*(.+)\sat/");
        $depTime = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/at\s([\d\:]+\s*A?P?M)/");

        $s->departure()
            ->code($this->http->FindSingleNode("./following::text()[normalize-space()='Departure Airport'][1]/following::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/"))
            ->date($this->normalizeDate($depDate . ', ' . $depTime));

        $arrTime = $this->http->FindSingleNode("./following::text()[normalize-space()='Arrival'][1]/following::text()[normalize-space()][1]", $root, true, "/at\s([\d\:]+\s*A?P?M)/");
        $s->arrival()
            ->code($this->http->FindSingleNode("./following::text()[normalize-space()='Arrival'][1]/following::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/"))
            ->date($this->normalizeDate($depDate . ', ' . $arrTime));

        $arrTerminal = $this->http->FindSingleNode("//text()[normalize-space()='Arrival']/ancestor::td[1]/following::td[normalize-space()][1]", null, true, "/(\w+)\s*Terminal\s*\(/");

        if (!empty($arrTerminal)) {
            $s->arrival()
                ->terminal($arrTerminal);
        }
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+\s*A?P?M)$#ui", //December 24, 2020, 9:28 AM
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
}
