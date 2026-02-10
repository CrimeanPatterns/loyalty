<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class BookingText2015En extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-5175775.eml, eurobonus/it-5215274.eml, eurobonus/it-61097782.eml";
    private $travellers = [];
    private $accounts = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getHTMLBody();
        $this->parseReservation($email,
            str_replace(' ', ' ', $this->findCutSection($text, null, ['PLEASE SEE WWW.FLYSAS.COM'])));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@flysas.com') !== false
            && isset($headers['subject']) && (
            preg_match('/.+?\d+\w+\d+\s+[A-Z]{3}\s+[A-Z]{3}/u', $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'TO                     DEPART  ARRIVAL') !== false
            || stripos($parser->getHTMLBody(), '  YOUR RESERVATION HAS BEEN CANCELLED') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysas.com') !== false;
    }

    //========================================
    // Auxiliary methods
    //========================================

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
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($input, $searchFinish, true);
            } else {
                $inputResult = $input;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseReservation(Email $email, $text)
    {
        $f = $email->add()->flight();

        if (preg_match('/BOOKING REF\s+([A-Z\d]{5,6})/', $text, $matches)) {
            $f->general()->confirmation($matches[1], 'BOOKING REF');
        }

        if (preg_match('/DATE\s+(\d+\w+\d+)/', $text, $matches)) {
            $f->general()->date2($matches[1]);
        }

        foreach ($this->splitter('/\n+\s*(.+?\s+-\s+[A-Z]{2}\s*\d+)/', $text) as $value) {
            $s = $f->addSegment();
            $reg = '([A-Z\s]+)\s+-\s+([A-Z]{2})\s*(\d+)\s+(?:REF\.\s*[\d-]+\s*)?';
            $reg .= '(.{1,18})\s+(.+?)\s*(\d+)\s+(\d+).+?(\d+\w+)';

            if (preg_match("/{$reg}/s", $value, $matches)) {
                $s->airline()->operator($matches[1]);
                $s->airline()->name($matches[2]);
                $s->airline()->number($matches[3]);
                $s->departure()->name(trim($matches[4]));
                $s->arrival()->name(trim($matches[5]));
                $depDate = strtotime($matches[8] . ', ' . $matches[6], $f->getReservationDate());

                if ($f->getReservationDate() > $depDate) {
                    $depDate = strtotime('+1 year', $depDate);
                }
                $arrDate = strtotime($matches[7], $depDate);

                while ($depDate > $arrDate) {
                    $arrDate = strtotime('+1 day', $arrDate);
                }
                $s->departure()->date($depDate);
                $s->arrival()->date($arrDate);
            }

            if (preg_match("/DURATION\s+(\d+:\d+)/", $value, $matches)) {
                $s->extra()->duration($matches[1]);
            }

            if (preg_match("/RESERVATION (\w+)\s+-\s+([A-Z])\s+(\w+)/", $value, $matches)) {
                $s->extra()->bookingCode($matches[2]);
                $s->extra()->cabin($matches[3]);
            }

            if (preg_match("/EQUIPMENT:\s+(.+)/", $value, $matches)) {
                $s->extra()->aircraft($matches[1]);
            }
            $s->departure()->noCode();
            $s->arrival()->noCode();
            $this->parseSegmentPassenger($s, $value);
        }

        if (!empty($this->travellers)) {
            $f->general()->travellers($this->travellers);
        }

        if (!empty($this->accounts)) {
        }

        if (preg_match('/YOUR RESERVATION HAS BEEN CANCELLED\b/', $text)) {
            $f->general()->status('CANCELLED');
            $f->general()->cancelled();
        }

        if (preg_match_all("/\s+TICKET:[A-Z\d]{2}\/ETKT *(\d{3} ?\d+)/", $text, $matches)) {
            $f->issued()
                ->tickets($matches[1], false);
        }
    }

    protected function parseSegmentPassenger(FlightSegment $s, $text)
    {
        // SEAT 08K NO SMOKING SEAT CONFIRMED FOR AAKERBERG/THOMAS MR
        if (preg_match_all("/SEAT\s+(\w+)\s+(.+?)\s+SEAT.+?FOR\s+(.+)/", $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $value) {
                $s->extra()->seat($value[1]);

                if ($value[2] === 'NO SMOKING') {
                    $s->extra()->smoking(false);
                }

                if (!in_array($value[3], $this->travellers)) {
                    $this->travellers[] = $value[3];
                }
            }
        } else { // TG FREQUENT FLYER SKEBG001828862 DANIELSEN/MATS MR
            if (preg_match_all("/FREQUENT FLYER\s+(\w+)\s+(.+)/", $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $value) {
                    if (!in_array($value[1], $this->accounts)) {
                        $this->accounts[] = $value[1];
                    }

                    if (!in_array($value[2], $this->travellers)) {
                        $this->travellers[] = $value[2];
                    }
                }
            }
        }
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }
}
