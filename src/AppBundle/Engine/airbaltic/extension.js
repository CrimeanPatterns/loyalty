var plugin = {
    hideOnStart: true,
    // clearCache: true,
    // keepTabOpen: true,//todo
    hosts: {
        'www.pinsforme.com'  : true,
        'spend.pinsforme.com': true,
        'www.pins.co'        : true
    },

    getStartingUrl: function (params) {
        return 'https://www.pins.co/lv-en/my-account';
    },

    getFocusTab: function (account, params) {
        return true;
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
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
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
                provider.logBody("lastPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[action *= "login"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('.i-mobile-modal__customer-name').text();
        browserAPI.log("name: " + name);
        return typeof(account.properties) != 'undefined'
            && account.properties.Name
            && account.properties.Name !== ''
            && name
            && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.pins.co/lv-en/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[action *= "login"]:visible');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input[name = "_username"]').val(params.account.login);
        form.find('input[name = "_password"]').val(params.account.password);
        provider.setNextStep('checkLoginCaptcha', function () {
            form.find('button:contains("Sign in")').get(0).click();
        });
    },

    /*login2: function (params) {
        browserAPI.log("login");
        // open login form
        $('a.login-btn:visible').get(0).click();

        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('form#inline_login_form');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                var identity = form.find('input[name = "identity"]');
                identity.focus();
                identity.val(params.account.login);

                setTimeout(function () {
                    var password = form.find('input[name = "password"]');
                    password.focus();
                    password.val(params.account.password);
                    form.find('input[name = "remember-pass"]').click();

                    setTimeout(function () {
                        provider.setNextStep('checkLoginErrors', function () {
                            setTimeout(function() {
                                $('.login-recaptcha-container').show();
                                var $captchaFrame = form.find('iframe[src*="/recaptcha/api2/anchor"]');
                                if ($captchaFrame.length) {
                                    var captcha = util.findRegExp( $captchaFrame.attr('src'), /k=([^&]+)/i);
                                    if (captcha && captcha.length > 0) {
                                        browserAPI.log("waiting...");
                                        if (provider.isMobile) {
                                            provider.command('show', function () {
                                                provider.reCaptchaMessage();
                                                form.bind('submit', function (event) {
                                                    if (params.autologin) {
                                                        provider.setNextStep('checkLoginErrors', function () {
                                                            browserAPI.log("captcha entered by user");
                                                            setTimeout(function () {
                                                                if ($('div.errors_wrap:visible').length > 0)
                                                                    plugin.checkLoginErrors(params);
                                                            }, 3000);
                                                        });
                                                    }
                                                    else {
                                                        provider.command('hide', function () {
                                                            provider.setNextStep('checkLoginErrors', function () {
                                                                browserAPI.log("captcha entered by user");
                                                                setTimeout(function () {
                                                                    if ($('div.errors_wrap:visible').length > 0)
                                                                        plugin.checkLoginErrors(params);
                                                                }, 3000);
                                                            });
                                                        });
                                                    }
                                                });
                                            });
                                        } else {
                                            provider.reCaptchaMessage();
                                            var counter = 0;
                                            var login = setInterval(function () {
                                                browserAPI.log("waiting captcha... " + counter);
                                                if($('#google_recaptcha_inline_login_response').val().length > 1) {
                                                    browserAPI.log("Captcha is valid, submit...");
                                                    clearInterval(login);
                                                    setTimeout(function() {
                                                        $('input[name = "login"]').click();
                                                        setTimeout(function () {
                                                            if ($('div.errors_wrap:visible').length > 0)
                                                                plugin.checkLoginErrors(params);
                                                        }, 3000);
                                                    }, 1000);
                                                    return;
                                                }
                                                if (counter > 120) {
                                                    clearInterval(login);
                                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                                    return;
                                                }
                                                if ($('div.errors_wrap:visible').length > 0) {
                                                    clearInterval(login);
                                                    plugin.checkLoginErrors(params);
                                                }
                                                counter++;
                                            }, 1000);
                                        }
                                    } else {
                                        browserAPI.log("captcha key not found");
                                        $('input[name = "login"]').trigger('click');
                                    }
                                } else {
                                    browserAPI.log("captcha frame is not found");
                                    $('input[name = "login"]').trigger('click');
                                    setTimeout(function () {
                                        if ($('div.errors_wrap:visible').length > 0)
                                            plugin.checkLoginErrors(params);
                                    }, 3000);
                                }
                            }, 1000)
                        });
                    }, 2000);
                }, 500);


            }
            if (counter > 80) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },*/

    checkLoginCaptcha: function (params) {
        var form = $('form[name="sso_api_recaptcha"]');
        var captchaFrame = form.find('iframe[src*="/recaptcha/api2/anchor"]');
        if (form.length && captchaFrame.length) {
            var captcha = util.findRegExp(captchaFrame.attr('src'), /k=([^&]+)/i);
            if (captcha && captcha.length > 0) {
                provider.setNextStep('checkLoginErrors', function () {
                    $('input[name = "submit"]').click();
                    setTimeout(function () {
                        provider.reCaptchaMessage();
                        browserAPI.log("waiting...");
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting captcha... " + counter);
                            if ($('#g-recaptcha-response').val().length > 1) {
                                browserAPI.log("Captcha is valid, submit...");
                                clearInterval(login);
                                setTimeout(function () {
                                    $('input[name = "submit"]').click();
                                }, 1000);
                                return;
                            }
                            if (counter > 160) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                                return;
                            }
                            counter++;
                        }, 1000);
                    }, 3000);
                });
            } else {
                browserAPI.log("captcha key not found");
                $('input[name = "submit"]').click();
            }
        } else {
            plugin.checkLoginErrors(params);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.i-flash-message > p:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text(), true);
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.logBody("loginCompletePage");
        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        if (params.autologin) {
            provider.complete();
            return;
        }
        var myAccountUrl = 'https://www.pinsforme.com/en/my-account/account-statement';
        if (document.location.href != myAccountUrl) {
            provider.setNextStep('waitingAccountLoading', function () {
                setTimeout(function() {
                    document.location.href = myAccountUrl;
                }, 2000);
            });
        }
        else
            plugin.waitingAccountLoading(params);
    },

    waitingAccountLoading: function (params) {
        browserAPI.log("waitingAccountLoading");
        var counter = 0;
        var waitingAccountLoading = setInterval(function () {
            browserAPI.log("waiting account loading... " + counter);
            var balance = $('dt:contains("card number") + dd:eq(0)');
            if ((balance.length > 0 && balance.text() != '') || counter > 20) {
                clearInterval(waitingAccountLoading);
                plugin.parse(params);
            }
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parse");
        browserAPI.log('Current URL: ' + document.location.href);
        provider.updateAccountMessage();

        var data = {};
        // My PINS card number
        var number = $('dt:contains("card number") + dd:eq(0)');
        if (number.length > 0) {
            number = number.text();
            browserAPI.log("My PINS card number: " + number);
            data.Number = number;
        } else
            browserAPI.log(">>> My PINS card number not found");
        // Balance - My PINS
        var balance = $('span.user-balance > span:eq(0)');
        if (balance.length > 0 && balance.text() != '') {
            browserAPI.log("Balance: " + balance.text());
            data.Balance = util.trim(balance.text());
        }
        else
            browserAPI.log(">>> Balance not found");

        // Expiration Date
        var exp = $('div#balance-expire > ul > li');
        browserAPI.log("Total " + exp.length + " exp nodes found");
        for (var i = 0; i < exp.length; i++) {
            var points = util.filter(exp.eq(i).find('b').text());
            var month = util.findRegExp(exp.eq(i).text(), /in\s+([^<]+)/i);

            var date = '01 ' + month + ' ' + new Date().getFullYear();
            date = new Date(date + ' UTC');
            browserAPI.log("Date " + date + " / points " + points);
            if (points > 0) {
                var unixtime =  date / 1000;
                if (unixtime != 'NaN') {
                    browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime );
                    data.AccountExpirationDate = unixtime;
                    // PINS to Expire
                    data.PointsToExpire = points;
                    browserAPI.log("PointsToExpire: " + data.PointsToExpire);
                }// if (date != 'NaN')
                break;
            }// if ($points > 0)
        }// for (var i = 0; i < exp.length; i++)

        // Expiration Date  // refs #8861
        if (exp.length == 3 && typeof (data.PointsToExpire) == 'undefined') {
            browserAPI.log(">>> Loading history...");
            balance = balance.text();
            var from_date = plugin.getDate(4);
            var to_date = plugin.getDate();
            $.ajax({
                url: "https://www.pinsforme.com/en/my-account/account-statement?from_date=" + from_date + "&to_date=" + to_date + "&type=collected",
                async: false,
                success: function (response) {
                    browserAPI.log("parse History");
                    response = $(response);

                    var nodes = $('table[id = "all-points"]', response).find('tr:not(:has(th))');
                    browserAPI.log("Total " + nodes.length + " nodes were found");
                    var pointsEarned = [];
                    for (var i = 0; i < nodes.length; i++) {
                        var points = util.filter(nodes.eq(i).find('td[class *= "points"]').text());
                        var date = util.filter(nodes.eq(i).find('td[class *= "date"]').text());
                        browserAPI.log("date " + date + " / points: " + points);
                        if (date && points && points > 0) {
                            var transaction = {
                                'date': date,
                                'points': points
                            };
                            pointsEarned.push(transaction);
                            balance = balance - transaction['points'];
                            browserAPI.log("Date " + transaction['date'] + " / Balance: " + balance);
                            if (balance <= 0) {
                                browserAPI.log("Date " + transaction['date']);
                                // Earning Date     // refs #4936
                                data.EarningDate = transaction['date'];
                                browserAPI.log("EarningDate: " + data.EarningDate);
                                // Expiration Date
                                if ((typeof(transaction) != 'undefined') && (transaction['date'] != '')) {
                                    var d = new Date(transaction['date'] + ' UTC');
                                    // ExpirationDate = lastActivity" + "3 year"
                                    d.setFullYear(d.getFullYear() + 3);
                                    var unixtime =  d / 1000;
                                    if (unixtime != 'NaN') {
                                        browserAPI.log("ExpirationDate = lastActivity + 3 year");
                                        browserAPI.log("Date: " + d + " Unixtime: " + unixtime );
                                        data.AccountExpirationDate = unixtime;
                                        // Points to Expire
                                        balance = balance + (transaction['points'] * 1);
                                        pointsEarned.pop();
                                        pointsEarned.reverse();
                                        pointsEarned.forEach(function (element, index) {
                                            browserAPI.log('#' + index + ' : ' + element.date + " / " + element.points);
                                            browserAPI.log("> Balance: " + balance);
                                            if (typeof (element.date) != 'undefined' && element.date == transaction['date'])
                                                balance = balance + (element.points * 1);
                                        });
                                        data.PointsToExpire = balance;
                                        browserAPI.log("PointsToExpire: " + data.PointsToExpire);
                                        break;
                                    }// if (unixtime != 'NaN')
                                }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
                            }// if (balance <= 0)
                        }// if (date && points && points > 0)
                    }// for (var i = 0; i < nodes.length; i++)
                }// success: function (response)
            });// $.ajax({
        }// if (exp.length == 3 && typeof (data.PointsToExpire) == 'undefined')

        params.data.properties = data;
        // save data
        // console.log(params.data.properties);//todo
        provider.saveTemp(params.data);

        provider.setNextStep('parseStatus', function () {
            document.location.href = 'https://www.pinsforme.com/en/my-account/status-level/airbaltic';
        });
    },

    getDate: function(offset) {
        browserAPI.log("getDate");
        var date = new Date();
        if (typeof (offset) != 'undefined')
            date.setFullYear(date.getFullYear() - offset);
        var result = date.getFullYear() + "-";
        if (/^\d$/.test(date.getMonth() + 1))
            result = result + '0' + (date.getMonth() + 1) + "-";
        else
            result = result + (date.getMonth() + 1) + "-";
        if (/^\d$/.test(date.getDate()))
            result = result + '0' + date.getDate();
        else
            result = result + date.getDate();

        browserAPI.log(">>> Date: " + result);

        return result;
    },

    parseStatus: function (params) {
        // Status
        var level = $('div:contains("Your status") > span');
        if (level.length > 0) {
            params.data.properties.Status = util.trim(level.text());
            browserAPI.log("Status: " + params.data.properties.Status);
        } else
            browserAPI.log(">>> Status not found");
        // Status PINS
        var statusPoints = $('p:contains("Status PINS")').prev('span:eq(0)');
        if (statusPoints.length > 0) {
            params.data.properties.StatusPoints = statusPoints.text();
            browserAPI.log("Status PINS: " + params.data.properties.StatusPoints);
        } else
            browserAPI.log(">>> Status PINS not found");
        // Last year's flights
        var lastYearsFlights = $('p:contains("Last year\'s flights")').prev('span:eq(0)');
        if (lastYearsFlights.length > 0) {
            params.data.properties.LastYearsFlights = lastYearsFlights.text();
            browserAPI.log("Last year's flights: " + params.data.properties.LastYearsFlights);
        } else
            browserAPI.log(">>> Last year's flights not found");

        provider.setNextStep('parseName', function () {
            document.location.href = 'https://www.pinsforme.com/en/my-account/profile-information';
        });
    },

    parseName: function (params) {
        browserAPI.log("parseName");
        // Name
        var name = util.trim($('input[name *= "first_name"]').attr('value')
                + " " + $('input[name *= "last_name"]').attr('value'));
        if (name && name.length > 0) {
            name = util.beautifulName(name);
            browserAPI.log("Name: " + name);
            params.data.properties.Name = name;
        } else
            browserAPI.log(">>> Name not found");

        params.account.properties = params.data.properties;
        // console.log(params.account.properties);//todo
        provider.saveProperties(params.account.properties);

        provider.complete();
    }

};
