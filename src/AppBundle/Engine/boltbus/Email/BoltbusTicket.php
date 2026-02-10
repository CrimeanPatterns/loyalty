<?php

namespace AwardWallet\Engine\boltbus\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoltbusTicket extends \TAccountChecker
{
    public $mailFiles = "boltbus/it-3601595.eml";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class);

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href,'www.boltbus.com')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'tickets@boltbus.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (stripos($headers['from'], 'tickets@boltbus.com')) !== false
            || isset($headers['from'])
            && ((stripos($headers['subject'], 'Boltbus Ticket Confirmation and Receipt')) !== false
                || (stripos($headers['subject'], 'BoltBus Reservation')) !== false);
    }

    protected function parseEmail(Email $email)
    {
        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Grand Total')]/ancestor::td[1]/preceding::td[1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Fares:')]/following-sibling::td");
        }

        if (preg_match("/(?<cur>[\$])(?<total>[0-9.]+)/", $total, $m)) {
            $email->price()
                ->total($m['total'])
                ->currency("USD");
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Boarding Pass']/ancestor::table[count(./tr[normalize-space()!=''])=3 or count(./tbody/tr[normalize-space()!=''])=3 ][1]");

        foreach ($nodes as $root) {
            $r = $email->add()->bus();

            $r->general()
                ->traveller($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), 'Boarding Group')]/ancestor::tr[1]/preceding::text()[string-length(normalize-space())>2][1]",
                    $root))
                ->confirmation($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), 'Confirmation #')]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()!=''][2]",
                    $root));

            $s = $r->addSegment();

            $node = $this->http->FindSingleNode("./descendant::tr[1]", $root);

            if (preg_match("/(?<depname>[\w -\/]+) to (?<arrname>[\w -\/]+)/", $node, $m)) {
                $s->departure()
                    ->name($m['depname']);
                $s->arrival()
                    ->name($m['arrname']);
            }
            $node = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), 'Departing')]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()!=''][1]",
                $root);

            if (preg_match("#(?<date>[\d\S]+) (?<time>[\d: \w]+)#", $node, $m)) {
                $s->departure()->date(strtotime($m['date'] . ' ' . $m['time']));
                $timeArr = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), 'Arriving')]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()!=''][1]",
                    $root);
                $s->arrival()->date(strtotime($m['date'] . ' ' . $timeArr));
            }

            if ($nodes->length == 1) {
                if (preg_match("/(?<cur>[\$])(?<total>[0-9.]+)/", $total, $m)) {
                    $r->price()
                        ->total($m['total'])
                        ->currency("USD");
                }
            }
        }

        return true;
    }
}
