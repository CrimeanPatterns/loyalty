var plugin = {

    //hideOnStart: true,
    clearCache: true,
     //keepTabOpen: true,//todo
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

	hosts: {
        '/\\w+\\.aeroflot\\.ru/': true,
        'www.aeroflot.ru': true,
        'gw.aeroflot.ru': true,
        '.aeroflot.ru': true
    },

	getStartingUrl: function(params) {
		return "https://www.aeroflot.ru/personal?_preferredLanguage=en";
	},

    getFocusTab: function(account, params){
        return true;
    },

	start: function(params) {
        browserAPI.log("start");
        browserAPI.log('Current URL -> ' + document.location.href);
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
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.logBody("lastPage");

                if ($('form[id = "form"], form.login__form:visible').length > 0) {
                    //alert(1);
                }

                let maintenance = $('p:contains("This web page is under maintenance. Please check back later."):visible');
                if (maintenance.length) {
                    provider.setError([util.filter(maintenance.text()), util.errorCodes.providerError]);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
	},

    isLoggedIn: function () {
		browserAPI.log("isLoggedIn");
        if ($('button.main-module__profile-summary__footer-logout:contains("Sign Out")').length > 0) {
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('form[id = "form"], form.login__form:visible').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
        return null;
	},

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('#loyalty-card-number').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
	},

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('login', function() {
            document.location.href = 'https://www.aeroflot.ru/personal/logout';
        });
	},

    login: function (params) {
		browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0   ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://www.aeroflot.ru/sb/pnr/app/en-en';
            });
            return;
        }
        setTimeout(function() {
		let form = $('form[id = "form"]');
        if (form.length > 0) {
			browserAPI.log("submitting saved credentials");
			form.find('input[name = "login"]').val(params.account.login);
			form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    //browserAPI.log("waiting captcha -> " + captcha);
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("waiting...");
                        if (provider.isMobile) {
                            provider.command('show', function(){
                                provider.reCaptchaMessage();
                                var submitButton = form.find('input[name = "submit0"]');
                                var fakeButton = submitButton.clone();
                                form.find('div.field_box:has(input[name = "submit0"])').append(fakeButton);
                                submitButton.hide();
                                fakeButton.unbind('click mousedown mouseup tap tapend');
                                fakeButton.bind('click', function (event) {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    if (params.autologin) {
                                        browserAPI.log("captcha entered by user");
                                        provider.setNextStep('checkLoginErrors', function () {
                                            submitButton.click();
                                        });
                                    }
                                    else {
                                        provider.command('hide', function () {
                                            browserAPI.log("captcha entered by user");
                                            provider.setNextStep('checkLoginErrors', function () {
                                                submitButton.click();
                                            });
                                        });
                                    }
                                });
                            });
                        }else{
                            provider.reCaptchaMessage();
                            setTimeout(function() {
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }, 1000*120);
                        }
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.find('input[name = "submit0"]').click();
                    }
                },1000);
            });
		}
		else {
            form = $('form.login__form:visible');
            if (form.length > 0) {
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
                    "triggerInput('input.main-module__input__text-input[type=text]', '" + params.account.login + "');\n" +
                    "triggerInput('input.main-module__input__text-input[type=password]', '" + params.account.password + "');"
                );
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[type = "submit"]').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                });
            }
            else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        }
        },1000);
	},

	checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
		let errors = $('div.info_important ul li, div.login__form-message--error:visible');
		if (errors.length == 0)
            errors = $('div[class *= "message error"] > p:visible:not(:contains("Back"))');
        if (errors.length == 0 && $('div[class *= "message error"]:contains("System authorization error. Please try again later"):visible').length > 0) {
            provider.setError(["System authorization error. Please try again later", util.errorCodes.providerError], true);
            return true;
        }
        if ($('h2:contains("To work with your personal account, connect to the SMS-Info service."):visible').length > 0) {
            provider.setError(['Aeroflot Bonus website is asking you to update your profile, until you do so we would not be able to retrieve your account information.', util.errorCodes.providerError], true);
            return true;
        }
        // retries
        var retry = $.cookie("aeroflot.ru_aw_retry_"+params.account.login);
        if ($('p:contains("Please confirm that you are not a robot"):visible,' +
              'p:contains("Пожалуйста, подтвердите, что вы не робот"):visible,' +
              'p:contains("Please, confirm that you are not a robot"):visible,' +
              'p:contains("Пожалуйста, подтвердите, что Вы не робот"):visible').length > 0
            ||
            $('form.login__form button[type = "submit"]:visible').length > 0
        ) {
            browserAPI.log(">>> Login failed");
            if (retry == null || retry < 2) {
                if (retry == null)
                    retry = 0;
                retry++;
                $.cookie("aeroflot.ru_aw_retry_"+params.account.login, retry, { expires: 0.01, path:'/', domain: '.aeroflot.ru', secure: true });
                plugin.login(params);
                return true;
            }
            else {
                let counter = 0;
                let login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    let errors = $('div.info_important ul li, div.login__form-message--error:visible');
                    let captcha = $('p:contains("Please confirm that you are not a robot"):visible,' +
                          'p:contains("Пожалуйста, подтвердите, что вы не робот"):visible,' +
                          'p:contains("Please, confirm that you are not a robot"):visible,' +
                          'p:contains("Пожалуйста, подтвердите, что Вы не робот"):visible');
                    if (captcha.length === 0 && errors.length > 0) {
                        clearInterval(login);
                        provider.setError(errors.text(), true);
                    }// if (errors.length > 0)
                    if (counter > 120) {
                        clearInterval(login);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    counter++;
                }, 1000);
                return true;
            }
        }
		if (errors.length > 0 && util.filter(errors.text()) !== '') {
            // Personal account is now available for Aeroflot Bonus members only.
            if (/Personal account is now available for Aeroflot Bonus members only\./.test(errors.text())) {
                provider.setError(['You are not a member of this loyalty program.', util.errorCodes.providerError], true);
                return true;
            }
            if (
                /The participant’s online account will be inaccessible till\./.test(errors.text())
                || /личный кабинет участника будет недоступен в связи с плановой модернизацией технической платформы\./.test(errors.text())
            ) {
                provider.setError([util.filter(errors.text()), util.errorCodes.providerError], true);
                return true;
            }
            // Your account is locked.
            if (/(?:Your account is locked|Ваш личный кабинет заблокирован)\./.test(errors.text())) {
                provider.setError([util.filter(errors.text()), util.errorCodes.lockout], true);
                return true;
            }
            provider.setError(util.filter(errors.text()), true);
		}// if (errors.length > 0)
		else {

		    var maintenance = $('p:contains("This web page is under maintenance. Please check back later."):visible');
		    if (maintenance.length > 0) {
                provider.setError([util.filter(maintenance.text()), util.errorCodes.providerError], true);
                return true;
            }

			plugin.loginComplete(params);
            return true;
        }
		return false;
	},

	loginComplete: function(params) {
        provider.logBody("loginCompletePage");
        if ($('form.login__form:visible').length) {
            browserAPI.log("Check for errors");
            provider.complete();
            return;
        }
        browserAPI.log("loginComplete");
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://m.aeroflot.ru/b/my';
            });
			return;
		}
        // parse account
        if (params.autologin) {
            browserAPI.log("only autologin");
            provider.complete();
            return;
        }
        if ($('p:contains("To work with your personal account, connect to the SMS-Info service"):visible').length) {
            provider.setError(['Aeroflot Bonus website is asking you to update your profile, until you do so we would not be able to retrieve your account information.', util.errorCodes.providerError], true);
            return;
        }
        let question = $('p:contains("A confirmation code has been sent to your mobile phone"),' +
             'p:contains("направлен код подтверждения, пожалуйста, введите его в выделенное поле"),' +
             'p:contains("番号に認証コードが送信されました。表示された番号を画面の空欄に入力してください。"),' +
             'h2:contains("Confirm sign-in"),' +
             'h2:contains("The confirmation code has been sent to phone number")'
        );
        browserAPI.log("question: " + question.length);
        if (question.length === 0) {
            plugin.redirectToProfile(params);
            return;
        }
        provider.setNextStep('redirectToProfile', function() {
            if (provider.isMobile) {
                let form = $('form[id = "form"], form.main-module__login__form-wrapper');
                provider.command('show', function () {
                    provider.showFader('Message from AwardWallet: In order to log in into this account please answer the question below and click the “Confirm” button. Once logged in, sit back and relax, we will do the rest.');/*review*/
                    let submitButton = form.find('input[name = "submit_pin"], button:contains("Confirm")');
                    /*
                    let fakeButton = submitButton.clone();
                    fakeButton.insertBefore(form.find('div.buttons:has(input[name = "send_pin"]), button:contains("Confirm")'));
                    submitButton.hide();
                    fakeButton.unbind('click mousedown mouseup tap tapend');
                    fakeButton.bind('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    */
                    submitButton.bind('click.code', function (event) {
                        if (params.autologin) {
                            browserAPI.log("answers entered by user");
                            provider.setNextStep('checkLoginErrors', function () {
                                submitButton.click();
                            });
                        } else {
                            provider.command('hide', function () {
                                browserAPI.log("answers entered by user");
                                provider.setNextStep('checkLoginErrors', function () {
                                    submitButton.click();
                                });
                            });
                        }
                    });
                });
            } else {
                provider.showFader('Message from AwardWallet: In order to log in into this account please answer the question below and click the “Confirm” button. Once logged in, sit back and relax, we will do the rest.');/*review*/
                provider.setTimeout(function () {
                    provider.setError([question.text(), util.errorCodes.question], true);
                }, 1000 * 120);
            }
        });
	},

    redirectToProfile: function(params) {
        browserAPI.log('redirectToProfile');
        provider.updateAccountMessage();
        plugin.parse(params);
        // set English language
        /*
        provider.setNextStep('loadingAccount', function () {
            document.location.href = "https://www.aeroflot.ru/personal/set_lang/en";
        });
        */
    },

	toItineraries: function(params) {
        browserAPI.log('toItineraries');
		var confNo = params.account.properties.confirmationNumber;
		var link = $('a[href*="' + confNo + '"]');
		if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function() {
                var href = link.attr('href');
                document.location.href = 'https://m.aeroflot.ru' + href;
            });
		}
		else
            provider.setError(util.errorMessages.itineraryFormNotFound);
	},

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var login = $('label:contains("Booking code (PNR)")').closest('div').find('input');
        var password = $('label:contains("Last name in Latin letters")').closest('div').find('input');
        if (login.length > 0 && password.length > 0) {
            login.val(properties.ConfNo);
            util.sendEvent(login.get(0), 'blur');
            password.val(properties.LastName);
            util.sendEvent(password.get(0), 'blur');
            provider.setNextStep('getConfNoItinerary2', function() {
                setTimeout(function() {
                    $('button.button--lg').click();
                    setTimeout(function() {
                        plugin.getConfNoItinerary2(params);
                    }, 7000);
                }, 4000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    getConfNoItinerary2: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var login = $('label:contains("Booking code (PNR)")').closest('div').find('input');
        var password = $('label:contains("Last name in Latin letters")').closest('div').find('input');
        if (login.length > 0 && password.length > 0) {
            login.val(properties.ConfNo);
            util.sendEvent(login.get(0), 'blur');
            password.val(properties.LastName);
            util.sendEvent(password.get(0), 'blur');
            provider.setNextStep('itLoginComplete', function() {
                setTimeout(function() {
                    $('button.button--lg').click();
                }, 4000);
            });
        }// if (form.length > 0)
        else
            plugin.itLoginComplete();
    },

	itLoginComplete: function(params) {
		provider.complete();
	},
/*

    loadingAccount: function(params) {
        browserAPI.log('loadingAccount');
        provider.updateAccountMessage();
        provider.setNextStep('parseDelay', function () {
            document.location.href = "https://www.aeroflot.ru/personal/activity";
        });
    },

    parseDelay: function(params) {
        browserAPI.log("parseDelay");
        let counter = 0;
        let parse = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if (
                $('legend:contains("Account status")').length > 0
                || $('h1:contains("Consent to personal data processing"), h1:contains("Согласие на обработку персональных данных")').length > 0
            ) {
                clearInterval(parse);
                plugin.parse(params);
            }
            if (counter > 10) {
                clearInterval(parse);
                plugin.parse(params);
            }
            counter++;
        }, 1000);
    },
*/
    parse: function (params) {
        browserAPI.log("parse");
        let token = localStorage.getItem('auth.accessToken');
        let headers = {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'x-ibm-client-id': window["afl-frontend-runtime-config"] && window["afl-frontend-runtime-config"].CLIENT_ID || "52965ca1-f60e-46e3-834d-604e023600f2",
            'x-ibm-client-secret': window["afl-frontend-runtime-config"] && window["afl-frontend-runtime-config"].CLIENT_SECRET || "rU0gE3yP1wV0dY6nJ8kY8pD6pI5dF7xP5nH5nR4cH3sC0rK2rR"
        };
        $.ajax({
            //async: false,
            //crossDomain: true,
            type: 'POST',
            url: 'https://gw.aeroflot.ru/api/pr/LKAB/Profile/v3/get',
            data: JSON.stringify({data:{}, lang: 'en'}),
            headers: headers,
            dataType: 'json',
            success: function (response) {
                console.log('success');
                response = response.data;
                var loyaltyInfo = response.loyaltyInfo;
                var data = {};
                data.Name = util.beautifulName(response.contact.firstName + " " + response.contact.lastName);
                data.Level = util.beautifulName(loyaltyInfo.tierLevel);
                var date = null;
                if (typeof loyaltyInfo.tierLevelExpirationDate !== undefined && loyaltyInfo.tierLevelExpirationDate !== '' && loyaltyInfo.tierLevelExpirationDate != null) {
                    date = new Date(loyaltyInfo.tierLevelExpirationDate);
                    date = ((date.getMonth() > 8) ? (date.getMonth() + 1) : ('0' + (date.getMonth() + 1))) + '/' + ((date.getDate() > 9) ? date.getDate() : ('0' + date.getDate())) + '/' + date.getFullYear();
                    data.LevelExpirationDate = date;
                }
                data.Number = loyaltyInfo.loyaltyId;
                data.Balance = loyaltyInfo.miles.balance;
                data.QualMiles = loyaltyInfo.miles.qualifying;
                data.FlightSegments = loyaltyInfo.currentYearStatistics.segments;

                date = new Date(loyaltyInfo.regDate);
                date = ((date.getMonth() > 8) ? (date.getMonth() + 1) : ('0' + (date.getMonth() + 1))) + '/' + ((date.getDate() > 9) ? date.getDate() : ('0' + date.getDate())) + '/' + date.getFullYear();
                data.EnrollmentDate = date;

                var exp = loyaltyInfo.miles.expirationDate;
                if (exp) {
                    browserAPI.log("Expiration Date: " + exp);
                    date = new Date(exp + ' UTC');
                    if (!isNaN(date)) {
                        var unixtime = date / 1000;
                        if (!isNaN(unixtime)) {
                            browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime));
                            data.AccountExpirationDate = unixtime;
                        }
                    } else
                        browserAPI.log("Invalid Expiration Date");
                } else
                    browserAPI.log("Miles activity date not found");

                console.log(data);
                params.data = data;
                params.account.properties = params.data;
                provider.saveProperties(params.account.properties);

                if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
                    plugin.parseItineraries(params);
                }
                else
                    provider.complete();
            },
            error: function (response) {
                console.log(`response.status = ${response.status}`);
                //provider.complete();
            }
        });
    },

    /*parse2: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        var data = {};

        // Name
        var name = $('.main-module__loyalty-card__name:eq(0)');
        if (name.length > 0) {
            data.Name = util.beautifulName(util.trim(util.filter(name.text())));
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log("Name not found");
        // Balance - Current Balance
        var balance = $('.main-module__profile-summary__miles > a');
        if (balance.length > 0) {
            data.Balance = util.trim(util.findRegExp(balance.text(), /([\d\s.,]+)/ig));
            browserAPI.log("Balance: " + data.Balance);
        } else
            browserAPI.log("Balance not found");
        // Segments
        var segments = $('.main-module__profile-summary__info-item-title:contains("Flight segments (")');
        if (segments.length > 0) {
            data.Segments = util.findRegExp(segments.text(), /\((\d+) \//ig);
            browserAPI.log("Segments: " + data.Segments);
        } else
            browserAPI.log("Segments not found");
        // Level
        var level = $('.main-module__profile-summary__title:contains(" tier")');
        if (level.length > 0) {
            data.Level = util.beautifulName(util.findRegExp(level.text(), /To ([A-z]+) tier/ig));
            browserAPI.log("Level: " + data.Level);
        } else
            browserAPI.log("Level not found");
        // Aeroflot Bonus Number
        var number = $('#loyalty-card-number');
        if (number.length == 0 || util.filter(number.text()) == '')
            number = $('#member_loyalty_id');
        if (number.length > 0) {
            data.Number = number.text();
            browserAPI.log("Number: " + data.Number);
        } else
            browserAPI.log("Number not found");
        // Qualifying Miles
        var qualMiles = $('div:contains("Qualifying miles")').parent().next('a').find('.main-module__progress-bar__value');
        if (qualMiles.length > 0) {
            data.QualMiles = util.findRegExp(qualMiles.text(), /[\s\d]+ \//ig);
            browserAPI.log("QualMiles: " + data.QualMiles);
        } else
            browserAPI.log("QualMiles not found");
        // Enrollment date
        /!*
        var enrollment = $('label:contains("Enrollment date") + span');
        if (enrollment.length > 0) {
            data.EnrollmentDate = enrollment.text();
            browserAPI.log("EnrollmentDate: " + data.EnrollmentDate);
        } else
            browserAPI.log("EnrollmentDate not found");
        *!/
        // Miles activity date
        var exp = $('label:contains("Miles activity date") + span');
        if (exp.length > 0) {
            exp = util.modifyDateFormat(exp.text(), '.');
            browserAPI.log("Expiration Date: " + exp);
            var date = new Date(exp + ' UTC');
            if (!isNaN(date)) {
                var unixtime = date / 1000;
                if ( date != 'NaN' && !isNaN(unixtime) ) {
                    browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime) );
                    data.AccountExpirationDate = unixtime;
                }
            } else
                browserAPI.log("Invalid Expiration Date");
        } else
            browserAPI.log("Miles activity date not found");

        // save data
        params.data = data;
        params.account.properties = params.data;
        provider.saveProperties(params.account.properties);
        //browserAPI.log(params.account.properties);//todo

        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            if (document.location.href != 'https://www.aeroflot.ru/lk/app/us-en/services?_preferredLanguage=en') {
                provider.setNextStep('parseItineraries', function () {
                    document.location.href = 'https://www.aeroflot.ru/lk/app/us-en/services?_preferredLanguage=en';
                });
            }// if (document.location.href != 'https://www.aeroflot.ru/personal/my_bookings?_preferredLanguage=en')
            else
                plugin.parseItineraries(params);
        }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
        else
            provider.complete();
    },*/

    parseItineraries: function(params) {
        browserAPI.log("parseItineraries");

        params.data.Itineraries = [];
        params.data.links = [];

        let token = localStorage.getItem('auth.accessToken');
        let headers = {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'x-ibm-client-id': window["afl-frontend-runtime-config"] && window["afl-frontend-runtime-config"].CLIENT_ID || "52965ca1-f60e-46e3-834d-604e023600f2",
            'x-ibm-client-secret': window["afl-frontend-runtime-config"] && window["afl-frontend-runtime-config"].CLIENT_SECRET || "rU0gE3yP1wV0dY6nJ8kY8pD6pI5dF7xP5nH5nR4cH3sC0rK2rR"
        };
        $.ajax({
            //async: false,
            //crossDomain: true,
            type: 'POST',
            url: 'https://gw.aeroflot.ru/api/pr/SB/UserLoyaltyPNRs/v1/get',
            data: JSON.stringify({lang: 'en'}),
            headers: headers,
            dataType: 'json',
            xhrFields: {
                withCredentials: true
            },
            success: function (response) {
                console.log('success');
                if (response.data.pnrs && response.data.pnrs.length === 0) {
                    params.account.properties.Itineraries = [{ NoItineraries: true }];
                    //browserAPI.log(params.account.properties);
                    provider.saveProperties(params.account.properties);
                    provider.complete();
                    return;
                }

                for (var i = 0; i < response.data.pnrs.length; i++) {
                    var link = `https://www.aeroflot.ru/sb/pnr/app/us-en#/?pnr_locator=${response.data.pnrs[i].pnrLocator}&last_name=${response.data.pnrs[i].mainPassenger.lastName}&first_name=${response.data.pnrs[i].mainPassenger.firstName}`;
                    browserAPI.log('Link ' + link);
                    params.data.links.push(link);
                }
                if (params.data.links.length > 0) {
                    var nextLink = params.data.links.shift();
                    provider.setNextStep('waitItinerary', function () {
                        provider.saveTemp(params.data);
                        document.location.href = nextLink;
                    });
                }
            },
            error: function (response) {
                console.log(`response.status = ${response.status}`);
                provider.complete();
            }
        });
    },

    waitItinerary: function (params) {
        browserAPI.log('waitItinerary');
        util.waitFor({
            selector: 'div.text--gray:contains("Booking code *")',
            success: function () {
                plugin.parseItinerary(params);
            },
        });
    },

    parseItinerary: function (params) {
        browserAPI.log("parseItinerary");
        provider.updateAccountMessage();

        var result = {};

        // ConfirmationNumber
        result.RecordLocator = $('div.text--gray:contains("Booking code *")').next('div').text();
        browserAPI.log("RecordLocator: " + result.RecordLocator);
        // Passengers
        result.Passengers = [];
        $('span.icon--man-blue').each(function () {
            result.Passengers.push(util.beautifulName($(this).next('p').text()));
        });
        browserAPI.log("Passengers: " + result.Passengers);
        // AccountNumbers
        var accountNumbers = [];
        $('div.h-lh--24:contains("Aeroflot Bonus")').each(function() {
            accountNumbers.push($(this).text().replace(/[^\d\,]/g, ''));
        });
        result.AccountNumbers = accountNumbers;
        browserAPI.log("AccountNumbers: " + result.AccountNumbers);
        // TicketNumbers
        var ticketNumbers = [];
        $('a.button--small[href *= "ums-doc"]').each(function() {
            ticketNumbers.push($(this).text());
        });
        result.TicketNumbers = ticketNumbers;
        browserAPI.log("TicketNumbers: " + result.TicketNumbers);

        // Segments
        result.TripSegments = [];
        var segments = $('div.flight-booking__row').not('.flight-booking__row--head');
        browserAPI.log(">>> Total segments were found: " + segments.length);
        var k = 0;
        segments.each(function () {
            browserAPI.log(">>> Segment " + k);
            var node = $(this);
            var singleSeg = {};
            var DT;
            var unixtime;
            var date = util.findRegExp(node.prevAll().last().find('div.flight-booking__day-title').text(), /(^.+?\d{4}),/);

            // FlightNumber
            singleSeg.FlightNumber = util.findRegExp(node.find('div.flight-booking__flight-number').text() , /\w{2}\s*(\d+)/i);
            browserAPI.log("FlightNumber: " + singleSeg.FlightNumber);
            // AirlineName
            singleSeg.AirlineName = util.findRegExp(node.find('div.flight-booking__flight-number').text() , /(\w{2})\s*\d+/i);
            browserAPI.log("AirlineName: " + singleSeg.AirlineName);
            // DepCode
            singleSeg.DepCode = node.find('div.time-destination__airport:nth(0)').text();
            browserAPI.log("DepCode: " + singleSeg.DepCode);
            // DepartureTerminal
            singleSeg.DepartureTerminal = node.find('div.time-destination__terminal:nth(0)').text() || null;
            browserAPI.log("DepartureTerminal: " + singleSeg.DepartureTerminal);
            // ArrCode
            singleSeg.ArrCode = node.find('div.time-destination__airport:nth(1)').text();
            browserAPI.log("ArrCode: " + singleSeg.ArrCode);
            // ArrivalTerminal
            singleSeg.ArrivalTerminal = node.find('div.time-destination__terminal:nth(1)').text() || null;
            browserAPI.log("ArrivalTerminal: " + singleSeg.ArrivalTerminal);
            // DepDate
            var depTime = node.find('div.time-destination__time:nth(0)').text();
            var depDate = date + ' ' + depTime;
            DT = new Date(depDate + ' UTC');
            if (DT.getTime()) {
                unixtime = DT.getTime() / 1000;
                browserAPI.log("DepDate: " + depDate + " Unixtime: " + unixtime);
                singleSeg.DepDate = unixtime;
            }
            // ArrDate
            var arrTime = node.find('div.time-destination__time:nth(1)').text();
            var arrDate = date + ' ' + arrTime;
            DT = new Date(arrDate + ' UTC');
            if (DT.getTime()) {
                unixtime = DT.getTime() / 1000;
                browserAPI.log("ArrDate: " + arrDate + " Unixtime: " + unixtime);
                singleSeg.ArrDate = unixtime;
            }
            // Cabin
            singleSeg.Cabin = node.find('div.flight-booking__class').text();
            browserAPI.log("Cabin: " + singleSeg.Cabin);
            // Status
            singleSeg.Status = node.find('div.flight-booking__status').text();
            browserAPI.log("Status: " + singleSeg.Status);
            // Aircraft
            singleSeg.Aircraft = node.find('div.flight-booking__ship').text();
            browserAPI.log("Aircraft: " + singleSeg.Aircraft);
            // Duration
            singleSeg.Duration = node.find('div.flight-booking__flight-time').text();
            browserAPI.log("Duration: " + singleSeg.Duration);
            // Meal
            singleSeg.Meal = node.find('div.flight-booking__food').text();
            browserAPI.log("Meal: " + singleSeg.Meal);

            result.TripSegments.push(singleSeg);

            browserAPI.log("<<< Segment id " + k);
            k++;
        });// segments.forEach(function(segment)

        if (result.TicketNumbers.length == 0 && result.TripSegments == 0) {
            browserAPI.log('Skipping "No data" flight');
        } else {
            params.data.Itineraries.push(result);
        }

        if (params.data.links.length == 0) {
            params.account.properties.Itineraries = params.data.Itineraries;
            //browserAPI.log(params.account.properties);//todo
            provider.saveProperties(params.account.properties);
            provider.complete();
        } else {
            var nextLink = params.data.links.shift();
            plugin.waitItinerary(params);
            provider.saveTemp(params.data);
            document.location.href = nextLink;
            // provider.setNextStep('waitItinerary', function () {
            // });
        }
    },

    unionArray: function ( elem, separator, unique ){
        // $.map not working in IE 8, so iterating through items
        var result = [];
        for (var i = 0; i < elem.length; i++) {
            var text = util.trim(elem.eq(i).text());
            if (text != "" && (!unique || result.indexOf(text) == -1))
                result.push(text);
        }
        return result.join( separator );
    }
};
