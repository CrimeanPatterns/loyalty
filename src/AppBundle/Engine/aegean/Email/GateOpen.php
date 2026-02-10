<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Schema\Parser\Email\Email;

class GateOpen extends \TAccountChecker
{
    public $mailFiles = "aegean/it-10854522.eml, aegean/it-30527304.eml, aegean/it-30527306.eml, aegean/it-30540244.eml, aegean/it-30793225.eml, aegean/it-30814033.eml";
    private $reFrom = [
        'notifications@aegeanair.com',
        'notifications@olympicair.com',
    ];
    private $reSubject = [
        'Information about your flight: Gate',
        'Information about your flight:Gate',
        'Information about your flight: Delayed',
        'Information about your flight:Delayed',

        'How was your flight today?',
        'Important notice: Passenger Locator Form',
        'Σημαντική ενημέρωση: Passenger Locator Form',
        'Delayed Baggage',
        'Baggage Belt Information',
        'Change of check-in area at ',

        'Вам была выдана багажная квитанция',
        'ВАЖНОЕ сообщение о вашем рейсе:',
    ];
    private static $reBody = [
        'aegean'     => 'Aegean Airlines',
        'olympicair' => 'Olympic Air',
    ];
    private $reBody2 = [
        ['Information about your flight: Gate', 'Boarding Time'],
        ['Information about your flight:Gate', 'Boarding Time'],
        ['Information about your flight: Delayed', 'Departure Time'],
        ['Information about your flight:Delayed', 'Departure Time'],

        ['How was your flight today?', 'Your opinion is valuable for us in order to improve'],
        ['Important notice: Passenger Locator Form', 'It is compulsory for all passengers on international flights to Greece'],
        ['Σημαντική ενημέρωση: Passenger Locator Form', 'Όλοι οι επιβάτες διεθνών πτήσεων προς Ελλάδα, υποχρεούνται να έχουν συμπληρώσει'],
        ['Delayed Baggage', 'We would like to inform you that your bag did not arrive at your destination airport'],
        ['Baggage Belt Information', 'You will collect your baggage from belt'],
        ['Change of check-in area at ', 'have been relocated to '],
        ['Вам была выдана багажная квитанция', ''],
        ['ВАЖНОЕ сообщение о вашем рейсе:', 'Время отправления'],
    ];
    private $emailDate;
    private $forwardedEmail;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        //check that email was forwarded
        $header = $parser->getHeader('from');
        $this->forwardedEmail = true;

        foreach ($this->reFrom as $reFrom) {
            if (stripos($header, $reFrom) !== false) {
                $this->forwardedEmail = false;
            }
        }

        $this->emailDate = strtotime($parser->getDate());

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        if (!$this->parseEmail($email)) {
            return null;
        }

        foreach (self::$reBody as $code => $reBody) {
            if (stripos($this->http->Response['body'], $reBody) !== false) {
                $email->setProviderCode($code);
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->reFrom as $reFrom) {
            if (stripos($headers["from"], $reFrom) !== false) {
                $finded = true;
            }
        }

        if ($finded === false) {
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

        $finded = false;

        foreach (self::$reBody as $reBody) {
            if (stripos($body, $reBody) !== false) {
                $finded = true;
            }
        }

        if ($finded === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re[0]) !== false and strpos($body, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aegeanair.com') !== false or stripos($from, '@olympicair.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$reBody);
    }

    private function parseEmail(Email $email)
    {
        if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'The departure gate') and contains(normalize-space(),'for your flight')]")->length === 0
        && $this->http->XPath->query("//text()[starts-with(normalize-space(),'Your flight') and contains(normalize-space(),'is delayed')]")->length === 0) {
            $this->logger->debug("other format");

//            return false;
        }
        $r = $email->add()->flight();

        $r->general()
            ->noConfirmation();
        $s = $r->addSegment();

        $node = $this->http->FindSingleNode("//img[contains(@src,'ic_flight.png')]/ancestor::table[1]/preceding::text()[normalize-space()!=''][1]");

        if (preg_match("#^\s*([A-Z\d]{2})(\d{1,5})\s*$#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        $s->departure()
            ->code($this->http->FindSingleNode("//img[contains(@src,'ic_flight.png')]/ancestor::td[1]/preceding-sibling::td[1]/descendant::text()[normalize-space()!=''][1]"))
            ->name($this->http->FindSingleNode("//img[contains(@src,'ic_flight.png')]/ancestor::td[1]/preceding-sibling::td[1]/descendant::text()[normalize-space()!=''][2]"))
            ->terminal($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Terminal')]/following::text()[normalize-space()!=''][1]"),
                false, true);

        $s->arrival()
            ->code($this->http->FindSingleNode("//img[contains(@src,'ic_flight.png')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()!=''][1]"))
            ->name($this->http->FindSingleNode("//img[contains(@src,'ic_flight.png')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()!=''][2]"))
            ->noDate();

        if (($this->http->XPath->query("//text()[starts-with(normalize-space(),'Information about your flight:') and contains(normalize-space(),'Gate open')]")->length === 0
                && $this->http->XPath->query("//text()[starts-with(normalize-space(),'Information about your flight:') and contains(normalize-space(),'Delayed departure')]")->length === 0)
            || $this->forwardedEmail
        ) {
            //if gate is not open and is not delayed departure, or forwarded email-> can't detect day of flight 100%
            $s->departure()->noDate();

            return true;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Departure Time') or contains(normalize-space(), 'Время отправления')]")->length == 0) {
            $s->departure()->noDate();
            //try get day of flight by look at Boarding Time
            $time = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Boarding Time') or contains(normalize-space(), 'Время посадки')]/following::text()[normalize-space()!=''][1]");

            if (!empty($time) && !empty($this->emailDate)) {
                $dateBoard = strtotime($time, $this->emailDate);

                if ($this->emailDate > $dateBoard) {
                    $dateBoard = strtotime("+1 day", $dateBoard);
                    $s->departure()
                        ->day(strtotime(date("Y-m-d", $dateBoard)));
                } else {
                    $day = strtotime(date("Y-m-d", $dateBoard));
                    // set the duration of boarding 2 hours
                    $minDate = strtotime("+2 hours", $day);
                    $maxDate = strtotime("-2 hours", strtotime("+1 day", $day));

                    if ($dateBoard > $minDate && $dateBoard < $maxDate) {
                        $s->departure()
                            ->day($day);
                    }
                }
            }
        } else {
            $time = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Departure Time') or contains(normalize-space(), 'Время отправления')]/following::text()[normalize-space()!=''][1]");
            $time = preg_replace("/ μμ\s*$/", 'pm', $time);

            if (!empty($time) && !empty($this->emailDate)) {
                $s->departure()
                    ->date(strtotime($time, $this->emailDate));

                if ($this->emailDate > $s->getDepDate()) {
                    $s->departure()
                        ->date(strtotime("+1 day", $s->getDepDate()));
                }
            }
        }

        return true;
    }
}
