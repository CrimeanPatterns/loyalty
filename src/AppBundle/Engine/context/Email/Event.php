<?php

namespace AwardWallet\Engine\context\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    public $mailFiles = "context/it-132464032.eml, context/it-298000469.eml, context/it-328393427.eml, context/it-38829443.eml, context/it-39184166.eml, context/it-39602374.eml, context/it-40216559.eml";

    public static $dictionary = [
        'en' => [
            'Meeting Point' => ['Meeting Point', 'Meeting point', 'You will meet your expert guide at the'],
        ],
    ];

    private $lang = 'en';

    private $detects = [
        'Meeting Point:', 'Meeting point:', 'You will meet your expert guide at the', 'info@contexttravel.com',
    ];

    private $from = '/[@\.]contexttravel\.com/';

    private $subjects = [
        'Context Itinerary',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!preg_match($this->from, $headers['from'])) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (false !== stripos($headers['subject'], $subject)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".contexttravel.com/") or contains(@href,"/contexttravel.com") or contains(@href,"www.contexttravel.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Welcome to the Context community") or contains(.,"@contexttravel.com")]')->length === 0
        ) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 < $this->http->XPath->query("//*[contains(normalize-space(),'{$detect}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email)
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd")';
        $xpath = "//text()[normalize-space()][1][contains(., '•')][$xpathTime]/ancestor::*[count(.//text()[{$this->starts($this->t('Meeting Point'))}]) = 1][1][descendant::text()[normalize-space()][2][contains(., '•')]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[normalize-space()][1][contains(., '•')][$xpathTime]/ancestor::*[count(.//text()[{$this->starts($this->t('Meeting Point'))}]) = 1][1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpathTime = 'contains(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd")';
            $xpath = "//text()[normalize-space()][1][contains(., '•')][$xpathTime]/ancestor::*[count(.//text()[{$this->contains($this->t('Private Tour'))}]) = 1][1][descendant::text()[normalize-space()][1][contains(., '•')]]";
            $nodes = $this->http->XPath->query($xpath);
        }

        $this->logger->debug($xpath);
        // Type 2
        // FRI, JAN 14, 2022 • 2:00 PM – 5:00 PM (3.0 HOURS) • PRIVATE TOUR
        if ($nodes->length > 0) {
            $this->logger->debug('type 2');

            return $this->parseEmail2($email, $nodes);
        }

        // Type 1
        // Date: 2-Jul-2019 (Tue) @ 9:30 AM
        $xpath = "//*[self::p or self::div][not(.//p[normalize-space()]) and not(.//div[normalize-space()])][{$this->starts($this->t('Meeting Point'))}]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug('type 1');

            return $this->parseEmail1($email, $nodes);
        }
    }

    private function parseEmail1(Email $email, $nodes): void
    {
        $this->logger->debug(__METHOD__);

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd")';

        foreach ($nodes as $i => $root) {
            $cond = "[count(preceding::text()[{$this->starts($this->t('Meeting Point'))}]) = {$i}]";
            $e = $email->add()->event();

            $e->general()
                ->noConfirmation();

            $e->type()
                ->event();

            $address = null;

            $addressStr = implode("\n",
                $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/The exact address is[ ]+(.+?)\. If you are taking a taxi/i", $addressStr, $m)
                || preg_match("/(?:You will meet your (?:expert guide|expert|docent)|Your expert guide will meet you) \D*(?:in front of|in the lobby of the|outside the|just outside the|at the)[ ]+(.+?)[.!(](?: Your guide will be waiting| The nearest metro| The bar is| The shop is|the main door|\n|$)/i", $addressStr, $m)
            ) {
                $address = $m[1];
            }

            $e->place()->address($address);

            // type 1
            $name = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(),'Group walk') or starts-with(normalize-space(),'Small Group walk') or starts-with(normalize-space(),'Private walk')][1]){$cond}", null, true, '/(?:Group walk|Small Group walk|Private walk)[ ]*:[ ]*(.+)/')
                ?? $this->http->FindSingleNode("(//tr[ preceding-sibling::tr[normalize-space() or .//img][1][normalize-space()='' and .//img] and following-sibling::tr[normalize-space()][1][{$xpathTime}] ]){$cond}")
            ;

            if (empty($name)) {
                $name = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), 'Private Tour') or contains(normalize-space(), 'Small Group Tour')][1]{$cond}/preceding::text()[normalize-space()][1]", $root);
            }

            $e->place()->name($name);

            if ($startDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Date:') and following-sibling::text()[contains(normalize-space(.), 'Duration')]][1]{$cond}", null, true, '/Date:[ ]+(\d{1,2}\-\w+\-\d{2,4}.+)/')
            ) {
                $e->booked()
                    ->start(strtotime(preg_replace(['/\@/', '/\(\w+\)/', '/\-/'], ['', ',', ' '], $startDate)))
                    ->noEnd();
            }

            $participants = $this->http->FindSingleNode("//text()[normalize-space()='Participants:']/following::text()[normalize-space()][1]{$cond}", null, true, '/^\d{1,3}$/');

            if ($participants !== null) {
                $e->booked()->guests($participants);
            } elseif (!empty($guests = count($this->http->FindNodes("./preceding::text()[normalize-space()='Tour Participants:'][1]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Tour Participants:'))]{$cond}", $root)))) {
                $e->booked()->guests($guests);
            }

            if ($paxs = $this->http->FindNodes("//text()[normalize-space()='Tour Participants:']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Tour Participants:'))]{$cond}")) {
                $e->general()
                    ->travellers(array_unique($paxs));
            } elseif ($paxs = $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Participants:')]{$cond}/following-sibling::text()[normalize-space(.)][not(contains(normalize-space(.), 'Status')) and not(contains(normalize-space(.), 'hotel'))]")) {
                $e->general()
                    ->travellers(array_unique($paxs));
            }

            if ($status = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Status:')][1]/following-sibling::node()[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]){$cond}")) {
                $e->setStatus($status);
            }
        }
    }

    private function parseEmail2(Email $email, $nodes): void
    {
        $this->logger->debug(__METHOD__);

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        foreach ($nodes as $root) {
            $e = $email->add()->event();

            $confNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order #')]", null, true, "/{$this->opt($this->t('Order #'))}\s*(\d+)$/");

            if (!empty($confNumber)) {
                $e->general()
                    ->confirmation($confNumber);
            } else {
                $e->setNoConfirmationNumber(true);
            }

            $e->type()
                ->event();

            $address = null;
            $url = $this->http->FindSingleNode(".//a[contains(., 'View Larger Map') and contains(@href, 'google.com/maps')]/@href", $root);

            if (!empty($url)) {
                if (!isset($http2)) {
                    $http2 = clone $this->http;
                }
                $http2->GetURL($url);

                if (!empty($http2->FindSingleNode("(//title[normalize-space() = 'Google Maps' or normalize-space() = 'Google Карты'])[1]"))) {
                    $address = $http2->FindSingleNode("//meta[@itemprop = 'name']/@content");
                }
            }

            if (empty($address)) {
                $addressStr = implode("\n",
                    $this->http->FindNodes(".//*[self::p or self::div][not(.//p[normalize-space()]) and not(.//div[normalize-space()])][{$this->starts($this->t('Meeting Point'))}]/descendant::text()[normalize-space()]", $root));

                if (empty($addressStr)) {
                    $addressStr = $this->http->FindSingleNode("./following::text()[normalize-space()='Meeting Point:']/ancestor::tr[1]", $root);
                }

                if (preg_match("/The exact address is[ ]+(.+?)\. If you are taking a taxi/i", $addressStr, $m)
                    || preg_match("/(?:You will meet your (?:expert guide|expert|docent)|Your expert guide will meet you) \D*(?:in front of|in the lobby of the|outside the|just outside the|at the)[ ]+(.+?)[.!(](?: Your guide will be waiting| The nearest metro| The bar is| The shop is|the main door|\n|$)/i", $addressStr, $m)
                ) {
                    $address = $m[1];
                }
            }

            $e->place()->address($address);

            $name = $this->http->FindSingleNode("descendant::text()[contains(., '•')]/preceding::text()[normalize-space()][1]/ancestor::a", $root);

            if (empty($name)) {
                $name = $this->http->FindSingleNode("descendant::text()[contains(., '•')]/preceding::text()[normalize-space()][1]", $root);
            }
            $e->place()->name($name);

            $dateText = $this->http->FindSingleNode("descendant::text()[contains(., '•')][1]", $root);

            if (preg_match("/^(?<date>.*\d.*?)\s*•\s*(?<time1>{$patterns['time']})\s*–\s*(?<time2>{$patterns['time']})/", $dateText, $m)) {
                // Sun, Apr 24, 2022 • 10:15 AM – 1:15 PM (3.0 hours) • Private Tour
                $date = strtotime($m['date']);
                $e->booked()
                    ->start(strtotime($m['time1'], $date))
                    ->end(strtotime($m['time2'], $date));
            }
            $participants = $this->http->FindSingleNode(".//text()[normalize-space()='Participants:']/following::text()[normalize-space()][1]", $root, true, '/^\d{1,3}$/');

            if ($participants !== null) {
                $e->booked()->guests($participants);
            } elseif (!empty($guests = count($this->http->FindNodes(".//text()[normalize-space()='Tour Participants:'][1]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Tour Participants:'))]", $root)))) {
                $e->booked()->guests($guests);
            }

            if ($paxs = $this->http->FindNodes("//text()[normalize-space()='Tour Participants:']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Tour Participants:'))]", $root)) {
                $e->general()
                    ->travellers(array_unique($paxs));
            } elseif ($paxs = $this->http->FindNodes("./following::text()[normalize-space()='Tour Participants:']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Tour Participants:'))]", $root)) {
                $e->general()
                    ->travellers(array_unique($paxs));
            } elseif ($preparedFor = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Prepared for')]", null, true, "/^Prepared for\s+({$patterns['travellerName']})$/u")) {
                // it-132464032.eml
                $e->general()->traveller($preparedFor);
            }
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
