<?php

namespace AwardWallet\Engine\aplus\Transfer;

class Transfer extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    public $timeout = 10;

    protected static $providersMap = [
        'aeromexico'   => 'FFP27',
        'airberlin'    => 'FFP7',
        'aeroplan'     => 'FFP13',
        'airchina'     => 'FFP14',
        'airfrance'    => 'FFP1',
        'klm'          => 'FFP1',
        'alitalia'     => 'FFP15',
        'aviancataca'  => 'FFP26',
        'british'      => 'FFP6',
        'asia'         => 'FFP17',
        'delta'        => 'FFP2',
        'skywards'     => 'FFP23',
        'etihad'       => 'FFP25',
        'finnair'      => 'FFP29',
        'iberia'       => 'FFP21',
        'jetairways'   => 'FFP22',
        'lufthansa'    => 'FFP9',
        'swissair'     => 'FFP9',
        'austrian'     => 'FFP9',
        'qantas'       => 'FFP3',
        'singaporeair' => 'FFP4',
        'tapportugal'  => 'FFP12',
        'thaiair'      => 'FFP5',
        'turkish'      => 'FFP28',
    ];

    private $targetProviderCode;

    private $targetAccountNumber;

    private $sourceRewardsQuantity;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->AccountFields['BrowserState'] = null;
        $this->keepCookies(false);
        $this->keepSession(false);
    }

    public function LoadLoginForm()
    {
        if (!isset(self::$providersMap[$this->TransferFields['targetProvider']])) {
            throw new \UserInputError('Unsupported target provider');
        }
        $this->http->GetURL('http://www.accorhotels.com/gb/usa/index.shtml');
        $this->driver->findElement(\WebDriverBy::xpath('//span[contains(@class, "pb-button") and contains(@class, "pb-button")]'))->click();
        $loginFrame = $this->waitForElement(\WebDriverBy::xpath('//div[contains(@class, "login")]/iframe'), $this->timeout);

        if (!$loginFrame) {
            $this->http->Log('Could not find login frame', LOG_LEVEL_ERROR);

            return false;
        }
        $this->driver->switchTo()->frame($loginFrame);
        $this->waitForElement(\WebDriverBy::id('login'), $this->timeout)->sendKeys($this->AccountFields['Login']);
        $this->waitForElement(\WebDriverBy::id('pwd'), $this->timeout)->sendKeys($this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->waitForElement(\WebDriverBy::id('login-submit'), $this->timeout)->click();

        if ($this->waitForElement(\WebDriverBy::className('countrySelectorTitle'), $this->timeout)) {
            // TODO: Add support for other countries
            $this->http->Log('Passing country choosing');
            //			$this->driver->findElement(\WebDriverBy::id('en_united-kingdom'))->click();
            $this->driver->executeScript('$("#en_united-kingdom")[0].click()');
        }

        if ($elem = $this->waitForElement(\WebDriverBy::className('pb-welcome'), $this->timeout)) {
            return true;
        } elseif ($elem = $this->waitForElement(\WebDriverBy::className('error'), $this->timeout)) {
            throw new \UserInputError($elem->getText());
        } // Is it always user input error?
        else {
            return false;
        }
    }

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->targetProviderCode = $targetProviderCode;
        $this->targetAccountNumber = $targetAccountNumber;
        $this->sourceRewardsQuantity = $numberOfMiles;
        $this->http->GetURL('https://s-leclub.accorhotels.com/transfer-airline-miles.action?lang=en&override=www.accorhotels.com');
        $value = self::$providersMap[$this->targetProviderCode];
        $select = new \WebDriverSelect($this->waitForElement(\WebDriverBy::id('list_reward'), $this->timeout));

        try {
            $select->selectByValue($value);
        } catch (\Exception $e) {
            $this->http->Log('Could not select target provider, seems that it is no longer supported or internal code has changed', LOG_LEVEL_ERROR);

            return false;
        }
        $this->waitForElement(\WebDriverBy::id('ffpCardNumber'), $this->timeout)->sendKeys($this->targetAccountNumber);

        // TODO: Set points amount

        $this->waitForElement(\WebDriverBy::id('submit_burnffp_link'), $this->timeout)->click();

        if ($elem = $this->waitForElement(\WebDriverBy::className('mess_error'), $this->timeout)) {
            throw new \ProviderError($elem->getText()); // Is it always provider error?
        } else {
            // TODO: More accurate success check
            $this->http->Log('Transfer succeeded');
            $this->saveResponse();

            return true;
        }

        return false;
    }
}
