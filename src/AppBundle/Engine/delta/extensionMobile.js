var plugin = {

    flightStatus : {
        url: 'https://m.delta.com/?p=flightStatusForm',
        match: /^(?:DL)?\s*\d+/i,

        start : function () {
            setTimeout(function () {
                var $flightDate = $('#flight_date');
                var $flightNumber = $('#flight_number');
                var $flightStatusFind = $('#view_flight_status_results');

                if ($flightNumber.length && $flightStatusFind.length) {
                    $('#search_by').val('flightNumber').trigger('change');
                    $flightNumber.val(params.flightNumber.replace(/DL/gi, ''));

                    var date = $.format.date(api.getDepDate(), 'MMM dd, yyyy');
                    if (params && params.depDate && params.depDate.fmt) {
                        var fmt = params.depDate.fmt;
                        date = $.format.date(new Date(fmt.y, fmt.m, fmt.d, fmt.h, fmt.i), 'MMM dd, yyyy');
                    }
                    var depDateElem = $('option:contains("' + date + '")', $flightDate);
                    if (depDateElem.length) {
                        $flightDate.val(depDateElem.val());
                        $flightStatusFind.trigger('click');
                        return plugin.flightStatus.finish();
                    }
                    api.errorDate();
                }

            }, 2000);
        },

        finish : function () {
            var counter = 0;
            var search = setInterval(function () {
                if ($('span:contains("DL' + params.flightNumber.replace(/DL/gi, '') + '")', '#flight_status_1').length) {
                    clearInterval(search);
                    api.complete();
                } else {
                    var $msg = $('.message');
                    if ('' != $msg.text()) {
                        clearInterval(search);
                        api.error($msg.text());
                    }
                }
                if (++counter > 5) {
                    clearInterval(search);
                    api.error();
                }
            }, 500);
        }
    }
};
