<?php

namespace AwardWallet\Engine\amtrak\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "amtrak/it-143065698.eml, amtrak/it-183530128.eml, amtrak/it-8736083.eml";
    public $reFrom = "etickets@amtrak.com";
    public $reSubject = [
        "en" => "Amtrak: eTicket and Receipt for Your",
    ];

    public $reBody2 = [
        "en" => ["Amtrak tickets may only", "Change Summary - Ticket Number"],
    ];

    public static $dictionary = [
        "en" => [
            "TRAIN"            => ["TRAIN", 'Train'],
            "totalPrice"       => ["Total Charged by Amtrak", "Revised Fare", "Total Charged"],
            "cancelledPhrases" => ["Cancelled Trip Details", "Canceled Trip Details"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email): void
    {
        $segmentsName = array_merge((array) $this->t("TRAIN"), (array) $this->t("BUS"), (array) $this->t("FERRY"));
        $segments = $this->http->XPath->query("//text()[{$this->starts($segmentsName)}]/ancestor::*[1]");

        $xpathDepartElement = "*[{$this->eq(['ChangeSummaryDepart', strtolower('ChangeSummaryDepart'), strtoupper('ChangeSummaryDepart')], 'normalize-space(@class)')}]";
        $departNodes = $this->http->XPath->query("//{$xpathDepartElement}[{$this->starts($this->t('Depart'))}]");
        $periodNodes = $this->http->XPath->query("//{$xpathDepartElement}[{$this->starts($this->t('Valid Travel Period'))}]");

        if ($segments->length === 0 && $departNodes->length === 0 && $periodNodes->length > 0
            && $periodNodes->length === $this->http->XPath->query("//{$xpathDepartElement}[normalize-space()]")->length
        ) {
            // it-582834956-junk.eml
            $email->setIsJunk(true);

            return;
        }

        foreach ($segments as $root) {
            $name = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(?:{$this->preg_implode($this->t("TRAIN"))})\s+(\d+):#", $name)) {
                if (!isset($train)) {
                    $train = $email->add()->train();
                }
                $s = $train->addSegment();
            } elseif (preg_match("#(?:{$this->preg_implode($this->t("BUS"))})\s+(\d+):#", $name)) {
                if (!isset($bus)) {
                    $bus = $email->add()->bus();
                }
                $s = $bus->addSegment();
            } elseif (preg_match("#(?:{$this->preg_implode($this->t("FERRY"))})\s+(\d+):#", $name)) {
                if (!isset($ferry)) {
                    $ferry = $email->add()->ferry();
                }
                $s = $ferry->addSegment();
            } else {
                continue;
            }

            $number = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]",
                $root, true, "#(?:{$this->preg_implode($segmentsName)})\s+(\d+):#");

            if (stripos($s->getId(), 'ferry') !== false) {
                /** @var \AwardWallet\Schema\Parser\Common\FerrySegment $s */
                $s->extra()->vessel($number);
            } else {
                $s->extra()->number($number);
            }

            $depName = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('Depart'))})][last()]", $root, true, "#(?:{$this->preg_implode($segmentsName)})\s+\d+\:\s*(.+)\s+to\s+#")
            ?? $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root, true, "#(?:{$this->preg_implode($segmentsName)})\s+\d+:\s+(.*?)\s+-\s+#");

            $s->departure()
                ->name($depName);

            $depDate = strtotime($this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Depart'))}][1]", $root, true, "/{$this->opt($this->t('Depart'))}\s+(.+)/")));

            if (empty($depDate)) {
                $depDate = strtotime($this->normalizeDate($this->http->FindSingleNode("following::text()[{$this->contains($this->t('Depart'))}][1]", $root, true, "/{$this->opt($this->t('Depart'))}\s+(.+)/")));
            }

            $s->departure()
                ->date($depDate);

            $arrName = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('Depart'))})][last()]", $root, true, "#(?:{$this->preg_implode($segmentsName)})\s+\d+:\s*.+\s+to\s+(.+)#")
            ?? $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root, true, "#(?:{$this->preg_implode($segmentsName)})\s+\d+:\s+.*?\s+-\s+(.+)#");

            $arrName = preg_replace("/\s*\([^\)]*(way|trip)\)\s*$/i", '', $arrName);
            $s->arrival()
                ->name($arrName)
                ->noDate();

            $seatText = '';
            $nextTexts = $this->http->FindNodes("following::text()[normalize-space()]", $root);

            foreach ($nextTexts as $text) {
                if (preg_match("/^{$this->opt($segmentsName)}/", $text)) {
                    break;
                } elseif (preg_match("/{$this->opt($this->t('Seat'))}/", $text)) {
                    $seatText = $text;

                    break;
                }
            }

            foreach ($nextTexts as $text) {
                if (preg_match("/^{$this->opt($segmentsName)}/", $text)) {
                    break;
                } elseif (preg_match("/^{$this->opt($this->t('Car'))} /", $text)) {
                    $seatText = $text;

                    break;
                }
            }

            if (preg_match_all("/\s+(\d+[A-Z])\b/", $seatText, $m)) {
                $s->setSeats($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Car'))}\s*(\d+)\b/", $seatText, $m)) {
                $s->setCarNumber($m[1]);
            }
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Number'))}]", null, true, "/{$this->opt($this->t('Reservation Number'))}\s+-\s+(\w+)$/");
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Purchased:'))}]", null, true, "/{$this->opt($this->t('Purchased:'))}\s*(.{3,})$/")));
        $travellers = array_map('trim', explode(",", $this->nextText("Passengers")));

        $tickets = $this->http->FindNodes("//text()[{$this->contains($this->t('Ticket Number'))}]", null, "/{$this->opt($this->t('Ticket Number'))}\s+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})$/");
        $cancelled = $this->http->XPath->query("//text()[{$this->eq($this->t("cancelledPhrases"))}]")->length > 0;

        foreach ($email->getItineraries() as $it) {
            /** @var \AwardWallet\Schema\Parser\Common\Train $it */
            if (empty($it->getSegments())) {
                continue;
            }

            $it->general()
                ->confirmation($confirmation)
                ->date($resDate)
                ->travellers($travellers, true)
            ;

            if ($tickets) {
                $it->setTicketNumbers($tickets, false);
            }

            if ($cancelled) {
                $it->general()->cancelled();
            }
        }

        $totalPrice = $this->nextText($this->t("totalPrice"));

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $138.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(.,"Amtrak.com")]')->length === 0) {
            return false;
        }

        foreach ($this->reBody2 as $phrases) {
            if ($this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*[\]\[\d]+\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($textPdf)) {
                $this->logger->debug("pdf exists, go to ETicketPdf");
                $this->logger->debug("because there are arrDate in pdf-attachment");

                return $email;
            }
        }

        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $phrases) {
            if ($this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0) {
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

    private function nextText($field, $root = null, $n = 1): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str): string
    {
        $in = [
            //9:00 AM, Thursday, July 6, 2017
            "#^(\d+:\d+\s+[AP]M),\s+[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
            //08/14/2020 2:44 PM PT
            "/^\s*(\d{1,2}\/\d{1,2}\/\d{4})\s+(\d+:\d+(?:\s*[AP]M\b)?)\D*\s*$/i",
        ];
        $out = [
            "$3 $2 $4, $1",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field));
    }
}
