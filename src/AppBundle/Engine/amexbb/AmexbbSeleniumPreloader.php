<?php

namespace AwardWallet\Engine\amexbb;

class AmexbbSeleniumPreloader
{
    /**
     * we will load all cookies with selenium, fill login form
     * and click login button
     * then catch AJAX login request, and send it with curl browser.
     *
     * because there are some js-generated time-linked codes in the request
     */
    public static function loginWithSeleniumPreload(\TAccountCheckerAmexbb $original): bool
    {
        $checker2 = self::createSelenium($original);

        try {
            $checker2->http->GetURL("https://secure.bluebird.com/login");
            $loginInput = $checker2->waitForElement(\WebDriverBy::id('bb-username'), 30);

            if ($loginInput === null) {
                return false;
            }
            $loginInput->sendKeys($original->AccountFields['Login']);
            $passInput = $checker2->waitForElement(\WebDriverBy::id('bb-password'), 1);
            $passInput->sendKeys($original->AccountFields['Pass']);

            $checker2->driver->executeScript( /** @lang JavaScript */ "
            var lastAjaxRequest = null;
            (function() {
                oldOpen = XMLHttpRequest.prototype.open;
                XMLHttpRequest.prototype.open = function() { 
                    console.log('open', arguments); 
                    this.curRequest = {'method': arguments[0], 'url': arguments[1], 'headers': {}};
                    return oldOpen.apply(this, arguments); 
                };

                oldSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader; 
                XMLHttpRequest.prototype.setRequestHeader = function() {
                  console.log('setRequestHeader', arguments);
                  this.curRequest.headers[arguments[0]] = arguments[1];
                  return oldSetRequestHeader.apply(this, arguments);
                };

                oldSend = XMLHttpRequest.prototype.send;
                XMLHttpRequest.prototype.send = function() {
                  console.log('send', arguments);
                  this.curRequest.data = arguments[0];
                  lastAjaxRequest = this.curRequest;
                  
                  if (lastAjaxRequest.url === 'https://ui.bluebird.com/api/login') {
                    console.log('stop');
                    var pre = document.createElement('pre');
                    pre.id = 'last-ajax-request';
                    pre.style = 'position: absolute; top: 0; left: 0; width: 300px;height: 300px; z-index: 100;';
                    pre.innerText = JSON.stringify(lastAjaxRequest);
                    document.body.append(pre);
                    return;
                  }
                  
                  return oldSend.apply(this, arguments);
                };
            })();
            ");

            $button = $checker2->waitForElement(\WebDriverBy::id('bb-submit'), 1);
            $button->click();

            $element = $checker2->waitForElement(\WebDriverBy::xpath("//pre[@id = 'last-ajax-request']"), 10);

            if ($element === null) {
                return false;
            }
            $request = json_decode($element->getText(), true);
            $cookies = $checker2->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $original->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $checker2->http->cleanup();
        }

        $original->http->RetryCount = 0;
        $original->http->PostURL("https://ui.bluebird.com/api/login", $request['data'], $request['headers']);
        $original->http->RetryCount = 2;

        return true;
    }

    private static function createSelenium(\TAccountCheckerAmexbb $original): \TAccountCheckerAmexbb
    {
        $checker2 = clone $original;
        $original->http->brotherBrowser($checker2->http);
        $original->logger->notice("Running Selenium...");
        $checker2->UseSelenium();
//        $checker2->http->SetProxy($original->http->GetProxy());
        $checker2->useChromium();
        $checker2->http->saveScreenshots = true;
        $checker2->http->start();
        $checker2->Start();

        return $checker2;
    }
}
