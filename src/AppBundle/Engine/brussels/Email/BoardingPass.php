<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "brussels/it-147739179.eml, brussels/it-244891622.eml, brussels/it-274284876.eml, brussels/it-7663509.eml, brussels/it-7750138.eml, brussels/it-7946180.eml, brussels/it-8628391.eml, brussels/it-9786821.eml";

    public $reBody = "BRUSSELS";

    public $reBody2 = "If this boarding pass is not displayed";

    public $reFrom = "noreply@brusselsairlines.com";

    public $reSubject = "Brussels Airlines Boarding Pass";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHtmlBody();
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#-{2,}=.{0,8}(P|p)art.*#", "\n", $texts);

            $body = '';
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;

            while ($posBegin1 !== false && $i < 50) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\n", $posBegin);
                $str = substr($texts, $posBegin1, $posBegin - $posBegin1);

                if (empty($posEnd)) {
                    $textBlock = substr($texts, $posBegin);
                } else {
                    $textBlock = substr($texts, $posBegin, $posEnd - $posBegin);
                }

                if (preg_match("#filename=.*\.htm.*base64#s", $str)) {
                    $body .= htmlspecialchars_decode(base64_decode($textBlock));
                } elseif (preg_match("#quoted-printable#s", $str)) {
                    $body .= quoted_printable_decode($textBlock);
                } else {
                    $body .= $textBlock;
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
            $this->http->setEmailBody($body);
        }

        $class = explode('\\', __CLASS__);
        $class = end($class);

        $type = '';
        //$xpath = "//*[name() = 'div' or name() = 'p'][contains(., 'Gate may change - check screens') and not(descendant::div)]/following-sibling::div[1]/descendant::tr[not(descendant::tr)][normalize-space(.)]";
        $xpath = "//*[contains(., 'Gate may change - check screens') and not(descendant::div)]/following-sibling::div[1]/descendant::tr[not(descendant::tr)][normalize-space(.)]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $type = 'Html';
            $email->setType($class . $type);

            return $this->parseEmail($email);
        }

        $bodyP = $parser->getPlainBody();

        if (empty($bodyP) || stripos($bodyP, 'Gate may change') === false) {
            $bodyP = str_replace("&nbsp;", " ", strip_tags($body));
        } elseif (stripos($bodyP, '<table') !== false) {
            $bodyP = str_replace("&nbsp;", " ", strip_tags($bodyP));
        }

        $type = 'Plain';
        $email->setType($class . $type);

        $this->parseEmailPlain($email, $bodyP);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false) {
            return true;
        }
        $body = $parser->getPlainBody();

        return stripos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false || strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        if ($href = $this->http->FindSingleNode("//a[normalize-space(.)='online version']/@href")) {
            $bp = $email->add()->bpass();

            $bp->setUrl($href);
        }

        $s = $f->addSegment();

        $flight = $this->http->FindSingleNode("//*[normalize-space(text())='Flight']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        if (preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b/", $flight, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);

            if (isset($bp)) {
                $bp->setFlightNumber($m[1] . ' ' . $m[2]);
            }
        }

        $seat = $this->http->FindSingleNode("//tr[contains(., 'Flight') and contains(., 'Seat')]/following-sibling::tr[1]/td[normalize-space(.)][last()]");

        if (!preg_match("/^(?:GATE|ADOC)$/i", $seat)) {
            $s->extra()
                ->seat($seat);
        }

        $xpath = "//*[contains(., 'Gate may change - check screens') and not(descendant::div)]/following-sibling::div[1]/descendant::tr[not(descendant::tr)][normalize-space(.)]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $pasngr = $this->http->FindSingleNode($xpath . '[1]');

            if (preg_match('/(\D+)\/(\D+)/', $pasngr, $m)) {
                $f->general()
                    ->traveller(trim($m[2]) . ' ' . trim($m[1]), true);

                if (isset($bp)) {
                    $bp->setTraveller(trim($m[2]) . ' ' . trim($m[1]));
                }
            }

            $date = $this->http->FindSingleNode($xpath . "[contains(., 'Boarding')]/ancestor::tr/following-sibling::tr[3]", null, true, "#(\d+\/\d+\/\d{4})#");
            $re = '/(?:(\d{1,2})\:?(\d{2})|-)\s*(.+)/';

            $dep = $this->http->FindSingleNode($xpath . "[contains(., 'Boarding')]/ancestor::tr/following-sibling::tr[1]");

            if (preg_match($re, $dep, $m)) {
                $time = !empty($m[1]) ? $m[1] . ':' . $m[2] : '';

                if (empty($time)) {
                    $s->departure()
                        ->noDate();
                } else {
                    $s->departure()
                        ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $time) : null);

                    if (isset($bp)) {
                        $bp->setDepDate(!empty($date) ? $this->normalizeDate($date . ', ' . $time) : null);
                    }
                }

                if (preg_match("/(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $m[3], $mat)) {
                    $s->departure()
                        ->code($mat[2])
                        ->name(trim($mat[1]));

                    if (isset($bp)) {
                        $bp->setDepCode($mat[2]);
                    }
                } else {
                    $s->departure()
                        ->noCode()
                        ->name($m[3]);
                }
            }

            $arr = $this->http->FindSingleNode($xpath . "[contains(., 'Boarding')]/ancestor::tr/following-sibling::tr[2]");

            if (preg_match($re, $arr, $m)) {
                $time = !empty($m[1]) ? $m[1] . ':' . $m[2] : '';

                if (empty($time)) {
                    $s->arrival()
                        ->noDate();
                } else {
                    $s->arrival()
                        ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $time) : null);
                }

                if (preg_match("/(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $m[3], $mat)) {
                    $s->arrival()
                        ->code($mat[2])
                        ->name(trim($mat[1]));
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($m[3]);
                }
            }

            $cabin = $this->http->FindSingleNode("//text()[normalize-space()='Flight']/preceding::text()[normalize-space()][not(contains(normalize-space(), 'FAST LANE') or contains(normalize-space(), 'CHECK') or contains(normalize-space(), 'LIGHT'))][1]", null, true, "/^([A-Z\s]+)(?:\-|$)/u");

            if (!empty($cabin)) {
                $s->setCabin($cabin);
            }

            $account = $this->http->FindSingleNode($xpath . "[contains(., 'Boarding')]/ancestor::tr/following-sibling::tr[4]", null, true, "#([A-Z]{2}\-?[A-Z\d\-]+)#");

            if (!empty($account)) {
                $f->program()
                    ->account($account, false);
            }
            $ticket = $this->http->FindSingleNode($xpath . "[contains(., 'Boarding')]/ancestor::tr/following-sibling::tr[5]", null, true, "#([A-Z\-\d]+)#u");

            if (empty($ticket)) {
                $ticket = $this->http->FindSingleNode($xpath . "[contains(., 'Boarding')]/ancestor::tr/following-sibling::tr[4]", null, true, "#([A-Z\-\d]+)#u");
            }
            $f->issued()
                ->ticket($ticket, false);
        }

        return $email;
    }

    private function parseEmailPlain(Email $email, $text)
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        $text = preg_replace("#^>\s#m", '', $text);
        $text = preg_replace("#^ +#m", '', $text);
        $text = preg_replace("#^ +#m", '', $text);
        $text = preg_replace("#\n{3,}#", "\n\n", $text);
        $text = preg_replace("#\[image: [^\]]+\]#su", "", $text);
        $text = preg_replace("#<http[^>]+>#su", "", $text);

        if ($href = $this->http->FindSingleNode("//a[normalize-space(.)='online version']/@href")) {
            $bp = $email->add()->bpass();

            $bp->setUrl($href);
        }

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        $s = $f->addSegment();

