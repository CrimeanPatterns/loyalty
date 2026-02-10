<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass2 extends \TAccountChecker
{
    public $mailFiles = "aa/it-95883284.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            //            "Take a screenshot of your boarding pass" => "",
            //            "Departs" => "",
            //            "Record locator:" => "",
        ],
    ];
    private $detectSubjects = [
        'en' => [
            ' Boarding Pass - ', // CHRISTOPHER KOKESH Boarding Pass - AA2251
        ],
    ];

    private $detectBody = [
        'en' => ['Here is your boarding pass for flight '],
    ];

    public function detectEmailFromProvider($from)
    {
        $emails = ['no-reply@info.email.aa.com'];

        foreach ($emails as $email) {
            if (stripos($from, $email) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $xpath = "//text()[" . $this->starts($this->t("Take a screenshot of your boarding pass")) . "]/ancestor::*[" . $this->contains($this->t("Departs")) . "][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->confirmation($this->http->FindSingleNode("(./following::text()[{$this->eq($this->t('Record locator:'))}])/following::text()[normalize-space()][1]", $root))
                ->traveller(preg_replace("/(.+?) *\/ *(.+)/", "$2 $1",
                    $this->http->FindSingleNode("(./following::text()[{$this->eq($this->t('Record locator:'))}])/preceding::text()[normalize-space()][1]", $root)))
            ;

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode(".//tr[*[1][{$this->eq($this->t('Flight'))}]]/following-sibling::tr[1]/*[1]", $root, true, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode(".//tr[*[1][{$this->eq($this->t('Flight'))}]]/following-sibling::tr[1]/*[1]", $root, true, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/"))
            ;

            // Departure
            $date = $this->http->FindSingleNode("(./following::text()[{$this->eq($this->t('Record locator:'))}])/following::text()[normalize-space()][2]", $root);
            $time = $this->http->FindSingleNode(".//tr[*[2][{$this->eq($this->t('Departs'))}]]/following-sibling::tr[1]/*[2]", $root, true, "/^\s*(\d{1,2}:\d{2}\s*[AP]M)\s*$/");

            $s->departure()
                ->code($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Take a screenshot of your boarding pass")) . "]/preceding::text()[normalize-space()][1]", $root, null,
                    "/^\s*([A-Z]{3}) to [A-Z]{3}\s*$/"))
                ->date(strtotime((!empty($date) && !empty($time)) ? $date . ', ' . $time : null))
                ->terminal($this->http->FindSingleNode(".//tr[*[2][{$this->eq($this->t('Terminal'))}]]/following-sibling::tr[1]/*[2]", $root))
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Take a screenshot of your boarding pass")) . "]/preceding::text()[normalize-space()][1]", $root, null,
                    "/^\s*[A-Z]{3} to ([A-Z]{3})\s*$/"))
                ->noDate();

            // Extra
            $s->extra()
                ->seat($this->http->FindSingleNode(".//tr[*[3][{$this->eq($this->t('Seat'))}]]/following-sibling::tr[1]/*[3]", $root));

            $img = $this->http->FindSingleNode(".//img[contains(@src, 'https://www.aa.com/content/boardingpass/barcodes')]/@src", $root);

            if (!empty($img)) {
                $bp = $email->add()->bpass();

                $bp
                    ->setDepCode($s->getDepCode())
                    ->setDepDate($s->getDepDate())
                    ->setFlightNumber($s->getAirlineName() . '' . $s->getFlightNumber())
                    ->setUrl($img)
                    ->setTraveller($f->getTravellers()[0][0])
                    ->setRecordLocator($f->getConfirmationNumbers()[0][0])
                ;
            }
        }
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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
}
