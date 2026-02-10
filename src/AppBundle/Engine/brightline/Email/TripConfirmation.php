<?php

namespace AwardWallet\Engine\brightline\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripConfirmation extends \TAccountChecker
{
    public $mailFiles = "brightline/it-581668582.eml, brightline/it-682405518.eml, brightline/it-684700676.eml, brightline/it-685568105.eml";
    public $subjects = [
        'Your Trip Confirmation:',
    ];

    public $lang = 'en';
    public $confirmation;
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Departs' => ['Departs', 'DEPARTS'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.gobrightline.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Brightline')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('OUTBOUND TRIP DETAILS'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Boarding Closes'))}]")->length > 0) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'BOARDING CLOSES') !== false
                && stripos($text, 'prior to departure') !== false
                && stripos($text, 'Brightline App') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.gobrightline\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->confirmation = $this->re("/Your Trip Confirmation: ([A-Z\d]{6})\s*(\||$)/", $parser->getSubject());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $type = '';

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->ParseTrainPDF($email, $text);
                $type = 'Pdf';
            }
        } else {
            $this->ParseTrain($email);
            $type = 'Html';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function ParseTrain(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->confirmation);

        $travellers = [];
        $classes = explode(',', strtoupper(implode(',', array_unique(
            $this->http->FindNodes("//text()[{$this->eq($this->t('Class:'))}]/following::text()[normalize-space()][1]")))));

        if (!empty($classes) && $this->http->XPath->query("//tr[{$this->starts($this->t('FARE-'))}]/preceding-sibling::tr[1][{$this->starts($classes)}]")->length == 0) {
            $travellers = $this->http->FindNodes("//tr[{$this->starts($this->t('FARE-'))}]/preceding-sibling::tr[1][contains(., ' ')][descendant-or-self::*/@style[{$this->contains(['font-weight: 700', 'font-weight:700'])}]]");
        }

        if (empty($travellers)) {
            $travellers = array_filter(preg_replace("/^\s*Passenger Name\s*:\s*/", '',
                $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ticket Number:')]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Ticket'))][string-length()>3]")));
        }

        $t->general()
            ->travellers($travellers);

        $t->setTicketNumbers(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ticket Number:')]", null, "/{$this->opt($this->t('Ticket Number:'))}\s*([A-Z\d]+)/")), false);

        $this->Price($t);

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Departs'))}][not(ancestor::*/@style[{$this->contains(['display:none', 'display: none'])}])]");

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $date = $this->http->FindSingleNode("./ancestor::table[starts-with(normalize-space(), 'Boarding Closes')][1]/descendant::text()[starts-with(normalize-space(), 'Boarding Closes')]/following::text()[normalize-space()][1]", $root);

            $depInfo = $this->http->FindSingleNode("./ancestor::td[1]", $root);

            if (preg_match("/^(?<depName>.+)\s+Departs\s+(?<depTime>[\d\:]+\s*[AP]M)\s*$/i", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($date . ' ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td[2]", $root);

            if (preg_match("/^(?<arrName>.+)\s+Arrives\s+(?<arrTime>[\d\:]+\s*[AP]M)\s*$/i", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($date . ' ' . $m['arrTime']));
            }

            $s->setNoNumber(true);

            $duration = $this->http->FindSingleNode("./ancestor::table[starts-with(normalize-space(), 'Boarding Closes')][1]/descendant::text()[starts-with(normalize-space(), 'Duration')]/following::text()[normalize-space()][1]", $root, true, "/^([\d\:]+(?: *min)?)$/");

            if (!empty($duration)) {
                $s->setDuration($duration);
            }

            $cabin = $this->http->FindSingleNode("./ancestor::table[starts-with(normalize-space(), 'Boarding Closes')][1]/descendant::text()[normalize-space() = 'Class:']/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\s]+)$/i");

            if (!empty($cabin)) {
                $s->setCabin($cabin);
            }
        }
    }

    public function ParseTrainPDF(Email $email, string $text)
    {
        $t = $email->add()->train();
        $t->general()
            ->confirmation($this->confirmation);

        $textArray = array_filter(preg_split("/BOARDING CLOSES/", $text));

        $tickets = [];
        $travellers = [];

        foreach ($textArray as $textSegment) {
            $s = $t->addSegment();

            $traveller = $this->re("/\n *([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\n\s+Coach/", $textSegment);
            $travellers[] = $traveller;

            $tickets[] = $this->re("/Ticket\s*[#]\s*([A-Z\d]{5,})/", $textSegment);

            $date = $this->re("/Extras\s+(.+?\d{4}\b)\s+/", $textSegment);

            if (preg_match("/^\s*(\w+[,. ]*)?\w+[\s.,]*$/", $date)) {
                $date = $this->re("/\n {10,25}(\S.*?)( {2,}.*)?\n *Extras/", $textSegment) . ' ' . $date;
            }

            $depTime = $this->re("/\n *(\d{1,2}:\d{2} *[AP]M) +\d{1,2}:\d{2} *[AP]M\s*\n/", $textSegment);
            $arrTime = $this->re("/\n *\d{1,2}:\d{2} *[AP]M +(\d{1,2}:\d{2} *[AP]M)\s*\n/", $textSegment);

            $depCode = $this->re("/\n *([A-Z]{3})\s+[A-Z]{3}\s*\n/", $textSegment);
            $arrCode = $this->re("/\n *[A-Z]{3}\s+([A-Z]{3})\s*\n/", $textSegment);

            $s->setNoNumber(true);

            $s->departure()
                ->code($depCode)
                ->date(strtotime($date . ', ' . $depTime));

            $s->arrival()
                ->code($arrCode)
                ->date(strtotime($date . ', ' . $arrTime));

            if (preg_match("/Coach\s*(?<coach>\S+)\s*(?<seat>\d+[A-Z])\n+\s*(?<cabin>[A-Z ]{3,})\n/", $textSegment, $m)) {
                $s->setCarNumber($m['coach']);
                $s->addSeat($m['seat'], true, true, $traveller);
                $s->setCabin($m['cabin']);
            }

            $segments = $t->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => [], 'assignedSeats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'assignedSeats' => []]))) {
                        foreach ($s->toArray()['assignedSeats'] as $seatsAr) {
                            $segment->extra()->seat($seatsAr[0], true, true, $seatsAr[1]);
                        }
                        $t->removeSegment($s);

                        break;
                    }
                }
            }
        }

        if (count($tickets) > 0) {
            $t->setTicketNumbers(array_unique($tickets), false);
        }

        $t->general()
            ->travellers(array_unique($travellers));

        $this->Price($t);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function Price(Train $t)
    {
        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[normalize-space()][last()]", null, true, "/^(\D{1,3}[\d\.\,]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,]+)$/", $price, $m)) {
            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Fare-')]", null, true, "/{$this->opt($this->t('Fare-'))}\s*([A-Z]{3})/");

            if (empty($currency)) {
                $currency = $m['currency'];
            }

            $t->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $costsText = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Fare-') or starts-with(normalize-space(), 'FARE-')]/ancestor::tr[1]/descendant::td[normalize-space()][last()]", null, "/^\D{1,3}([\d\.\,]+)/");
            $cost = 0.0;

            foreach ($costsText as $cText) {
                $cost += PriceHelper::parse($cText, $currency);
            }

            if (!empty($cost)) {
                $t->price()
                    ->cost($cost);
            }

            $feeNodes = $this->http->XPath->query("//text()[{$this->starts(['Fare-', 'FARE-'])}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][not({$this->starts(['Fare-', 'FARE-'])})][count(*[normalize-space()]) > 1]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);
                $feeSumm = $this->http->FindSingleNode("./descendant::td[normalize-space()][last()]", $feeRoot);

                if (preg_match("/^(?:(?<minus>\-))?\D{1,3}(?<feeSumm>[\d\.\,]+)$/u", $feeSumm, $m)) {
                    $v = PriceHelper::parse($m['feeSumm'], $currency);

                    if (stripos($feeName, 'Discount') !== false || stripos($feeName, 'BL Credit/Promo Code') !== false) {
                        $t->price()
                            ->discount($v);
                    } elseif (!empty($v)) {
                        $t->price()
                            ->fee($feeName, $v);
                    }
                }
            }
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . '="' . $s . '"';
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
