<?php

namespace AwardWallet\Engine\bcferries\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "bcferries/it-358487401.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Booking Cancellation' => ['Booking Cancellation', 'Your reservation has been cancelled'],
            'Total'                => ['Total', 'Products and Fees'],
        ],
    ];

    private $detectFrom = "no_reply@bcferries.com";
    private $detectSubject = [
        // en
        'Your booking is confirmed:',
    ];
    private $detectBody = [
        'en' => [
            'This is a transactional or informational email that contains important details',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['.bcferries.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['British Columbia Ferry Services Inc'])}]")->length === 0
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
        $xpath = "//text()[normalize-space()='TIME/DATE']/ancestor::*[contains(., 'Booking Holder')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $f = $email->add()->ferry();

            // General
            $f->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z]\d{5,})\s*$/"))
                ->traveller($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Booking Holder'))}]/following::text()[normalize-space()][1]", $root));

            if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('Booking Cancellation'))}])[1]"))) {
                $f->general()
                    ->cancelled()
                    ->status('Cancelled');
            }

            // Program
            $account = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Customer Number:'))}]",
                $root, true, "/:\s*(\d{5,})\s*$/");

            if (!empty($account)) {
                $f->program()
                    ->account($account, false);
            }

            // Price
            if (!$f->getCancelled()) {
                $total = $this->http->FindSingleNode(".//tr[not(.//tr)][*[normalize-space()][1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]",
                    $root, true, "/^\s*\\$ ?(\d[\d,.]*)\s*$/");

                if (!empty($total)) {
                    $f->price()
                        ->total(PriceHelper::parse($total, 'USD'))
                        ->currency('USD');
                }
            }

            $s = $f->addSegment();

            $xpathD = ".//tr[preceding::tr[1][count(.//text()[normalize-space()]) = 2 and .//text()[normalize-space()='TIME/DATE'] and .//text()[normalize-space()='DEPARTS']]][count(.//td[not(.//td)][normalize-space()]) = 2]//td[not(.//td)][normalize-space()]";
            $dep = $this->http->FindNodes($xpathD, $root);

            if (count($dep) == 2) {
                $s->departure()
                ->name($dep[0])
                ->date($this->normalizeDate($dep[1]));
            }

            $xpathA = ".//tr[preceding::tr[1][count(.//text()[normalize-space()]) = 2 and .//text()[normalize-space()='TIME/DATE'] and .//text()[normalize-space()='ARRIVES']]][count(.//td[not(.//td)][normalize-space()]) = 2]//td[not(.//td)][normalize-space()]";
            $arr = $this->http->FindNodes($xpathA, $root);

            if (count($arr) == 2) {
                $s->arrival()
                ->name($arr[0])
                ->date($this->normalizeDate($arr[1]));
            }

            if (!$f->getCancelled()) {
                $ferry = $this->http->FindSingleNode(".//td[not(.//td)][{$this->starts(['Ferry:'])}]", $root, true, "/Ferry:\s*(.+)\s*$/");
                $s->extra()
                    ->vessel($ferry);
            }

            $fxpath = ".//text()[normalize-space()='Fare Information'][1]/following::text()[normalize-space()][1]/ancestor::table[1]//tr[not(.//tr)]";
            $fNodes = $this->http->XPath->query($fxpath, $root);

            foreach ($fNodes as $fr) {
                $count = $this->http->FindSingleNode("*[1]", $fr, true, "/^\s*(\d+)x\s*$/");
                $name = $this->http->FindSingleNode("*[2]", $fr);

                if (preg_match("/passenger vehicle/", $name)) {
                    for ($i = 1; $i <= $count; $i++) {
                        $s->addVehicle()
                            ->setType($name);
                    }
                } elseif (preg_match("/^\s*\d+\+\s*years\s*$/", $name, $m)) {
                    // 12+ years
                    $s->booked()
                        ->adults(($s->getAdults() ?? 0) + $count);
                } elseif (preg_match("/^\s*\d+\-\d+\s*years\s*$/", $name, $m)) {
                    // 0-4 years
                    $s->booked()
                        ->kids(($s->getKids() ?? 0) + $count);
                }
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
//        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            //            // 01:00 PM12/May/2023
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*(\d{1,2})\\/([[:alpha:]]+)\\/(\d{4})\s*$/ui',
        ];
        $out = [
            '$2 $3 $4, $1',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r($date, true));

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
