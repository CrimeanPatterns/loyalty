var plugin = {
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML like Gecko) Chrome/68.0.3440.75 Safari/537.36',

    hosts: {
        'www.expedia.com': true,
        'www.expedia.co.uk': true,
        'www.expedia.be': true,
        'www.expedia.ca': true,
        'www.expedia.com.sg': true,
        'www.expedia.com.br': true,
        'www.expedia.co.jp': true,
        'www.expedia.com.au': true,
        'www.expedia.ch': true,
        'www.expedia.com.hk': true,
        'www.expedia.co.id': true,
        'www.expedia.es': true,
        'www.expedia.se':true,
        'www.expedia.com.my':true,
		'www.expedia.ie':true,
        'www.expedia.com.tw':true,
        'www.expedia.nl':true,
        'www.expedia.no':true,
        'www.expedia.co.in':true,
        'www.expedia.co.th':true
    },

    cashbackLinkMobile : false,
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params) {
        switch (params.account.login2) {
            case 'UK':
                return 'https://www.expedia.co.uk/login';
                break;
            case 'AU':
                return 'https://www.expedia.com.au/login';
                break;
            case 'BR':
                return 'https://www.expedia.com.br/login';
                break;
            case 'BE':
                return 'https://www.expedia.be/login';
                break;
            case 'CA':
                return 'https://www.expedia.ca/login';
                break;
            case 'SG':
                return 'https://www.expedia.com.sg/login';
                break;
            case 'JP':
                return 'https://www.expedia.co.jp/login';
                break;
            case 'CH':
                return 'https://www.expedia.ch/login';
                break;
            case 'HK':
                return 'https://www.expedia.com.hk/login';
                break;
            case 'ID':
                return 'https://www.expedia.co.id/login';
                break;
			case 'ES':
                return 'https://www.expedia.es/login';
                break;
			case 'SV':
                return 'https://www.expedia.se/login';
                break;
			case 'MS':
                return 'https://www.expedia.com.my/login';
                break;
			case 'IE':
                return 'https://www.expedia.ie/login';
                break;
            case 'TW':
                return 'https://www.expedia.com.tw/login';
                break;
            case 'NL':
                return 'https://www.expedia.nl/login';
                break;
            case 'NO':
                return 'https://www.expedia.no/login';
                break;
            case 'IN':
                return 'https://www.expedia.co.in/login';
                break;
            case 'TH':
                return 'https://www.expedia.co.th/login';
                break;
            default:// 'US'
                return 'https://www.expedia.com/login';
                break;
        }
    },

    getToItinerariesUrl: function(params) {
        browserAPI.log('getToItinerariesUrl');
        var startingUrl = plugin.getStartingUrl(params);
        var country = util.findRegExp(startingUrl, /expedia\.([.\w]+)/);
        if (!country)
            return;
        var url = 'https://www.expedia.' + country + '/trips';
        browserAPI.log('to itins url = ' + url);
        return url;
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
		plugin.start(params);
    },

    start: function(params) {
        browserAPI.log('start');
        provider.setNextStep('start2', function() {
            document.cookie = 'linfo=;';
            document.cookie = 'minfo=;';
			document.location.href = plugin.getStartingUrl(params);
        });
    },

    start2: function (params) {
        browserAPI.log("start2");
        // if (provider.isMobile) {
        //     provider.setNextStep('startMobile', function() {
        //         document.location.href = plugin.getStartingUrl(params);
        //     });
        // } else {
             plugin.doStart(params);
        // }
    },

    startMobile: function (params) {
        $(document).ready(function() {
            provider.setNextStep('doStart', function() {
                document.location.reload(true);
            });
        });
    },

    doStart: function(params) {
        browserAPI.log("doStart");
        let counter = 0;
        let start = setInterval(function() {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = $('div.heading-container h3, #header-menu-account_circle-description').text();
        browserAPI.log("name: " + name);
        return (typeof(account.properties) != 'undefined')
           && (typeof(account.properties.Name) != 'undefined')
           && (account.properties.Name !== '')
           && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1;
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        // USA, CA, UK, SG, CH, HK, ID, SV, NL
        if ($('form[name="loginEmailForm"]').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        // USA, CA, UK, SG, CH, HK, ID, SV
        if ($('a#header-account-menu-signed-in').length > 0
            // Australia, NL
            || $('a#account-signout').length > 0
            // BE
            || $('a[href*="/user/logout?"]:contains("Sign out")').length > 0
            // Mobile
            || $('#account-menu-icon:visible').length
        ) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    logout: function(params) {
        browserAPI.log("logout");
        var logout;
        switch (params.account.login2) {
            case 'AU':
                logout = 'https://www.expedia.com.au/user/logout';
                break;
            case 'BR':
                logout = 'https://www.expedia.com.br/user/logout?';
                break;
            case 'BE':
                logout = 'https://www.expedia.be/user/logout?';
                break;
            case 'CA':
                logout = 'https://www.expedia.ca/user/logout?';
                break;
            case 'SG':
                logout = 'https://www.expedia.com.sg/logout?';
                break;
            case 'JP':
                logout = 'https://www.expedia.co.jp/logout?';
                break;
            case 'UK':
                logout = 'https://www.expedia.co.uk/user/logout';
                break;
            case 'CH':
                logout = 'https://www.expedia.ch/user/logout?';
                break;
            case 'HK':
                logout = 'https://www.expedia.com.hk/user/logout?';
                break;
            case 'ID':
                logout = 'https://www.expedia.co.id/user/logout?';
                break;
            case 'SV':
                logout = 'https://www.expedia.se/user/logout?';
                break;
            case 'MS':
                logout = 'https://www.expedia.com.my/user/logout?';
                break;
			case 'IE':
                logout = 'https://www.expedia.ie/user/logout?';
                break;
            case 'TW':
                logout = 'https://www.expedia.com.tw/user/logout?';
                break;
            case 'NL':
                logout = 'https://www.expedia.nl/user/logout?';
                break;
            default://'US'
                logout = 'https://www.expedia.com/user/logout?';
                break;
        }// switch (account.login2)
        provider.setNextStep('loadLoginForm', function() {
            document.location.href = logout;
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[name = "loginEmailForm"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return
        }

        browserAPI.log("submitting saved credentials");
        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('input[id = \"loginFormEmailInput\"]', '" + params.account.login + "');\n"
        );

        form.find('#loginFormSubmitButton').get(0).click();
        util.waitFor({
            selector: '#passwordButton:visible',
            success: function(item){
                item.get(0).click();
                setTimeout(function () {
                    let form = $('form[name="enterPasswordForm"]:visible');
                    // reactjs
                    provider.eval(
                        "function triggerInput(selector, enteredValue) {\n" +
                        "      let input = document.querySelector(selector);\n" +
                        "      input.dispatchEvent(new Event('focus'));\n" +
                        "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                        "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                        "      nativeInputValueSetter.call(input, enteredValue);\n" +
                        "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                        "      input.dispatchEvent(inputEvent);\n" +
                        "}\n" +
                        "triggerInput('input[id = \"enterPasswordFormPasswordInput\" or od = \"loginFormPasswordInput\"]', '" + params.account.password + "');\n"
                    );
                    form.find('#enterPasswordFormSubmitButton, #loginFormSubmitButton').get(0).click();
                    provider.setNextStep('checkLoginErrors');
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 10000);
                }, 1000);
            },
            fail: function(){
                plugin.checkLoginErrors(params);
            },
            timeout: 10
        });
    },

    checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.uitk-field-message-error:visible, div.uitk-error-summary h3:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    toItineraries: function(params) {
        browserAPI.log('toItineraries');
        var confNo = params.account.properties.confirmationNumber;
        var link = $('a.trip-link[href *= "' + confNo + '"]');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function() {
                link.get(0).click();
                return;
            });
        } else {
            plugin.itLoginComplete(params);
        }
    },

	loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (
            typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = plugin.getToItinerariesUrl(params);
            });
            return;
        }

        provider.complete();
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
