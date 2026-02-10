<?php

namespace AwardWallet\Engine\chase\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1604230 extends \TAccountCheckerExtended
{
    public $mailFiles = "chase/it-1604230.eml, chase/it-1604607.eml, chase/it-2111698.eml, chase/it-3.eml, chase/it-5.eml, chase/it-5198136.eml, chase/it-5270898.eml, chase/it-6.eml";
    private $detects = [
        'Thank you for your reservation',
        'Thank you for choosing the Travel Rewards Center',
        'We have identified an airline generated schedule change for your',
        'Changes or Cancellations',
    ];

    private $from = '/[.@]chase\.com/i';
    private $lang = 'en';

    private $subjects = [
        'Travel Reservation Center Trip ID',
    ];

    private $needle = [
        'Chase Travel Center',
        'Travel Rewards Center',
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.ixfr.com') or contains(@href,'chase')]")->length > 0) {
            $body = $parser->getPlainBody();

            if (empty($body)) {
                $body = text($parser->getHTMLBody());
            }
            $key = false;

            foreach ($this->needle as $needle) {
                if (false !== stripos($body, $needle)) {
                    $key = true;
                }
            }

            foreach ($this->detects as $detect) {
                if (false !== stripos($body, $detect) && $key) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->travellers($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passenger')]/following::text()[normalize-space()][1]"));

        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Airline Reference Number')]/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]{4,})/");

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        } else {
            $f->general()
                ->noConfirmation();
        }

        $status = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Item')]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[2]");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $xpath = "//text()[(contains(normalize-space(), 'hr') and contains(normalize-space(), 'min')) or contains(normalize-space(), 'Non-stop')]/ancestor::tr[not(contains(normalize-space(), 'for'))][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{2})/"))
                ->number($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]{2}\s*[#](\d+)/"));

            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][2]", $root))
                ->duration($this->http->FindSingleNode("./descendant::td[6]", $root));

            $aircraft = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][3]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $stops = $this->http->FindSingleNode("./descendant::td[5]", $root);

            if (!empty($stops) && $stops == 'Non-stop') {
                $s->extra()
                    ->stops('0');
            }

            $depDate = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()][1]", $root);
            $depTime = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()][2]", $root);
            $s->departure()
                ->date(strtotime($depDate . ' ' . $depTime))
                ->name($this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()][3]", $root, true, "/^(.+)\s\(/"))
                ->code($this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()][3]", $root, true, "/\(([A-Z]{3})\)/"));

            $arrDate = $this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()][1]", $root);
            $arrTime = $this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()][2]", $root);
            $s->arrival()
                ->date(strtotime($arrDate . ' ' . $arrTime))
                ->name($this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()][3]", $root, true, "/^(.+)\s\(/"))
                ->code($this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()][3]", $root, true, "/\(([A-Z]{3})\)/"));
        }

        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total Charges')]/ancestor::tr[1]/descendant::td[3]");

        if (!empty($total)) {
            $f->price()
                ->currency($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total Charges')]/ancestor::tr[1]/descendant::td[3]", null, true, "/^(\D)[\d\,\.]+/"))
                ->total(str_replace(',', '', $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total Charges')]/ancestor::tr[1]/descendant::td[3]", null, true, "/^\D([\d\,\.]+)/")));
        }

        $spentAwards = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total Charges')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\,]+)/");

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards(str_replace(',', '', $spentAwards) . ' Points');
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Agency Reference Number')]/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]{4,})/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }
}
