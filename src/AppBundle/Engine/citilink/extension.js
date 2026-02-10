var plugin = {

    hosts: {'www.citilink.ru': true},
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

    months: {
        "ЯНВАРЯ"  : 1,
        "ФЕВРАЛЯ" : 2,
        "МАРТА"    : 3,
        "АПРЕЛЯ"  : 4,
        "МАЯ"     : 5,
        "ИЮНЯ"    : 6,
        "ИЮЛЯ"    : 7,
        "АВГУСТА"  : 8,
        "СЕНТЯБРЯ": 9,
        "ОКТЯБРЯ" : 10,
        "НОЯБРЯ"  : 11,
        "ДЕКАБРЯ" : 12
    },

    getStartingUrl: function (params) {
        return 'https://www.citilink.ru/profile/club/';
    },

    getFocusTab: function(account, params){
        return true;
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
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

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[action *= "auth/login"]').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a[href *= "exit"]').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('b:contains("Карта №")').text(), /№\s*([^<\,]+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.CardNumber) !== 'undefined')
            && (account.properties.CardNumber !== '')
            && (number === account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a[href *= "exit"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action *= "auth/login"]');
        if (form.length > 0) {
            browserAPI.log("Submitting credentials");
            form.find('input[name = "login"]').val(params.account.login).focus().change().blur();
            form.find('input[name = "pass"]').val(params.account.password).focus().change().blur();
            provider.setNextStep('checkLoginErrors', function () {
                var signIn = form.find('#formSubmit');
                setTimeout(function () {
                    if ($('div.__input-captcha:visible').length === 0) {
                        signIn.removeAttr('disabled');
                        signIn.get(0).click();
                    }
                    setTimeout(function () {
                        provider.reCaptchaMessage();
                        browserAPI.log("waiting...");
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            if (counter > 120) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            counter++;
                        }, 500);
                    }, 3000)
                }, 1000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.error');
        if (errors.length > 0) {
            if (/Вы неправильно ввели код с картинки/.test(errors.text()))
                provider.setError([errors.text(), util.errorCodes.providerError], true);
            else
                provider.setError(errors.text(), true);
        }
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }
        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log('Current URL: ' + document.location.href);
        if (document.location.href !== 'https://www.citilink.ru/profile/club/') {
            provider.setNextStep('parse', function () {
                document.location.href = 'https://www.citilink.ru/profile/club/';
            });
        }
        else
            plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("Retrieving balance");
        browserAPI.log('Current URL: ' + document.location.href);
        provider.updateAccountMessage();

        var data = {};
        // Balance - Мои бонусы
        let balance = $('p:contains("Мои бонусы:")');
        if (balance.length > 0) {
            balance = util.findRegExp(balance.text(), /Мои бонусы:\s*([^<]+)/);
            browserAPI.log("Balance: " + balance);
            data.Balance = util.filter(balance);
        }
        else {
            browserAPI.log("Balance is not found");
            balance = $('.UserMenu__menu-link span:contains("Бонусы"):visible + span');
            if (balance.length > 0) {
                balance = util.filter(balance.text());
                browserAPI.log("Balance: " + balance);
                data.Balance = balance;
            }
            else
                browserAPI.log("Balance (v.2) not found");
        }
        // Карта №
        var number = $('b:contains("Карта №")');
        if (number.length > 0) {
            number = util.findRegExp(number.text(), /№\s*([^<\,]+)/);
            browserAPI.log("Карта №: " + number);
            data.CardNumber = number;
        }
        else
            browserAPI.log("CardNumber not found");
        // Name
        var name = $('div.user_name');
        if (name.length > 0) {
            name = util.beautifulName(util.filter(name.text()));
            browserAPI.log("Name: " + name);
            data.Name = name;
        }
        else
            browserAPI.log("Name is not found");
        // Status
        var status = $('td:has(label:contains("Текущий статус:")) + td');
        if (status.length > 0) {
            data.Status = util.filter(status.text());
            browserAPI.log("Status: " + data.Status);
        }
        else
            browserAPI.log("Status not found");

        // Exp Date
        var row = $('table.club-card-will-debited:has(th:contains("Дата списания")) tr:has(td)');
        if (row.length > 0) {
            var d = util.filter(row.children('td:eq(0)').text());
            browserAPI.log("Date: " + d);
            var month = util.findRegExp( d, /^\d+\s+([а-я]+)/i);
            var day = util.findRegExp( d, /^(\d+)/i);
            var year = util.findRegExp( d, /(\d{4})$/i);
            if (!year)
                year = new Date().getFullYear();
            // Expiring Balance
            data.ExpiringBalance = util.filter(row.children('td:eq(1)').text());
            browserAPI.log("Exp date: " + plugin.months[month.toUpperCase()] + '/' + day + '/' + year + ' - ' + data.ExpiringBalance);
            if ((typeof(plugin.months[month.toUpperCase()]) !== 'undefined')) {
                var date = new Date(plugin.months[month.toUpperCase()] + '/' + day + '/' + year + ' UTC');
                var unixtime = date / 1000;
                if (!isNaN(date) && !isNaN(unixtime)) {
                    browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime);
                    data.AccountExpirationDate = unixtime;
                }// if (date !== 'NaN')
            }// if ((typeof(plugin.months[month.toUpperCase()]) !== 'undefined'))
        }// if (row.length > 0)
        else
            browserAPI.log("Exp Date is not found");

        params.account.properties = data;
        console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    }
};