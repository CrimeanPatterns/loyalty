<?php

namespace AwardWallet\Engine\funjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpdateFlights extends \TAccountChecker
{
    public $mailFiles = "funjet/it-10189057.eml, funjet/it-633821793.eml";

    private $detectSubject = [
        'FLIGHT SCHEDULE HAS BEEN UPDATED',
        'Flight Schedule Change Notification',
    ];

    private $detects = [
        'A change in your upcoming flight schedule has occurred',
    ];

    private $lang = 'en';

    private $providerCode = '';
    private static $providerDetect = [
        'funjet' => [
            'from' => ['@funjetvacations.com'],
            'body' => ['@funjetvacations.com', '@FunjetVacations.com'],
        ],
        'travimp' => [
            'from' => ['@travimp.com'],
            'body' => ['@travimp.com'],
        ],
        'appleva' => [
            'from' => ['@applevacations.com'],
            'body' => ['@applevacations.com', 'Apple Vacations Flight'],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetect);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider();

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedProvider = false;

        foreach (self::$providerDetect as $code => $prov) {
            if (!empty($prov['from']) && in_array($headers['from'], $prov['from'])) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $detectedProvider = false;

        foreach (self::$providerDetect as $code => $prov) {
            if ($this->http->XPath->query("//*[{$this->contains($prov['body'])}]")->length > 0) {
                $this->providerCode = $code;

                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
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
        return stripos($from, '@funjetvacations.com') !== false;
    }

    private function assignProvider(): bool
    {
        if (!empty($this->providerCode)) {
            return true;
        }

        foreach ($this->providerDetect as $code => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//*[contains(normalize-space(),'{$value}')]")->length > 0) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Please review the']/following-sibling::a[contains(normalize-space(),'Terms and Conditions')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src,'FJ1_logoCart') or contains(@src, '.vaxvacationaccess.com')]")->length > 0
        ) {
            $this->providerCode = 'funjet';

            return true;
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $tripNumber = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Reservation Number') and not(.//td)]/following-sibling::td[1]");
        $email->obtainTravelAgency();

        $email->ota()->confirmation($tripNumber);

        // FLIGHT
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//td[starts-with(normalize-space(.), 'Passenger(s)') and not(.//td)]/following-sibling::td[1]//text()[normalize-space(.)!='']"), true);

        $xpath = "//tr[contains(normalize-space(.), 'Airline Confirmation Number') and not(.//tr)]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->info("Segments didn't found by xpath: {$xpath}");

            return [];
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->getNode($root))
                ->number($this->getNode($root, 2, 1, '/:\s*(\d+)/'));

            $conf = $this->http->FindSingleNode("descendant::td[contains(normalize-space(.), 'Airline Confirmation Number') and not(.//td)]", $root, true, '/Airline Confirmation Number\s*:\s*([A-Z\d]{5,9})\s*$/');
            $s->airline()
                ->confirmation($conf);

            $re = '/:\s*(.+)\s*\(([A-Z]{3})\)/';

            if (preg_match($re, $this->getNode($root, 1, 2), $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }

            $s->departure()
                ->date(strtotime($this->getNode($root, 1, 3)));

            if (preg_match($re, $this->getNode($root, 2, 2), $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }

            $s->arrival()
                ->date(strtotime($this->getNode($root, 2, 3)));

            $s->extra()
                ->bookingCode($this->getNode($root, 3, 1, '/:\s*([A-Z])/'));
            $seats = preg_split('/\s*,\s*/', trim($this->getNode($root, 3, 2, '/:\s*(\d{1,3}[A-Z](?:\s*,\s*\d{1,3}[A-Z])*)\s*$/')));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }

        return $email;
    }

    private function getNode(\DOMNode $root, int $tr = 1, int $td = 1, string $re = null)
    {
        return $this->http->FindSingleNode("descendant::tr[{$tr}]/td[{$td}]", $root, true, $re);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
