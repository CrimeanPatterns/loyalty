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
        if (fieldName == 'login3') {
            var jsonAuth = [
                'com.br',
                'com.au',
                'ca',
                'co.uk',
                'de',
                'com.mx',
                'es',
                'com',
                'fr',
                'nl',
            ];
            var visible = jsonAuth.indexOf(form.getValue('login3')) === -1;
            form.showField("login2", visible);
            form.requireField("login2", visible);
        }
    };

    /**
     * will be called when form loaded and ready
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFormReady = function (form, fieldName) {
        this.onFieldChange(form, 'login3');
    };

}

