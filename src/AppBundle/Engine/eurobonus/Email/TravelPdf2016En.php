<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelPdf2016En extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-5167226.eml, eurobonus/it-6687564.eml";

    private $result = [];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdf = $parser->searchAttachmentByName('.*Travel\s?Pass\.pdf');

        if (empty($pdf)) {
            $this->logger->info('Pdf is not found or is empty!');

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $this->parseReservation($email, str_replace(' ', ' ', $this->findCutSectionToWords($text, ['SAS Organization number'])));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findСutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'reply@flysas.com') !== false) {
            return true;
        }

        if (strpos($headers['subject'], 'SAS ') === false) {
            return false;
        }

        if (stripos($headers['subject'], 'Travel Pass') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>«»?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (strpos($textPdf, "Best regards,\nSAS") === false && strpos($textPdf, 'SAS Org') === false) {
                continue;
            }

            if (stripos($textPdf, 'Travel Pass') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysas.com') !== false;
    }

    protected function parseReservation(Email $email, $text)
    {
        $f = $email->add()->flight();

        $this->result['Kind'] = 'T';

        if (preg_match('/Booking reference:\s*([A-Z\d]+)/', $text, $matches)) {
            $f->general()
                ->confirmation(trim($matches[1]));
        }

        if (preg_match('/Name:\s*(.+?)(?:\n|\s{3,})/', $text, $matches)) {
            $f->general()
                ->traveller(trim($matches[1]), true);
        }

        if (preg_match('/Ticketless number:?\s*([\d-]+)/', $text, $matches)) {
            $f->issued()
                ->ticket(trim($matches[1]), false);
        }

        if (preg_match('/Travel Pass number:?\s*(\d{7,})/', $text, $matches)) {
            $f->program()
                ->account(trim($matches[1]), false);
        }

        if (preg_match('/(?:Date of issue|Date):\s*(\d{1,2}\w{3,}\d{2,4})/', $text, $matches)) {
            $f->general()
                ->date(strtotime($matches[1]));

            $this->result['ReservationDate'] = strtotime($matches[1]);
        }

        if (preg_match('/Sub Total Amount\s{3,}([\d,]+)\s*([A-Z]{3})/', $text, $matches)) {
            $f->price()
                ->total((float) str_replace(',', '.', $matches[1]))
                ->currency($matches[2]);

            if (preg_match('/Fare\s{3,}([\d,]+)\s*' . $f->getPrice()->getCurrencyCode() . '/', $text, $matches)) {
                $f->price()
                    ->cost((float) str_replace(',', '.', $matches[1]));
            }

            if (preg_match('/Tax\s{3,}([\d,]+)\s*' . $f->getPrice()->getCurrencyCode() . '/', $text, $matches)) {
                $f->price()
                    ->tax((float) str_replace(',', '.', $matches[1]));
            }
        }
        $this->parseSegments($f, $this->findСutSection($text, '  Arrival', ['Ticketless number']));
    }

    protected function parseSegments(Flight $f, $text)
    {
        // SK 4480 28NOV B  Stavanger - Trondheim  06:10  07:20
        foreach (preg_split('/\n+/', $text, null, PREG_SPLIT_NO_EMPTY) as $value) {
            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s*(\d+\w+)\s*([A-Z])\s+(.+?)\s+-\s+(.+?)\s*(\d{1,2}:\d{2})\s*(\d{1,2}:\d{2})/', $value, $matches)) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);

                $s->departure()
                    ->noCode()
                    ->name($matches[5])
                    ->date($this->normalizeDate($matches[3] . ', ' . $matches[7]));

                $s->arrival()
                    ->noCode()
                    ->name($matches[6])
                    ->date($this->normalizeDate($matches[3] . ', ' . $matches[8]));

                $s->extra()
                    ->bookingCode($matches[4]);
            }
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    /**
     * <pre>Example:
     * findCutSectionToWords('start cut text', ['cut'])
     * // start cut
     * </pre>.
     */
    protected function findCutSectionToWords($input, $searchFinish = [])
    {
        foreach ($searchFinish as $value) {
            $pos = strpos($input, $value);

            if ($pos !== false) {
                return mb_substr($input, 0, $pos);
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->result['ReservationDate']);

        $this->logger->warning($year);
        $this->logger->warning($str);

        $in = [
            //25OKT, 17:45
            "#^(\d+)(\w+)\,\s*([\d\:]+)$#u",
        ];
        $out = [
            "$1 $2 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->warning($str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        return strtotime($str);
    }
}
