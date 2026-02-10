<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: Booking, It3363267

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-2053163.eml, easyjet/it-2211348.eml, easyjet/it-5265779.eml, easyjet/it-7.eml";

    private $lang = null;

    public function ParseBookingReference2012(Email $email)
    {
        $http = $this->http;
        $text = text($this->http->Response['body']);

        $f = $email->add()->flight();

        $f->general()
            ->confirmation(re('#(?:Thank\s+you\s+for(?:\s+your)?\s+booking:|Your\s*booking\s*reference\s*is|Merci\s+de\s+votre\s+réservation:|Bedankt\s+voor\s+uw\s+boeking\.:|La\s+référence\s+de\s+votre\s+réservation\s+est)\s+([A-Z\d\-]+)#iu', $text));

        // Passengers
        $passengers = $http->FindNodes('//*[normalize-space(.)="Passengers" or normalize-space(.)="Passenger List"]/following-sibling::*[normalize-space(.)!=""]');

        if (count($passengers) === 0) {
            $passengers = $http->FindNodes('//text()[normalize-space(.)="Passengers" or normalize-space(.)="Passagers" or normalize-space(.)="Passagiers"]/ancestor::tr[1 and count(descendant::img)=0]/following-sibling::tr');
        }

        if (count($passengers) === 0) {
            $passengers = $http->FindNodes('//*[normalize-space(.)="Passengers" or normalize-space(.)="Passagers" or normalize-space(.)="Passagiers"]/following-sibling::text()[string-length(normalize-space(.))>3]');
        }

        if (count($passengers) === 0) {
            $passengers = $http->FindNodes('//*[normalize-space(.)="Passengers" or normalize-space(.)="Passagers" or normalize-space(.)="Passagiers"]/following-sibling::p');
        }
        $passengers = array_map(function ($name) {
            // Mr VASILIY KURDIN plus 134 infant
            if (preg_match('/((MR|MRS|MISS|child|Enfant)\s+)?(.+)(\s+plus\s+\d+\s+(infant|Enfant))?/ims', $name, $matches)) {
                return $matches[3];
            }

            return $name;
        }, $passengers);

        if (!empty($passengers)) {
            $f->general()
                ->travellers(array_values(array_filter($passengers, 'strlen')));
        }

        $total = $this->orval(
            $http->FindSingleNode("//text()[contains(.,'La somme de')]", null, true, "#La\s+somme\s+de\s+(.+?)\s+a\s+été\s+débitée#iu"),
            $http->FindSingleNode("//text()[contains(.,'has been charged') or contains(.,'été débitée de votre') or contains(.,'is betaald met uw')]/preceding::strong[normalize-space(.)!=''][1]"),
            $http->FindSingleNode("//td[contains(.,'has been charged') and not(.//td)]", null, true, '/^(.+?)\s+has\s+been\s+charged/'),
            $http->FindSingleNode("//text()[normalize-space(.)='Total']/following::text()[normalize-space(.)!=''][1]")
        );

        if (!empty(cost($total))) {
            $f->price()
                ->total(cost($total))
                ->currency(currency($total));
        }

        $rows = $this->http->XPath->query('//tr[not(.//tr) and count(./td)=2 and ./td[1]/img[contains(@src,"flight_")]]');

        if ($rows->length < 1) {
            $rows = $this->http->XPath->query("//tr[count(descendant::tr)=0 and (contains(normalize-space(.),'Dep') or contains(normalize-space(.),'Dép.') or contains(normalize-space(.),'Vertr')) and (contains(.,'Flight') or contains(.,'Vol') or contains(.,'Vlucht'))]");
        }

        if ($rows->length < 1) {
            $rows = $this->http->XPath->query("//tr[count(descendant::tr)=0 and (contains(normalize-space(.),'Dep') or contains(normalize-space(.),'Dép.') or contains(normalize-space(.),'Vertr'))]/preceding::tr[1]");
        }

        foreach ($rows as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->number($this->orval(
                    $http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'Flight') or starts-with(normalize-space(.),'Vol') or starts-with(normalize-space(.),'Vlucht')][1]", $root, true, '/(?:Flight|Vol|Vlucht)\s+(\d+)/'),
                    $http->FindSingleNode("./following::text()[starts-with(normalize-space(.),'Flight') or starts-with(normalize-space(.),'Vol') or starts-with(normalize-space(.),'Vlucht')][1]", $root, true, '/(?:Flight|Vol|Vlucht)\s+(\d+)/'),
                    $http->FindSingleNode(".//text()[starts-with(normalize-space(.),'Flight ')][1]", $root, true, '/(?:Flight\s+Number|Flight)\s+(\d+)/')
                ));

            if (!empty($s->getFlightNumber())) {
                $s->setAirlineName('U2');
            }

            $s->departure()
                ->noCode()
                ->name($this->orval(
                    $http->FindSingleNode('.//*[name(.)="b" or name(.)="h4"][1]', $root, true, '/(.*?)\s+(?:>|to|à|naar)\s+.+/'),
                    $http->FindSingleNode('./td[2]/*[1]', $root, true, '/(.*?)\s+(?:>|to|à|naar)\s+.+/')
                ))
                ->date(strtotime($this->orval(
                    $http->FindSingleNode('./td[2]', $root, true, '/(?:Dep|Dép.|Vertr)\s+(\d{1,2}\s+[^\d\s]+\s+\d{4}\s+\d{1,2}:\d{2})/'),
                    $http->FindSingleNode('./td[2]', $root, true, '/(?:Dep|Dép.|Vertr)\s+\w*\,\s*(\w*\s*\d{1,2}\,\s+\d{4}\s+\d{1,2}:\d{2})/'),
                    en($http->FindSingleNode("./descendant::text()[normalize-space(.)='Dep' or normalize-space(.)='Dép.' or normalize-space(.)='Vertr'][1]/ancestor::td[1]", $root, true, '/(?:Dep|Dép.|Vertr)\s+(.+)/')),
                    en($http->FindSingleNode("./following::text()[normalize-space(.)='Dep' or normalize-space(.)='Dép.' or normalize-space(.)='Vertr'][1]/ancestor::td[1]", $root, true, '/(?:Dep|Dép.|Vertr)\s+(.+)/')),
                    preg_replace('/^(\d+:\d+)\s+-\s+(\d+)\w*\s+(\w+)\s+(\d{4})$/', '$2 $3 $4, $1', $http->FindSingleNode('./td[2]/*[2]', $root))
                )));

            if (preg_match('/^(.+)\s+\(([^)(]*Terminal[^)(]*)\)$/i', $s->getDepName(), $matches)) {
                $s->departure()
                    ->name($matches[1])
                    ->terminal(str_replace('Terminal ', '', $matches[2]));
            }

            $s->arrival()
                ->noCode()
                ->name($this->orval(
                    $http->FindSingleNode('.//*[name(.)="b" or name(.)="h4"][1]', $root, true, '/.*?\s+(?:>|to|à|naar)\s+(.+)/'),
                    $http->FindSingleNode('./td[2]/*[1]', $root, true, '/.*?\s+(?:>|to|à|naar)\s+(.+)/')
                ))
                ->date(strtotime($this->orval(
                    $http->FindSingleNode('./td[2]', $root, true, '/(?:Arr|Arr.|Aank.)\s+(\d{1,2}\s+[^\d\s]+\s+\d{4}\s+\d{1,2}:\d{2})/'),
                    $http->FindSingleNode('./td[2]', $root, true, '/(?:Dep|Dép.|Vertr)\s+\w*\,\s*(\w*\s*\d{1,2}\,\s+\d{4}\s+\d{1,2}:\d{2})/'),
                    en($http->FindSingleNode("./descendant::text()[normalize-space(.)='Arr' or normalize-space(.)='Arr.' or normalize-space(.)='Aank.'][1]/ancestor::td[1]", $root, true, '/(?:Arr|Arr.|Aank.)\s+(.+)/')),
                    en($http->FindSingleNode("./following::text()[normalize-space(.)='Arr' or normalize-space(.)='Arr.' or normalize-space(.)='Aank.'][1]/ancestor::td[1]", $root, true, '/(?:Arr|Arr.|Aank.)\s+(.+)/')),
                    preg_replace('/^(\d+:\d+)\s+-\s+(\d+)\w*\s+(\w+)\s+(\d{4})$/', '$2 $3 $4, $1', $http->FindSingleNode('./td[2]/*[3]', $root))
                )));

            if (preg_match('/^(.+)\s+\(([^)(]*Terminal[^)(]*)\)$/i', $s->getArrName(), $matches)) {
                $s->arrival()
                    ->name($matches[1])
                    ->terminal(str_replace('Terminal ', '', $matches[2]));
            }
        }
    }

    public function orval()
    {
        $array = func_get_args();
        $n = sizeof($array);

        for ($i = 0; $i < $n; $i++) {
            if (((gettype($array[$i]) === 'array' || gettype($array[$i]) === 'object') && sizeof($array[$i]) > 0) || $i === $n - 1) {
                return $array[$i];
            }

            if ($array[$i]) {
                return $array[$i];
            }
        }

        return '';
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $htmlBody = $parser->getHTMLBody();

        return stripos($htmlBody, 'easyJet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['subject']) && stripos($headers['subject'], 'easyJet') !== false)
            || (isset($headers['from']) && stripos($headers['from'], 'easyJet.com') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'easyJet.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseBookingReference2012($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr', 'nl'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }
}
