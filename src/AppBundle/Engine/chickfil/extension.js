var plugin = {
    hideOnStart: true,
    hosts: {
        'chick-fil-a.com'          : true,
        'www.chick-fil-a.com'      : true,
        'login.my.chick-fil-a.com' : true,
        'manage.my.chick-fil-a.com': true,
        'order.chick-fil-a.com'    : true
    },
    // keepTabOpen: true, // todo
    alwaysSendLogs: true,//todo
    saveScreenshot: true,

    getStartingUrl: function (params) {
        return 'https://www.chick-fil-a.com/myprofile/points';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        let counter = 0;
        let start = setInterval(async function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (await plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.logBody("lastPage");

                let providerError = $('h1:contains("403 Forbidden"):visible, p:contains("It looks like we\'re experiencing some technical issues. We apologize for the inconvenience."):visible');
                if (providerError.length > 0) {
                    provider.setError([providerError.text(), util.errorCodes.providerError], true);
                    return;
                }

                let terms = $('div:contains("Updated Terms & Conditions"):visible');
                if (terms.length > 0) {
                    provider.setError(["Chick-fil-A One website is asking you to accept their new Terms and Conditions, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                    return;
                }

                if (
                    $('p:contains("net::ERR_TIMED_OUT"):visible, p:contains("net::ERR_UNKNOWN_URL_SCHEME"):visible').length > 0
                    && $('h2:contains("Webpage not available"):visible').length > 0
                    && $('span:contains("An error occured"):visible').length > 0
                ) {
                    provider.setError(util.errorMessages.providerErrorMessage, true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form#login-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "/Account/Logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: async function (account) {
        browserAPI.log("isSameAccount");
        let number;
        let counter = 0;
        do {
            if (counter === 5) break;
            browserAPI.log("account number is not loaded yet, waiting 1 sec");
            await new Promise(res => setTimeout(res, 1000));
            number = $('h5:contains("Membership #") + p, h5:contains("Membership #") + span').text();
            counter++;
        }
        while (number === '')
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.AccountNumber) !== 'undefined')
            && (account.properties.AccountNumber != '')
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.chick-fil-a.com/account/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form#login-form:visible');

        if (form.length === 0) {
            provider.logBody("loginLastPage");
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name="pf.username"]').val(params.account.login);
        form.find('input[name="pf.pass"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[name="pf.ok"]').get(0).click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.err-container > .err:visible');
        if (errors.length === 0) {
            errors = $('div.err:visible');
        }
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            browserAPI.log("[Error]: " + util.filter(errors.text()));
            if (
                errors.text().indexOf("We didn't recognize the username or password you entered. Please try again.") !== -1
                || errors.text().indexOf("We're having trouble logging you in. You may have entered an incorrect email address or password") !== -1
            ) {
                provider.setError(errors.text(), true);
                return;
            }
            // access blocked
            if (errors.text().indexOf("You entered an incorrect email address or password. You may reset your password. OR") !== -1) {
                provider.setError([errors.text(), util.errorCodes.providerError], true);
                return;
            }

            provider.complete();
            return;
        }

        let providerError = $('p:contains("We\'re having one of those days. Our site is experiencing some technical issues, but we\'re working hard to get it fixed. Check back soon."):visible, p:contains("It looks like we\'re experiencing some technical issues. We apologize for the inconvenience."):visible');
        if (providerError.length > 0) {
            provider.setError([providerError.text(), util.errorCodes.providerError], true);
            return;
        }

        let terms = $('div:contains("Updated Terms & Conditions"):visible');
        if (terms.length > 0) {
            provider.setError(["Chick-fil-A One website is asking you to accept their new Terms and Conditions, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.logBody("loginCompletePage");

        if (params.autologin) {
            provider.complete();
            return;
        }

        let counter = 0;
        let loadAccount = setInterval(function () {
            browserAPI.log("[loadAccount]: waiting... " + counter);
            // if the page completely loaded
            let balance = $('span:contains("Rewards balance:"):visible, h5:contains("Available Points") + h2:visible');
            if (counter > 7 && balance.length === 0) {
                balance = $('p:contains("Points last updated") + h1:visible');
            }
            if (balance.length > 0 && balance.text() !== '') {
                clearInterval(loadAccount);
                plugin.parse(params);
            }// if (balance.length > 0 && balance.text() !== '')

            if (
                $('div#titleSubText:contains("We\'ve made some updates to our Privacy Policy."):visible').length
                || $('div#titleSubText:contains("We\'ve made some updates to our Chick-fil-A Terms"):visible').length
            ) {
                clearInterval(loadAccount);
                provider.setError(["Chick-fil-A One website is asking you to accept their new Terms and Conditions, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                return;
            }

            let verification = $('div:contains("Please verify your device by clicking on Verify Device link sent to your email"):visible, div:contains("Please verify your device by clicking on the Verify Device link sent to"):visible');
            if (verification.length) {
                clearInterval(loadAccount);

                if (!provider.isMobile) {
                    provider.setError([verification.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.command('show', function () {
                    provider.showFader('Message from AwardWallet: In order to log in into this account please Verify this device and click the “Next” button. Once logged in, sit back and relax, we will do the rest.');/*review*/
                    provider.setNextStep('loadAccount', function () {
                        browserAPI.log("waiting answers...");
                        let counter = 0;
                        let waitingAnswers = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            let balance = $('span:contains("Rewards balance:"):visible, h5:contains("Available Points") + h2:visible');
                            if (counter > 7 && balance.length === 0) {
                                balance = $('p:contains("Points last updated") + h1:visible');
                            }

                            if (counter > 180) {
                                clearInterval(waitingAnswers);
                                provider.setError([verification.text(), util.errorCodes.providerError], true);
                                provider.setError(['Message from AwardWallet: In order to log in into this account please Verify this device and click the “Next” button. Once logged in, sit back and relax, we will do the rest.', util.errorCodes.providerError], true);
                                return;
                            }// if (counter > 180)
                            if (balance.length > 0 && balance.text() !== '') {
                                clearInterval(waitingAnswers);
                                provider.command('hide', function () {
                                });
                                plugin.parse(params);
                            }
                            counter++;
                        }, 500);
                    });
                });

                return;
            }

            if (counter > 20) {
                clearInterval(loadAccount);
                plugin.parse(params);
            }// if (counter > 10)
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parsePage", true);
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);

        var data = {};
        var balance = $('span:contains("Rewards balance:")');
        if (balance.length > 0) {
            data.Balance = util.findRegExp(balance.text(), /:\s*(.+)\s+pts/);
            browserAPI.log("Balance: " + data.Balance);
        } else {
            if (balance.length === 0) {
                browserAPI.log("Balance v.2");
                balance = $('p:contains("Points last updated") + h1, h5:contains("Available Points") + h2');
                if (balance.length > 0) {
                    data.Balance = balance.text();
                    browserAPI.log("Balance: " + data.Balance);
                }
            }
            else {
                browserAPI.log("Balance is not found");
            }
        }

        // Name
        var name = $('.cp-nav__details > h4');
        if (name.length > 0) {
            name = util.beautifulName(name.text());
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name is not found");

        // Status
        let status = $('div[class *= "member-tier"] > p:eq(1), h5.member-category:visible');
        if (status.length > 0) {
            data.Status = status.text();
            browserAPI.log("Status: " + data.Status);
        } else
            browserAPI.log("Status is not found");

        // Lifetime points earned
        let totalPointsEarned = $('h5:contains("Lifetime points earned") + p, h5:contains("Lifetime points earned") + span');
        if (totalPointsEarned.length > 0) {
            browserAPI.log("Lifetime points earned: " + totalPointsEarned.text());
            data.TotalPointsEarned = totalPointsEarned.text();
        } else
            browserAPI.log("Lifetime points earned is not found");

        // Points Next Level
        let pointsNextLevel = $('div.progress-bar-anim > p, span:contains("more points by the end of the year"):visible');
        if (pointsNextLevel.length > 0) {
            data.PointsNextLevel = util.findRegExp(pointsNextLevel.text(), /Earn\s*(\d+)/i);
            browserAPI.log("PointsNextLevel: " + data.PointsNextLevel);
        } else
            browserAPI.log("PointsNextLevel is not found");

        // Membership #
        let accountNumber = $('h5:contains("Membership #") + p, h5:contains("Membership #") + span');
        if (accountNumber.length > 0) {
            browserAPI.log("Membership #: " + accountNumber.text());
            data.AccountNumber = accountNumber.text();
        } else
            browserAPI.log("Membership # is not found");

        // Member Since
        var memberSince = $('h5:contains("Member Since")').next('p');
        if (memberSince.length > 0) {
            browserAPI.log("MemberSince: " + memberSince.text());
            data.MemberSince = memberSince.text();
        } else
            browserAPI.log("Member Since is not found");

        // Your Chick-fil-A One™ red status is valid through ...
        const statusExpiration = $('div[class *= "status-until"] > p:eq(1), span:contains("Status valid until"):visible');
        if (statusExpiration.length > 0) {
            data.StatusExpiration = util.findRegExp(statusExpiration.text().replace('Status valid until', ''), /([^\.]+)/);
            browserAPI.log("Status Expiration: " + data.StatusExpiration);
        } else
            browserAPI.log("Status Expiration is not found");


        // save data
        params.data.properties = data;
        //console.log(params.data.properties);
        provider.saveTemp(params.data);
        provider.setNextStep('parseRewardsPreload', function () {
            document.location.href = 'https://order.chick-fil-a.com/my-rewards';
        });
    },

    parseRewardsPreload: function (params) {
        browserAPI.log("parseRewards");

        setTimeout(function () { plugin.parseRewards(params) }, 5000)
    },

    parseRewards: function (params) {
        browserAPI.log("parseRewards");
        provider.logBody("parseRewardsPage", true);
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);
        let data = params.data.properties;
        let subAccounts = [];

        if ($('h5:contains("You currently do not have any rewards available"):visible').length === 0) {
            let rewards = $('#my-rewards-set').find('div.reward-card');
            browserAPI.log('Total ' + rewards.length + ' rewards were found');

            if (rewards.length === 0) {
                rewards = $('[role="list"]').find('li[data-cy = "Reward"]');
                browserAPI.log('Total ' + rewards.length + ' rewards v.2 were found');
            }

            if (rewards.length > 0) {
                rewards.each(function () {
                    let displayName = util.trim($(this).find('.reward-details > h5:eq(0), h4[data-cy="RewardName"]').text());
                    let expStr = util.findRegExp($(this).find('p:contains("Valid through"), div[aria-hidden="true"]:contains("Valid through")').text(), /Valid\s*through\s*(.+)/);
                    browserAPI.log(displayName + " / Exp date: " + expStr);
                    let exp = new Date(expStr + ' UTC');
                    if (exp) {
                        let unixtime = exp / 1000;
                        if (!isNaN(unixtime)) {
                            subAccounts.push({
                                'Code': 'chickfil' + displayName.replace(' ', '').replace(' ', '').replace('® ', '').replace('™', '') + unixtime,
                                'DisplayName': displayName,
                                'Balance': null,
                                'ExpirationDate': unixtime
                            });
                        }
                    }
                });
            } else
                browserAPI.log("Rewards Balance is not found");

        } else
            browserAPI.log('Rewards not found');

        // Save properties
        params.account.properties = data;
        params.account.properties.SubAccounts = subAccounts;
        params.account.properties.CombineSubAccounts = 'false';
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    }

};