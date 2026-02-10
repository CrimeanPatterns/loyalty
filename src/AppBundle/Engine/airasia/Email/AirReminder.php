<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirReminder extends \TAccountChecker
{
    public $mailFiles = "airasia/it-33659143.eml";

    private $from = '/[@\.]airasia\.com/';

    private $detects = [
        'Ready for your next trip?',
    ];

    private $lang = 'en';

    private $prov = 'airasia';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && preg_match($this->from, $headers['from']) > 0;
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

        if ($conf = $this->http->FindSingleNode("//text()[normalize-space(.)='Booking number']/following::text()[normalize-space(.)][1]")) {
            $f->general()
                ->confirmation($conf);
        }

        if ($name = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Hey')][1])[1]", null, true, '/Hey[ ]*(.+),/')) {
            $f->addTraveller($name);
        }

        $xpath = "//*[count(tr)>=5]/tr[1][descendant::tr[count(td)>=3]]/following-sibling::tr[1]/descendant::tr[1]";

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//img[contains(@src, 'icon/planered-icon')]/ancestor::tr[2]";
        }
        $roots = $this->http->XPath->query($xpath);
        // $this->http->XPath->query("(//img[contains(@src, 'icon/planered-icon')]/ancestor::tr[2])[position()!=1]")
        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $s->departure()
                ->code($this->http->FindSingleNode('td[1]', $root));

            $s->arrival()
                ->code($this->http->FindSingleNode('td[normalize-space(.)][last()]', $root));

            $xp = "ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)][1]/descendant::tr[1]";
            $s->departure()
                ->name($this->http->FindSingleNode($xp . '/td[1]', $root));
            $s->arrival()
                ->name($this->http->FindSingleNode($xp . '/td[normalize-space(.)][last()]', $root));

            $node = $this->http->FindSingleNode($xp . '/td[normalize-space(.)][2]', $root);

            if (preg_match('/([A-Z\d]{2})[ ]*(\d+)/', $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            $xp1 = "ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::tr[1]";
            $dDate = strtotime($this->http->FindSingleNode($xp1 . '/td[1]', $root, true, '/\w+,[ ]*(.+)/'));
            $aDate = strtotime($this->http->FindSingleNode($xp1 . '/td[normalize-space(.)][last()]', $root, true, '/\w+,[ ]*(.+)/'));

            $xp2 = "ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]/descendant::tr[1]";
            $dTime = $this->http->FindSingleNode($xp2 . '/td[1]', $root);
            $aTime = $this->http->FindSingleNode($xp2 . '/td[normalize-space(.)][last()]', $root);

            if (!empty($dDate) && !empty($dTime)) {
                $s->departure()
                    ->date(strtotime($dTime, $dDate));
            }

            if (!empty($aDate) && !empty($aTime)) {
                $s->arrival()
                    ->date(strtotime($aTime, $aDate));
            }
        }
    }
}
