<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    private $detects = [
        'Hotel booking status',
    ];

    private $from = '/[@.]cleartrip[.]com/';

    private $prov = 'Cleartrip';

    private $lang = 'en';

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->parseEmail($email);
        $ns = explode('\\', __CLASS__);
        $class = end($ns);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
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
        return preg_match($this->from, $from);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): Email
    {
        $h = $email->add()->hotel();

        $anchor = 'Trip ID';
        $confNo = $this->http->FindSingleNode("//h2[contains(normalize-space(.), '{$anchor}:')]", null, true, '/Trip ID:\s*([A-Z\d]+)/');
        $h->general()
            ->confirmation($confNo, $anchor);

        $h->hotel()
            ->name($this->getNode('Hotel Name'))
            ->address($this->getNode('Address'))
            ->phone(preg_replace('/\-\w+/', '', $this->getNode('Tel')));
//            ->fax($this->getNode('Fax'));

        $h->general()
            ->status($this->getNode('Status'));

        $h->booked()
            ->checkIn($this->normalizeDate($this->getNode('Check in')));

        $h->booked()->rooms($this->getNode('Rooms'));

        $h->booked()
            ->checkOut($this->normalizeDate($this->getNode('Check out')));

        if (preg_match('/Accommodation details\s*\S\s*(\d+)\s*adults\s*and\s*(\d+)\s*child/iu', $this->http->FindSingleNode("//h2[contains(normalize-space(.), 'Accommodation details')]"), $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        $h->general()
            ->traveller($this->getNode('Name'));

        $h->addRoom()
            ->setRate($this->getNode('Room Rate'));

        $h->price()
            ->tax(str_replace('.', '', $this->getNode('Taxes & Fees', '/([\d\.]+)/')));

        if (preg_match('/([A-Z]{3})\s*([\d\.]+)/', $this->getNode('Total paid'), $m)) {
            $h->price()
                ->currency($m[1])
                ->total(str_replace('.', '', $m[2]));
        }

        $h->booked()
            ->cancellation(implode(', ', $this->http->FindNodes("//p[contains(., 'Cancellation Policy') and not(.//p)]/following-sibling::li[position() < 3]")));

        return $email;
    }

    private function getNode(string $s, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("//td[not(.//td) and starts-with(normalize-space(.), '{$s}')]/following-sibling::td[normalize-space(.)][1]", null, true, $re);
    }

    private function normalizeDate(?string $str)
    {
        $in = [
            '/(\d{1,2}:\d{2}\s*[ap]m),? (\w+) (\d{1,2}),? (\d{2,4})/i',
        ];
        $out = [
            '$3 $2 $4, $1',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }
}
