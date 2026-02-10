/**
 * @param {AbstractFormExtension} extension
 */
function addFormExtension(extension) {

    /**
     * will be called on field change
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFieldChange = function (form, fieldName) {
        var authinfo = form.getValue('authinfo') != '';
        var login2 = form.getValue('login2');

        if (fieldName == 'login2' || fieldName == 'authinfo') {
            var oauth = form.getValue('login2') != 'CA';
            var topDesc = form.getInput('topDesc');
            if (topDesc) {
                form.showField('topDesc', oauth);
            }
            form.showField("authinfo", oauth);
            form.requireField("authinfo", false);
            form.requireField('login', !oauth || !authinfo);
            form.requireField('pass', !oauth || !authinfo);
        }

        if (form.hasOwnProperty('mobile') && form.mobile === true) {//temp fix for mobile
            if (login2 === 'US') {
                form.requireField('login', false);
                form.requireField('pass', false);
            }
        }
    };

    /**
     * will be called when form loaded and ready
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFormReady = function (form, fieldName) {
        this.onFieldChange(form, 'login2');
        if (form.hasOwnProperty('mobile') && form.mobile === true) {//temp fix for mobile
            form.requireField('login2', true);
        }
    };

}

