var plugin = {
    flightStatus:{
        url: 'http://www.cathaypacific.com/mobile/US?action=fsSearch',
        match: /^(?:CX|KA)?\d+/i,

        start: function () {
            var input = $('[name=fi]');
            var selectOperator = $('[name=c]');
            if (input !== null){
                if(opMatches = params.flightNumber.match(/(CX|KA)/i))
                    selectOperator.val(opMatches[1]);
                input.val(params.flightNumber.replace(/CX|KA/gi, ''));

                var depDateElem = $('option[value*="' + $.format.date(api.getDepDate(), 'yyyyMMdd') + '"]');
                if(depDateElem.length == 1){
                    $('select[name=d]').val(depDateElem.val());
                    api.setNextStep('finish', function(){
                        document.forms[0].submit();
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        finish: function () {
            if($('td:contains("departing")').length > 0){
                api.complete();
            }else{
                api.error('No flights can be found');
            }
        }
    }/*,

    autologin: {
        url: "http://asiamiles.com",

        getStartingUrl: function (){
            if(/(iPad|iPhone|iPod)/gi.test(navigator.userAgent)){
                // ios
                return 'https://www.asiamiles.com/am/en/iphone/login'
            }else{
                return 'https://www.asiamiles.com/am/en/mobile/login';
            }
        },

        start: function () {
            if (plugin.autologin.isLoggedIn())
                if (plugin.autologin.isSameAccount())
                    plugin.autologin.finish();
                else
                    plugin.autologin.logout();
            else
                plugin.autologin.login();
        },

        login: function () {
            var form = $('form[action *= "login"]');
            if(form.length == 1){
                form.find('#txtMbrID').val(params.login);
                form.find('#txtMbrPIN').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    form.find('input[type = "submit"]').click();
                    form.submit();
                })
            }else{
                api.error("can't find login form");
            }

        },

        checkLoginErrors: function (){
            var error = $('.warning_list');
            if(error.length > 0){
                api.error(error.text());
            }else{
                api.complete();
            }
        },

        logout: function () {
            api.setNextStep('login', function (){
                document.location.href = $('a[href *= "logout"]').attr('href');
            })
        },

        isSameAccount: function () {
            return ((typeof(account.properties) !== 'undefined')
                && (typeof(account.properties.Number) !== 'undefined') && (account.properties.Number.trim() !== '')
                && ($('*:has("' + account.properties.Number + '")]'))
            ) || ($('*:has("' + account.login + '")]'));
        },

        isLoggedIn: function (){
            if($('.account_summary').length > 0){
                return true;
            }

            if($('form[action *= "login"]').length > 0){
                return false;
            }

            if($('a[href *= "logout"]').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        finish: function () {
            api.complete();
        }
    }*/
};