<?php

namespace AwardWallet\Engine\bedsonline\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkBookingCancellation extends \TAccountChecker
{
    public $mailFiles = "bedsonline/it-42130855.eml, bedsonline/it-43911651.eml";

    public $reFrom = ["@bedsonline.com"];
    public $reBody = [
        'en' => ['BOOKING CANCELLATION'],
    ];
    public $reBodyPdf = [
        'en' => ['Booking cancelled'],
    ];
    public $reSubject = [
        '#Booking cancellation \d+\-\d+ Bedsonline$#', // junk by body, if no attach
        '#Booking Cancellation Notice$#', // this emails has only attach, have no body -> junk
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            // HTML
            'Your reference number:'      => 'Your reference number:',
            'Cancellation charges total:' => 'Cancellation charges total:',
            // PDF
            'Booking Ref.'                                      => 'Booking Ref.',
            'Booking cancelled due to lack of client’s payment' => 'Booking cancelled due to lack of client’s payment',
        ],
    ];
    private $keywordProv = 'Bedsonline';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // unknown type cancelled reservation -> junk
        if (!$this->checkFormatJunk($parser)) {
            $this->logger->debug('not junk format');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setIsJunk(true);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                return $this->checkFormatJunk($parser);
            }
        }
        $pdfs = $parser->searchAttachmentByName(".*");

        if (isset($pdfs) && count($pdfs) === 1) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]))) === null) {
                return false;
            }

            foreach ($this->reBodyPdf as $lang => $reBody) {
                if ($this->stripos($text, $reBody)) {
                    return $this->checkFormatJunk($parser);
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
                if (($fromProv || stripos($headers["subject"], $this->keywordProv))
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function checkFormatJunk(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*");

        if (isset($pdfs) && count($pdfs) > 1) {
            $this->logger->debug("can't assign as junk. email has more then one attach");

            return false;
        }

        if (isset($pdfs) && count($pdfs) === 1) {
            if (empty($parser->getHTMLBody()) && empty($parser->getPlainBody())) {
                if (null !== ($text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0])))
                    && $this->checkPdfFormatJunk($text)
                ) {
                    return true;
                } else {
                    $this->logger->debug("can't assign as junk. email has unknown attach");

                    return false;
                }
            } else {
                $this->logger->debug("can't assign as junk. email has attach && not empty body");

                return false;
            }
        }

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        return $this->checkHtmlFormatJunk($body);
    }

    private function checkHtmlFormatJunk($body)
    {
        // detect lang at first
        if (!$this->assignLang()) {
            return false;
        }

        $node = $this->http->XPath->query("//pre");

        if ($node->length === 0) {
            $node = $this->http->XPath->query("//body/descendant::div[1]");
        }

        if ($node->length == 1 && ($node = $this->re("#<pre>(.+)</pre>#s", $body) ?? $node = $this->re("#<body>(.+)</body>#s", $body))) {
            $node = strip_tags($node);

            $condition1 = preg_match("#BOOKING CANCELLATION\n+\s*The booking \d+\-\d+#u",
                    $node) > 0;

            $condition2 = preg_match("#\n[ \t]*{$this->opt($this->t('The booking'))} \d+\-\d+ on behalf of .+? has been successfully cancelled.\n*\s*Your reference number:#",
                    $node) > 0;
            $condition3 = preg_match("#Your reference number:[^\n]+\s+Cancellation charges total:[^\n]+\n*\s*Please do not reply to this email#",
                    $node) > 0;
            $condition4 = preg_match("#(Depart|Location)#i", $node) === 0; //just control guess

            if ($condition1 && $condition2 && $condition3 && $condition4) {
                return true;
            }
        }

        return false;
    }

    private function checkPdfFormatJunk($textPdf)
    {
        // detect lang at first
        if (!$this->assignLang($textPdf)) {
            return false;
        }
        $condition1 = preg_match("#^\s*We would like to inform you that the following bookings have been cancelled automatically#",
                trim($textPdf)) > 0;
        $condition2 = preg_match("#This is an automated message, please do not reply\.\s*$#",
                trim($textPdf)) > 0;
        $condition3 = preg_match("#Booking Ref\.\s+Ref\.\s*Agency\s+Destination\s+Check in\s+Guest Name#",
                trim($textPdf)) > 0;

        if ($condition1 && $condition2 && $condition3) {
            return true;
        }

        return false;
    }

    private function assignLang($textPdf = null)
    {
        if (isset($textPdf)) {
            $body = $textPdf;
        } else {
            $body = $this->http->Response['body'];
        }

        foreach (self::$dict as $lang => $words) {
            if (!isset($textPdf) && isset($words['Your reference number:'], $words['Cancellation charges total:'])) {
                if ($this->stripos($body, $words['Your reference number:'])
                    && $this->stripos($body, $words['Cancellation charges total:'])
                ) {
                    $this->lang = $lang;

                    return true;
                }
            } elseif (isset($words['Booking Ref.'], $words['Booking cancelled due to lack of client’s payment'])) {
                if ($this->stripos($body, $words['Booking Ref.'])
                    && $this->stripos($body, $words['Booking cancelled due to lack of client’s payment'])
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
