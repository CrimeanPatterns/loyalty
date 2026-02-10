<?php

namespace AwardWallet\Engine\aplus\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class BanyanTreeConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Our Location'              => 'Our Location',
            'Number of Adults/Children' => ['Number of Adults/Children', 'Number of Aduts / Children'],
            'Accommodation Type'        => ['Accommodation Type', 'Accomodation Type'],
            'Number of Rooms'           => ['Number of Rooms', 'Number of Villas/Suites'],
        ],
    ];

    private $detectFrom = ['@stay.banyantree.com', '@garrya.com'];
    // private $detectFrom = '@stay.banyantree.com';
    private $detectSubject = [
        // en
        'Confirmation',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:banyantree|garrya)\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false) {
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['banyantree.com', 'garrya.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['banyantree.com', 'garrya.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Our Location']) && $this->http->XPath->query("//*[{$this->contains($dict['Our Location'])}]")->length > 0
                && !empty($dict['Number of Adults/Children']) && $this->http->XPath->query("//*[{$this->contains($dict['Number of Adults/Children'])}]")->length > 0
            ) {
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
            if (!empty($dict["Our Location"])
             && $this->http->XPath->query("//*[{$this->contains($dict['Our Location'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t("Confirmation Number")))
            ->traveller(preg_replace("/^\s*(Dr|Mr|Mrs|Ms)\.? /", "", $this->nextTd($this->t("Guest Name"))))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Cancellation Policy'))}])][last()]"))
        ;

        // Hotel
        $locationXpath = "//text()[{$this->eq($this->t('Our Location'))}]/following::text()[normalize-space()][1]/ancestor::*[count(.//tr[not(tr)][normalize-space()]) = 2][count(.//img) = 2][not(.//text()[{$this->eq($this->t('Our Location'))}])][1]";
        $h->hotel()
            ->name($this->http->FindSingleNode($locationXpath . "//tr[not(.//tr)][2]//a"))
            ->address($this->http->FindSingleNode($locationXpath . "//tr[not(.//tr)][1]"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t("Arrival Date"))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t("Departure Date"))))
            ->rooms($this->nextTd($this->t("Number of Rooms")))
            ->guests($this->nextTd($this->t("Number of Adults/Children"), "/^\s*(\d+)\s*\/\s*\d+\s*$/"))
            ->kids($this->nextTd($this->t("Number of Adults/Children"), "/^\s*\d+\s*\/\s*(\d+)\s*$/"))
        ;

        // Rooms
        $r = $h->addRoom();
        $r
            ->setType($this->nextTd($this->t("Accommodation Type")))
            ->setRateType($this->nextTd($this->t("Rate Type")))
        ;

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $nights = date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');
            $rates = $this->http->FindNodes("//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Daily Rate'))}]]/*[normalize-space()][2]//tr[not(.//tr)]");

            if ($nights == count($rates)) {
                $r->setRates($rates);
            }
        }

        $this->detectDeadLine($h);

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match('/(?:^\s*|\.\s+)Free cancellation up to (?<days>\d+ days) prior to arrival/', $cancellationText, $m)
            || preg_match('/(?:^\s*|\.\s+)Free cancellation up to (?<hours>\d+ hours) prior to arrival/', $cancellationText, $m)
        ) {
            if (!empty($m['days'])) {
                $h->booked()->deadlineRelative('+' . $m['days'], '00:00');
            } elseif (!empty($m['hours'])) {
                $h->booked()->deadlineRelative('+' . $m['hours'], '00:00');
            }
        }

        if (
            preg_match('/It is non-cancellable, non-refundable and no changes allowed/', $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function nextTd($field, $regexp = null)
    {
        return $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($field)}]]/*[normalize-space()][2]",
            null, true, $regexp);
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
