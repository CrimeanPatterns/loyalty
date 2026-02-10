var plugin = {

    hosts: {'www.avios.com': true},

    getStartingUrl: function (params) {
        return 'https://www.avios.com/gb/en_gb/my-account/log-into-avios';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            browserAPI.log("waiting... " + isLoggedIn);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[name = loginForm]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.filter($('li.account-number > p').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('LoadLoginForm', function () {
            document.location.href = 'https://www.avios.com/my-account/logout';
        });
    },

    LoadLoginForm: function(params){
        browserAPI.log("LoadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = loginForm]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "j_username"]').val(params.account.login);
            form.find('input[name = "j_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input.btn-login').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.error01');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(errors.text());
        else
            plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        if (params.autologin || provider.isMobile) {
            provider.complete();
            return;
        }
        if (document.location.href != 'https://www.avios.com/gb/en_gb/my-account/your-avios-account') {
            provider.setNextStep('parse', function () {
                var myAcc = $('a.section-home[href *= "your-avios-account"]');
                if (myAcc.length > 0)
                    myAcc.get(0).click();
                else
                    document.location.href = 'https://www.avios.com/gb/en_gb/my-account/your-avios-account';
            });
        }// if (document.location.href != 'https://www.avios.com/gb/en_gb/my-account/your-avios-account')
        else
            plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("Retrieving balance");
        var data = {};
        // Balance - Avios
        var balance = util.findRegExp( $('p.display').text(), /([\d\.\,]+)/i);
        if (balance.length > 0) {
            browserAPI.log("Balance: " + balance );
            data.Balance = util.trim(balance);
        }else
            browserAPI.log("Balance is not found");
        // Account Number
        var Number = $('h3:contains("Account number")').siblings('p:eq(0)').text();
        if (Number.length > 0) {
            browserAPI.log("Account Number: " + util.trim(Number) );
            data.AccountNumber = util.trim(Number);
        }else
            browserAPI.log("Account Number is not found");
        // Name
        var name = util.findRegExp( $('p:contains("Hello,")').text(), /Hello,\s*(.+)/i);
        if (name.length > 0) {
            name = util.beautifulName( name );
            browserAPI.log("Name: " + name );
            data.Name = name;
        }else
            browserAPI.log("Name is not found");
        // Vouchers
        var vouchers = util.findRegExp( $('p:contains("You have")').text(), /You have\s*(\d+)/i);
        if (!vouchers)
            vouchers = $('span:contains("no vouchers")').text();
        if (vouchers) {
            browserAPI.log("Vouchers: " + vouchers);
            data.Vouchers = vouchers;
        } else
            browserAPI.log("Vouchers are not found");

        // Expiration Date   // refs #4309
        // Last Activity
        var lastActivity = $(':contains("Your recent transactions")').siblings('tbody').find('tr > td:eq(0)');
        if (lastActivity.length > 0) {
            lastActivity = lastActivity.text();
            browserAPI.log("Last Activity: " + lastActivity );
            if ((typeof(lastActivity) != 'undefined') && (lastActivity != '')) {
                data.LastActivity = lastActivity;
                var expiration = util.ModifyDateFormat(lastActivity);
                if (expiration != null) {
                    var date = new Date(expiration + ' UTC');
                    // ExpirationDate = lastActivity" + "3 year"
                    date.setFullYear(date.getFullYear() + 3);
                    browserAPI.log("Date: " + date );
                    var unixtime =  date / 1000;
                    if (date != 'NaN') {
                        browserAPI.log("ExpirationDate = lastActivity + 3 year");
                        browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime) );
                        data.AccountExpirationDate = unixtime;
                    }// if (date != 'NaN')
                }// if (expiration != null)
            }// if ((typeof(lastActivity) != 'undefined') && (lastActivity != ''))
        }else
            browserAPI.log("Last Activity is not found");

        // SubAccounts - Vouchers
        browserAPI.log("parseSubAccounts");
        var i = 0;
        var subAccounts = [];
        $(':contains("Your vouchers")').siblings('tbody').find('tr').each(function () {
            var displayName = util.trim($('td:eq(0)', $(this)).text());
            //browserAPI.log("DisplayName: " + displayName );
            var code = util.trim($('td:eq(5)', $(this)).text());
            //browserAPI.log("Code: " + code);

            var exp =  util.trim($('td:eq(2)', $(this)).text());
            exp = util.ModifyDateFormat(exp);
            exp = new Date(exp + ' UTC');
            var unixtime =  exp / 1000;
            var balance = util.trim($('td:eq(3)', $(this)).text());
            //browserAPI.log("Balance: " + balance);
            balance = util.findRegExp( balance, /([\d\.\,]+)/i);

            if (!isNaN(exp) && code) {
                //browserAPI.log("Expiration Date: " + exp + " Unixtime: " + unixtime );
                subAccounts.push({
                    "Code" : code,
                    "DisplayName" : displayName,
                    "Balance" : balance,
                    "ExpirationDate" : unixtime,
                    'ValidFrom' : util.trim($('td:eq(1)', $(this)).text()),
                    'Issuer' : util.trim($('td:eq(4)', $(this)).text())
                });
            } else if (code) {
                subAccounts.push({
                    "Code" : code,
                    "DisplayName" : displayName,
                    "Balance" : balance,
                    'ValidFrom': util.trim($('td:eq(1)', $(this)).text()),
                    'Issuer' : util.trim($('td:eq(4)', $(this)).text())
                });
            }
            i++;
            //console.log(subAccounts);
        });
        data.SubAccounts = subAccounts;

        params.data.properties = data;
        params.account.properties = params.data.properties;
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);

        provider.complete();
    }
}