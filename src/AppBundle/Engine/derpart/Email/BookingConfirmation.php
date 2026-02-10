<?php

namespace AwardWallet\Engine\derpart\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    // the same as derpart/BookingConfirmationPdf (parse pdf)
    public $mailFiles = "derpart/it-136514711.eml, derpart/it-136517383.eml";

    private $detectFrom = "@derpart.com";
    private $detectSubject = [
        // de
        'DERPART Buchungsbestätigung', // DERPART Buchungsbestätigung NAETSCHER/CHRISTINA MRS - 11.03.2022 - W9WY5I
    ];
    private $detectBody = [
        'de' => [
            'Bitte überprüfen Sie die Buchungsbestätigung umgehend auf die Korrektheit und Vollständigkeit der Daten und Termine',
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
//            'Confirmation' => 'Confirmation',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'Derpart') === false) {
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
        if ($this->http->XPath->query("//a[{$this->contains(['.derpart.com', '@derpart.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
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
            ->confirmation($this->http->FindSingleNode("//text()[".$this->starts($this->t("Buchungscode Reisebüro:"))."]",
                null, true, "/:\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers(preg_replace("/^\s*(.+?)\s*\/\s*(.+?)(?:\s+(Mrs|Mr))?\s*$/", '$2 $1',
                $this->http->FindNodes("//tr[td[1][".$this->eq($this->t("Reisender / Reisende"))."] and td[2][".$this->eq($this->t("Ticketnummer"))."] ]/following-sibling::*/td[1]")), true)
        ;

        // Issued
        $tickets = $this->http->FindNodes("//tr[td[1][".$this->eq($this->t("Reisender / Reisende"))."] and td[2][".$this->eq($this->t("Ticketnummer"))."] ]/following-sibling::*/td[2]",
            null, "/^\s*([\d\- \/,]{10,})\s*$/");
        if (!empty($tickets)) {
            foreach ($tickets as $row) {
                $f->issued()
                    ->tickets(preg_split("/\s*,\s*/", $row), false);
            }
        }

        // Program
        $accounts = $this->http->FindNodes("//tr[td[1][".$this->eq($this->t("Reisender / Reisende"))."] and td[2][".$this->eq($this->t("Ticketnummer"))."] ]/following-sibling::*/td[2]",
            null, "/^\s*([A-Z\d\- \/,]{5,})\s*$/");
        if (!empty($accounts)) {
            foreach ($accounts as $row) {
                $f->program()
                    ->accounts(preg_split("/\s*,\s*/", $row), false);
            }
        }

        // Segments
        $xpath = "//text()[".$this->eq($this->t("Flug"))."][following::text()[normalize-space()][1][".$this->eq($this->t("Von"))."]]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug('XPath = '.print_r( $xpath,true));
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $airline = $this->http->FindSingleNode(".//tr[not(.//tr)][normalize-space()][2]/td[1]", $root);
            if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\b/", $airline, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $operator = $this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Durchgeführt von"))."]",
                $root, true, "/".$this->opt($this->t("Durchgeführt von"))."\s*(.+)/");
            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }
            $confirmation = $this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Airline ref."))."]/following-sibling::td[normalize-space()][1]",
                $root, true, "/^\s*([A-Z\d]{5,7})\s*$/");
            if (!in_array($confirmation, array_column($f->getConfirmationNumbers(), 0))) {
                $s->airline()
                    ->confirmation($confirmation);
            }

            // Departure
            $dep = $this->http->FindSingleNode(".//tr[not(.//tr)][normalize-space()][2]/td[2]", $root);
            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{3})\)(\s+\S.*)?\s*$/", $dep, $m)) {
                $s->departure()
                    ->code($m[2])
                    ->name($m[1])
                ;
                if (!empty($m[3])) {
                    $s->departure()->terminal(preg_replace("/\s*\bterminal\b\s*/iu", '', $m[3]));
                }
                $s->departure()
                    ->date($this->normalizeDate($this->http->FindSingleNode(".//tr[not(.//tr)][normalize-space()][2]/td[3]", $root)));
            }

            // Arrival
            $arr = $this->http->FindSingleNode(".//tr[not(.//tr)][td[".$this->eq($this->t("Nach"))."] and td[".$this->eq($this->t("Ankunft"))."]]/following::tr[not(.//tr)][normalize-space()][1]/td[2]", $root);
            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{3})\)(\s+\S.*)?\s*$/", $arr, $m)) {
                $s->arrival()
                    ->code($m[2])
                    ->name($m[1])
                ;
                if (!empty($m[3])) {
                    $s->arrival()->terminal(preg_replace("/\s*\bterminal\b\s*/iu", '', $m[3]));
                }
                $s->arrival()
                    ->date($this->normalizeDate($this->http->FindSingleNode(".//tr[not(.//tr)][td[".$this->eq($this->t("Nach"))."] and td[".$this->eq($this->t("Ankunft"))."]]/following::tr[not(.//tr)][normalize-space()][1]/td[3]",
                        $root, true, "/(.+?)\s*".$this->opt($this->t("Dauer:"))."/")));
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode(".//text()[".$this->contains($this->t("Dauer"))."]", $root, true, "/".$this->opt($this->t("Dauer:"))."\s*(.+)/"))
                ->aircraft($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Fluggerät"))."]/following-sibling::td[normalize-space()][1]", $root))
                ->cabin($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Buchungsklasse"))."]/following-sibling::td[normalize-space()][1]",
                    $root, true, "/^\s*(.+?)\s*\([A-Z]{1,2}\)\s*$/"))
                ->bookingCode($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Buchungsklasse"))."]/following-sibling::td[normalize-space()][1]",
                    $root, true, "/^\s*.+?\s*\(([A-Z]{1,2})\)\s*$/"))
                ->meal($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("An Bord"))."]/following-sibling::td[normalize-space()][1]", $root))
            ;
            $seat = $this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Sitzplatz"))."]/following-sibling::td[normalize-space()][1]",
                $root, true, "/".$this->opt($this->t("Sitzplatz"))."\s*(\d{1,3}[A-Z])\s*$/");
            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
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
            // Mi., 02. Feb. 2022 07:40
            '/^\s*[\w\-]+[,.\s]+(\d{1,2})[.]?\s+(\w+)[.]?\s+(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . $en . $m[3];
            }
        }

        return strtotime($date);
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }


}