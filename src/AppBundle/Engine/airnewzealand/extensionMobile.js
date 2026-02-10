var plugin = {
    flightStatus: {
        url: 'https://m.airnz.co.nz/arrivals-and-departures',
        match: /^(?:NZ)?\d+/i,

        start: function () {
            browserAPI.log("start");
            $('a.departures').get(0).click();
            var counter = 0;
            var start = setInterval(function() {
                var form = $('a.departures').filter('.selected');
                console.log('searching form... ' + start);
                if (form.length > 0) {
                    console.log('searching airport ' + params.arrCode);
                    clearInterval(start);
                    var option = $('select[name = airport]').find('option[value *= "' + params.arrCode + '"]');
                    console.log('option.length = ' + option.length);
                    if (option.length > 1 || option.length == 0)
                        option = $('select[name = airport]').find('option[value = "' + params.arrCode + 'I"]');
                    console.log('option.length = ' + option.length);
                    if (option.length == 1) {
                        api.setNextStep('finish', function() {
                            console.log('airport = ' + option.attr('value'));
                            document.location.href = "https://m.airnz.co.nz/arrivals-and-departures?type=D&airport=" + option.attr('value');
                        });
                    }// if (option.length == 1)
                    else
                        api.error("Destination not found");
                }
                if (counter > 20) {
                    clearInterval(start);
                    api.error('flight info not found');
                }
                counter++;
            }, 500);
        },

        finish: function () {
            api.complete(); // no errors at all :(
        }
    }
};