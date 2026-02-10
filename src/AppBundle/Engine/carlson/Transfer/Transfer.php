<?php

namespace AwardWallet\Engine\carlson\Transfer;

class Transfer extends \TAccountCheckerCarlson
{
    public static $providersMap = [
        // AW prov code => carlson code
        'aeromexico'       => 'AM',
        'aeroplan'         => 'AC',
        'airberlin'        => 'AB',
        'airchina'         => 'CA',
        'aa'               => 'AA',
        'asiana'           => 'OZ',
        'british'          => 'BA',
        'asia'             => 'CX',
        'czech'            => 'OK',
        'delta'            => 'DL',
        'skywards'         => 'EK',
        'etihad'           => 'EY',
        'frontierairlines' => 'YX',
        'gulfair'          => 'GF',
        'icelandair'       => 'FI',
        'japanair'         => 'JL',
        'jetairways'       => '9W',
        'airfrance'        => 'KL',
        'lanpass'          => 'LA',
        'swissair'         => 'LH',
        'eurobonus'        => 'SK',
        'singaporeair'     => 'SQ',
        'rapidrewards'     => 'WN',
        'dividendmiles'    => 'US',
        'mileageplus'      => 'UA',
    ];

    // InitBrowser from TAccountCheckerCarlson

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        //$this->ArchiveLogs = true;

        $this->http->Log('Checking transfer options');

        if (!isset(self::$providersMap[$targetProviderCode])) {
            throw new \UserInputError("Target provider $targetProviderCode is not supported");
        }
        $this->http->Log('Target provider OK');
        // TODO: Check in provs site select

        if (!is_numeric($numberOfMiles)) {
            $msg = "Source rewards quantity $numberOfMiles should be numeric";

            throw new \UserInputError($msg);
        }

        if ($numberOfMiles % 2000 !== 0) {
            $msg = "Unsupported source rewards quantity $numberOfMiles";

            throw new \UserInputError($msg);
        }
        $this->http->Log('Source rewards quantity OK');
        $msg = "Transferring $numberOfMiles rewards to $targetProviderCode account $targetAccountNumber";
        $this->http->Log($msg);
        $this->http->GetURL('https://www.clubcarlson.com/fgp/redeem/catalog/am/home.do');

        if (!$this->http->ParseForm('fgpRedeemForm')) {
            return false;
        }
        $this->http->log(sprintf('[INFO] form url = %s', $this->http->FormURL));
        $airlineProgramCode = self::$providersMap[$targetProviderCode];

        if (!$this->http->FindSingleNode('//select[@name="airlineProgramCode"]/option[@value="' . $airlineProgramCode . '"]')) {
            $this->http->Log("Seems that provider $targetProviderCode is not supported by Carlson. This should be checked manually on the site.", LOG_LEVEL_ERROR);

            return false;
        }
        $this->http->FormURL = 'https://www.clubcarlson.com/fgp/redeem/change/am/home.do';
        $this->http->Form = [];
        $inputs = [
            'airlineProgramCode'         => $airlineProgramCode,
            'frequentFlyerNumber'        => '',
            'productCatalog.totalMiles'  => '0',
            'productCatalog.totalPoints' => '0',
        ];

        foreach ($inputs as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('fgpRedeemForm')) {
            return false;
        }
        // $this->http->log('[INFO] fgpRedeemForm');
        // $this->http->log(print_r($this->http->Form, true));

        $this->http->FormURL = 'https://www.clubcarlson.com/fgp/redeem/review/am/home.do';
        $this->http->Form = [];
        $inputs = [
            'airlineProgramCode'                  => self::$providersMap[$targetProviderCode],
            'frequentFlyerNumber'                 => $targetAccountNumber,
            'productCatalog.products[0].quantity' => $numberOfMiles / 2000,
            'productCatalog.products[1].quantity' => '0',
            'productCatalog.products[2].quantity' => '0',
            'productCatalog.totalMiles'           => $numberOfMiles / 10, // TODO: Take it from site
            'productCatalog.totalPoints'          => $numberOfMiles, // TODO: Take it from site
            'product[0].productId'                => '31',
            'product[0].subTotalPoints'           => '0',
            'product[1].productId'                => '32',
            'product[1].subTotalPoints'           => '0',
            'product[2].productId'                => '33',
            'product[2].subTotalPoints'           => '0',
        ];

        foreach ($inputs as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        // $this->http->setDefaultHeader('Referer', 'https://www.clubcarlson.com/fgp/redeem/catalog/am/home.do');
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($errors = $this->http->FindNodes('//div[@class="globalerrors"]')) {
            // TODO: Move error message creation to some generic place from here and from other similar places
            if (count($errors) == 1) {
                $errorMessage = trim($errors[0]);
            } else {
                $errorMessage = 'Errors: ' . implode(' ', $errors);
            }
            $this->http->Log($errorMessage, LOG_LEVEL_ERROR);

            throw new \ProviderError($errorMessage); // Is it always provider error?
        }

        // TODO: Check submit data

        if (!$this->http->ParseForm('fgpRedeemForm')) {
            return false;
        }

        $this->http->FormURL = 'https://www.clubcarlson.com/fgp/redeem/finish/am/home.do';
        $this->http->SetInputValue('agreesWithTerms', 'on');

        if (isset($this->idle) && $this->idle) {
            $this->http->log('[INFO] idle run, no final submit');

            return true;
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($msg = $this->http->FindPreg('#Your\s+points\s+will\s+be\s+processed\s+in\s+5-7\s+days\s+and\s+sent\s+to\s+your\s+frequent\s+flyer\s+account\.\s+Miles\s+may\s+take\s+4-6\s+weeks\s+to\s+post\s+to\s+your\s+frequent\s+flyer\s+account\.#i')) {
            $this->http->Log($msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        $this->http->Log('Could not parse known success message. Transfer may be ok and may be failed, result should be checked manually.', LOG_LEVEL_ERROR);

        return false;
    }
}
