<?php

namespace AwardWallet\Engine\autoslash\Email;

// it-3879645.eml, it-3879646.eml, it-3883332.eml, it-3884471.eml

class CarRentalConfirmation extends \TAccountChecker
{
    public $mailFiles = "autoslash/it-3879645.eml, autoslash/it-3879646.eml, autoslash/it-3883332.eml, autoslash/it-3884471.eml";

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cs.travelpn.com') !== false
            || stripos($from, '@autoslash.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && preg_match('/\[AutoSlash\.com\].+AutoSlash\.com[\s]*Reservation/i', $headers['subject'])
            || isset($headers['from']) && stripos($headers['from'], 'service.tpn@cs.travelpn.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $plain = $parser->getPlainBody();

        return stripos($plain, 'Your AutoSlash.com Trip ID is:') !== false
            && stripos($plain, 'Please reference your AutoSlash.com Trip ID') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $plain = $parser->getPlainBody();
        $it = $this->ParseEmail($plain);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'CarRentalConfirmation',
        ];
    }

    protected function ParseEmail($plain)
    {
        $it = [];
        $it['Kind'] = 'L';
        $lines = array_values(array_filter(array_map('trim', explode("\n", $plain)), 'strlen'));

        foreach ($lines as $i => $line) {
            if (!isset($it['TripNumber'])) {
                if (preg_match('/Trip ID is:\s*([\d\s]+)$/i', $line, $matches)) {
                    $it['TripNumber'] = str_replace(" ", '', $matches[1]);
                }
            }

            if (!isset($it['Number'])) {
                if (preg_match('/^Confirmation\s*#:\s*([A-Z\d]+)$/i', $line, $matches)) {
                    $it['Number'] = $matches[1];
                }
            }

            if (!isset($it['Status'])) {
                if (preg_match('/^Status:\s*([\w]+)$/i', $line, $matches)) {
                    $it['Status'] = $matches[1];
                }
            }

            if (!isset($it['RentalCompany'])) {
                if (preg_match('/^Rental Company:\s*(.+)$/i', $line, $matches)) {
                    $it['RentalCompany'] = $matches[1];
                }
            }

            if (!isset($it['RenterName'])) {
                if (preg_match('/^Driver:\s*(.+)$/i', $line, $matches)) {
                    $it['RenterName'] = $matches[1];
                }
            }

            if (!isset($it['CarType'])) {
                if ($line === 'Car:') {
                    $it['CarType'] = $lines[$i + 1];
                }
            }

            if (!isset($it['PickupDatetime'])) {
                if (preg_match('/^Pick-up:\s*([\d]{2}:[\d]{2}(AM|PM)) [\w]{3}, ([\w]{3} [\d]{1,2}, [\d]{4})$/i', $line, $matches)) {
                    $datePic = str_replace(',', '', $matches[3]);
                    $it['PickupDatetime'] = strtotime($datePic . ' ' . $matches[1]);
                }
            }

            if (!isset($it['DropoffDatetime'])) {
                if (preg_match('/^Drop-off:\s*([\d]{2}:[\d]{2}(AM|PM)) [\w]{3}, ([\w]{3} [\d]{1,2}, [\d]{4})$/i', $line, $matches)) {
                    $dateDro = str_replace(',', '', $matches[3]);
                    $it['DropoffDatetime'] = strtotime($dateDro . ' ' . $matches[1]);
                }
            }

            if (!isset($it['PickupLocation'])) {
                if ($line === 'Pick up Location:') {
                    $it['PickupLocation'] = $lines[$i + 1];
                }
            }

            if (!isset($it['DropoffLocation'])) {
                if (preg_match('/^Drop off Location:\s*(.+)$/i', $line, $matches)) {
                    $it['DropoffLocation'] = $matches[1];
                }
            }

            if (!isset($it['PromoCode'])) {
                if (preg_match('/Promo Code:\s*([\d]+)/i', $line, $matches)) {
                    $it['PromoCode'] = $matches[1];
                }
            }

            if (!isset($it['Currency']) || !isset($it['TotalCharge'])) {
                if (preg_match('/^Total:\s*(USD)([.\d]+)$/i', $line, $matches)) {
                    $it['Currency'] = $matches[1];
                    $it['TotalCharge'] = $matches[2];
                }
            }
        }

        if (!isset($it['DropoffLocation']) && isset($it['PickupLocation'])) {
            $it['DropoffLocation'] = $it['PickupLocation'];
        }

        return $it;
    }
}
