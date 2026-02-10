var plugin = {

    hosts : {
        'www.ana.co.jp' : true,
        'aswbe-i.ana.co.jp' : true
    },

    getStartingUrl : function(params) {
        return 'http://www.ana.co.jp/asw/wws/us/e/';
    },

    start : function(params) {
        browserAPI.log('start');
        setTimeout(function() {
            if (plugin.isLoggedIn(params)) {
                if (plugin.isSameAccount(params.account))
                    plugin.finish();
                else
                    plugin.logout(params);
            } else
                plugin.login(params);
        }, 2000);
    },

    isLoggedIn : function() {
        browserAPI.log('isLoggedIn');
        if ($('li.logout').length) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        if ($('form[action*="amcloginconfirm"],form[action*="amclogin_action"]').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount : function(account) {
        browserAPI.log('isSameAccount');
        if ('undefined' != typeof params.account.properties
            && 'undefined' != typeof params.account.properties.Name) {
            var name = $('p.login-name').text();
            return ('' != name && name.toLowerCase() == params.account.properties.Name.toLowerCase());
        }
        return false;
    },

    logout : function(params) {
        browserAPI.log('logout');
        provider.setNextStep('logoutConfirm', function() {
            document.location.href = 'https://www.ana.co.jp/asw/global/include/amclogout_e.jsp?type=us/en';
        });
    },

    logoutConfirm : function(params) {
        browserAPI.log('logoutConfirm');
        provider.setNextStep('loadLoginForm', function() {
            var $form = $('form[action*="amclogout_action"]');
            if ($form.length)
                return $('input[name="yes"]', $form).trigger('click');

            $('body').append('<form id="__emulateLogout" action="https://www.ana.co.jp/asw/include/amclogout_action.jsp" method="POST">' +
                '<input type="hidden" name="type" value="us/en">' +
                '<input type="checkbox" name="isNotConfirmLogout" value="1">' +
                '<input type="submit" name="yes" value="Yes">' +
                '<input type="button" value="No">' +
                '</form>'
            );
            setTimeout(function() {
                $('input[name="yes"]', '#__emulateLogout').click();
            }, 1000);
        });
    },

    loadLoginForm : function(params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('login', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login : function(params) {
        browserAPI.log('login');
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0)      {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://aswbe-i.ana.co.jp/international_asw/pages/servicing/reservation_confirm/login_search.xhtml?CONNECTION_KIND=LAX&LANG=en';
            });
            return;
        }

        // $('button.btn-login.js-toggleSwitch').click();
        $('li[class*=asw-header-login]:not([class*=new-user]):not([class*=hidden-md]) a')[0].click();
        
        setTimeout(function() {
            var $form = $('form[action*="amcloginconfirm"],form[action*="amclogin_action"]');
            if ($form.length) {
                $form.attr('action', 'https://www.ana.co.jp/asw/include/amclogin_action.jsp');
                $('input[name="custno"]', $form).val(params.account.login);
                $('input[name="password"]', $form).val(params.account.password);
                var $fields = '<input type="hidden" name="lasting" value="null">' +
                    '<input type="hidden" name="useragent" value="P">' +
                    '<input type="hidden" name="type" value="us/en">';
                $form.append($fields);
                return provider.setNextStep('checkLoginErrors', function() {
                    // $('button[type="submit"][name="login"]', $form).trigger('click');
                    $('form[action*="amcloginconfirm"],form[action*="amclogin_action"]').submit();
                });
            }
            provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#noMemberLogin');
        if (form.length > 0) {
            form.find('input[name = "recLoc"]').val(properties.ConfNo);
            form.find('input[name = "passportFirstName"]').val(properties.FirstName);
            form.find('input[name = "passportLastName"]').val(properties.LastName);
            provider.setNextStep('informationAgreement', function() {
                form.find('input[name = "searchByRecLoc"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    informationAgreement: function(params) {
        browserAPI.log('informationAgreement');
        var form = $('form#j_idt327');
        if (form.length > 0) {
            provider.setNextStep('itLoginComplete', function() {
                form.find('input[name = "detailMessages:0:detailRuleMessageCheckbox"]').click();
                form.find('input[name = "forward"]').click();
            });
        } else {
            plugin.itLoginComplete(params);
        }
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    checkLoginErrors : function(params) {
        browserAPI.log('checkLoginErrors');
        var $error = $('p:contains("We were unable to verify your ANA number"), p:contains("Please enter your 10-digit number on your card"), p:contains("Web password is necessary to use the online services")');
        var $loginModalActive = $('#login-modal[aria-hidden="false"]');
        var $errorIsPresent = $error.length > 0 && $loginModalActive.length > 0;
        if ($errorIsPresent && '' != util.trim($error.text()))
            provider.setError('We were unable to verify your ANA number or password');
        else
            plugin.finish();
    },

    finish : function() {
        browserAPI.log('finish');
        provider.complete();
    }

};
