var plugin = {

    hosts: {'www.basspro.com': true},
    hideOnStart: true,
    clearCache: true,
    // keepTabOpen: true,//todo
    mobileUserAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36",

    getStartingUrl: function (params) {
        return 'https://www.basspro.com/';
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                // The store has encountered a problem processing the last request. Try again later.
                // If the problem persists, contact your site administrator.
                let error = $('h1:contains("The store has encountered a problem processing the last request"):visible, h1:contains("Service Unavailable"):visible, h3:contains("Basspro.com is currently down for maintenance."):visible');

                if (error.length > 0) {
                    provider.setError([error.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            (!provider.isMobile && $('div:not([style = "display: none;"]):visible > #Header_GlobalLogin_signInQuickLink').length > 0)
            || (provider.isMobile && $('div:not([style = "display: none;"]) > #Header_GlobalLogin_signInQuickLink').length > 0)
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a#signInOutQuickLink, a.sign-out').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.eval("document.getElementById('monetate_lightbox').remove();");
        return null;
    },

    isSameAccount: function (account) {
		browserAPI.log("isSameAccount");
		return false;
    },

    login: function (params) {
        browserAPI.log("login");
		$('#Header_GlobalLogin_signInQuickLink').get(0).click();
		util.waitFor({
			selector: '#Header_GlobalLogin_GlobalLogon',
			success: function(){
				var form = $('#Header_GlobalLogin_GlobalLogon');
				if (form.length > 0) {
					browserAPI.log("submitting saved credentials");
					var $login = form.find('#Header_GlobalLogin_WC_AccountDisplay_FormInput_logonId_In_Logon_1');
					$login.val(params.account.login);
					util.sendEvent($login.get()[0], 'blur');
					var $pass = form.find('#Header_GlobalLogin_WC_AccountDisplay_FormInput_logonPassword_In_Logon_1');
					$pass.val(params.account.password);
					util.sendEvent($pass.get()[0], 'blur');
					provider.setNextStep('checkLoginErrors', function () {
						form.find('#Header_GlobalLogin_WC_AccountDisplay_links_2').get(0).click();
						util.waitFor({
							selector: '.errorLabel.active, .myaccount_error:contains("email"), #bp-alert-textId:contains("Please provide a valid email"):visible',
							success: function(){
								plugin.checkLoginErrors(params);
							},
							fail: function(){
							}
						});
					});
				}else {
                    provider.logBody("lastPageSuccess");
				    provider.setError(util.errorMessages.loginFormNotFound);
                }
			},
            fail: function () {
                provider.logBody("lastPageFail");
				provider.setError(util.errorMessages.loginFormNotFound);
			}
		});
    },
	
	logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $("a#signInOutQuickLink, a.sign-out").get()[0].click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('.errorLabel.active, .myaccount_error:contains("email"), #bp-alert-textId:contains("Please provide a valid email"):visible');
        if (errors.length === 0 && $('h1:contains("Account Locked"):visible').length) {
            provider.setError(["Account Locked", util.errorCodes.lockout], true);
        }
        if (errors.length === 0 && $('h2:contains("Your current password has expired."):visible').length) {
            provider.setError(["Your current password has expired. Please enter a new password.\t", util.errorCodes.providerError], true);
        }
        if (errors.length > 0) {
            provider.setError(errors.text(), true);
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        browserAPI.log('Current URL: ' + document.location.href);
        if (params.autologin) {
            browserAPI.log("Only autologin");
            provider.complete();
            return;
        }
        let data = {};
        // Name
        let name = $('span#welcome_header_firstName');
        if (name.length > 0) {
            name = util.beautifulName(name.text() + ' ' + $('div#lastName_initials').text());
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name not found");

        // CLUB Account -> Rewards Available
        let rewards = $('div#clubWalletClubPoints1 > span');
        if (rewards.length) {
            let subAccounts = [];
            subAccounts.push({
                "Code": 'bassproClubRewards',
                "DisplayName": "Club Rewards",
                "Balance": rewards.text()
            });
            data.SubAccounts = subAccounts;
        } else
            browserAPI.log("Rewards Available not found");

        let myAccount = $('div#section_list_rewards a:contains("Outdoor Rewards")');
        if (myAccount.length === 0 && $('div.myaccount_desc_title:contains("Welcome, ")').length > 0) {

            if (rewards.length) {
                browserAPI.log("set Balance N\A");
                data.Balance = 'null';
                params.account.properties = data;
                provider.saveProperties(params.account.properties);
                provider.complete();
                return;
            }

            provider.setError(['You are not a member of this loyalty program.', util.errorCodes.providerError]);
            return;
        }

        params.data.properties = data;
        provider.saveTemp(params.data);
        provider.setNextStep('loadAccount', function () {
            // myAccount.get(0).click();
            provider.eval("document.querySelector('a[href *= \"OutdoorRewards\"]').click()");
        });
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        browserAPI.log('Current URL: ' + document.location.href);
        provider.updateAccountMessage();
        // Balance - Your Avios
        let balance = $('#rewardsBalanceAmount');
        if (balance.length) {
            params.data.properties.Balance = util.trim(balance).text();
            browserAPI.log("Balance: " + params.data.properties.Balance);
        } else {
            browserAPI.log("Balance not found");

            if (typeof (params.data.properties.SubAccounts) != 'undefined' && params.data.properties.SubAccounts.length) {
                browserAPI.log("set Balance N\A");
                params.data.properties.Balance = 'null';
                params.account.properties = params.data.properties;
                provider.saveProperties(params.account.properties);
                provider.complete();
                return;
            }

            let myAccount = $('a#submitLinkRewardsAcctBtn:contains("Connect Outdoor Rewards"):visible');
            if (myAccount.length === 1) {
                provider.setError(['You are not a member of this loyalty program.', util.errorCodes.providerError]);
                return;
            }
        }
        // My Points
        let myPoints = $('#rewardsPointBalance');
        if (myPoints.length) {
            params.data.properties.MyPoints = util.trim(myPoints.text());
            browserAPI.log("MyPoints: " + params.data.properties.MyPoints);
        } else
            browserAPI.log("MyPoints not found");
        // Welcome, Name
        var string = $('#or-welcome').text();
        // Member ID
        browserAPI.log("string: " + string);
        var number = util.trim(util.findRegExp(string, /Member\s*ID:\s*(\w+)/));
        if (number.length > 0) {
            number = util.beautifulName(number);
            browserAPI.log("Number: " + number);
            params.data.properties.Number = number;
        } else
            browserAPI.log("Number not found");

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);

        provider.complete();
    }

};