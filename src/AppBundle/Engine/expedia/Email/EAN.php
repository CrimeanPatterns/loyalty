<?php

namespace AwardWallet\Engine\expedia\Email;

class EAN extends \TAccountChecker
{
    public $mailFiles = "expedia/it-11.eml, expedia/it-12.eml, expedia/it-2662181.eml, expedia/it-2843119.eml, expedia/it-2845710.eml, expedia/it-53.eml, expedia/it-6630790.eml, expedia/it-7.eml, expedia/it-70.eml, expedia/it-71.eml, expedia/it-8.eml, expedia/it-9.eml";

    private $plain = false;

    public function detectEmailFromProvider($from)
    {
        return preg_match("/@(ian|expedia)\.com/", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("/@(ian|expedia)\.com/", $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        return stripos($body, 'ExpediaAffiliate.com') !== false
            || stripos($body, 'Expedia Affiliate Network') !== false
            || stripos($body, 'expedia.com') !== false
            || stripos($body, 'Your reservation is confirmed and your card has been charged') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response["body"])) {
            $this->http->SetBody($parser->getPlainBody());
            $this->plain = true;
        }
        $type = $this->getEmailType();

        switch ($type) {
            case 'PlainReservation':
                $result = $this->ParsePlainReservation();

                break;

            case 'ConfirmedReservation':
                $result = $this->ParseConfirmedReservation();

                break;

            case 'ConfirmedReservation2017':
                $result = $this->ParseConfirmedReservation2017();

                break;

            case 'PendingCancellation':
                $result = $this->ParsePendingCancellation();

                break;

            default:
                $result = null;
        }

        return ['parsedData' => $result, 'emailType' => $type];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    //it-9.eml
    protected function ParsePendingCancellation()
    {
        $text = text($this->http->Response['body']);
        $result = ["Kind" => "R"];
        $result["ConfirmationNumber"] = $this->http->FindSingleNode("//td[contains(., 'Itinerary Number:') and not(.//td)]/following-sibling::td[1]");
        $result["HotelName"] = $this->http->FindSingleNode("//tr[.//a[contains(text(), 'reviews')] and td[img]]/ancestor::tr[1]/preceding-sibling::tr[1]");
        $result["Address"] = $this->http->FindSingleNode("//tr[.//a[contains(text(), 'reviews')] and td[img]]/ancestor::tr[1]/following-sibling::tr[1]");
        $result['CheckInDate'] = strtotime($this->http->FindSingleNode("//td[contains(., 'Check-in:') and not(.//td)]/following-sibling::td[1]"));
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode("//td[contains(., 'Check-out:') and not(.//td)]/following-sibling::td[1]"));
        $result['RoomType'] = $this->http->FindSingleNode("//td[contains(., 'Room type:') and not(.//td)]/following-sibling::td[1]");
        $result['Rooms'] = $this->http->FindSingleNode("//td[contains(., 'Rooms:') and not(.//td)]/following-sibling::td[1]");
        $result['Guests'] = $this->http->FindSingleNode("//td[contains(., 'Guests:') and not(.//td)]/following-sibling::td[1]", null, true, '/(\d+) Adults/');
        $result['Status'] = orval(re('#Your\s+reservation\s+(cancellation\s+is\s+pending)#i', $text), 'Cancelled');
        $result['CancellationPolicy'] = $this->http->FindSingleNode("//tr[contains(., 'Cancellation Policy') and not(.//tr)]/following-sibling::tr[1]");

        if (!isset($result['CancellationPolicy'])) {
            $result['CancellationPolicy'] = $this->http->FindSingleNode("//tr[contains(., 'Cancellation Policy') and not(.//tr)]/ancestor::tr[1]/following-sibling::tr[1]");
        }

        return ["Itineraries" => [$result]];
    }

