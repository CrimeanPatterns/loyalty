<?php

namespace AwardWallet\Engine\finnair\Transfer;

class Transfer extends \TAccountCheckerFinnair
{
    protected $transfer_url_params = [
        "aplus" => [
            7000  => "1136",
            14000 => "1217",
            28000 => "1218",
            35000 => "1219",
            42000 => "1220",
            49000 => "1221",
        ],
        "ichotelsgroup" => [
            20000  => "1258",
            40000  => "1259",
            60000  => "1260",
            80000  => "1261",
            100000 => "1262",
        ],
    ];

    public function InitBrowser()
    {
        parent:$this->InitBrowser();
    }

    public function LoadLoginForm()
    {
        $this->http->Log("Logging in to pointshop.finnair.com");
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://pointshop.finnair.com/index.php?language=en");

        if (!($signInUrl = $this->http->FindSingleNode("//a[contains(., 'Sign In')]/@href"))) {
            return false;
        }
        $this->http->GetURL($signInUrl);

        if (!$this->http->ParseForm("fm1")) {
            return false;
        }
        $this->http->SetInputValue("username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//p[@class="form-signin-general-error"]')) {
            throw new \UserInputError($error);
        } // Is it always user input error?

        if (($allow = $this->http->FindSingleNode('//div[@id="errorContent"]/p[contains(text(), "Allow login")]')) && ($url = $this->http->FindSingleNode('//a[@id="allow"]/@href'))) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        }
        //		if (!$this->http->ParseForm("theLogForm") || !$this->http->PostForm())
        //			return false;
        return true;
    }

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $transferUrl = $this->getTransferUrl($targetProviderCode, $numberOfMiles);
        $this->http->GetURL($transferUrl);
        $this->http->Log("logged in, checking price");
        $price = $this->http->FindSingleNode("//dl[@id='price']/dd");

        if (strcasecmp($price, "Sold out") === 0) {
            throw new \ProviderError($price);
        }

        if ($this->http->FindSingleNode('//div[@id="priceblock"]//b[contains(text(), "Unfortunately you don\'t have enough points")]')) {
            throw new \UserInputError("Unfortunately you don't have enough points");
        }
        $price = preg_replace("/\D/", "", $price);

        if (strcmp($price, $numberOfMiles) !== 0) {
            $this->http->Log("Parsed price doesn't match numberOfMiles: " . $price, LOG_LEVEL_ERROR);

            return false;
        }

        if (!$this->http->ParseForm("cart_quantity") || !$this->http->PostForm()) {
            return false;
        }
        $this->http->GetURL("https://pointshop.finnair.com/checkout_shipping.php");

        if (!$this->http->ParseForm("checkout_address")) {
            return false;
        }
        $this->http->Log("Setting target account number");
        $this->http->Form["comments"] = $targetAccountNumber;

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->FindSingleNode("//h2[contains(text(), 'Order Confirmation')]")) {
            return false;
        }

        if (!$this->http->ParseForm("checkout_confirmation") || !$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//h2[contains(text(), 'Your Order Has Been Processed')]")) {
            $this->http->Log('found success message');
            $this->ErrorMessage = 'Your Order Has Been Processed';

            return true;
        }

        return true;
    }

    protected function getTransferUrl($code, $miles)
    {
        if (!isset($this->transfer_url_params[$code])) {
            throw new \UserInputError("Can't transfer to target provider");
        }

        if (!isset($this->transfer_url_params[$code][$miles])) {
            throw new \UserInputError("Invalid number of points");
        }

        return sprintf("https://pointshop.finnair.com/product_info.php?products_id=%s&language=en", $this->transfer_url_params[$code][$miles]);
    }
}
