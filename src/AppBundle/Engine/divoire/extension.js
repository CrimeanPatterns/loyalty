var plugin = {

    hosts: {
        'vre.frequentflyer.aero': true,
        'ifsvre.frequentflyer.aero': true,
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp';
    },

    start: function (params) {
        browserAPI.log('start');
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log('waiting... ' + counter);

            if ($('#Content > div').text().includes('Error Message : Login expired')) {
                browserAPI.log('catched error "Login expired", redirecting to login form');
                clearInterval(start);
                provider.setNextStep('start', () => { document.location.href = plugin.getStartingUrl(params) });
                return;
            }

            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
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

    isLoggedIn: function (params) {
        browserAPI.log('isLoggedIn');

        if ($('#form1 input').length) {
            browserAPI.log('not LoggedIn');
            return false;
        }

        if ($('a[href *= "code=Logout"]').length) {
            browserAPI.log('LoggedIn');
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        let numberAndLevel = $('.LoginDetails').text();
        browserAPI.log('number: ' + numberAndLevel);
        return (typeof (account.properties) != 'undefined'
                && typeof (account.properties.CardNumber) != 'undefined'
                && account.properties.CardNumber !== ''
                && numberAndLevel.includes(account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://vre.frequentflyer.aero/cranelogin?code=Logout&lang=en';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log('login');

        if (
            typeof (params.account.itineraryAutologin) == 'boolean'
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = 'https://www.aircotedivoire.com/';
            return;
        }

        let form = $('#form1');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log('submitting saved credentials');
        form.find('input#txtUser').val(params.account.login);
        form.find('input#txtPass').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('input#btnSubmit').get(0).click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        let message = util.filter($('#errorPanelDiv:visible').text());

        if (message.length) {
            browserAPI.log("[Error]: " + message);

            if (message.includes('INVALID_LOGIN')) {
                provider.setError([message, util.errorCodes.invalidPassword], true);
                return;
            }

            provider.complete();
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');

        if (typeof (params.account.itineraryAutologin) === 'boolean' && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://vre.frequentflyer.aero/trips';
            });
            return;
        }

        if (params.autologin) {
            provider.complete();
            return;
        }

        provider.setNextStep('parse', () => {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        let counter = 0;
        let toItineraries = setInterval(function () {
            browserAPI.log('waiting... ' + counter);
            let link = $('a[href *= "' + params.account.properties.confirmationNumber + '"]');
            browserAPI.log('link ' + link);

            if (link.length) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
                return;
            }// if (link)

            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (counter > 20)

            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log('getConfNoItinerary');
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form#findReservationForm',
            success: function () {
                let form = $('form#findReservationForm');
                form.find('input[name *= "ConfirmationNumber"]').val(properties.ConfNo);
                form.find('input[name *= "LastName"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function () {
                    $('input[name = "btnSubmit"]').get(0).click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    },

    parse: function (params) {
        browserAPI.log('parse');
        let parsed = {};

        // Name
        const name = util.filter($('.LoginName').text().split(' ').slice(1).join(' ')); // cutting word MONSIEUR in the beginning
        if (name !== null && name.length)
            parsed.Name = util.beautifulName(name);
        else browserAPI.log('Name not found');

        const numberAndLevel = $('.LoginDetails').text();
        // Card Number
        const number = util.findRegExp(numberAndLevel, /Card Number (\d+)/);
        if (number !== null && !isNaN(number))
            parsed.CardNumber = number;
        else browserAPI.log('Card Number not found');

        // Tier
        const status = util.findRegExp(numberAndLevel, /Tier ([\w\s]+)/);
        if (status !== null && status.length)
            parsed.Status = status;
        else browserAPI.log('Tier not found');

        // Balance - Award Miles
        const balance = util.findRegExp($('.LoginAwd').text(), /(\d+)/);
        if (balance !== null && !isNaN(balance))
            parsed.Balance = balance;
        else browserAPI.log('Balance not found');

        params.data = parsed;
        provider.saveTemp(params.data);

        provider.setNextStep('parse2', () => {
            document.location.href = 'https://ifsvre.frequentflyer.aero/StandardWebSite/StatusMilesToExpire.jsp?activeLanguage=EN&amp;wmode=transparent';
        });
    },

    parse2: function (params) {
        browserAPI.log('parse2');

        // Total Tier Miles
        const totalTierMiles = $('.MainLabelHalf:contains("Total Tier Miles") + div').text();
        if (!isNaN(totalTierMiles))
            params.data.TotalTierMiles = totalTierMiles;
        else browserAPI.log('Total Tier Miles not found');

        // Tier Count
        const tierCount = $('.MainLabelHalf:contains("Tier Count") + div').text();
        if (!isNaN(tierCount))
            params.data.TierCount = tierCount;
        else browserAPI.log('Tier Count not found');

        const expiration = $('tr:nth-child(2) td');
        if (expiration.length === 2) {
            // Expiration Date
            const dateParts = expiration.get(0).textContent.split('/');
            const date = Date.UTC(dateParts[2], dateParts[1] - 1, dateParts[0]);
            const unixtime = date / 1000;
            // Expiring Balance
            const expBalance = expiration.get(1).textContent.replace(',', '');
            if (!isNaN(unixtime) && !isNaN(expBalance)) {
                params.data.AccountExpirationDate = unixtime;
                params.data.ExpiringBalance = expBalance;
            }
            else browserAPI.log('Expirations not parsed');
        }
        else browserAPI.log('Expirations not found');

        params.account.properties = params.data;
        provider.saveProperties(params.account.properties);

        provider.complete();
    },
};