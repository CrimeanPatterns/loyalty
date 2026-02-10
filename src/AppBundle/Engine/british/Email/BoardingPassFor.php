<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassFor extends \TAccountChecker
{
    public $mailFiles = "british/it-30032293.eml, british/it-30435744.eml, british/it-30527900.eml, british/it-30598711.eml";

    public $reFrom = ["ba@ba.com", "ba@britishairways.com"];
    public $reBody = [
        ['Thank you for using British Airways', 'the boarding pass'],
    ];
    public $reSubject = [
        '/Boarding pass for .+? (?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+:[A-Z]{3}\-[A-Z]{3}:\d+\-[A-Z]{3}\-\d{4}\s*$/u',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (count($pdfs) > 0) {
            $this->logger->debug('go to parse by other parsers (by attach)');

            return null;
        }
        //work with only British
        $isBA = $this->detectEmailFromProvider($parser->getCleanFrom());
        $body = $parser->getHTMLBody();

        if (!$isBA) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($body, $reFrom) !== false) {
                    $isBA = true;
                }
            }
        }

        if (!$isBA) {
            return null;
        }

        $subject = $parser->getSubject();

        if (preg_match("/Boarding pass for (?<pax>.+?) (?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<flight>\d+):(?<dep>[A-Z]{3})\-(?<arr>[A-Z]{3}):(?<date>\d+\-[A-Z]{3}\-\d{4})\s*$/u",
            $subject, $m)) {
            $r = $email->add()->flight();

            $r->general()
                ->traveller($m['pax']);

            $ref = $this->http->FindSingleNode("//text()[contains(normalize-space(),'seat for your next flight by following the link below')]/following::text()[normalize-space()!=''][1]",
                null, false, "/^http:\/\/ba.com.+?&bookingRef=([A-Z\d]{5,6})&lastname=/");

            if (empty($ref)) {
                $ref = $this->http->FindSingleNode("//text()[contains(normalize-space(),'seat for your next flight by following the link below')]",
                    null, false, "/http:\/\/ba.com.+?&bookingRef=([A-Z\d]{5,6})&lastname=/");
            }

            if (!empty($ref)) {
                $r->general()->confirmation($ref);
            } else {
                $r->general()->noConfirmation();
            }

            $s = $r->addSegment();
            $s->airline()
                ->name($m['airline'])
                ->number((int) $m['flight']);
            $s->departure()
                ->code($m['dep'])
                ->noDate()
                ->day(strtotime($m['date']));
            $s->arrival()
                ->code($m['arr'])
                ->noDate();
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $parseBySubject = false;

        foreach ($this->reSubject as $reSubject) {
            if ((preg_match($reSubject, $parser->getSubject()))
            ) {
                $parseBySubject = true;
            }
        }
        $isBA = $this->detectEmailFromProvider($parser->getCleanFrom());
        $body = $parser->getHTMLBody();

        if (!$isBA) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($body, $reFrom) !== false) {
                    $isBA = true;
                }
            }
        }

        if (!$isBA) {
            return false;
        }

        if ($parseBySubject) {
            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && preg_match($reSubject, $headers["subject"]))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }
}
