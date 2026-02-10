<?php

namespace AwardWallet\Engine\airindia\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class Notification extends \TAccountChecker
{
    public $mailFiles = "airindia/it-685161957.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "noreply-notification@airindia.com";
    private $detectSubject = [
        'Web check-in now for flight',
    ];
    private $detectBody = [
        'en' => [
            'Web check-in for your flight',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]airindia\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['airindia.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@airindia.com'])}]")->length === 0
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

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REFERENCE'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));

        // Segment
        $s = $f->addSegment();

        $airline = $this->http->FindSingleNode("//text()[{$this->eq($this->t('FLIGHT NO.'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*$/", $airline, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DATE'))}]/following::text()[normalize-space()][1]");

        $text = implode("\n", $this->http->FindNodes("//img[contains(@src, 'flight-connection')]/ancestor::*[normalize-space()][1]//text()[normalize-space()]"));

        if (preg_match("/^\s*(?<dCode>[A-Z]{3})\n\s*(?<aCode>[A-Z]{3})\n\s*(?<dName>.+)\n\s*(?<dTime>\d{1,2}:\d{2})\n\s*(?<aName>.+)\n\s*(?<aTime>\d{1,2}:\d{2})\s*$/", $text, $m)) {
            $s->departure()
                ->code($m['dCode'])
                ->name($m['dName'])
            ;

            if (!empty($date)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $m['dTime']));
                $s->arrival()
                    ->date(strtotime($date . ', ' . $m['aTime']));
            }
            // Arrival
            $s->arrival()
                ->code($m['aCode'])
                ->name($m['aName'])
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
        $this->logger->debug('date begin = ' . print_r($date, true));

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

        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
//        if (
//            preg_match("/Free cancellation before (?<day>\d+)\-(?<month>\D+)\-(?<year>\d{4}) (?<time>\d+:\d+)\./i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->deadline($this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
//        }
//
//        if (
//            preg_match("/Free cancellation before  (?<date>.+{6,40}), (?<time>\d+:\d+)\./i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->deadline2($this->normalizeDate($m['date'] . ', ' . $m['time']));
//        }
//
//        if (
//            preg_match("/This reservation is non-refundable/i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->nonRefundable();
//        }
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
