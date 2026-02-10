<?php

namespace AwardWallet\Engine\contourair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "contourair/it-681469917.eml, contourair/it-682658743.eml, contourair/it-683150624.eml";
    public $subjects = [
        'Your Contour Airlines trip confirmation',
        'Important flight information – Your flight has changed.',
        'Your reservation has been canceled',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Manage Booking'                      => ['Manage Booking', 'Book a New Flight'],
            'Your Confirmation Number (PNR) is :' => ['Your Confirmation Number (PNR) is :', 'Confirmation Number (PNR):'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@contourairlines.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Contour Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Manage Booking'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Flight Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]contourairlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your reservation has been canceled.'))}]")->length > 0) {
            $f->general()
                ->status('canceled')
                ->cancelled();
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('There has been a change in your flight'))}]")->length > 0) {
            $f->general()
                ->status('changed');
        }

        $priceText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip Receipt'))}]/following::text()[{$this->starts($this->t('TK '))}]");

        if (preg_match("/^TK\s*(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{1,3})\s*/", $priceText, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $confs = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Your Confirmation Number (PNR) is :'))}]/ancestor::td[1]", null, "/{$this->opt($this->t('Your Confirmation Number (PNR) is :'))}\s*([A-Z\d]{6})$/su"));

        if (count($confs) === 0) {
            $confs = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Your Confirmation Number (PNR) is :'))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('Your Confirmation Number (PNR) is :'))}\s*([A-Z\d]{6})$/su"));
        }

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $xpath = "//img[contains(@src, 'plane')]/ancestor::tr[3]";
        $nodes = $this->http->XPath->query($xpath);
        $travellers = [];

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./descendant::tr[1]", $root, true, "/^(.+\s\d{4})/");

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::img[contains(@src, 'plane')][1]/ancestor::td[1]/preceding::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depTime>[\d\:]+\s*A?P?M)\n(?:[\d\:]+\s*A?P?M\n)?(?<depName>.+)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($date . ', ' . $m['depTime']))
                    ->noCode();
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::img[contains(@src, 'plane')][1]/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrTime>[\d\:]+\s*A?P?M)\n(?:[\d\:]+\s*A?P?M\n)?(?<arrName>.+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($date . ', ' . $m['arrTime']))
                    ->noCode();
            }

            $airlineInfo = $this->http->FindSingleNode("./descendant::img[contains(@src, 'plane')][1]/ancestor::tr[1]/following::tr[1]", $root);

            if (preg_match("/^(?<duration>.+(?:h|min))\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})$/", $airlineInfo, $m)) {
                $s->extra()
                    ->duration($m['duration']);

                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $flightName = preg_replace("/(\,\s*[A-Z]{3})/", ", IL", $s->getDepName() . ' - ' . $s->getArrName());

            $flightNodes = $this->http->XPath->query("//text()[{$this->eq($flightName)}]");

            foreach ($flightNodes as $flightRoot) {
                $traveller = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $flightRoot);
                $seat = $this->http->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::p[1]", $flightRoot, true, "/{$this->opt($this->t('Seat:'))}\s*(\d+[A-Z])/");

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat, false, false, $traveller);
                    $travellers[] = $traveller;
                } else {
                    $travellers[] = $traveller;
                }
            }
        }

        $travellers = array_unique($travellers);
        $f->general()
            ->travellers($travellers);

        $tickets = array_unique(array_filter(explode(" ", $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'E-Ticket Number:')]", null, true, "/{$this->opt($this->t('E-Ticket Number:'))}\s*(.+)/su"))));

        if (count($travellers) === 1 && count($tickets) > 0) {
            foreach ($tickets as $ticket) {
                $f->addTicketNumber($ticket, false, $travellers[0]);
            }
        } elseif (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
