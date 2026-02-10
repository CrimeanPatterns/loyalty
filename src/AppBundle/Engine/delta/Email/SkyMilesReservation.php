<?php

namespace AwardWallet\Engine\delta\Email;

class SkyMilesReservation extends \TAccountChecker
{
    public $mailFiles = "delta/it-1667420.eml";

    // subject: Delta SkyMiles Reservation 12345
    // from noreply-itinerary@travelmarketplace.delta.com

    public function ParseEmail()
    {
        $its = [];

        $xpath = "//tr[normalize-space(.)= 'Hotel' and not(.//tr)]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\Hotel $it */
            $it = ["Kind" => "R", "GuestNames" => []];

            $it["ConfirmationNumber"] = str_replace(' ', '', $this->http->FindSingleNode("descendant::td[contains(., 'Confirmation:') and not(.//td)]", $root, true, "/Confirmation: ([\d\s]+)/"));

            $stars = $this->http->XPath->query("descendant::tr[.//img[contains(@src, 'star')] and not(.//tr) and following-sibling::tr[contains(., 'Room description')]]", $root);

            if ($stars->length > 0) {
                $it["HotelName"] = $this->http->FindSingleNode("preceding-sibling::tr[1]", $stars->item(0));
                $it["Address"] = $this->http->FindSingleNode("following-sibling::tr[1]", $stars->item(0));
                $it["RoomType"] = $this->http->FindSingleNode("following-sibling::tr[contains(., 'Room type')]", $stars->item(0), true, "/Room type: (.+)$/");
                $it["RoomTypeDescription"] = $this->http->FindSingleNode("following-sibling::tr[contains(., 'Room description')]", $stars->item(0), true, "/Room description (.+)$/");
            }

            $it["GuestNames"][] = implode(' ', $this->http->FindNodes("descendant::b[.='Travelers']/following-sibling::span[1]/span", $root));

            if ($checkin = $this->http->FindSingleNode("descendant::td[contains(., 'Check-in') and not(.//td)]/following-sibling::td[1]", $root, null, "/\d+\/\d+\/\d+.+$/")) {
                $it["CheckInDate"] = strtotime($checkin);
            }

            if ($checkout = $this->http->FindSingleNode("descendant::td[contains(., 'Check-out') and not(.//td)]/following-sibling::td[1]", $root, null, "/\d+\/\d+\/\d+.+$/")) {
                $it["CheckOutDate"] = strtotime($checkout);
            }

            $it["Guests"] = $this->http->FindSingleNode("descendant::td[contains(., 'Occupants') and not(.//td)]/following-sibling::td[1]", $root, true, "/(\d+) Adult/");

            $it["Kids"] = $this->http->FindSingleNode("descendant::td[contains(., 'Occupants') and not(.//td)]/following-sibling::td[1]", $root, true, "/(\d+) Child/");

            $its[] = $it;
        }

        return $its;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "SkyMilesReservation",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], "Delta SkyMiles Reservation") !== false
        || isset($headers['from']) && stripos($headers['from'], "@travelmarketplace.delta.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "marketplace.delta.com/") !== false;
    }
}