//        $this->logger->debug('$text = '.print_r( $text,true));

        if (preg_match('#(?:\n|^)\W*([A-Z][A-Z \-*]{2,})(?:\s*-\s([A-Z]{1,2})|\s*-\s.*)?\s+Flight\s+Gate\s+Seat(?:\s+Docs)?\s+([A-Z\d]{2})(\d{1,5})\s+[\w\-]+\s+\*?(\d{1,3}[A-Z])\*?\s+#ms', $text, $m)) {
            $s->airline()
                ->name($m[3])
                ->number($m[4])
            ;

            if (isset($bp)) {
                $bp->setFlightNumber($m[3] . ' ' . $m[4]);
            }

            if (stripos(trim($m[1]), 'CHECK') === false) {
                $s->extra()
                    ->cabin(trim($m[1], '-'));
            }

            if (!empty($m[2])) {
                $s->extra()
                    ->bookingCode($m[2]);
            }

            $s->extra()
                ->seat($m[5]);
        }

        if (preg_match('#Gate may change.*\s+(.+)\s+Boarding.*(?:\n.*)?\s+(?:(\d{1,2}):?(\d{2}))\s*(.+)\s+(?:(\d{1,2}):?(\d{2})|(-))\s*(.+)\s+(\d+)\/(\d{2})\/(\d{4})\s+(?:([A-Z\d]{2}-?[\dA-Z]{5,}[-A-Z]*|-+)\s+)?(\d{9,})#u', $text, $m)) {
            if (preg_match('/(\D+)\/(\D+)/', trim($m[1]), $mat)) {
                $f->general()
                    ->traveller(trim($mat[2]) . ' ' . trim($mat[1]), true);

                if (isset($bp)) {
                    $bp->setTraveller(trim($mat[2]) . ' ' . trim($mat[1]));
                }
            }

            if (preg_match("#(.*)\(\s*([A-Z]{3})\s*\)#", $m[4], $mat)) {
                $s->departure()
                    ->code($mat[2])
                    ->name(trim($mat[1]));

                if (isset($bp)) {
                    $bp->setDepCode($mat[2]);
                }
            } else {
                $s->departure()
                    ->noCode()
                    ->name(trim($m[4]));
            }

            $date = strtotime($m[9] . '.' . $m[10] . '.' . $m[11] . ' ' . $m[2] . ':' . $m[3]);
            $s->departure()
                ->date($date);

            if (isset($bp)) {
                $bp->setDepDate($date);
            }

            if (preg_match("#(.*)\(\s*([A-Z]{3})\s*\)#", $m[8], $mat)) {
                $s->arrival()
                    ->code($mat[2])
                    ->name(trim($mat[1]));
            } else {
                $s->arrival()
                    ->noCode()
                    ->name(trim($m[8]));
            }

            if (empty($m[7])) {
                $s->arrival()
                    ->date(strtotime($m[9] . '.' . $m[10] . '.' . $m[11] . ' ' . $m[5] . ':' . $m[6]));
            } else {
                $s->arrival()
                    ->noDate();
            }

            $m[12] = trim($m[12], '-');

            if (!empty($m[12])) {
                $f->program()
                    ->account($m[12], false);
            }

            $f->issued()
                ->ticket($m[13], false);
        }

        return $email;
    }

    private function normalizeDate($s)
    {
        $in = [
            '/(\d{1,2})\/(\d{2})\/(\d{4})\,\s*(\d+:\d+)/',
        ];
        $out = [
            '$2/$1/$3, $4',
        ];

        return strtotime(preg_replace($in, $out, $s));
    }
}
