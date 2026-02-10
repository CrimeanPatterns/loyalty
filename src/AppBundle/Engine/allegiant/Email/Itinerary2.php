<?php

namespace AwardWallet\Engine\allegiant\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary2 extends \TAccountChecker
{
    public $mailFiles = "allegiant/it-724986962.eml";
    public $subjects = [
        'AllegiantAir.com - Itinerary #',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Departing Flight' => ['Departing Flight', 'Returning Flight'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@t.allegiant.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Allegiant Air')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight itinerary for'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for traveling with'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departing Flight'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t\.allegiant\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your itinerary has been')]", null, true, "/{$this->opt($this->t('Your itinerary has been'))}\s*(\w+)\.$/");

        if (!empty($status)) {
            $f->setStatus($status);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation:'))}\s*[#]\s*([A-Z\d]{6})\s/"))
            ->travellers(array_unique($this->http->FindNodes("//text()[normalize-space()='Passengers']/ancestor::table[1]/descendant::text()[contains(normalize-space(), 'Seat')]/preceding::text()[normalize-space()][1]")));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Airfare']/following::text()[normalize-space()='Total Cost'][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Cost'))}\s*(.+)/");

        if (preg_match("/(?<currency>\D{1,3})(?<total>[\d\,\.]+)/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Airfare']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Airfare'))}\s*\D{1,3}\s*([\d\.\,\']+)/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $feeNodes = $this->http->XPath->query("//text()[normalize-space()='Airfare']/following::text()[normalize-space()='Total Cost'][1]/ancestor::tr[1]/preceding-sibling::tr[not(contains(normalize-space(), 'Airfare') or contains(normalize-space(), 'Payment'))][normalize-space()]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);
                $feeSumm = $this->http->FindSingleNode("./descendant::td[2]", $feeRoot, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

                if (!empty($feeName) && $feeSumm !== null) {
                    $f->price()
                        ->fee($feeName, PriceHelper::parse($feeSumm, $m['currency']));
                }
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Departing Flight'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightInfo = $this->http->FindSingleNode("./descendant::tr[1]", $root);
            $date = '';

            if (preg_match("/{$this->opt($this->t('Departing Flight'))}\s*(?<fNumber>\d{1,4})\s*\-\s*(?<date>\w+\s*\d+\,\s*\d{4})/", $flightInfo, $m)) {
                if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Thank you for traveling with Allegiant')]")->length > 0) {
                    $s->airline()
                        ->name("G4");
                }

                $s->airline()
                    ->number($m['fNumber']);

                $date = $m['date'];
            }

            $depDate = $this->http->FindSingleNode("./descendant::tr[2]/descendant::tr[normalize-space()][last()-1]", $root);

            if (preg_match("/^(?<depCode>[A-Z]{3})\s+(?<depTime>\d+\:\d+\s?A?P?M?)$/", $depDate, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($date . ', ' . $m['depTime']));
            }

            $arrDate = $this->http->FindSingleNode("./descendant::tr[2]/descendant::tr[normalize-space()][last()]", $root);

            if (preg_match("/^(?<arrCode>[A-Z]{3})\s+(?<arrTime>\d+\:\d+\s?A?P?M?)$/", $arrDate, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }

            $seats = array_filter($this->http->FindNodes("./following::text()[normalize-space()][1][contains(normalize-space(), 'Passengers')]/ancestor::table[1]/descendant::text()[contains(normalize-space(), 'Seat')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Seat'))}\s+(\d+[A-Z])/"));

            foreach ($seats as $seat) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/ancestor::tr[1]/preceding::tr[1]");

                if (!empty($pax)) {
                    $s->addSeat($seat, true, true, $pax);
                } else {
                    $s->addSeat($seat);
                }
            }
        }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }
}
