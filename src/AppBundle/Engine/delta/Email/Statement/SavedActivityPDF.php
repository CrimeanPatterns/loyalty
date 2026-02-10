<?php

namespace AwardWallet\Engine\delta\Email\Statement;

class SavedActivityPDF extends \TAccountChecker
{
    public $mailFiles = "delta/statements/st-999.eml";

    protected $month3l = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    private $levelsStr = 'GOLD DIAMOND PLATINUM SILVER';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = $this->ParseEmail($parser);

        return [
            'parsedData' => $result,
            'emailType'  => 'SavedActivityPDF',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return stripos($text, 'ACCOUNT ACTIVITY DETAILS') !== false && stripos($text, 'MEDALLION QUALIFICATION MILES') !== false;
        }

        return false;
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $props = [];
        $activity = [];
        $result = ['Properties' => &$props, 'Activity' => &$activity];

        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
        } else {
            return $result;
        }
        $lines = explode("\n", $text);
        $lines = array_map(function ($s) {
            return str_replace('Not Applicable', '--', CleanXMLValue($s));
        }, $lines);
        $lines = array_filter($lines, function ($s) {
            return strlen($s) > 0 && $s !== 'Date' && $s !== 'Activity' && preg_match('/^Page \d+/', $s) === 0;
        });
        $statement = '';
        $num = 0;

        while (count($lines) > 0) {
            $l = array_shift($lines);
            $statement .= ' ' . $l;
            $num++;

            if (stripos($l, 'ACTIVITY DETAILS') === 0) {
                break;
            }
        }

        if ($num > 100) {
            $this->http->Log('first part too long');

            return $result;
        }

        if (preg_match('/SKYMILES \# (\d+)\b .+ TOTAL AVAILABLE MILES ([\d\,]+)\b/', $statement, $m) > 0) {
            $props = [
                'Login'   => $m[1],
                'Number'  => $m[1],
                'Balance' => str_replace(',', '', $m[2]),
            ];
        }

        if (preg_match("/ACTIVITY DETAILS .+? (\w+) MEDALLION/", $statement, $m) > 0
            && stripos($this->levelsStr, $m[1]) !== false
            && isset($props)
        ) {
            $props['Level'] = ucwords($m[1] . ' MEDALLION');
        }

        while (count($lines) > 0) {
            $skip = false;
            $header = [];
            $desc = [];
            $current = [];

            for ($i = 0; $i < 5; $i++) {
                $line = array_shift($lines);

                if (!isset($header['d']) && preg_match('/^\d{2}$/', $line) > 0) {
                    $header['d'] = $line;
                } elseif (!isset($header['m']) && preg_match('/^[A-Z]{3}$/', $line) > 0 && in_array($line, $this->month3l)) {
                    $header['m'] = $line;
                } elseif ($i !== 0 && !isset($header['y']) && preg_match('/^(20\d{2})\b/', $line, $m) > 0) {
                    $header['y'] = $m[1];

                    break;
                } elseif (strlen($line) > 10) {
                    $desc[] = $line;
                }
            }
            $header['desc'] = array_pop($desc);

            if (!isset($header['m']) && isset($header['desc']) && preg_match('/^([A-Z]{3})\b\s*(.+)$/', $header['desc'], $m) > 0) {
                $header['m'] = $m[1];
                $header['desc'] = $m[2];
            }

            if (count($header) === 4 && ($date = strtotime(sprintf('%s %s %s', $header['d'], $header['m'], $header['y'])))) {
                $current['Posting Date'] = $date;
                $current['Description'] = $header['desc'];
            } else {
                $this->http->Log('history parse failed 1: ' . var_export($header, true));
                $skip = true;
            }

            while (count($lines) > 0) {
                $line = array_shift($lines);

                if (preg_match('/^TOTAL (?<i>.+) (TOTAL MILES|MILES REDEEMED)$/', $line, $m) > 0) {
                    if ($skip) {
                        break;
                    }
                    $line = array_shift($lines);
                    $current['Total Miles'] = str_replace(',', '', trim($line, '+'));
                    $items = explode(" ", $m['i']);

                    if (count($items) === 5) {
                        $current = array_merge($current, [
                            'MQM Earned'  => $items[0],
                            'MQS Earned'  => $items[1],
                            'MQD Earned'  => $items[2],
                            'Miles'       => $items[3],
                            'Bonus Miles' => $items[4],
                        ]);
                    }

                    if (count($current) !== 8) {
                        $this->http->Log('history parse failed 2: ' . var_export($current, true));
                    } else {
                        $activity[] = $current;
                    }

                    break;
                } elseif (preg_match('/^.+? ((\w+) Medallion)$/', $line, $m) > 0
                    && stripos($this->levelsStr, $m[2]) !== false
                ) {
                    $props['Level'] = $m[1];
                }
            }
        }

        return $result;
    }
}
