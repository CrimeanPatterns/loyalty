var plugin = {

    hosts: {'login.live.com': true, 'www.bing.com': true, 'ssl.bing.com': true},

    getStartingUrl: function(params){
        var unixtime = Math.round(new Date().getTime() / 1000);
        return 'https://login.live.com/login.srf?wa=wsignin1.0&rpsnv=11&ct=' + unixtime + '&rver=6.0.5286.0&wp=MBI&wreply=http:%2F%2Fwww.bing.com%2FPassport.aspx%3Frequrl%3Dhttp%253a%252f%252fwww.bing.com%252f%253fscope%253dweb%2526setmkt%253den-US%2526setlang%253dmatch%2526FORM%253dW5WA%2526uid%253dC3305BD0&lc=1033&id=264960';
    },

    start: function(params){
        if(plugin.isLoggedIn()){
            if(plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if( $('form[name = "f1"]').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if( $('a[href *=logout]').text() || $('#id_n').length > 0 ){
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
        throw "Can't determine login state";
    },

    isSameAccount: function(account){
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('#id_n').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function(){
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            var unixtime = Math.round(new Date().getTime() / 1000);
            document.location.href = 'http://login.live.com/logout.srf?ct='+ unixtime +'&rver=6.0.5286.0&lc=1033&id=264960&ru=http:%2F%2Fwww.bing.com%2FPassport.aspx%3Frequrl%3Dhttp%253a%252f%252fwww.bing.com%252f%253fscope%253dweb%2526setmkt%253den-US%2526setlang%253dmatch%2526FORM%253dW5WA%2526uid%253dC3305BD0';
        });
    },

    loadLoginForm: function(){
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('form[name = "f1"]');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "login"]').val(params.account.login);
            form.find('input[name = "passwd"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                $('#idSIButton9').click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function(){
        var errors = $("#idTd_Tile_Error");
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}