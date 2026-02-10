var plugin = {

    hosts : {
        'www.avis.co.uk'				: true,
        'secure.avis.co.uk'				: true,
        '/\\w+\\.avis\\.\\w+/'			: true,
        '/\\w+\\.avis\\.\\w+\\.\\w+/'   : true,
        'secure.avis-europe.com'		: true,
        'www.avisloyalty.eu'			: true,
        '/\\w+\\.avisautonoleggio\\.it/': true
    },

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback : function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl : function (params) {
        switch (params.account.login2) {
            case 'Australia':
                return 'https://www.avis.com.au/en/loyalty-profile/avis-preferred/dashboard/my-activity';
                break;
            case 'Germany':
                return 'https://secure.avis.de/';
                break;
            case 'Belgium':
                return 'https://secure.avis.be/';
                break;
            case 'Finland':
                return 'https://secure.avis.fi/';
                break;
            case 'France':
                return 'https://secure.avis.fr/';
                break;
            // return 'https://secure.avis-europe.com/secure/preferred/default.aspx?Locale=fi-FI&Domain=FI&NBE=true';
            case 'Italy':
                return 'https://secure.avisautonoleggio.it/';
                break;
            case 'Norway':
                return 'https://secure.avis.no/';
                break;
            case 'Spain':
                return 'https://secure.avis.es/';
                break;
            case 'Sweden':
                return 'https://secure.avis.se/';
                break;
            case 'Switzerland':
                return 'https://secure.avis.ch/';
                break;
            case 'UK':
                return 'https://secure.avis.co.uk/';
                break;
            default:
                return 'https://www.avis.com/en/loyalty-profile/avis-preferred/dashboard/my-activity';
                break;
        }
    },

    start : function (params) {
        browserAPI.log("start");
        setTimeout(function() {
            if (plugin.isLoggedIn(params.account.login2))
                plugin.logout(params.account.login2);
            else {
                if (provider.isMobile)
                    plugin.login(params);
                else
                    plugin.loadLoginForm(params);
            }
        }, 2500);
    },

    isLoggedIn : function (region) {
        browserAPI.log("isLoggedIn");
        switch (region) {
            case 'Belgium':
            case 'Germany':
            case 'Finland':
            case 'France':
            case 'Norway':
            case 'Spain':
            case 'Sweden':
            case 'Switzerland':
            case 'Italy':
            case 'UK':
                if ('' != $('#custwizdetail').text()) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('form.login-form, form#loginForm').length) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            // case 'Finland':
            // 	return false;
            // 	break;
            case 'Australia':
            default:
                if ($('div.hidden-sm h3:contains("#"):eq(0)').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('form[name = "loginForm"]:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    logout : function (region) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            switch (region) {
                case 'Belgium':
                case 'Germany':
                case 'Finland':
                case 'France':
                case 'Norway':
                case 'Spain':
                case 'Sweden':
                case 'Switzerland':
                case 'Italy':
                case 'UK':
                    $('a.sign-out').get(0).click();
                    break;
                case 'Australia':
                default:
                    $('button.my-logout').get(0).click();
                    break;
            }
        });
    },

    loadLoginForm : function (params) {
        browserAPI.log("loadLoginForm");
        if (provider.isMobile)
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        else {
            if (
                document.location.href === 'https://www.avis.com/en/loyalty-profile/avis-preferred/login'
                || document.location.href === 'https://www.avis.com.au/en/loyalty-profile/avis-preferred/login'
            ) {
                plugin.login(params);
                return;
            }

            provider.setNextStep('login', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }
    },

    login : function (params) {
        browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            var url;
            switch (params.account.properties.confFields.Region) {
                case 'Australia':
                    url = "https://www.avis.com.au/en/reservation/view-modify-cancel";
                    break;
                case 'Belgium':
                    url = 'https://secure.avis.be/mijn-avis/reservering-wijzigen';
                    break;
                case "Germany":
                    url = 'https://secure.avis.de/mein-avis/buchung-bearbeiten';
                    break;
                case 'France':
                    url = 'https://secure.avis.fr/votre-avis/g%C3%A9rer-ma-r%C3%A9servation';
                    break;
                case "Finland":
                    url = 'https://secure.avis.fi/oma-avis/hallinnoi-varaustasi';
                    break;
                case 'Italy':
                    url = 'https://secure.avisautonoleggio.it/avis-per-te/gestisci-prenotazione';
                    break;
                case 'Norway':
                    url = 'https://secure.avis.no/din-avis/manage-booking';
                    break;
                case 'Spain':
                    url = 'https://secure.avis.es/tu-avis/gestionar-reserva';
                    break;
                case 'Sweden':
                    url = 'https://secure.avis.se/ditt-avis/hantera-bokning';
                    break;
                case 'Switzerland':
                    url = 'https://secure.avis.ch/mein-avis/buchung-bearbeiten';
                    break;
                case "UK":
                    url = 'https://secure.avis.co.uk/your-avis/manage-booking';
                    break;
                default:
                    url = "https://www.avis.com/en/reservation/view-modify-cancel";
                    break;
            }// switch (params.account.properties.confFields.Region)
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = url;
            });
            return;
        }// if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0)

        switch (params.account.login2) {
            case 'Belgium':
            case 'Germany':
            case 'Finland':
            case 'France':
            case 'Norway':
            case 'Spain':
            case 'Sweden':
            case 'Switzerland':
            case 'Italy':
            case 'UK':
                $('#your-avis-tab').click();
                var form = $('form.login-form, form#loginForm');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    $('#your-avis-tab').click();
                    $('#login-email').val(params.account.login);
                    $('#login-hidtext').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button.submit-button').click();
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            // case 'Finland':
            // 	var form = $('form.#aspnetForm');
            // 	if (form.length > 0) {
            //        browserAPI.log("submitting saved credentials");
            //        form.find('#ctl00_main_signIn_txtLogInEmailAddress').val(params.account.login);
            //        form.find('#ctl00_main_signIn_txtLogInPassword').val(params.account.password);
            //        provider.setNextStep('checkLoginErrors', function () {
            //            form.find('#ctl00_main_signIn_btnLogIn').get()[0].click();
            //        });
            //    }
            //    else
            //        provider.setError(util.errorMessages.loginFormNotFound);
            // 	break;
            case 'Australia':
            default:
                var form = $('form[name = "loginForm"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('#username').val(params.account.login);
                    form.find('#password').val(params.account.password);
                    util.sendEvent(form.find('input[name = "username"]').get(0), 'input');
                    util.sendEvent(form.find('input[name = "password"]').get(0), 'input');
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('#res-login-profile').click();
                        setTimeout(function () {
                            const captcha = form.find('div.g-recaptcha:visible');
                            if (captcha.length > 0) {
                                provider.reCaptchaMessage();
                                let counter = 0;
                                let login = setInterval(function () {
                                    browserAPI.log("waiting... " + counter);
                                    let error = $('div.mainErrorMsg:visible');
                                    if (error.length > 0) {
                                        clearInterval(login);
                                        plugin.checkLoginErrors(params);
                                    }
                                    let otp = $('input[name="otp"]:visible');
                                    if (otp.length > 40) {
                                        clearInterval(login);
                                        provider.complete();
                                    }
                                    if (counter > 120) {
                                        clearInterval(login);
                                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    }// if (counter > 120)
                                    counter++;
                                }, 1000);
                            }// if (captcha.length > 0)
                            else {
                                browserAPI.log("captcha is not found");
                                form.submit();
                            }
                        }, 2000)
                    });
                }// if (form.length > 0)
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
        }
    },

    checkLoginErrors : function (params) {
        browserAPI.log("checkLoginErrors");
        // if (params.account.login2 == 'Finland') {
        // var errors = $('#signIn').contents().filter(function() {
        // 	return this.nodeType === 3;
        // });
        // if (errors.length > 0) {
        // 		provider.setError(errors.text());
        // }else{
        // 	provider.setNextStep('loginComplete', function () {
        // 		$('a#btnLoyaltyPortal').get()[0].click();
        // 	});
        // }
        // }// if (params.account.login2 == 'Finland')
        // else {
        let errors = $('span.errorMessage, mark.login-email-hidtext-error .msg em');
        if (errors.length === 0)
            errors = $('div.mainErrorMsg:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete : function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            switch (params.account.login2) {
                case 'Belgium':
                case 'Germany':
                case 'Finland':
                case 'France':
                case 'Norway':
                case 'Spain':
                case 'Sweden':
                case 'Switzerland':
                case 'Italy':
                case 'UK':
                    plugin.toItineraries(params);
                    break;
                case 'Australia':
                    provider.setNextStep('toItineraries', function () {
                        document.location.href = 'https://www.avis.com.au/en/loyalty-profile/avis-preferred/dashboard/my-activity/upcoming-reservations';
                    });
                    //plugin.toItineraries(params);
                    break;
                // USA
                default:
                    provider.setNextStep('toItineraries', function () {
                        document.location.href = 'https://www.avis.com/en/loyalty-profile/avis-preferred/dashboard/my-activity/upcoming-reservations';
                    });
                    //plugin.toItineraries(params);
                    break;
            }
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        browserAPI.log('conf number: ' + confNo);
        var viewLink = $('#upbooking').next('.avis-panel-end-link').find('.viewallbooking');
        if(viewLink.length)
            viewLink.get(0).click();

        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('a[href *= "' + confNo.split('-').join('') + '"]:eq(0)');
            // USA
            if (link.length == 0)
                link = $('div.rental-list-item:has(span:contains("' + confNo + '")) a');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            if (counter > 15) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('#InputBookingNumber').closest('form');
        if (form.length > 0) {
            form.find('input[name = InputBookingNumber]').val(properties.ConfNo);
            form.find('input[name = InputSurname]').val(properties.LastName);
            form.find('input[name = InputEmailAddress]').val(properties.EmailAddress);
            provider.setNextStep('itLoginComplete', function () {
                form.find('button[type = submit]').get(0).click();
            });
        }// if (form.length > 0)
        else {
            form = $('form[name = "VMCForm"]');
            if (form.length > 0) {
                form.find('input[name = "vm.lookupModel.confirmationNumber"]').val(properties.ConfNo);
                form.find('input[name = "vm.lookupModel.lastName"]').val(properties.LastName);
                util.sendEvent(form.find('input[name = "vm.lookupModel.confirmationNumber"]').get(0), 'input');
                util.sendEvent(form.find('input[name = "vm.lookupModel.lastName"]').get(0), 'input');
                if (form.find('input[name = "vm.lookupModel.lastName"]').length) {
                    util.sendEvent(form.find('input[name = "vm.lookupModel.lastName"]').get(0), 'input');
                }
                provider.setNextStep('itLoginComplete', function () {
                    form.find('button[type = submit]').get(0).click();
                    setTimeout(function() {
                        plugin.itLoginComplete(params);
                    }, 7000);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        // provider bug workaround
        var form = $('#InputBookingNumber').closest('form:visible');
        if (form.length > 0 && $('span.error:visible').length > 0)
            form.find('button[type=submit]').get(0).click();

        provider.complete();
    }
};