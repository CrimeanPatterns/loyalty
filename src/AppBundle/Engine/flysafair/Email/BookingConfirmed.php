<?php

namespace AwardWallet\Engine\flysafair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "flysafair/it-81425384.eml, flysafair/it-94500556.eml";
    public $subjects = [
        '/your On Business statement is ready to view$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'route' => ['OUTBOUND', 'RETURN', 'Flight Details'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flysafair.co.za') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Safair Operations')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('BOOKING REFERENCE:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('route'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flysafair\.co\.za$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='BOOKING REFERENCE:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('BOOKING REFERENCE:'))}\s*([A-Z\d]{5,})$/"), 'BOOKING REFERENCE');

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'FLYSAFAIR BOOKING')]", null, true, "/{$this->opt($this->t('FLYSAFAIR BOOKING'))}\s*(\D+)/");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $travellers = [];
        $xpath = "//text()[{$this->eq($this->t('route'))}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root);

            $flight = $this->http->FindSingleNode('following-sibling::tr[1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $depTime = $this->http->FindSingleNode("./following::tr[2]/descendant::tr[3]/td[1]", $root);
            $s->departure()
                ->name($this->http->FindSingleNode("./following::tr[2]/descendant::tr[1]/td[1]", $root))
                ->date(strtotime($date . ' ' . $depTime))
                ->code($this->http->FindSingleNode("./following::tr[2]/descendant::tr[2]/td[1]", $root));

            $arrTime = $this->http->FindSingleNode("./following::tr[2]/descendant::tr[3]/td[last()]", $root);
            $s->arrival()
                ->name($this->http->FindSingleNode("./following::tr[2]/descendant::tr[1]/td[last()]", $root))
                ->date(strtotime($date . ' ' . $arrTime))
                ->code($this->http->FindSingleNode("./following::tr[2]/descendant::tr[2]/td[last()]", $root));

            $seats = [];

            $passengerRows = $this->http->XPath->query("following-sibling::tr/descendant::tr[{$this->eq($this->t('PASSENGER DETAILS'))}]/following-sibling::tr[count(*[normalize-space()])=2 and not(starts-with(.,'-'))]", $root);

            foreach ($passengerRows as $pRow) {
                if (($passenger = $this->http->FindSingleNode('*[normalize-space()][1]', $pRow, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u"))) {
                    $travellers[] = $passenger;
                }
                $seatValue = $this->http->FindSingleNode('*[normalize-space()][2]', $pRow, true, "/^[, A-Z\d]+$/");
                $seats = array_merge($seats, array_map(function ($item) {
                    return str_replace(' ', '', $item);
                }, preg_split('/\s*,\s*/', $seatValue)));
            }

            if (count($seats) > 0) {
                $seats = preg_replace("/^\s*([A-Z])(\d{1,3})\s*$/", '$2$1', $seats);
                $s->extra()->seats($seats);
            }
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        } else {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hey'))}\s*(\D+)\,/");
            $f->general()
                ->traveller($traveller, false);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
