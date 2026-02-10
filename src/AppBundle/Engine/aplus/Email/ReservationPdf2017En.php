<?php

// bcdtravel

namespace AwardWallet\Engine\aplus\Email;

class ReservationPdf2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('MHR-\w+\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $this->parseReservation($this->findCutSection($text, null, ['info@mercureroeselare.be']));

        return [
            'parsedData' => ['Itineraries' => $this->result],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@mercureroeselare.be') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Reservation -') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Hotel Mercure Roeselare') !== false
                && stripos($parser->getHTMLBody(), 'Receptie') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mercureroeselare.be') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
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
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseReservation($text)
    {
        $result['Kind'] = 'R';
        $result['HotelName'] = $this->match('/for your reservation in\s+(.+?)\. We are/', $text);

        // Front Office Manager
        // Kwadestraat 149B 8800 Roeselare Belgium
        // T T +32 (0)51 43 20 00 F F +32 (0)51 43 20 01
        $info = $this->match('/Manager\s+(.+?)\s+T T\s*([+)(\d\s]+)F F\s*([+)(\d\s]+)/s', $text, true);

        if (!empty($info)) {
            $result['Address'] = $info[0];
            $result['Phone'] = $info[1];
            $result['Fax'] = $info[1];
        }

        $text = $this->findCutSection($text, 'Baby           Rate', 'Your reservation is');

        foreach ($this->splitter('#(MHR-\w+\s+.+?\s+\d+/\d+/\d+)#s', $text) as $text) {
            $i = [];
            // MHR-F28256         Ms. Viktoria BERECZ
            // 13/02/17           16/02/17         1          DBB                                  1        0      0       € 297,00
            $array = $this->match('#(MHR-\w+)\s+(.+?)\s+(\d+/\d+/\d+)\s+(\d+/\d+/\d+)\s+.*?(€|\$)\s*([\d,]+)#', $text, true);

            if (!empty($array)) {
                $i = $result;
                $i['ConfirmationNumber'] = $array[0];
                $i['GuestNames'] = $array[1];
                $i['CheckInDate'] = strtotime($this->normalizeDate($array[2]));
                $i['CheckOutDate'] = strtotime($this->normalizeDate($array[3]));
                $i['Currency'] = preg_replace(['/^€$/', '/^\$$/'], ['EUR', 'USD'], $array[4]);
                $i['Total'] = (float) str_replace(',', '.', $array[5]);
            }

            $this->result[] = $i;
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function normalizeDate($subject)
    {
        $pattern = ['#(\d+)/(\d+)/(\d+)#'];
        $replacement = ['$2/$1/$3'];

        return preg_replace($pattern, $replacement, $subject);
    }

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
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
