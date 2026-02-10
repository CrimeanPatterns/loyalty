<?php

namespace AwardWallet\Engine\flysaa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "flysaa/it-41852733.eml";

    private $lang = 'en';

    private $detects = [
        'Thank you for making your booking on',
    ];

    private $from = '/[@\.]flysaa\.com/';

    private $prov = 'flysaa';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Booking Reference Number']/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[normalize-space()='Booking Reference Number']", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $status = $this->http->FindSingleNode("//text()[normalize-space()='Booking Status']/following::text()[normalize-space()][1]");
        $f->general()->status($status);

        $paxs = $this->http->FindNodes("//tr[normalize-space(.)='Name' and not(.//tr)]/ancestor-or-self::tr[following-sibling::tr][1]/following-sibling::tr[string-length(normalize-space(.)) > 2]", null, '/([A-Z ]+)[ ]*\(/');

        foreach ($paxs as $pax) {
            $f->general()
                ->traveller($pax);
        }

        $total = $this->http->FindSingleNode("//td[normalize-space(.)='Total price for all passengers including tax' and not(.//td)]/following-sibling::td[normalize-space(.)][1]");

        if (preg_match('/([A-Z]{3})[ ]+([\d\.]+)/', $total, $m)) {
            $f->price()
                ->currency($m[1])
                ->total($m[2]);
        }

        $xpath = "//tr[contains(normalize-space(.), 'Departure') and not(.//tr)]/ancestor::tr[contains(normalize-space(.), 'Arrival') and contains(normalize-space(.), 'Flight')][2]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->http->FindSingleNode("preceding-sibling::tr[string-length(normalize-space(.))>2][1]", $root, true, '/(\d{1,2} \w+ \d{2,4})/'));

            // 11:15 NewYork
            $re = '/^(?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)\s+(?<city>.{2,})$/';

            // O.R. Tambo Intl Airport (JNB)
            $re2 = '/^(?<airport>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/';

            // (JNB)
            $re3 = '/^[(\s]*(?<code>[A-Z]{3})[\s)]*$/';

            $cityDep = null;
            $depTimeCity = $this->http->FindSingleNode("descendant::tr[normalize-space()='Departure']/following-sibling::tr[normalize-space()][1]", $root);

            if (preg_match($re, $depTimeCity, $m)) {
                $s->departure()->date(strtotime($m['time'], $date));
                $cityDep = $m['city'];
            }

            $depAirport = $this->http->FindSingleNode("descendant::tr[normalize-space()='Departure']/following-sibling::tr[normalize-space()][2]", $root);

            if (preg_match($re2, $depAirport, $m)) {
                $s->departure()->name($m['airport'] . ($cityDep ? ', ' . $cityDep : ''))->code($m['code']);
            } elseif (preg_match($re3, $depAirport, $m)) {
                $s->departure()->code($m['code']);
            } elseif ($cityDep) {
                $s->departure()->name($cityDep);
            }

            $cityArr = null;
            $arrTimeCity = $this->http->FindSingleNode("descendant::tr[normalize-space()='Arrival']/following-sibling::tr[normalize-space()][1]", $root);

            if (preg_match($re, $arrTimeCity, $m)) {
                $s->arrival()->date(strtotime($m['time'], $date));
                $cityArr = $m['city'];
            }

            $arrAirport = $this->http->FindSingleNode("descendant::tr[normalize-space()='Arrival']/following-sibling::tr[normalize-space()][2]", $root);

            if (preg_match($re2, $arrAirport, $m)) {
                $s->arrival()->name($m['airport'] . ($cityArr ? ', ' . $cityArr : ''))->code($m['code']);
            } elseif (preg_match($re3, $arrAirport, $m)) {
                $s->arrival()->code($m['code']);
            } elseif ($cityArr) {
                $s->arrival()->name($cityArr);
            }

            $flight = $this->http->FindSingleNode("descendant::tr[starts-with(normalize-space(.), 'Flight')][1]", $root, true, '/Flight[ ]*:[ ]*(.+)/');

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+(\d+)/', $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->airline()
                ->operator($this->getNode($root, 'Operator'), false, true);

            $s->extra()
                ->aircraft($this->getNode($root, 'Aircraft'))
                ->bookingCode($this->getNode($root, 'Class'))
                ->cabin($this->getNode($root, 'Cabin'), false, true)
                ->stops($this->getNode($root, 'Stops'), false, true)
            ;
        }
    }

    private function getNode(\DOMNode $root, string $s = '', ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("descendant::td[starts-with(normalize-space(.), '{$s}') and not(.//td)][1]/following-sibling::td[normalize-space(.)][last()]", $root, true, $re);
    }
}
