<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "despegar/it-37924463.eml";

    private $detects = [
        'es' => ['Tu vuelo a', 'Reserva confirmada #'],
    ];

    private $lang = 'es';

    private $from = '/[@.]despegar\.com/';

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
        if (0 === $this->http->XPath->query("//a[contains(@href, 'despegar.com')]")->length) {
            return false;
        }

        foreach ($this->detects as $lang => $detects) {
            if (
                0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detects[0]}')]")->length
                && 0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detects[1]}')]")->length
            ) {
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
        // flights
        $xpath = "//div[contains(normalize-space(.), 'Tu vuelo a') and not(.//div)]/ancestor::div[following-sibling::div][1]";
        $flights = $this->http->XPath->query($xpath);

        if (0 < $flights->length) {
            $this->flight($email, $flights);
        }
    }

    private function flight(Email $email, \DOMNodeList $flights)
    {
        foreach ($flights as $flight) {
            $f = $email->add()->flight();
            $conf = null;

            if (preg_match('/Reserva confirmada \#[ ]+(\d+)/', $flight->nodeValue, $m)) {
                $f->addConfirmationNumber($m[1]);
                $conf = $m[1];
            }

            if ($pax = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'faltan')][1]", null, true, '/(.+), /')) {
                $f->addTraveller($pax, false);
            }

            $re = "/(?:Ida|Vuelta) ([A-Z]{3})[ ]*\-[ ]*([A-Z]{3}) (\d{1,2} \w+ \d{2,4})[ ]*\|[ ]*(\d{1,2}:\d{2}) hs/ui";
            $nodes = $this->http->FindNodes("following-sibling::div[string-length(normalize-space(.))>2][1]/descendant::div[count(div)=2]/div", $flight);

            foreach ($nodes as $node) {
                if (preg_match($re, $node, $m)) {
                    $s = $f->addSegment();
                    $s->departure()
                        ->code($m[1])
                        ->date(strtotime($m[3] . ', ' . $m[4]));
                    $s->arrival()
                        ->code($m[2])
                        ->noDate();
                    $s->airline()
                        ->noName()
                        ->noNumber();
                } else {
                    $email->removeItinerary($f);
                }
            }
        }
    }

    private function hotel(Email $email)
    {
        $h = $email->add()->hotel();
    }
}
