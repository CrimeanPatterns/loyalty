<?php

namespace AwardWallet\Engine\autoslash\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "autoslash/it-26774980.eml, autoslash/it-30214217.eml";

    private $lang = 'en';

    private $detects = [
        'en' => 'these numbers in case you need to email AutoSlash ',
    ];

    private $from = '/[@\.]autoslash\.com/';
    private $subjects = [
        'AutoSlash Booking Confirmation',
    ];
    private $prov = 'autoslash';

    private $rentalProviders = [
        'alamo' => [
            'Alamo',
        ],
        'hertz' => [
            'Hertz',
        ],
        'national' => [
            'National',
        ],
        'thrifty' => [
            'Thrifty',
        ],
        'rentacar' => [
            'Enterprise',
        ],
        'dollar' => [
            'Dollar',
        ],
        'avis' => [
            'Avis',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[normalize-space()='Original message' or normalize-space()='Your original message']")->length > 0) {
            $this->parseEmailText($email);
        } else {
            $this->parseEmailHtml($email);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->subjects as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], 'AutoSlash') !== false
                ) {
                    return true;
                }
            }
        }

        return false;
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

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 2; //text | html
    }

    private function parseEmailText(Email $email): void
    {
        $text = implode("\n",
            $this->http->FindNodes("//text()[contains(normalize-space(),'Your AutoSlash Trip ID is')]/ancestor::td[1]//text()[normalize-space()!='']",
                null, "/^[> ]*(.*)/"));

        $r = $email->add()->rental();

        $email->ota()
            ->confirmation($this->re('/Your AutoSlash Trip ID is\s*(\d+)/', $text));

        $r->general()
            ->confirmation($this->re('/\n.+? Confirmation # ([\w\-]+)/', $text))
            ->status($this->re('/\n.+? Confirmation # [\w\-]+ (.+)/', $text));

        $rentalCompany = $this->re('/\n(.+?) Confirmation # [\w\-]+/', $text);

        if (!empty($rentalCompany)) {
            $r->extra()->company($rentalCompany);

            foreach ($this->rentalProviders as $code => $detects) {
                foreach ($detects as $detect) {
                    if (stripos($rentalCompany, $detect) === 0) {
                        $r->setProviderCode($code);

                        break 2;
                    }
                }
            }
        }

        $pickupDate = $this->re('/\nPick Up\n(.+)/', $text);
        $pickupDate = str_replace(',', '', $pickupDate);
        $pickupTime = $this->re('/\nPick Up\n.+\n(.+)/', $text);
        $r->pickup()
            ->date(strtotime($pickupDate . ', ' . $pickupTime));

        $dropoffDate = $this->re('/\nDrop Off\n(.+)/', $text);
        $dropoffDate = str_replace(',', '', $dropoffDate);
        $dropoffTime = $this->re('/\nDrop Off\n.+\n(.+)/', $text);

        $r->dropoff()
            ->date(strtotime($dropoffDate . ', ' . $dropoffTime));

        $r->pickup()
            ->location($this->re('/\nPick-up Location\n(?:\[.+\] +)?(.+)/', $text));

        $r->pickup()
            ->phone($this->re('/\nPick-up Location\n(?:\[.+\] +)?.+\n(?:\[.*Telephone.*\] +)?(.+)/', $text));

        $node = $this->re('/\nDrop-off Location\n(?:\[.+\] +)?(.+)/', $text);

        if (stripos($node, 'Same as pick-up location') !== false) {
            $r->dropoff()->same();
        } else {
            $r->dropoff()
                ->location($node);
        }

        $r->dropoff()
            ->phone($this->re('/\nDrop-off Location\n(?:\[.+\] +)?.+\n(?:\[.*Telephone.*\] +)?(.+)/', $text));

        $r->addTraveller($this->re('/\nDriver\n(.+)/', $text));

        if (preg_match('/\nTotal:\n.+ (\D+)\s*([\d\.]+)\n/', $text, $m)) {
            $m[1] = str_replace('$', 'USD', $m[1]);
            $r->price()
                ->total($m[2])
                ->currency($m[1]);
        }

        $carInfo = $this->re('/\n(?:\[.+\] +)?(.+)\n+Pick Up\n/', $text);

        if (preg_match('/(.+)\s*\-\s*(.+)/', $carInfo, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }
    }

    private function parseEmailHtml(Email $email): void
    {
        $r = $email->add()->rental();

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Your AutoSlash Trip ID is')]/following-sibling::node()[normalize-space(.)!=''][1]"));

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(),'Confirmation #')]/following::text()[normalize-space()!=''][1]"));

        $rentalCompany = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Confirmation #')]", null,
            false, "/(.+) Confirmation \#/");

        if (!empty($rentalCompany)) {
            $r->extra()->company($rentalCompany);

            foreach ($this->rentalProviders as $code => $detects) {
                foreach ($detects as $detect) {
                    if (false !== stripos($rentalCompany, $detect)) {
                        $r->program()->code($code);
                        $flagCode = true;

                        break 2;
                    }
                }
            }

            if (!isset($flagCode)) {
                $r->program()->keyword($rentalCompany);
            }
        }

        $pickupDate = $this->getNode('Pick Up');
        $pickupDate = str_replace(',', '', $pickupDate);
        $pickupTime = $this->getNode('Pick Up', 2);
        $r->pickup()
            ->date(strtotime($pickupDate . ', ' . $pickupTime));

        $dropoffDate = $this->getNode('Drop Off');
        $dropoffDate = str_replace(',', '', $dropoffDate);
        $dropoffTime = $this->getNode('Drop Off', 2);
        $r->dropoff()
            ->date(strtotime($dropoffDate . ', ' . $dropoffTime));

        $r->pickup()
            ->location($this->getNode('Pick-up Location'));

        $r->pickup()
            ->phone($this->getNode('Pick-up Location', 2));

        $node = $this->getNode('Drop-off Location');

        if (stripos($node, 'Same as pick-up location') !== false) {
            $r->dropoff()->same();
        } else {
            $r->dropoff()
                ->location($node);
        }

        $r->dropoff()
            ->phone($this->getNode('Drop-off Location', 2));

        if ($status = $this->getNode('Confirmation #', 2, true)) {
            $r->general()
                ->status($status);
        }

        $r->addTraveller($this->getNode('Driver'));

        $total = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Total:') and not(.//td)]/following-sibling::td[1]");

        if (preg_match('/(\D+)\s*([\d\.]+)/', $total, $m)) {
            $m[1] = str_replace('$', 'USD', $m[1]);
            $r->price()
                ->total($m[2])
                ->currency($m[1]);
        }

        $carInfo = $this->http->FindSingleNode("//img[contains(@src, 'car-img')]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($carInfo)) {
            $carInfo = $this->http->FindSingleNode("//text()[normalize-space(.)='Pick Up']/preceding::text()[normalize-space(.)!=''][1]");
        }

        if (preg_match('/(.+)\s*\-\s*(.+)/', $carInfo, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }
    }

    private function getNode(string $s, int $n = 1, bool $contains = false): ?string
    {
        if (!$contains) {
            return $this->http->FindSingleNode("//node()[normalize-space(.)='{$s}']/following-sibling::node()[normalize-space(.)!=''][{$n}]");
        } else {
            return $this->http->FindSingleNode("(//node()[contains(normalize-space(.), '{$s}')]/following-sibling::node()[normalize-space(.)!=''][{$n}])[1]");
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