    // it-8.eml
    protected function ParseConfirmedReservation()
    {
        $result = ["Kind" => "R"];
        $result["GuestNames"] = $this->http->FindSingleNode("//td[contains(., 'Customer name:') and not(.//td)]/following-sibling::td[1]");
        $result["ConfirmationNumber"] = $this->http->FindSingleNode("//td[contains(., 'Itinerary Number:') and not(.//td)]/following-sibling::td[1]");
        $result['HotelName'] = orval(
            $this->http->FindSingleNode("//img[contains(@id, 'confirm-hotel-image')]/@alt"),
            re('#View\s+or\s+cancel\s+your\s+reservation\s+online\s+Hotel\s+(.*)#', text($this->http->Response['body']))
        );
        $result['Address'] = $this->http->FindSingleNode("//td[contains(., 'Address:') and not(.//td)]/following-sibling::td[1]");
        $result['Phone'] = $this->http->FindSingleNode("//td[contains(., 'Phone:') and not(.//td)]/following-sibling::td[1]");

        if (!$result['Phone']) {
            $result['Phone'] = null;
        }
        $result['Fax'] = $this->http->FindSingleNode("//td[contains(., 'Fax:') and not(.//td)]/following-sibling::td[1]");
        $result['CheckInDate'] = strtotime($this->http->FindSingleNode("//td[contains(., 'Check-in:') and not(.//td)]/following-sibling::td[1]"));
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode("//td[contains(., 'Check-out:') and not(.//td)]/following-sibling::td[1]"));
        $result['Guests'] = $this->http->FindSingleNode("//td[contains(., 'Number of guests:') and not(.//td)]/following-sibling::td[1]", null, true, '/Adults: (\d+)/');
        $result['RoomType'] = $this->http->FindSingleNode("//th[contains(., 'Room Type')]/ancestor::table[1]/tbody/tr[1]/td[2]");
        $currency = $this->http->FindSingleNode("//*[contains(text(), 'Cost per night and per room in')]", null, true, '/Cost per night and per room in (.+)/');

        if (preg_match("/[A-Z]{3}/", $currency, $m)) {
            $result['Currency'] = $m[0];
        } else {
            $result['Currency'] = $currency;
        }
        $result['Rate'] = $this->http->FindSingleNode("//th[contains(., 'Total per night')]/ancestor::table[1]/tbody/tr[1]/td[last()]");

        if (!empty($result['Rate'])) {
            $result['Rate'] .= ' per night';
        }
        $result['Cost'] = str_ireplace(",", "", $this->http->FindSingleNode("//tr[contains(., 'Total Per room') and not(.//tr)]/td[last()]", null, true, '/[\d\.\,]+$/'));
        $result['Taxes'] = str_ireplace(",", "", $this->http->FindSingleNode("//td[contains(., 'Tax Recovery Charges')]/following-sibling::td[last()]", null, true, '/[\d\.\,]+$/'));
        $nodes = $this->http->XPath->query("//th[contains(., 'Total cost of stay')]/ancestor::table[1]");

        if ($nodes->length > 0) {
            $total = $this->http->FindSingleNode("tfoot/tr[1]/td[last()]", $nodes->item(0), true, '/[\d\.\,]+$/');

            if (!isset($total)) {
                $total = $this->http->FindSingleNode("tbody/tr[last()]/td[last()]", $nodes->item(0), true, '/[\d\.\,]+$/');
            }

            if (isset($total)) {
                $result['Total'] = str_ireplace(",", "", $total);
            }
        }
        $result['CancellationPolicy'] = $this->http->FindSingleNode("//tr[contains(., 'Cancellation Policy') and not(.//tr)]/following-sibling::tr[2]");
        $userEmail = $this->http->FindSingleNode("//td[contains(., 'Customer email:') and not(.//td)]/following-sibling::td[1]");

