<?php

namespace AwardWallet\Engine\delta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement2023 extends \TAccountChecker
{
    public $mailFiles = "delta/statements/it-418129514.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return ($this->http->XPath->query("//img[contains(@src, 'Delta-Logo')]")->length > 0
            || $this->http->XPath->query("//a[contains(@href, 'delta.com')]")->length > 0)
            && ($this->http->XPath->query("//text()[contains(normalize-space(), 'STATUS PROGRESS:')]/following::text()[normalize-space()][1][normalize-space()='SKYMILES MEMBER']")->length > 0
                || $this->http->XPath->query("//text()[contains(normalize-space(), 'Status progress:')]/following::text()[normalize-space()][1][normalize-space()='SkyMiles Member']")->length > 0
                || $this->http->XPath->query("//text()[contains(normalize-space(), 'CURRENT STATUS')]/following::text()[normalize-space()][2][contains(normalize-space(), 'SKYMILES MEMBER SINCE')]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Travel Info')]/following::text()[normalize-space()][not(contains(normalize-space(), 'SkyMiles') or contains(normalize-space(), 'Need Help?'))][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'My SkyMiles')]/preceding::text()[contains(normalize-space(), ' miles')][1]/preceding::text()[normalize-space()][1]");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[normalize-space()='SKYMILES #']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{10,})$/");

        if (!empty($number)) {
            $st->setNumber($number)
               ->setLogin($number);
        }

        $level = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'STATUS PROGRESS:')]/following::text()[normalize-space()][1]");

        if (empty($level)) {
            $level = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'CURRENT STATUS:')]/following::text()[normalize-space()][1]", null, true, "/^SKYMILES[®]\s*(\w+)$/u");
        }

        if (!empty($level)) {
            $st->addProperty('Level', $level);
        }

        $medallionMilesYTD = $this->http->FindSingleNode("//text()[normalize-space()='Medallion Qualification Miles'][1]/following::text()[normalize-space()='Current value is'][1]/following::text()[normalize-space()][1]", null, true, "/^([\d\,\.]+)$/");

        if ($medallionMilesYTD !== null) {
            $st->addProperty('MedallionMilesYTD', $medallionMilesYTD);
        }

        $medallionSegmentsYTD = $this->http->FindSingleNode("//text()[normalize-space()='Medallion Qualification Segments'][1]/following::text()[normalize-space()='Current value is'][1]/following::text()[normalize-space()][1]", null, true, "/^([\d\,\.]+)$/");

        if ($medallionSegmentsYTD !== null) {
            $st->addProperty('MedallionSegmentsYTD', $medallionSegmentsYTD);
        }

        $medallionDollarYTD = $this->http->FindSingleNode("//text()[normalize-space()='Medallion Qualification Dollars'][1]/following::text()[normalize-space()='Current value is'][1]/following::text()[normalize-space()][1]", null, true, "/^(\D*[\d\,\.]+)$/");

        if ($medallionDollarYTD !== null) {
            $st->addProperty('MedallionDollarsYTD', $medallionDollarYTD);
        }

        $millionMiles = $this->http->FindSingleNode("//text()[normalize-space()='MILLION MILER STATUS'][1]/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\,\.]+)$/");

        if ($millionMiles !== null) {
            $st->addProperty('MillionMiles', $millionMiles);
        }

        $balance = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Travel Info')]/following::text()[normalize-space()][not(contains(normalize-space(), 'SkyMiles') or contains(normalize-space(), 'Need Help?'))][2]", null, true, "/^([\d\,\.]+)\s*miles/");

        if ($balance === null) {
            $balance = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'My SkyMiles')]/preceding::text()[contains(normalize-space(), ' miles')]", null, true, "/^([\d\,\.]+)\s*miles/");
        }

        if ($balance === null) {
            $balance = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'BOOK WITH MILES')]/preceding::text()[contains(normalize-space(), 'MILES AVAILABLE')][1]/preceding::text()[normalize-space()][1]", null, true, "/^([\d\,\.]+)$/");
        }

        if ($balance !== null) {
            $st->setBalance(str_replace(',', '', $balance));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
