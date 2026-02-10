<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-516188168.eml";
    public $subjects = [
        '[EXTERNAL] Air Canada – ',
    ];

    public $lang = 'en';
    public $text;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.aircanada.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Air Canada, PO Box')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Duration:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('DEPART'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Time:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Date:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.aircanada\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Reference:']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/"))
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Passengers']/following::table[1]/descendant::tr/td[2]/descendant::text()[normalize-space()][1]"))
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Date of issue:']/following::text()[normalize-space()][1]")));

        $tickets = $this->http->FindNodes("//text()[normalize-space()='Ticket Number']/ancestor::td[1]", null, "/{$this->opt($this->t('Ticket Number'))}\s*(\d{5,})/su");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique(array_filter($tickets)), false);
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Grand Total - ')]/ancestor::tr[1]");

        if (preg_match("/\((?<currency>[A-Z]{3})\)\s*\D(?<total>[\d\.\,]+)/", $price, $m)) {
            $f->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));

            if (preg_match("/Taxes, Fees and Charges\s*\n+(?<feeText>(?:.*\n){1,5})Change fee\s*\n/", $this->text, $m)) {
                $feesText = array_filter(explode("\n", $m['feeText']));

                foreach ($feesText as $feeText) {
                    if (preg_match("/^(?<feeName>.+)[ ]{1,}\D*(?<feeSumm>[\d\,\.]+)\s*$/", $feeText, $match)) {
                        $f->price()
                            ->fee($match['feeName'], $match['feeSumm']);
                    }
                }
            }
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'plane-takeoff')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))/"))
                ->number($this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,4})$/"));

            $operator = trim($this->http->FindSingleNode("./following::text()[normalize-space()='Operating Airline'][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Operating Airline'))}\:?\s*(.+)/"));

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $airline = $s->getAirlineName() . $s->getFlightNumber();
            $seats = $this->http->FindNodes("//text()[normalize-space()='Flight Number']/ancestor::td[1]/descendant::text()[{$this->contains($airline)}]/preceding::td[contains(normalize-space(), 'Seat Number')]/descendant::text()[normalize-space()][not(contains(., 'Seat Number'))]", null, "/^(\d+[A-Z])\s*\-\s*$/");

            if (count($seats) > 0) {
                $s->setSeats(array_filter($seats));
            }

            $depInfo = $this->http->FindSingleNode("./descendant::td[3]", $root);

            if (preg_match("/^\s*(?<code>[A-Z]{3}).*Date\:\s*\w+\,?\s+(?<date>.+)\s+Time\:\s+(?<time>[\d\:]+\s*a?p?m?)/ui", $depInfo, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date(strtotime(str_replace(',', '', $m['date']) . ', ' . $m['time']));
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::td[5]", $root);

            if (preg_match("/^\s*(?<code>[A-Z]{3}).*Date\:\s*\w+\,?\s+(?<date>.+)\s+Time\:\s+(?<time>[\d\:]+\s*a?p?m?)/ui", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date(strtotime(str_replace(',', '', $m['date']) . ', ' . $m['time']));
            }

            $cabin = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[contains(normalize-space(), ' - ')]", $root);

            if (!empty($cabin)) {
                $s->setCabin($cabin);
            }

            $duration = trim($this->http->FindSingleNode("./following::text()[normalize-space()='Flight Duration:'][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Flight Duration:'))}\s*(.+)/"));

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->text = $parser->getBody();

        $this->ParseFlight($email);

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
