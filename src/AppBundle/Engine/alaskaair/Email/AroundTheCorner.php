<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AroundTheCorner extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-11345603.eml, alaskaair/it-11389896.eml, alaskaair/it-35195621.eml, alaskaair/it-90559262.eml, alaskaair/it-430963413.eml";

    public $reFrom = "service@ifly.alaskaair.com";

    public $reProvider = "alaskaair.com";

    public $reSubject = [
        "en"  => " is just around the corner.",
        "en2" => "Ready for your trip on",
        "en3" => "Check in now for your flight to ",
        "en4" => "More info for your trip on",
        ', make your trip on',
        'Need-to-know details about your',
    ];

    public $reBody = 'alaskaair.com';

    public $lang = 'en';

    /*
    public $reBody2 = [
        "en"  => "your trip just a few days away",
        "en2" => "you can now check in for your flight to ",
        "en3" => "here’s what you’ll need to know for your",
        "en4" => "you can now check in. See you on board soon",
        "en5" => "Review inflight services before you",
        "en6" => "Your trip, from takeoff to touch down",
    ];
    */

    public static $dictionary = [
        "en" => [
            'Hello'  => ['Hello', 'Hi'],
            'confNo' => [
                'Confirmation', 'Confirmation:', 'Confirmation :',
                'Confirmation code', 'Confirmation code:', 'Confirmation code :',
            ],
        ],
    ];

    private $xpath = [
        'airportCode' => 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"',
        'number'      => "translate(normalize-space(),'0123456789','')=''",
    ];

    public function ParseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNo'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/')
            ?? $this->http->FindSingleNode("//p[{$this->eq($this->t('confNo'))}]/following-sibling::p[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/')
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNo'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/')
        ;

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[following-sibling::tr[normalize-space()] and {$this->eq($this->t('confNo'))}]", null, true, '/^(.+?)[\s:：]*$/u')
                ?? $this->http->FindSingleNode("//p[following-sibling::p[normalize-space()] and {$this->eq($this->t('confNo'))}]", null, true, '/^(.+?)[\s:：]*$/u')
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNo'))}]", null, true, '/^(.+?)[\s:：]*$/u')
            ;
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $passenger = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\,?\s*({$patterns['travellerName']})(?:\s*\||$)/u");

        if (empty($passenger)) {
            $passenger = $this->http->FindSingleNode("//text()[contains(normalize-space(),', you can now check in')]", null, true, "/^({$patterns['travellerName']}), you can now check in/u");
        }

        if (empty($passenger)) {
            $passenger = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code'))}]/preceding::text()[{$this->starts($this->t('Hello'))}][1]", null, true, "/{$this->opt($this->t('Hello'))}\,?\s*(.+?)\s*(?:\||$)/");
        }

        if (count(array_filter([$passenger])) > 0) {
            $f->general()
                ->travellers(array_filter([$passenger]));
        }

        $accountValues = array_filter($this->http->FindNodes("descendant::node()[{$this->eq($this->t('confNo'))}][1]/preceding::text()[{$this->starts($this->t('Hello'))}]/following::br[1]/following::text()[normalize-space()][1]", null, "/(?:{$this->opt($this->t('Member'))}[ ]*[:]+[ ]*|^)([Xx]{3}[A-z\d]{4,})$/")); // xxxx3MU8

        if (count(array_unique($accountValues)) === 1) {
            $account = array_shift($accountValues);
            $f->program()->account($account, true);
        }

        $segments = $this->findSegments();

        if ($segments->length !== 1) {
            $this->logger->debug('Too many segments found!');

            return;
        }
        $root = $segments->item(0);

        $s = $f->addSegment();

        $xpathFlightRow = "preceding::tr[ not(.//tr[normalize-space()]) and descendant::node()[self::text()[normalize-space()] or self::img][1][self::img] and descendant::text()[normalize-space()][1][{$this->xpath['number']}] ]";

        $flightNumber = $this->http->FindSingleNode($xpathFlightRow . "/descendant::text()[normalize-space()][1]", $root, true, '/^\d+$/');

        if ($this->http->XPath->query($xpathFlightRow, $root)->length === 0) {
            $s->airline()->noNumber();
        } else {
            $s->airline()->number($flightNumber);
        }

        $airlineImg = $this->http->FindSingleNode($xpathFlightRow . "/descendant::img[1]/@src", $root);

        if (preg_match("/\/airlinelogos\/(?<iata>[A-z][A-z\d]|[A-z\d][A-z])\.[A-z]/i", $airlineImg, $m)
            || preg_match("/alaskaair.*\/(?<iata>[A-z][A-z\d]|[A-z\d][A-z])_wordmark\.[A-z]/i", $airlineImg, $m)
        ) {
            $s->airline()->name(strtoupper($m['iata']));
        } elseif (preg_match("/\/responsysimages.*\/alaskaair.*\/logo/i", $airlineImg, $m)) {
            $s->airline()->name('Alaska Airlines');
        } else {
            $s->airline()->noName();
        }

        $date = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Departure'))}]/following-sibling::tr[normalize-space()]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//p[{$this->eq($this->t('Departure'))}]/following-sibling::p[normalize-space()]", null, true, "/^.*\d.*$/");
        $route = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

        if (preg_match("#(.+)\n\s*([A-Z]{3})\s+(.+?)\s*$#", $route, $m)) {
            if (!empty($date)) {
                $s->departure()
                    ->date(strtotime($date . ' ' . $m[1]));
            } else {
                $s->departure()
                    ->date(strtotime($m[1]));
            }
            $s->departure()
                ->code($m[2])
                ->name($m[3]);
        }

        $date = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Arrival'))}]/following-sibling::tr[normalize-space()]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//p[{$this->eq($this->t('Arrival'))}]/following-sibling::p[normalize-space()]", null, true, "/^.*\d.*$/");
        $route = implode("\n", $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

        if (preg_match("#(.+)\n\s*([A-Z]{3})\s+(.+?)\s*$#", $route, $m)) {
            if (!empty($date)) {
                $s->arrival()
                    ->date(strtotime($date . ' ' . $m[1]));
            } else {
                $s->arrival()
                    ->date(strtotime($m[1]));
            }

            if ($m[2] === $s->getDepCode() && $m[3] === $s->getDepName()) {
                $s->arrival()->noCode();
            } else {
                $s->arrival()
                    ->code($m[2])
                    ->name($m[3]);
            }
        }

        if ($seats = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Seat']/following::text()[normalize-space()][1]", null, true, "#^\s*(\d{1,3}[A-Z])\s*(?:\W\s*)?$#u")) {
            $s->extra()
                ->seat($seats);
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHtml($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false && $this->http->XPath->query("//text()[{$this->contains($this->t('Alaska Airlines'))}]")->length == 0) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    private function findSegments(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[*[3] and *[normalize-space()='' and descendant::img] and count(*[descendant::text()[{$this->xpath['airportCode']}]])=2]");
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
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
}
