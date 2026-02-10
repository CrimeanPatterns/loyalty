<?php

namespace AwardWallet\Engine\ebuckst\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationBus extends \TAccountChecker
{
    // the same as in BookingConfirmationPDF, only parse html body
    public $mailFiles = "ebuckst/it-240351687.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    private $detectSubject = [
        // en
        'Booking Confirmation For Booking Ref:',
    ];
    private $detectBody = [
        'en' => [
            'Trip summary:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ebucks.com') !== false;
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
            $this->http->XPath->query("//img[{$this->contains(['.ebucks.com'], '@src')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['The eBucks Travel Team'])}]")->length === 0
        ) {
            return false;
        }

        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        if (count($pdfs) > 0) {
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
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        if (count($pdfs) > 0) {
            $this->logger->debug('Contains pdf. Go to BookingConfirmationPDF');

            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["FROM"]) && $this->http->XPath->query("//*[{$this->eq($dict['FROM'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t("eBucks reference:"))}]",
                null, true, "/:\s*(\d+[\-]?\d{5,})\s*$/"));

        // Bus
        $b = $email->add()->bus();

        // General
        $b->general()
            ->noConfirmation()
            ->travellers(preg_replace("/^(?:Mrs|Mr|Miss|Mstr|Ms|Dr)\s+/", '',
                $this->http->FindNodes("//text()[{$this->eq($this->t('Traveller details:'))}]/ancestor::table[{$this->contains($this->t('Ticket No:'))}][1]//tr/td[1][not({$this->contains($this->t('Traveller details:'))})]")));

        // Issued
        $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Traveller details:'))}]/ancestor::table[{$this->contains($this->t('Ticket No:'))}][1]//tr/td[position() > 1]",
            null, "/^\s*[A-Z\d]{5,}\s*$/"));

        if (!empty($tickets)) {
            $b->setTicketNumbers($tickets, false);
        }

        // Segments
        $xpath = "//text()[{$this->eq($this->t('FROM'))}]/ancestor::tr[1]/ancestor::*[1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $b->addSegment();

            $segXpath = ".//text()[{$this->eq($this->t('FROM'))}]/ancestor::tr[1]/following-sibling::tr";
            // Depart
            $col = 1 + count($this->http->FindNodes(".//*[self::td or self::th][not(.//td) and not(.//th)][{$this->eq($this->t('FROM'))}]/preceding-sibling::*", $root));
            $s->departure()
                ->name($this->http->FindSingleNode($segXpath . "[1]/td[{$col}]", $root))
//                ->code($this->http->FindSingleNode($segXpath . "[2]/td[{$col}]", $root))
                ->geoTip("Africa")
                ->date($this->normalizeDate($this->http->FindSingleNode($segXpath . "[3]/td[{$col}]", $root)))
            ;

            // Arrival
            $col = count($this->http->FindNodes(".//*[self::td or self::th][not(.//td) and not(.//th)][{$this->eq($this->t('TO'))}]/preceding-sibling::*", $root));

            if ($col > 0) {
                $col++;
            }
            $s->arrival()
                ->name($this->http->FindSingleNode($segXpath . "[1]/td[{$col}]", $root))
//                ->code($this->http->FindSingleNode($segXpath . "[2]/td[{$col}]", $root))
                ->geoTip("Africa")
                ->date($this->normalizeDate($this->http->FindSingleNode($segXpath . "[3]/td[{$col}]", $root)))
            ;

            // Extra
            $node = $this->http->FindSingleNode($segXpath . "[3][count(*[normalize-space()]) = 3]/*[normalize-space()][2]", $root);

            if (preg_match("/^\s*(?<duration>(?: *\d+ *[hm]+)+)\s*$/ui", $node, $m)) {
                $s->extra()
                    ->duration($m['duration'])
                ;
            }

            $s->extra()
                ->noNumber();
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
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 16:35 Fri, 21 Oct '22
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+[[:alpha:]\-]+\s*,\s+(\d+)\s+([[:alpha:]]+)\s+\'\s*(\d{2})\s*$/ui',
        ];
        $out = [
            '$2 $3 20$4, $1',
        ];

        $date = preg_replace($in, $out, $date);

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