        return ["Itineraries" => [$result], 'userEmail' => $userEmail];
    }

    // it-6630790.eml
    protected function ParseConfirmedReservation2017()
    {
        $result = ["Kind" => "R"];
        $result["GuestNames"] = $this->http->FindSingleNode("//td[contains(., 'Guest Name:') and not(.//td)]/following-sibling::td[1]");
        $result["TripNumber"] = $this->http->FindSingleNode("//td[contains(., 'Itinerary Number:') and not(.//td)]/following-sibling::td[1]");
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Status:')]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("#^\s*(\w+),\s+([A-Z\d]+)\s*$#", $node, $m)) {
            $result["Status"] = $m[1];
            $result["ConfirmationNumber"] = $m[2];
        } else {
            $result["ConfirmationNumber"] = $result["TripNumber"];
        }
        $result['HotelName'] = orval(
            $this->http->FindSingleNode("//img[contains(@src, 'expedia.com/hotels/')]/@alt"),
            $this->http->FindSingleNode("//text()[normalize-space(.)='Hotel']/following::text()[normalize-space(.)][1]")
        );
        $result['Address'] = $this->http->FindSingleNode("//td[normalize-space(.)='Address:' and not(.//td)]/following-sibling::td[1]");
        $result['Phone'] = $this->http->FindSingleNode("//td[(contains(., 'Phone:') or contains(., 'Telephone:')) and not(.//td)]/following-sibling::td[1]");

        if (!$result['Phone']) {
            $result['Phone'] = null;
        }
        $result['Fax'] = $this->http->FindSingleNode("//td[contains(., 'Fax:') and not(.//td)]/following-sibling::td[1]");

        $roots = $this->http->XPath->query("//text()[normalize-space(.)='Check-in:']/ancestor::tr[1][contains(.,'Check-out:')]/following-sibling::tr[1]");

        if ($roots->length > 0) {
            $root = $roots->item(0);
        } else {
            $root = null;
        }

        $result['CheckInDate'] = strtotime($this->http->FindSingleNode("./td[1]", $root));
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root));
        $result['Guests'] = $this->http->FindSingleNode("./td[5]", $root, true, '/(\d+) adult/');
        $result['Rooms'] = $this->http->FindSingleNode("./td[3]", $root, true, '/(\d+)/');

        $result['RoomType'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Room Type')]/ancestor::td[1]/following-sibling::td[1]");
        $currency = $this->http->FindSingleNode("//*[contains(text(), 'Cost per night and per room in')]", null, true, '/Cost per night and per room in (.+)/');

        if (preg_match("/[A-Z]{3}/", $currency, $m)) {
            $result['Currency'] = $m[0];
        } else {
            $result['Currency'] = $currency;
        }
        $result['Rate'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Total per night')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]");

        if (!empty($result['Rate'])) {
            $result['Rate'] .= ' per night';
        }
        $result['Cost'] = str_ireplace(",", "", $this->http->FindSingleNode("//text()[normalize-space(.)= 'Total per room']/ancestor::td[1]/following-sibling::td[last()]", null, true, '/[\d\.\,]+$/'));
        $result['Taxes'] = str_ireplace(",", "", $this->http->FindSingleNode("//text()[normalize-space(.)= 'Taxes']/ancestor::td[1]/following-sibling::td[1]", null, true, '/[\d\.\,]+$/'));
        $result['Total'] = str_ireplace(",", "", $this->http->FindSingleNode("//text()[normalize-space(.)= 'Grand Total']/following::p[2]", null, true, '/[\d\.\,]+$/'));

        if (empty($result['Currency'])) {
            $result['Currency'] = str_replace('$', 'USD', $this->http->FindSingleNode("//text()[normalize-space(.)= 'Total per room']/ancestor::td[1]/following-sibling::td[last()]", null, true, '/(.+?)\s*[\d\.\,]+$/'));
        }
        $result['CancellationPolicy'] = $this->http->FindSingleNode("//text()[normalize-space(.)= 'Cancellation Policy']/following::p[1]");
        $userEmail = $this->http->FindSingleNode("//td[contains(., 'Guest Email:') and not(.//td)]/following-sibling::td[1]");

        return ["Itineraries" => [$result], 'userEmail' => $userEmail];
    }

    // it-7.eml
    protected function ParsePlainReservation()
    {
        $result = ["Kind" => "R"];
        $userEmail = null;

        if ($this->plain) {
            $body = $this->http->Response['body'];
        } else {
            $body = implode("\n", $this->http->FindNodes("//text()[normalize-space(.)]"));
        }
        $body = preg_replace("/\r?\n\r?[\s\t]*\_+/ims", "__________", $body);

        if (preg_match('/Itinerary \#: (\d+)/', $body, $m)) {
            $result['ConfirmationNumber'] = $m[1];
        }

        $lines = explode("\n", $body);
        $isCancellation = false;
        $cancellation = "";
        $lines = array_values(array_filter($lines, "trim"));

        foreach ($lines as $i => $line) {
            if (stripos($line, 'Hotel__________') !== false && !isset($result['HotelName'])) {
                if (!isset($lines[$i + 2])) {
                    $lines = array_merge($lines, ["", ""]);
                }
                $result['HotelName'] = trim(preg_replace("/\(\s*id[^\)]+\)/i", "", $lines[$i + 1]));
                $result['Address'] = trim($lines[$i + 2]);
            }

            if (preg_match("/Check-in:.*?([a-zA-Z]{3} \d{1,2}\, \d{4})/", $line, $m)) {
                $result['CheckInDate'] = strtotime($m[1]);
            } elseif (preg_match("/Check-in:.*?(\d+\/\d+\/\d{4})/", $line, $m)) {
                $result['CheckInDate'] = strtotime($m[1]);
            }

            if (preg_match("/Check-out:.*?([a-zA-Z]{3} \d{1,2}\, \d{4})/", $line, $m)) {
                $result['CheckOutDate'] = strtotime(trim($m[1]));
            } elseif (preg_match("/Check-out:.*?(\d+\/\d+\/\d{4})/", $line, $m)) {
                $result['CheckOutDate'] = strtotime($m[1]);
            }

            if (preg_match("/Room (\d+).+confirmation \#/", $line, $m)) {
                $result['Rooms'] = $m[1];

                if (isset($lines[$i + 1]) && preg_match("/Confirmed for (.+), for (\d+) adults/", $lines[$i + 1], $ma)) {
                    $result['GuestNames'] = $ma[1];
                    $result['Guests'] = $ma[2];
                }
            }

            if (preg_match("/Cost per night in ([^\(]+)/", $line, $m)) {
                if (preg_match("/[A-Z]{3}/", $m[1], $ma)) {
                    $result['Currency'] = $ma[0];
                } else {
                    $result['Currency'] = $m[1];
                }

                if (isset($lines[$i + 1]) && preg_match("/[\d\.\,]+$/", $lines[$i + 1], $ma)) {
                    $result['Rate'] = $ma[0] . " per night";
                }
            }

            if (stripos($line, "Total per room") !== false && isset($lines[$i + 1]) && preg_match("/[\d\.\,]+$/", $lines[$i = 1], $m)) {
                $result["Cost"] = str_ireplace(",", "", $m[0]);
            }

            if (preg_match("/Total tax recovery charges and service fees:\D([\d\.\,]+)/", $line, $m)) {
                $result["Taxes"] = str_ireplace(",", "", $m[1]);
            }
            $totalPresent = (
                stripos($line, 'Total for for entire stay') !== false
                || stripos($line, 'Total for entire stay') !== false
                || stripos($line, 'Total cost for entire stay') !== false
            );

            if ($totalPresent && isset($lines[$i + 1]) && preg_match("/^[^\d]?([\d\.\,]+)/u", trim($lines[$i + 1]), $m)) {
                $result['Total'] = str_ireplace(",", "", $m[1]);
            }

            if ($isCancellation === true) {
                if (preg_match("/Room \d+:/", $line)) {
                    continue;
                }
                $line = trim($line);
                $cancellation .= " $line";

                if (preg_match("/\.$/", $line)) {
                    $isCancellation = false;
                }
            }

            if (stripos($line, "Cancellation Policy_") !== false) {
                $isCancellation = true;
            }

            if (preg_match("/Customer: [^<]+<\s*(\S+)\s*>/", $line, $m) && !isset($userEmail)) {
                $userEmail = $m[1];
            }
        }

        if (!empty($cancellation)) {
            $result['CancellationPolicy'] = trim($cancellation);
        }

        return ["Itineraries" => [$result], "userEmail" => $userEmail];
    }

    protected function getEmailType()
    {
        if ($this->http->FindPreg("/reservation was recently made through your site/ims")) {
            return 'PlainReservation';
        }

        if ($this->http->FindSingleNode("//text()[contains(., 'Your reservation is confirmed and your card has been charged')]")) {
            if ($this->http->FindSingleNode("//text()[normalize-space(.)='Check-in:']/ancestor::tr[1][contains(.,'Check-out:')]")) {
                return 'ConfirmedReservation2017';
            } else {
                return 'ConfirmedReservation';
            }
        }

        if ($this->http->FindSingleNode("//text()[contains(., 'Your reservation cancellation is pending') or contains(., 'Your reservation has been cancelled')]")) {
            return 'PendingCancellation';
        }

        return 'undefined';
    }
}
