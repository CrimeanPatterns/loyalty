define("common/template/serviceError", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "div",
            m: [{n: "id", f: "serviceErrorDialog", t: 13}, {n: "class", f: "jpui modal", t: 13}],
            f: [{
                t: 7,
                e: "div",
                m: [{
                    n: "class",
                    f: "dialog vertical-center col-xs-12 col-sm-7 col-sm-offset-2 col-lg-6 col-lg-offset-3 util print-width-100-percent",
                    t: 13
                }],
                f: [{
                    t: 7,
                    e: "section",
                    m: [{n: "class", f: "dialogContent", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "modalContent", t: 13}],
                        f: [{
                            t: 7,
                            e: "h1",
                            m: [{n: "tabindex", f: "-1", t: 13}, {n: "class", f: "u-no-outline", t: 13}],
                            f: [{t: 2, r: "logonErrorHeader"}]
                        }, " ", {
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "content", t: 13}],
                            f: [{t: 3, r: "logonErrorAdvisory"}]
                        }, " ", {
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "dialogButtonContainer row", t: 13}],
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "col-sm-4", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "button",
                                    m: [{n: "click", f: "exitLogonError", t: 70}, {
                                        n: "class",
                                        f: "jpui button primary fluid",
                                        t: 13
                                    }, {n: "id", f: "exitLogonError", t: 13}, {n: "tabindex", f: "0", t: 13}],
                                    f: [{
                                        t: 7,
                                        e: "div",
                                        m: [{n: "class", f: "label", t: 13}],
                                        f: [{t: 2, r: "exitLogonErrorLabel"}]
                                    }]
                                }]
                            }]
                        }]
                    }]
                }]
            }]
        }]
    }
})), define("common/template/relaxOrCompactTableRows", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", m: [{n: "class", f: "info-density-controls util print-hide", t: 13}], f: [{
                t: 7, e: "span", f: [{
                    t: 4, f: [{
                        t: 7,
                        e: "span",
                        m: [{n: "class", f: "icons-expand-collapse", t: 13}],
                        f: [" ", {
                            t: 7,
                            e: "blueIconAction",
                            m: [{
                                n: "id",
                                f: ["relaxedButton", {t: 4, f: ["-", {t: 2, r: ".viewName"}], n: 50, r: ".viewName"}],
                                t: 13
                            }, {n: "type", f: "info-density-relaxed util", t: 13}, {
                                n: "classes",
                                f: ["info-density-button expand-icon ", {
                                    t: 4,
                                    f: ["condensed"],
                                    r: ".tableRowsCompacted"
                                }, {t: 4, n: 51, f: ["relaxed"], l: 1}],
                                t: 13
                            }, {
                                n: "adatext",
                                f: [{
                                    t: 4,
                                    f: [{t: 2, r: ".crfRelaxTableRowsAda"}],
                                    n: 50,
                                    r: ".widgetConfig.isEnglishOnlyContent"
                                }, {t: 4, n: 51, f: [{t: 2, r: ".relaxTableRowsAda"}], l: 1}, {
                                    t: 4,
                                    f: [{
                                        t: 4,
                                        f: [{t: 2, r: "~/englishOnlyInformationDensityStatusAda"}],
                                        n: 50,
                                        r: ".widgetConfig.isEnglishOnlyContent"
                                    }, {t: 4, n: 51, f: [{t: 2, r: "~/informationDensityStatusAda"}], l: 1}],
                                    n: 50,
                                    x: {r: [".tableRowsCompacted"], s: "!_0"}
                                }],
                                t: 13
                            }, {
                                n: "minitooltiptext",
                                f: [{
                                    t: 4,
                                    f: [{t: 2, r: "~/englishOnlyRelaxTableRowsLabel"}],
                                    n: 50,
                                    r: ".widgetConfig.isEnglishOnlyContent"
                                }, {t: 4, n: 51, f: [{t: 2, r: "~/relaxTableRowsLabel"}], l: 1}],
                                t: 13
                            }, {n: "minitooltipRightAlign", f: "true", t: 13}, {
                                n: "rClick",
                                f: "relaxTableRows",
                                t: 13
                            }]
                        }, " ", {
                            t: 7,
                            e: "blueIconAction",
                            m: [{
                                n: "id",
                                f: ["condensedButton", {t: 4, f: ["-", {t: 2, r: ".viewName"}], n: 50, r: ".viewName"}],
                                t: 13
                            }, {n: "type", f: "info-density-condensed util", t: 13}, {
                                n: "classes",
                                f: ["info-density-button collapse-icon ", {
                                    t: 4,
                                    f: ["condensed"],
                                    r: ".tableRowsCompacted"
                                }, {t: 4, n: 51, f: ["relaxed"], l: 1}],
                                t: 13
                            }, {
                                n: "adatext",
                                f: [{
                                    t: 4,
                                    f: [{t: 2, r: ".crfCompactTableRowsAda"}],
                                    n: 50,
                                    r: ".widgetConfig.isEnglishOnlyContent"
                                }, {t: 4, n: 51, f: [{t: 2, r: ".compactTableRowsAda"}], l: 1}, {
                                    t: 4,
                                    f: [{
                                        t: 4,
                                        f: [{t: 2, r: "~/englishOnlyInformationDensityStatusAda"}],
                                        n: 50,
                                        r: ".widgetConfig.isEnglishOnlyContent"
                                    }, {t: 4, n: 51, f: [{t: 2, r: "~/informationDensityStatusAda"}], l: 1}],
                                    n: 50,
                                    r: ".tableRowsCompacted"
                                }],
                                t: 13
                            }, {
                                n: "minitooltiptext",
                                f: [{
                                    t: 4,
                                    f: [{t: 2, r: "~/englishOnlyCompactTableRowsLabel"}],
                                    n: 50,
                                    r: ".widgetConfig.isEnglishOnlyContent"
                                }, {t: 4, n: 51, f: [{t: 2, r: "~/compactTableRowsLabel"}], l: 1}],
                                t: 13
                            }, {n: "minitooltipRightAlign", f: "true", t: 13}, {
                                n: "rClick",
                                f: "compactTableRows",
                                t: 13
                            }]
                        }]
                    }], r: ".relaxOrCompactRowsVisible"
                }]
            }]
        }], e: {}
    }
})), define("common/analytics/data/hooks", ["require", "appkit-utilities/analytics/data/hooks"], (function (e) {
    "use strict";
    var t = e("appkit-utilities/analytics/data/hooks");
    return [{
        module: "common/utility/dynamicContentUtil",
        ref: "dynamicSettings",
        method: "get"
    }, {
        module: "common/utility/dynamicContentUtil",
        ref: "dynamicSettings",
        method: "set"
    }, {module: "common/utility/dynamicContentUtil", ref: "dynamicContent", method: "setForBinding"}].concat(t)
})), define("common/analytics/tagmanager", ["require", "blue-tags/main", "blue/is", "analytics/util/streamCollator"], (function (e) {
    "use strict";
    var t = e("blue-tags/main"), n = {}, i = e("blue/is");
    return function (o) {
        n = o.appConfig;
        var r = !i.defined(n.enableTagManager) || n.enableTagManager,
                a = i.defined(n.tagServerHost) ? n.tagServerHost : "https://wwwist2.dev.chase.com";
        e("analytics/util/streamCollator")(o.site, o.application, void 0, r, {
            webEvent: !0,
            screenEvent: !0
        }).then((function (e) {
            i.array(e) && e[1].onValue((function (e) {
                if (e.screen && "UNDEFINED_SCREEN_ID" !== e.screen.id) {
                    var n = e.screen.id;
                    n.indexOf("?") > -1 && (n = n.substring(0, n.indexOf("?"))), t.init(n, a)
                }
            }))
        }))
    }
})), define("common/auth/component/logon", [], (function () {
    "use strict";
    return {
        init: function () {
            var e = this;
            this.output.on("ready", (function () {
                e.input.on({
                    signoutTransitionComplete: function () {
                        e.context.bubble("logon:signoutTransitionComplete")
                    }
                })
            }))
        }, logonToLandingPage: function () {
        }, proceedToLogon: function () {
        }, forgotPassword: function () {
        }, enrollNewUser: function () {
        }
    }
})), define("bluespec/logon", [], (function () {
    return {
        name: "LOGON",
        data: {
            userId: {type: "Noop"},
            password: {type: "Noop"},
            rememberMyUserId: {type: "OnOff"},
            useRSAToken: {type: "OnOff"},
            securityToken: {type: "RSAToken"},
            secondarySecurityToken: {type: "RSAToken"},
            useTokenState: {type: "OnOff"},
            secondarySecurityTokenRequested: {type: "OnOff"}
        },
        actions: {
            logonToLandingPage: !0,
            proceedToLogon: !0,
            forgotPassword: !0,
            enrollNewUser: !0,
            requestPrivacyNotice: !0,
            userSignIn: !0,
            requestUseTokenHelpMessage: !0,
            useToken: !0,
            unsupportedBrowserRedirect: !0
        },
        states: {validationErrorDisplayed: !0, thirdPartyTokenRequested: !0},
        settings: {
            logoAda: !0,
            logonHeader: !0,
            welcomeHeader: !0,
            logoImage: !0,
            chaseLabel: !0,
            userIdPlaceholder: !0,
            passwordPlaceholder: !0,
            rememberMyUserIdLabel: !0,
            useRSATokenLabel: !0,
            securityTokenPlaceholder: !0,
            securityTokenError: !0,
            logonLabel: !0,
            forgotPasswordNavigation: !0,
            enrollNavigation: !0,
            logoffInProgressMessage: !0,
            logoffSuccessfulMessage: !0,
            proceedToLogonLabel: !0,
            userIdError: !0,
            passwordError: !0,
            logonErrorHeader: !0,
            logonErrorAdvisory: !0,
            errorAnnouncementAda: !0,
            importantAda: !0,
            rememberMyUserIdAda: !0,
            chaseLogoAda: !0,
            pageTitle: !0,
            useRSATokenAda: !0,
            useTokenLabel: !0,
            useTokenHelpMessage: !0,
            requestUseTokenHelpMessageAda: !0,
            secondarySecurityTokenLabel: !0
        }
    }
})), define("common/auth/controller/auth", ["require", "blue/observable", "bluespec/logon", "common/auth/component/logon"], (function (e) {
    "use strict";
    var t = e("blue/observable"), n = e("bluespec/logon"), i = e("common/auth/component/logon");
    return function (e) {
        var o, r = this, a = e.userInfoPromise;
        r.registerComponent = function (e) {
            r.register.components(r, [{
                name: "signout",
                model: t.Model({logoutURLs: e, servicePromise: null}),
                spec: n,
                methods: i
            }])
        }, r.init = function () {
            r.logoff.setAppContext(e.application), o = r.context.application.config, e.on({
                "logon:signoutTransitionComplete": function () {
                    r.logoff.removeUrlStorage(), r.model.get("servicePromise").then(r.navigateToDestination)
                }
            })
        }, r.integratedSignout = function (e) {
            var t = e && "session" === e.params;
            return r.registerComponent(r.logoff.getUrlStorage()), r.model.set("servicePromise", r.logoff.authSignout(t)), [r.components.signout, "auth/signout", {target: "#signoutModal"}]
        }, r.expressSignout = function (e) {
            var t = e && "session" === e.params;
            r.logoff.authSignout(t).then(r.navigateToDestination)
        }, r.standaloneSignout = function () {
            a.then((function () {
                r.logoff.partnerSignOutAndRedirect()
            }))
        }, r.navigateToDestination = function () {
            var e = r.logoff, t = o.enableAggregatorSignoff ? o.aggregatorSignoffUrl : e.destination;
            r.context.is.string(t) ? r.goURL(t) : r.context.application.broadcast("makeNavigation", t)
        }
    }
})), define("common/template/signout", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", m: [{n: "id", f: "signout-modal-parent", t: 13}, {n: "class", f: "", t: 13}], f: [{
                t: 7,
                e: "blueModal",
                m: [{n: "id", f: "signout-modal", t: 13}, {n: "customDialogLayout", f: "true", t: 13}, {
                    n: "classes",
                    f: "signout-modal-parent",
                    t: 13
                }],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "signout-container", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "indicator baseline", t: 13}, {n: "aria-hidden", f: "true", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "container-fluid", t: 13}],
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "message-container", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "h1",
                                    m: [{n: "class", f: "H3", t: 13}],
                                    f: [{t: 2, r: "logoffInProgressMessage"}]
                                }]
                            }]
                        }]
                    }, " ", {
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "indicator to-transition progression", t: 13}, {
                            n: "aria-hidden",
                            f: "true",
                            t: 13
                        }],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "container-fluid", t: 13}],
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "message-container", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "h1",
                                    m: [{n: "class", f: "H3", t: 13}],
                                    f: [{t: 2, r: "logoffInProgressMessage"}]
                                }]
                            }]
                        }]
                    }, " ", {
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "indicator to-transition overlapped-text", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "container-fluid", t: 13}],
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "message-container", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "h1",
                                    m: [{n: "class", f: "H3", t: 13}, {n: "tabindex", f: "-1", t: 13}],
                                    f: [{t: 2, r: "logoffInProgressMessage"}]
                                }]
                            }]
                        }]
                    }]
                }, " ", {
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "row header", t: 13}],
                    f: [{t: 7, e: "div", m: [{n: "class", f: "header__black-linear-bg", t: 13}]}, " ", {
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "header__white-bg", t: 13}]
                    }, " ", {
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "col-xs-6 col-sm-4 col-sm-offset-4 col-xs-offset-3 chase-logo", t: 13}],
                        f: [{
                            t: 7,
                            e: "h2",
                            m: [{n: "class", f: "util accessible-text", t: 13}],
                            f: [{t: 2, r: "chaseLogoAda"}]
                        }, " ", {
                            t: 7, e: "div", m: [{n: "class", f: "logo-svg", t: 13}], f: [{
                                t: 7,
                                e: "svg",
                                m: [{n: "x", f: "0", t: 13}, {n: "y", f: "0", t: 13}, {
                                    n: "xmlns",
                                    f: "http://www.w3.org/2000/svg",
                                    t: 13
                                }, {n: "viewBox", f: "0 0 273 50", t: 13}, {n: "xml:space", f: "preserve", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "path",
                                    m: [{n: "id", f: "chase_logo", t: 13}, {n: "fill", f: "#ffffff", t: 13}, {
                                        n: "d",
                                        f: "m166.444443,6.100021c-0.199982,0.299988 -0.199982,0.5 -0.299988,0.600006c-1.200012,1.899994 -2.399994,3.799988 -3.600006,5.699982c-0.200012,0.400024 -0.5,0.5 -1,0.5c-6.399994,0 -12.899994,0 -19.299988,0c-1.700012,0 -2.400024,0.600006 -2.5,2.300018c-0.100006,1.199982 0,2.399994 0.099976,3.600006c0.100006,1.5 1,2.099976 2.400024,2.099976c5.099976,0 10.099976,0 15.199982,0c1.300018,0 2.600006,0.200012 3.899994,0.600006c3.100006,0.800018 4.900024,3 5.600006,6.100006c0.5,2.399994 0.5,4.899994 0.399994,7.399994c-0.099976,1.100006 -0.299988,2.300018 -0.599976,3.399994c-0.900024,3.5 -3.300018,5.5 -6.800018,6.100006c-1,0.200012 -2.100006,0.300018 -3.199982,0.300018c-8.100006,0 -16.200012,0 -24.300018,0c-0.199982,0 -0.5,0 -0.799988,-0.100006c0.199982,-0.300018 0.299988,-0.5 0.399994,-0.600006c1.200012,-1.899994 2.399994,-3.799988 3.600006,-5.700012c0.299988,-0.399994 0.600006,-0.599976 1.100006,-0.599976c6.799988,0 13.5,0 20.299988,0c1.600006,0 2.5,-0.600006 2.600006,-2.200012c0.199982,-1.899994 0.100006,-3.700012 0,-5.600006c-0.100006,-1.399994 -1,-2 -2.5,-2c-5.5,0 -11,0 -16.600006,0c-1.100006,0 -2.299988,-0.199982 -3.399994,-0.600006c-2.300018,-0.699982 -3.700012,-2.299988 -4.399994,-4.5c-1.300018,-4 -1.200012,-8.099976 0.299988,-12c1.200012,-3 3.5,-4.5 6.700012,-4.699982c1,-0.100006 2.099976,-0.100006 3.199982,-0.100006c7.600006,0 15.200012,0 22.799988,0c0.100006,0 0.300018,0 0.700012,0zm42.199982,38.599976c-0.299988,0 -0.5,0.100006 -0.700012,0.100006c-10.899994,0 -21.799988,0 -32.699982,0c-0.800018,0 -0.800018,0 -0.800018,-0.800018c0,-12.399994 0,-24.799988 0,-37.099976c0,-0.700012 0,-0.700012 0.700012,-0.700012c10.600006,0 21.299988,0 31.899994,0c0.200012,0 0.399994,0 0.700012,0c-0.100006,0.200012 -0.200012,0.299988 -0.200012,0.5c-1.199982,1.899994 -2.399994,3.799988 -3.600006,5.799988c-0.199982,0.400024 -0.5,0.5 -0.899994,0.5c-6.600006,0 -13.199982,0 -19.899994,0c-0.799988,0 -0.799988,0 -0.799988,0.800018c0,2.299988 0,4.5 0,6.799988c0,0.700012 0,0.700012 0.699982,0.700012c6.399994,0 12.700012,0 19.100006,0c0.799988,0 0.799988,0 0.799988,0.799988c0,1.700012 0,3.399994 0,5.100006c0,0.899994 0,0.899994 -0.899994,0.899994c-6.199982,0 -12.5,0 -18.699982,0c-0.200012,0 -0.300018,0 -0.5,0c-0.5,-0.100006 -0.600006,0.100006 -0.600006,0.600006c0,2.700012 0,5.299988 0,8c0,1.299988 0,1.299988 1.299988,1.299988c6.600006,0 13.200012,0 19.899994,0c0.5,0 0.800018,0.100006 1,0.5c1.200012,1.900024 2.5,3.900024 3.800018,5.800018c0.100006,0 0.200012,0.200012 0.399994,0.399994zm-128.29998,-19.199982c0,6.200012 0,12.300018 0,18.5c0,0.800018 0,0.800018 -0.799988,0.800018c-2.100006,0 -4.300018,0 -6.400024,0c-0.5,0 -0.699982,-0.200012 -0.699982,-0.700012c0,-5.200012 0,-10.299988 0,-15.5c0,-0.700012 0,-0.700012 -0.700012,-0.700012c-6.399994,0 -12.799988,0 -19.199982,0c-0.5,0 -0.700012,0.100006 -0.700012,0.700012c0,5.100006 0,10.299988 0,15.399994c0,0.899994 0,0.899994 -0.899994,0.899994c-2.100006,0 -4.100006,0 -6.200012,0c-0.799988,0 -0.799988,0 -0.799988,-0.799988c0,-9.399994 0,-18.799988 0,-28.200012c0,-3 0,-6 0,-8.899994c0,-0.699982 0,-0.699982 0.699982,-0.699982c2.100006,0 4.300018,0 6.400024,0c0.5,0 0.699982,0.199982 0.699982,0.699982c0,4.5 0,9 0,13.399994c0,0.700012 0,0.700012 0.700012,0.700012c6.399994,0 12.799988,0 19.199982,0c0.700012,0 0.700012,0 0.700012,-0.800018c0,-4.399994 0,-8.899994 0,-13.299988c0,-0.800018 0,-0.800018 0.700012,-0.800018c2.099976,0 4.299988,0 6.399994,0c0.5,0 0.699982,0.100006 0.699982,0.700012c0.200012,6.200012 0.200012,12.399994 0.200012,18.600006zm4.100006,19.100006c0.100006,-0.299988 0.200012,-0.5 0.300018,-0.700012c4.399994,-9.299988 8.799988,-18.599976 13.199982,-27.899994c1.5,-3.100006 3,-6.299988 4.5,-9.399994c0.200012,-0.399994 0.399994,-0.600006 0.899994,-0.600006c2.200012,0 4.400024,0 6.5,0c0.5,0 0.700012,0.200012 0.900024,0.600006c4.599976,9.799988 9.199982,19.5 13.899994,29.299988c1.299988,2.700012 2.600006,5.400024 3.79998,8.100006c0.100006,0.200012 0.100006,0.300018 0.200012,0.600006c-0.300018,0 -0.399994,0.100006 -0.600006,0.100006c-2.499992,0 -4.999992,0 -7.599998,0c-0.5,0 -0.699982,-0.200012 -0.799988,-0.600006c-1,-2.299988 -2.100006,-4.600006 -3.100006,-7c-0.200012,-0.399994 -0.399994,-0.5 -0.799988,-0.5c-6.200012,0 -12.300018,0 -18.5,0c-0.400024,0 -0.600006,0.100006 -0.800018,0.5c-1,2.299988 -2.100006,4.600006 -3.100006,7c-0.199982,0.399994 -0.399994,0.600006 -0.899994,0.600006c-2.399994,0 -4.899994,0 -7.299988,0c-0.200012,-0.000031 -0.400024,-0.000031 -0.700012,-0.100006zm28.600006,-15.100006c-2.200012,-5.100006 -4.399994,-10 -6.600006,-15c-2.299988,5.100006 -4.5,10.100006 -6.699982,15c4.399963,0 8.799988,0 13.299988,0zm-74.200012,15.199982c-0.299988,0 -0.5,0.100006 -0.700012,0.100006c-8,0 -15.999985,0 -24.099992,0c-1.800003,0 -3.5,-0.200012 -5.199997,-0.899994c-3.5,-1.300018 -5.5,-4 -6.300003,-7.600006c-0.300003,-1.5 -0.5,-3.100006 -0.5,-4.600006c-0.099991,-4 0,-8 0,-12c0,-2.100006 0.199997,-4.200012 0.900009,-6.200012c1.399994,-4.199982 4.399994,-6.5 8.799988,-7.099976c1.200012,-0.200012 2.400009,-0.200012 3.600006,-0.200012c7.500001,0 15.000001,0 22.500001,0c0.299988,0 0.5,0 0.899994,0c-0.100006,0.299988 -0.199982,0.399994 -0.299988,0.600006c-1.200012,1.899994 -2.5,3.899994 -3.700012,5.799988c-0.299988,0.399994 -0.5,0.600006 -1,0.600006c-6.100006,0 -12.199997,0 -18.299989,0c-0.600006,0 -1.300003,0 -1.900009,0.200012c-1.899994,0.399994 -2.800003,1.799988 -3.199997,3.699982c-0.100006,0.800018 -0.199997,1.700012 -0.199997,2.5c0,3.899994 0,7.800018 0,11.700012c0,1.100006 0.199997,2.299988 0.399994,3.399994c0.5,1.899994 1.900009,3 3.800003,3c3.300004,0.100006 6.600007,0 9.899995,0.100006c3.200012,0 6.399994,0 9.5,0.100006c0.200012,0 0.5,0.099976 0.700012,0.299988c1.399994,2.100006 2.799988,4.299988 4.199982,6.399994c0.100006,-0.199982 0.200012,-0.099976 0.200012,0.100006zm197.200005,-43.499985c0,0.300003 0.100006,0.5 0.100006,0.699997c0,10.5 0,21.100006 0,31.600006c0,0.5 -0.100006,0.699982 -0.700012,0.699982c-3.899994,0 -7.799988,0 -11.699982,0c-1.300018,0 -2,-0.699982 -2,-2c0,-5.199982 0,-10.299988 0,-15.5c0,-0.299988 0.199982,-0.699982 0.399994,-0.899994c2.899994,-3.100006 5.799988,-6.100006 8.699982,-9.199982c1.600006,-1.600006 3.100006,-3.300018 4.700012,-4.900009c0.099976,-0.199997 0.299988,-0.300003 0.5,-0.5zm-12.900024,34.999985c0.200012,0 0.399994,0 0.600006,0c10.5,0 21,0 31.5,0c0.800003,0 0.800003,0 0.800003,0.799988c0,3.800018 0,7.600006 0,11.400024c0,1.399994 -0.699997,2.099976 -2.100021,2.099976c-5.099976,0 -10.199982,0 -15.199982,0c-0.399994,0 -0.800018,-0.199982 -1.100006,-0.399994c-4.799988,-4.5 -9.600006,-9.100006 -14.299988,-13.600006c-0.100006,-0.099976 -0.200012,-0.099976 -0.300018,-0.199982c0.000031,0 0.000031,-0.100006 0.100006,-0.100006zm47.800034,-21.899994c-0.199982,0 -0.399994,0 -0.600006,0c-10.5,0 -21.000015,0 -31.500015,0c-0.799988,0 -0.799988,0 -0.799988,-0.800018c0,-3.799988 0,-7.599976 0,-11.399979c0,-1.400009 0.700012,-2.100006 2.100006,-2.100006c5.100006,0 10.199982,0 15.299988,0c0.399994,0 0.700027,0.100006 1.000015,0.399994c3.399994,3.200012 6.700012,6.400009 10.100006,9.599991c1.299988,1.300018 2.700012,2.5 4,3.800018c0.100006,0.100006 0.299988,0.199982 0.5,0.299988c0,0.000031 -0.100006,0.100006 -0.100006,0.200012zm-12.899994,34.800018c0,-0.200012 0,-0.399994 0,-0.600006c0,-10.5 0,-21 0,-31.5c0,-0.799988 0,-0.799988 0.799988,-0.799988c3.800018,0 7.700012,0 11.5,0c1.400024,0 2.100006,0.699982 2.100006,2.100006c0,5.099976 0,10.199982 0,15.299988c0,0.399994 -0.199982,0.799988 -0.399994,1.100006c-2.5,2.699982 -5,5.299988 -7.600006,8c-1.899994,2 -3.899994,4.100006 -5.799988,6.100006c-0.100006,0.099976 -0.200012,0.299988 -0.300018,0.5c-0.200012,-0.100037 -0.299988,-0.100037 -0.299988,-0.200012z",
                                        t: 13
                                    }]
                                }]
                            }]
                        }]
                    }]
                }, " ", {
                    t: 7,
                    e: "div",
                    f: [{
                        t: 4,
                        f: [{
                            t: 7,
                            e: "img",
                            m: [{n: "width", f: "1", t: 13}, {n: "height", f: "1", t: 13}, {
                                n: "src",
                                f: [{t: 3, x: {r: ["sanitizer", "."], s: "_0.sanitizeHTML(_1)"}}],
                                t: 13
                            }]
                        }],
                        n: 52,
                        r: "logoutURLs"
                    }]
                }]
            }]
        }], e: {}
    }
})), define("common/auth/view/webspec/signout", {
    name: "LOGON",
    bindings: {},
    triggers: {}
}), define("common/auth/view/signoutMixin", ["require", "blue/is", "blue/device/platform", "common/template/signout", "common/auth/view/webspec/signout", "blue-ui/view/modules/modal"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("blue/device/platform");
    return function () {
        var i = this;
        this.viewName = "signoutView", this.viewId = "signoutView", this.template = e("common/template/signout"), this.bridge = e("common/auth/view/webspec/signout"), this.views = {blueModal: e("blue-ui/view/modules/modal")}, this.init = function () {
            this.bridge.on("ready", (function () {
                i.onReady()
            }))
        }, this.onReady = function () {
            var e = $(".to-transition"), t = 0;
            if ($(".overlapped-text h1").length > 0 && $(".overlapped-text h1").focus(), e.length > 0) {
                var o = n.name;
                "IE" === o && (t = 200), "Safari" === o && setTimeout((function () {
                    i.signoutTransitionPass || i.bridge.output.emit("signoutTransitionComplete", {})
                }), 600), i.transitionHasEnded(e.get(0), (function () {
                    i.signoutTransitionPass = !0, i.bridge.output.emit("signoutTransitionComplete", {})
                })), setTimeout((function () {
                    e.addClass("transition-in-progress")
                }), t)
            }
        }, this.whichTransitionEvent = function () {
            var e = document.createElement("testelem"), n = {
                transition: "transitionend",
                OTransition: "oTransitionEnd",
                MozTransition: "transitionend",
                WebkitTransition: "webkitTransitionEnd"
            };
            for (var i in n) if (n.hasOwnProperty(i) && t.defined(e.style[i])) return n[i]
        }, this.transitionHasEnded = function (e, t) {
            var n = this.whichTransitionEvent();
            n && e.addEventListener(n, t)
        }
    }
})), define("common/component/modal", ["require", "blue/$"], (function (e) {
    "use strict";
    var t = e("blue/$");
    return {
        show: function () {
            t("body").css("overflow", "hidden"), this.$modal = t("#" + this.model.get("modalId")).show()
        }, hide: function () {
            t("body").css("overflow", "auto"), t("body").removeClass("no-scroll util"), this.$modal.remove()
        }, focusModalAPI: function (e) {
            this.output.emit("state", {value: "focusModalAPI", id: e})
        }
    }
})), define("common/lib/constants", [], (function () {
    "use strict";
    var e = {
        MONTH: {
            "01": "Jan",
            "02": "Feb",
            "03": "Mar",
            "04": "Apr",
            "05": "May",
            "06": "Jun",
            "07": "Jul",
            "08": "Aug",
            "09": "Sep",
            10: "Oct",
            11: "Nov",
            12: "Dec",
            1: "Jan",
            2: "Feb",
            3: "Mar",
            4: "Apr",
            5: "May",
            6: "Jun",
            7: "Jul",
            8: "Aug",
            9: "Sep"
        },
        monthArray: ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"],
        CPO_LANDING_PAGE: "PERSONAL_OVERVIEW",
        CBO_LANDING_PAGE: "BUSINESS_OVERVIEW",
        APP: {THIRD_PARTY_ACCESS: "thirdpartyaccess", DASHBOARD: "dashboard"},
        HYBRID_EXTERNAL_PARAMETER: "#externalwindow",
        MASK_DOTS: "...",
        SELECT_ACCOUNT_STATUS: {
            SYSTEM_FAILURE: "SYSTEM_FAILURE",
            FLEXCREDIT: "FLEX_CREDIT_ACCOUNT",
            RECENTLY_OPENED_ACCOUNTS: "EARLY_MONTH_ON_BOOKS_CARD",
            NO_ELIGIBLE_ACCOUNTS_FOR_FLEX: "NO_ELIGIBLE_ACCOUNTS",
            NO_ELIGIBLE_ACCOUNTS_FOR_EMOB: "NO_ELIGIBLE_ACCOUNTS_EARLY_MONTH_ON_BOOKS"
        },
        detailTypeReplace: {HLM: "HEL", RCM: "RCA", HEM: "HEO", ILM: "ILA"},
        DETAIL_TYPE: {
            ABLE: "ABL",
            ANNUITY: "ANU",
            ASSET_MANAGEMENT: "AMA",
            AUTO: "AUTO",
            AUTO_LEASE: "ALS",
            AUTO_LOAN: "ALA",
            BUSINESS_CREDIT_CARD: "BCC",
            BUSINESS_LOAN_ACCOUNT: "BLA",
            BUSINESS_REVOLVING_CREDIT: "BRC",
            BROKERAGE: "BRK",
            BROKERAGE2: "BR2",
            CERTIFICATE_OF_DEPOSIT: "CDA",
            CHASE_FLEX_CARD: "CSL",
            CHECKING: "CHK",
            COMMERCIAL_LOAN_FACILITY: "CCF",
            COMMERCIAL_TERM_LOAN: "CTL",
            COMMERCIAL_LOAN_OBLIGATION: "CCO",
            CONSUMER_CREDIT_CARD: "BAC",
            CREDIT_REVOLVING_FACILITY: "CRF",
            DDA: "DDA",
            GRAMMAR: "GMR",
            HMG: "HMG",
            HOME_MORTGAGE: "HMG",
            HOME_EQUITY_LINE: "HEL",
            HOME_EQUITY_LOAN: "HEO",
            HOME_EQUITY_MORTGAGE: "HEM",
            HOME_LOAN_MORTGAGE: "HLM",
            INDIVIDUAL_RETIREMENT_ARRANGMENT: "IRA",
            INSTALLMENT_LOAN: "ILA",
            INSTALLMENT_LOAN_MORTGAGE: "ILM",
            INVESTMENT_MANAGEMENT: "MAN",
            JPMORGAN_FUND: "JPF",
            LINE_OF_CREDIT: "CRF",
            LIQUID: "PPC",
            MANAGED: "WR2",
            MARGIN: "MAR",
            MERCHANT: "MSA",
            MERCHANT_SERVICE_ACCOUNT: "MSA",
            MONEY_MARKET: "MMA",
            MUTUAL: "MUT",
            OFF: "OFF",
            OVERDRAFT_LINE_OF_CREDIT: "OLC",
            PPG: "PPG",
            PREPAID: "PPA",
            PREPAID_LITE: "PPX",
            PRIVATE_LABEL_CONSUMER: "PAC",
            REVOLVING_CREDIT: "RCA",
            REVOLVING_CREDIT_MORTGAGE: "RCM",
            SAVINGS: "SAV",
            SPEND_FOCUS_CARD: "SCC",
            STUDENT_LOAN: "SLA",
            WPY: "WPY"
        },
        ACCOUNT_TYPE: {
            AUTOLEASE: "AUTOLEASE",
            AUTOLOAN: "AUTOLOAN",
            BANKING: "DEPOSIT",
            BLA: "BLA",
            BUSINESS_CREDIT_CARD: "BCC",
            CARD: "CARD",
            CERTIFICATE_OF_DEPOSIT: "CDA",
            CHASE_FLEX_CARD: "CHASE_FLEX_CARD",
            COMMERCIALLOAN: "LOAN",
            COMMERCIAL_LOAN_FACILITY: "LOAN",
            COMMERCIAL_LOAN_OBLIGATION: "LOAN",
            COMMERCIAL_TERM_LOAN: "LOAN",
            CHARITABLE_FUND: "CHARITABLE_FUND",
            CHARITABLE_FUNDS: "CHARITABLE_FUNDS",
            CREDIT_CARD: "CREDIT_CARD",
            CREDIT_SCORE_INFORMATION: "CREDIT_SCORE_INFORMATION",
            DAF: "DAF",
            DDA: "DDA",
            DEPOSIT: "DDA",
            INVESTMENT: "INVESTMENT",
            LIABILITY: "LIABILITY",
            LOAN: "LOAN",
            MERCHANT: "MERCHANT",
            MORTGAGE: "MORTGAGE",
            OTHER: "OTHER",
            OTHER_ASSETS: "OTHER_ASSETS",
            PREPAID: "PrePaid",
            ULTIMATE_REWARDS: "ULTIMATE_REWARDS",
            SLA: "SLA"
        },
        PRICINGSEGMENT: {CHECK_OLD: 84, NOPRICINGPACKAGE: 12, PREMIUMPLUS: 24, PREMIUM: 6, STANDARD: 4},
        PRODUCT_DESCRIPTION: {
            INDEX: "index",
            DDA: "dda",
            CHK: "dda",
            SAV: "dda",
            MMA: "dda",
            MSA: "msa",
            BLA: "bla",
            PPA: "prepaid",
            PPX: "prepaid",
            PPG: "prepaid",
            CDA: "cds",
            IRA: "ira",
            CARD: "creditCard",
            CSL: "creditCard",
            BAC: "creditCard",
            PAC: "creditCard",
            OLC: "creditCard",
            BCC: "businessCard",
            LOAN: "loan",
            ILA: "ila",
            ILM: "ilm",
            RCA: "rca",
            RCM: "rcm",
            HEO: "homeEquityLoan",
            HEM: "homeEquityLoan",
            HLM: "homeEquityLineOfCredit",
            HEL: "homeEquityLineOfCredit",
            MORTGAGE: "homeMortgage",
            HMG: "homeMortgage",
            INVESTMENT: "investment",
            ALS: "autoLease",
            ALA: "autoLoan",
            SLA: "studentLoan",
            BRC: "businessLoan",
            CCO: "loanObligation",
            CCF: "loanFacility",
            CTL: "commercialTermLoan",
            CRF: "creditFacility"
        },
        TRANTYPE_CODES: {
            ACCT_TRANSFERS: "ACCT_XFERS",
            ACH_CREDITS: "ACH_CREDITS",
            ACH_DEBITS: "ACH_DEBITS",
            ADJUSTMENT_REVERSALS: "ADJUSTMT_REVERSALS",
            ALL: "ALL",
            ALL_DEBIT: "ALL_DEBIT",
            ALL_TRANSACTION: "ALL_TRANSACTION",
            ATMS: "ATMS",
            BILL_PAYS: "BILLPAYS",
            CHECK_OLD: "CHECK_OLD",
            CHECK_WITHDRAWS: "CHECK_WITHDRAWS",
            CREDITS: "CREDITS",
            DEBITS: "DEBITS",
            DEBIT_CARDS: "DEBIT_CARDS",
            DEPOSITS: "DEPOSITS",
            DEPOSIT_RETURNS: "DEPOSIT_RETURNS",
            FEE_TRANSACTION: "FEE_TRANSACTION",
            LOAN_PMTS: "LOAN_PMTS",
            MISC_CREDITS: "MISC_CREDITS",
            MISC_DEBITS: "MISC_DEBITS",
            QUICKPAY_CREDITS: "QUICKPAY_CREDITS",
            QUICKPAY_DEBITS: "QUICKPAY_DEBITS",
            OVERNIGHT_CHECKS: "OVERNIGHT_CHECKS",
            REFUND_TRANSACTION: "REFUND_TRANSACTION",
            WIRE_INCOMINGS: "WIRE_INCOMINGS",
            WIRE_OUTGOINGS: "WIRE_OUTGOINGS"
        },
        REWARD_TYPE: {
            AARP: "AARP",
            AMAZON: "AMAZON",
            AMAZON_REWARDS_VISA: "AMAZON",
            AMAZON_PRIME: "AMAZON_PRIME",
            AIRFORCE_CLUB: "AIRFORCE_CLUB",
            ARMY_MWR: "ARMY_MWR",
            BIZ_CARD_FLEXIBLE: "BIZ_CARD_FLEXIBLE",
            CAPITAL_CARD: "CAPITAL_CARD",
            CARD: "CARD",
            CHASE_INK_BUSINESS_PREFERRED: "CHASE_INK_BUSINESS_PREFERRED",
            CHASE_INK_BUSINESS_PREFERRED_CORP: "CHASE_INK_BUSINESS_PREFERRED_CORP",
            CHASE_LOYALTY: "CHASE_LOYALTY",
            CHASE_SLATE: "CHASE_SLATE",
            CHASE_SAPPHIRE: "CHASE_SAPPHIRE",
            CHASE_SAPPHIRE_PREFERRED: "CHASE_SAPPHIRE_PREFERRED",
            CHASE_MARSHALL: "CHASE_MARSHALL",
            CORPORATE_FLEX_CARD: "CORPORATE_FLEX_CARD",
            DEFAULT: "DEFAULT",
            DISNEY: "DISNEY",
            DISNEY_DREAM_REWARDS_DOLLARS: "DOLLAR",
            FAIRMONT: "FAIRMONT",
            FLEXIBLE_REWARDS_SELECT: "FLEXIBLE_REWARDS_SELECT",
            FREEDOM_CARD: "FREEDOM_CARD",
            FREEDOM_PLATINUM: "FREEDOM_PLATINUM",
            FREEDOM_SIGNATURE: "FREEDOM_SIGNATURE",
            FREEDOM_UNLIMITED: "FREEDOM_UNLIMITED",
            FREEDOM_UNLIMITED_POINTS: "FREEDOM_UNLIMITED_POINTS",
            JPMORGAN: "JPMORGAN",
            JPMORGAN_PRIVATE_BANK: "JPMORGAN_PRIVATE_BANK",
            JPM_MARSHALL: "JPM_MARSHALL",
            INK: "INK",
            INK_CAPITAL: "INK_CAPITAL",
            INK_BOLD: "INK_BOLD",
            INK_BOLD_521: "INK_BOLD_521",
            INK_BOLD_CORPORATE: "INK_BOLD_CORPORATE",
            INK_BOLD_EXCLUSIVES: "INK_BOLD_EXCLUSIVES",
            INK_CASH: "INK_CASH",
            INK_CASH_521: "INK_CASH_521",
            INK_EXCLUSIVES: "INK_EXCLUSIVES",
            INK_PLUS: "INK_PLUS",
            INK_PLUS_521: "INK_PLUS_521",
            INK_PLUS_CORPORATE: "INK_PLUS_CORPORATE",
            INK_PLUS_521_CORP: "INK_PLUS_521_CORP",
            INK_521: "INK_521",
            LIVING_SOCIAL: "LIVING_SOCIAL",
            MBAPPE_CARD: "MBAPPE_CARD",
            MARY_KAY: "MARY_KAY",
            MILITARY: "MILITARY",
            MILITARY_STAR: "MILITARY_STAR",
            REWARDS: "REWARDS",
            RITZ_CARLTON: "RITZ_CARLTON",
            STARBUCKS: "STARBUCKS",
            TRAVELPLUS_PREM_$29: "TRAVELPLUS_PREM_$29",
            ULTIMATE: "ULTIMATE",
            ZAPPOS: "ZAPPOS"
        },
        SEGMENT: {
            BUSINESSBANKING: "BB",
            COMMERCIAL: {CML: "CML", CRE: "CRE"},
            BB: {BMG: "BMG", BOH: "BOH", BPL: "BPL"}
        },
        VALIDATION_ERRORS: {
            ABOVE_MAXIMUM: "ABOVE_MAXIMUM",
            AMOUNT: "Amount",
            AMT_LESS_THAN_TRILLION: "BELOW_MAXIMUM",
            AMT_MORE_THAN_TRILLION: "ABOVE_MAXIMUM",
            AUTO_INVALID_DATE_RANGE: "INVALID_DATE_RANGE",
            BELOW_MINIMUM: "BELOW_MINIMUM",
            CHECK: "Check",
            DATE_BLANK: "dateBlank",
            DATE: "Date",
            ENTER_NUMBERS_ONLY: "ENTER_NUMBERS_ONLY",
            INVALID_LENGTH: "INVALID_LENGTH",
            EXCEEDING_11_DIGITS: "INVALID_LENGTH",
            FUTURE_DATE_ERROR: "FUTURE_DATE_ERROR",
            FUTURE_DATE_FOUR_MONTHS: "FUTURE_DATE_FOUR_MONTHS",
            FUTURE_DATE_FIVE_MONTHS: "FUTURE_DATE_FIVE_MONTHS",
            FUTURE_DATE_SIX_MONTHS: "FUTURE_DATE_SIX_MONTHS",
            FUTURE_DATE_TWENTY_FOUR_MONTHS: "FUTURE_DATE_TWENTY_FOUR_MONTHS",
            FUTURE_DATE: "OLD_FILTER_DATE",
            INVALID_AMOUNT_RANGE: "FILTER_AMOUNT_ERROR",
            INVALID_AMOUNT: "INVALID_AMOUNT",
            INVALID_CARD_NUMBER: "INVALID_AMOUNT",
            INVALID_CHARACTER: "INVALID_CHARACTER",
            INVALID_CHECK_RANGE: "ABOVE_MAXIMUM",
            INVALID_CHECK: "INVALID_CHECK_NUMBER",
            INVALID_DATE_COMPARISON: "INVALID_DATE_COMPARISON",
            INVALID_DATE_FORMAT: "INVALID_DATE_FORMAT",
            INVALID_DATE_RANGE_2: "INVALID_DATE_RANGE",
            INVALID_DATE_RANGE: "FILTER_DATE_ERROR",
            INVALID_DATE: "INVALID_DATE",
            INVALID_FORMAT: "INVALID_FORMAT",
            INVALID_FROM_CURRENT_DATE_RANGE: "fromDateGreaterThanCurrentDate",
            INVALID_FROM_DATE_RANGE: "FILTER_DATE_ERROR",
            INVALID_TO_CURRENT_RANGE: "toDateGreaterThanCurrentDate",
            INVALID_TO_DATE_RANGE: "toDateLessThanFromDate",
            INVALID_CUSIP: "CUSIP",
            INVALID_TICKER_SYMBOL: "TICKER",
            LAST_TWENTY_FOUR_MONTHS: "LAST_TWENTY_FOUR_MONTHS",
            MISSING_MANDATORY_DATA: "MISSING_MANDATORY_DATA",
            NO_FROM_AMOUNT: "MISSING_MANDATORY_DATA",
            NO_FROM_CHECK: "MISSING_MANDATORY_DATA",
            NO_FROM_DATE: "MISSING_MANDATORY_DATA",
            NOT_AVAILABLE: "NOT_AVAILABLE",
            OLDER_THAN_24_MONTHS: "OLD_FILTER_DATE",
            OLDER_THAN_48_MONTHS: "dateOlderThan48Months",
            OLDER_THAN_5_MONTHS: "OLD_FILTER_DATE",
            PAST_DATE_FIVE_MONTHS: "PAST_DATE_FIVE_MONTHS",
            PAST_DATE_FOUR_MONTHS: "PAST_DATE_FOUR_MONTHS",
            PAST_DATE_TWENTY_FOUR_MONTHS: "PAST_DATE_TWENTY_FOUR_MONTHS",
            PAST_DATE_RANGE_TWENTY_FOUR_MONTHS: "PAST_DATE_RANGE_TWENTY_FOUR_MONTHS"
        },
        DATERANGEHELPMESSAGE: {
            LAST_FOUR_MONTHS: "LAST_FOUR_MONTHS",
            LAST_SEVEN_YEARS: "LAST_SEVEN_YEARS",
            LAST_SIX_MONTHS: "LAST_SIX_MONTHS",
            LAST_TWELVE_MONTHS: "LAST_TWELVE_MONTHS",
            LAST_TWENTY_FOUR_MONTHS: "LAST_TWENTY_FOUR_MONTHS"
        },
        DATE_FORMATS: {YYYYMMDD: "YYYY-MM-DD", MMDDYYYY: "MM/DD/YYYY", yearMonthDateFormat: "YYYYMMDD"},
        VARIANCE_TYPE: {
            BUSINESS: "BUSINESS",
            CHASE_FLEX_CARD: "FLEX_CREDIT_ACCOUNT",
            NOT_SAPPHIRE_PREFERRED: "NOT_CHASE_SAPPHIRE_PREFERRED_CARD",
            SAPPHIRE_PREFERRED: "CHASE_SAPPHIRE_PREFERRED_CARD"
        },
        SYMBOLS: {
            CLASSIC_SPACE: "&#32;",
            COMMA: ",",
            COPYRIGHT: "Â©",
            CLOSEPARENTHESES: ")",
            DOLLAR: "$",
            DOUBLEHYPHEN: "--",
            EMPTY_STRING: "",
            ENDASH: "â€“",
            HTML_SPACE: "&nbsp;",
            HTML_BREAK: "<br>",
            HYPHEN: "-",
            MINUS_SIGN: "âˆ’",
            OPENPARENTHESES: "(",
            PERCENT: "%",
            PERIOD: ".",
            PLUS: "+",
            POUND: "#",
            SLASH: "/",
            SPACE: " ",
            UNDERSCORE: "_"
        },
        MASK_X: "x",
        NUMBER_ZERO: "0",
        PAST_DUE_1_TO_60_DAYS_CLOSED: "PAST_DUE_1_TO_60_DAYS_CLOSED",
        PAST_DUE_61_TO_120_DAYS_CLOSED: "PAST_DUE_61_TO_120_DAYS_CLOSED",
        PAST_DUE_121_TO_210_DAYS_CLOSED: "PAST_DUE_121_TO_210_DAYS_CLOSED",
        DUE_IN_DAYS: "DUE_IN_DAYS",
        DUE_TODAY: "DUE_TODAY",
        LATE: "LATE",
        PAST_DUE: "PAST_DUE",
        PAST_DUE_1_TO_30_DAYS_OPEN: "PAST_DUE_1_TO_30_DAYS_OPEN",
        PAST_DUE_31_TO_60_DAYS_OPEN: "PAST_DUE_31_TO_60_DAYS_OPEN",
        AFTER_DUE_BEFORE_CYCLE: "AFTER_DUE_BEFORE_CYCLE",
        JUMBO_ACCOUNT_DETAIL_TYPES_ORDER: ["BAC", "BCC", "CHK", "PPG", "PPX"],
        EXCLUDED_BUSINESS_CARD_TYPES: ["EMPLOYEE", "OWNER", "SUBACCOUNT"],
        MERCHANT_FUNDED_OFFERS_VALID_DETAIL_TYPES: ["BAC", "BCC", "CHK", "PPG", "PPX"],
        BCC_CONTROL_CARD_NAME: "BUSINESS CARD",
        NO_ACCOUNT_ACTIVITY: "NO_ACCOUNT_ACTIVITY",
        ACCOUNT_BRAND_ID: {FINN: "GOLDFISH"},
        CARDS_LOGO_ADA: {
            AARP: "AARP",
            AAFES: "AAFES_MILITARY_STAR_REWARDS",
            AIRFORCE_CLUB: "MILITARY_FREE_CASH_REWARDS",
            AMAZON: "AMAZON",
            AMAZON_PRIME: "AMAZON_PRIME",
            ARMY_AND_AIR_FORCE_EXCHANGE_SERVICE: "ARMY_AND_AIR_FORCE_EXCHANGE_SERVICE_REWARDS",
            ARMY_MWR: "MILITARY_FREE_CASH_REWARDS",
            AER_LINGUS_AVIOS: "AER_LINGUS_AVIOS",
            BRITISH_AIRWAYS: "BRITISH_AIRWAYS_AVIOS_REWARDS",
            CHASE_FLEX_CARD: "FLEX_CREDIT_CARD_ACCOUNT",
            CHASE_FLEXIBLE: "FLEX_CREDIT_CARD_ACCOUNT",
            CHASE_INK_BUSINESS_PREFERRED: "INK_REWARDS",
            CHASE_SAPPHIRE: "SAPPHIRE",
            CHASE_SAPPHIRE_PREFERRED: "SAPPHIRE_PREFERRED",
            CHASE_SLATE: "SLATE",
            DISNEY: "DISNEY_REWARDS",
            FAIRMONT_HOTELS_AND_RESORTS: "FAIRMONT_REWARDS",
            FREEDOM_CARD: "CHASE_FREEDOM",
            FREEDOM_PLATINUM: "CHASE_FREEDOM",
            FREEDOM_SIGNATURE: "CHASE_FREEDOM",
            FREEDOM_UNLIMITED: "CHASE_FREEDOM_UNLIMITED",
            GOLDFISH: "FINN",
            HYATT: "HYATT_REWARDS",
            HYATT_HOTELS: "HYATT_REWARDS",
            IBERIA_AVIOS: "IBERIA_AVIOS",
            INTERCONTINENTAL_HOTELS_GROUP: "IHG_REWARDS",
            JPMORGAN: "JPMORGAN_SELECT",
            JPMORGAN_PRIVATE_BANK: "JPMORGAN_PALLADIUM",
            MARINE_CORPS: "MILITARY_FREE_CASH_REWARDS",
            MARRIOTT: "MARRIOTT_REWARDS",
            MARRIOTT_BONSAI: "MARRIOTT_REWARDS",
            MARRIOTT_REWARDS_PREMIER: "MARRIOTT_REWARDS_PREMIER",
            MARY_KAY: "MARY_KAY_REWARDS",
            MBAPPE_CARD: "MBAPPE_CARD",
            NAVY_MWR: "MILITARY_FREE_CASH_REWARDS",
            RITZ_CARLTON: "RITZ_CARLTON_REWARDS",
            SOUTHWEST_AIRLINES: "SOUTHWEST",
            SOUTHWEST_PLUS: "SOUTHWEST",
            SOUTHWEST_PREMIER: "SOUTHWEST",
            STARBUCKS: "STARBUCKS",
            UNITED: "UNITED",
            UNITED_MILEAGE_PLUS_UA: "UNITED",
            UNITED_MILEAGE_PLUS_FCB: "UNITED",
            UNITED_MILEAGE_PLUS_MIDDLE: "UNITED",
            UNITED_MILEAGEPLUS_CLUB: "UNITED",
            UNITED_MILEAGEPLUS_EXPLORER: "UNITED",
            UNITED_MILEAGEPLUS_PRESIDENTIAL_PLUS: "UNITED",
            UNITED_TRAVEL_CASH: "UNITED",
            ZAPPOS: "ZAPPOS_REWARDS"
        },
        FILTER_FIELDS_ID: {
            AMOUNT_RANGE: "transactionAmountRangeLabel",
            CARD_MASK_NUMBER: "transactionCardNumberLabel",
            CARD_NUM_FIRST_DIGITS: "cardNumberFirstSixDigits",
            CARD_NUM_LAST_DIGITS: "cardNumberLastFourDigits",
            CHECK_RANGE: "checkNumberRangeLabel",
            DATE_RANGE: "transactionDateRangeOptionsLabel",
            FROM_AMOUNT: "transactionFromAmount",
            FROM_CHECK: "checkNumberFrom",
            FROM_DATE: "transactionPostedFromDate",
            MAX_AMOUNT: "maximumTransactionAmount",
            MERCHANT_NAME_LABEL: "merchantNameLabel",
            MERCHANT_NAME: "merchantName",
            MIN_AMOUNT: "minimumTransactionAmount",
            MS_FROM_DATE: "transactionFromDate",
            MS_FROM_MONTH: "transactionFromMonth",
            MS_TO_DATE: "transactionToDate",
            MS_TO_MONTH: "transactionToMonth",
            TO_AMOUNT: "transactionToAmount",
            TO_CHECK: "checkNumberTo",
            TO_DATE: "transactionPostedToDate"
        },
        SCREEN_WIDTH: {SM: 768, MD: 992, LG: 1200},
        NUMBER_PREFIXES: {
            1: "ONE",
            2: "TWO",
            3: "THREE",
            4: "FOUR",
            5: "FIVE",
            6: "SIX",
            7: "SEVEN",
            8: "EIGHT",
            9: "NINE",
            10: "TEN",
            11: "ELEVEN",
            12: "TWELVE",
            13: "THIRTEEN",
            14: "FOURTEEN",
            15: "FIFTEEN",
            16: "SIXTEEN",
            17: "SEVENTEEN",
            18: "EIGHTEEN",
            19: "NINETEEN",
            20: "TWENTY",
            21: "TWENTYONE",
            22: "TWENTYTWO",
            23: "TWENTYTHREE",
            24: "TWENTYFOUR",
            25: "TWENTYFIVE",
            26: "TWENTYSIX",
            27: "TWENTYSEVEN",
            28: "TWENTYEIGHT",
            29: "TWENTYNINE",
            30: "THIRTY",
            31: "LAST"
        },
        CBO_PRCINGSEGMENT_VALIDATION_ERRORS: {
            DATE: "DATE",
            FUTURE_DATE_FIVE_MONTHS: "FUTURE_DATE_FIVE_MONTHS",
            INVALID_DATE_RANGE: "INVALID_DATE_RANGE",
            PAST_DATE_TWENTY_FOUR_MONTHS: "PAST_DATE_TWENTY_FOUR_MONTHS",
            PAST_DATE_FIVE_MONTHS: "PAST_DATE_FIVE_MONTHS"
        },
        CHASE_LOYALTY_CARDS: ["AARP", "AMAZON", "AMAZON_PRIME", "AMAZON_REWARDS_VISA", "DISNEY"],
        BREAKPOINTS: {XS: "xs", SM: "sm", MD: "md", LG: "lg"},
        BRAND_ID: {BUSINESS: "BUSINESS", COMMERCIAL: "COMMERCIAL", JPMORGAN: "JPMORGAN", PERSONAL: "PERSONAL"},
        BRAND_TYPE: {BUSINESS: "business", COMMERICAL: "commercial"},
        TILE_ID: {
            CREDIT_SCORE_TILE: "creditScoreTile",
            INVESTMENT_APPLICATION: "investmentApplication",
            MORTGAGE_APPLICATION: "mortgageApplication"
        },
        TIME_ZONES: {America_New_York: "America/New_York"},
        DIGITAL_STATEMENT_FEATURE_FLAGS: {
            BAC: "digitalStatementsCardTypeBacFlag",
            BCC: "digitalStatementsCardTypeBccFlag",
            OLC: "digitalStatementsCardTypeOlcFlag",
            PAC: "digitalStatementsCardTypePacFlag"
        },
        ODS_SERVICE_FEATURE_FLAGS: {
            ODS_ACTIVITY: {DDA: "odsAccountActivityDDAFlag"},
            RECENT_ACTIVITY: {CARD: "odsRecentActivityCARDFlag", DDA: "odsRecentActivityDDAFlag"}
        },
        ACTIVITY_LENGTH_DEFAULTS: {
            DDA: {STANDARD: 24, CHECK_OLD: 84},
            CCO: {STANDARD: 4, STATEMENTS_ONLY: 4, PREMIUM: 24, PREMIUM_PLUS: 24, DEFAULT: 4},
            CCF: {STANDARD: 4, STATEMENTS_ONLY: 4, PREMIUM: 24, PREMIUM_PLUS: 24, DEFAULT: 4},
            CTL: {STANDARD: 4, PREMIUM: 24, PREMIUM_PLUS: 24, DEFAULT: 4}
        },
        USER_PREFERENCE: {
            RESTORE_FLYOUT_ON_SAVE_CHECK: "restoreSlideInOnSaveCheck",
            RESTORE_URL: "restoreUrl",
            RESTORE_FLYOUT_ON_SPENDING_REPORT: "restoreSlideInOnSpendingReport"
        },
        ERR_STATUS_CODE: {
            ACCOUNT_STATUS_101: "ACCOUNT_STATUS_101",
            ACCOUNT_STATUS_102: "ACCOUNT_STATUS_102",
            ACCOUNT_STATUS_103: "ACCOUNT_STATUS_103",
            ACCOUNT_STATUS_104: "ACCOUNT_STATUS_104",
            ACCOUNT_STATUS_105: "ACCOUNT_STATUS_105",
            ACCOUNT_STATUS_106: "ACCOUNT_STATUS_106",
            ACCOUNT_STATUS_107: "ACCOUNT_STATUS_107",
            ACCOUNT_STATUS_108: "ACCOUNT_STATUS_108",
            ACCOUNT_STATUS_109: "ACCOUNT_STATUS_109",
            PAST_DUE_LESS_THAN_90: "PAST_DUE_LESS_THAN_90",
            PAST_DUE_MORE_THAN_90: "PAST_DUE_MORE_THAN_90",
            PAYMENT_CHANGING_AFTER_ESCROW_ANALYSIS: "PAYMENT_CHANGING_AFTER_ESCROW_ANALYSIS",
            PAST_DUE_1_TO_19: "PAST_DUE_1_TO_19",
            PAST_DUE_20_TO_59: "PAST_DUE_20_TO_59",
            PAST_DUE_MORE_THAN_59: "PAST_DUE_MORE_THAN_59",
            NEARING_LEASE_END: "NEARING_LEASE_END",
            NEAR_LEASE_END_PAST_DUE_LESS_THAN_59: "NEAR_LEASE_END_PAST_DUE_LESS_THAN_59",
            NEARING_LEASE_END_LEASE_TERM_EXTENDED: "NEARING_LEASE_END_LEASE_TERM_EXTENDED",
            ACCOUNT_CLOSED: "ACCOUNT_CLOSED",
            SUB_USER_TRANSACTION_ONLY: "SUB_USER_TRANSACTION_ONLY",
            SUB_USER_NO_TRASACT_AND_VIEW_BALANCE: "SUB_USER_NO_TRASACT_AND_VIEW_BALANCE",
            MINIMUM_PAYMENT: "MINIMUM_PAYMENT",
            PAYMENT: "PAYMENT",
            PAST_DUE_1_TO_30_DAYS_OPEN: "PAST_DUE_1_TO_30_DAYS_OPEN",
            PAST_DUE_1_TO_30_DAYS_OPEN_PAYMENT_PLANS: "PAST_DUE_1_TO_30_DAYS_OPEN_PAYMENT_PLANS",
            PAST_DUE_31_TO_60_DAYS_OPEN_PAYMENT_PLANS: "PAST_DUE_31_TO_60_DAYS_OPEN_PAYMENT_PLANS",
            PAST_DUE_1_TO_60_DAYS_CLOSED_PAYMENT_PLANS: "PAST_DUE_1_TO_60_DAYS_CLOSED_PAYMENT_PLANS",
            PAST_DUE_61_TO_120_DAYS_CLOSED_PAYMENT_PLANS: "PAST_DUE_61_TO_120_DAYS_CLOSED_PAYMENT_PLANS",
            PAST_DUE_121_TO_210_DAYS_CLOSED_PAYMENT_PLANS: "PAST_DUE_121_TO_210_DAYS_CLOSED_PAYMENT_PLANS",
            AFTER_DUE_BEFORE_CYCLE_PAYMENT_PLANS: "AFTER_DUE_BEFORE_CYCLE_PAYMENT_PLANS",
            LAST_MONTH_PAYMENT_NOT_RECEIVED: "LAST_MONTH_PAYMENT_NOT_RECEIVED",
            PAST_DUE_PAYMENT_REQUIRED: "PAST_DUE_PAYMENT_REQUIRED",
            PAST_DUE_PAYMENT_SCHEDULED: "PAST_DUE_PAYMENT_SCHEDULED",
            NEXT_PAYMENT_SCHEDULED: "NEXT_PAYMENT_SCHEDULED",
            PAYMENT_PLAN_ENROLLED: "PAYMENT_PLAN_ENROLLED",
            PAYMENT_DUE_IN_DAYS: "PAYMENT_DUE_IN_DAYS",
            JOINT_LIABILITY: "JOINT_LIABILITY",
            FIRST_PAYMENT_PENDING_TO_COMPLETE_ENROLLMENT: "FIRST_PAYMENT_PENDING_TO_COMPLETE_ENROLLMENT",
            PROGRAM_PAYMENT_DUE: "PROGRAM_PAYMENT_DUE",
            AGENCY_SPECIALITY_LITIGATION: "AGENCY_SPECIALITY_LITIGATION",
            IRU_SPECIALITY_PRELITIGATION: "IRU_SPECIALITY_PRELITIGATION",
            IRU_SPECIALITY_OUT_OF_STATUTE: "IRU_SPECIALITY_OUT_OF_STATUTE",
            IRU_SETTLEMENT_PLAN_ENROLLED_SETTLEMENT_BALANCE_SCHEDULED: "IRU_SETTLEMENT_PLAN_ENROLLED_SETTLEMENT_BALANCE_SCHEDULED",
            IRU_SETTLEMENT_PLAN_ENROLLED_SETTLEMENT_BALANCE_NOT_SCHEDULED: "IRU_SETTLEMENT_PLAN_ENROLLED_SETTLEMENT_BALANCE_NOT_SCHEDULED",
            IRU_SETTLEMENT_PLAN_ENROLLED_PENDING_REASSIGNMENT_AGENCY: "IRU_SETTLEMENT_PLAN_ENROLLED_PENDING_REASSIGNMENT_AGENCY",
            IRU_SETTLEMENT_PLAN_NOT_ENROLLED_SETTLEMENT_BALANCE_SCHEDULED: "IRU_SETTLEMENT_PLAN_NOT_ENROLLED_SETTLEMENT_BALANCE_SCHEDULED",
            IRU_SETTLEMENT_PLAN_NOT_ENROLLED_SETTLEMENT_BALANCE_NOT_SCHEDULED: "IRU_SETTLEMENT_PLAN_NOT_ENROLLED_SETTLEMENT_BALANCE_NOT_SCHEDULED",
            IRU_SETTLEMENT_PLAN_NOT_ENROLLED_PENDING_REASSIGNMENT_AGENCY: "IRU_SETTLEMENT_PLAN_NOT_ENROLLED_PENDING_REASSIGNMENT_AGENCY",
            INTRADAY_DEFAULT: "INTRADAY_DEFAULT"
        },
        DEFAULT_LANDING_PAGE: {
            ACCOUNTS: "requestAccountSummary",
            BUSINESS_OVERVIEW: "requestAccountsOverview",
            GWM_OVERVIEW: "requestOverviewDashboard"
        },
        LANGUAGES: {en: "en", es: "es"},
        DASHBOARD_MODEL_NAMES: {MODEL_ANNOUNCEMENTS: "announcementsModel"},
        spanishServiceMapper: {TRANSFER_AGREEMENT: "es.transfer.agreement.json"},
        LOCKED: "LOCKED",
        hybridChannel: ["MON", "MOP", "PBN", "PBP", "PBD"],
        CHASE_HYBRID_MENU_AREAS: ["investments", "eda", "trade", "markets", "ideas", "learning", "learningInsights", "aat", "financialProfile", "savingsToInvest", "marketscreeners"],
        JPM_HYBRID_MENU_AREAS: ["profile", "investments", "eda", "trade", "markets", "ideas", "learning", "learningInsights", "aat", "financialProfile", "savingsToInvest", "marketscreeners"],
        ACCOUNT_TYPE_ADA_REGEX: {"<sup>": "", "</sup>": "", "<a[^<>]+>": " footnote ", "</a>": ""},
        ACCESS_STATUS: {ACTIVE: "ACTIVE", DISABLED: "DISABLED"},
        HTTPS: "https://",
        DOWNLOADABLE_URL_EXTENSIONS: [".pdf"],
        ACCOUNT_FILTERING: {
            CBO_FILTER_CRITERIA_ID: {
                ALL_ACCOUNTS: "ALL_ACCOUNTS",
                FAVORITES: "FAVORITES",
                ALL_BUSINESS: "ALL_BUSINESS",
                ALL_PERSONAL: "ALL_PERSONAL",
                CUSTOM_GROUP: "CUSTOM_GROUP",
                ACCOUNT_GROUP: "ACCOUNT_GROUP",
                CUSTOM_ACCOUNTS: "CUSTOM_ACCOUNTS"
            },
            CBO_ACCOUNTS_FILTER_CRITERIA_TYPE: {
                BUSINESS: "BUSINESS",
                PERSONAL: "PERSONAL",
                ACCOUNT_GROUP: "ACCOUNT_GROUP"
            }
        },
        staySafe: {
            STAY_SAFE_FLAG: "staySafeFlag",
            ACH_DEBIT_BLOCK: "achDebitBlock",
            ACH_DEBIT_BLOCK_ID: "requestAchDebitBlock",
            FRAUD_PROTECTION_SERVICE_ID: "fraudProtectionServices"
        }
    };
    return e.CHANNEL_AREA_MAP = {
        MON: e.CHASE_HYBRID_MENU_AREAS,
        MOP: e.CHASE_HYBRID_MENU_AREAS,
        MOD: e.CHASE_HYBRID_MENU_AREAS,
        PBN: e.JPM_HYBRID_MENU_AREAS,
        PBP: e.JPM_HYBRID_MENU_AREAS,
        PBD: e.JPM_HYBRID_MENU_AREAS
    }, e.PAYMENT_HUB_MENU = {
        paymentHubRecipients: ["payments.payees", "payments.quickpayrecipients", "payroll.payee", "payments.wirerecipients"],
        paymentHubQuickPaySettings: ["payments.quickpaysettings", "payroll.demo"],
        paymentHubTertiaryContext: "cxo_nav_paymentsummary_sub_menu",
        customRecipientsPrivilege: "custom.paymentHub.showRecipient",
        customSettingsPrivilege: "custom.paymentHub.showSettings"
    }, e
})), define("common/lib/pageTitle", ["blue/log", "appkit-utilities/content/dcu", "appkit-utilities/messenger/messenger", "common/lib/constants"], (function (e, t, n, i) {
    "use strict";
    var o = e("[common/lib/pageTitle]"), r = i.BRAND_ID, a = t.dynamicContent, s = function (e) {
        return !(!e.context || !e.context.pageTitle)
    }, c = function (e, t, n, i) {
        return i = i || "", e in n && "string" == typeof n[e].value && (t = !0 === n[e].isHtml ? function (e) {
            var t = "";
            if ("string" == typeof e) {
                var n = document.createElement("div");
                n.innerHTML = e, t = n.textContent
            }
            return t
        }(n[e].value) + i : n[e].value + i), t
    };
    return {
        setTitle: function (e, t) {
            if (s(e)) {
                var i, l, u = " " + a.getGlobal("hyphenSymbol") + " ", d = null, m = e.context.userInfo,
                        f = m && m.brandId === r.JPMORGAN ? "jpMorganOnlineLabel" : "chasePersonalOnlineLabel";
                l = a.getGlobal(f), u = c("delimiter", u, t), d = c("h1", d, t, u), l = c("appName", l, t), null !== d ? (e.context.pageTitle.clearPrefix(), i = d + l) : i = l, e.context.pageTitle.setTitle(i), n.isFramedIn() && n.sendMessage({
                    protocol: "childEvent",
                    command: "setTitle",
                    data: {title: i}
                })
            } else o.error("Could not set page title because view does not have a page context.")
        }, getTitle: function (e) {
            return s(e) ? e.context.pageTitle.getTitle() : ""
        }
    }
})), define("common/lib/jsBridge", ["require", "blue/root"], (function (e) {
    "use strict";
    var t, n = e("blue/root"), i = {}, o = {}, r = "#/dashboard/documents/myDocs/menu";

    function a(e, t, i) {
        var o, r, a, s = function () {
            t.logger.info('calling javaScript bridge "' + e + '"'), void 0 !== i ? n.JPKJavaScriptBridgeHandler[e](i) : n.JPKJavaScriptBridgeHandler[e]()
        };
        n.hybrid && (n.JPKJavaScriptBridgeHandler && n.JPKJavaScriptBridgeHandler[e] ? s() : (o = e, a = 0, new Promise((function (e, t) {
            r = setInterval((function () {
                n.JPKJavaScriptBridgeHandler && n.JPKJavaScriptBridgeHandler[o] ? (clearInterval(r), e()) : a >= 500 ? (clearInterval(r), t(new Error("Promise failed to resolve JPKJavaScriptBridgeHandler"))) : a++
            }), 10)
        }))).then((function () {
            setTimeout(s, 1)
        })).catch((function (n) {
            t.logger.error('javaScript bridge "' + e + '" is not available', n)
        })))
    }

    function s(e, n) {
        setTimeout((function () {
            t = n, a("updateNativeNavigationBarButtons", e, n)
        }), 1)
    }

    function c() {
        return t
    }

    return {
        changeScreenTitle: function (e, t) {
            e = e || "", [["<sup>Â®</sup>", "(R)"], [/&amp;/g, "&"], ["<sup>SM</sup>", "â„ "]].forEach((function (t) {
                e = e.replace(t[0], t[1])
            })), a("changeScreenTitle", t, {title: e})
        },
        getPDFDocument: function (e, t) {
            a("getPDFDocument", t, e)
        },
        showBackArrow: function (e, t) {
            setTimeout((function () {
                a("showBackArrow", t, {showArrow: e})
            }), 1)
        },
        showNativeDisclosure: function (e) {
            a("showNativeDisclosure", e)
        },
        showNativeLegalAgreements: function (e) {
            a("showNativeLegalAgreements", e)
        },
        enrollUserQuickPay: function (e) {
            a("enrollUserQuickPay", e)
        },
        quickPayUserUnenrolled: function (e) {
            a("quickPayUserUnenrolled", e)
        },
        onFinish: function (e, t, i, o, r) {
            if (n.hybrid) {
                var s = {requireRefresh: Boolean(i), exitToNativeEntryPoint: Boolean(r)};
                o && (s.nativeHamburgerMenuSelectedOption = o), a("onFinish", e, s)
            } else e && e.state(t || "/dashboard/")
        },
        profileBackNavRequired: function () {
            if (n.hybrid) return "#/dashboard/profile/menu/index" === location.hash || (i.context.state("#/dashboard/profile/menu/index"), !1)
        },
        statementsBackNavRequired: function (e) {
            var t = !1;
            if (n.hybrid) return t = location.hash === r || location.hash.indexOf("#/dashboard/documents/myDocs/accountMenu;accountId=") >= 0, e && !t ? (i.context.state("#/dashboard/documents/myDocs/accountMenu;accountId=" + e), !1) : !!t || (i.context.state(r), !1)
        },
        visitDirectory: function (e) {
            a("visitDirectory", e)
        },
        showPaperlessSettings: function (e) {
            a("showPaperlessSettings", e)
        },
        updateNicknames: function (e) {
            n.hybrid && a("updateNicknames", e)
        },
        showNativeAlert: function (e, t) {
            n.hybrid && a("showNativeAlert", e, {message: t})
        },
        isHybridBackButtonRequired: function () {
            var e = location.hash, t = e.indexOf("#/dashboard/documents/myDocs");
            return !(t >= 0) || (e.indexOf(r) >= 0 || e.indexOf("menuOpen=true", t) >= 0 || (i.context.state(r), !1))
        },
        isCloseSelected: function () {
            return !0
        },
        navigateToRequestCardScreen: function () {
            return !0
        },
        updateNativeNavigationBarButtons: s,
        getCurrentNativeNavigationBarButtons: c,
        updateCurrentNativeNavigationBarButtons: function (e) {
            t = e
        },
        saveJSBridgeState: function (e, t) {
            var n = {};
            return n.currentNativeNavigationBarButtons = c(), n.isHybridBackButtonRequired = e.isHybridBackButtonRequired, n.leaveFlow = e.leaveFlow, o[t] = n, n
        },
        restoreJSBridgeState: function (e, t, n) {
            var i = o[t];
            return i && (s(n, i.currentNativeNavigationBarButtons), e.isHybridBackButtonRequired = i.isHybridBackButtonRequired, e.leaveFlow = i.leaveFlow), i
        },
        setRoutingAPI: function (e) {
            i.context = e
        },
        showNativeQuickPaySendMoney: function (e) {
            a("showNativeQuickPaySendMoney", e)
        },
        showNativeQuickPayRequestMoney: function (e) {
            a("showNativeQuickPayRequestMoney", e)
        },
        showNativeQuickPaySingleDoor: function (e) {
            a("showNativeQuickPaySingleDoor", e)
        },
        releaseSpinner: function (e) {
            a("releaseSpinner", e)
        },
        bandwidthQuality: function (e, t) {
            a("bandwidthQuality", e, t)
        },
        refreshProfile: function (e) {
            a("refreshProfile", e)
        },
        wiresUserEnrolled: function (e) {
            a("wiresUserEnrolled", e)
        },
        checkDigitalWalletConnection: function (e, t) {
            a("checkDigitalWalletConnection", e, t)
        },
        gotoAutomaticPayments: function (e, t) {
            a("gotoAutomaticPayments", e, t)
        },
        addCardToWallet: function (e, t) {
            a("addCardToWallet", e, t)
        },
        showAllActivities: function (e, t) {
            a("showAllActivities", e, t)
        },
        showExtendedTransactionDetails: function (e, t) {
            a("showExtendedTransactionDetails", e, t)
        },
        externalBrowser: function (e, t) {
            a("externalBrowser", e, t)
        },
        autoPayUserEnrolled: function (e) {
            a("autoPayUserEnrolled", e)
        },
        agreeConsent: function (e, t) {
            a("agreeConsent", e, t)
        },
        errorConsent: function (e, t) {
            a("errorConsent", e, t)
        },
        cancelConsent: function (e, t) {
            a("cancelConsent", e, t)
        },
        openCameraOrGallery: function (e, t) {
            a("openCameraOrGallery", e, t)
        },
        schedulePayment: function (e, t) {
            a("schedulePayment", e, t)
        },
        dialPhone: function (e, t) {
            a("dialPhone", e, t)
        },
        updatePortfolioSummaryGroups: function (e) {
            a("updatePortfolioSummaryGroups", e)
        },
        showSendGift: function (e) {
            a("showSendGift", e)
        },
        goToNativePage: function (e, t) {
            a("goToNativePage", e, {navKey: t})
        },
        goToNativePageWithParams: function (e, t) {
            a("goToNativePage", e, t)
        },
        cxoPerfLog: function (e, t) {
            a("cxoPerfLog", e, t)
        }
    }
})), define("common/lib/utility/hybridMixin", ["require", "common/lib/jsBridge", "blue/root"], (function (e) {
    "use strict";
    var t = e("common/lib/jsBridge"), n = e("blue/root"), i = n.hybrid,
            o = ["onFinish", "updateNativeNavigationBarButtons", "showNativeAlert", "showNativeDisclosure", "agreeConsent", "cancelConsent", "errorConsent", "addCardToWallet", "bandwidthQuality", "showNativeLegalAgreements", "showAllActivities", "showExtendedTransactionDetails", "enrollUserQuickPay", "quickPayUserUnenrolled", "updateNicknames", "visitDirectory", "showPaperlessSettings", "checkDigitalWalletConnection", "gotoAutomaticPayments", "showNativeAlert", "showNativeQuickPaySendMoney", "showNativeQuickPayRequestMoney", "showNativeQuickPaySingleDoor", "showSendGift", "refreshProfile", "updatePortfolioSummaryGroups", "releaseSpinner", "wiresUserEnrolled", "externalBrowser", "agreeConsent", "errorConsent", "cancelConsent", "schedulePayment", "autoPayUserEnrolled", "dialPhone", "openCameraOrGallery", "goToNativePage", "goToNativePageWithParams", "bandwidthQuality", "cxoPerfLog"];
    return function () {
        this._resolveHybridBridge = function (e, t) {
            var i, o = 0;
            return new Promise((function (r, a) {
                i = setInterval((function () {
                    n.JPKJavaScriptBridgeHandler && n.JPKJavaScriptBridgeHandler[e] ? (clearInterval(i), r()) : o >= 500 ? (clearInterval(i), a(new Error("Promise failed to resolve JPKJavaScriptBridgeHandler"))) : (o % 50 == 0 && t.logger.warn("Incrementing interval count: ", o), o++)
                }), 10)
            }))
        }, this.dispatchHybridEvent = function (e, r) {
            var a = [].slice.call(arguments, 2);
            if (o.indexOf(e) > -1 ? a.unshift(r) : a.push(r), i) {
                var s = t[e];
                n.JPKJavaScriptBridgeHandler && n.JPKJavaScriptBridgeHandler[e] && a.length ? s.apply(null, a) : this._resolveHybridBridge(e, r).then((function () {
                    a.length && s.apply(null, a)
                })).catch((function () {
                    r.logger.error("Could not dispatch hybrid event: ", e)
                }))
            }
        }
    }
})), define("common/lib/focusUtil", ["require", "blue/log", "blue-ui/utilities/common", "common/lib/pageTitle", "appkit-utilities/content/dcu", "common/lib/utility/hybridMixin", "blue/$", "blue/root"], (function (e) {
    "use strict";
    var t, n = e("blue/log")("[focusUtil]"), i = e("blue-ui/utilities/common"), o = e("common/lib/pageTitle"),
            r = e("appkit-utilities/content/dcu"), a = e("common/lib/utility/hybridMixin"), s = e("blue/$"),
            c = e("blue/root");

    function l(e, t) {
        return e(t).is(":visible") && !e(t).parents().addBack().filter((function () {
            return "hidden" === e(t).css("visibility")
        })).length
    }

    function u(e, t) {
        var n, i, o, r = t.nodeName.toLowerCase(), a = !isNaN(e(t).attr("tabindex"));
        return "area" === r ? (i = (n = t.parentNode).name, !(!t.href || !i || "map" !== n.nodeName.toLowerCase()) && (!!(o = e("img[usemap=#" + i + "]")[0]) && l(e, o))) : (/input|select|textarea|button|object/.test(r) ? !t.disabled : "a" === r && t.href || a) && l(e, t)
    }

    function d(e) {
        return 13 === e.domEvent.which || 13 === e.domEvent.keyCode || "mouse" === e.domEvent.pointerType || "touch" === e.domEvent.pointerType || "tap" === e.domEvent.type || "click" === e.domEvent.type
    }

    function m(e, t, n) {
        var i = s(e.element.node);
        t && !n ? i.find(t).attr("tabindex", "-1").first().focus() : t && n ? i.find(t).first().focus() : i.focus()
    }

    return {
        isVisible: l, isFocusable: u, setFocus: function (e, i, o, r) {
            var a = this, s = function (e, n, i, o, r) {
                t && clearTimeout(t), t = setTimeout((function () {
                    try {
                        "string" == typeof n && (n = e(n)), (n = n.filter(":visible")) && n.length && l(e, n[0]) ? (u(e, n[0]) || n.attr("tabIndex", -1), n.first().focus(), o && "function" == typeof o && o()) : r && "function" == typeof r && r({
                            code: "NOT_VISIBLE",
                            local$: e,
                            jQElement: n,
                            delay: i
                        })
                    } catch (e) {
                        r && "function" == typeof r && r("focus rejected with err", e)
                    }
                }), i && parseInt(i) > 0 ? i : 100)
            };
            if (!r || !r.noListener) return new Promise((function (t, n) {
                s.call(a, e, i, o, t, n)
            })).catch((function (e) {
                n.debug(e)
            }));
            s.call(a, e, i, o)
        }, isMobile: function () {
            return i.isMobile() || i.isApple() && i.isTouch()
        }, isAndroid: function () {
            return i.isAndroid()
        }, getBreakpoint: function () {
            return i.getBreakpoint()
        }, listenUpdateViewModel: function (e, t) {
            t = t || "updateViewModel", e.bridge.on("state/" + t, (function (t) {
                t.data && Object.keys(t.data).forEach((function (n) {
                    e.model[n] = t.data[n]
                }))
            }))
        }, setPageTitle: function (e, t) {
            var n = {h1: {value: t, isHTML: !1}};
            e && e.model && e.model.hybrid ? (a.call(this), this.dispatchHybridEvent("changeScreenTitle", e.context, t)) : o.setTitle(e, n)
        }, getCleanPageTitle: function (e) {
            var t = r.dynamicContent.getGlobal("hyphenSymbol") || "-";
            return e && e.context && e.context.pageTitle && "string" == typeof e.context.pageTitle.getTitle() ? e.context.pageTitle.getTitle().substring(0, e.context.pageTitle.getTitle().indexOf(t)).trim() : ""
        }, getGlobalContent: function (e) {
            return r.dynamicContent.getGlobal(e)
        }, scrollTop: function (e) {
            e = e || 0, i.isIE() || i.isFirefox() ? s("html").animate({scrollTop: e}, "fast") : s("body").scrollTop(e)
        }, scrollToWindowTop: function () {
            window.scroll(0, 0)
        }, isEnterSpaceOrMousePress: function (e) {
            return 32 === e.domEvent.which || 32 === e.domEvent.keyCode || d(e)
        }, isEnterOrMousePress: d, focusOnRender: m, focusAndScrollToTopOnRender: function (e, t) {
            m(e, t), c.scrollTo(0, 0)
        }, scrollToTopOfPage: function (e, t) {
            e = e || 0, s("html, body").animate({scrollTop: e}, t || "fast")
        }, setFocusWithScrollPosition: function (e, t) {
            var n = e(t.domElementSelector), i = t.delay || 100;
            setTimeout((function () {
                n.length && n.focus(), t.scrollPosition && window.scrollTo(0, t.scrollPosition)
            }), i)
        }, isIE: function () {
            return i.isIE()
        }
    }
})), define("common/component/spinner", ["require", "blue/$", "blue/root", "common/lib/focusUtil"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = e("blue/root"), i = e("common/lib/focusUtil").setFocus, o = [5e3, 1e4, 2e4, 4e4, 8e4],
            r = [3e3];
    return function (e) {
        var a = this, s = e.config || {}, c = s && s.spinnerMaxTTL || 125e3, l = s && s.spinnerErrorTTLs || o,
                u = s && s.spinnerWarnTTLs || r, d = null, m = null,
                f = ["default-spinner_1", "default-spinner_2", "default-spinner_3"];
        a.onReady = function () {
            var t = a.model && a.model.get("id");
            -1 === f.indexOf(t) && (m = Date.now(), d = setTimeout((function () {
                a.destroy()
            }), c), e.logger.debug("This component will self-destruct in " + (c / 1e3 | 0) + " seconds."))
        }, a.onDestroy = function () {
            var t = Date.now() - m, n = l.reduce(o, 0), i = u.reduce(o, 0);

            function o(e, n) {
                return t >= n ? n : e
            }

            null !== m && (clearTimeout(d), t >= c ? e.logger.error("Spinner reached timeout (" + (c / 1e3 | 0) + " sec) Actual time was " + t + " millis") : n ? e.logger.error("Spinner with long display (at least " + (n / 1e3 | 0) + " sec) Actual time was " + t + " millis") : i ? e.logger.warn("Spinner with long display (at least " + (i / 1e3 | 0) + " sec) Actual time was " + t + " millis") : e.logger.debug("Spinner displayed for " + t + " millis"))
        }, a.reposition = function () {
            var e = t("#" + a.model.get("id"));
            if (e.length) {
                var n = a.model.get("type"), o = a.model.get("doNotFocus");
                "FULLSCREEN" === n && a.lockScreen(), o || i(t, e)
            }
        }, a.keydownLock = function (e) {
            -1 !== [37, 38, 39, 40, 32, 33, 34, 35, 36].indexOf(e.keyCode) && ((e = e || n.event).preventDefault && e.preventDefault(), e.returnValue = !1)
        }, a.mouseWheelLock = function (e) {
            (e = e || n.event).preventDefault && e.preventDefault(), e.returnValue = !1
        }, a.registerResponsiveSpinner = function () {
            t(document).on("DOMSubtreeModified.responsiveSpinner", (function (e) {
                a.unregisterResponsiveSpinner(e)
            })), t(document, n).on("DOMNodeRemoved.responsiveSpinner", (function (e) {
                a.unregisterResponsiveSpinner(e)
            }))
        }, a.unregisterResponsiveSpinner = function () {
            a.getVisibleSelectorLength() > 0 || (t(document).off("DOMSubtreeModified.responsiveSpinner"), t(document).off("DOMNodeRemoved.responsiveSpinner"))
        }, a.getVisibleSelectorLength = function () {
            return t(document).find(".spinner-overlay").filter(":visible").length
        }, a.lockScreen = function () {
            t(document).on("DOMMouseScroll.lockedSpinner", (function (e) {
                a.mouseWheelLock(e)
            })), t(document).on("DOMSubtreeModified.lockedSpinner", (function (e) {
                a.validateSpinnerState(e)
            })), t(document).on("DOMNodeRemoved.lockedSpinner", (function (e) {
                a.validateSpinnerState(e)
            })), t(document).on("mousewheel.lockedSpinner", (function (e) {
                a.mouseWheelLock(e)
            })), t(document).on("keydown.lockedSpinner", (function (e) {
                a.keydownLock(e)
            }))
        }, a.unlockScreen = function () {
            t(document).off("DOMMouseScroll.lockedSpinner"), t(document).off("DOMSubtreeModified.lockedSpinner"), t(document).off("DOMNodeRemoved.lockedSpinner"), t(document).off("mousewheel.lockedSpinner"), t(document).off("keydown.lockedSpinner")
        }, a.validateSpinnerState = function (e) {
            (e && "DOMNodeRemoved" === e.type && e.target && e.target.classList && e.target.classList.contains("spinner-overlay") || e && "DOMSubtreeModified" === e.type && a.getVisibleSelectorLength() < 1) && a.unlockScreen()
        }
    }
})), define("bluespec/spinner", [], (function () {
    return {name: "SPINNER", settings: {waitAda: !0}}
})), define("common/template/subViews/dashboardSpinnerWrapper", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "div",
            m: [{n: "id", f: [{t: 2, r: "id"}], t: 13}, {
                n: "class",
                f: [" spinner-overlay ", {t: 2, r: "classes"}, " ", {
                    t: 4,
                    f: ["loadingMessage-spinner"],
                    n: 50,
                    r: "loadingMessage"
                }],
                t: 13
            }],
            f: [{
                t: 7,
                e: "blueSpinner",
                m: [{n: "id", f: [{t: 2, r: "id"}, "-spinner"], t: 13}, {
                    n: "accessibleText",
                    f: [{t: 2, r: "accessibleText"}],
                    t: 13
                }]
            }, " ", {
                t: 4,
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "CH1", t: 13}, {
                        n: "id",
                        f: [{t: 2, r: "id"}, "-spinner-content"],
                        t: 13
                    }, {n: "class", f: "loadingMessage-content", t: 13}, {
                        n: "accessibleText",
                        f: [{t: 2, r: "waitAda"}],
                        t: 13
                    }],
                    f: [{t: 3, x: {r: ["sanitizer", ".loadingMessage"], s: "_0.sanitizeHTML(_1)"}}]
                }],
                n: 50,
                r: "loadingMessage"
            }]
        }],
        e: {}
    }
})), define("common/lib/transition/motionHandler", ["require", "blue/$", "blue/root", "blue/util", "blue-ui/utilities/motion"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = e("blue/root"), i = e("blue/util").lang.defaults, o = e("blue-ui/utilities/motion"),
            r = n.TimelineMax || null, a = n.blueMotionUtilities || null, s = {
                expand: function (e, i) {
                    var o = n.Power4.easeInOut ? n.Power4.easeInOut : null, a = i && i.duration ? i.duration : .4,
                            s = e.ractive.root.view, c = new r({delay: a});
                    c.set(t(e.node), {height: "auto", opacity: 1}), c.from(t(e.node), a, {
                        height: 0,
                        opacity: 0,
                        ease: o,
                        onComplete: function () {
                            i && i.emitOnComplete && s && s.bridge && s.bridge.output && s.bridge.output.emit("expandMotionComplete", i), e.complete && e.complete()
                        },
                        onCompleteScope: this,
                        onCompleteParams: ["{self}"]
                    })
                }, hide: function (e, i) {
                    var o = n.Power4.easeInOut ? n.Power4.easeInOut : null, a = i && i.duration ? i.duration : .4,
                            s = e.root.view;
                    new r({delay: a}).to(t(e.node), a, {
                        height: 0, opacity: 0, ease: o, onComplete: function (n) {
                            (n || n.target) && t(n.target).removeAttr("style"), i && i.removeDOM && t(this.target).remove(), i && i.emitOnComplete && s && s.bridge && s.bridge.output && s.bridge.output.emit("hideMotionComplete", i), e.complete && e.complete()
                        }, onCompleteScope: this, onCompleteParams: ["{self}"]
                    })
                }, staggerNodes: function (e, i) {
                    if (i && i.selector) {
                        var o = n.Power4.easeInOut ? n.Power4.easeInOut : null, a = i.duration ? i.duration : .4,
                                s = i.transition ? i.transition : .1;
                        new r({delay: a}).staggerFromTo(i.selector, a, {y: 50, opacity: 0}, {
                            y: 0,
                            opacity: 1,
                            ease: o,
                            onComplete: function (n) {
                                (n || n.target) && t(n.target).removeAttr("style"), e.complete()
                            },
                            onCompleteScope: this,
                            onCompleteParams: ["{self}"]
                        }, s)
                    }
                }
            };
    return function (e) {
        if (e && e.node && o && a) {
            var t = e.params.length ? e.params[0] : null;
            if (t) {
                var n = i(t.type, ""), r = t.options ? t.options : null;
                n && "string" == typeof n && s[n](e, r)
            }
        }
    }
})), define("common/view/subViews/dashboardSpinnerWrapper", ["require", "common/template/subViews/dashboardSpinnerWrapper", "blue-ui/view/elements/spinner", "common/lib/transition/motionHandler"], (function (e) {
    "use strict";
    return function () {
        this.bridge = {
            name: "DashboardSpinnerWrapper",
            bindings: {},
            triggers: {render: {action: "reposition"}}
        }, this.template = e("common/template/subViews/dashboardSpinnerWrapper"), this.init = function () {
        }, this.views = {blueSpinner: e("blue-ui/view/elements/spinner")}, this.transitions = {motionHandler: e("common/lib/transition/motionHandler")}
    }
})), define("common/controller/spinner", ["require", "blue/$", "blue/dom", "bluespec/spinner", "common/component/spinner", "appkit-utilities/content/dcu", "common/view/subViews/dashboardSpinnerWrapper"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = {
        SPINNER: {
            spec: e("bluespec/spinner"),
            methods: e("common/component/spinner"),
            view: e("common/view/subViews/dashboardSpinnerWrapper")
        }
    }, i = e("appkit-utilities/content/dcu").dynamicContent, o = e("blue/util").lang.defaults;
    return function (e) {
        var r, a = [], s = {globalTransfer: 2, wires: 2, wiresAdmin: 2}, c = Object.keys({
                    accounts: !0,
                    accountServicing: !0,
                    convoDeck: !0,
                    ebills: !0,
                    gallery: !0,
                    gifting: !0,
                    investments: !0,
                    markets: !0,
                    onlineEnrollment: !0,
                    trade: !0,
                    cboGallery: !0,
                    creditCardAccountServicing: !0,
                    merchantPayeesAdmin: !0,
                    payeesAdmin: !0,
                    qpEnroll: !0,
                    qpSettings: !0,
                    overviewAccounts: !0,
                    payMultipleRecipients: !0,
                    payMultipleBills: !0,
                    savingsToInvest: !0,
                    fraudHub: !0,
                    flexCredit: !0,
                    externalAccounts: !0,
                    singleDoor: !0,
                    quickPay: !0,
                    campaign: !0,
                    paymentServicesHub: !0,
                    portfolioLineOfCredit: !0,
                    offers: !0,
                    customerInteraction: !0,
                    myProfileOverview: !0,
                    myProfilePersonalDetails: !0,
                    myProfileSignInSecurity: !0,
                    myProfileAccountSafe: !0,
                    myProfileAccountSettings: !0,
                    chaseRateAdvantage: !0,
                    pmr: !0,
                    upcomingPayments: !0
                }).concat(["spinner", "menu", "header"]), l = !0, u = "noRoute", d = "#content-spinner-overlay",
                m = "#spinner-container", f = "#flyoutSpinnerContainer", p = 0, g = 0, y = {}, h = [], v = [];
        c.forEach((function (e, t, n) {
            n[t] = "/" + e
        }));

        function b(e) {
            for (var n = Object.keys(y), i = n.length - 1; i >= 0; i--) if (t(e).closest(y[n[i]][0])) return n[i]
        }

        function A(e, t, n) {
            var i = ["target"], o = n.name && -1 !== n.name.indexOf("interceptor.");
            if (e.context.util.object.equals(t, n)) return !0;
            if (!o) for (var r = 0; r < i.length; r++) if (n[i[r]] && t[i[r]] && n[i[r]] === t[i[r]]) return !0;
            return !1
        }

        function E(e, t, n, i) {
            if (t || (t = {}), !e.isArea(t.name)) if ("on" === i) {
                for (var o = 0; o < v.length; o++) if (A(e, v[o], t)) return void v.splice(o, 1);
                h[h.length] = t, e.showSpinner(t)
            } else {
                var r = h.findIndex((function (n) {
                    return A(e, n, t)
                }));
                if (-1 === r) return void v.push(t);
                h.splice(r, 1), e.hideSpinner(t)
            }
        }

        this.init = function () {
            r = e && e.settings && e.settings.get("ignoreParentSpinner") || !1;
            var n = this;

            function i(e, t) {
                return e && !c.some((function (t) {
                    return e.indexOf(t) >= 0
                })) && function (e, t) {
                    l && (s = a.reduce((function (e, t) {
                        return e[t] = 2, e
                    }), s), l = !1);
                    var n = !1, i = a, o = e ? e.split("/") : [""], r = i.findIndex((function (e) {
                        return -1 !== o.indexOf(e)
                    }));
                    e && -1 !== r && s[i[r]] > 0 && u !== t && (s[i[r]] = s[i[r]] - 1, n = !0);
                    return n
                }(e, t)
            }

            function o(e) {
                n.context.logger.info("spinner:off due to error", e && e.code, e && e.description), t(f + "," + d + "," + m).removeClass().addClass("hide"), n.hideSpinner({name: "any"})
            }

            this.context.application.on("spinner:on", (function (e) {
                0 === p && n.createInitialSpinners(), E(n, e, 0, "on")
            })), this.context.application.on("spinner:off", (function (e) {
                E(n, e, 0, "off")
            })), n.context.application.on("blue:route:viewRenderComplete", (function (e) {
                e.contextPath && i(e.contextPath, e.msgType) && (t(f + "," + d + "," + m).removeClass().addClass("hide"), u = e.msgType)
            })), n.context.application.on("blue:route:lazyLoad", (function (o) {
                var r, a,
                        s = o.nextSegment && o.nextSegmentType && "area" === o.nextSegmentType && i("/" + o.nextSegment, o.msgType);
                (s = s && (r = o.nextSegment, a = n.context.state(), !("profile" === r && a.action.params.flyout))) && (e.spinnerDisabled ? t(f + "," + d + "," + m).addClass("hide").removeClass("viewRender") : t(f + "," + d + "," + m).addClass("viewRender").removeClass("hide"), u = o.msgType)
            })), n.context.on("spinner: forceAbort", o), n.context.on("blue:route:error:actionNotFound", o), n.context.on("blue:app:error:actionExecutionError", o), n.context.on("blue:app:error:actionNotBound", o), n.context.on("blue:cav:error:componentConstructionFailed", o), n.context.on("blue:route:error:actionNotResolved", o), n.context.on("blue:route:error:areaActionNotMapped", o), n.context.on("blue:route:error:areaInitializationFailed", o), n.context.on("blue:route:error:areaInitialLoadFailed", o), n.context.on("blue:route:error:areaLoadTimeout", o), n.context.on("blue:route:error:areaNotFound", o), n.context.on("blue:route:error:areaNotLoaded", o), n.context.on("blue:route:error:controllerNotFound", o)
        }, this.createInitialSpinners = function () {
            this.registerAndCav({
                id: "default-spinner_1",
                name: "",
                dynamicPosition: !1,
                classes: "spinner-fullscreen",
                target: d,
                spinnerId: "spinnerId_1",
                spinnerComponentName: "spinnerComponent_1",
                doNotFocus: !1
            }), this.registerAndCav({
                id: "default-spinner_2",
                name: "",
                dynamicPosition: !1,
                classes: "spinner-fullscreen",
                target: m,
                spinnerId: "spinnerId_2",
                spinnerComponentName: "spinnerComponent_2",
                doNotFocus: !1
            }), t(m + "," + d).addClass("hide"), p++;
            var e = this.registry.getComponent("spinnerComponent_2");
            e && e.model.set("accessibleText", i.get(e, "waitAda"))
        }, this.createFlyoutTargetSpinner = function () {
            this.registerAndCav({
                id: "default-spinner_3",
                name: "",
                dynamicPosition: !1,
                classes: "spinner-fullscreen",
                target: f,
                spinnerId: "spinnerId_3",
                spinnerComponentName: "spinnerComponent_3",
                doNotFocus: !1
            }), t(f).addClass("hide"), g++
        }, this.isArea = function (t) {
            if (t) {
                a.length || (a = e.areaList || []);
                var n = t.split(".");
                return a.find((function (e) {
                    return e === n[0]
                }))
            }
        }, this.registerAndCav = function (e) {
            t(e.target).length > 0 && (this.regComponent(e.spinnerComponentName, e), this.eCav(e.spinnerComponentName, e.target, !0))
        }, this.regComponent = function (e, t) {
            this.registry.hasComponent(e) || this.registry.registerComponent(e, {
                spec: n.SPINNER.spec,
                model: t,
                methods: n.SPINNER.methods
            }, !0)
        }, this.eCav = function (e, t, i) {
            this.executeCAV([this.components[e], n.SPINNER.view, {target: t, append: i}])
        }, this.isFlyout = function () {
            var e = this.context.state();
            return e.action.params && !!e.action.params.flyout
        }, this.showSpinner = function (n) {
            var a = o(n.target, m), s = [], c = o(n.containerClass, ""), l = "spinnerComponent_" + a.slice(1),
                    u = Object.keys(y).length;
            if (!e.spinnerDisabled) {
                if (!n.ignoreDefaultSpinner && (s = [a], this.isFlyout() && (s = [a, f]), s.some((function (e) {
                    return -1 !== [f, d, m].indexOf(e)
                })))) return 0 === g && -1 !== s.indexOf(f) && this.createFlyoutTargetSpinner(), t(s.join()).addClass(c).removeClass("hide"), void (-1 !== s.indexOf(m) && t("#default-spinner_2-spinner").focus());
                !function (n, o, a, s, c) {
                    var l;
                    if (!(l = b(c)) || r || o.ignoreParentSpinner) if (t(c).length) {
                        if (y[c] = a, y[c].counter++, o.overlayType) if ("FULLSCREEN" === o.overlayType) a.classes = "spinner-fullscreen", a.dynamicPosition = !1; else if ("INSECTION" === o.overlayType) {
                            t(o.target).children().length > 0 && (a.classes = "spinner-insection")
                        } else a.classes = "spinner-container spinner-insection";
                        n.regComponent(s, a);
                        var u = n.registry.getComponent(s), d = o.accessibleText || i.get(u, "waitAda");
                        u.model.set("accessibleText", d);
                        var m = u.onDestroy;
                        u.onDestroy = function () {
                            m && m.apply(u, arguments), delete y[c], e.logger.info(s, " removed from spinnerMap", y)
                        }, n.eCav(s, c, "boolean" != typeof o.append || o.append)
                    } else n.context.logger.debug("spinner:showSpinner target does not exist", c, s, y, Object.keys(y).map((function (e) {
                        return e + ":" + y[e].counter
                    }))); else y[l].counter++
                }(this, n, {
                    id: o(n.id, "default-spinner"),
                    name: o(n.name, ""),
                    type: n.overlayType,
                    dynamicPosition: n.dynamicPosition || !0,
                    classes: o(n.classes, ""),
                    target: a,
                    spinnerId: u,
                    spinnerComponentName: l,
                    doNotFocus: n.doNotFocus || !1,
                    counter: 0,
                    loadingMessage: o(n.loadingMessage, ""),
                    originalOptions: n
                }, l, a), this.context.logger.debug("spinner:showSpinner", a, Object.keys(y).map((function (e) {
                    return e + ":" + y[e].counter
                })))
            }
        }, this.hideSpinner = function (e) {
            var n = e.target || m, i = [];
            e.ignoreDefaultSpinner || (i = [n], this.isFlyout() && (i = [n, f]), !i.some((function (e) {
                return -1 !== [f, d, m].indexOf(e)
            }))) ? (y[n] || (n = b(n) || n), this.context.logger.debug("spinner:hideSpinner", n, Object.keys(y).map((function (e) {
                return e + ":" + y[e].counter
            }))), function (e, n, i) {
                if (y[i] && (!y[i].name || "any" === n.name || y[i].name === n.name || !e.isArea(n.name) || y[i].counter > 1)) {
                    y[i].counter--, y[i].name === n.name && (y[i].name = "");
                    var r = o(y[i] && y[i].spinnerComponentName, "");
                    y[i].counter < 1 && e.registry.isComponent(r) && (e.registry.destroyComponent(r).then((function () {
                        n.callback && n.callback(), n.focusTarget && t(n.focusTarget).focus()
                    })), e.context.logger.debug("spinner:destroyed", i, Object.keys(y).map((function (e) {
                        return e + ":" + y[e].counter
                    }))))
                }
            }(this, e, n)) : t(i.join()).removeClass().addClass("hide")
        }
    }
})), define("common/service/helpers/requestHeader", ["require", "blue-app/settings", "blue/store/enumerable/cookie"], (function (e) {
    var t = e("blue-app/settings"), n = new (e("blue/store/enumerable/cookie"))("scenario");
    return {
        getHeader: function () {
            var e = {}, i = t.get("e2eScenario", t.Type.PERM);
            return (i || (i = n.get("e2eScenario"))) && (e["x-jpmc-scenario"] = "id=" + i.scenarioIdFixture + "; ts=" + i.scenarioDateTimeFixture), e
        }, getTimeoutSettings: function () {
            return 1e4
        }
    }
})), define("common/interceptor/request", ["require", "blue-app/with/locationAPI", "common/service/helpers/requestHeader", "blue-app/settings"], (function (e) {
    var t = e("blue-app/with/locationAPI"), n = e("common/service/helpers/requestHeader"), i = e("blue-app/settings");
    return function (e) {
        var o = new t;
        return e || (e = i), {
            before: function (t) {
                var i = n.getHeader();
                return Object.keys(i).length && (t.beforeSend = function (e) {
                    Object.keys(i).forEach((function (t) {
                        e.setRequestHeader(t, i[t])
                    }))
                }), e.set("authRedirectURL", o.getLocationURL(), e.Type.USER), t
            }
        }
    }
})), define("common/interceptor/serverValidationStatusInterceptor", ["require", "blue/log", "blue/http", "blue/is"], (function (e) {
    var t = new (e("blue/log"))("[serverValidationStatusInterceptor]"), n = e("blue/http"), i = e("blue/is");

    function o(e) {
        return 300 === e || 301 === e || 302 === e || 305 === e || 306 === e || 307 === e
    }

    function r(e, n, o, r) {
        var c = a(r.status);
        t.info("Service response from URL " + o.url + " contained non-success status: ", c), o.handleError && i.function(o.handleError) && o.handleError.call(r, r), o.handleStatus && o.handleStatus[s(c)] && i.function(o.handleStatus[s(c)]) && o.handleStatus[s(c)].call(r, r), n(r)
    }

    function a(e) {
        return 1 * e
    }

    function s(e) {
        return e + ""
    }

    return {
        around: function (e) {
            var s = e.args[0];
            return new Promise((function (c, l) {
                e.proceed().then((function (n) {
                    s.data.mockStatusData ? s.data.mockStatusData.resolve ? c(n) : (n.status = s.data.mockStatusData.statusCode, r(0, l, s, n)) : s.handleSuccess && i.function(s.handleSuccess) ? c(s.handleSuccess(n)) : s.handleStatus && s.handleStatus[200] && i.function(s.handleStatus[200]) ? c(s.handleStatus[200](n)) : s.statusCodeField && n[s.statusCodeField] ? (t.info("********serverValidationStatusInterceptor()**********  data." + s.statusCodeField + "=", n[s.statusCodeField]), s.handleServerSideValidation && i.function(s.handleServerSideValidation) ? (s.handleServerSideValidation(n, e), n.markAsSuccess ? c(n) : l(n)) : l(n)) : c(n)
                })).catch((function (e) {
                    o(a(e.status)) && s.redirect ? function (e, t, i, s) {
                        var c;
                        c = s.getResponseHeader("Location"), i.url = c, n.request(i).then((function (t) {
                            e(t)
                        })).catch((function (s) {
                            o(a(s.status)) ? n.request(i).then((function (t) {
                                e(t)
                            })).catch((function (s) {
                                o(a(s.status)) ? n.request(i).then((function (t) {
                                    e(t)
                                })).catch((function (n) {
                                    r(e, t, i, n)
                                })) : r(e, t, i, s)
                            })) : r(e, t, i, s)
                        }))
                    }(c, l, s, e) : s.data.mockStatusData ? s.data.mockStatusData.resolve ? c(e) : (e.status = s.data.mockStatusData.statusCode, r(0, l, s, e)) : r(0, l, s, e)
                }))
            }))
        }
    }
})), define("common/interceptor/urlInterceptor", ["require", "blue/util"], (function (e) {
    var t = e("blue/util").string.interpolate;
    return {
        around: function (e) {
            return e.args.forEach((function (e) {
                e.data && e.data.urlParams && (e.url = t(e.url, e.data.urlParams), delete e.data.urlParams), e.data && e.data.jsonRequestBody && (e.data = JSON.stringify(e.data.jsonRequestBody))
            })), e.proceed()
        }
    }
})), define("common/lib/selectedAccountUtil", ["require", "blue/siteData", "blue/is", "common/lib/constants"], (function (e) {
    "use strict";
    var t = e("blue/siteData"), n = e("blue/is"), i = e("common/lib/constants").TILE_ID;
    return {
        parseTilesIntoAccountTypes: function (e) {
            var t = this;
            (e = e || this.context.config.cache.find((function (e) {
                return "/svc/rr/accounts/secure/v4/dashboard/tiles/list" === e.url
            }))) && (e.response || e.accountTiles) ? o(e = e.response || e) : this.context.controller.services.summaryService["accounts.dashboard.summary.svc"]().then((function (e) {
                o.call(t, e)
            }))
        }, getAccountTypeById: function (e) {
            e = e || 0, n.string(e) && (e = parseInt(e));
            if (t.getData("accountTileMetadata") && t.getData("accountTileMetadata")[e]) return t.getData("accountTileMetadata")[e].accountType;
            return "PERSONAL"
        }, isStaticSummaryHeader: function (e) {
            return [i.MORTGAGE_APPLICATION, i.INVESTMENT_APPLICATION, i.CREDIT_SCORE_TILE].indexOf(e) > -1
        }
    };

    function o(e) {
        var n, i, o = (n = e.businessTileGroups, i = [], n && n.forEach((function (e) {
            e.depositAccountTileIds && (i = i.concat(e.depositAccountTileIds)), e.merchantAccountTileIds && (i = i.concat(e.merchantAccountTileIds)), e.loanAccountTileIds && (i = i.concat(e.loanAccountTileIds)), e.businessCardTileIds && (i = i.concat(e.businessCardTileIds)), e.creditCardAccountTileIds && (i = i.concat(e.creditCardAccountTileIds)), e.merchantAccountIds && (i = i.concat(e.merchantAccountIds))
        })), i), r = function (e, t, n) {
            var i = {};
            return t = t || [], e && e.forEach((function (e) {
                var o = "PERSONAL";
                t.indexOf(e.tileId) > -1 && (o = "CML" === n || "CRE" === n ? "COMMERCIAL" : "BUSINESS"), i[e.accountId] = {accountType: o}
            })), i
        }(e.accountTiles, o, t.getData("segment"));
        t.setData("accountTileMetadata", r)
    }
})), define("common/lib/accountDetailUtility", ["require", "common/lib/constants", "common/lib/selectedAccountUtil"], (function (e) {
    "use strict";
    var t = e("common/lib/constants"), n = e("common/lib/selectedAccountUtil"), i = t.PRODUCT_DESCRIPTION,
            o = t.DETAIL_TYPE, r = t.ACCOUNT_TYPE, a = t.BRAND_TYPE;
    return {
        getAccountSpecificSummaryUrl: function (e, t) {
            var s, c = [], l = i[e.detailType] || i[e.accountType] || i.INDEX, u = e.instanceName || e.accountId;
            e.controlAccountId && (u = e.accountId);
            var d = [e.accountType && e.accountType.toLowerCase(), u];
            return s = t + l, c.params = d, e.detailType === o.BUSINESS_CREDIT_CARD && e.controlAccountId ? (c.controlAccountId = e.controlAccountId + "", c.accountType = e.businessCardType) : e.detailType === o.COMMERCIAL_LOAN_OBLIGATION && e.controlAccountId && (c.controlAccountId = e.controlAccountId + ""), function (e, t) {
                return e.accountType === r.DEPOSIT && (t === a.BUSINESS || t === a.COMMERICAL || e.isBusiness)
            }(e, n.getAccountTypeById(e.accountId).toLowerCase()) && (c.accgroup = "business"), {url: s, params: c}
        }, getAccountSpecificDetailsUrl: function (e, t) {
            var n = t + (i[e.detailType] || i[e.accountType] || i.INDEX),
                    o = e.productId || e.accountType + "-" + e.detailType,
                    r = {params: [e.accountType, e.detailType, e.accountId, o]};
            return e.controlAccountId && r.params.push(e.controlAccountId), {url: n, params: r}
        }
    }
})), define("common/lib/accountInfo", ["require", "blue/siteData", "blue/log", "common/lib/constants"], (function (e) {
    "use strict";
    var t = e("blue/siteData"), n = e("blue/log")("[accountInfo]"), i = e("common/lib/constants"), o = {
        profileType: {
            personal: ["PER", "MUG"],
            gemini: ["GEM", "GNC", "GNM"],
            multiTin: ["MUL", "GNM", "GMC"],
            multiChannel: ["CHN"],
            businessBanking: ["BUS", "GNM", "GNC", "MUL", "GEM"]
        },
        segmentType: {
            commercial: ["CML", "CRE"],
            smallBusiness: ["BMG", "BMS", "BPL", "BPS", "BOH", "BOS"],
            businessBanking: ["BOH", "BMG", "BPL", "PVB", "WTH"],
            jpmSecurities: ["PCB"],
            privateBanking: ["PVB", "WTH"]
        },
        userType: {subUser: ["SU"]}
    }, r = function (e) {
        var n = t.getData("accountTypes");
        return n && n.indexOf(e) > -1
    }, a = function (e) {
        e && (this.profileType = e.profileType, this.userType = e.userType, this.segmentType = e.segmentType)
    };

    function s(e) {
        return function () {
            return this.segmentType || (this.segmentType = t.getData("segment"), this.segmentType || n.warn("Caller attempted to get segment from siteData before siteData was initialized.")), e.apply(this, arguments)
        }
    }

    return a.prototype.isBusinessUser = function () {
        return this.isCommercialUser() || this.isSmallBusinessUser()
    }, a.prototype.isPersonalUser = function () {
        return this._isAvailableinMapperList("profileType", "personal")
    }, a.prototype.isCommercialUser = s((function () {
        return this._isAvailableinMapperList("segmentType", "commercial")
    })), a.prototype.isBusinessBankingUser = s((function () {
        return this._isAvailableinMapperList("segmentType", "businessBanking")
    })), a.prototype.isBusinessBankingUserProfile = function () {
        return this._isAvailableinMapperList("profileType", "businessBanking")
    }, a.prototype.isGWMUser = s((function () {
        return this.isPrivateBankUser() || this.isJPMSecuritiesUser()
    })), a.prototype.isJPMSecuritiesUser = s((function () {
        return this._isAvailableinMapperList("segmentType", "jpmSecurities")
    })), a.prototype.isPrivateBankUser = s((function () {
        return this._isAvailableinMapperList("segmentType", "privateBanking")
    })), a.prototype.isSmallBusinessUser = s((function () {
        return this._isAvailableinMapperList("segmentType", "smallBusiness")
    })), a.prototype.isGeminiUser = function () {
        return this._isAvailableinMapperList("profileType", "gemini")
    }, a.prototype.isMultiTinUser = function () {
        return this._isAvailableinMapperList("profileType", "multiTin")
    }, a.prototype.isFinancialAdvisor = function () {
        return t.getData("userType") && t.getData("userType").indexOf("FinancialAdvisor") > -1
    }, a.prototype.isBrokerage2 = function () {
        return r("BR2")
    }, a.prototype.isManagedBrokerage = function () {
        return r("WR2")
    }, a.prototype.isMargin = function () {
        return r("MAR")
    }, a.prototype.isOlympic = function () {
        return r("MAN")
    }, a.prototype.isMultiChannelUser = function () {
        return this._isAvailableinMapperList("profileType", "multiChannel")
    }, a.prototype.isSubUser = function () {
        return this._isAvailableinMapperList("userType", "subUser")
    }, a.prototype.getSegment = s((function () {
        return this.segmentType
    })), a.prototype._isAvailableinMapperList = function (e, t) {
        return -1 !== o[e][t].indexOf(this[e])
    }, a.prototype.isEligibleForRealTimeData = function () {
        return this.isBrokerage2() || this.isManagedBrokerage() || this.isMargin() || this.isOlympic()
    }, a.prototype.isMerchantServicesUser = function () {
        return r(i.DETAIL_TYPE.MERCHANT)
    }, a
})), define("common/utility/dynamicAjaxCallUtil", ["blue/http"], (function (e) {
    return function (t) {
        return new Promise((function (n, i) {
            e.request(t).then((function (e) {
                n(e)
            })).catch((function (e) {
                i(e)
            }))
        }))
    }
})), define("common/utility/properCaseForNames", [], (function () {
    "use strict";

    function e(e) {
        return null == e ? "" : e.toString()
    }

    function t(t) {
        return (t = e(t)).toUpperCase()
    }

    return function (n) {
        return function (t) {
            return (t = e(t)).toLowerCase()
        }(n = e(n)).replace(/^\w|\s\w|[-'](?=[a-zA-z])[a-zA-z]/g, t)
    }
})), define("common/utility/rapidash", ["require", "blue/util", "blue/$", "common/utility/properCaseForNames"], (function (e) {
    "use strict";
    var t = e("blue/util"), n = e("blue/$"), i = e("common/utility/properCaseForNames"), o = function (e) {
        var n = t.array[e], i = t.object[e];
        return function (e) {
            var t = [].slice.call(arguments);
            return e = t[0], Array.isArray(e) ? n.apply(this, t) : "object" == typeof e ? i.apply(this, t) : void 0
        }
    }, r = function (e, t) {
        if (Array.isArray(e)) e.forEach(t); else if ("object" == typeof e) for (var n in e) {
            if (Object.hasOwnProperty.call(e, n)) t(n, e[n])
        }
    };
    return {
        combine: t.array.combine,
        compact: t.array.compact,
        difference: t.array.difference,
        intersection: t.array.intersection,
        invoke: t.array.invoke,
        range: t.array.range,
        sort: t.array.sort,
        union: t.array.union,
        unique: t.array.unique,
        toHtmlString: function (e) {
            var t = '<ul class="account-service-list">';
            return e.forEach((function (e) {
                t += "<li>", t += e, t += "</li>"
            })), t += "</ul>"
        },
        objToHtmlString: function (e, t) {
            var n = '<ul class="account-service-list">';
            return e.forEach((function (e) {
                n += "<li>", n += e[t], n += "</li>"
            })), n += "</ul>"
        },
        flatten: function (e) {
            var t = [];
            return e.forEach((function (e) {
                Array.isArray(e) ? t = t.concat(e) : t.push(e)
            })), t
        },
        findByKey: function (e, n, i) {
            return t.array.find(e, (function (e) {
                return e[n] === i
            }))
        },
        compose: t.function.compose,
        debounce: t.function.debounce,
        partial: t.function.partial,
        prop: t.function.prop,
        series: t.function.series,
        times: t.function.times,
        wrap: t.function.wrap,
        equals: t.object.equals,
        every: t.object.every,
        extend: t.object.extend,
        filter: t.object.filter,
        find: t.object.find,
        functions: t.object.functions,
        get: t.object.get,
        has: t.object.has,
        map: t.object.map,
        merge: t.object.merge,
        set: t.object.set,
        unset: t.object.unset,
        deepCopy: function (e) {
            var t = {};
            return n.extend(!0, t, e), t
        },
        restructureObject: function (e) {
            if ("object" == typeof e) {
                var n = {};
                return Object.keys(e).forEach((function (i) {
                    "object" != typeof t.object.get(n, i) && t.object.set(n, i, e[i])
                })), n
            }
            return e
        },
        intersectObjects: function (e, t) {
            r(t, (function (n) {
                e[n] || (t[n] = null)
            }))
        },
        values: function (e) {
            return Object.keys(e).reduce((function (t, n) {
                return e.hasOwnProperty(n) && t.push(e[n]), t
            }), [])
        },
        bindMethods: function (e) {
            Object.getOwnPropertyNames(e.constructor.prototype).forEach((function (t) {
                "function" == typeof e[t] && (e[t] = e[t].bind(e))
            }))
        },
        subclass: function (e, t) {
            return t.prototype = Object.create(e.prototype), t.prototype.constructor = t, t
        },
        camelCase: t.string.camelCase,
        escapeHtml: t.string.escapeHtml,
        hyphenate: t.string.hyphenate,
        interpolate: t.string.interpolate,
        makePath: t.string.makePath,
        properCase: t.string.properCase,
        properCaseForNames: i,
        removeNonASCII: t.string.removeNonASCII,
        removeNonWord: t.string.removeNonWord,
        stripHtmlTags: t.string.stripHtmlTags,
        truncate: t.string.truncate,
        typecast: t.string.typecast,
        unCamelCase: t.string.unCamelCase,
        unescapeHtml: t.string.unescapeHtml,
        unhyphenate: t.string.unhyphenate,
        snakeCase: function (e) {
            return t.string.unCamelCase(e, "_")
        },
        lispCase: function (e) {
            return t.string.unCamelCase(e, "-")
        },
        max: o("max"),
        min: o("min"),
        pick: o("pick"),
        pluck: o("pluck"),
        forEach: r,
        serialize: function (e, n, i) {
            var o = {};
            i.forEach((function (e, t) {
                o[n + "[" + t + "]"] = e
            })), t.object.extend(!1, e, o)
        },
        update: function (e, t) {
            if ("string" == typeof t) throw new Error("type error: function `update` argument `object` must be type `object`");
            var n;
            for (var i in t) if (t.hasOwnProperty(i)) if (Array.isArray(t[i])) {
                n = t[i], e.set(i, []);
                for (var o = 0; o < n.length; o++) e.set(i + "." + o, n[o])
            } else e.set(i, t[i])
        },
        isObject: function (e) {
            return null != e && "object" == typeof e && !Array.isArray(e)
        },
        toObject: function (e) {
            void 0 === Function.prototype.name && Object.defineProperty(Function.prototype.name, {
                get: function () {
                    return /function ([^(]*)/.exec(this + "")[1]
                }
            });
            var t = {};
            return e.forEach((function (e) {
                "function" == typeof e ? t[e.name] = e : t[e] = e
            })), t
        },
        setValueOrDefault: function (e, t) {
            return void 0 === e && (e = t || ""), e
        }
    }
})), define("common/utility/dynamicContentUtil", ["require", "blue-app/settings", "blue/util", "blue/is", "common/utility/rapidash", "appkit-utilities/content/dcu", "blue/log", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue-app/settings"), n = e("blue/util").string.interpolate, i = e("blue/is"),
            o = e("common/utility/rapidash"), r = e("appkit-utilities/content/dcu"), a = e("blue/log")("[DCU]"),
            s = e("blue/util").lang.defaults, c = null, l = "LOCALIZED_CONTENT";

    function u(e) {
        var t;
        return t = l, o.has(e, "context.area.areaName") ? t = l + "_" + e.context.area.areaName.toString().toLowerCase() : e.area && (t = l + "_" + e.area.name.toString().toLowerCase()), t
    }

    function d(e) {
        return o.has(e, "spec.name") ? e.spec.name : e.context ? e.context.componentName : e.name
    }

    function m(e, t, n) {
        if (e && e[t] && e[t][n]) return e
    }

    function f(e, n, r) {
        var a, f, p, g, y, h;
        return a = t.get(l + "_app"), f = t.get(u(e)), p = n, r && (p = n + "." + r), g = d(e), (y = s(m(f, g, p), m(a, g, p))) && (h = o.get(y[g], p), i.defined(h) && null !== h || (h = y[g][p])), i.defined(h) && null !== h || (c || (c = o.restructureObject(t.get(l, t.Type.APP))), h = (y = s(c, y)) ? o.get(y, g + "." + p) : null), h
    }

    return {
        settings: t,
        settingsKey: l,
        getAreaLocaleKey: u,
        getSpecName: d,
        getLocaleContent: f,
        dynamicSettings: {
            get: function (e, t, n) {
                return f(e, t, n)
            }, set: function (e, t, n) {
                var i = f(e, t, n);
                return e.model.set(t, i), i
            }
        },
        dynamicContent: {
            get: function (e, t, n, i) {
                var o;
                "" === i && (i = void 0);
                try {
                    o = r.dynamicContent.get(e, t, i, n)
                } catch (n) {
                    o = f(e, t, i), a.warn("Error fetching content from appkit-utilities/dcu.js for  " + t + "." + i, n)
                }
                return o
            }, set: function (e, t, i, o) {
                var s;
                "" === o && (o = void 0);
                try {
                    s = r.dynamicContent.set(e, t, o, i)
                } catch (r) {
                    s = n(f(e, t, o), i), a.warn("Error setting content in appkit-utilities/dcu.js for  " + t + "." + o, r)
                }
                return s
            }, setForBinding: function (e, t, i, o) {
                e[i] = n(f(e, t), o)
            }
        },
        getContentFor: function (e, t, n) {
            return this.dynamicSettings.get(e, t, n)
        },
        selectContentFor: function (e, t, n) {
            return this.dynamicSettings.set(e, t, n)
        },
        getDynamicContentsQuick: function (e, t, n, i, o) {
            var r = {area: {name: e}, spec: {name: t}};
            return this.dynamicContent.get(r, n, o, i)
        }
    }
})), define("common/lib/dateFormatterUtility", ["require", "common/lib/constants", "moment", "common/utility/dynamicContentUtil"], (function (e) {
    "use strict";
    var t = e("common/lib/constants"), n = e("moment"), i = e("common/utility/dynamicContentUtil");
    return {
        getGlobalFormattedDate: function (e, o) {
            var r, a = this.getNotAvailable("HEL_ACCOUNT", "accounts");
            if (this.isDefined(e)) {
                var s = n(e).format("YYYYMMDD"), c = this.getComponentArea("GLOBAL", "app");
                r = i.dynamicSettings.get(c, "monthAbbreviation", t.MONTH[s.substr(4, 2)].toUpperCase()) + " " + Number(s.substr(6, 2)) + ", " + s.substr(0, 4)
            }
            return r || (void 0 === o ? a : o)
        }, convertDate: function (e) {
            return 8 === e.length ? e.substr(4, 2) + t.SYMBOLS.SLASH + e.substr(6, 2) + t.SYMBOLS.SLASH + e.substr(0, 4) : (e = new Date(e)).getMonth() + 1 + t.SYMBOLS.SLASH + e.getDate() + t.SYMBOLS.SLASH + e.getFullYear()
        }, convertDateMonthYear: function (e) {
            return 8 === e.length || 6 === e.length ? e.substr(4, 2) + t.SYMBOLS.SLASH + e.substr(0, 4) : (e = new Date(e)).getMonth() + 1 + t.SYMBOLS.SLASH + e.getFullYear()
        }, convertDateYearMonth: function (e) {
            return 8 === e.length || 6 === e.length ? e.substr(0, 4) + t.SYMBOLS.SLASH + e.substr(4, 2) : (e = new Date(e)).getMonth() + 1 + t.SYMBOLS.SLASH + e.getFullYear()
        }, asOfDateWithHrs: function (e) {
            var t, n = new Date(e);
            return (t = (t = n.getHours()) < 12 ? t + "AM " : 12 === t ? t + "PM " : t - 12 + "PM ") + (n + "").split(" ").pop().replace(/\(|\)/g, "") + ", " + this.convertDate(e)
        }, splitMMDDYYYY: function (e) {
            return 8 === e.length ? t.MONTH[e.substr(4, 2)] + " " + Number(e.substr(6, 2)) + ", " + e.substr(0, 4) : e.substr(5, 2) + t.SYMBOLS.SLASH + e.substr(2, 2) + t.SYMBOLS.SLASH + e.substr(0, 4)
        }, asOfDateWithMM: function (e) {
            var n;
            return n = (n = (e = new Date(e)).getMonth() + 1) < 10 ? t.NUMBER_ZERO + n : n, this.splitMMDDYYYY(e.getFullYear() + "" + n + e.getDate())
        }, getFormattedDate: function (e) {
            var n = e.getFullYear(), i = (1 + e.getMonth()).toString();
            i = i.length > 1 ? i : t.NUMBER_ZERO + i;
            var o = e.getDate().toString();
            return o = o.length > 1 ? o : t.NUMBER_ZERO + o, i + t.SYMBOLS.SLASH + o + t.SYMBOLS.SLASH + n
        }, getFormattedDateYYYYMMDD: function (e) {
            var n = new Date(e), i = n.getFullYear(), o = (1 + n.getMonth()).toString();
            o = o.length > 1 ? o : t.NUMBER_ZERO + o;
            var r = n.getDate().toString();
            return i + o + (r = r.length > 1 ? r : t.NUMBER_ZERO + r)
        }, getFormattedDateYYYYMM: function (e) {
            var n = new Date(e), i = n.getFullYear(), o = (1 + n.getMonth()).toString();
            return i + (o = o.length > 1 ? o : t.NUMBER_ZERO + o)
        }, asOfDateFullYear: function (e) {
            return new Date(e).getFullYear()
        }, getDayOnly: function (e) {
            return Number(e.substr(6, 2))
        }, addMonthsInEST: function (e, t) {
            var n = new Date(t);
            return n.setMonth(n.getMonth() + e), n
        }, addDaysInEST: function (e, t) {
            var n = new Date(t);
            return n.setDate(n.getDate() + e), n
        }, addMonths: function (e) {
            var t = new Date;
            return t.setMonth((new Date).getMonth() + e), t
        }, addDays: function (e) {
            var t = new Date;
            return t.setDate((new Date).getDate() + e), t
        }, dateFormatter: function (e, o, r) {
            var a, s;
            switch (a = e ? n(e, o) : n(), r) {
                case"obj":
                    s = {date: a.get("D"), month: a.get("M"), year: a.get("y")};
                    break;
                case"longFormat":
                    s = i.getDynamicContentsQuick("app", "GLOBAL", "month", t.MONTHS[a.get("M")]) + " " + a.get("D") + ", " + a.get("y");
                    break;
                case"shortFormat":
                    s = i.getDynamicContentsQuick("app", "GLOBAL", "monthAbbreviation", t.MONTHS[a.get("M")]) + " " + a.get("D") + ", " + a.get("y");
                    break;
                default:
                    s = a
            }
            return s
        }, getAsOfDateAndTime: function (e, t) {
            var n;
            (n = this.getEasternTime(e) + " " + i.getDynamicContentsQuick("app", "GLOBAL", "timeZoneAbbreviation", "EASTERN_TIME"), t) && (n = n + ", " + this.dateFormatter(e, null, "shortFormat"));
            return n
        }, getEasternTime: function (e) {
            var t = e ? n(e) : null;
            return t ? t.utcOffset(t.isDST() ? -4 : -5).format("h:mm A") : null
        }, getMinutes: function (e) {
            return "(" + Math.floor(e / 60) + ":" + ("0" + e % 60).substr(-2) + ")"
        }, getDateMMDDYYYY: function (e, t) {
            var n = this.getNotAvailable("HEL_ACCOUNT", "accounts");
            return this.isDefined(e) ? this.splitMMDDYYYY(e) : void 0 === t ? n : t
        }
    }
})), define("common/lib/formatter", ["require", "mout/number/currencyFormat", "moment", "moment-timezone", "mout/string/properCase", "common/utility/dynamicContentUtil", "appkit-utilities/content/dcu", "common/lib/constants", "mout/string", "blue/log", "blue/util"], (function (e) {
    "use strict";
    var t = e("mout/number/currencyFormat"), n = e("moment"), i = e("moment-timezone"), o = e("mout/string/properCase"),
            r = e("common/utility/dynamicContentUtil"), a = e("appkit-utilities/content/dcu"),
            s = e("common/lib/constants"), c = e("mout/string"), l = null, u = e("blue/log")("[lib/common/formatter]"),
            d = e("blue/util").lang.defaults;

    function m(e, t) {
        return a.dynamicContent.getGlobal(e, t)
    }

    function f(e, t) {
        var n = t ? '<span class="util accessible-text">' + t + "</span>" : "";
        return l || (l = {
            hyphen: m("hyphenSymbol"),
            doubleHyphen: m("doubleHyphenSymbol"),
            defaultNull: m("notAvailableLabel"),
            emDash: m("emDash")
        }), l[e] + n
    }

    function p(e) {
        return void 0 !== e
    }

    function g(e) {
        var t = n(e), i = t.isDST() ? -4 : -5;
        return t.utc().utcOffset(i)
    }

    function y(e, n, i, o, r, a, c) {
        i = !p(i) || i, n = d(n, 2), o = d(o, "defaultNull"), r = !!p(r) && r;
        var l = c && c.isEnglishOnly ? c.defaultContent : f(o, a);
        if (p(e) && function (e) {
            return null !== e
        }(e) && "" !== e) {
            var u = e < 0;
            if (u && (e *= -1), l = t(e, n), i) l = d(m("currencyDollarSymbol"), "$") + l;
            u ? l = s.SYMBOLS.MINUS_SIGN + l : r && e > 0 && (l = s.SYMBOLS.PLUS + l)
        }
        return l
    }

    function h(e, t, n, i) {
        t = t || "BODYPOS", n = n || "BODYNEG";
        var o = i = i || "BODY", r = Number(e);
        return isNaN(r) || 0 === r || (o = r > 0 ? t : n), o
    }

    function v(e) {
        var t = "", n = Number(e);
        isNaN(n) || 0 === n || (t = ' <span class="util accessible-text">' + (t = m(n > 0 ? "gainAda" : "lossAda")) + "</span>");
        return t
    }

    function b(e, t) {
        if (e) {
            var n = m("openParenthesesSymbol"), i = m("closeParenthesesSymbol");
            return n + (t = t || "") + (e.length > 4 ? "" : m("horizontalEllipsisSymbol")) + e + i
        }
    }

    return {
        percentageFormat: function (e, t, n, i) {
            if (n = p(n) ? n : "defaultNull", e = parseFloat(e).toFixed(2), isNaN(parseInt(e))) return f(n, i);
            switch (!0) {
                case e >= 0:
                    return t ? s.SYMBOLS.OPENPARENTHESES + e + s.SYMBOLS.PERCENT + s.SYMBOLS.CLOSEPARENTHESES : e + s.SYMBOLS.PERCENT;
                case e < 0:
                    return t ? s.SYMBOLS.OPENPARENTHESES + s.SYMBOLS.MINUS_SIGN + parseFloat(-1 * e).toFixed(2) + s.SYMBOLS.PERCENT + s.SYMBOLS.CLOSEPARENTHESES : s.SYMBOLS.MINUS_SIGN + parseFloat(-1 * e).toFixed(2) + s.SYMBOLS.PERCENT
            }
        },
        decimalFormatterWithSign: function (e, n, i, o, r) {
            var a = f(o, r);
            return e = Number(e), isNaN(e) || (a = t(e, n), i && e > 0 && (a = s.SYMBOLS.PLUS + a)), a
        },
        getTimeFromTimestamp: function (e, t, i) {
            var o = n(e);
            if (!o.isValid()) return f(i);
            var r = m("timeZoneAbbreviation", "EASTERN_TIME");
            switch (t) {
                case"HH:mm:ss":
                    return o.format("HH:mm:ss");
                case"h:mm:ss a":
                    return o.format("h:mm:ss a");
                case"withET":
                    return (o = n(e).tz("America/New_York")).format("HH:mm:ss") + r;
                case"h:mm A ET":
                    return (o = n(e).tz("America/New_York")).format("h:mm A [" + r + "]");
                case"dateTimeET":
                    return (o = n(e).tz("America/New_York")).format("h:mm A [" + r + "] MM/DD/YYYY");
                case"dateET":
                    return (o = n(e).tz("America/New_York")).format("MM/DD/YYYY");
                case"dateTimeETTimezone":
                    return (o = n(e).tz("America/New_York")).format("MMM D, YYYY, h:mm A [" + r + "]")
            }
        },
        getFormattedDate: function (e, t, i, o) {
            if (!e || !n(e).isValid()) return f(i, o);
            var r = n(e);
            switch (t) {
                case"MM/YYYY":
                    return n(e).format("MM/YYYY");
                case"MM/DD/YYYY":
                    return n(e).format("MM/DD/YYYY");
                case"MMM Do YYYY":
                    return n(e).format("ll");
                case"MMM D YYYY":
                    return m("monthAbbreviation", s.monthArray[r.get("M")]) + " " + r.get("D") + ", " + r.get("y");
                case"MMMM D YYYY":
                    return m("month", s.monthArray[r.get("M")]) + " " + r.get("D") + ", " + r.get("y")
            }
        },
        changeDateFormat: function (e, t, i) {
            if (void 0 !== e && void 0 !== t) return void 0 === i ? n(e).format(t) : n(e, i, !0).format(t);
            u.error("function changeDateFormat needs parameters. You provided date: " + e + " and formatRequired: " + t)
        },
        dateFormatConverter: function (e) {
            var t, n;
            return 8 === e.length ? (t = e.substr(0, 4), n = e.substr(4, 2) + "/" + e.substr(6, 2) + "/" + t) : n = e, n
        },
        showDateTime: function (e) {
            var t = n(e).isDST() ? "EDT" : "EST";
            return g(e).format("LT [" + t + "] L")
        },
        showDateTimeET: function (e) {
            var t = m("timeZoneAbbreviation", "EASTERN_TIME");
            return t = t || "ET", g(e).format("h:mm A [" + t + "] MM/DD/YYYY")
        },
        showTimeET: function (e) {
            var t = m("timeZoneAbbreviation", "EASTERN_TIME");
            return t = t || "ET", g(e).format("h:mm A [" + t + "]")
        },
        getSignClassName: h,
        formatPercentageChange: function (e, t, n) {
            var i = s.SYMBOLS.OPENPARENTHESES + f(t, n) + s.SYMBOLS.CLOSEPARENTHESES;
            return isNaN(parseInt(e)) || (i = s.SYMBOLS.OPENPARENTHESES + parseFloat(e).toFixed(2) + s.SYMBOLS.PERCENT + s.SYMBOLS.CLOSEPARENTHESES), i
        },
        currencyFormatter: y,
        getGainLossADA: v,
        properCase: o,
        upperCase: c.upperCase,
        getContentFromGlobal: m,
        isValueDefined: function (e, t, n) {
            return e || f(t, n)
        },
        numberPercentageFormat: function (e, n, i) {
            var o = f(i);
            return e = Number(e), isNaN(e) || (o = t(e, n) + s.SYMBOLS.PERCENT), o
        },
        rewardPointsFormatter: function (e) {
            var n = m("notAvailableLabel"), i = m("pointsAbbreviationLabel");
            return e = parseInt(e), isNaN(e) || (n = t(e, 0) + " " + i), n
        },
        getContentForKey: function (e, t) {
            return t[e] || ""
        },
        formatMaskNumber: b,
        addEllipsisToMaskNumber: function (e) {
            var t = s.MASK_DOTS;
            return e && -1 === e.indexOf(t) ? t + e : e
        },
        getAccountDisplayWithMask: function (e, t) {
            return t && (t = t.toString().replace(s.MASK_X, "")), e + " " + b(t)
        },
        getAccountName: function (e, t, n, i) {
            return e === s.ACCOUNT_TYPE.BUSINESS_CREDIT_CARD && t ? i.toUpperCase() : n
        },
        setInvestmentChangeValue: function (e, t, n, i, o, r) {
            return '<span class="' + h(e) + '"> ' + v(e) + y(e, t, n, i, o, r) + "</span>"
        },
        noFormatter: function (e) {
            var t = m("notAvailableLabel");
            return p(e) && (t = e), t
        },
        getInvestmentsMonthNickname: function (e) {
            var t = n(e);
            return a.dynamicContent.get({
                spec: {name: "ACCOUNT_SUMMARY"},
                area: {name: "accounts"}
            }, "monthNickname", s.monthArray[t.get("M")]) + " " + t.get("D") + ", " + t.get("y")
        },
        getETDateTime: function (e) {
            var t = m("timeZoneAbbreviation", "EASTERN_TIME");
            return n.parseZone(e).format("MMM D, YYYY, h:mm A [" + t + "]")
        },
        getContentFromSpec: function (e, t, n, i) {
            return r.getDynamicContentsQuick(e, t, n, i)
        },
        getRewardsFormatter: function (e) {
            return e = parseInt(e), isNaN(e) || (e = t(e, 0)), e
        },
        getFormattedESTDate: function (e, t) {
            var o = n(e);
            return o && i.tz && i.tz(o, s.TIME_ZONES.America_New_York).format(t)
        }
    }
})), define("common/lib/accountsUtility", ["require", "common/lib/constants", "common/utility/dynamicAjaxCallUtil", "appkit-utilities/content/dcu", "appkit-utilities/language/helper", "blue/util", "common/lib/dateFormatterUtility", "blue/siteData", "common/lib/formatter"], (function (e) {
    "use strict";
    var t = e("common/lib/constants"), n = e("common/utility/dynamicAjaxCallUtil"), i = t.DETAIL_TYPE,
            o = t.ACCOUNT_TYPE, r = t.SYMBOLS.DOLLAR, a = t.SYMBOLS.PERCENT, s = t.PRODUCT_DESCRIPTION,
            c = t.SYMBOLS.COPYRIGHT, l = t.DATE_FORMATS, u = e("appkit-utilities/content/dcu"),
            d = e("appkit-utilities/language/helper"), m = e("blue/util").object.extend,
            f = e("common/lib/dateFormatterUtility"), p = e("blue/siteData"), g = e("common/lib/formatter");

    function y(e, n) {
        return e = (e = function (e) {
            return e.sort((function (e, n) {
                var i = t.JUMBO_ACCOUNT_DETAIL_TYPES_ORDER.indexOf(b(e)),
                        o = t.JUMBO_ACCOUNT_DETAIL_TYPES_ORDER.indexOf(b(n));
                return i > o ? 1 : i < o ? -1 : e.accountId < n.accountId ? 1 : e.accountId > n.accountId ? -1 : 0
            }))
        }(e)).slice(0, n)
    }

    function h(e) {
        return e.controlCard || e.isControlCard
    }

    function v(e) {
        return b(e) === t.DETAIL_TYPE.BUSINESS_CREDIT_CARD
    }

    function b(e) {
        return e && (e.detailType || e.accountDetailType)
    }

    function A(e) {
        return e.filter((function (e) {
            return function (e) {
                return ((n = e) && (n.accountType || n.accountCategoryType)) !== t.ACCOUNT_TYPE.INVESTMENT;
                var n
            }(e)
        }))
    }

    function E(e) {
        return e.filter((function (e) {
            return function (e) {
                return !!e && t.MERCHANT_FUNDED_OFFERS_VALID_DETAIL_TYPES.indexOf(b(e)) > -1
            }(e)
        }))
    }

    function T(e) {
        return e && (e.id || e.accountId)
    }

    function C(e) {
        return e && (e.accountMaskNumber || e.mask)
    }

    function S(e) {
        return h(e) && v(e) && (e.accountName = t.BCC_CONTROL_CARD_NAME), e && (e.accountName || e.accountDisplayName || e.nickname)
    }

    function _(e) {
        if (e.hasOwnProperty("isBusinessAccount")) return e.isBusinessAccount;
        var n = p.getData("accountCategoryMetadata") || p.getData("accountTileMetadata"), i = T(e);
        return n && n[i] && (n[i].accountType === t.BRAND_ID.BUSINESS || n[i].isBusiness)
    }

    function O(e, t) {
        return {spec: {name: e}, area: {name: t}}
    }

    return m(f, {
        isDefined: function (e) {
            return "object" == typeof e ? null !== e && 0 !== Object.getOwnPropertyNames(e).length : null != e && "" !== e
        }, getFormattedData: function (e, t, n) {
            var i = {
                asOfDateFullYear: this.asOfDateFullYear,
                titleCase: this.getTitleCase,
                dayOnly: f.getDayOnly,
                integerFormat: parseInt,
                interestFormated: this.interestFormated,
                floatFormat: parseFloat,
                phoneNumber: this.getPhoneNumber,
                maskData: this.getMaskData,
                stateFormatter: this.stateFormatter,
                numberSuffixDayOnly: this.getNumberSuffixDayOnly,
                lastYear: this.getLastYear
            };
            return this.isDefined(e) ? i[t](e) : n
        }, interestFormated: function (e) {
            return 100 * e % 1 != 0 ? e + "%" : e.toFixed(2) + "%"
        }, getLastYear: function (e) {
            return parseFloat(e) - 1
        }, getNumberSuffixDayOnly: function (e) {
            return f.getNumberSuffix(f.getDayOnly(e))
        }, getAppendedData: function (e, t, n, i) {
            return this.isDefined(e) ? t + e + n : i
        }, getMaskData: function (e) {
            return e.toUpperCase().replace(t.X, t.MASK_DOTS)
        }, objectClone: function (e) {
            return JSON.parse(JSON.stringify(e))
        }, getAccountBalanceLabel: function (e) {
            var t, n = function () {
                t = this.getLoanAccountBalanceLabel(e.detailType, e.multiCurrency)
            }, r = {};
            return r[o.DEPOSIT] = function () {
                t = e.zeroBalanceAccount ? "accountZeroBalanceLabel" : e.detailType !== i.CERTIFICATE_OF_DEPOSIT && e.detailType !== i.INDIVIDUAL_RETIREMENT_ARRANGMENT ? "accountBalanceLabel" : "accountPrincipalBalanceLabel"
            }, r[o.CARD] = function () {
                t = e.showSubAccountBalanceLabel ? "accountAvailableCreditBalanceLabel" : "accountCurrentBalanceLabel"
            }, r[o.MERCHANT] = function () {
                t = "merchantServicesTotalSalesLabel"
            }, r[o.LOAN] = n, r[o.MORTGAGE] = n, r[o.AUTOLOAN] = n, r[o.AUTOLEASE] = n, r[o.INVESTMENT] = function () {
                t = "accountValuationLabel"
            }, r[e.accountType] && r[e.accountType].call(this), t
        }, getLoanAccountBalanceLabel: function (e, t) {
            var n;
            return n = "nextPaymentDueAmountLabel", [i.BUSINESS_LOAN_ACCOUNT, i.HOME_EQUITY_LINE, i.REVOLVING_CREDIT, i.BUSINESS_REVOLVING_CREDIT, i.CREDIT_REVOLVING_FACILITY].indexOf(e) > -1 ? n = e === i.CREDIT_REVOLVING_FACILITY && t ? "estimatedCurrentAccountBalanceLabel" : "accountCurrentBalanceLabel" : function (e) {
                var t = [i.COMMERCIAL_LOAN_FACILITY, i.COMMERCIAL_LOAN_OBLIGATION, i.COMMERCIAL_TERM_LOAN],
                        n = [i.ABLE];
                return t.indexOf(e) > -1 || n.indexOf(e) > -1
            }(e) && (n = "loanCurrentBalanceLabel"), n
        }, getTileSummaryData: function (e, t) {
            return e = this.updateObjectProperties(t, e, {
                isShowAccountAlert: "showAccountAlert",
                isLocked: "locked",
                creditCardLockStatus: "creditCardLockStatus",
                isClosed: "closed",
                isShowEscrowDetails: "showEscrowDetails",
                isPastDue: "pastDueFlag",
                isShowActivity: "showActivity",
                isProductTraded: "productTrade",
                isNewlyTraded: "newlyProductTraded",
                isShowReviewAccount: "reviewAccount",
                ultimateRewardsEarned: "balance",
                isBreachedAccount: "breached",
                isReplacementAccount: "activityItemsPendingReview",
                accountValuationDate: "asOf",
                multiCurrency: "multiCurrency",
                dueStatus: "dueStatus"
            })
        }, updateObjectProperties: function (e, t, n) {
            var i = e.tileDetail;
            return Object.keys(n).forEach((function (o) {
                this.isDefined(e[n[o]]) && (t[o] = e[n[o]]), i && this.isDefined(i[n[o]]) && (t[o] = i[n[o]])
            }), this), t
        }, mergeObject: function (e, t, n) {
            return n = n || Object.keys(t), t && n && n.forEach((function (n) {
                this.isDefined(t[n]) && (e[n] = t[n])
            }), this), e
        }, hasValidFilterCriteria: function (e) {
            return !e || 0 === e.length
        }, isAutoSaveSavingAccount: function (e, n) {
            var i = [t.DETAIL_TYPE.SAVINGS, t.DETAIL_TYPE.MONEY_MARKET].includes(e);
            return n && (i = n !== t.ACCOUNT_BRAND_ID.FINN), i
        }, showUnfundedIndicator: function (e, n) {
            return !n && p.getData("segment") !== t.SEGMENT.COMMERCIAL.CML && p.getData("segment") !== t.SEGMENT.COMMERCIAL.CRE && e
        }, isAutoSaveCheckingAccount: function (e, n) {
            var i = [t.DETAIL_TYPE.CHECKING, t.DETAIL_TYPE.PREPAID_LITE].includes(e);
            return n && (i = n !== t.ACCOUNT_BRAND_ID.FINN), i
        }, isOnlyChecking: function (e, t) {
            return e && !t
        }, isOnlySavingOrCheckingAndSaving: function (e, t) {
            return e && t || t && !e
        }, isOnlySaving: function (e, t) {
            return t && !e
        }, getCardLogo: function (e, t, i, o) {
            var r = i.get("cardLogoJsonUrl");
            return n({type: "GET", url: o + r + e + "-" + t + ".json"})
        }, getCardArt: function (e, t, i, o) {
            var r = i.get("cardArtJsonUrl");
            return n({type: "GET", url: o + r + e + "/" + t + ".json"})
        }, getCardLogoBrandingAda: function (e) {
            var n, i = "", o = t.CARDS_LOGO_ADA;
            if (e && (0 === e.indexOf("INK") ? n = "INK_REWARDS" : o[e] && (n = o[e]), n)) {
                var r = O("GLOBAL", "app");
                i = u.dynamicContent.get(r, "accountLogoBrandingAda", n)
            }
            return i
        }, createApplyFilterErrorMsg: function (e) {
            var n = {}, i = {}, o = function () {
                n[t.FILTER_FIELDS_ID.DATE_RANGE] = 1
            }, r = function () {
                n[t.FILTER_FIELDS_ID.AMOUNT_RANGE] = 1
            }, a = function () {
                n[t.FILTER_FIELDS_ID.CHECK_RANGE] = 1
            }, s = function () {
                n[t.FILTER_FIELDS_ID.CARD_MASK_NUMBER] = 1
            };
            return i[t.FILTER_FIELDS_ID.FROM_DATE] = o, i[t.FILTER_FIELDS_ID.TO_DATE] = o, i[t.FILTER_FIELDS_ID.MS_FROM_DATE] = o, i[t.FILTER_FIELDS_ID.MS_TO_DATE] = o, i[t.FILTER_FIELDS_ID.MS_FROM_MONTH] = o, i[t.FILTER_FIELDS_ID.MS_TO_MONTH] = o, i[t.FILTER_FIELDS_ID.FROM_AMOUNT] = r, i[t.FILTER_FIELDS_ID.TO_AMOUNT] = r, i[t.FILTER_FIELDS_ID.MIN_AMOUNT] = r, i[t.FILTER_FIELDS_ID.MAX_AMOUNT] = r, i[t.FILTER_FIELDS_ID.FROM_CHECK] = a, i[t.FILTER_FIELDS_ID.TO_CHECK] = a, i[t.FILTER_FIELDS_ID.MERCHANT_NAME] = function () {
                n[t.FILTER_FIELDS_ID.MERCHANT_NAME_LABEL] = 1
            }, i[t.FILTER_FIELDS_ID.CARD_NUM_FIRST_DIGITS] = s, i[t.FILTER_FIELDS_ID.CARD_NUM_LAST_DIGITS] = s, Object.keys(e).forEach((function (t) {
                var n = e[t];
                i[n] && i[n]()
            })), [t.FILTER_FIELDS_ID.DATE_RANGE, t.FILTER_FIELDS_ID.MERCHANT_NAME_LABEL, t.FILTER_FIELDS_ID.AMOUNT_RANGE, t.FILTER_FIELDS_ID.CHECK_RANGE, t.FILTER_FIELDS_ID.CARD_MASK_NUMBER].reduce((function (e, t) {
                return n[t] ? e.concat(t) : e
            }), [])
        }, convertToTwoDecimal: function (e) {
            var n = e.toString().indexOf(t.SYMBOLS.PERIOD);
            return "" !== e ? n < 0 ? e : (e = e + t.NUMBER_ZERO + t.NUMBER_ZERO).substr(0, n + 3) : ""
        }, isValidCheckAmount: function (e) {
            var t = Number(e);
            return /$/.test(e) && (e = e.trim()) && 0 === e.indexOf("$") && e.length >= 1 && (t = e.substr(1, e.length - 1)), !(isNaN(t) || t < 0 || t >= 1e11)
        }, checkAmountCurrency: function (e) {
            var t;
            return e > 0 && (t = r + this.convertToTwoDecimal(e)), t
        }, isValidCheckNumber: function (e) {
            if (/e/.test(e)) return !1;
            var n = Number(e);
            return !isNaN(e) && -1 === e.indexOf(t.SYMBOLS.PERIOD) && n > 0
        }, validateRange: function (e, t) {
            return !(e && t && Number(e) > Number(t))
        }, percentileFormat: function (e) {
            return e < 0 ? t.SYMBOLS.HYPHEN + this.convertToDecimal(-1 * e) + a : this.convertToDecimal(e) + a
        }, percentileFormatWithoutDecimal: function (e) {
            return e + a
        }, currencyFormat: function (e, n) {
            return n = this.isDefined(n) ? n : t.SYMBOLS.ENDASH, e < 0 ? n + r + this.convertToDecimal(-1 * e) : r + this.convertToDecimal(e)
        }, convertToDecimal: function (e) {
            return parseFloat(Math.abs(e), 10).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, "$&,")
        }, convertToInt: function (e) {
            return e.substr(0, e.length - 3)
        }, formatAccountMask: function (e) {
            return e ? -1 !== e.indexOf("(") ? e.replace(t.MASK_X, t.MASK_DOTS) : g.formatMaskNumber(e) : null
        }, getTitleCase: function (e) {
            return e.replace(/\w\S*/g, (function (e) {
                return e.charAt(0).toUpperCase() + e.substr(1).toLowerCase()
            }))
        }, stateFormatter: function (e) {
            return 2 === e.length ? e.toUpperCase() : e.toLowerCase().replace(/(?:^|\s)\S/g, (function (e) {
                return e.toUpperCase()
            }))
        }, checkForSixMonthsDateRange: function (e, t) {
            e = this.convertDate(e), t = this.convertDate(t);
            var n = new Date(e), i = new Date(t);
            return Math.round((i.getTime() - n.getTime()) / 864e5) < 180
        }, interestFormat: function (e) {
            return 100 * e % 1 != 0 ? e : e.toFixed(2)
        }, getPhoneNumber: function (e) {
            var n = e.match(/\d/g);
            return 10 !== (n = n.join("")).length ? null : "(" + n.substr(0, 3) + ") " + n.substr(3, 3) + t.SYMBOLS.HYPHEN + n.substr(6, 4)
        }, getNumberSuffix: function (e, n) {
            var i, o = this.getNotAvailable("HEL_ACCOUNT", "accounts");
            if (this.isDefined(e)) {
                var r = this.getComponentArea("GLOBAL", "app");
                i = u.dynamicSettings.get(r, "dayOfMonth", t.NUMBER_PREFIXES[e])
            }
            return i || (void 0 === n ? o : n)
        }, getSentenceCase: function (e) {
            return e.charAt(0).toUpperCase() + e.substr(1).toLowerCase()
        }, formateRewardPoints: function (e) {
            var n = this.getNotAvailable("ACCOUNT_REWARDS", "accounts");
            return this.isDefined(e) ? e < 0 ? t.SYMBOLS.HYPHEN + this.convertToInt(this.convertToDecimal(e)) : this.convertToInt(this.convertToDecimal(e)) : n
        }, paddingZeroes: function (e, n) {
            return new Array(Math.max(n - e.length, 0)).join(t.NUMBER_ZERO) + e
        }, getFormattedInvestmentAccountValues: function (e, n, i, o) {
            var r, a = {};
            return this.isDefined(o) && this.isDefined(o.plusSign) && this.isDefined(o.plusSign) || ((o = {}).plusSign = t.SYMBOLS.PLUS, o.minusSign = t.SYMBOLS.HYPHEN), r = o.minusSign + o.minusSign, a.accountValuation = this.getCurrency(e, r), this.isDefined(n) ? n < 0 ? (a.accountValueChange = this.currencyFormat(n, o.minusSign), a.amtClass = "BODYNEG") : n > 0 ? (a.accountValueChange = o.plusSign + this.currencyFormat(n, o.minusSign), a.amtClass = "BODYPOS") : (a.accountValueChange = o.plusSign + this.currencyFormat(n, o.minusSign), a.amtClass = "NUMSTR2") : a.accountValueChange = r, a.accountValueChangePercent = this.isDefined(i) ? this.percentileFormat(i) : r, a.accountValueChangePercent = t.SYMBOLS.OPENPARENTHESES + a.accountValueChangePercent + t.SYMBOLS.CLOSEPARENTHESES, a
        }, getTruncatedZeroesValue: function (e) {
            return e.replace(/^0+(?!\.)/, "")
        }, formatTileRewards: function (e) {
            var t;
            if (null == e) t = "--"; else {
                if (0 === e) return 0;
                e > 0 ? t = this.convertToInt(this.convertToDecimal(e)) : e < 0 && (t = "â€“" + this.convertToInt(this.convertToDecimal(e)))
            }
            return t
        }, isRequestedComponentExist: function (e, t) {
            return !!(e.context.is.defined(e.components) && e.components[t] && e.components[t].__enabled)
        }, getAccountInfo: function (e) {
            return {
                accountId: e.accountId ? e.accountId : e.accId,
                accountType: e.accountType ? e.accountType : e.accType,
                detailType: e.detailType,
                instanceName: e.instanceName,
                accountName: e.accountName,
                accountMaskNumber: e.accountMask,
                activityPrivilege: e.activityPrivilege,
                escrowPrivilege: e.escrowPrivilege,
                navigateToDetailsPage: e.navigateToDetailsPage,
                headerFocus: e.headerFocus,
                headerErrorFocus: e.headerErrorFocus,
                productId: e.productId,
                productCode: e.productCode,
                rewardsTypeId: e.rewardsTypeId,
                accountOwner: e.accountOwner,
                businessCardType: e.businessCardType,
                reward: e.reward,
                accountOwnerId: e.accountOwnerId,
                controlAccountId: e.controlAccountId,
                hasSubAccounts: e.hasSubAccounts,
                isSingleOvdAccount: Boolean(e.isSingleOvdAccount)
            }
        }, stripCurrency: function (e) {
            var n = new RegExp("[^\\d.\\-]", "g");
            return e ? e.toString().replace(t.SYMBOLS.ENDASH, "-").replace(n, "") : ""
        }, getCurrency: function (e, t) {
            var n = this.getNotAvailable("HEL_ACCOUNT", "accounts");
            return this.isDefined(e) ? this.currencyFormat(e) : void 0 === t ? n : t
        }, getDefinedValue: function (e, t) {
            var n = this.getNotAvailable("HEL_ACCOUNT", "accounts");
            return this.isDefined(e) ? e : void 0 === t ? n : t
        }, getWindowResolution: function (e) {
            switch (!0) {
                case e > 991:
                    return "large";
                case 768 < e < 992:
                    return "medium";
                case 320 < e < 769:
                    return "small";
                case e < 321:
                    return "extraSmall"
            }
        }, testCurrentPageLanguage: function () {
            return "es" === d.getContentLanguage()
        }, getAccountsUrlState: function (e, t, n) {
            if (t || "investment" !== n.accountType && "sla" !== n.accountType || (t = n.accountType.toUpperCase()), e += s[t] ? s[t] : s.INDEX, n) {
                var i = !0;
                for (var o in n) Object.hasOwnProperty.call(n, o) && (i ? (e = e + ";params=" + ("sla" === n[o] ? "studentLoan" : n[o]), i = !1) : e = e + "," + n[o])
            }
            return e
        }, getNotAvailable: function (e, t) {
            var n = this.getComponentArea(e, t);
            return u.dynamicSettings.get(n, "notAvailableLabel")
        }, getCQ5Content: function (e, t, n, i) {
            var o = this.getComponentArea(e, t);
            return u.dynamicSettings.get(o, n, i)
        }, getComponentArea: O, getDateVariationErrorKey: function (e, n, i, o) {
            var r = this.getVariationKeys(n, o);
            return r[e] || r[t.PRICINGSEGMENT.PREMIUMPLUS]
        }, getVariationKeys: function (e, n) {
            var i = {}, o = t.NO_ACCOUNT_ACTIVITY, r = t.CBO_PRCINGSEGMENT_VALIDATION_ERRORS.DATE, a = {
                4: "_FOUR_MONTHS",
                5: "_FIVE_MONTHS",
                6: "_SIX_MONTHS",
                12: "_TWELVE_MONTHS",
                24: "_TWENTY_FOUR_MONTHS"
            }, s = e === o ? e : e + "_" + r;
            for (var c in a) a.hasOwnProperty(c) && (i[c] = s + a[c]);
            return this.variationKeysOverrides(e, n, s, i)
        }, variationKeysOverrides: function (e, n, i, o) {
            return "PAST" === e && n && (o[t.PRICINGSEGMENT.PREMIUMPLUS] = i + "_RANGE_TWENTY_FOUR_MONTHS"), o[t.PRICINGSEGMENT.CHECK_OLD] = "ABOVE_SEVEN_YEARS", o
        }, getPricingSegmentToolTip: function (e) {
            var n = {};
            return n[t.PRICINGSEGMENT.STANDARD] = t.DATERANGEHELPMESSAGE.LAST_FOUR_MONTHS, n[t.PRICINGSEGMENT.PREMIUM] = t.DATERANGEHELPMESSAGE.LAST_SIX_MONTHS, n[t.PRICINGSEGMENT.NOPRICINGPACKAGE] = t.DATERANGEHELPMESSAGE.LAST_TWELVE_MONTHS, n[t.PRICINGSEGMENT.PREMIUMPLUS] = t.DATERANGEHELPMESSAGE.LAST_TWENTY_FOUR_MONTHS, n[e] || t.DATERANGEHELPMESSAGE.LAST_TWENTY_FOUR_MONTHS
        }, getCurrentYearWithCopyright: function () {
            var e = (new Date).getFullYear();
            return c + e + " "
        }, getSubUserEntitlements: function (e) {
            var t = {}, n = "SU" === e.userType;
            return e && (t.viewBalancesAndTransactAllowed = n && this.getDefinedValue(e.viewBalancesAllowed, !1) && this.getDefinedValue(e.transactAllowed, !1), t.viewBalancesAllowed = n && this.getDefinedValue(e.viewBalancesAllowed, !1) && !t.viewBalancesAndTransactAllowed, t.transactAllowed = n && this.getDefinedValue(e.transactAllowed, !1) && !t.viewBalancesAndTransactAllowed, t.viewStatementsAllowed = n && this.getDefinedValue(e.viewStatementsAllowed, !1), t.transferAndPaymentsAllowed = n && this.getDefinedValue(e.transferAndPaymentsAllowed, !1), e.subUserEntitlements = t), t
        }, convertToMessageObject: function (e, t, n) {
            var i = {};
            return e && t && (i.spec = e, i.key = t, i.variation = n), i
        }, setFocus: function (e, t) {
            var n = {value: "setFocus", element: e || ""};
            t.emit("state", n)
        }, getImage: function (e, t) {
            var n = {imageURL: "https://" + cq5Url + t.context.stringMap.get(e)};
            t.output.emit("state", {target: t, value: "updateViewModel", data: n})
        }, isChaseFlex: function (e) {
            return e && e.detailType === t.DETAIL_TYPE.CHASE_FLEX_CARD
        }, isBcc: function (e) {
            return !(!e || e !== t.DETAIL_TYPE.BUSINESS_CREDIT_CARD)
        }, hasEmployeeCards: function (e) {
            return !!((e.subAccounts ? Array.isArray(e.subAccounts) ? e.subAccounts.length : Object.keys(e.subAccounts).length : 0) > 0)
        }, validateKeyEvent: function (e) {
            return 13 === e.domEvent.which || "click" === e.domEvent.type || 32 === e.domEvent.which
        }, sortByDateAscending: function (e, t) {
            var n = new Date(e.historicalDate), i = new Date(t.historicalDate);
            return (n = n.getTime()) < (i = i.getTime()) ? -1 : n > i ? 1 : 0
        }, isEligibleForMerchantFundedOffers: function (e) {
            if (!e) return !1;
            var n = e.detailType || e.accountDetailType;
            return t.MERCHANT_FUNDED_OFFERS_VALID_DETAIL_TYPES.indexOf(n) > -1
        }, isUserProfileEligibleForMerchantFundedOffers: function (e) {
            return !!e && !e.isSubUser()
        }, getMaxCountAccounts: function (e, t) {
            return e.length > t ? y(e, t) : e
        }, getAccounts: function (e) {
            if (!Array.isArray(e)) {
                var t = [];
                return Object.keys(e).forEach((function (n) {
                    t.push(e[n])
                })), t
            }
            return e
        }, getEligibleMFOAccountObjects: function (e, n) {
            var i = function (e) {
                return e.filter((function (e) {
                    return !(function (e) {
                        return e.breached
                    }(e) || function (e) {
                        return e.isClosed || e.closed
                    }(e))
                }))
            }(E(A(function (e) {
                var n, i, o, r;
                return e.filter((function (e) {
                    return !!e && (n = v(e), i = h(e), o = t.EXCLUDED_BUSINESS_CARD_TYPES.indexOf(e.businessCardType) > -1, r = e.businessCardType && !o, !n || (r || i))
                }))
            }(e))));
            return function (e) {
                return e.map((function (e) {
                    return {accountMaskNumber: C(e), accountName: S(e), accountId: T(e), isBusinessAccount: _(e)}
                }))
            }(this.getMaxCountAccounts(i, n))
        }, setProductInfoUserPreferences: function (e, t) {
            if (e && e.length) {
                var n = [];
                e.forEach((function (e) {
                    var t = e.productCode || (e.detail ? e.detail.productCode : void 0),
                            i = e.productGroupCode || (e.detail ? e.detail.productGroupCode : void 0);
                    n.push({accountId: e.accountId, cardsPnpcData: t, cardProductData: i, breached: e.breached})
                })), t.userPreferences.set("productGroupInfos", n)
            }
        }, getFormattedDate: function (e) {
            return g.getFormattedESTDate(e, l.MMDDYYYY)
        }
    })
})), define("common/lib/viewHelper/domEventTypeHelper", [], (function () {
    "use strict";
    return {
        isKeyDown: function (e) {
            return "keydown" === e
        }, isEnterKey: function (e) {
            return 13 === (e.keyCode ? e.keyCode : e.domEvent.keyCode)
        }, isTabPress: function (e) {
            return 9 === (e.keyCode || e.which) && !e.shiftKey
        }, isShiftTabPress: function (e) {
            return 9 === (e.keyCode || e.which) && e.shiftKey
        }, isSpaceOrEnterKey: function (e) {
            var t = e.keyCode ? e.keyCode : e.domEvent.keyCode;
            return 32 === t || 13 === t
        }, isClickOrTap: function (e) {
            return "click" === e.domEvent.type || "mouse" === e.domEvent.pointerType || "touch" === e.domEvent.pointerType || "tap" === e.domEvent.type
        }, isValidCTA: function (e) {
            return this.isClickOrTap(e) || this.isSpaceOrEnterKey(e)
        }, isChangeEvent: function (e) {
            return "change" === e.domEvent.type
        }, isAnchorClicked: function (e) {
            var t = e.domEvent.target.nodeName;
            return t && "A" === t.toUpperCase()
        }, isEnterOrTap: function (e) {
            return 13 === (e.keyCode ? e.keyCode : e.domEvent.keyCode) || "tap" === e.domEvent.type
        }, isClick: function (e) {
            return "click" === e.domEvent.type
        }
    }
})), define("common/lib/accountViewUtility", ["require", "blue/$", "common/lib/viewHelper/domEventTypeHelper"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = e("common/lib/viewHelper/domEventTypeHelper");
    return {
        setOverFlowY: function (e) {
            t("body").css("overflow-y", e)
        },
        updateClassAttr: function (e, n, i) {
            var o = t(e);
            i ? o.removeClass(n) : o.addClass(n)
        },
        createDataAttrInjection: function (e, t, n) {
            return {
                id: t,
                dataAttributes: [{
                    target: "#content-" + t,
                    key: "data-attr",
                    value: e + "." + n + "HelpMessage"
                }, {
                    target: "#openText-" + t,
                    key: "data-attr",
                    value: e + ".request" + n + "HelpMessageAda"
                }, {
                    target: "#closeText-" + t,
                    key: "data-attr",
                    value: e + ".exit" + n + "HelpMessageAda"
                }, {
                    target: "#beginText-" + t,
                    key: "data-attr",
                    value: e + ".beginHelpMessageAda"
                }, {
                    target: "#endText-" + t,
                    key: "data-attr",
                    value: e + ".endHelpMessageAda"
                }, {target: "#" + t + " .js-close i", key: "data-attr", value: e + ".exit" + n + "HelpMessageAda"}]
            }
        },
        getHelpMessageTrigger: function (e, t) {
            return this.isKeyDown(t.domEvent.type) && this.isSpaceOrEnterKey(t) ? t.domEvent.currentTarget.classList.contains("show") ? "exit" + e + "HelpMessage" : "request" + e + "HelpMessage" : t.domEvent.srcEvent.target.classList.contains("close") ? "exit" + e + "HelpMessage" : "request" + e + "HelpMessage"
        },
        isKeyDown: n.isKeyDown,
        isSpaceOrEnterKey: n.isSpaceOrEnterKey,
        isClickOrTap: n.isClickOrTap,
        isValidCTA: n.isValidCTA,
        isTabPress: n.isTabPress,
        isEnterKey: n.isEnterKey,
        windowResize: function (e, n, i) {
            var o = t(n), r = t("#header"), a = t(e);
            a.innerHeight() - r.height() < i ? o.css({height: i}) : o.css({height: a.innerHeight() - r.height()})
        },
        isDomInViewPort: function (e) {
            var t = e.attr("id");
            return document.getElementById(t).getBoundingClientRect().bottom - window.innerHeight < 1
        },
        isChangeEvent: function (e) {
            return "change" === e.domEvent.type
        }
    }
})), define("common/lib/MutationElementObserver", [], (function () {
    "use strict";
    var e = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;
    return function (t) {
        var n = t && t.ttl || null, i = [], o = function () {
        }, r = new e((function (e) {
            e.forEach((function (e) {
                (e.addedNodes || []).length && i.forEach((function (e) {
                    document.querySelector(e.selector) && (e.isInserted || (e.callback(e.selector), e.isInserted = !0))
                }))
            })), 0 === (i = i.filter((function (e) {
                var t = Date.now() - e.timestamp;
                return !(e.isInserted || n && n < t)
            }))).length && (r.disconnect(), o())
        }));

        function a(e, t) {
            document.querySelector(e) ? t(e) : (i.length || r.observe(document.body, {
                childList: !0,
                subtree: !0
            }), i.push({selector: e, callback: t, timestamp: Date.now()}))
        }

        return {
            isInserted: a, isInsertedPromise: function (e) {
                return new Promise((function (t, n) {
                    o = n, a(e, t)
                }))
            }
        }
    }
})), define("common/lib/IntervalElementObserver", [], (function () {
    "use strict";
    return function (e) {
        var t, n = e && e.ttl || null, i = e && e.delay || 50, o = [], r = function () {
        };

        function a() {
            o.forEach((function (e) {
                var t = e.selector, n = e.callback;
                document.querySelector(t) && (n(t), e.isInserted = !0)
            })), 0 === (o = o.filter((function (e) {
                var t = Date.now() - e.timestamp;
                return !e.isInserted && (!n || n < t)
            }))).length && (clearInterval(t), r())
        }

        function s(e, n) {
            document.querySelector(e) ? n(e) : (o.length || (t = setInterval(a, i)), o.push({
                selector: e,
                callback: n,
                timestamp: Date.now()
            }))
        }

        return {
            isInserted: s, isInsertedPromise: function (e) {
                return new Promise((function (t, n) {
                    r = n, s(e, t)
                }))
            }
        }
    }
})), define("common/lib/elementObserver", ["require", "common/lib/MutationElementObserver", "common/lib/IntervalElementObserver"], (function (e) {
    "use strict";
    var t = e("common/lib/MutationElementObserver"), n = e("common/lib/IntervalElementObserver");
    return window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver ? t : n
})), define("common/lib/ada/setFocus", ["require", "blue/deferred", "blue/$", "blue/log", "common/lib/elementObserver"], (function (e) {
    var t = e("blue/deferred"), n = e("blue/$"), i = e("blue/log"),
            o = new (e("common/lib/elementObserver"))({ttl: 2e3, delay: 200});

    function r(e, r, a) {
        var s = this && this.context && this.context.$ || n,
                c = this && this.context && this.context.logger || i("[setFocus]"), l = r || 200;
        c.debug("focus requested on ", e, "number" == typeof r ? "with " + r + " delay" : "without delay");
        var u = new t, d = void 0;
        try {
            if (s(e).is(":focus") && "-1" !== s(e).attr("tabindex")) {
                var m = "setFocus_" + Date.now(), f = "#" + m;
                (d = s(f)).length || (s(e).parent().append('<span class="util accessible-text" id="' + m + '" tabindex="-1"></span>'), d = s(e).parent().find(f)), d.focus()
            }
            return o.isInserted(e, (function () {
                d && d.remove(), setTimeout((function () {
                    try {
                        var t = s(e);
                        void 0 !== a && t.attr("tabindex", a), t.focus(), u.resolve(e), c.debug("focused on ", e, "number" == typeof r ? "with " + r + " delay" : "without delay")
                    } catch (t) {
                        c.warn("Exception while focusing on ", e, "Continuing with warning...", t), n(e).focus(), u.resolve(e), c.debug("focused on ", e, "number" == typeof r ? "with " + r + " delay" : "without delay")
                    }
                }), l)
            })), u.promise
        } catch (t) {
            c.error('Failed to setFocus for Selector: "' + e + '" with this Exception:\n', t)
        }
    }

    return function () {
        var e = arguments[0];
        return 1 === arguments.length && "object" == typeof e ? r.call(this, e.selector || e.focus, e.delay, e.tabindex) : r.apply(this, arguments)
    }
})), define("common/lib/API/contextValidation/contextValidationAPI", ["require", "blue/is", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("blue/util").lang.defaults, i = ["CONTROLLER", "AREA", "APPLICATION"];

    function o(e) {
        return {flag: !(!e || !e.controller), name: e && e.controllerName || ""}
    }

    function r(e) {
        return {flag: !(!e || !e.area), name: e && e.areaName || ""}
    }

    function a(e) {
        return {flag: !(!e || !e.application), name: e && e.appName || ""}
    }

    function s(e, t, n) {
        var i = o(e) || {flag: !1};
        if (!i.flag) throw new Error("controllerContext must be a controller context object");
        if (void 0 !== t && ("object" != typeof t || Array.isArray(t))) throw new Error("filter must be a filter object if defined");
        return t = t || {}, Object.keys(t).length > 0 ? !(!t.hasOwnProperty(i.name) || !t[i.name]) && !("function" != typeof e.isDirty || !e.isDirty(n)) : !("function" != typeof e.isDirty || !e.isDirty(n))
    }

    function c(e, t, n, i) {
        var o = !1, a = "", c = r(e) || {flag: !1};
        if (!c.flag) throw new Error("areaContext must be a area context object");
        if (void 0 !== t && ("object" != typeof t || Array.isArray(t))) throw new Error("filter must be a filter object if defined");
        t = t || {};
        var l = function () {
            for (var t = e.children, r = e.children.length || e.children.size, c = 0; c < r; c++) {
                var l = t[c];
                if (l.controllerName && l.controllerName.length > 0 && (o = s(l, n, i))) return a = l.controllerName, !0
            }
            return !1
        };
        return Object.keys(t).length > 0 ? t.hasOwnProperty(c.name) && t[c.name] && (o = l()) : o = l(), {
            controllerName: a,
            flag: o
        }
    }

    function l(e, t, n, i, o) {
        if (!(a(e) || {flag: !1}).flag) throw new Error("applicationContext must be a application context object");
        if (void 0 !== t && ("object" != typeof t || Array.isArray(t))) throw new Error("filter must be a filter object if defined");
        for (var r = e.children, s = e.children.length || e.children.size, l = 0; l < s; l++) {
            var u = r[l];
            if (u.areaName && u.areaName.length > 0) {
                var d = c(u, n, i, o);
                if (d.flag) return {controllerName: d.controllerName, flag: d.flag}
            }
        }
        return {controllerName: "", flag: !1}
    }

    function u(e, t, r, a, u, d) {
        if (!o(e).flag || -1 === i.indexOf(t)) throw new Error("incorrect parameters");
        var m = !1, f = "", p = {};
        switch (t) {
            case"CONTROLLER":
                f = (m = p = s(e, r, d)) && e.controllerName || "";
                break;
            case"AREA":
                m = (p = c(e.parent, a, r, d)) && p.flag, f = n(p.controllerName, "");
                break;
            case"APPLICATION":
                m = (p = l(e.parent.parent.appName ? e.parent.parent : e.parent, u, a, r, d)) && p.flag, f = n(p.controllerName, "")
        }
        return {level: t, controllerName: f, flag: m}
    }

    return {
        _isComponentContext: function (e) {
            return {flag: !(!e || !e.component), name: e && e.componentName || ""}
        },
        _isControllerContext: o,
        _isAreaContext: r,
        _isApplicationContext: a,
        _isControllerContextDirty: s,
        _isAreaContextDirty: c,
        _isApplicationContextDirty: l,
        _isDirtyBasedOnLevel: u,
        isControllerDirty: function (e, n) {
            var i = !1;
            for (var o in e.components) if (Object.prototype.hasOwnProperty.call(e.components, o)) {
                if (e.registry.hasComponent(o) && "function" == typeof e.components[o].isDirty) {
                    if (t.array(n) && n.indexOf(o) > -1) continue;
                    var r = e.components[o],
                            a = (i = r.isDirty()) && "function" == typeof r.getDirtyMessageVariation && r.getDirtyMessageVariation();
                    e.context.application.broadcast("dirtyOverlayAPI/setDynamicContentVariationOverride", {data: {variationOverride: a}})
                }
                if (i) {
                    var s = e && e.context, c = s && s.logger;
                    return c && c.debug(o, "isDirty =", i), i
                }
            }
            return i
        },
        isDirty: function (e, t) {
            var n = u(e, (t = {
                level: t && t.level ? t.level : i[0],
                controllerFilter: t && t.controllerFilter ? t.controllerFilter : {},
                componentFilter: t && t.componentFilter ? t.componentFilter : {},
                areaFilter: t && t.areaFilter ? t.areaFilter : {
                    payBills: !0,
                    payeesAdmin: !0,
                    paymentsAdmin: !0,
                    payMultipleBills: !0
                },
                applicationFilter: t && t.applicationFilter ? t.applicationFilter : {}
            }).level, t.controllerFilter, t.areaFilter, t.applicationFilter, t.componentFilter);
            return n && n.flag
        },
        showConfirmationOverlay: function (e, t, n, i, o, r, a, s, c) {
            var l = {
                executeAction: i,
                executeActionParams: s,
                executeNoAction: o,
                parentComponent: t,
                parentComponentAnalyticsKey: n,
                variation: r,
                triggerAnalyticsOnConfirm: a,
                useDefaultAnalyticsKey: c
            };
            e.application.broadcast("dirtyOverlayAPI/showConfirmationOverlay", {data: l})
        },
        showLegalAgreementOverlay: function (e, t, n, i, o) {
            e.application.broadcast("showLegalDocumentViewer", {
                data: t,
                component: n,
                analyticsKey: i,
                executeCloseAction: o
            })
        },
        exitLegalAgreementOverlay: function (e) {
            e.application.broadcast("requestDocuments/destroyView", {
                data: {
                    actionName: "exitRequestDocument",
                    componentName: "requestLegalDocumentOverlay"
                }
            })
        }
    }
})), define("common/lib/API/contextValidation/contextValidationMixin", [], (function () {
    "use strict";
    return function (e, t) {
        return e.context && "function" != typeof e.context.isDirty && (e.context.isDirty = function (n) {
            return t && "function" == typeof t ? t.call(e) : e.context.contextValidationAPI.isControllerDirty(e, n)
        }), e
    }
})), define("common/lib/API/dataValidation/dataValidationAPI", ["require", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/util");
    return function (e, n) {
        if (void 0 === e) throw new Error("component is required");
        var i = e.context.is;
        if (e.initialData = {}, e.isFormInitialized = !1, void 0 === n) n = {}, Object.keys(e.spec.data).forEach((function (e) {
            Object.defineProperty(n, e, {value: !0})
        })); else if (Array.isArray(n) || "object" != typeof n) throw new Error("field must be a map object");

        function o(t, n) {
            n && e.context.is.object(n) ? Object.hasOwnProperty.call(e.initialData, t) && e.isFormInitialized ? e.initialData[t].current = n[t] : e.initialData[t] = {
                original: n[t] && JSON.parse(JSON.stringify(n[t])),
                current: n[t]
            } : e.context.logger.error('setInitialDataHelper: provided  "data" is not  object passed value for component,key,data is:', e, t, n)
        }

        function r(t) {
            if (e.initialData.hasOwnProperty(t) && i.defined(e.initialData[t].current)) {
                var n = e.initialData[t].current;
                if (Array.isArray(n)) return JSON.stringify(n) !== JSON.stringify(e.initialData[t].original);
                if (n instanceof Object) return !e.context.util.object.deepMatches(e.initialData[t].original, n);
                if (null !== n && i.defined(n) && null !== e.initialData[t].original && i.defined(e.initialData[t].original)) return n.toString() !== e.initialData[t].original.toString()
            }
            return !1
        }

        return "function" != typeof e.isDirty && (e.isDirty = function () {
            var i = Object.keys(n), o = i.length > 0 ? i : Object.keys(e.initialData);
            return !!t.array.find(o, r)
        }), "function" != typeof e.resetDirtyData && (e.resetDirtyData = function () {
            for (var t in e.initialData) e.initialData.hasOwnProperty(t) && void 0 !== e.initialData[t].current && (e.initialData[t].original = e.initialData[t].current)
        }), e.formInitialized = function (t) {
            e.isFormInitialized = t
        }, e.resetDirtyField = function (t) {
            e.initialData.hasOwnProperty(t) && void 0 !== e.initialData[t].current && (e.initialData[t].original = e.initialData[t].current)
        }, e.setInitialDataForDirty = function (e, t) {
            var n = {};
            n[e] = t, o(e, n)
        }, e.model.onValue((function (e, t) {
            if ("" === t) for (var n = Object.keys(e), i = 0; i < n.length; i++) o(n[i], e); else o(t, e)
        })), e
    }
})), define("common/lib/API/dirtyOverlay/component/dirtyOverlay", ["require", "appkit-utilities/analytics/overlay"], (function (e) {
    "use strict";
    return function (t) {
        var n, i = this, o = t.is, r = e("appkit-utilities/analytics/overlay"), a = t.dcu.dynamicSettings,
                s = function (e) {
                    t.application.broadcast("dirtyOverlayAPI/destroyView", {data: {actionName: e}})
                }, c = function (e) {
                    var a, c;
                    i.model.get("useDefaultAnalyticsKey") ? (a = i, c = "exitConfirmationOverlay") : (a = t.parentComponent, c = i.model.get("parentComponentAnalyticsKey")), o.null(a) || o.undefined(a) || o.empty(a) || o.null(c) || o.undefined(c) || o.empty(c) ? "SHOW" !== e && s(e) : "SHOW" === e ? r.showOverlay(a, c) : "confirmExit" === e && n || "doNotExit" === e ? r.hideOverlay(a, c, e).catch((function () {
                        t && t.logger.warn("Analytics action " + c + " not in spec ")
                    })).then((function () {
                        s(e)
                    })) : s(e)
                }, l = function (e, t, n, o) {
                    a.set(i, "exitConfirmationHeader", e), a.set(i, "exitConfirmationAdvisory", e), a.set(i, "exitConfirmationMessage", t || e), a.set(i, "confirmExitLabel", n || t || e), a.set(i, "doNotExitLabel", o || n || t || e), i.exitConfirmationAdvisory && (i.exitConfirmationMessage = i.exitConfirmationAdvisory + "<br/><br/>" + i.exitConfirmationMessage)
                };
        this.onInit = function () {
            n = !1
        }, this.confirmExit = function () {
            c("confirmExit")
        }, this.doNotExit = function () {
            c("doNotExit")
        }, this.onReady = function () {
            var e, t;
            e = i.model.get("variation"), t = i.model.get("variationOverride"), (e = e || t) && "string" == typeof e ? l(e) : e && "object" == typeof e && (e.isDirectOverride ? (e.exitConfirmationHeader && a.set(i, "exitConfirmationHeader", e.exitConfirmationHeader), e.exitConfirmationAdvisory && a.set(i, "exitConfirmationAdvisory", e.exitConfirmationAdvisory), e.exitConfirmationMessage && a.set(i, "exitConfirmationMessage", e.exitConfirmationMessage), e.confirmExitLabel && a.set(i, "confirmExitLabel", e.confirmExitLabel), e.doNotExitLabel && a.set(i, "doNotExitLabel", e.doNotExitLabel)) : 2 === Object.keys(e).length ? l(e[Object.keys(e)[0]], e[Object.keys(e)[0]], e[Object.keys(e)[1]]) : 3 === Object.keys(e).length ? l(e[Object.keys(e)[0]], e[Object.keys(e)[1]], e[Object.keys(e)[2]]) : 4 === Object.keys(e).length && l(e[Object.keys(e)[0]], e[Object.keys(e)[1]], e[Object.keys(e)[2]], e[Object.keys(e)[3]])), n = Boolean(i.model.get("triggerAnalyticsOnConfirm")), c("SHOW")
        }
    }
})), define("bluespec/exit_confirmation", [], (function () {
    return {
        name: "EXIT_CONFIRMATION",
        data: {
            exitConfirmationError: {type: "Description"},
            fileName: {type: "Description"},
            optOutStatus: {type: "OnOff"}
        },
        actions: {
            doNotExit: !0,
            confirmExit: !0,
            exitWithActiveLiveChat: !0,
            doNotExitWithActiveLiveChat: !0,
            exitError: !0,
            exitPrintingOverlay: !0
        },
        states: {exitConfirmationOverlay: !0},
        settings: {
            waitAda: !0,
            exitConfirmationHeader: !0,
            exitConfirmationMessage: !0,
            exitConfirmationAdvisory: !0,
            doNotExitLabel: !0,
            confirmExitLabel: !0,
            activeLiveChatExitWarningHeader: !0,
            activeLiveChatExitWarningAdvisory: !0,
            errorPrintingTransactionMessage: !0,
            closeLabel: !0,
            optOutStatusLabel: !0
        }
    }
})), define("common/template/common/API/dirtyOverlay", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "div",
            m: [{n: "id", f: "dirtyOverlay-wrapper", t: 13}],
            f: [{
                t: 7,
                e: "blueModal",
                m: [{n: "id", f: "dirtyFormEdit", t: 13}, {
                    n: "title",
                    f: [{t: 2, r: "exitConfirmationHeader"}],
                    t: 13
                }, {n: "dialogText", f: [{t: 2, r: ".exitConfirmationMessage"}], t: 13}, {
                    n: "cancelButtonId",
                    f: "do-not-exit-confirmation-button",
                    t: 13
                }, {n: "cancelButtonText", f: [{t: 2, r: "doNotExitLabel"}], t: 13}, {
                    n: "cancelButtonClasses",
                    f: "secondary fluid",
                    t: 13
                }, {n: "cancelButtonClick", f: "doNotExit", t: 13}, {
                    n: "confirmationButtonId",
                    f: "exit-confirmation-button",
                    t: 13
                }, {
                    n: "confirmationButtonText",
                    f: [{t: 2, r: "confirmExitLabel"}],
                    t: 13
                }, {n: "confirmationButtonClasses", f: "primary fluid", t: 13}, {
                    n: "confirmationButtonClick",
                    f: "confirmExit",
                    t: 13
                }, {n: "additionalDialogContent", f: "false", t: 13}, {
                    t: 4,
                    f: [{n: "returnFocusToSelector", f: [{t: 2, r: ".returnFocusToSelector"}], t: 13}],
                    r: "returnFocusToSelector"
                }],
                f: []
            }]
        }]
    }
})), define("common/lib/API/dirtyOverlay/webspec/dirtyOverlay", {
    name: "EXIT_CONFIRMATION",
    preventDefault: !0,
    bindings: {
        exitConfirmationError: {direction: "BOTH"},
        exitConfirmationHeader: {direction: "BOTH"},
        exitConfirmationMessage: {direction: "BOTH"},
        doNotExitLabel: {direction: "BOTH"},
        confirmExitLabel: {direction: "BOTH"}
    },
    triggers: {}
}), define("common/lib/API/dirtyOverlay/view/dirtyOverlay", ["require", "common/template/common/API/dirtyOverlay", "common/lib/API/dirtyOverlay/webspec/dirtyOverlay", "blue-ui/view/modules/modal"], (function (e) {
    "use strict";
    return function () {
        this.template = e("common/template/common/API/dirtyOverlay"), this.bridge = e("common/lib/API/dirtyOverlay/webspec/dirtyOverlay"), this.views = {blueModal: e("blue-ui/view/modules/modal")}, this.init = function () {
        }
    }
})), define("common/template/common/API/mdsDirtyOverlay", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "mds-dialog",
            m: [{n: "id", f: "mdsDirtyForm", t: 13}, {n: "dialog-height", f: "251", t: 13}, {
                n: "dialog-width",
                f: "564",
                t: 13
            }, {n: "header-text", f: [{t: 2, r: "~/exitConfirmationHeader"}], t: 13}, {
                n: "trigger-id",
                f: [{t: 2, r: "~/returnFocusToSelector"}],
                t: 13
            }, {n: "primary-button-text", f: [{t: 2, r: "~/confirmExitLabel"}], t: 13}, {
                n: "secondary-button-text",
                f: [{t: 2, r: "~/doNotExitLabel"}],
                t: 13
            }, {n: "dialog-header-icon-name", f: "ico_alert_filled", t: 13}, {n: "open", f: "false", t: 13}],
            f: [{
                t: 7,
                e: "div",
                m: [{n: "slot", f: "dialogContent", t: 13}],
                f: [{
                    t: 7,
                    e: "span",
                    m: [{n: "class", f: "bodyLarge", t: 13}],
                    f: [{t: 2, r: "~/exitConfirmationMessage"}]
                }]
            }]
        }]
    }
})), define("common/lib/API/dirtyOverlay/view/mdsDirtyOverlay", ["require", "common/lib/elementObserver", "common/template/common/API/mdsDirtyOverlay", "common/lib/API/dirtyOverlay/webspec/dirtyOverlay"], (function (e) {
    "use strict";
    var t = new (e("common/lib/elementObserver")), n = e("common/template/common/API/mdsDirtyOverlay"),
            i = e("common/lib/API/dirtyOverlay/webspec/dirtyOverlay");
    return function () {
        var e = this;
        this.template = n, this.bridge = i, e.onInit = function () {
            t.isInserted("#mdsDirtyForm", (function () {
                var t = document.querySelector("#mdsDirtyForm");
                t && (t.addEventListener("click-button2", e.doNotExit), t.addEventListener("click-button1", e.confirmExit))
            }))
        }, e.doNotExit = function () {
            e.trigger("doNotExit")
        }, e.confirmExit = function () {
            e.trigger("confirmExit")
        }
    }
})), define("common/lib/API/dirtyOverlay/dirtyOverlayAPI", ["require", "bluespec/exit_confirmation", "common/lib/API/dirtyOverlay/component/dirtyOverlay", "common/lib/API/dirtyOverlay/view/dirtyOverlay", "common/lib/API/dirtyOverlay/view/mdsDirtyOverlay"], (function (e) {
    "use strict";
    return function (t) {
        var n, i = this, o = e("bluespec/exit_confirmation"),
                r = e("common/lib/API/dirtyOverlay/component/dirtyOverlay"),
                a = e("common/lib/API/dirtyOverlay/view/dirtyOverlay"),
                s = e("common/lib/API/dirtyOverlay/view/mdsDirtyOverlay"), c = null;
        this.onInit = function () {
            t.on({
                "dirtyOverlayAPI/showConfirmationOverlay": function (e) {
                    var l;
                    l = e.data, i.registry.hasComponent("dirtyOverlay") || i.registry.registerComponent("dirtyOverlay", {
                        spec: o,
                        methods: r,
                        model: i.model.lens("dirtyOverlay")
                    }), n = {
                        scope: l.parentComponent,
                        executeAction: l.executeAction,
                        executeActionParams: l.executeActionParams,
                        executeNoAction: l.executeNoAction
                    }, i.model.set("dirtyOverlay.variationOverride", c), i.model.set("dirtyOverlay.variation", l.variation), i.model.set("dirtyOverlay.returnFocusToSelector", l.returnFocusToSelector), t.parentComponent = l.parentComponent, i.model.set("dirtyOverlay.parentComponentAnalyticsKey", l.parentComponentAnalyticsKey), i.model.set("dirtyOverlay.triggerAnalyticsOnConfirm", l.triggerAnalyticsOnConfirm), i.model.set("dirtyOverlay.useDefaultAnalyticsKey", l.useDefaultAnalyticsKey), i.executeCAV([i.components.dirtyOverlay, l.useMdsTemplate ? s : a, {target: ".overlay"}])
                }, "dirtyOverlayAPI/destroyView": function (e) {
                    var o, r, a;
                    o = e.data, r = o.actionName, a = t.is, c = null, i.components && i.components.dirtyOverlay.destroy(), "confirmExit" === r ? a.null(n.executeAction) || a.undefined(n.executeAction) || !a.function(n.executeAction) || (n.scope && n.scope.context ? n.executeAction.call(n.scope, n.executeActionParams) : n.executeAction()) : "doNotExit" === r && (a.null(n.executeNoAction) || a.undefined(n.executeNoAction) || !a.function(n.executeNoAction) || n.executeNoAction())
                }, "dirtyOverlayAPI/setDynamicContentVariationOverride": function (e) {
                    var t;
                    t = e.data, c = t.variationOverride
                }
            })
        }
    }
})), define("common/lib/API/genericOverlay/component/genericOverlay", ["appkit-utilities/analytics/overlay"], (function () {
    "use strict";
    var e = null, t = require("appkit-utilities/analytics/overlay"), n = {cancel: "doNot", proceed: "confirm"},
            i = {EXIT_CONFIRMATION: "Exit", CANCEL_CONFIRMATION: "Cancel", DELETE_CONFIRMATION: "Delete"};

    function o(t) {
        e.context.application.broadcast("genericOverlay/destroyView", {data: {actionName: t}})
    }

    return {
        init: function () {
            e = this
        }, cancelAction: function () {
            e.triggerAnalyticsAction("cancel")
        }, triggerAnalyticsAction: function (r) {
            if (r) {
                var a = n[r] + i[e.spec.name];
                t.hideOverlay(e, "exitConfirmationOverlay", a), e[a] && e[a](), "cancelAction" === (s = r + "Action") || "proceedAction" === s ? setTimeout((function () {
                    o(s)
                }), 50) : o(s)
            }
            var s
        }, executeSetDynamicContent: function (t, n, i) {
            var o = e.context.dcu.dynamicSettings.get(e, t.header, n || ""),
                    r = e.context.dcu.dynamicSettings.get(e, t.message, n || ""),
                    a = e.context.dcu.dynamicSettings.get(e, t.primary, n || ""),
                    s = e.context.dcu.dynamicSettings.get(e, t.secondary, n || "");
            this.setValue({key: "headerVariation", value: o}), this.setValue({
                key: "messageVariation",
                value: r
            }), this.setValue({key: "primaryLabel", value: a}), this.setValue({
                key: "secondaryLabel",
                value: s
            }), this.setValue({key: "showAdvisory", value: i})
        }, setValue: function (e) {
            this.output.emit("updateViewModel", e)
        }, proceedAction: function () {
            e.triggerAnalyticsAction("proceed")
        }, doNotCancel: function () {
        }, confirmCancel: function () {
        }, doNotExit: function () {
        }, confirmExit: function () {
        }, doNotDelete: function () {
            t.hideOverlay(e, "deleteConfirmationOverlay", "doNotDelete")
        }, confirmDelete: function () {
        }
    }
})), define("common/lib/API/genericOverlay/webspec/genericOverlay", {
    name: "LANGUAGE_SUPPORT_DISCLAIMER",
    bindings: {},
    triggers: {cancelAction: {action: "cancelAction"}, proceedAction: {action: "proceedAction", preventDefault: !0}}
}), define("common/template/common/API/genericOverlay", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "section",
            m: [{n: "role", f: "region", t: 13}],
            f: [{
                t: 7,
                e: "blueModal",
                m: [{n: "id", f: "languageOverlayId", t: 13}, {
                    n: "title",
                    f: [{t: 2, r: "headerVariation"}],
                    t: 13
                }, {
                    n: "dialogText",
                    f: [{t: 4, f: [{t: 2, r: "messageVariation"}], n: 50, r: "showAdvisory"}],
                    t: 13
                }, {n: "modalWindow", f: "", t: 13}, {
                    n: "cancelButtonId",
                    f: "commonOverlayCancelButton",
                    t: 13
                }, {n: "cancelButtonText", f: [{t: 2, r: "secondaryLabel"}], t: 13}, {
                    n: "cancelButtonClasses",
                    f: "",
                    t: 13
                }, {n: "cancelButtonClick", f: "cancelAction", t: 13}, {
                    n: "confirmationButtonId",
                    f: "commonOverlayProceedButton",
                    t: 13
                }, {
                    n: "confirmationButtonText",
                    f: [{t: 2, r: "primaryLabel"}],
                    t: 13
                }, {n: "confirmationButtonClasses", f: "", t: 13}, {
                    n: "confirmationButtonClick",
                    f: "proceedAction",
                    t: 13
                }]
            }]
        }]
    }
})), define("common/lib/API/genericOverlay/view/genericOverlay", ["require", "common/lib/API/genericOverlay/webspec/genericOverlay", "common/template/common/API/genericOverlay", "blue-ui/view/modules/modal"], (function (e) {
    "use strict";
    return function () {
        this.bridge = e("common/lib/API/genericOverlay/webspec/genericOverlay"), this.template = e("common/template/common/API/genericOverlay"), this.views = {blueModal: e("blue-ui/view/modules/modal")}, this.model = {
            headerVariation: "",
            advisoryVariation: "",
            messageVariation: "",
            primaryLabel: "",
            secondaryLabel: "",
            showAdvisory: !1
        };
        var t = this;
        this.init = function () {
            this.bridge.on("updateViewModel", (function (e) {
                t.model[e.key] = e.value
            }))
        }
    }
})), define("common/lib/API/genericOverlay/helper", [], (function () {
    "use strict";
    return function () {
        var e = {
            exit: {
                header: "exitConfirmationHeader",
                message: "exitConfirmationMessage",
                primary: "confirmExitLabel",
                secondary: "doNotExitLabel",
                spec: "bluespec/exit_confirmation"
            },
            cancel: {
                header: "cancelConfirmationHeader",
                message: "cancelConfirmationMessage",
                primary: "confirmCancelLabel",
                secondary: "doNotCancelLabel",
                spec: "bluespec/cancel_confirmation"
            },
            delete: {
                header: "deleteConfirmationHeader",
                message: "deleteConfirmationMessage",
                primary: "confirmDeleteLabel",
                secondary: "doNotDeleteLabel",
                spec: "bluespec/delete_confirmation"
            },
            siteExit: {
                header: "siteExitWarningHeader",
                message: "siteExitMessage",
                primary: "proceedToExternalSiteLabel",
                secondary: "doNotProceedToExternalSiteLabel",
                spec: "bluespec/site_exit_warning"
            }
        };
        this.getComponentType = function (e) {
            var t;
            return e.fromCancel ? t = "cancel" : e.fromExit ? t = "exit" : e.fromDelete ? t = "delete" : e.siteExit && (t = "siteExit"), t
        }, this.getLayoutKeys = function (t) {
            var n = this.getComponentType(t);
            return e[n]
        }
    }
})), define("bluespec/cancel_confirmation", [], (function () {
    return {
        name: "CANCEL_CONFIRMATION",
        data: {
            transactionAmount: {type: "Money"},
            transactionInitiationDate: {type: "Date"},
            transactionDueDate: {type: "Date"},
            payeeName: {type: "Description"},
            transferToAccountName: {type: "Description"},
            memo: {type: "Description"},
            cancellationReasonOptions: {
                type: "List",
                items: {cancellationReason: "Description", cancellationReasonId: "Description"}
            },
            cancellationReasonId: {type: "Description"},
            cancellationReasonOptionsDisplayedState: {type: "OnOff"},
            checkNumber: {type: "Description"},
            checkAmount: {type: "Money"},
            automatedClearingHouseAmount: {type: "Money"},
            checkNumberRange: {type: "Description"},
            transactionInitiationRecurringDate: {type: "Date"},
            beneficiaryAccountNickname: {type: "Description"},
            beneficiaryAccountMaskNumber: {type: "AccountMaskNumber"},
            accountName: {type: "Description"},
            accountMaskNumber: {type: "AccountMaskNumber"},
            payorName: {type: "Description"},
            fundingAccountDisplayName: {type: "Description"},
            balanceTransferAmount: {type: "Money"},
            balanceTransferAccountDisplayName: {type: "Description"},
            loanAccountDisplayName: {type: "Description"},
            moneyTransferContactName: {type: "Description"},
            fileName: {type: "Description"},
            disclosureAcceptanceStatus: {type: "OnOff"},
            automaticPaymentStartDate: {type: "Description"},
            numberOfDaysBeforeDueDate: {type: "Numbers"}
        },
        actions: {
            confirmCancel: !0,
            doNotCancel: !0,
            skipBack: !0,
            confirmCancelTransaction: !0,
            doNotCancelTransaction: !0
        },
        states: {cancelConfirmationOverlay: !0},
        settings: {
            cancelConfirmationHeader: !0,
            cancelConfirmationAdvisory: !0,
            cancelConfirmationMessage: !0,
            memoLabel: !0,
            optionalLabel: !0,
            memoAdvisory: !0,
            memoError: !0,
            confirmCancelLabel: !0,
            doNotCancelLabel: !0,
            cancellationReasonOptionsLabel: !0,
            currentSelectionAda: !0,
            endOfSelectionAda: !0,
            cancellationReasonOptionsPlaceholder: !0,
            cancellationReason: !0,
            cancellationReasonOptionsMessage: !0,
            cancellationReasonOptionsAdvisory: !0,
            cancelConfirmationWarningHeader: !0,
            cancelConfirmationWarningAdvisory: !0,
            skipBackLabel: !0,
            cancelConfirmationVerificationHeader: !0,
            mobilePageTitle: !0,
            disclosureAcceptanceStatusAda: !0,
            disclosuresMessage: !0,
            disclosureAcceptanceStatusError: !0,
            cancelConfirmationErrorHeader: !0,
            cancelConfirmationErrorAdvisory: !0
        }
    }
})), define("bluespec/delete_confirmation", [], (function () {
    return {
        name: "DELETE_CONFIRMATION",
        data: {
            payeeName: {type: "Description"},
            payeeGroupName: {type: "Description"},
            payorName: {type: "Description"},
            payorGroupName: {type: "Description"},
            favoriteAccountsGroupName: {type: "Description"},
            recipientGroupName: {type: "Description"},
            transactionAmount: {type: "Money"},
            totalAmount: {type: "Money"},
            transactionFrequency: {type: "Description", exportable: !0},
            fundingAccountDisplayName: {type: "Description"},
            thirdPartyApplicationName: {type: "Description"},
            wireTransferContactNickname: {type: "Description"},
            wireRecipientGroupName: {type: "Description"},
            fileName: {type: "Description"},
            transferToAccountNickname: {type: "Description"},
            transferToAccountDisplayName: {type: "Description"},
            paymentAmountDue: {type: "Money"},
            totalPendingPayments: {type: "Numbers"},
            paymentsAdvisoryDisplayedState: {type: "OnOff"},
            savedSearchName: {type: "Description"},
            reportName: {type: "Description"},
            totalNumberOfReportsSelected: {type: "Number"}
        },
        actions: {confirmDelete: !0, doNotDelete: !0},
        states: {deleteConfirmationOverlay: !0, errorState: !0},
        settings: {
            deleteConfirmationHeader: !0,
            deleteConfirmationAdvisory: !0,
            deleteConfirmationMessage: !0,
            confirmDeleteLabel: !0,
            doNotDeleteLabel: !0,
            importantLabel: !0,
            optionalLabel: !0,
            checkmarkAda: !0,
            deleteConfirmationErrorHeader: !0,
            deleteConfirmationErrorAdvisory: !0,
            importantAda: !0,
            pendingTransactionDetailMessage: !0,
            pendingTransactionDetailLabel: !0,
            paymentsAdvisory: !0,
            stopSharingAccountInformationLabel: !0,
            doNotStopSharingAccountInformationLabel: !0
        }
    }
})), define("bluespec/site_exit_warning", [], (function () {
    return {
        name: "SITE_EXIT_WARNING",
        data: {
            companyDisplayName: {type: "Description"},
            targetNavigation: {type: "Description", exportable: !0},
            siteExitMessageShownState: {type: "OnOff"},
            setFocus: {type: "Description"}
        },
        states: {showOverlay: !0, languageSupportDisclaimerOverlay: !0, siteExitWarningOverlay: !0},
        actions: {proceedToExternalSite: !0, doNotProceedToExternalSite: !0, restrictTabMovementAda: !0},
        settings: {
            siteExitWarningHeader: !0,
            siteExitMessage: !0,
            proceedToExternalSiteLabel: !0,
            doNotProceedToExternalSiteLabel: !0,
            overlayAnnouncementAda: !0,
            siteExitDisclaimer: !0,
            siteExitDisclaimerAda: !0,
            siteExitFootnote: !0,
            languageSupportDisclaimer: !0,
            loggedOffAdvisory: !0
        }
    }
})), define("common/lib/API/genericOverlay/genericOverlay", ["require", "common/lib/API/genericOverlay/component/genericOverlay", "blue/observable", "common/lib/API/genericOverlay/view/genericOverlay", "blue/is", "appkit-utilities/analytics/overlay", "common/lib/API/genericOverlay/helper", "bluespec/cancel_confirmation", "bluespec/exit_confirmation", "bluespec/delete_confirmation", "bluespec/site_exit_warning"], (function (e) {
    "use strict";
    return function (t) {
        var n, i = "", o = e("common/lib/API/genericOverlay/component/genericOverlay"), r = e("blue/observable"),
                a = e("common/lib/API/genericOverlay/view/genericOverlay"), s = e("blue/is"), c = null,
                l = e("appkit-utilities/analytics/overlay"), u = new (e("common/lib/API/genericOverlay/helper"));
        this.init = function () {
            c = this, t.on({
                "genericOverlay/showOverlay": function (t) {
                    var s, d;
                    s = t.data, d = u.getLayoutKeys(s.contentVariationsAndFlags), i = s.contentVariationsAndFlags.fromCancel ? e("bluespec/cancel_confirmation") : s.contentVariationsAndFlags.fromExit ? e("bluespec/exit_confirmation") : s.contentVariationsAndFlags.fromDelete ? e("bluespec/delete_confirmation") : s.contentVariationsAndFlags.siteExit ? e("bluespec/site_exit_warning") : e("bluespec/" + s.contentVariationsAndFlags.specName), c.register.components(c, [{
                        name: "genericOverlay",
                        model: r.Model({}),
                        spec: i,
                        methods: o
                    }]), n = {
                        component: s.parentComponent,
                        executeAction: s.executeAction,
                        executeNoAction: s.doNotExecuteAction,
                        contentVariationsAndFlags: s.contentVariationsAndFlags,
                        actionKey: s.actionKey
                    }, c.executeCAV([c.components.genericOverlay, a, {
                        target: ".overlay",
                        append: !1
                    }]).then((function () {
                        l.showOverlay(n.component, s.contentVariationsAndFlags.analyticsKey), c.components.genericOverlay.executeSetDynamicContent(d, s.contentVariationsAndFlags.variation, s.contentVariationsAndFlags.showAdvisory)
                    }))
                }, "genericOverlay/destroyView": function (e) {
                    var t, i;
                    t = e.data, i = t.actionName, c.components.genericOverlay.destroy(), "proceedAction" === i ? s.null(n.executeAction) || s.undefined(n.executeAction) || !s.function(n.executeAction) || n.executeAction() : "cancelAction" === i && (s.null(n.executeNoAction) || s.undefined(n.executeNoAction) || !s.function(n.executeNoAction) || n.executeNoAction())
                }
            })
        }
    }
})), define("common/lib/API/languageOverlay/component/languageOverlay", [], (function () {
    "use strict";
    var e = null;

    function t(t) {
        e.context.application.broadcast("languageOverlayAPI/destroyView", {data: {actionName: t}})
    }

    function n(e) {
        "proceedInSameLanguage" === e || "doNotProceedInSameLanguage" === e ? setTimeout((function () {
            t(e)
        }), 50) : t(e)
    }

    return {
        init: function () {
            e = this
        }, proceedInSameLanguage: function () {
            n("proceedInSameLanguage")
        }, executeSetDynamicContent: function (t) {
            e.context.dcu.dynamicSettings.set(e, "languageSupportDisclaimerHeader", t), e.context.dcu.dynamicSettings.set(e, "languageSupportDisclaimer", t)
        }, doNotProceedInSameLanguage: function () {
            n("doNotProceedInSameLanguage")
        }
    }
})), define("bluespec/language_support_disclaimer", [], (function () {
    return {
        name: "LANGUAGE_SUPPORT_DISCLAIMER",
        data: {doNotShowDisclaimerAgain: {type: "OnOff"}, showDisclaimerCheckbox: {type: "OnOff"}},
        states: {showOverlay: !0, siteExitWarningOverlay: !0, languageSupportDisclaimerOverlay: !0},
        actions: {
            proceedToChangeLanguage: !0,
            doNotProceedToChangeLanguage: !0,
            restrictTabMovementAda: !0,
            proceedInSameLanguage: !0,
            doNotProceedInSameLanguage: !0
        },
        settings: {
            languageSupportDisclaimerHeader: !0,
            languageSupportDisclaimer: !0,
            doNotShowDisclaimerAgainLabel: !0,
            proceedToChangeLanguageLabel: !0,
            doNotProceedToChangeLanguageLabel: !0,
            proceedInSameLanguageLabel: !0,
            doNotProceedInSameLanguageLabel: !0
        }
    }
})), define("common/lib/API/languageOverlay/webspec/languageOverlay", {
    name: "LANGUAGE_SUPPORT_DISCLAIMER",
    preventDefault: !0,
    bindings: {},
    triggers: {}
}), define("common/template/common/API/languageOverlay", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "section",
            m: [{n: "role", f: "region", t: 13}],
            f: [{
                t: 7,
                e: "blueModal",
                m: [{n: "id", f: "languageOverlayId", t: 13}, {
                    n: "title",
                    f: [{t: 2, r: "languageSupportDisclaimerHeader"}],
                    t: 13
                }, {n: "dialogText", f: [{t: 2, r: "languageSupportDisclaimer"}], t: 13}, {
                    n: "modalWindow",
                    f: "",
                    t: 13
                }, {n: "cancelButtonId", f: "doNotProceedInSameLanguageId", t: 13}, {
                    n: "cancelButtonText",
                    f: [{t: 2, r: "doNotProceedInSameLanguageLabel"}],
                    t: 13
                }, {n: "cancelButtonClasses", f: "", t: 13}, {
                    n: "cancelButtonClick",
                    f: "doNotProceedInSameLanguage",
                    t: 13
                }, {n: "confirmationButtonId", f: "proceedInSameLanguageId", t: 13}, {
                    n: "confirmationButtonText",
                    f: [{t: 2, r: "proceedInSameLanguageLabel"}],
                    t: 13
                }, {n: "confirmationButtonClasses", f: "", t: 13}, {
                    n: "confirmationButtonClick",
                    f: "proceedInSameLanguage",
                    t: 13
                }]
            }]
        }]
    }
})), define("common/lib/API/languageOverlay/view/languageOverlay", ["require", "common/lib/API/languageOverlay/webspec/languageOverlay", "common/template/common/API/languageOverlay", "blue-ui/view/modules/modal"], (function (e) {
    "use strict";
    return function () {
        this.bridge = e("common/lib/API/languageOverlay/webspec/languageOverlay"), this.template = e("common/template/common/API/languageOverlay"), this.views = {blueModal: e("blue-ui/view/modules/modal")}, this.init = function () {
        }
    }
})), define("common/lib/API/languageOverlay/languageOverlayAPI", ["require", "bluespec/language_support_disclaimer", "common/lib/API/languageOverlay/component/languageOverlay", "blue/observable", "common/lib/API/languageOverlay/view/languageOverlay", "blue/is", "appkit-utilities/analytics/overlay"], (function (e) {
    "use strict";
    return function (t) {
        var n, i = e("bluespec/language_support_disclaimer"),
                o = e("common/lib/API/languageOverlay/component/languageOverlay"), r = e("blue/observable"),
                a = e("common/lib/API/languageOverlay/view/languageOverlay"), s = e("blue/is"), c = null,
                l = e("appkit-utilities/analytics/overlay");
        this.init = function () {
            c = this, t.on({
                "languageOverlayAPI/showLanguageOverlay": function (e) {
                    var t;
                    t = e.data, c.register.components(c, [{
                        name: "languageOverlay",
                        model: r.Model({}),
                        spec: i,
                        methods: o
                    }]), n = {
                        component: t.parentComponent,
                        executeAction: t.executeAction,
                        executeNoAction: t.executeNoAction
                    }, c.components.languageOverlay.executeSetDynamicContent(t.variation), c.executeCAV([c.components.languageOverlay, a, {
                        target: ".overlay",
                        append: !1
                    }]).then((function () {
                        l.showOverlay(n.component, "languageSupportDisclaimerOverlay")
                    }))
                }, "languageOverlayAPI/destroyView": function (e) {
                    var t, i;
                    t = e.data, i = t.actionName, c.components.languageOverlay.destroy(), "proceedInSameLanguage" === i ? s.null(n.executeAction) || s.undefined(n.executeAction) || !s.function(n.executeAction) || n.executeAction() : "doNotProceedInSameLanguage" === i && (s.null(n.executeNoAction) || s.undefined(n.executeNoAction) || !s.function(n.executeNoAction) || n.executeNoAction()), l.hideOverlay(n.component, "languageSupportDisclaimerOverlay", i)
                }
            })
        }
    }
})), define("common/lib/utility/agreementParser", ["require", "common/lib/focusUtil", "blue/$"], (function (e) {
    "use strict";
    var t = e("common/lib/focusUtil"), n = function (n, i, o) {
        var r, a, s = i("body"), c = e("blue/$");
        n.preventDefault();
        var l = i(this).attr("href");
        void 0 === l && (l = c(this).attr("href")), "#" === l ? (r = s.find(o).find(".agreements")[0], a = s.find(r), r.scrollIntoView(), t.setFocus(i, a)) : r = s.find('a[name="' + l.substr(1) + '"]').parent().filter(":visible").first().attr("tabIndex", 0).focus()[0]
    };
    return {
        parse: function (e) {
            if ("object" == typeof e) return e;
            if ("string" == typeof e) {
                var t = document.createElement("div");
                return t.className = "agreements", t.innerHTML = e, t
            }
        }, removeTag: function (e, t) {
            var n = e.querySelector(t);
            if (n) try {
                e.removeChild(n)
            } catch (e) {
            }
        }, insertTag: function (e, t) {
            var n = e.firstChild;
            e.insertBefore(t, n)
        }, enableContentNavigation: function (e, t) {
            e("body").find(t).find('a[href^="#"]').click((function (i) {
                n.bind(this)(i, e, t)
            })).keyup((function (i) {
                13 === i.keyCode && n.bind(this)(i, e, t)
            }))
        }, removePrintAndImg: function (e) {
            var t = e.querySelector("p");
            if (t) for (var n = t.childNodes, i = 0; i < n.length; i++) switch (n[i].tagName) {
                case"IMG":
                    t.removeChild(n[i]);
                    break;
                case"A":
                    "helplinks" === n[i].className && t.removeChild(n[i])
            }
        }, removePrintAndImgDiv: function (e) {
            var t = e.querySelector("div.content-print");
            t && t.remove()
        }
    }
})), define("common/lib/API/requestDocuments/component/requestDocuments", ["require", "common/lib/utility/agreementParser", "common/lib/viewHelper/domEventTypeHelper", "appkit-utilities/content/globalContentMixin", "blue/is", "appkit-utilities/analytics/overlay"], (function (e) {
    "use strict";
    var t, n, i, o, r = e("common/lib/utility/agreementParser"), a = e("common/lib/viewHelper/domEventTypeHelper"),
            s = e("appkit-utilities/content/globalContentMixin"), c = null, l = null, u = null;

    function d(e) {
        c.context.controller.emit("requestDocuments/destroyView", {data: {actionName: e, componentName: c.key}})
    }

    function m(e) {
        t.null(l) || t.undefined(l) || t.empty(l) || t.null(u) || t.undefined(u) || t.empty(u) ? "SHOW" !== e && d(e) : "SHOW" === e ? n.showOverlay(l, u) : (n.hideOverlay(l, u, e), setTimeout((function () {
            d(e)
        }), 50))
    }

    function f(e) {
        var t;
        try {
            t = r.parse(e), r.removeTag(t, "link"), r.removeTag(t, "style"), r.removeTag(t, "script"), r.removePrintAndImg(t), r.removePrintAndImgDiv(t)
        } catch (e) {
            t = {outerHTML: ""}
        }
        return t.outerHTML
    }

    function p(e) {
        return e.model.get("getSpanishContent")().then((function (t) {
            return t = f(t.model.documentSource), e.model.set("documentSource2", t), t
        }))
    }

    function g(e, t, n, r, a) {
        var s;
        if (a && (a.messageVariation && c.context.dcu.dynamicSettings.set(c, "requestDocumentMessage", a.messageVariation), a.headerVariation && c.context.dcu.dynamicSettings.set(c, "requestDocumentHeader", a.headerVariation), a.spanishAgreementMessageVariation ? c.context.dcu.dynamicSettings.set(c, "requestSpanishAgreementMessage", a.spanishAgreementMessageVariation) : c.model.set("requestSpanishAgreementMessage", ""), a.spanishAgreementMessageServiceKey && (c.model.set("spanishAgreementMessageServiceKey", a.spanishAgreementMessageServiceKey), c.context.controller.emit("setSpanishAgreementUrl", {
            key: a.spanishAgreementMessageServiceKey,
            isPdfAgreement: "pdf" === (c.model.get("documentType") || "").toLowerCase()
        })), a.advisoryVariation ? c.context.dcu.dynamicSettings.set(c, "requestDocumentAdvisory", a.advisoryVariation) : c.model.set("requestDocumentAdvisory", ""), a.headerValueJson && (c.model.set("requestDocumentMessage", c.model.get("documentMessage")), c.model.set("requestDocumentAdvisory", c.model.get("documentAdvisory")), c.model.set("requestDocumentHeader", c.model.get(a.headerVariation ? "requestDocumentHeader" : "documentName"))), a.aemData && (s = a.aemData, i = !0, o = a.selectedLanguage || "en")), l = n, u = r, ".overlay" === e && m("SHOW"), this.output.emit("setDocumentViewerType", {
            data: {
                target: e,
                aemData: s,
                selectedLanguage: o,
                onRenderScrollElementSelector: "#documentViewer-document-container h2",
                displaySpanishHtmlOverlay: a.displaySpanishHtmlOverlay
            }
        }), this.model.get("documentSource")) {
            this.model.get("pdfOverride") && this.model.set("documentType", "");
            var d = "pdf" !== (this.model.get("documentType") || "").toLowerCase() ? f(this.model.get("documentSource")) : this.model.get("documentSource");
            this.model.set("documentSource", d), this.model.set("documentSource1", d), "function" == typeof this.model.get("getSpanishContent") && p(this), this.output.emit("state", {value: "enableContentNavigation"}), this.output.emit("showHidePrintIcon", {hidePrintIcon: !!t})
        }
    }

    return {
        init: function () {
            c = this, i = !1, o = "en", t = e("blue/is"), n = e("appkit-utilities/analytics/overlay"), s.call(this, ["updatesContentBelowAda", "currentSelectionAda"]), c.focusBackId = ""
        }, requestDocument: g, requestDocumentWithoutAnalytics: g, openUrlInNewTab: function (e) {
            this.newTabUrl = e, this.newTabUrl = ""
        }, exitRequestDocument: function () {
            m("exitRequestDocument")
        }, requestSpanishAgreement: function (e) {
            a.isAnchorClicked(e) && (e.context.displaySpanishHtmlOverlay ? this.context.controller.emit("showTransferMoneySpanishHtmlAgreementInOverlay", {key: e.context.spanishAgreementMessageServiceKey}) : this.context.controller.emit("showSpanishAgreement", {key: e.context.spanishAgreementMessageServiceKey}))
        }, requestSpanishLanguage: function () {
            if (!i) {
                var e = this, t = this.model.get("documentSource2");
                t ? this.model.set("documentSource", t) : p(e).then((function (t) {
                    e.model.set("documentSource", t)
                }))
            }
        }, requestEnglishLanguage: function () {
            i || this.model.set("documentSource", this.model.get("documentSource1"))
        }, printDocument: function () {
        }, requestLegalAgreementsHelpMessage: function () {
        }, exitLegalAgreementsHelpMessage: function () {
        }, accept: function () {
        }
    }
})), define("common/lib/API/requestDocuments/lib/legalAgreementsUtil", ["require", "blue/http", "appkit-utilities/language/helper"], (function (e) {
    "use strict";
    var t, n, i, o = e("blue/http"), r = e("appkit-utilities/language/helper");
    return function (e) {
        var a, s = {
            TRANSFER_AGREEMENT: {
                en: (t = e.config.APP_CQ5_HOST_CARD) + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/transfers_la.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/transfers_la.json"
            },
            INSTANT_ACCOUNT_VERIFICATION_SERVICE_ADDENDUM: {
                en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/iav_la.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/iav_la.json"
            },
            STATEMENT_BALANCE: {
                en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/autopay_auth_statementbal_la.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/autopay_auth_statementbal_la.json"
            },
            MINIMUM_PAYMENT_DUE: {
                en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/autopay_auth_miinpayment_la.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/autopay_auth_miinpayment_la.json"
            },
            FIXED_AMOUNT: {
                en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/autopay_auth_fixedamount_la.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/autopay_auth_fixedamount_la.json"
            },
            INTEREST_SAVING_BALANCE: {
                en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/autopay_auth_interestsavingsbal_la.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/autopay_auth_interestsavingsbal_la.json"
            },
            AUTOMATIC_MORTGAGE_PAYMENT_AGREEMENT: {
                en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/home_setup_rptg_pmts_text.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/home_setup_rptg_pmts_text.json"
            },
            AUTOMATIC_HOME_EQUITY_PAYMENT_AGREEMENT: {
                en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/pb_shared_legalheloc_p.json",
                es: t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/pb_shared_legalheloc_p.json"
            },
            MARGIN_AGREEMENT: {en: t + "/content/legal-agreements/legal-agreements-library/en/groups/legal-agreements/dwm_yit_trade_marginaccount_la.json"}
        };

        function c(e) {
            return {url: e, type: "GET", disableCsrf: !0, xhrFields: {withCredentials: !1}}
        }

        function l(e) {
            var t = e.sections;
            if (Array.isArray(t) && t.length > 0) {
                var n = {section: t[0].section, subSection: "<h2>" + e.title + "</h2>"};
                return t.forEach((function (e) {
                    n.subSection += e.subSection
                })), [n]
            }
        }

        function u(e) {
            return a(c(s[n].en)).then((function (t) {
                var o = t.result;
                return o ? (o.legalAgreement.sections = l(o.legalAgreement), e.AEMData = e.AEMData || {}, e.AEMData.en = o.legalAgreement, e.AEMData.en.useHTML = !0, e.displaySpanishLink && (e.displaySpanishHtmlOverlay = !0, e.spanishAgreementMessageVariation = n, e.spanishAgreementMessageServiceKey = i), e.selectedLanguage = "en", e.uniqueVersionId = o.legalAgreement.uniqueVersionId, Promise.resolve(e)) : Promise.reject(new Error("response.result must be defined"))
            }))
        }

        function d(e) {
            return a(c(s[n].es)).then((function (t) {
                var n = t.result;
                return n ? (n.legalAgreement.sections = l(n.legalAgreement), e.AEMData = e.AEMData || {}, e.AEMData.es = n.legalAgreement, e.AEMData.es.useHTML = !0, e.dualLanguageOverlay = !0, e.uniqueVersionId = n.legalAgreement.uniqueVersionId, Promise.resolve(e)) : Promise.reject(new Error("response.result must be defined"))
            }))
        }

        return a = function (e) {
            return new Promise((function (t, n) {
                o.request(e).then((function (e) {
                    t(e)
                })).catch((function (e) {
                    n(e)
                }))
            }))
        }, {
            requestLegalAgreementsOverlay: function (t, o) {
                if (!o.agreementType) throw new Error("Agreement type must be defined");
                var a = o;
                return a.doNotTriggerAnalyticsAction = !0, n = o.agreementType, i = o.spanishAgreementMessageServiceKey, function (e) {
                    return e.selectedLanguage ? {en: u, es: d}[e.selectedLanguage](e) : u(e).then((function () {
                        if ("es" === r.getContentLanguage() && !e.displaySpanishLink) return d(e)
                    }))
                }(a).then((function () {
                    return e.contextValidationAPI.showLegalAgreementOverlay(e.controller, a, t, "requestDocumentOverlay", a.executeCloseAction), Promise.resolve(a)
                })).catch((function (e) {
                    throw new Error(e)
                }))
            }, _overrideAjaxForUnitTest: function (e) {
                a = e
            }
        }
    }
})), define("bluespec/request_document", [], (function () {
    return {
        name: "REQUEST_DOCUMENT",
        data: {
            progressSteps: {
                type: "List",
                items: {
                    progressStepName: "Description",
                    progressStepNumber: "Numeric",
                    progressStepPercentageComplete: "Numeric"
                }
            },
            progressTotalSteps: {type: "Numeric"},
            progressCurrentStep: {type: "Numeric"},
            documentName: {type: "Description"},
            documentDate: {type: "Date"},
            documentFile: {type: "Description"},
            documentVersion: {type: "Description"},
            documentSource: {type: "Description"},
            termsAcceptanceStatus: {type: "OnOff"},
            newTabUrl: {type: "Description"},
            focusBackId: {type: "Description"},
            profileHeaderDisplayedState: {type: "OnOff"}
        },
        actions: {
            exitRequestDocument: !0,
            printDocument: !0,
            requestDocument: !0,
            accept: !0,
            acceptInvestmentsAccessAgreement: !0,
            acceptTermsConditions: !0,
            declineLegalAgreement: !0,
            requestLegalAgreementsHelpMessage: !0,
            exitLegalAgreementsHelpMessage: !0,
            requestAccountActivity: !0,
            requestInvestmentDashboard: !0,
            requestUserDashboard: !0,
            skipBack: !0,
            requestESignDisclosureConsent: !0,
            requestSpanishAgreement: !0,
            requestSpanishLanguage: !0,
            requestEnglishLanguage: !0
        },
        states: {
            exitConfirmationOverlay: !0,
            errorDisplayed: !0,
            focusPlacement: !0,
            documentViewerInitialized: !0,
            requestConfirmationOverlay: !0
        },
        settings: {
            exitLabel: !0,
            documentsHeader: !0,
            requestDocumentHeader: !0,
            onlineDisclosureHeader: !0,
            menuHeader: !0,
            requestDocumentMessage: !0,
            requestDocumentAdvisory: !0,
            exitRequestDocumentLabel: !0,
            termsAcceptanceStatusLabel: !0,
            acceptLabel: !0,
            requestLegalAgreementsHelpMessageAda: !0,
            legalAgreementsHelpMessage: !0,
            declineLegalAgreementLabel: !0,
            exitHelpMessageAda: !0,
            beginHelpMessageAda: !0,
            endHelpMessageAda: !0,
            printAda: !0,
            downloadDocumentAda: !0,
            exitAda: !0,
            chaseLogoAda: !0,
            documentTitleAda: !0,
            progressBarAda: !0,
            requestDocumentErrorHeader: !0,
            requestDocumentErrorAdvisory: !0,
            requestAccountActivityLabel: !0,
            requestInvestmentDashboardLabel: !0,
            requestSpanishAgreementMessage: !0,
            checkmarkAda: !0,
            requestDocumentConfirmationHeader: !0,
            requestDocumentConfirmationAdvisory: !0,
            skipBackLabel: !0,
            backLabel: !0,
            requestUserDashboardLabel: !0,
            termsAcceptanceStatusError: !0,
            requestEnglishLanguageLabel: !0,
            requestSpanishLanguageLabel: !0,
            skipBackToTopLabel: !0,
            profileHeader: !0
        }
    }
})), define("common/template/common/API/requestDocuments", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", f: [{
                t: 4, f: [{
                    t: 7, e: "div", m: [{t: 71, f: "restrictTabbing"}], f: [{
                        t: 7,
                        e: "blueModal",
                        m: [{n: "id", f: "documentviewermodal", t: 13}, {
                            n: "classes",
                            f: "improved transferAgreement",
                            t: 13
                        }, {n: "customDialogLayout", f: "true", t: 13}, {
                            n: "setFocusToSelector",
                            f: "documentViewerH1",
                            t: 13
                        }],
                        f: [{
                            t: 7,
                            e: "h1",
                            m: [{n: "id", f: "documentViewerH1", t: 13}, {
                                n: "class",
                                f: "dialogTitle",
                                t: 13
                            }, {n: "tabindex", f: "-1", t: 13}],
                            f: [{t: 3, x: {r: ["~/sanitizer", ".requestDocumentHeader"], s: "_0.sanitizeHTML(_1)"}}]
                        }, " ", {
                            t: 4,
                            f: [{
                                t: 7,
                                e: "p",
                                m: [{n: "class", f: "dialogMessage", t: 13}, {n: "tabindex", f: "-1", t: 13}],
                                f: [{t: 2, r: "requestDocumentMessage"}]
                            }],
                            n: 50,
                            r: "requestDocumentMessage"
                        }, " ", {
                            t: 4,
                            f: [{
                                t: 4,
                                f: [{
                                    t: 7,
                                    e: "p",
                                    m: [{n: "class", f: "toolbar", t: 13}, {n: "tabindex", f: "-1", t: 13}],
                                    f: [{t: 2, r: "requestDocumentAdvisory"}]
                                }],
                                n: 50,
                                r: "requestDocumentAdvisory"
                            }],
                            n: 51,
                            x: {r: ["hybrid", "isXS"], s: "_0||_1"}
                        }, " ", {t: 8, r: "spanishAgreementPartial"}, " ", {
                            t: 7,
                            e: "div",
                            m: [{
                                n: "lang",
                                f: [{
                                    t: 4,
                                    f: [{t: 2, r: "selectedLanguage"}, "-us"],
                                    n: 50,
                                    x: {r: ["isEspanol", "dualLanguageOverlay"], s: "_0&&_1"}
                                }],
                                t: 13
                            }],
                            f: [{
                                t: 7,
                                e: "blueDocumentviewer",
                                m: [{n: "id", f: "documentViewer", t: 13}, {
                                    n: "class",
                                    f: "inmodal",
                                    t: 13
                                }, {
                                    n: "printable",
                                    f: [{
                                        t: 4,
                                        f: ["true"],
                                        n: 50,
                                        x: {r: ["hybrid", "isXS", ".hidePrintIcon"], s: "!_0&&!_1&&!_2"}
                                    }],
                                    t: 13
                                }, {n: "content", f: [{t: 2, r: "documentSource"}], t: 13}, {
                                    n: "rPrinterClick",
                                    f: "printDocument",
                                    t: 13
                                }, {n: "rPrinterKeydown", f: "printDocument", t: 13}, {
                                    n: "contentType",
                                    f: [{t: 2, r: ".documentType"}],
                                    t: 13
                                }, {n: "scrollToTopText", f: [{t: 2, r: "skipBackToTopLabel"}], t: 13}, {
                                    n: "title",
                                    f: [{t: 2, r: ".title"}],
                                    t: 13
                                }]
                            }]
                        }, " ", {
                            t: 4,
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "footer action-buttons row", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "div",
                                    m: [{
                                        n: "class",
                                        f: "col-lg-4 col-md-4 col-sm-6 col-xs-12 col-lg-offset-8 col-md-offset-8 col-sm-offset-6",
                                        t: 13
                                    }],
                                    f: [{
                                        t: 7,
                                        e: "blueButton",
                                        m: [{n: "id", f: "modalTrigger", t: 13}, {
                                            n: "classes",
                                            f: "primary fluid",
                                            t: 13
                                        }, {n: "label", f: [{t: 2, r: "exitLabel"}], t: 13}, {
                                            n: "rClick",
                                            f: "exitRequestDocument",
                                            t: 13
                                        }]
                                    }]
                                }]
                            }],
                            n: 50,
                            x: {r: [".hideCloseButton"], s: "!_0"}
                        }]
                    }]
                }], n: 50, x: {r: ["docViewerType"], s: '_0==="modal"'}
            }, {
                t: 4,
                n: 50,
                f: [{t: 8, r: "spanishAgreementPartial"}, " ", {
                    t: 7,
                    e: "blueDocumentviewer",
                    m: [{n: "id", f: "documentViewer", t: 13}, {n: "class", f: "inmodal", t: 13}, {
                        n: "printable",
                        f: [{
                            t: 4,
                            f: ["true"],
                            n: 50,
                            x: {r: ["hybrid", "isXS", ".hidePrintIcon"], s: "!_0&&!_1&&!_2"}
                        }],
                        t: 13
                    }, {n: "content", f: [{t: 2, r: "documentSource"}], t: 13}, {
                        n: "rPrinterClick",
                        f: "printDocument",
                        t: 13
                    }, {n: "rPrinterKeydown", f: "printDocument", t: 13}, {
                        n: "contentType",
                        f: [{t: 2, r: ".documentType"}],
                        t: 13
                    }, {n: "scrollToTopText", f: [{t: 2, r: "skipBackToTopLabel"}], t: 13}, {
                        n: "title",
                        f: [{t: 2, r: ".title"}],
                        t: 13
                    }]
                }],
                x: {r: ["docViewerType"], s: '_0==="embedded"'},
                l: 1
            }]
        }], e: {}
    }
})), define("common/template/common/API/spanishAgreementPartial", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "div",
            f: [" ", {
                t: 4,
                f: [{
                    t: 4,
                    f: [{
                        t: 7,
                        e: "blueTabs",
                        m: [{n: "id", f: "languageSwitch", t: 13}, {
                            n: "tabs",
                            f: ["[ { label: ", {
                                t: 3,
                                r: "requestEnglishLanguageLabel",
                                s: !0
                            }, ', active: true, rClick: "getEnglishLanguage", adatext: ', {
                                t: 2,
                                r: "updatesContentBelowAda",
                                s: !0
                            }, ", selectedAdaText: ", {t: 2, r: "currentSelectionAda", s: !0}, " }, { label: ", {
                                t: 3,
                                x: {r: ["sanitizer", "requestSpanishLanguageLabel"], s: "_0.sanitizeHTML(_1)"},
                                s: !0
                            }, ', rClick: "getSpanishLanguage", adatext: ', {
                                t: 2,
                                r: "updatesContentBelowAda",
                                s: !0
                            }, ", selectedAdaText: ", {t: 2, r: "currentSelectionAda", s: !0}, " } ]"],
                            t: 13
                        }]
                    }],
                    n: 50,
                    r: "dualLanguageOverlay"
                }, {
                    t: 4,
                    n: 51,
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "id", f: "requestSpanishAgreementLink", t: 13}, {
                            n: "click",
                            f: "requestSpanishAgreement",
                            t: 70
                        }],
                        f: [{t: 3, r: "requestSpanishAgreementMessage"}]
                    }],
                    l: 1
                }],
                n: 50,
                r: "isEspanol"
            }]
        }],
        e: {}
    }
})), define("common/lib/modal/restrictTabbing", ["require", "blue/$"], (function (e) {
    "use strict";
    return function () {
        return function () {
            return function (t) {
                var n = e("blue/$"), i = n(t).find("*").filter("a, button, :input, [tabindex]").not("[tabIndex=-1]"),
                        o = i[0], r = i[i.length - 1], a = n(t).find("h1").first();
                return n(a.add(o)).keydown((function (e) {
                    e.shiftKey && 9 === e.keyCode && (r.focus(), e.preventDefault())
                })), n(r).keydown((function (e) {
                    9 === e.keyCode && !1 === e.shiftKey && (o.focus(), e.preventDefault())
                })), {
                    teardown: function () {
                        n(a).off("keydown"), n(o).off("keydown"), n(r).off("keydown")
                    }
                }
            }
        }
    }
})), define("common/lib/API/requestDocuments/webspec/requestDocuments", {
    name: "REQUEST_DOCUMENTS",
    preventDefault: !0,
    bindings: {dualLanguageOverlay: {binding: "BOTH"}, documentSource: {}, newTabUrl: {}, focusBackId: {}},
    triggers: {
        requestDocument: {action: "exitRequestDocument"},
        requestLegalAgreementsHelpMessage: {action: "exitRequestDocument"},
        exitLegalAgreementsHelpMessage: {action: "exitRequestDocument"},
        documentViewerInitialization: {},
        openUrlInNewTab: {},
        getSpanishLanguage: {action: "view.getSpanishLanguage"},
        getEnglishLanguage: {action: "view.getEnglishLanguage"}
    }
}), define("common/lib/API/requestDocuments/view/requestDocuments", ["require", "common/lib/utility/agreementParser", "appkit-utilities/common/mediaQueryListener", "common/lib/focusUtil", "common/lib/constants", "appkit-utilities/language/helper", "common/template/common/API/requestDocuments", "blue-ui/view/elements/button", "blue-ui/view/modules/modal", "blue-ui/view/elements/documentviewer", "blue-ui/view/elements/icon", "blue-ui/view/elements/iconwrap", "blue-ui/view/modules/tabs", "common/template/common/API/spanishAgreementPartial", "common/lib/modal/restrictTabbing", "common/lib/API/requestDocuments/webspec/requestDocuments"], (function (e) {
    "use strict";
    return function (t) {
        var n, i = e("common/lib/utility/agreementParser"), o = e("appkit-utilities/common/mediaQueryListener"),
                r = e("common/lib/focusUtil"), a = e("common/lib/constants"), s = e("appkit-utilities/language/helper"),
                c = this;

        function l() {
            var e = n[c.model.selectedLanguage];
            if (e) {
                var o = "";
                e.useHTML || !e.pdfPath || t.hybrid ? (e.sections && e.sections.forEach((function (e) {
                    o += e.subSection
                })), c.model.documentType = "html", c.model.title = e.title, c.model.documentSource = o, i.enableContentNavigation(t.page.$, "#documentViewer")) : (c.model.documentSource = e.pdfPath, c.model.title = e.title, c.model.documentType = "pdf")
            }
        }

        this.template = e("common/template/common/API/requestDocuments"), this.viewName = "requestDocument", this.views = {
            blueButton: e("blue-ui/view/elements/button"),
            blueModal: e("blue-ui/view/modules/modal"),
            blueDocumentviewer: e("blue-ui/view/elements/documentviewer"),
            blueIcon: e("blue-ui/view/elements/icon"),
            blueIconwrap: e("blue-ui/view/elements/iconwrap"),
            blueTabs: e("blue-ui/view/modules/tabs")
        }, this.partials = {spanishAgreementPartial: e("common/template/common/API/spanishAgreementPartial")}, this.model = {
            isXS: o.currentBreakpoint === o.BREAKPOINT.XS,
            isEspanol: s.getContentLanguage() === a.LANGUAGES.es,
            selectedLanguage: "en",
            documentType: "html",
            title: "",
            docViewerType: ""
        }, this.decorators = {restrictTabbing: e("common/lib/modal/restrictTabbing")(this)}, this.bridge = e("common/lib/API/requestDocuments/webspec/requestDocuments"), this.init = function () {
            this.bridge.on("printAgreement", (function () {
                window.print()
            })), this.bridge.on("state/enableContentNavigation", (function () {
                i.enableContentNavigation(t.page.$, "#documentViewer")
            })), this.bridge.on("setDocumentViewerType", (function (e) {
                e.data.target ? ".overlay" === e.data.target ? c.model.docViewerType = "modal" : c.model.docViewerType = "embedded" : c.model.docViewerType = "", n = e && e.data && e.data.aemData, c.model.selectedLanguage = e && e.data && e.data.selectedLanguage || "en", n && (c.model.dualLanguageOverlay = Object.keys(n).length > 1, l()), c.model.onRenderScrollElementSelector = e.data.onRenderScrollElementSelector, c.model.displaySpanishHtmlOverlay = e.data.displaySpanishHtmlOverlay
            })), this.bridge.on("showHidePrintIcon", (function (e) {
                c.model.hidePrintIcon = e.hidePrintIcon
            })), this.onData("focusBackId", (function (e) {
                e && t.$(e).focus()
            }), {init: !1}), this.onData("newTabUrl", (function (e) {
                e && window.open(e, "_blank")
            }), {init: !1})
        }, this.onReady = function () {
            this.model.isXS = o.currentBreakpoint === o.BREAKPOINT.XS, this.model.isEspanol = s.getContentLanguage() === a.LANGUAGES.es, this.scrollToDocumentEl(this.model.onRenderScrollElementSelector), r.setFocus(t.page.$, "#documentViewerH1")
        }, this.onDestroy = function () {
            t.page.broadcast("documentViewerDestroyed")
        }, this.onData("documentViewerInitialized", (function () {
            c.model && "DONE" === c.model.documentViewerInitialized && r.setFocus(t.page.$, "#documentViewerH1")
        })), this.scrollToDocumentEl = function (e) {
            if (e) {
                var n = t.page.$(e)[0];
                n && n.scrollIntoView()
            }
        }, this.getEnglishLanguage = function () {
            n && (this.model.selectedLanguage = "en", l()), this.scrollToDocumentEl(this.model.onRenderScrollElementSelector), this.trigger("requestEnglishLanguage")
        }, this.getSpanishLanguage = function () {
            n && (this.model.selectedLanguage = "es", l()), this.scrollToDocumentEl(this.model.onRenderScrollElementSelector), this.trigger("requestSpanishLanguage")
        }
    }
})), define("common/utility/adaUtility", ["require", "blue/$", "blue-ui/utilities/isFirefox"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = e("blue-ui/utilities/isFirefox");
    return {
        doFocus: function (e, t, i) {
            var o = (0, this.context.$)(e), r = void 0 !== i && !0 === i, a = !0 === r ? o.attr("tabindex") : null;
            !0 !== r && null != t && !1 === t || o.attr({tabindex: -1}), n() ? setTimeout((function () {
                o.focus()
            }), 0) : o.focus(), !0 === r && o.attr({tabindex: a})
        }, scrollTop: function (e, n) {
            var i = t("#payment_activity_menu"), o = t("#header-outer-container"), r = !!(i && i.length > 0 && i[0]),
                    a = !!(o && o.length > 0 && o[0]), s = 0;
            if (e && !n) try {
                s = t(e).offset().top - (!0 === r ? i.offset().top : a ? o.height() : 0)
            } catch (e) {
                s = 0
            }
            t("html,body").scrollTop(s)
        }, openFocusWindow: function (e, t, n) {
            if (!e || e.closed) {
                var i = "scrollbars,resizable";
                !window.screenX && 0 !== window.screenX || !window.screenY && 0 !== window.screenY || (i = i + ",top=" + (window.screenY + 20) + ",left=" + (window.screenX + 20)), e = window.open(t, n, i)
            }
            return e && e.focus && e.focus(), e
        }
    }
})), define("common/lib/API/requestDocuments/requestDocumentsAPI", ["require", "bluespec/request_document", "common/lib/API/requestDocuments/component/requestDocuments", "common/lib/API/requestDocuments/view/requestDocuments", "common/utility/adaUtility", "common/lib/jsBridge", "common/lib/API/contextValidation/contextValidationAPI"], (function (e) {
    "use strict";
    return function (t) {
        var n, i = e("bluespec/request_document"), o = e("common/lib/API/requestDocuments/component/requestDocuments"),
                r = e("common/lib/API/requestDocuments/view/requestDocuments"), a = e("common/utility/adaUtility"),
                s = e("common/lib/jsBridge"), c = this;
        i.data.dualLanguageOverlay = {type: "OnOff"};
        var l = e("common/lib/API/contextValidation/contextValidationAPI");
        t.contextValidationAPI = l, this.init = function () {
            c.openWindow = a.openFocusWindow, t.on({
                showLegalDocumentViewer: function (e) {
                    c.showDocumentViewer(e.data, e.component, e.analyticsKey, e.executeCloseAction)
                }, "requestDocuments/destroyView": function (e) {
                    !function (e) {
                        var i = e.data.actionName;
                        c.registry.hasComponent(e.data.componentName) && c.registry.destroyComponent(e.data.componentName), "exitRequestDocument" === i && (t.is.null(n.executeCloseAction) || t.is.undefined(n.executeCloseAction) || !t.is.function(n.executeCloseAction) || n.executeCloseAction())
                    }(e)
                }, setSpanishAgreementUrl: function (e) {
                    var t = e.isPdfAgreement ? c.context.config.APP_CQ5_HOST_CARD : c.context.config.contentAgreementHost,
                            n = e.key,
                            i = c.context.services.spanishAgreementServices && c.context.services.spanishAgreementServices[n];
                    "requestIAVSpanishAddendum" !== n ? i && i().then((function (n) {
                        c.model.set("spanishUrl", e.isPdfAgreement ? n.result.legalAgreement.pdfPath : t + n.htmlpath)
                    }), (function (e) {
                        c.context.logger.error(n + " rejected", e)
                    })) : c.model.set("spanishUrl", c.context.config.APP_CQ5_HOST_CARD + c.context.services.spanishAgreementServices[n])
                }, showSpanishAgreement: function (e) {
                    var t = c.model.get("spanishUrl"),
                            n = c.components.requestLegalDocumentOverlay || c.components.requestLegalDocumentEmbedded;
                    t && (e && "requestIAVSpanishAddendum" === e.key ? n && n.openUrlInNewTab(t) : (c.context.hybrid && "es.legalAgreement.mortgage.legalHome" === e.key && s.externalBrowser(c.context, {
                        url: t,
                        speedBump: !0
                    }), !c.context.hybrid && c.openWindow("", t, "spanishAgreementWindow")))
                }, showTransferMoneySpanishHtmlAgreementInOverlay: function (e) {
                    var t = c.components.requestLegalDocumentEmbedded;
                    c.context.config.APP_CQ5_HOST_CARD, c.context.services.spanishAgreementServices[e.key]().then((function (e) {
                        var n = {AEMData: {}};
                        n.AEMData.es = e.result.legalAgreement, n.AEMData.es.useHTML = !0, n.dualLanguageOverlay = !0, n.messageVariation = "TRANSFER_AGREEMENT", n.headerVariation = "TRANSFER_AGREEMENT", n.advisoryVariation = "TRANSFER_AGREEMENT", n.selectedLanguage = "es", n.displaySpanishHtmlOverlay = !0;
                        var i = t.requestSpanishAgreementMessage;
                        c.showDocumentViewer(n, t, "requestDocumentOverlay", (function () {
                            t.requestSpanishAgreementMessage = i, t.focusBackId = "#requestSpanishAgreementLink a"
                        }))
                    }))
                }
            })
        }, this.showDocumentViewer = function (e, t, i, o) {
            e.target = e.target || ".overlay", e.model = e.model || {}, ("pdf" === e.model.documentType || e.AEMData && e.selectedLanguage && e.AEMData[e.selectedLanguage] && e.AEMData[e.selectedLanguage].pdfPath) && ".overlay" === e.target && (e.model.documentContent && (e.model.documentSource = e.model.documentContent), e.model.pdfOverride = !0), c.registerComponent(e, t, i), n = {executeCloseAction: o}, c.loadViews(e.target)
        }, this.registerComponent = function (e, t, n) {
            var r = e.model || {}, a = {
                messageVariation: e.messageVariation,
                headerVariation: e.headerVariation,
                advisoryVariation: e.advisoryVariation,
                headerValueJson: e.headerValueJson,
                spanishAgreementMessageVariation: e.spanishAgreementMessageVariation,
                spanishAgreementMessageServiceKey: e.spanishAgreementMessageServiceKey,
                aemData: e.AEMData,
                selectedLanguage: e.selectedLanguage,
                displaySpanishHtmlOverlay: e.displaySpanishHtmlOverlay
            }, s = ".overlay" === e.target ? "requestLegalDocumentOverlay" : "requestLegalDocumentEmbedded";
            c.registry.hasComponent(s) && c.registry.destroyComponent(s), c.model.update(r), c.registry.registerComponent(s, {
                model: c.model.lens(),
                spec: i,
                methods: o
            });
            var l = c.components[s];
            e.doNotTriggerAnalyticsAction ? l.requestDocumentWithoutAnalytics(e.target, e.hideDocumentViewerPrintIcon, t, n, a) : l.requestDocument(e.target, e.hideDocumentViewerPrintIcon, t, n, a)
        }, this.loadViews = function (e) {
            var t = ".overlay" === e ? "requestLegalDocumentOverlay" : "requestLegalDocumentEmbedded";
            return c.executeCAV([c.components[t], r, {target: e, append: !1}])
        }
    }
})), define("common/lib/API/requestDocuments/services/spanishAgreementServices", [], (function () {
    "use strict";
    return function (e) {
        var t = e.config.APP_CQ5_HOST_CARD, n = function (e) {
            return {settings: {url: e, type: "GET", disableCsrf: !0, xhrFields: {withCredentials: !1}}}
        };
        this.serviceCalls = {
            "es.billpay.agreement.json": n(t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/C_BillPay_LA.json"),
            "es.business.billpay.agreement.json": n(t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/B_BillPay_LA.json"),
            "es.billpay.enrollment.agreement": n(t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/C_BillPay_LA.json"),
            "es.business.billpay.enrollment.agreement": n(t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/B_BillPay_LA.json"),
            "es.legalAgreement.homeEquity.heloc": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/pb_shared_legalheloc_p.json"),
            "es.legalAgreement.mortgage.legalHome": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/home_setup_rptg_pmts_text.json"),
            "es.transfer.agreement.json": n(t + "/content/legal-agreements/legal-agreements-library/es/groups/legal-agreements/transfers_la.json"),
            "es.legalAgreement.creditCard.MINIMUM_PAYMENT_DUE": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/AUTOPAY_AUTH_MinPayment_LA.json"),
            "es.legalAgreement.creditCard.FIXED_AMOUNT": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/AUTOPAY_AUTH_fixedamount_LA.json"),
            "es.quickpay.agreement.json": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/chasenet_la.json"),
            "es.legalAgreement.creditCard.STATEMENT_BALANCE": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/AUTOPAY_AUTH_StatementBal_LA.json"),
            "es.legalAgreement.creditCard.INTEREST_SAVING_BALANCE": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/AUTOPAY_AUTH_InterestSavingsBal_LA.json"),
            "es.legalAgreement.creditCard.BLUEPRINT_PAYMENT": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/AUTOPAY_AUTH_SpendFocus_LA.json"),
            "es.legalAgreement.creditCard.BLUEPRINT_AMOUNT": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/AUTOPAY_AUTH_Blueprint_LA.json"),
            "es.legalAgreement.gifting": n(contentAgreementHostUrl + "/content/chaseonline/es/legalagreements/dsa1_la.json")
        }, this.requestIAVSpanishAddendum = "/content/dam/legal-agreements/library/es/iav_la/versions/iav_la.pdf"
    }
})), define("common/lib/areaStore", ["require", "mout/object"], (function (e) {
    "use strict";
    var t = e("mout/object"), n = function (e) {
        this.settingsStore = e || {}
    };
    return n.prototype.set = function (e, n) {
        e && t.set(this.settingsStore, e, n)
    }, n.prototype.get = function (e) {
        return t.get(this.settingsStore, e)
    }, n
})), define("common/lib/autoLoanContext", {
    BRAND_SPECIFIC_CONTEXT: {
        requestSubaruCarDetails: "CHANNELNET_SUBARU",
        requestJaguarCarDetails: "CHANNELNET_JAGUAR",
        requestLandRoverCarDetails: "CHANNELNET_LANDROVER",
        requestMaseratiCarDetails: "CHANNELNET_MASERATI",
        requestMazdaCarDetails: "CHANNELNET_MAZDA",
        requestAstonMartinCarDetails: "CHANNELNET_ASTON"
    }
}), define("common/lib/bandwidthQuality", ["require", "common/lib/utility/hybridMixin"], (function (e) {
    "use strict";
    var t = e("common/lib/utility/hybridMixin"), n = null, i = {}, o = {failedServices: [], responseTimes: []},
            r = function () {
                o = {responseTimes: [], failedServices: []}
            }, a = function () {
                if (!i.enabled) return "NOT ENABLED";
                var e = o.responseTimes.filter((function (e) {
                    return e > i.maxTime
                })).length / o.responseTimes.length * 100 / 2;
                return (e += o.failedServices.length / o.responseTimes.length * 100 / 2) > i.svcDelayPercentage ? "BAD" : "GOOD"
            };
    return {
        init: function (e, t) {
            n = e;
            try {
                i = JSON.parse(t)
            } catch (e) {
                e && n.logger.error("[Bandwidth] JSON parse failed. Using default values."), i = {
                    enabled: !1,
                    maxTime: 2500,
                    svcDelayPercentage: 60,
                    maxLength: 25,
                    resetAfter: 5
                }
            }
            i.signalStrength = null, i.startTime = 0
        }, get: a, send: function () {
            var e = a();
            i.enabled && (null === i.signalStrength || "BAD" === e && "BAD" !== i.signalStrength || "GOOD" === e && "BAD" === i.signalStrength) && (i.signalStrength = e, t.call(n), n.dispatchHybridEvent("bandwidthQuality", n, {quality: i.signalStrength}), 0 === i.startTime && (i.startTime = Date.now()), Math.floor((Date.now() - i.startTime) / 6e4) > i.resetAfter && r())
        }, store: function (e) {
            if (i.enabled && e) {
                var t = JSON.parse(JSON.stringify(e));
                if (t = t && t.buckets && t.buckets.service && t.buckets.service.details, Array.isArray(t)) for (var n = 0; n < t.length; n++) -1 === o.failedServices.indexOf(t[n].key) && t[n].ms && o.responseTimes.unshift(t[n].ms) > i.maxLength && o.responseTimes.pop();
                if (!0 === e.hasOwnProperty("code")) {
                    var r = e.request && e.request.url;
                    r = r && "blue/http/" + r, -1 === o.failedServices.indexOf(r) && o.failedServices.push(r)
                }
            }
        }, reset: r
    }
})), define("common/lib/componentErrorMixin", ["require", "blue/util", "mout/object", "appkit-utilities/content/dcu"], (function (e) {
    var t = {500: "NOT_MAPPED", 504: "SYSTEM_FAILURE"}, n = e("blue/util").lang.defaults, i = e("mout/object").fillIn,
            o = e("appkit-utilities/content/dcu");
    return function () {
        var e = Array.prototype.shift.apply(arguments), r = Array.prototype.slice.call(arguments);

        function a(e, t) {
            return e[t] && "Content" !== e[t]
        }

        var s = function (e, i, r, s) {
            i = n(i, {}), r = n(r, this.model && this.model.get(), {}), s = Object.assign({}, t, s);
            var c, l = "string" == typeof i.statusCode && i.statusCode || i.status, u = s[l];
            if (u && (c = u, o.dynamicContent.set(this, e, r, u)), !a(this, e) && l && (c = l, o.dynamicContent.set(this, e, r, l)), a(this, e) || (c = "SYSTEM_FAILURE", o.dynamicContent.set(this, e, r, c)), a(this, e) || (c = "SYSTEM_FAILURE", this[e] = "ERROR"), void 0 === l) throw i;
            return c
        }, c = function (e, t, n, i) {
            var o;
            return s.call(this, e, t, n, i), o = this[e], this[e] = "", o
        }, l = function (e, t, i) {
            var o = this, a = null, c = null;
            if (n(i, r, []).forEach((function (n) {
                try {
                    a = s.call(o, n, e, t && t.dataTokens, t && t.statusCodeMap)
                } catch (e) {
                    c = e
                }
            })), c) throw c;
            return a
        }, u = function (e, t, i) {
            var o = this;
            return n(i, r, []).reduce((function (n, i) {
                return n[i] = c.call(o, i, e, t && t.dataTokens, t && t.statusCodeMap), n
            }), {})
        }, d = function (e) {
            var t = this;
            return n(e, r, []).map((function (e) {
                return t.model.set(e, "")
            }))
        }, m = {
            resetError: d.bind(e),
            getError: u.bind(e),
            setError: l.bind(e),
            getErrorContent: c.bind(e),
            setErrorContent: s.bind(e)
        };
        return e && (i(e, m), e.resetError()), n(e, m)
    }
})), define("common/lib/componentUtils", ["require", "blue/root", "common/lib/componentErrorMixin"], (function (e) {
    "use strict";
    var t = e("blue/root").hybrid;
    return function (n) {
        var i;
        e("common/lib/componentErrorMixin").apply(n, arguments), n.displayError = function (e, t, n, i, o) {
            this.setError(n, {dataTokens: i, statusCodeMap: o}, [e, t])
        }, n.setFocus = function (e, t) {
            var i = {value: "focus", focus: e};
            if (t) for (var o in t) Object.hasOwnProperty.call(t, o) && (i[o] = t[o]);
            n.output.emit("setFocus", i)
        }, n.setFocusOnH2 = function (e, i) {
            t ? n.setFocus(e, i) : this.context.application.broadcast("setFocusOnMenuHat")
        }, n.setFocusFromEvt = function (e) {
            n.output.emit("state", {value: "setDirtyFocus", data: e})
        }, n.viewModel = n.viewModel || (i = {}, {
            set: function (e, t) {
                n.output.emit("updateViewModel", {key: e, value: t}), i[e] = t
            }, get: function (e) {
                return i[e]
            }
        })
    }
})), define("common/lib/contentEvent", [], (function () {
    "use strict";
    return {
        contentEvent: {
            get: function (e, t, n) {
                return {name: e, data: {placement: t, variation: n}}
            }
        }
    }
})), define("common/lib/contentUtil", ["require", "blue-app/settings", "blue/log", "blue/is", "appkit-utilities/content/dcu", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue-app/settings"), n = e("blue/log")("[contentUtil]"), i = e("blue/is"),
            o = e("appkit-utilities/content/dcu"), r = e("blue/util").lang.defaults, a = function (e) {
                n.warn(new Date + " contentUtil WARNING " + e)
            };
    return {
        getList: function (e, n, a, s, c, l) {
            var u, d, m = s ? t.get("LOCALIZED_CONTENT_" + s) : t.get("LOCALIZED_CONTENT_app"), f = [],
                    p = e.split("."), g = p[0], y = p[1], h = new RegExp("^" + y + "\\."),
                    v = i.defined(m) ? m[g] : null;
            if (n = r(n, "code"), a = r(a, "description"), v) for (u in v) h.test(u) && v[u] && ((d = {})[n] = u.replace(h, ""), d[a] = v[u], f.push(d));
            return function (e, n, i, r, a, s, c) {
                var l, u, d = t.get("LOCALIZED_CONTENT", t.Type.APP), m = new RegExp("^" + e + "\\.");
                if (r) Object.keys(r).forEach((function (e) {
                    u = a + "." + e;
                    var t = o.dynamicSettings.get(s, u);
                    (l = {})[n] = e, l[i] = t, c.push(l)
                })); else if (!c.length) for (u in d) m.test(u) && ((l = {})[n] = u.replace(m, ""), l[i] = d[u], c.push(l))
            }(e, n, a, c, y, l, f), f
        }, getValue: function (e, n, o) {
            var r, s = o ? t.get("LOCALIZED_CONTENT_" + o) : t.get("LOCALIZED_CONTENT_app"),
                    c = t.get("LOCALIZED_CONTENT", t.Type.APP), l = e.split("."), u = l[0], d = l[1],
                    m = i.defined(s) ? s[u] : null, f = d ? d + "." + n : n, p = e + "." + n;
            return m ? r = m[f] : a("specContent NOT DEFINED"), r || (a("specContent value for key '" + f + "' from spec '" + u + "' NOT FOUND"), c ? (r = c[p]) || a("localPropKey '" + p + "' NOT FOUND in localContentApp") : a("localContentApp not defined")), r || (r = "CUTIL-UND"), r
        }
    }
})), define("common/lib/controllerUtility", ["require", "appkit-utilities/content/dcu"], (function (e) {
    "use strict";
    var t = e("appkit-utilities/content/dcu");
    return {
        setDeferred: function (e) {
            this.deferred.push(e)
        }, setComponentModelWithLens: function (e, t, n) {
            this.components && this.components[e] && this.components[e].model.lens(n).set(t)
        }, setComponentModelData: function (e, t, n) {
            if (this.components && this.components[e]) {
                var i = this.components[e];
                n ? i.model.set(n, t) : i.model.set(t)
            }
        }, getComponentModelData: function (e, t) {
            var n;
            if (this.components && this.components[e]) {
                var i = this.components[e];
                n = t ? i.model.get(t) : i.model.get()
            }
            return n
        }, getComponentPropertyData: function (e, t) {
            var n;
            return this.components && this.components[e] && (n = this.components[e][t]), n
        }, getComponentDynamicContent: function (e, n, i) {
            var o;
            return this.components && this.components[e] && (o = t.dynamicSettings.get(this.components[e], n, i)), o
        }, callComponentMethod: function (e, t, n) {
            this.components && this.components[e] && this.context.is.function(this.components[e][t]) && this.components[e][t](n)
        }
    }
})), define("common/lib/momentTimeZoneData", {"America/New_York": "America/New_York|EST EDT EWT EPT|50 40 40 40|01010101010101010101010101010101010101010101010102301010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010|-261t0 1nX0 11B0 1nX0 11B0 1qL0 1a10 11z0 1qN0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 11z0 1o10 11z0 RB0 8x40 iv0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1cN0 1cL0 1cN0 1cL0 s10 1Vz0 LB0 1BX0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0|21e6"}), define("common/lib/dateUtility", ["require", "moment-timezone", "common/lib/constants", "common/lib/momentTimeZoneData"], (function (e) {
    "use strict";
    var t = e("moment-timezone"), n = e("common/lib/constants");
    return t.tz.add(e("common/lib/momentTimeZoneData")[n.TIME_ZONES.America_New_York]), {
        getTodaysESTDate: function () {
            var e = new Date, i = t(e, t.ISO_8601);
            return t.tz && t(t.tz(i, "America/New_York").format(n.DATE_FORMATS.year_Month_DateFormat))
        }, isDateBeyondDays: function (e, n, i, o) {
            return t(o, i).diff(e, "days") <= n
        }
    }
})), define("common/lib/downloadUtil", ["require", "blue/root", "blue/is"], (function (e) {
    "use strict";
    var t = e("blue/root"), n = e("blue/is").defined;
    return function e(i, o, r) {
        var a, s, c, l, u, d, m, f, p = t, g = "application/octet-stream", y = [r, g].find(n), h = i, v = !o && !r && h,
                b = document.createElement("a"), A = function (e) {
                    return String(e)
                }, E = function (e, t, n, i) {
                    return e || t || n || i
                }(p.Blob, p.MozBlob, p.WebKitBlob, A), T = [o, "download"].find(n);
        if (E = (c = E).call ? c.bind(p) : Blob, function () {
            "true" === String(this) && (y = (h = [h, y])[0], h = h[1])
        }.call(this), v && v.length < 2048 && (T = v.split("/").pop().split("?")[0], b.href = v, -1 !== b.href.indexOf(v))) {
            var C = new XMLHttpRequest;
            return C.open("GET", v, !0), C.responseType = "blob", C.onload = function (t) {
                e(t.target.response, T, g)
            }, setTimeout((function () {
                C.send()
            }), 0), C
        }
        if (/^data:([\w+-]+\/[\w+.-]+)?[,;]/.test(h)) {
            if (!(h.length > 2096103.424 && E !== A)) return l = h, u = T, navigator.msSaveBlob ? navigator.msSaveBlob(D(l), u) : I(l);
            h = D(h), d = h.type, m = g, y = d || m
        } else {
            for (var S = 0, _ = new Uint8Array(h.length), O = _.length; S < O; ++S) _[S] = h.charCodeAt(S);
            h = new E([_], {type: y})
        }

        function D(e) {
            for (var t = e.split(/[:;,]/), n = t[1], i = ("base64" === t[2] ? atob : decodeURIComponent)(t.pop()), o = i.length, r = 0, a = new Uint8Array(o); r < o; ++r) a[r] = i.charCodeAt(r);
            return new E([a], {type: n})
        }

        function I(e, n) {
            if ("download" in b) return b.href = e, b.setAttribute("download", T), b.className = "download-js-link", b.innerHTML = "downloading...", b.style.display = "none", document.body.appendChild(b), setTimeout((function () {
                b.click(), document.body.removeChild(b), !0 === n && setTimeout((function () {
                    p.URL.revokeObjectURL(b.href)
                }), 250)
            }), 66), !0;
            if (/(Version)\/(\d+)\.(\d+)(?:\.(\d+))?.*Safari\//.test(navigator.userAgent)) return /^data:/.test(e) && (e = "data:" + e.replace(/^data:([\w/\-+]+)/, g)), t.open(e) || t.confirm("Displaying New Document\n\nUse Save As... to download, then click back to return to this page.") && (location.href = e), !0;
            var i = document.createElement("iframe");
            document.body.appendChild(i), !n && /^data:/.test(e) && (e = "data:" + e.replace(/^data:([\w/\-+]+)/, g)), i.src = e, setTimeout((function () {
                document.body.removeChild(i)
            }), 333)
        }

        if (a = function (e) {
            return e instanceof E ? e : new E([e], {type: y})
        }(h), navigator.msSaveBlob) return navigator.msSaveBlob(a, T);
        if (p.URL) I(p.URL.createObjectURL(a), !0); else {
            if ("string" == typeof (f = a) || f.constructor === A) try {
                return I("data:" + y + ";base64," + p.btoa(a))
            } catch (e) {
                return I("data:" + y + "," + encodeURIComponent(a))
            }
            (s = new FileReader).onload = function () {
                I(this.result)
            }, s.readAsDataURL(a)
        }
        return !0
    }
})), define("common/lib/ecdConfig", [], (function () {
    return {
        ecdStandIn: {"convodeck.message": !0, "convodeck.search": !0, manageChasePay: !0, "profile.menu": !1},
        c3StandIn: {"convodeck.message": !0, "convodeck.search": !0},
        splash: {makeBillPayment: !0, requestPaymentActivity: !0, manageChasePay: !0, "profile.menu": !0},
        readOnly: {
            makeBillPayment: !0,
            sendOrRequestMoney: !0,
            transferMoney: !0,
            makeWireTransfers: !0,
            manageExternalAccounts: !0,
            requestPaymentActivity: !0,
            manageChasePay: !0,
            managePayees: !0,
            manageMoneyTransferContacts: !0,
            updateMyMoneyTransferProfile: !0,
            manageFundingAccounts: !0,
            makeRecurringWireTransfers: !0,
            manageTransferContacts: !0,
            makeBrokerageTransfers: !0,
            makeCardBalanceTransfers: !0,
            qpAddRecipient: !0,
            "convodeck.message": !0,
            "convodeck.search": !0,
            "convodeck.notification": !0,
            investmentSummary: !0,
            investmentTYCD: !0,
            disableTrade: !0,
            disableUltimateRewardsLink: !0
        },
        safeMode: {"convodeck.notification": !0},
        chasePaySplash: {manageChasePay: !0},
        billPaySplash: {
            makeBillPayment: !0,
            managePayees: !0,
            manageFundingAccounts: !0,
            bill_pay_payees_menu_item: !0,
            bill_pay_repeating_menu_item: !0
        },
        quickPaySplash: {
            sendOrRequestMoney: !0,
            manageMoneyTransferContacts: !0,
            updateMyMoneyTransferProfile: !0,
            quickpay_pending_actions_menu_item: !0,
            quickpay_money_received_menu_item: !0,
            quickpay_money_sent_menu_item: !0,
            quickpay_repeating_payments_menu_item: !0,
            quickpay_requests_activity_menu_item: !0
        },
        xferSplash: {transferMoney: !0, transfer_funds_all_menu_item: !0, transfer_funds_recurring_menu_item: !0},
        universalPayeeSplash: {managePayees: !0, manageMoneyTransferContacts: !0},
        wireSplash: {
            makeWireTransfers: !0,
            manageTransferContacts: !0,
            makeRecurringWireTransfers: !0,
            wire_funds_all_activity_menu_item: !0
        },
        taxStatementsSplash: {taxDocument: !0},
        statementsSplash: {statementsSplashInv: !0}
    }
})), define("common/lib/emulationUtils", ["require", "blue/siteData", "appkit-utilities/accountInfo/accountInfo"], (function (e) {
    var t, n = e("blue/siteData"), i = e("appkit-utilities/accountInfo/accountInfo");

    function o() {
        t || (t = new i({profileType: n.getData("profileType")}))
    }

    function r() {
        return o(), t.isFinancialAdvisor()
    }

    return {
        isEmulationMode: r, isGWMEmulationMode: function () {
            return o(), r() && t.isWealthyUser()
        }
    }
})), define("common/lib/exceptionUtility", ["require", "blue/siteMode", "blue/siteFeature", "common/lib/ecdConfig"], (function (e) {
    "use strict";
    var t, n, i, o, r, a, s, c, l, u, d, m, f, p, g, y, h, v = !1, b = function (e) {
        return f[e]
    };
    return {
        getExceptionModes: function () {
            var e = [];
            return i && e.push(b("ecdStandIn")), o && e.push(b("readOnly")), r && e.push(b("c3StandIn")), a && e.push(b("splash")), y && e.push(b("chasePaySplash")), s && e.push(b("billPaySplash")), c && e.push(b("quickPaySplash")), l && e.push(b("xferSplash")), u && e.push(b("universalPayeeSplash")), this.getWires() && e.push(b("wireSplash")), m && e.push(b("taxStatementsSplash")), p && e.push(b("statementsSplash")), h && e.push(b("safeMode")), e
        }, hasExceptionProp: function (b) {
            v || (v = !0, t = e("blue/siteMode"), n = e("blue/siteFeature"), f = e("common/lib/ecdConfig"), i = t.isModeEnabled("ecdStandIn"), o = t.isModeEnabled("readOnly"), r = t.isModeEnabled("c3StandIn"), a = t.isModeEnabled("blackoutFeature"), s = t.isModeEnabled("billPaySplash"), y = t.isModeEnabled("chasePaySplash"), c = t.isModeEnabled("quickPaySplash"), l = t.isModeEnabled("xferSplash"), u = t.isModeEnabled("universalPayeeSplash"), d = t.isModeEnabled("wireSplash"), m = t.isModeEnabled("taxStatementsSplash"), p = t.isModeEnabled("statements"), h = t.isModeEnabled("safeMode"), g = n.getData("wire"));
            for (var A = this.getExceptionModes(), E = 0; E < A.length; E++) {
                if (!0 === A[E][b]) return !0
            }
            return !1
        }, getWires: function () {
            return null == g ? d : d || !g
        }, getSplashList: function () {
            return o && (s = c = l = d = u = !0, g = !1), i && !o && (s = c = l = d = u = !1, g = !0), {
                BILL_PAY_SPLASH_MODE: s,
                QUICKPAY_SPLASH_MODE: c,
                TRANSFER_SPLASH_MODE: l,
                WIRE_SPLASH_MODE: this.getWires(),
                UNIVERSAL_PAYEE_SPLASH_MODE: u
            }
        }, isSplashEnable: function () {
            return y || s || c || l || this.getWires() || u
        }, getSiteModeMap: function (e) {
            var t = this.isSplashEnable(), n = e.isModeEnabled("readOnly"), i = e.isModeEnabled("readOnlyUnplanned"),
                    o = e.isModeEnabled("ecdStandIn"), r = e.isModeEnabled("c3StandIn"),
                    a = e.isModeEnabled("safeMode"), s = {
                        UNPLANNED_READ_ONLY: !1,
                        BELLVILLE_READ_ONLY: !1,
                        ECD_STAND_IN: !1,
                        C3_READ_ONLY: !1,
                        SPLASH: !1,
                        SAFE_MODE: !1,
                        EMERGENCY: !0
                    };
            return i && n ? Object.assign({}, s, {UNPLANNED_READ_ONLY: !0}) : n ? Object.assign({}, s, {BELLVILLE_READ_ONLY: !0}) : o ? Object.assign({}, s, {ECD_STAND_IN: !0}) : t ? Object.assign({}, s, {SPLASH: !0}) : r ? Object.assign({}, s, {C3_READ_ONLY: !0}) : {
                UNPLANNED_READ_ONLY: !1,
                BELLVILLE_READ_ONLY: n,
                ECD_STAND_IN: o,
                C3_READ_ONLY: r,
                SAFE_MODE: a,
                SPLASH: !1,
                EMERGENCY: !0
            }
        }
    }
})), define("common/lib/externalCallApi", ["require", "blue/is", "blue/util", "blue/declare", "blue/resolver/module"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("blue/util").lang.defaults;
    return e("blue/declare")({
        constructor: function (i) {
            var o = i.context && i.context.app, r = i, a = {}, s = {}, c = function (e) {
                Object.hasOwnProperty.call(e, "logName") && (r.context.logger.name = e.logName), r.context.logger[e.type || "info"](e.message)
            }, l = function (e) {
                return Object.prototype.hasOwnProperty.call(o.settings.controllers, e) && t.object(o.settings.controllers[e])
            }, u = function (t, n) {
                new Promise((function (n) {
                    var i = new (e("blue/resolver/module"));
                    i.prefix = "wire!", i.suffix = "/" + t, i.resolve(o.appName).onValue((function (e) {
                        Object.keys(e.controllers).forEach((function (t) {
                            o.settings.controllers.addController(t, e.controllers[t])
                        })), n(e)
                    }))
                })).then((function (e) {
                    Object.keys(e.controllers).forEach((function (t) {
                        "function" == typeof e.controllers[t] && (l(t) ? c({
                            logName: "[externalCallApi::loadBundle]",
                            message: ["controller already loaded", t, o.settings.controllers[t]]
                        }) : e.controllers[t]().then((function (e) {
                            o.settings.controllers[t] = e.controller, c({
                                logName: "[externalCallApi::loadBundle]",
                                message: ["loaded controller", t, o.settings.controllers[t]]
                            })
                        })))
                    })), n()
                }), (function (e) {
                    c({logName: "[externalCallApi::loadBundle]", message: e})
                })).catch((function (e) {
                    c({logName: "[externalCallApi::loadBundle]", message: ["caught", e]})
                }))
            }, d = function (e, t) {
                l(e) ? (c({
                    logName: "[externalCallApi::loadController]",
                    message: ["controller already loaded", e, o.settings.controllers[e]]
                }), t()) : o.settings.controllers[e]().then((function (n) {
                    o.settings.controllers[e] = n.controller, c({
                        logName: "[externalCallApi::loadController]",
                        message: ["loaded controller", e, o.settings.controllers[e]]
                    }), t()
                }), (function (e) {
                    c({logName: "[externalCallApi::loadController]", message: e})
                })).catch((function (e) {
                    c({logName: "[externalCallApi::loadController]", message: ["caught", e]})
                }))
            }, m = function () {
                a = {}
            }, f = {};
            return {
                getExternalCallData: function () {
                    return a
                }, getExternalCallReturnData: function () {
                    return s
                }, getRestoreStateData: function () {
                    return f
                }, hasReturnData: function (e) {
                    return e = e || null, !t.null(s) && !t.empty(s) && (null === e || Object.hasOwnProperty.call(s, e))
                }, hasStateData: function () {
                    return !(t.null(f) || t.empty(f))
                }, invokeController: function (e, n, i) {
                    switch (!0) {
                        case l(e):
                            i();
                            break;
                        case t.null(n):
                            d(e, i);
                            break;
                        default:
                            u(n, i)
                    }
                }, isExternalCall: function () {
                    return Object.hasOwnProperty.call(a, "caller")
                }, makeExternalCall: function (e, t) {
                    t.isArea ? r.context.area.broadcast(e, t) : r.context.application.broadcast(e, t)
                }, resetExternalCallData: m, resetExternalCallReturnData: function () {
                    s = {}, f = {}
                }, returnToCaller: function (e) {
                    var t = !!(e = n(e, {})).cancel, i = n(e.result, {}),
                            o = t && Object.hasOwnProperty.call(a, "cancelUrl") ? a.cancelUrl : a.caller + (t || !Object.hasOwnProperty.call(a, "noFlag") || a.noFlag ? "" : ";newPayFromAccount=true");
                    if (Object.hasOwnProperty.call(a, "promise")) {
                        var s = e.resolve || function () {
                            c({
                                logName: "[externalCallApi::returnToCaller]",
                                message: "promise " + (t ? "cancelled" : "finished")
                            })
                        }, l = e.reject || function (e) {
                            c({logName: "[externalCallApi::returnToCaller]", message: ["promise failed", e]})
                        };
                        a.promise(i).then(s, l)
                    }
                    m(), r.context.state(o)
                }, setExternalCallData: function (e) {
                    a = e
                }, setExternalCallReturnData: function (e) {
                    s = e
                }, setRestoreStateData: function (e) {
                    f = e
                }, showDocumentViewer: function (e) {
                    r.context.application.broadcast("showDocumentViewer", {data: e})
                }
            }
        }
    })
})), define("common/lib/fakeComponent", [], (function () {
    "use strict";
    return function (e, t) {
        return {spec: {name: e}, area: {name: t || "profile"}, context: {area: {areaName: t || "profile"}}}
    }
})), define("common/lib/feature", ["require", "exports", "module", "blue/is"], (function (e, t, n) {
    "use strict";
    var i = n.config() || {}, o = e("blue/is");
    return {
        enabled: function (e) {
            if (!o.string(e)) return !1;
            var t = i.feature;
            if (!o.object(t)) return !1;
            var n = t[e];
            return o.boolean(n) ? n : !!o.string(n) && "true" === n.toLowerCase()
        }, getList: function (e) {
            return i.feature && i.feature[e] || {}
        }
    }
})), define("common/lib/featureFlags/featureMixin", ["require", "blue/util", "blue/is", "blue/log"], (function (e) {
    "use strict";
    var t = e("blue/util").lang.defaults, n = e("blue/is"), i = e("blue/log")("[common/lib/featureFlags/featureFlags]");

    function o(e, t, n) {
        if (!t.key) throw new Error("Please provide the flag key for " + e);
        if (void 0 === t.defaultValue || null === t.defaultValue || "" === t.defaultValue) throw new Error("Please provide default value for the flag " + e);
        this.key = t.key, this.defaultValue = t.defaultValue, this.featureFlagsService = n.featureFlags
    }

    return Object.assign(o.prototype, {
        strategy: function (e) {
            var o = this;
            if (!e || "object" != typeof e) throw new Error("Please provide an object of {value1: <callback>, value2: <callback>");
            if (!e[this.defaultValue]) throw new Error("No callback defined for the default value: " + this.defaultValue);
            return {
                execute: function () {
                    return o.featureFlagsService.getFeatureFlagValue(o.key, o.defaultValue).then((function (r) {
                        return n.defined(e[r]) || i.warn("No callback defined for the value: " + r + ".  Hence, executing the default callback for: " + o.defaultValue), t(e[r], e[o.defaultValue])()
                    })).catch((function (e) {
                        return i.error("Error occured while executing the strategy with flagKey: " + o.key + ", defaultValue: " + o.defaultValue + " Error: " + e && e.stack), Promise.reject(e)
                    }))
                }
            }
        }, getFeatureFlagValue: function () {
            return this.featureFlagsService.getFeatureFlagValue(this.key, this.defaultValue)
        }, getFeatureFlagValueSync: function () {
            return this.featureFlagsService.getFeatureFlagValueSync(this.key, this.defaultValue)
        }
    }), function (e) {
        function t(t, n) {
            if (!n || "object" != typeof n) throw new Error("Please provide feature options with structure {key: <featureFlagKey>, defaultValue: <default value>} for " + t);
            return new o(t, n, e)
        }

        this.addFeatures = function (e) {
            var n;
            e = e || {};
            var i = Object.getOwnPropertyNames(e), o = Object.getOwnPropertyNames(Object.getPrototypeOf(this)),
                    r = i.filter((function (e) {
                        return -1 !== o.indexOf(e)
                    }));
            if (r && r.length) throw new Error("Flag key(s) [" + r + "] are overlapping with common provider keys. Please use different names.");
            var a = this;
            Object.keys(e).forEach((function (i) {
                n = e[i], Object.defineProperty(a, i, {value: t(i, n), enumerable: !0})
            }))
        }, this.getFeatureFlagValues = function (e) {
            var t = this, n = e.filter((function (e) {
                return void 0 === t[e]
            }));
            if (n.length) return Promise.reject('Flags "' + n + '" are not mapped with dark canary flags');
            var i = e.map((function (e) {
                return t[e].getFeatureFlagValue()
            }));
            return Promise.all(i).then((function (t) {
                var n = {};
                return t.forEach((function (t, i) {
                    n[e[i]] = t
                })), n
            }))
        }
    }
})), define("common/lib/featureFlags/commonFeatureProvider", ["require", "common/lib/featureFlags/featureMixin"], (function (e) {
    "use strict";
    var t, n = {
        jpoStackedTileEnabled: {key: "cxo.jpo.ideas.stackedTile.enable", defaultValue: !0},
        garageCollectionFlag: {key: "cxo.garageCollections.enable", defaultValue: !1},
        pmbLogsFlag: {key: "app.pmblog.enabled", defaultValue: !1},
        wiresHybridFlag: {key: "app.wires.hybrid.enable", defaultValue: !1},
        a_string_flag: {key: "a_string_flag", defaultValue: "red"},
        an_int_flag: {key: "an_int_flag", defaultValue: 2},
        appointmentSchedulerFlag: {key: "cxo.customerAppointmentScheduler.enable", defaultValue: !1},
        appointmentSchedulerAccessFlag: {key: "cxo.cas.access.enable", defaultValue: !1},
        appointmentReschedulingFlag: {key: "cxo.customerAppointmentScheduler.reschedule.enable", defaultValue: !1},
        appointmentOptInFlag: {key: "cxo.customerAppointmentScheduler.optIn.enable", defaultValue: !1},
        appointmentHelpAndSupportFlag: {key: "cxo.cas.helpAndSupport.enable", defaultValue: !1},
        digitalStatementsActivityFlag: {key: "cxo.digitalstatements.activity.enable", defaultValue: !1},
        digitalStatementsCardTypeBccFlag: {key: "cxo.digitalStatements.cardType.bcc.enable", defaultValue: !1},
        digitalStatementsCardTypeBacFlag: {key: "cxo.digitalStatements.cardType.bac.enable", defaultValue: !1},
        digitalStatementsCardTypePacFlag: {key: "cxo.digitalStatements.cardType.pac.enable", defaultValue: !1},
        digitalStatementsCardTypeOlcFlag: {key: "cxo.digitalStatements.cardType.olc.enable", defaultValue: !1},
        singleDoorFlag: {key: "cxo.payments.singledoor.enable", defaultValue: !0},
        enableMortgagePMR: {key: "cxo.payments.enableMortgage.pmr.enable", defaultValue: !0},
        isPaymentHubEnabled: {key: "cxo.payments.paymenthub.enable", defaultValue: !1},
        currentPaymentHubRelease: {key: "cxo.payments.paymenthub.2020.release", defaultValue: 0},
        isTopRecipientsEnabled: {key: "cxo.payments.paymenthub.toprecipients", defaultValue: !1},
        isPaymentHubRTPEnabled: {key: "cxo.payments.paymenthub.rtp", defaultValue: !1},
        isRecurringPaymentsEnabled: {key: "cxo.singledoor.recurring.payments.enabled", defaultValue: !1},
        isSearchDropDownEnabled: {key: "cxo.payments.singledoor.search.suggestions.enabled", defaultValue: !1},
        singleDoorUniversalPayeeFlag: {key: "cxo.payments.singledoor.universal.payee.enable", defaultValue: !0},
        singleDoorQPEnrollAdVariantBFlag: {key: "cxo.singledoor.qp.enroll.ad.variantB.enable", defaultValue: !1},
        singleDoorSplashModeFlag: {key: "cxo.payments.singledoor.splashmode.enabled", defaultValue: !1},
        singleDoorBPAddPayeeAdVariantBFlag: {
            key: "cxo.singledoor.billpay.addpayee.ad.variantB.enable",
            defaultValue: !1
        },
        singleDoorUserSourceType: {key: "cxo.singledoor.usertype", defaultValue: "OMEGA"},
        pmrBlueBlockFlag: {key: "cxo.singledoor.PMR.blueblocks.enabled", defaultValue: !1},
        upcomingPaymentsBlueBlockFlag: {key: "cxo.digital.upcoming.payments.single.door.enable", defaultValue: !1},
        pendingApprovalOptimizedFlag: {key: "cxo.pendingApprovals.optimized.enable", defaultValue: !1},
        commercialTermLendingFlag: {key: "cxo.commercialTermLending.loans.enable", defaultValue: !1},
        escrowAnalysisStatementsFlag: {key: "cxo.ctl.escrowAnalysis.enable", defaultValue: !1},
        requestPayOffQuoteFlag: {key: "cxo.ctl.payOffQuote.enable", defaultValue: !1},
        mortgageBlackKnightServiceFlag: {key: "cxo.dashboard.blackKnightService.enable", defaultValue: !1},
        mortgageAmortizationFlag: {key: "cxo.dashboard.blackKnightAmortizationLink.enable", defaultValue: !1},
        homeEquityBlackKnightServiceFlag: {key: "cxo.dashboard.homeequity.blackKnightService.enable", defaultValue: !1},
        blackKnightRoutableEnabled: {key: "cxo.dashboard.blackKnightRoutable.enable", defaultValue: !1},
        cardReplacementMicroServicesEnabled: {key: "cxo.cardServicing.microServices.enable", defaultValue: !1},
        cardReplacementFeatureFlag: {key: "cxo.accountServices.replaceCard.enable", defaultValue: !1},
        mortgageAssistanceFlag: {key: "cxo.dashboard.mortgageAssistance.enable", defaultValue: !1},
        pendingApplicationFeatureFlag: {key: "cxo.dashboard.mortgageApplicationblade.enable", defaultValue: !1},
        redeemRewardsSamlNavigationEnable: {key: "cxo.cardServicing.redeemRewardsSaml.enable", defaultValue: !1},
        mortgageApplicationEsignVerification: {key: "cxo.accounts.MortgageApplicationEsign.enable", defaultValue: !1},
        crystalCardBenefitsFlag: {key: "cxo.menu.cardBenefits.enable", defaultValue: !1},
        crystalRedeemRewardsFlag: {key: "cxo.dashboard.redeemRewards.enable", defaultValue: !1},
        increaseCreditLimitFlag: {key: "cxo.changeCreditLimit.increase.enable", defaultValue: !1},
        newPinFlag: {key: "cxo.accountServices.getNewPIN.enable", defaultValue: !1},
        commercialCardFlag: {key: "cxo.accounts.commercialCard.enable", defaultValue: !1},
        bankSearchRequireBankNameFeatureFlag: {key: "cxo.wires.bankSearch.requireBankName.enable", defaultValue: !1},
        increaseCreditLimitBusinessCardFlag: {
            key: "cxo.changeCreditLimit.increase.businessCard.enable",
            defaultValue: !1
        },
        ulidDownloadActivityFlag: {key: "cxo.ulid.downloadActivity.enable", defaultValue: !1},
        newSMCPageEnabled: {key: "cxo.jpo.sendEmail.enable", defaultValue: !1},
        ulidSearchActivityFlag: {key: "cxo.ulid.searchActivity.enable", defaultValue: !1},
        enableExternalAccountsFlag: {key: "cxo.jpo.externalaccounts.enable", defaultValue: !1},
        unifiedPayFromFeatureFlag: {key: "cxo.paymentServicing.unifiedPayFrom.enable", defaultValue: !1},
        cardActivationDDAFlag: {key: "cxo.cardActivation.dda.enable", defaultValue: !1},
        jumboSearchFlag: {key: "cxo.dashboard.search.enable", defaultValue: !1},
        businessesWithCardOnFile: {key: "digital.profile.cardsOnFile.enable", defaultValue: !1},
        smcComposeMessageDesktopEnabled: {key: "cxo.secureMessages.composeMessageDesktop.enable", defaultValue: !1},
        smcComposeMessageMobileEnabled: {key: "cxo.secureMessages.composeMessageMobile.enable", defaultValue: !1},
        smcComplaintsDesktopEnableFlag: {key: "cxo.secureMessages.complaintsDesktop.enable", defaultValue: !1},
        smcComplaintsMobileEnableFlag: {key: "cxo.secureMessages.complaintsMobile.enable", defaultValue: !1},
        tycdLabelChange: {key: "cxo.ovd.tycdLabelChange.enable", defaultValue: !1},
        requestVoluntarySurrenderFlag: {key: "cxo.documents.edocs.voluntary.surrender.enable", defaultValue: !1},
        businessesWithCardOnFileDebit: {key: "digital.profile.cardsOnFile.debit.enable", defaultValue: !1},
        autoSaveGaiaServicesFlag: {key: "cxo.payments.autoSaveGaiaServices.enable", defaultValue: !1},
        businessesWithCardOnFileCredit: {key: "digital.profile.cardsOnFile.credit.enable", defaultValue: !1},
        accountActivityTransactionsTypeFlag: {key: "cxo.accountActivity.transactionsType.enable", defaultValue: !1},
        repricingCommercialLoansFlag: {key: "cxo.repricing.commercialLoans.enable", defaultValue: !0},
        odsAccountActivityDDAFlag: {key: "cxo.odsAccountActivity.dda.enable", defaultValue: !1},
        odsRecentActivityDDAFlag: {key: "cxo.odsRecentActivity.dda.enable", defaultValue: !1},
        odsRecentActivityCARDFlag: {key: "cxo.odsRecentActivity.card.enable", defaultValue: !1},
        odsAccountActivityUlidFlag: {key: "cxo.odsAccountActivity.ulid.enable", defaultValue: !1},
        discoverMerchantRecipientsFlag: {key: "cxo.payments.discoverMerchantRecipients.enable", defaultValue: !1},
        merchantPayeesAdminFlag: {key: "cxo.payments.payeesAdmin.enable", defaultValue: !1},
        discoverMerchantRecipientsExisting: {key: "cxo.payments.discoverMerchantRecipients.existing", defaultValue: !1},
        preClientBundleBillingSummaryEnabled: {key: "cxo.commercial.billingWidget.enable", defaultValue: !1},
        commercialCoBrowseEnabled: {key: "cxo.commercial.coBrowse.enable", defaultValue: !1},
        interestSavingBalanceFlag: {key: "cxo.payments.interestSavingBalance.enable", defaultValue: !1},
        pendingApprovalAchReversalBatchesFlag: {
            key: "cxo.pendingApprovals.achReversalBatches.enable",
            defaultValue: !1
        },
        pendingApprovalAchPaymentBatchesFlag: {key: "cxo.pendingApprovals.achPaymentBatches.enable", defaultValue: !1},
        cboEmobWidgetFlag: {key: "cxo.cboEmobTile.enable", defaultValue: !1},
        securityBasedLendingFlag: {key: "cxo.payments.securitybasedlending.enable", defaultValue: !1},
        transferActivityUpdated: {key: "cxo.investments.transferActivityUpdated.enable", defaultValue: !1},
        autoSaveFeatureFlag: {key: "cxo.payments.autoSave.enable", defaultValue: !1},
        scheduleFacilityAdvanceFeatureFlag: {key: "cbo.commercialLoans.drawdown.enable", defaultValue: !1},
        optionsChainStickyHeaderFlag: {key: "cxo.trade.optionsChainStickyHeader.enable", defaultValue: !0},
        homePayNewEditFlag: {key: "cxo.payments.mortgageAutomaticDetails.enable", defaultValue: !1},
        creditJourneyWidgetFlag: {key: "cxo.creditJourney.enable", defaultValue: !1},
        jfyOfferIndicatorFlag: {key: "cxo.jfy.offers.midas.enable", defaultValue: !1},
        checkingBalanceTransferFlagEnabled: {key: "cxo.payments.balanceTransfers.enable", defaultValue: !1},
        newRewardsTileLayoutsEnabled: {key: "cxo.newRewardsTileLayouts.enable", defaultValue: !1},
        myAutoAstonMartinFlag: {key: "cxo.menu.myAutoAstonMartin.enable", defaultValue: !1},
        borrowingServicesFlag: {key: "cxo.tycd.borrowingServices.enable", defaultValue: !1},
        ovdBorrowingServicesFlag: {key: "cxo.moreMenu.borrowingServices.enable", defaultValue: !1},
        recentPayeesLimitFlag: {key: "cxo.payBills.recentPayeeLimit", defaultValue: !1},
        myChasePlanFlag: {key: "cxo.accountActivity.myChasePay.enable", defaultValue: !1},
        myChaseLoanFlag: {key: "cxo.accountActivity.myChaseLoan.enable", defaultValue: !1},
        jpoSingleDepositLayoutEnabled: {key: "cxo.jpo.singleDeposit.layout.enable", defaultValue: !1},
        jpoSingleInvestmentLayoutEnabled: {key: "cxo.jpoLayout.singleInvestment.enable", defaultValue: !1},
        merchantServicesActivityEnabled: {key: "cxo.accountActivity.merchantServices.enable", defaultValue: !1},
        msaDisputesBBLXEnabled: {key: "cxo.merchantServices.disputes.bblx.enabled", defaultValue: !1},
        msaDepositAccountBBLXEnabled: {key: "cxo.merchantServices.depositAccount.bblx.enabled", defaultValue: !1},
        msaCardSummariesServiceUpdated: {key: "cxo.merchantServices.cardSummariesSvc.updated", defaultValue: !1},
        showMoneyGuideProSpeedBump: {key: "cxo.investments.moneyguidepro.showSpeedBump", defaultValue: !1},
        cpfaNewEligibilityCheck: {key: "cxo.investments.moneyguidepro.eligibilityCheck", defaultValue: !1},
        cardlyticsOffersEnabled: {key: "cxo.cardlytics.offers.enable", defaultValue: !1},
        singleDoorAutoUserFlag: {key: "cxo.payments.singledoor.autoloan.enable", defaultValue: !1},
        singleDoorMortgageFlag: {key: "cxo.payments.singledoor.mortgage.enable", defaultValue: !1},
        singleDoorShowNudgeError: {key: "cxo.singledoor.universal.no.default.enabled", defaultValue: !1},
        unfundedYouInvestFlag: {key: "cxo.youInvest.unfunded.enable", defaultValue: !1},
        updateSpendingCategoryFlag: {key: "cxo.updateSpendingCategory.enable", defaultValue: !1},
        updateTransactionDetailsServicesFlag: {key: "cxo.updateMemoServices.enable", defaultValue: !1},
        updateActivityVersionFlag: {key: "cxo.updateActivityVersion.enable", defaultValue: !1},
        sharedFundingAccountsEnable: {key: "cxo.payments.sharedFundingAccounts.enable", defaultValue: !1},
        ableReportsNbaEnable: {key: "cxo.commercial.ableReportsNba.enable", defaultValue: !1},
        ablePaymentsEnable: {key: "cxo.commercial.ablePayments.enable", defaultValue: !1},
        ableAdvancesEnable: {key: "cxo.commercial.ableAdvances.enable", defaultValue: !1},
        ableRepricingEnable: {key: "cxo.commercial.ableReprice.enable", defaultValue: !1},
        ableReportsEnable: {key: "cxo.commercial.ableReports.enable", defaultValue: !1},
        ovdForPartnerAndWhiteLabelFlag: {key: "cxo.ovdForPartnerAndWhiteLabel.enable", defaultValue: !1},
        fastPayFlyoutFlag: {key: "cxo.payments.fastPayFlyout.enable", defaultValue: !1},
        skipSimNavigationFlag: {key: "cxo.investments.s2i.skipSimNavigation.enabled", defaultValue: !1},
        enableOVDForOLC: {key: "cxo.ovd.olc.enable", defaultValue: !1},
        enableOVDForCHN: {key: "cxo.ovd.chn.enable", defaultValue: !1},
        lineChartFeatureFlag: {key: "cxo.spendingReportLineChart.enable", defaultValue: !1},
        hybridBlankLogWaitTimeout: {key: "cxo.hybrid.blankLogWaitTimeout", defaultValue: 5},
        hybridSpinnerWaitTimeout: {key: "cxo.hybrid.spinnerWaitTimeout", defaultValue: 12},
        hybridBlankPageLogEnabled: {key: "cxo.hybrid.blankPageLog.enable", defaultValue: !0},
        enableQuoteFlyoutOnly: {key: "cxo.markets.quote.markitIFrame.enable", defaultValue: !1},
        liveChatEnable: {key: "cxo.livechat.enable", defaultValue: !1},
        dynamicCreditJourneyWidgetFlag: {key: "cxo.dynamicCreditJourney.enable", defaultValue: !1},
        cxoBccPifFlag: {key: "cxo.bcc.pif.enable", defaultValue: !1},
        slideInMerchantFundedOffersFlag: {key: "cxo.cardlytics.offers.slideIn.enable", defaultValue: !1},
        usefulLinksWidgetFlag: {key: "cxo.dashboard.OVD.usefullinks.enable", defaultValue: !1},
        merchantServicesWePayEnabled: {key: "cxo.merchantServices.wePay.enabled", defaultValue: !1},
        autoSaveEnabledForInvestmentAccounts: {key: "cxo.dashboard.autoSave.investments.enable", defaultValue: !1},
        quickCapitalClassicDecommissionFlag: {key: "cxo.quickCapital.classicDecommission.enable", defaultValue: !1},
        isGlidePathEnabled: {key: "cxo.investments.eda.glidePath.enabled", defaultValue: !1},
        showAvailableCreditBalance: {key: "cxo.accountServices.showAvailableCredit.enable", defaultValue: !1},
        isTermLoanExternalFundingEnabled: {key: "cxo.termLoan.externalFunding.enable", defaultValue: !1},
        cboSlideinDDAFlag: {key: "cxo.cbo.dda.slideIn.enable", defaultValue: !1},
        cboSlideinCardFlag: {key: "cxo.cbo.card.slideIn.enable", defaultValue: !1},
        jpoSlideinCardFlag: {key: "cxo.card.slideIn.enable", defaultValue: !1},
        cboSlideinBusinessLoanFlag: {key: "cxo.cbo.businessLoan.slideIn.enable", defaultValue: !1},
        cboSlideinCommercialLoanFlag: {key: "cxo.cbo.commercialLoan.slideIn.enable", defaultValue: !1},
        cboSlideinMerchantFlag: {key: "cxo.cbo.merchant.slideIn.enable", defaultValue: !1},
        cpoDDATaxonomyMoreMenuFlag: {key: "cxo.cpo.dda.taxonomyMoreMenu.enable", defaultValue: !1},
        cboCRFSlideInFlag: {key: "cxo.cbo.crf.slideIn.enable", defaultValue: !1},
        isMsaAdjustmentEnabled: {key: "cxo.merchantServices.adjustment.enable", defaultValue: !1},
        qpInterstitialFlag: {key: "cxo.payments.quickpay.tcpainterstitial.enable", defaultValue: !1},
        globalTransferInterstitialFlag: {key: "cxo.payments.globaltransfer.tcpainterstitial.show", defaultValue: !1},
        wiresInterstitialFlag: {key: "cxo.payments.wires.tcpainterstitial.enable", defaultValue: !1},
        showDigitalWalletsContainer: {key: "cxo.digitalWallets.enable", defaultValue: !1},
        suitabilityProfileEnabled: {key: "cxo.investments.suitabilityProfile.enable", defaultValue: !1},
        enableDIPDashboardForBCC: {key: "cxo.DIPDashboard.card.BCC.enable", defaultValue: !1},
        enableDIPDashboardForMSA: {key: "cxo.DIPDashboard.merchant.MSA.enable", defaultValue: !1},
        dashboardSuppressFeedbackIcon: {key: "cxo.dashboard.suppressFeedbackIcon.enable", defaultValue: !1},
        investmentsPositionsTabViewEnabled: {key: "cxo.investments.positions.tabview", defaultValue: !1},
        fastPayFlag: {key: "cxo.ala.payoffQuote.fastPay.enable", defaultValue: !1},
        activityGenericNudgeFlag: {key: "cxo.smartData.activity.genericNudge.enable", defaultValue: !1},
        grammerAccountEnable: {key: "cxo.grammerAccount.enable", defaultValue: !1},
        quickAcceptEnable: {key: "cbo.businessComplete.quickAccept.enable", defaultValue: !1},
        disputeTrackerTYCDEntryPoint: {key: "cxo.accounts.disputeTrackerTYCDEntryPoint.enable", defaultValue: !1},
        claimsTrackerEnabled: {key: "cxo.accounts.claimsTracker.enable", defaultValue: !1},
        autoLeaseExtensionMaturityDate: {key: "cxo.autoLease.maturityDateForExtension", defaultValue: "DISABLED"},
        activityDashboardDecommissionEnabled: {key: "cxo.activityDashboard.decommission.enable", defaultValue: !1},
        enableAutoSaveForCBO: {key: "cbo.ovd.autoSave.enable", defaultValue: !1},
        enableAppKitJSBridge: {key: "cxo.appkit.jsbridge.enable", defaultValue: !1},
        internalSecurityTransfersEnabled: {key: "cxo.internal.transfer.securities.enabled", defaultValue: !1},
        BusinessPaymentCenterEnabled: {key: "cxo.payments.bpc.enabled", defaultValue: !0},
        isPaymentTrackerEnabled: {key: "cxo.payments.paymenthub.paymenttracker", defaultValue: !1},
        isPaymentTrackerActivityEnabled: {key: "cxo.payments.paymenthub.paymenttrackeractivity", defaultValue: !1},
        isERPPaymentsEnabled: {key: "cxo.payments.erppayments.enabled", defaultValue: !1},
        declinedTransactionsFlag: {key: "cxo.cardServicing.declinedTransactions.enable", defaultValue: !1},
        updatedOdsActivityCardFlag: {key: "cxo.odsActivity.card.enable", defaultValue: !1},
        isPaymentServicingAreaBlockEnabled: {key: "cxo.payments.paymentServicing.areaBlock.enable", defaultValue: !1},
        quickPayMemoStandardizationFlag: {key: "cxo.quickPay.memoStandardization.enabled", defaultValue: !0},
        isYodleeFastLinkEnabled: {key: "cxo.payments.fastlink.enable", defaultValue: !1},
        chaseOffersAlertLinkEnabled: {key: "cxo.cardlytics.offers.updateAlertLink.enable", defaultValue: !1},
        paymentsMerchantMicroBatch: {key: "cxo.payments.merchantMicroBatch", defaultValue: !1},
        incomeCaptureBlueBlock: {key: "cxo.incomeCapture.blueBlock.enable", defaultValue: !1},
        alertsEmulationModeEnableFlag: {key: "cxo.alerts.emulationMode.enable", defaultValue: !1},
        balanceTransferAreaBlockEnabled: {key: "cxo.payments.balanceTransfers.areablock.enabled", defaultValue: !1},
        zelleRebrandEnabled: {key: "cxo.quickPay.zelleRebrand.enable", defaultValue: !1},
        requestPaymentEnableFlag: {key: "cxo.payments.requestForPayment.enable", defaultValue: !1},
        accountSafeDIPViewEnabled: {key: "cxo.accountSafe.DIPNavigation.enable", defaultValue: !1},
        zelleRevampEnabled: {key: "cxo.quickPay.zelleRevamp.enable", defaultValue: !1},
        quickpayV2SendOtpEnabled: {key: "cxo.quickpay.v2SendOtp.enable", defaultValue: !1},
        merchantCloseLocationFlag: {key: "cxo.dashboard.msa.closeLocation.enable", defaultValue: !1},
        merchantTerminalSupervisorPasswordFlag: {
            key: "cxo.dashboard.msa.terminalSupervisorPassword.enable",
            defaultValue: !1
        }
    };
    return function (i) {
        return t && !i.recreateFeatureProviders || (t = new (e("common/lib/featureFlags/featureMixin"))(i)).addFeatures(n), t
    }
})), define("common/lib/featureFlags/featureExperimentImpressionChannel", ["require", "analytics/channel/experimentImpressionChannel"], (function (e) {
    "use strict";
    var t = e("analytics/channel/experimentImpressionChannel");
    return {
        createExperimentImpressionChannelEvent: function (e, n, i) {
            var o, r;
            t.getInstance().emit((o = {
                name: e,
                context: {experimentId: n, choice: i}
            }, (r = Object.create(null)).data = o.context, r.name = o.name, r))
        }
    }
})), define("common/lib/featureFlags/featureFlags", ["require", "blue/util", "blue/log", "common/lib/featureFlags/featureExperimentImpressionChannel"], (function (e) {
    "use strict";
    var t = e("blue/util").lang.defaults, n = e("blue/log")("[common/lib/featureFlags/featureFlags]"),
            i = e("common/lib/featureFlags/featureExperimentImpressionChannel"), o = "PENDING";

    function r() {
        var e = this;
        e.RESOLVED = "RESOLVED", e.REJECTED = "REJECTED", e.featureFlagLoadPromise = new Promise((function (t) {
            e.featureFlagLoadPromiseResolve = t
        })), e.featureFlags = {}
    }

    function a(e, n) {
        var o = t(this.featureFlags[e], n);
        return this.featureFlags["darkCanary.experimentImpressionsEnabled"] && i.createExperimentImpressionChannelEvent("", e, o), o
    }

    return r.prototype.setFeatureFlags = function (e, t) {
        this.featureFlags = e || {}, this.featureFlagLoadPromiseResolve(), o = t
    }, r.prototype.getFeatureFlagValue = function (e, t) {
        var n = this;
        return e ? null == t || "" === t ? Promise.reject(new Error("Please provide default value for the flag")) : n.featureFlagLoadPromise.then((function () {
            return a.call(n, e, t)
        })) : Promise.reject(new Error("Please provide the flag key"))
    }, r.prototype.getFeatureFlagValueSync = function (e, t) {
        if (!e) throw new Error("Please provide the flag key");
        if (null == t || "" === t) throw new Error("Please provide default value for the flag");
        return "PENDING" === o && n.error("featureFlagLoadPromise is not resolved for the flag : " + e + ".  Hence, returning the default value: " + t), a.call(this, e, t)
    }, r.prototype.onFeatureFlagsLoaded = function () {
        return this.featureFlagLoadPromise
    }, r
})),define("common/lib/featureFlags/featureProviderFactory", ["require", "common/lib/featureFlags/commonFeatureProvider"], (function (e) {
    return {
        createAreaFeatureProvider: function (t, n, i) {
            return t && !i.recreateFeatureProviders || (t = Object.create(e("common/lib/featureFlags/commonFeatureProvider")(i)), n && t.addFeatures(n)), t
        }
    }
})),define("common/lib/featureFlags/service/appFeatureFlagsMixin", ["require", "blue/siteData", "common/lib/featureFlags/featureFlags"], (function (e) {
    "use strict";
    var t = e("blue/siteData");
    return function (n) {
        var i = n.context, o = e("common/lib/featureFlags/featureFlags");
        i.site.featureFlags = new o, n.getFeatureFlags = function (e) {
            if (function (e) {
                var n = i.site && i.site.user && i.site.user.profile && i.site.user.profile.getProfile();
                e || n || i.logger.error("site.user.profile.getProfile not found for area: " + i.state().area.name), e = e || {}, n && (e.profileId = e.profileId || n.profileId, e.segment = e.segment || n.segmentType), e.channelId = e.channelId || ("undefined" != typeof channel ? channel : ""), e.podId = e.podId || t.getData("podID")
            }(e = Object.assign({}, e)), e.profileId) {
                var n = i.services.featureFlags.settings("getFeatureFlags");
                return n.url = n.url.replace(/:profileId/, e.profileId), delete e.profileId, i.services.featureFlags.settings("getFeatureFlags", n), i.services.featureFlags.getFeatureFlags(e).then((function (e) {
                    e && e.featureFlags ? i.site.featureFlags.setFeatureFlags(e.featureFlags, i.site.featureFlags.RESOLVED) : i.site.featureFlags.setFeatureFlags(null, i.site.featureFlags.RESOLVED)
                })).catch((function (e) {
                    i.site.featureFlags.setFeatureFlags(null, i.site.featureFlags.REJECTED), i.logger.error("Feature flags service failed", e)
                }))
            }
            var o = "ProfileId not found for area: " + i.state().area.name;
            return i.logger.error(o), i.site.featureFlags.setFeatureFlags(null, i.site.featureFlags.REJECTED), Promise.resolve()
        }
    }
})),define("common/lib/featureFlags/service/featureFlagsService", [], (function () {
    "use strict";
    return function (e) {
        this.serviceCalls = {
            getFeatureFlags: {
                settings: {
                    url: e.config.featureFlagsDomainUrl + "/events/svc/config/user/:profileId/featureFlags",
                    type: "GET",
                    disableDefaultError: !0
                }
            }
        }
    }
})),define("common/lib/flyout/flyoutUtility", [], (function () {
    "use strict";
    var e = ["initiateAdvisorConnect", "confirmAdvisorConnect", "announcement", "announcementDetails", "assetsOverview", "spendingReport", "marketCommentary", "transactionDetails", "transactionImageDetails", "ctlPaymentReceivedBreakdown", "personalizeBorrowing", "cardActivation", "pendingApprovalsEdit", "cardOnFileActivity", "ctlAutoPay", "merchantFundedOfferDetails", "merchantFundedOffersFrequentlyAskedQuestions", "cardOnFileTransactionDetails", "savedAccountManagerFrequentlyAskedQuestions", "faqFlyout", "research"],
            t = ["announcementDetails"];

    function n(e) {
        return !!(e && e.action && e.action.params && e.action.params.flyout)
    }

    function i(t) {
        var i;
        return i = Array.isArray(t.action.params.flyout) ? t.action.params.flyout[0] : t.action.params.flyout, n(t) && e.indexOf(i) > -1
    }

    function o(e) {
        return n(e) && t.indexOf(e.action.params.flyout) > -1
    }

    return {
        SIZES: {standard: "flyoutSize-standard", small: "flyoutSize-small", large: "flyoutSize-large"},
        ELEMENTS: {
            content: "#flyoutContent",
            header: "#flyoutHeaderContent",
            spinnerContainer: "#flyoutSpinnerContainer",
            wrapper: "#flyoutWrapper",
            footer: "#flyoutFooterContent",
            closeButton: "#flyoutClose"
        },
        CLASS_NAMES: {enabled: "flyout-enabled"},
        isFlyoutNavigation: function (e) {
            var t = e.routeToObject(e.routeHistory.lastRoute(1));
            return i(e.state()) || i(t)
        },
        isFlyout: n,
        isExceptionFlyout: i,
        isFlyoutRoute: function (e) {
            var t = e.routeToObject(e.routeHistory.lastRoute(1));
            return n(e.state()) || e.is.defined(t.action.params.flyout)
        },
        getFlyoutContextName: function (e) {
            var t = "";
            return e && e.action && e.action.params && e.action.params.flyout && (t = "string" == typeof e.action.params.flyout ? e.action.params.flyout : e.action.params.flyout[0]), t
        },
        isDetailsFlyoutNavigation: function (e) {
            var t = e.routeToObject(e.routeHistory.lastRoute(1));
            return o(e.state()) || o(t)
        },
        getFlyoutState: function (e, t, i) {
            var o = e.state();
            return n(o) ? (o.action && o.action && o.action.params && (o.action.params.flyout = t), o) : i
        }
    }
})),define("common/lib/hashUtil", ["require", "blue/hash", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/hash"), n = e("blue/util");
    return {
        isValidDeekLink: function (e, i, o, r) {
            var a = !1, s = t.getURL(), c = t.getHashArr(s), l = n.object.filter(c, (function (e) {
                return "" !== e
            })), u = this.getAppIndex(c);
            if (-1 === u) return a;
            var d = u + 1, m = d + 1, f = m + 1, p = f + 1, g = function (e, t) {
                a = !!l[t] && l[t] === e
            };
            return e && g(e, d), i && g(i, m), o && g(o, f), r && g(r, p), a
        }, findHashIndex: function (e, t) {
            for (var n = 0; n < e.length; n++) if (e[n] === t) return n;
            return -1
        }, getAppIndex: function (e) {
            for (var t = new RegExp("#?dashboard$"), n = 0; n < e.length; n++) if (t.test(e[n])) return n;
            return -1
        }, removeHashOfIndex: function (e) {
            var n = t.getHashArr();
            return n.splice(e, 1), "#/" + n.toString().replace(/,/g, "/")
        }, updateHashOnIndex: function (e, n) {
            var i = t.getHashArr();
            return e >= 0 && e < i.length && (i[e] = n), "#/" + i.toString().replace(/,/g, "/")
        }, getXHashAfterApp: function (e) {
            var i = t.getHashArr(t.getURL());
            return n.object.filter(i, (function (e) {
                return "" !== e
            }))[this.getAppIndex(i) + e]
        }, getHashValuesAfterXHash: function (e) {
            var n = t.getHashArr(t.getURL()), i = this.getAppIndex(n);
            return n.slice(i + e)
        }, isDeepLink: function (e) {
            if (this.params && e && Array.isArray(e)) {
                for (var t = 0; t < e.length; t++) if (e[t] !== this.params[t]) return !1;
                return !0
            }
            return !1
        }, hasher: t
    }
})),define("common/lib/infoDensity/infoDensityUtil", ["blue/siteMode"], (function (e) {
    "use strict";
    var t, n = {condensed: "CONDENSED", relaxed: "RELAXED"};
    return function () {
        t = this, this.getDensityDisplayPreference = function (e) {
            return n[e ? "condensed" : "relaxed"]
        }, this.isTableRowsCompacted = function () {
            return !!this.context.userInfo && this.context.userInfo.densityDisplayPreference === n.condensed
        }, this.setTableRowsCompacted = function (n) {
            var i = this.getDensityDisplayPreference(n), o = {densityDisplayPreference: i};
            this.context.userInfo = this.context.userInfo || {}, this.context.userInfo.densityDisplayPreference = i, this.setTableRowsCompactedModel(), !e.isModeEnabled("readOnly") && this.context.application.services.densityDisplayPreferenceService["account.display.preference.update"](o).catch((function () {
                t.context.logger.warn("Info density update service failed")
            }))
        }
    }
})),define("common/lib/infoDensity/componentMixin", ["require", "common/lib/infoDensity/infoDensityUtil"], (function (e) {
    "use strict";
    return function () {
        e("common/lib/infoDensity/infoDensityUtil").call(this);
        var t = !1, n = !1;
        this.relaxTableRows = function () {
            t || (this.setTableRowsCompacted(!1), this.setTableRowsCompactedModel(), t = !0, n = !1)
        }, this.compactTableRows = function () {
            n || (this.setTableRowsCompacted(!0), this.setTableRowsCompactedModel(), n = !0, t = !1)
        }, this.setToggleVisibility = function (e) {
            this.output.emit("relaxOrCompactRowsVisibleUpdated", {isVisible: e})
        }, this.setTableRowsCompactedModel = function () {
            this.tableRowsCompacted = this.isTableRowsCompacted()
        }
    }
})),define("common/lib/infoDensity/viewMixin", ["require", "common/utility/dynamicContentUtil", "common/lib/infoDensity/infoDensityUtil"], (function (e) {
    "use strict";
    return function () {
        var t = this, n = e("common/utility/dynamicContentUtil");
        e("common/lib/infoDensity/infoDensityUtil").call(this), this.context.util.object.extend(this.model, {
            relaxOrCompactRowsVisible: !0,
            tableRowsCompacted: t.isTableRowsCompacted(),
            relaxTableRowsLabel: n.getDynamicContentsQuick("app", "GLOBAL", "relaxTableRowsLabel"),
            relaxTableRowsAda: n.getDynamicContentsQuick("app", "GLOBAL", "relaxTableRowsAda"),
            compactTableRowsLabel: n.getDynamicContentsQuick("app", "GLOBAL", "compactTableRowsLabel"),
            compactTableRowsAda: n.getDynamicContentsQuick("app", "GLOBAL", "compactTableRowsAda"),
            informationDensityStatusAda: n.getDynamicContentsQuick("app", "GLOBAL", "informationDensityStatusAda")
        }), this.bridge.on("relaxOrCompactRowsVisibleUpdated", (function (e) {
            t.model.relaxOrCompactRowsVisible = e.isVisible, t.model.tableRowsCompacted = e.isVisible && t.isTableRowsCompacted()
        })), this.bridge.on("setGlobalContent", (function (e) {
            t.model.relaxTableRowsLabel = e.relaxTableRowsLabel, t.model.relaxTableRowsAda = e.relaxTableRowsAda, t.model.compactTableRowsLabel = e.compactTableRowsLabel, t.model.compactTableRowsAda = e.compactTableRowsAda, t.model.informationDensityStatusAda = e.informationDensityStatusAda
        })), this.bridge.on("ready", (function () {
            t.model.tableRowsCompacted = t.isTableRowsCompacted(), t.onData("tableRowsCompacted", (function (e) {
                t.context.page.broadcast("infoDensity:relaxOrCompactTableRows", {tableRowsCompacted: !!e})
            }))
        }))
    }
})),define("common/lib/inputMaskFactory", ["require", "blue/is"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = /\d/, i = function (e) {
        if (e.keyCode >= 96 && e.keyCode <= 105) {
            var n = t.number(e.which) ? e.which : e.keyCode;
            return n -= 48
        }
        return t.number(e.which) ? e.which : e.keyCode
    }, o = function (e, t, n) {
        var i = e.slice(0), o = null;
        return e.forEach((function (r, a) {
            if (t[a] === n) r !== n && (o = r), i[a] = n; else if (o) i[a] = o, o = r === n ? null : r; else if (r !== n) i[a] = r; else {
                var s = i[a];
                e[a] = i[a] = i[a + 1] || s, e[a + 1] = i[a + 1] = i[a + 1] ? s : ""
            }
        })), o && (i[i.length] = o, o = null), i
    }, r = function (e, t, n) {
        var i = [];
        return e.some((function (e) {
            return i.push(e), (i = o(i, t, n)).length === t.length
        })), i
    }, a = function (e, t, n, i, o) {
        var r = e.slice(0);
        return e.forEach((function (e, a) {
            "*" === t[a] && "" !== r[a] && r[a] !== i && a !== o && (r[a] = n)
        })), r
    }, s = function (e, t) {
        if (null == t) throw new Error("A parameter '" + e + "' is not defined.");
        return t
    }, c = function (e) {
        var r, s = e.maskedInput, c = s[0], l = e.event.domEvent, u = i(l), d = n.test(l.key), m = u >= 65 && u <= 90,
                f = 8 === u, p = 9 === u, g = u >= 37 && u <= 40, y = e.mask, h = e.unmaskedProperty,
                v = e.boundProperty, b = e.viewModel, A = e.componentModel;
        if (!g && !p) {
            if (function (e, n, i, o, r) {
                return (e || n && i || o) && (a = r, a && t.number(a.selectionEnd) && t.number(a.selectionStart) && a.selectionEnd !== a.selectionStart);
                var a
            }(d, m, e.allowAlpha, f, c) && (b[h] = "", s.val("")), r = b[h] ? b[h].split("") : s.val().split(""), f) !function (e) {
                var t = e.maskedInput, n = t[0], i = n.selectionStart, r = e.mask, s = e.maskingChar, c = e.separator,
                        l = e.unmaskedProperty, u = e.viewModel, d = u[l] ? u[l].split("") : t.val().split("");
                0 !== i && (i -= 1, d = d.slice(0, i).concat(d.slice(i + 1)), d = o(d, r, c), u[l] = "".concat.apply("", d), r[i] === c ? (e.maskTimeoutId && (e.maskTimeoutId = clearTimeout(e.maskTimeoutId), d = a(d, r, s, c)), n.setSelectionRange(i, i)) : (e.maskTimeoutId && (e.maskTimeoutId = clearTimeout(e.maskTimeoutId), d = a(d, s, c)), d = a(d, r, s, c), t.val("".concat.apply("", d)), n.setSelectionRange(i, i)), 0 === t.val().replace(/[-|/| ]*/, "").length && (t.val(""), u[l] = ""))
            }(e); else {
                if (r.length >= y.length) return A.set(v, b[h]), void e.event.domEvent.preventDefault();
                !function (e) {
                    var t = e.maskedInput, r = e.event.domEvent, s = t[0], c = s.selectionStart, l = r.metaKey,
                            u = r.ctrlKey, d = i(r), m = String.fromCharCode(d), f = d >= 65 && d <= 90,
                            p = n.test(r.key), g = u && 65 === d || l && 65 === d, y = e.mask, h = e.maskingChar,
                            v = e.separator, b = e.unmaskedProperty, A = e.viewModel,
                            E = A[b] ? A[b].split("") : t.val().split("");
                    (p || f && e.allowAlpha && e.allowAlpha && !g) && (E = E.slice(0, c).concat([m]).concat(E.slice(c)), E = o(E, y, v), y[c] === v && c++, A[b] = "".concat.apply("", E), e.maskTimeoutId && (e.maskTimeoutId = clearTimeout(e.maskTimeoutId), E = a(E, y, h, v, c)), e.maskTimeoutId = setTimeout((function (t, n, i) {
                        t = a(t, y, h, v), n.val("".concat.apply("", t)), e.maskTimeoutId = clearTimeout(e.maskTimeoutId), s.setSelectionRange(i + 1, i + 1)
                    }), e.maskDelay, E, t, c), E = a(E, y, h, v, c), t.val("".concat.apply("", E)), c += 1, s.setSelectionRange(c, c))
                }(e)
            }
            e.event.domEvent.preventDefault(), A.set(v, b[h]), b[v] = b[h]
        }
    };
    return {
        create: function (e) {
            return s("options", e), s("options.maskedInput", e.maskedInput), s("options.mask", e.mask), s("options.maskingChar", e.maskingChar), s("options.separator", e.separator), s("options.viewModel", e.viewModel), s("options.componentModel", e.componentModel), s("options.unmaskedProperty", e.unmaskedProperty), s("options.boundProperty", e.boundProperty), e.allowAlpha, e.maskTimeoutId = 0, e.maskDelay = t.number(e.maskDelay) ? e.maskDelay : 1e3, {
                keyUp: function (t, n) {
                    e.event = s("event", t), n && (e.maskedInput = n), c(e)
                }, onBlur: function () {
                    var t;
                    e.maskTimeoutId && (e.maskTimeoutId = clearTimeout(e.maskTimeoutId), t = a(e.viewModel[e.unmaskedProperty].split(""), e.mask, e.maskingChar, e.separator, e.viewModel[e.unmaskedProperty].length), e.maskedInput.val("".concat.apply("", t)))
                }, detectChange: function (t, n) {
                    t && "blur" === t.domEvent.type && (this.lastValue !== e.viewModel[e.unmaskedProperty] && ("dispatchEvent" in document ? n[0].dispatchEvent(new Event("change", {
                        bubbles: !1,
                        cancelable: !0
                    })) : n[0].fireEvent("onchange")), this.lastValue = e.viewModel[e.unmaskedProperty])
                }, mask: function (t, n) {
                    var i, o, s;
                    n && (e.maskedInput = n), t && (s = new RegExp(e.separator, "g"), o = (t.domEvent.target.value || "").replace(s, "").substring(0, e.mask.replace(s, "").length).split(""), e.viewModel[e.unmaskedProperty] = "".concat.apply("", r(o, e.mask, e.separator)), e.componentModel.set(e.boundProperty, e.viewModel[e.unmaskedProperty])), i = a(e.viewModel[e.unmaskedProperty].split(""), e.mask, e.maskingChar, e.separator, e.viewModel[e.unmaskedProperty].length), e.maskedInput.val("".concat.apply("", i))
                }, unmask: function (t, n) {
                    var i = e.viewModel[e.unmaskedProperty].split("");
                    n && (e.maskedInput = n), e.maskedInput.val("".concat.apply("", r(i, e.mask, e.separator)))
                }, maskValue: function () {
                    return a(e.viewModel[e.unmaskedProperty].split(""), e.mask, e.maskingChar, e.separator, e.viewModel[e.unmaskedProperty].length)
                }, getCharCode: function (e) {
                    return i(e)
                }
            }
        }
    }
})),define("common/lib/inview", ["blue/$"], (function (e) {
    "use strict";
    var t, n, i, o = {}, r = document, a = window, s = r.documentElement, c = e.expando;

    function l() {
        var i, c, l, d, m = [], f = 0;
        if (e.each(o, (function (e, t) {
            var n = t.data.selector, i = t.$element;
            m.push(n ? i.find(n) : i)
        })), i = m.length) for (t = t || ((d = {
            height: a.innerHeight,
            width: a.innerWidth
        }).height || !(c = r.compatMode) && e.support.boxModel || (d = {
            height: (l = "CSS1Compat" === c ? s : r.body).clientHeight,
            width: l.clientWidth
        }), d), n = n || {
            top: a.pageYOffset || s.scrollTop || r.body.scrollTop,
            left: a.pageXOffset || s.scrollLeft || r.body.scrollLeft
        }; f < i; f++) if (e.contains(s, m[f][0])) {
            if (!n || !t) return;
            u(m[f])
        }
    }

    function u(e) {
        var i, o, r, a, s = e.height(), c = e.width(), l = e.offset(), u = e.data("inview");
        l.top + s > n.top && l.top < n.top + t.height && l.left + c > n.left && l.left < n.left + t.width ? (r = (i = n.left > l.left ? "right" : n.left + t.width < l.left + c ? "left" : "both") + "-" + (o = n.top > l.top ? "bottom" : n.top + t.height < l.top + s ? "top" : "both"), a = l.top + s - n.top, u && u === r || e.data("inview", r).trigger("inview", [!0, i, o, a])) : u && e.data("inview", !1).trigger("inview", [!1])
    }

    e.event.special.inview = {
        add: function (t) {
            o[t.guid + "-" + this[c]] = {
                data: t,
                $element: e(this)
            }, i || e.isEmptyObject(o) || (i = setInterval(l, 250))
        }, remove: function (t) {
            try {
                delete o[t.guid + "-" + this[c]]
            } catch (e) {
            }
            e.isEmptyObject(o) && (clearInterval(i), i = null)
        }
    }, e(a).bind("scroll resize scrollstop", (function () {
        t = n = null
    })), !s.addEventListener && s.attachEvent && s.attachEvent("onfocusin", (function () {
        n = null
    }))
})),define("common/lib/isEmulationModeMixin", [], (function () {
    return function () {
        this.isNotEmulationMode = function (e) {
            return !e.user.profile.isGWMEmulationMode() || (e.state(e.settings.get("emulationErrorPage")), !1)
        }
    }
})),define("common/lib/learningArticleUtility", [], (function () {
    "use strict";
    var e = {
        EXCLUSIVELY_DIGITAL_ADVISORY: "EDA",
        SELF_DIRECTED_INVESTMENT: "SDI",
        FULL_SERVICE_WEALTH_MANAGEMENT: "FSWM",
        NEW_TO_INVESTMENT: "NOINV"
    }, t = [e.NEW_TO_INVESTMENT], n = ["PVB", "WTH", "PCB"], i = function (e) {
        return e && e.length ? e.sort().join("+") : ""
    }, o = function (e) {
        return n.indexOf(e) > -1
    }, r = function (n) {
        return n && n.length ? n.map((function (t) {
            return e[t]
        })) : t
    };
    return {
        accountTypeListToAEMString: i, accountTypeForAEMMenu: function (t) {
            return function (t) {
                return t && t.includes(e.FULL_SERVICE_WEALTH_MANAGEMENT)
            }(t) ? e.FULL_SERVICE_WEALTH_MANAGEMENT : i(t)
        }, isUserInIdeasSegment: o, mapDpsAccountTypeToAem: r, requestInvestmentAccountTypes: function (e, n) {
            return new Promise((function (i) {
                o(n) ? i(null) : e().then((function (e) {
                    i(r(e && e.accountCategories))
                }), (function () {
                    i(t)
                }))
            }))
        }
    }
})),define("common/settings", [], (function () {
    "use strict";
    return {
        allowOverrideTimeout: !0,
        serviceDelay: 420,
        areYouThereDelay: 340,
        sessionDelay: 310,
        checkTimeoutInterval: 10,
        logonUrl: "#/logon/logon/chaseOnline",
        signoutUrl: "auth/signout",
        expressSignoutUrl: "auth/expressSignout",
        cpoCookieName: "_iscpo",
        cpoCookieDefaultValue: 1,
        brandIdCookieName: {name: "BRAND_1_0"},
        defaultPageCookieName: {name: "DEFAULT_PAGE_1_0"},
        reloadCookieName: {name: "CPO_RELOAD"},
        hybridForceUpdateEnLanguage: {name: "hybridForceUpdateEnLanguage"},
        languageForHybrid: {name: "languageForHybrid"},
        siteDataPodID: "podID",
        defaultLogoffDestination: "https://www.chase.com",
        defaultLogoffVisualExperience: "greenbar",
        geoFreshnessCookieName: "_geoFreshness",
        geoZipCookieName: "_geoZip",
        isiToken: "Interconnect_Entry_Token",
        tcToken: "Taxcenter_Entry_Token",
        VoCUrl: "https://survey.experience.chase.com/jfe/form/SV_0rBuvmGXX6OhYEJ",
        gwmPCBUrl: "https://survey.experience.chase.com/jfe/form/SV_cHIMFgC8l4rjbZH",
        gwmNonPCBUrl: "https://survey.experience.chase.com/jfe/form/SV_0IpDBvmmvJGRjiR"
    }
})),define("common/voc/config/common", [], (function () {
    "use strict";
    return {
        blacklisted: {
            cpo: ["/verify", "/cancel", "/interstitial", "screen=verify", "screen=cancel", "signout", "logoff", "dashboard/index/index", "dashboard/investments", "dashboard/trade", "dashboard/payBills/index/index", "dashboard/paymentsAdmin/quickpayEnrollment/index", "dashboard/quickPay/quickpay/index", "dashboard/misc/helpCenter/index", "singlePayment/singlePaymentVerification", "singlePayment/repeatPaymentVerification", "billPayEnrollment/billPayEnrollment/reviewAgreement", "billPayEnrollment/billPayEnrollment/setUpBillPay", "billPayEnrollment/billPayEnrollmentConfirmation/index", "cancelBillPayment/confirmCancelMerchantBillPayment", "cancelBillPayment/confirmCancelRepeatingMerchantBillPayment"],
            cbo: ["/verify", "/cancel", "/interstitial", "screen=verify", "screen=cancel", "signout", "logoff", "dashboard/index/index", "dashboard/investments", "dashboard/trade", "dashboard/payBills/index/index", "dashboard/paymentsAdmin/quickpayEnrollment/index", "dashboard/quickPay/quickpay/index", "dashboard/misc/helpCenter/index", "singlePayment/singlePaymentVerification", "singlePayment/repeatPaymentVerification", "billPayEnrollment/billPayEnrollment/reviewAgreement", "billPayEnrollment/billPayEnrollment/setUpBillPay", "billPayEnrollment/billPayEnrollmentConfirmation/index", "cancelBillPayment/confirmCancelMerchantBillPayment", "cancelBillPayment/confirmCancelRepeatingMerchantBillPayment", "transferMoney/transferMoney/initiateTransfer", "mode=showPaymentActivity/verifySingleTransfer", "mode=showPaymentActivity/singleTransferConfirmation", "params=schedulewire"],
            cml: ["/verify", "/cancel", "/interstitial", "screen=verify", "screen=cancel", "signout", "logoff", "dashboard/index/index", "dashboard/investments", "dashboard/trade", "dashboard/payBills/index/index", "dashboard/paymentsAdmin/quickpayEnrollment/index", "dashboard/quickPay/quickpay/index", "dashboard/misc/helpCenter/index", "singlePayment/singlePaymentVerification", "singlePayment/repeatPaymentVerification", "billPayEnrollment/billPayEnrollment/reviewAgreement", "billPayEnrollment/billPayEnrollment/setUpBillPay", "billPayEnrollment/billPayEnrollmentConfirmation/index", "cancelBillPayment/confirmCancelMerchantBillPayment", "cancelBillPayment/confirmCancelRepeatingMerchantBillPayment", "transferMoney/transferMoney/initiateTransfer", "mode=showPaymentActivity/verifySingleTransfer", "mode=showPaymentActivity/singleTransferConfirmation", "params=schedulewire", "dashboard/accounts/summary", "dashboard/payBills/pendingApprovals", "dashboard/globalTransfer/send/calculate", "accessManager/users/edit", "collections/scheduleCollections/scheduleSingleVerify", "collections/scheduleCollections/scheduleRepeatVerify", "misc/classic/index;params=quickDepositChecks", "achPayments/schedule/scheduleSingleVerify", "achPayments/schedule/scheduleRepeatVerify", "accountServicing/downloadAccountTransactions", "commercialLoans/payLoans", "dashboard/serviceActivation/activation/wireTransfers", "dashboard/serviceActivation/activation/billPay", "dashboard/serviceActivation/activation/achPayments", "dashboard/serviceActivation/activation/cashflow", "dashboard/serviceActivation/activation/achCollections", "dashboard/serviceActivation/activation/quickDeposit", "dashboard/serviceActivation/activation/fraudProtection", "dashboard/serviceActivation/activation/manageTransactionLimit", "dashboard/serviceActivation/activation/dualControl", "dashboard/serviceActivation/activation/manageUserAccess", "dashboard/wires/wireMoney/recipientsList", "dashboard/payMultipleBills/payments/index", "dashboard/achPayments/schedule/list", "dashboard/cashflow/enrollment/index", "dashboard/landing/redirection;product=collections", "dashboard/collections/scheduleCollections/payorsList", "dashboard/quickDeposit/deposit/entry", "dashboard/quickDeposit/deposit/scan", "dashboard/accountsAdmin/fraudProtectionService/index", "dashboard/accountsAdmin/fraudProtectionService/accountsServices", "dashboard/accountsAdmin/fraudProtectionService/fraudProtectionSummary", "dashboard/profile/transactionLimits/manageTransactionLimits", "dashboard/accessManager/dualControl/index", "dashboard/accessManager/users/list", "dashboard/wiresAdmin/enrollment/main", "dashboard/achPayments/enrollment/companyActivation", "dashboard/collections/enrollment/enrollmentEnter;status=PENDING_ACTIVATION", "dashboard/collections/enrollment/enrollmentEnter;status=PROFILE_CREATION_REQUIRED", "dashboard/collections/companyActivation/index", "dashboard/quickDeposit/enrollment/index", "dashboard/quickDeposit/activation/questionnaire"],
            cre: ["/verify", "/cancel", "/interstitial", "screen=verify", "screen=cancel", "signout", "logoff", "dashboard/index/index", "dashboard/investments", "dashboard/trade", "dashboard/payBills/index/index", "dashboard/paymentsAdmin/quickpayEnrollment/index", "dashboard/quickPay/quickpay/index", "dashboard/misc/helpCenter/index", "singlePayment/singlePaymentVerification", "singlePayment/repeatPaymentVerification", "billPayEnrollment/billPayEnrollment/reviewAgreement", "billPayEnrollment/billPayEnrollment/setUpBillPay", "billPayEnrollment/billPayEnrollmentConfirmation/index", "cancelBillPayment/confirmCancelMerchantBillPayment", "cancelBillPayment/confirmCancelRepeatingMerchantBillPayment", "transferMoney/transferMoney/initiateTransfer", "mode=showPaymentActivity/verifySingleTransfer", "mode=showPaymentActivity/singleTransferConfirmation", "params=schedulewire", "dashboard/accounts/summary", "dashboard/payBills/pendingApprovals", "dashboard/globalTransfer/send/calculate", "accessManager/users/edit", "collections/scheduleCollections/scheduleSingleVerify", "collections/scheduleCollections/scheduleRepeatVerify", "misc/classic/index;params=quickDepositChecks", "achPayments/schedule/scheduleSingleVerify", "achPayments/schedule/scheduleRepeatVerify", "accountServicing/downloadAccountTransactions", "commercialLoans/payLoans", "dashboard/serviceActivation/activation/wireTransfers", "dashboard/wires/wireMoney/recipientsList", "dashboard/serviceActivation/activation/billPay", "dashboard/payMultipleBills/payments/index", "dashboard/serviceActivation/activation/achPayments", "dashboard/achPayments/schedule/list", "dashboard/serviceActivation/activation/cashflow", "dashboard/cashflow/enrollment/index", "dashboard/serviceActivation/activation/achCollections", "dashboard/landing/redirection;product=collections", "dashboard/serviceActivation/activation/quickDeposit", "dashboard/quickDeposit/deposit/entry", "dashboard/quickDeposit/deposit/scan", "dashboard/serviceActivation/activation/fraudProtection", "dashboard/accountsAdmin/fraudProtectionService/index", "dashboard/serviceActivation/activation/manageTransactionLimit", "dashboard/profile/transactionLimits/manageTransactionLimits", "dashboard/serviceActivation/activation/dualControl", "dashboard/accessManager/dualControl/index", "dashboard/serviceActivation/activation/manageUserAccess", "dashboard/accessManager/users/list", "dashboard/wiresAdmin/enrollment/main", "dashboard/achPayments/enrollment/entry", "dashboard/collections/enrollment/enrollmentEnter"]
        },
        resolutions: [{device: "desktop", width: {min: 992, max: 1e4}, height: {min: 0, max: 1e4}}, {
            device: "tablet",
            width: {min: 768, max: 991},
            height: {min: 0, max: 1e4}
        }]
    }
})),define("common/voc/util/session", ["require", "blue/is", "blue-app/settings", "common/voc/config/common", "blue/siteData"], (function (e) {
    "use strict";
    var t, n = e("blue/is"), i = e("blue-app/settings"), o = e("common/voc/config/common"), r = e("blue/siteData"),
            a = {BUSINESS: "Business", DEFAULT: "Default", COMMERCIAL: "Commercial"};
    return {
        getConditions: function () {
            if (t = i.get("voc_solicited", i.Type.USER), n.undefined(t) || n.undefined(t.applicableConditions) || 0 === t.applicableConditions.length) {
                var s = o.resolutions.filter((function (e) {
                    return e.width.min <= screen.width && e.width.max >= screen.width && e.height.min <= screen.height && e.height.max >= screen.height
                }));
                if (n.defined(s) && s.length > 0) {
                    var c = s[0].device, l = c.charAt(0).toUpperCase() + c.slice(1), u = r.getData("brandId");
                    e(["common/voc/config/invitation" + l + (a[u] || a.DEFAULT || "Default")], (function (e) {
                        n.defined(e) && (e.surveys.sort((function (e, t) {
                            return e.priority > t.priority ? -1 : e.priority < t.priority ? 1 : 0
                        })), i.set("voc_solicited", {applicableConditions: e.surveys}, i.Type.USER))
                    }))
                }
            }
            return this.getValue("applicableConditions")
        }, removeConditions: function (e) {
            if (n.defined(e) && e > 0) {
                var t = this.getValue("applicableConditions").filter((function (t) {
                    return t.priority >= e
                }));
                this.setValue("applicableConditions", t)
            }
        }, getValue: function (e) {
            if (t = i.get("voc_solicited", i.Type.USER), n.defined(t)) return t[e]
        }, setValue: function (e, o) {
            t = i.get("voc_solicited", i.Type.USER), n.defined(t) && (t[e] = o, i.set("voc_solicited", t, i.Type.USER))
        }, removeValue: function (e) {
            t = i.get("voc_solicited", i.Type.USER), n.defined(t) && delete t[e]
        }, cleanSession: function () {
            i.remove("voc_solicited", i.Type.USER)
        }
    }
})),define("common/voc/util/survey", ["require", "blue/is", "common/voc/util/session", "blue/store/enumerable/cookie", "appkit-utilities/VOC/util"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("common/voc/util/session"), i = new (e("blue/store/enumerable/cookie"))(null, !0),
            o = e("appkit-utilities/VOC/util");
    return {
        getSurvey: function (e) {
            var i = n.getConditions().filter((function (t) {
                return t.invitationId === e
            }));
            if (t.defined(i) && i.length > 0) return i[0]
        }, openSurvey: function () {
            if (t.defined(n.getValue("invitationId")) && !0 === n.getValue("isInvitationAccepted") && !this.surveyTriggered) {
                this.surveyTriggered = !0;
                var e = this.getSurvey(n.getValue("invitationId")), r = n.getValue("customVars");
                if (t.undefined(e)) return;
                s = e.invitationId, c = e.suppressionDuration, l = (new Date).setDate((new Date).getDate() + c), i.set("fsr.r", s, new Date(l)), n.cleanSession();
                var a = e.surveyUrl;
                if (t.undefined(a)) return;
                o.openVOCSurvey(a, {opinionLabParamsData: {referer: a}}, r)
            }
            var s, c, l
        }
    }
})),define("common/lib/logoff", ["require", "exports", "module", "blue/is", "blue/http", "common/settings", "blue/log", "blue/store/enumerable/cookie", "blue/store/enumerable/cookie", "blue/store/enumerable/cookie", "blue-app/settings", "common/voc/util/survey", "blue-app/with/locationAPI", "URI", "blue/store/enumerable/local"], (function (e, t, n) {
    "use strict";
    var i, o, r = e("blue/is"), a = e("blue/http"), s = e("common/settings"), c = e("blue/log")("[logoff]"),
            l = new (e("blue/store/enumerable/cookie"))(null, !0, null, "/", ".chase.com", !0),
            u = new (e("blue/store/enumerable/cookie"))("logoffSession", !1, null, "/", ".chase.com", !0),
            d = new (e("blue/store/enumerable/cookie"))("splash", !1, null, "/", ".chase.com", !0),
            m = e("blue-app/settings"), f = e("common/voc/util/survey"), p = e("blue-app/with/locationAPI"),
            g = e("URI"), y = !1, h = !1, v = [], b = new (e("blue/store/enumerable/local"))("Cobrowse Store");
    return new function () {
        var e = this, t = function (e) {
            var t = e ? e.status : "?", n = e ? e.statusText : "Unknown",
                    i = ((o || {}).application || {}).userLoggedOff ? "warn" : "error";
            c[i]("Signout failed " + t + ":" + n)
        }, A = function (e) {
            var t = o && g(o.config.pubUrl).host(), n = o && g(o.logoff.destination).host();
            o && t === n && l.set(m.get("logoffCookieName"), !0 === e ? "session" : "logoff")
        }, E = function () {
            d.remove("internationalizationSplashSeen")
        }, T = function () {
            try {
                sessionStorage.clear()
            } catch (e) {
                c.debug("Error when attempting to clear session storage: " + e)
            }
        }, C = function () {
            var e, t = Promise.resolve(), n = b.get("cobrowseSlotAPI");
            return "undefined" != typeof Cobrowse && Cobrowse.API && Cobrowse.API.Session && Cobrowse.API.Session.accessCode && (n && (Cobrowse.Events.SessionEnded.removeListener(n.sessionEndedCallback), o.services.coBrowseSlotService && o.services.coBrowseSlotService.coBrowseSlotApi && (t = o.services.coBrowseSlotService.coBrowseSlotApi(JSON.stringify({
                cobrowseSlotEvent: {
                    cobrowseMode: "Instant",
                    legalAgreementCode: "cobrowse",
                    legalAgreementCodeVersion: "gPDAI1uOET80Yajj",
                    privilegeLevel: "View_only",
                    profileId: n.profileId,
                    cobrowseSessionCode: n.cobrowseSessionCode,
                    digitalSegment: n.digitalSegment,
                    endSessionTimestamp: Date.now(),
                    agentId: n.agentId
                }
            })).then((function () {
            }), (function (e) {
                c.error("coBrowseSlotService.coBrowseSlotApi serviceError: " + JSON.stringify(e))
            })))), (e = Cobrowse.API.Session.stop()) && ("object" != typeof e || e.result) || c.info("Unable to end cobrowse session.", e)), b.clear(), t
        }, S = function (t) {
            c.debug("Signed out"), A(t), E(), e.goURL(o.newChild().config.AUTH_INDEX + o.newChild().config.AUTH_LOGOFF_PATH), f.openSurvey()
        }, _ = function (e) {
            var t, n = (e && e.logoutURLs || []).concat(v);
            t = n, u.set(m.get("logoffURLsCookieName"), t)
        }, O = function () {
            var e;
            o.config && o.config.staticImageUrl && (e = {
                type: "GET",
                url: o.config.staticImageUrl + "/ebank-out.jpg",
                data: {params: Date.now()}
            }, new Promise((function (t, n) {
                a.request(e).then((function (e) {
                    t(e)
                })).catch((function (e) {
                    n(e)
                }))
            }))).then((function () {
            }), (function (e) {
                c.info("Perf Logoff event call is failed", e)
            }))
        };
        p.call(e), e.getUrlStorage = function () {
            return u.get(m.get("logoffURLsCookieName"))
        }, e.removeUrlStorage = function () {
            return v = [], u.remove(m.get("logoffURLsCookieName"))
        }, e.logonSignout = function (e) {
            function n() {
                A(e), o.state(s.logonUrl)
            }

            T();
            return o.services.signOutService.authSignout().then(n, (function (e) {
                401 === e.status ? n() : t(e)
            }))
        }, e.accountsAndAuthSignout = function (e, n) {
            if (T(), !y) {
                y = !0;
                var i = function () {
                    A(e), E(), f.openSurvey(), n && r.function(n) && n()
                };
                return o.services.signOutService.accountsSignout({authType: "DEFAULT"}).then((function (e) {
                    return _(e), o.services.signOutService.authSignout()
                })).then(i, (function (e) {
                    401 === e.status ? i() : t(e)
                }))
            }
        }, e.authSignout = function (e) {
            return T(), o.application.userLoggedOff = !0, o.services.signOutService.authSignout().then((function () {
                A(e), E(), f.openSurvey()
            }), t)
        }, e.accountsSignout = function (e) {
            if (!y) {
                y = !0;
                return o.services.signOutService.accountsSignout({authType: "DEFAULT"}).then(n, (function (e) {
                    401 === e.status ? n() : t(e)
                }))
            }

            function n(t) {
                _(t), o.config.ebankPerfMetricDisabled || O(), e && r.function(e) && e()
            }
        }, e.signOutAndRedirectByPromise = function (t) {
            return o.newChild().config.enableIntegratedSignout ? C().then((function () {
                e.accountsSignout((function () {
                    o.state(("greenbar" === e.visualExperience ? i + s.signoutUrl : i + s.expressSignoutUrl) + (t ? ";params=session" : ""))
                }))
            })) : C().then((function () {
                e.accountsSignout((function () {
                    S(t)
                }))
            }))
        }, e.signOutAndRedirect = function (t) {
            o.newChild().config.enableIntegratedSignout ? C().then((function () {
                e.accountsSignout((function () {
                    o.state(("greenbar" === e.visualExperience ? i + s.signoutUrl : i + s.expressSignoutUrl) + (t ? ";params=session" : ""))
                }))
            })) : C().then((function () {
                e.accountsSignout((function () {
                    S(t)
                }))
            }))
        }, e.partnerSignOutAndRedirect = function () {
            C().then((function () {
                e.accountsSignout((function () {
                    o.state(i + s.signoutUrl)
                }))
            }))
        }, e.redirect = function (t) {
            o.newChild().config.enableIntegratedSignout ? C().then((function () {
                o.state(("greenbar" === e.visualExperience ? i + s.signoutUrl : i + s.expressSignoutUrl) + (t ? ";params=session" : ""))
            })) : C().then((function () {
                S(t)
            }))
        }, e.setDestination = function (t) {
            e.destination = t
        }, e.setVisualExperience = function (t) {
            e.visualExperience = t
        }, e.init = function (t) {
            if (!h) {
                h = !0, e.setAppContext(t);
                var i = n.config() || {};
                e.setDestination(i.pubUrl ? i.pubUrl : s.defaultLogoffDestination), e.setVisualExperience(s.defaultLogoffVisualExperience)
            }
        }, e.setAppContext = function (e) {
            e && (i = "#/" + (o = e).appName + "/")
        }, e.registerLogoffUrl = function (e) {
            if (!r.string(e)) return c.error("invalid logoff URL " + e);
            -1 === v.indexOf(e) && v.push(e)
        }, e._setPubSiteCookie = A
    }
})),define("common/lib/mailingAddressCodes", [], (function () {
    "use strict";
    return {
        stateCodes: ["AL", "AK", "AZ", "AR", "CA", "CO", "CT", "DE", "DC", "FL", "GA", "HI", "ID", "IL", "IN", "IA", "KS", "KY", "LA", "ME", "MD", "MA", "MI", "MN", "MS", "MO", "MT", "NE", "NV", "NH", "NJ", "NM", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "RI", "SC", "SD", "TN", "TX", "UT", "VT", "VA", "WA", "WV", "WI", "WY", "MW", "GU", "VI", "AS", "MP", "PR"],
        provinceCodes: ["AB", "BC", "MB", "NB", "NF", "NT", "NS", "NU", "ON", "PE", "QC", "SK", "YT"],
        militaryRegionCodes: ["AA", "AE", "AP"],
        countryCodes: ["AFG", "AXL", "ALB", "ALG", "AMS", "AND", "ANG", "AGU", "ATA", "ANU", "ARG", "ARM", "ARU", "ASL", "ASA", "AZE", "BAH", "BHR", "BNG", "BRB", "BEL", "BLG", "BLM", "BLZ", "BNN", "BER", "BHT", "BOL", "BES", "BOS", "BWA", "BRA", "IOT", "VGB", "BRU", "BGR", "BFA", "BUR", "BDI", "CDA", "CMR", "CPV", "CYI", "CAR", "CHD", "CHL", "CHA", "COL", "CMS", "CRA", "CDI", "CRO", "CBA", "CUW", "CYP", "CSK", "KOD", "DNK", "DJI", "DMA", "DOM", "ECU", "EGY", "ELV", "GNQ", "ERI", "EST", "ETH", "FLK", "FRO", "FJI", "FIN", "FRA", "GUF", "FPO", "ATF", "GAB", "GAM", "GRM", "GHA", "GIB", "GBI", "GRE", "GRL", "GRN", "GUA", "GTM", "GGY", "GIN", "GNB", "GUY", "HTI", "HND", "HKG", "HUN", "ISL", "IND", "IDN", "IRN", "IRQ", "IRL", "ISR", "ITA", "JMA", "JAP", "JEY", "JHN", "JOR", "KAZ", "KYA", "KIR", "KWT", "KYR", "LAS", "LAT", "LBN", "LST", "LBR", "LBA", "LIE", "LIT", "LUX", "MAC", "MAF", "MDG", "MWI", "MYS", "MLD", "MLI", "MLT", "MHL", "MTQ", "MRT", "MUS", "MEX", "FSM", "MID", "MOL", "MCO", "MNG", "MNE", "MSR", "MAR", "MOZ", "NAM", "NRU", "NPL", "NLD", "NCL", "NZL", "NCA", "NGR", "NGA", "NOR", "OMN", "PKS", "PLW", "PSE", "PAN", "PPA", "PRY", "PER", "PHI", "PCN", "POL", "PRT", "QAT", "SKR", "GEO", "MCE", "CON", "REU", "ROM", "RUS", "RWA", "KNA", "SHN", "LCA", "SPM", "VCT", "SMR", "STM", "SAR", "SGL", "SRB", "SYC", "SRL", "SGP", "SXM", "SLR", "SLO", "SLB", "SOM", "SAF", "SPN", "SLK", "SDN", "SRN", "SSD", "SWZ", "SWE", "SWI", "SYR", "TWN", "TAJ", "TZA", "THA", "TLS", "TGO", "TON", "TTO", "TUN", "TUR", "TUK", "TCA", "TVL", "UGA", "UKR", "ARE", "URG", "UZB", "VAN", "VTC", "VEN", "VTM", "WLL", "WSM", "YMN", "ZAM", "ZMB"],
        postOfficeTypeCodes: ["ARMY_AIR_POST_OFFICE", "FIELD_POST_OFFICE"],
        mailingAddressTypeCodes: ["UNITED_STATES", "INTERNATIONAL", "MILITARY", "CANADA"]
    }
})),define("common/lib/menu/menuState", {
    globalNavigationIsShowing: !1,
    filteredPA: !1,
    setProfileFocusOnH1: !1,
    isSubLevelNavigation: !1,
    filteredSingleDoorPA: !1,
    singleDoorSubMenu: !1,
    paymentServicesHubSubMenu: !1,
    subMenuPaths: ["markets/indices/screeners", "markets/assets", "dashboard/achPayments/activity/editSingle", "dashboard/achPayments/activity/editSingleVerify", "dashboard/achPayments/activity/editSingleConfirm", "dashboard/achPayments/activity/editRepeatingEntry", "dashboard/achPayments/activity/editRepeatingVerify", "dashboard/achPayments/activity/editRepeatingConfirm", "dashboard/achPayments/payees/editRepeatingEntry", "dashboard/achPayments/payees/editRepeatingConfirm", "dashboard/achPayments/payees/editRepeatingVerify", "dashboard/achPayments/payees/editSingle", "dashboard/achPayments/payees/editSingleVerify", "dashboard/achPayments/payees/editSingleConfirm", "dashboard/achPayments/schedule/list"]
}),define("common/lib/menu/menuUtility", ["require", "common/lib/accountInfo", "common/lib/API/contextValidation/contextValidationAPI", "common/lib/constants", "common/lib/menu/menuState", "blue/root", "blue/util", "blue/is", "common/lib/feature"], (function (e) {
    "use strict";
    var t, n = new (e("common/lib/accountInfo")), i = e("common/lib/API/contextValidation/contextValidationAPI"),
            o = e("common/lib/constants"), r = o.detailTypeReplace, a = {}, s = {}, c = {}, l = {},
            u = e("common/lib/menu/menuState"), d = e("blue/root"), m = e("blue/util"), f = e("blue/is"), p = d.hybrid,
            g = e("common/lib/feature"), y = {
                "profile.personal.service.plan": ["accountmanagement.service.plan"],
                "payments.more.accessmanager": ["accountmanagement.accessmanager"],
                "payments.more.classicaccmgr": ["accountmanagement.accessmanagerClassic"],
                chasedeposit: ["collectreceive.deposits"],
                "investments.research.mutualfunds": ["investments.research.mutualfunds", "investments.screeners.mutualfunds"],
                "investments.research.etfs": ["investments.research.etfs", "investments.screeners.etfs"],
                "investments.research.stocks": ["investments.research.stocks", "investments.screeners.stocks"]
            }, h = {
                statementsdocuments: ["statementsAndDocuments"],
                "marketsinsights.markets": ["marketsinsights.markets", "marketsinsights.symbolsearch"],
                "marketsinsights.iai": ["marketsinsights.ideasinsights"],
                "marketsinsights.markets.overviews": ["marketsinsights.markets.overviews"],
                "marketsinsights.markets.news": ["marketsinsights.markets.news"],
                "marketsinsights.markets.watchlists": ["marketsinsights.markets.watchlists"],
                "marketsinsights.markets.screeners": ["marketsinsights.markets.screeners"],
                "marketsinsights.markets.globaloverview": ["marketsinsights.markets.globaloverview"],
                "marketsinsights.markets.currencies": ["marketsinsights.markets.currencies"],
                "marketsinsights.markets.calendar": ["marketsinsights.markets.calendar"],
                "marketsinsights.markets.alerts": ["marketsinsights.markets.alerts"],
                "investments.markets": [],
                "accounts.summary": ["gwmAccountsOverview.summary"],
                "accounts.activity": ["gwmAccountsOverview.activity"],
                "accounts.transactions": ["gwmAccountsOverview.transactions"]
            }, v = {
                "investments.markets": ["investments.markets", "investments.symbolsearch"],
                "investments.research": ["investments.research"]
            }, b = function (e, t, n, i, o, r, a, s) {
                var c = {
                    parentComponent: a, parentComponentAnalyticsKey: s, executeAction: function () {
                        o && o(), e(n.clickHandlerEvent)
                    }, executeNoAction: function () {
                        r && r(), t && t()
                    }
                };
                this.context.application.dirtyFormVariation && (c.variation = this.context.application.dirtyFormVariation), i ? this.context.application.broadcast("dirtyOverlayAPI/showConfirmationOverlay", {data: c}) : c.executeAction()
            }, A = function (e) {
                return "payments.wiretransfers.business" === e.name
            };
    return {
        init: function (e) {
            e && (l = e.config)
        }, invokeActionIfRegistered: function (e, t, n) {
            a[e] = t, c.hasOwnProperty(e) && (a[e](), delete a[e], n && delete c[e])
        }, registerAction: function (e, t) {
            c[e] = !0, a.hasOwnProperty(e) && (a[e](), delete a[e], t && delete c[e])
        }, checkKeyAndReset: function (e) {
            a.hasOwnProperty(e) && delete a[e]
        }, rationalizeHamburgerMenuData: function (e) {
            var t = [];
            n.isBusinessUser() || n.isGeminiUser() || t.push("accountmanagement.profile.settings");
            for (var i = 0, o = e.length; i < o; i++) t.push(e[i]);
            return t
        }, rationalizeServiceData: function (e, i) {
            var o, r = m.lang.defaults(e.menuItems, []), a = l.enableInvestments && "false" !== l.enableInvestments;
            r = this.blockMenuItemsBasedOnKillSwitch(r), t = m.object.merge({}, y), o = n.isGWMUser(), g.enabled("gwmAggregateTransactions") || (h["accounts.transactions"] = []), Object.keys(h).forEach((function (e) {
                t[e] = o ? h[e] : v[e] || []
            }));
            var s = {"investments.markets": a ? ["investments.symbolsearch"] : []}, c = {
                "payments.transfersecurities": l.securitiesTransfersEnabled,
                "payments.brokeragetransfers": void 0 === l.brokerageTransfersEnabled || "false" === l.brokerageTransfersEnabled.toString().toLowerCase()
            };
            !function (e, i) {
                var o = m.array.find(e, A);
                l.achCollectionsEnabled && ("CRE" !== n.getSegment() || o) ? (t["payments.more.collections"] = ["collectreceive.collections"], t["collections.schedule"] = ["collectreceive.collections.scheduleach"], t["collections.activity"] = ["collectreceive.collections.activity"], t["collections.payor"] = ["collectreceive.collections.managepayors"], !1 !== l.pddCollectionsEnabled && (t["collections.company.activation"] = ["collectreceive.collections.company.activation"])) : i["payments.more.collections"] = !1
            }(r, c);
            var u = function (e, t) {
                var n = ["accounts.activity", "accounts.summary"];
                return m.array.find(e, (function (e) {
                    return n.indexOf(e.name) > -1
                })) || function (e) {
                    return e && 1 === e.length && {WPY: !0}[e[0]]
                }(t) ? [] : ["accounts.activity"]
            }(r, i);
            return function (e) {
                (n.isBusinessUser() || n.isGeminiUser()) && e.push("accountmanagement.profile.settings")
            }(u), r.forEach((function (e) {
                t[e.name] ? [].push.apply(u, t[e.name]) : c.hasOwnProperty(e.name) && !c[e.name] || (u.push(e.name), s.hasOwnProperty(e.name) && s[e.name].forEach((function (e) {
                    u.push(e)
                })))
            })), u
        }, rationalizeTertiaryPrivileges: function (e, n) {
            var i = [], o = [];
            return e.forEach((function (e) {
                n || "payments.manageexternalaccounts" !== e.name && "payments.managecardpayfromaccounts" !== e.name ? t[e.name] ? [].push.apply(i, t[e.name]) : i.push(e.name) : o.push(e.name), "payments.quickpaysettings" === e.name && i.push("payments.persontoperson.request")
            })), o.length > 1 ? i.push("payments.paybills.payfrom") : i = i.concat(o), i
        }, blockMenuItemsBasedOnKillSwitch: function (e) {
            var t = {
                enableFps: {privilege: "accountmanagement.fps", blockIfPropIsUndefined: !1},
                enableInvestmentsOpenAutomatedAccount: {
                    privilege: "investments.openaccount.digitalinvest",
                    blockIfPropIsUndefined: !1
                },
                enableTaxCenter: {privilege: "statementsdocuments.taxcenter", blockIfPropIsUndefined: !1},
                giftingEnabled: {privilege: "payments.gifting", blockIfPropIsUndefined: !1}
            }, n = {};
            return Object.keys(t).forEach((function (e) {
                var i = l[e];
                (!1 === i || void 0 === i && t[e].blockIfPropIsUndefined) && (n[t[e].privilege] = !0)
            })), e.filter((function (e) {
                return !n[e.name]
            }))
        }, preserveParams: function (e, t) {
            var n = {};
            if ((t = "string" == typeof t ? [t] : t).length && t.forEach((function (t) {
                e[t] && (n[t] = e[t])
            })), Object.keys(n).length) return {params: {action: n}}
        }, createStateChangeParams: function (e, t) {
            var n = {params: {}};
            return t || e && (n.params.action = {source: e}), n
        }, dirtyFormCheck: function (e, t, n) {
            var o = this;
            if ((f.undefined(n) || f.empty(n)) && (n = {clickHandlerEvent: {}}), this.context.sharedData.get("modalApi")) {
                var r = this.context.sharedData.get("modalApi"), a = this.context.sharedData.get("controllerContext");
                r ? this.context.sharedData.get("dirtyFormCheck").dirtyCheck(a, (function () {
                    o.context.sharedData.clean(), e(n.clickHandlerEvent)
                }), {}, (function () {
                    t && t()
                })) : (this.context.sharedData.clean(), e(n.clickHandlerEvent))
            } else i.isDirty(this.context.controller, {
                level: "APPLICATION",
                areaFilter: {}
            }) ? i.showConfirmationOverlay(this.context.controller, this, "exitConfirmationOverlay", (function () {
                o.context.application.broadcast("clearDirtyFlag", n), e(n.clickHandlerEvent)
            }), (function () {
                t && t()
            })) : this.context.dirtyForm.dirtyCheckAll().isDirty ? (this.context.application.showOverlay = b.bind(this, e, t, n), n.clickHandlerEvent.context && n.clickHandlerEvent.context.navMapping ? o.context.application.broadcast("makeNavigation", {navKey: n.clickHandlerEvent.context.navMapping}) : e(n.clickHandlerEvent)) : e(n.clickHandlerEvent)
        }, getPermissionsCache: function (e) {
            if (e && "string" == typeof e) return s[e]
        }, getDetailTypeReplace: function (e) {
            return e.replace(/\w+/g, (function (e) {
                return r[e] || e
            }))
        }, setPermissionsCache: function (e, t) {
            if (e && "string" == typeof e) return s[e] = t, t
        }, deletePermissionsCache: function (e) {
            e && "string" == typeof e && delete s[e]
        }, validateShownPaymentsActivityMenuSubset: function (e) {
            return !!document.getElementById(e.id)
        }, setOptionClass: function (e, t) {
            var n = this.context.state().action.params.ai, i = t && t.indexOf("payments.paymentcenter") > -1,
                    o = !isNaN(parseFloat(n)), r = {
                        realizedgainloss: function () {
                            return p && o ? "" : e.cssClass
                        }, defaultOptionClass: function () {
                            return e.cssClass
                        }, ACHPaymentServices: function () {
                            return i ? "hide-xs" : e.cssClass
                        }, quickPay: function () {
                            return i ? "hide-xs" : e.cssClass
                        }, payBills: function () {
                            return i ? "hide-xs" : e.cssClass
                        }, wireTransfers: function () {
                            return i ? "hide-xs" : e.cssClass
                        }
                    };
            return (r[e.id] || r.defaultOptionClass)()
        }, skipMenuH1Focus: function () {
            u.skipMenuFocusSet = !0
        }, getFeatureFlagValue: function (e, t, n) {
            if (e.id === o.staySafe.ACH_DEBIT_BLOCK_ID) {
                var i = t[o.staySafe.ACH_DEBIT_BLOCK], r = t[o.staySafe.STAY_SAFE_FLAG];
                i && !r && (n = i)
            } else n = e.id === o.staySafe.FRAUD_PROTECTION_SERVICE_ID || e.negateFeatureFlagValue ? !t[e.featureFlag] : !e.featureFlag || t[e.featureFlag];
            return n
        }
    }
})),define("common/lib/utility/formatDateUtility", ["require", "moment", "appkit-utilities/content/dcu", "common/lib/constants", "appkit-utilities/language/helper"], (function (e) {
    "use strict";
    var t, n, i, o, r, a, s, c, l, u, d, m = e("moment"), f = e("appkit-utilities/content/dcu"),
            p = e("common/lib/constants"), g = e("appkit-utilities/language/helper");
    return a = function () {
        var e = p.monthArray, r = g.getContentLanguage();
        if (o !== r || !n) {
            var a, s, c, l = {spec: {name: "GLOBAL"}, area: {name: "app"}}, u = {}, d = {}, m = [], y = [], h = {};
            if (o = r, a = f.dynamicContent.getList(l, "monthAbbreviation", "key", "value"), s = f.dynamicContent.getList(l, "month", "key", "value"), 0 !== a.length && 0 !== s.length) {
                for (c = 0; c < 12; c++) u[a[c].value] = a[c].key, d[s[c].value] = s[c].key;
                e.forEach((function (e, t) {
                    m[t] = u[e], y[t] = d[e], h[m[t]] = ("0" + (t + 1)).toString().slice(-2)
                })), t = m, n = h, i = y
            }
        }
    }, u = function (e) {
        var n, i = e;
        return r(e) && (a(), n = new Date(e), i = t[n.getMonth()] + " " + n.getDate() + ", " + n.getFullYear()), i
    }, {
        isValidDate: r = function (e) {
            var t = new Date(e);
            return !isNaN(t.valueOf())
        }, formatDate: function (e) {
            return m(e).format("MM/DD/YYYY")
        }, formatServiceDate: s = function (e) {
            return m(e, "YYYYMMDD").format("L")
        }, formatDateForServiceInput: l = function (e) {
            return m(e, "MM DD YYYY").format("YYYYMMDD")
        }, parseDateMonthDayYear: u, parseDateMonthYear: function (e) {
            var n, i = e;
            return r(e) && (a(), n = new Date(e), i = t[n.getMonth()] + ", " + n.getFullYear()), i
        }, parseFullMonthDayYear: function (e) {
            var t = e;
            if (r(e)) {
                var n = m(e);
                a(), t = i[n.month()] + " " + n.date() + ", " + n.year()
            }
            return t
        }, formatDashedYearMonthDayToSlashedMonthDayYear: c = function (e) {
            return m(e, "YYYY-MM-DD").format("L")
        }, parseDateMonthDayYearToMMDDYYYY: function (e) {
            if (!e) return e;
            var t = e.substring(0, 3), i = 11 === e.length ? "0" + e.substring(4, 5) : e.substring(4, 6),
                    o = 11 === e.length ? e.substring(7) : e.substring(8);
            return a(), n[t] + "/" + i + "/" + o
        }, parseDisplayDate: d = function (e) {
            try {
                if (e) {
                    a();
                    var t = e.split(" "), i = n[t[0]], o = t[1].replace(",", "");
                    return t[2] + i + o
                }
            } catch (e) {
            }
            return e
        }, dateFormatConverter: function (e) {
            return 8 === e.length ? s(e) : e
        }, formatDateInFormat: function (e, t) {
            return e ? m(this.dateFormatConverter(e)).format(t) : e
        }, formatFutureDateFromStartDate: function (e, t, n, i, o) {
            return m(e, i).add(t, n).format(o)
        }, formatPastDateFromStartDate: function (e, t, n, i, o) {
            return m(e, i).subtract(t, n).format(o)
        }, formatTodaysDate: function (e) {
            return m().format(e)
        }, formatServiceDateToShortDate: function (e) {
            return e ? this.parseDateMonthDayYear(this.dateFormatConverter(e)) : e
        }, formatServiceDateToShortMonthYearDate: function (e) {
            return e ? this.parseDateMonthYear(this.dateFormatConverter(e)) : e
        }, formatDateToUserDisplayFormat: function (e) {
            var t, n = e;
            try {
                e && (8 === e.length ? e = s(e) : 10 === e.length && -1 !== e.indexOf("-") && (e = c(e)), n = 10 === e.length && -1 !== e.indexOf("/") ? r(t = e) ? u(t) : t : e)
            } catch (t) {
                n = e
            }
            return n
        }, dateAllToMMddyyyy: function (e) {
            return e ? 8 === e.length ? s(e) : 10 === e.length && -1 !== e.indexOf("-") ? c(e) : e : ""
        }, dateAlltoyyyymmdd: function (e) {
            return e ? 10 === e.length && -1 !== e.indexOf("-") ? e.replace(/-/g, "") : 10 === e.length && -1 !== e.indexOf("/") ? l(e) : 12 === e.length ? d(e) : e : ""
        }, parseFullMonthYear: function (e) {
            var t = e;
            if (r(e)) {
                var n = m(e);
                a(), t = i[n.month()] + " " + n.year()
            }
            return t
        }, formatYearMonthServiceDate: function (e) {
            return m(e, "YYYYMM").format("MMMM YYYY")
        }
    }
})),define("common/lib/quickPay/qpFormatUtility", ["require", "common/lib/utility/formatDateUtility", "blue/util"], (function (e) {
    "use strict";
    var t, n, i = e("common/lib/utility/formatDateUtility"), o = e("blue/util").lang.defaults;
    return {
        formatDateUtility: i, formatCurrencyUtility: (t = function (e, t, n, i, o, r, a) {
            return "".concat(e, null == r || "" === r ? "" : r, o ? String(n).substr(0, o) + t : "", String(n).substr(o).replace(/(\d{3})(?=\d)/g, "$1" + t), a ? "." + Math.abs(i - n).toFixed(a).slice(2) : "", "(" === e ? ")" : "")
        }, {
            formatCurrency: function (e, n, i, r, a, s) {
                if (isNaN(e) && s) return s;
                var c = isNaN(n = Math.abs(n)) ? 2 : n, l = o(i, ","), u = function (e, t) {
                            return e < 0 ? null == t || "" === t ? "-" : t : ""
                        }(e, r),
                        d = isNaN(e) ? parseInt(e = 0) + "" : Number.MAX_SAFE_INTEGER > e ? parseInt(e = Math.abs(o(+e, 0)).toFixed(c)) + "" : o(e, 0),
                        m = (m = d.length) > 3 ? m % 3 : 0;
                return t(u, l, d, e, m, a, c)
            }, commonCurrency: function (e, t, n) {
                var i = e;
                try {
                    t = o(t, "$"), n = o(n, ","), i = this.formatCurrency(this.stripCurrency(e), 2, n, "â€“", t)
                } catch (t) {
                    i = e
                }
                return i
            }, stripCurrency: function (e) {
                var t = new RegExp("[^\\d.\\-]", "g");
                return e ? e.toString().replace(t, "") : ""
            }, removeCommaFromCurrency: function (e) {
                return "string" == typeof e ? e.replace(/,/g, "") : e
            }, formatAmount: function (e) {
                return parseFloat(Math.round(100 * e) / 100).toFixed(2)
            }, validCommonCurrency: function (e) {
                var t = e.toString().trim();
                return n(t) ? this.commonCurrency(t, "", ",") : t
            }, validCommonCurrencyCheck: n = function (e) {
                return new RegExp(/^\$?([0-9]{1,3}(,[0-9]{3})*(\.[0-9]{0,2})?|[0-9]+(\.[0-9]{1,2})?|[0-9]+\.|(\.[0-9]{1,2})+)$/).test(e)
            }
        }), formatStringUtility: {
            formatWrapText: function (e, t, n, i, o) {
                if (n = n || "\n", t = t || 40, i = i || !1, !(e = (e = e || "").replace(/\n|\r/g, ""))) return "";
                var r = ".{1," + t + "}($)" + (i ? "|.{" + t + "}|.+$" : "|\\S+?(\\s|$)"),
                        a = e.match(new RegExp(r, "g")).join(n);
                return a && o ? '"' + a + '"' : a
            }, formatStringIfNull: function (e, t) {
                return e || (e = t || ""), e
            }, unFormatWrapText: function (e, t) {
                if (!e) return "";
                var n = new RegExp(t, "g");
                return e.replace(n, "")
            }, removeNewLineChar: function (e) {
                return e ? e.replace(/\n|\r/g, "") : ""
            }
        }, formatPhoneUtility: {
            formatPhone: function (e) {
                return e && e.match(/^[0-9]{10}$/) ? e.substring(0, 3) + "-" + e.substring(3, 6) + "-" + e.substring(6, 10) : e
            }, unFormatPhone: function (e) {
                return "undefined" !== e && null !== e && e.match(/^[0-9-]{12}$/) ? e = e.match(/\d+/g).join("") : e
            }
        }, formatBooleanUtility: {
            formatPropertyToBool: function (e, t) {
                return e ? !0 === e || "true" === e : !0 === t && t
            }
        }, formatUrlUtility: {
            stripFocusEnumParam: function (e) {
                return e.replace(/;focusEnum=[^;]+/, "")
            }
        }, removeItemFromArray: function (e, t) {
            return -1 < t && e.splice(t, 1), e
        }, isNumeric: function (e) {
            return e > 1 && /^[0-9]{0,9}$/.test(e)
        }, isNumber: function (e) {
            return /^[0-9]{0,9}$/.test(e)
        }, showStatusTooltip: function (e) {
            return !(["COMPLETED", "SENT", "RECEIVED"].indexOf(e) > -1)
        }, getActivityStatusContentVariation: function (e) {
            return {
                COMPLIANCE_REJECTED: "REJECTED_BASED_ON_OFAC_REPORTS",
                COMPLIANCE_CANCELED: "CANCELED_BASED_ON_OFAC_REPORTS"
            }[e] || e
        }
    }
})),define("common/lib/merchantBillPay/formatUtilityWrapper", ["require", "common/lib/quickPay/qpFormatUtility", "appkit-utilities/content/dcu"], (function (e) {
    "use strict";
    var t = e("common/lib/quickPay/qpFormatUtility"), n = e("appkit-utilities/content/dcu"), i = n.dynamicContent.get,
            o = function (e) {
                var t = new RegExp("[^\\d.\\-]", "g");
                return e ? e.toString().replace(t, "") : ""
            }, r = function (e, t) {
                return n.dynamicContent.getGlobal(e) || t
            }, a = function (e, t) {
                return e.model.lens(t).get()
            };
    return {
        createDateObjArray: function (e, n, i) {
            var o = [];
            try {
                for (var r, a = 0; a < e.length; a++) r = t.formatDateUtility.dateAllToMMddyyyy(n ? e[a][n] : e[a]), o[o.length] = i ? {date: r} : r
            } catch (e) {
            }
            return i ? o : o.toString()
        }, getCorrespondingDate: function (e, n, i, o) {
            var r = t.formatDateUtility.dateAllToMMddyyyy(n);
            if (e && e[0]) {
                Object.keys(e[0]).length <= 1 && (o = i = Object.keys(e[0])[0]);
                for (var a = 0; a < e.length; a++) if (t.formatDateUtility.dateAllToMMddyyyy(e[a][i]) === r) return t.formatDateUtility.dateAllToMMddyyyy(e[a][o]);
                return n
            }
        }, stripCurrency: o, commonCurrency: function (e, n, i, a) {
            a = void 0 !== a ? a : 2;
            var s = r("minusSymbol", "â€“"), c = r("commaSymbol", ","), l = r("currencyDollarSymbol", "$"), u = e;
            try {
                n = void 0 !== n ? n : l, i = void 0 !== i ? i : c, u = t.formatCurrencyUtility.formatCurrency(o(e), a, i, s, n)
            } catch (t) {
                u = e
            }
            return u
        }, capitaliseString: function (e) {
            return e.charAt(0).toUpperCase() + e.slice(1).toLowerCase()
        }, dateAlltoyyyymmdd: t.formatDateUtility.dateAlltoyyyymmdd, updateViewModel: function (e, t, n) {
            n = void 0 !== n ? n : "updateBPViewModel", e.output.emit("state", {target: e, value: n, data: t})
        }, setFocus: function (e, t, n) {
            n = void 0 !== n ? n : "", e.output.emit("state", {value: "setFocus", element: t, payLoad: n})
        }, getValueFromResource: a, formatZuluDate: function (e) {
            var n = e;
            if (t.formatDateUtility.isValidDate(e)) {
                var i = new Date(e);
                n = t.formatDateUtility.parseDateMonthDayYear(i.toDateString()) + " " + i.toLocaleTimeString()
            } else n = t.formatDateUtility.formatDateToUserDisplayFormat(e);
            return n
        }, sanitize: function (e, t, n) {
            var i = t;
            return t && "" !== t || (i = n ? a(e, n) : ""), void 0 !== i ? i : n
        }, getDeliverByOption: function (e, t, n, o) {
            var r = "", a = o ? {area: {name: "app"}, spec: {name: "GLOBAL"}} : e;
            if (t.frequency) {
                var s = {
                    WEEKLY: function () {
                        t.dayOfWeek && (r = i(a, "dayOfWeek", t.dayOfWeek))
                    }, MONTHLY: function () {
                        t.dayOfMonth && (r = i(a, "dayOfMonth", t.dayOfMonth))
                    }, TWICE_MONTHLY: function () {
                        t.dayOfMonth && t.secondDayOfMonth && (r = i(a, "dayOfMonth", t.dayOfMonth) + " " + i(a, "transactionInitiationOptionJoinLabel") + " " + i(a, "dayOfMonth", t.secondDayOfMonth))
                    }, YEARLY: function () {
                        t.month && (r = i(a, "month", t.month) + (t.secondDayOfMonth ? " " + i(a, "dayOfMonth", t.secondDayOfMonth) : t.dayOfMonth ? " " + i(a, "dayOfMonth", t.dayOfMonth) : ""))
                    }
                };
                s.BIWEEKLY = s.WEEKLY, s.FOUR_WEEKS = s.WEEKLY, s.BIMONTHLY = s.MONTHLY, s.QUARTERLY = s.MONTHLY, s.SEMI_ANNUALLY = s.MONTHLY, s[t.frequency] && s[t.frequency](), r += " " + i(e, n ? n + "." + t.frequency : "transactionInitiationOptionAdvisory." + t.frequency)
            }
            return r
        }, formatUtility: t, dateAllToMMddyyyy: t.formatDateUtility.dateAllToMMddyyyy
    }
})),define("common/lib/messagingUtility", ["require", "appkit-utilities/content/dcu", "common/lib/constants"], (function (e) {
    "use strict";
    var t = e("appkit-utilities/content/dcu"), n = e("common/lib/constants"), i = n.ERR_STATUS_CODE;
    return {
        getMessageFlags: function (e) {
            var t = {}, n = {}, o = function () {
                t.errorFlag = !0
            }, r = function () {
                t.errorFlag = !1
            }, a = function () {
                t.errorFlag = !1, t.advisory = null
            };
            return n[i.ACCOUNT_CLOSED] = function () {
                t.isClosedAccount = !0, t.errorFlag = !1
            }, n[i.ACCOUNT_STATUS_101] = o, n[i.ACCOUNT_STATUS_102] = o, n[i.ACCOUNT_STATUS_103] = o, n[i.ACCOUNT_STATUS_104] = o, n[i.ACCOUNT_STATUS_105] = o, n[i.ACCOUNT_STATUS_106] = o, n[i.ACCOUNT_STATUS_109] = o, n[i.PAYMENT_CHANGING_AFTER_ESCROW_ANALYSIS] = o, n[i.ACCOUNT_STATUS_108] = o, n[i.PAST_DUE_MORE_THAN_90] = o, n[i.PAST_DUE_MORE_THAN_59] = o, n[i.PAST_DUE_LESS_THAN_90] = r, n[i.NEARING_LEASE_END] = r, n[i.NEAR_LEASE_END_PAST_DUE_LESS_THAN_59] = r, n[i.PAST_DUE_1_TO_19] = a, n[i.PAST_DUE_20_TO_59] = a, n[e] && n[e](), t
        }, getCollectionFlag: function (e) {
            return [n.PAST_DUE_1_TO_60_DAYS_CLOSED, n.PAST_DUE_61_TO_120_DAYS_CLOSED, n.PAST_DUE_121_TO_210_DAYS_CLOSED].indexOf(e) >= 0
        }, getDataVariationKey: function (e) {
            var t = {};
            return t[n.DUE_IN_DAYS] = n.DUE_IN_DAYS, t[n.DUE_TODAY] = n.DUE_TODAY, t[n.LATE] = n.LATE, t[n.PAST_DUE] = n.PAST_DUE, t[n.PAST_DUE_1_TO_30_DAYS_OPEN] = n.PAST_DUE_1_TO_30_DAYS_OPEN, t[n.PAST_DUE_31_TO_60_DAYS_OPEN] = n.PAST_DUE_31_TO_60_DAYS_OPEN, t[n.PAST_DUE_1_TO_60_DAYS_CLOSED] = n.PAST_DUE_1_TO_60_DAYS_CLOSED, t[n.PAST_DUE_61_TO_120_DAYS_CLOSED] = n.PAST_DUE_61_TO_120_DAYS_CLOSED, t[n.PAST_DUE_121_TO_210_DAYS_CLOSED] = n.PAST_DUE_121_TO_210_DAYS_CLOSED, t[n.AFTER_DUE_BEFORE_CYCLE] = n.AFTER_DUE_BEFORE_CYCLE, !!t[e] && t[e]
        }, getMessageModel: function (e, n, i, o) {
            var r = n + "Header", a = n + "Advisory", s = n + "Message", c = n + "HeaderHelpMessage", l = e.spec.name,
                    u = {header: {}};
            u.header.dataAttribute = l + "." + r, u.header.message = t.dynamicSettings.get(e, r, i);
            var d = t.dynamicSettings.get(e, a, i);
            d && (u.advisory = {}, u.advisory.dataAttribute = l + "." + a, u.advisory.message = d);
            var m = t.dynamicSettings.get(e, s, i);
            m && (u.message = {}, u.message.dataAttribute = l + "." + s, u.message.message = m);
            var f = t.dynamicSettings.get(e, c, i);
            return f && (u.headerHelpMessage = {}, u.headerHelpMessage.dataAttribute = l + "." + c, u.headerHelpMessage.headerHelpMessage = f), u.ada = {}, u.ada.dataAttribute = l + ".importantLabelAda", u.dataAttribute = l, "ACCOUNT_DETAILS_HEADER" === u.dataAttribute ? u.ada.message = t.dynamicSettings.get(e, "importantAda") : u.ada.message = t.dynamicSettings.get(e, "importantLabelAda"), u.accountId = o, u.accountCommunicationHeaderHelpMessage = t.dynamicSettings.get(e, c, i), u.beginHelpMessageAda = t.dynamicSettings.get(e, "beginHelpMessageAda"), u.endHelpMessageAda = t.dynamicSettings.get(e, "endHelpMessageAda"), u.exitHelpMessageAda = t.dynamicSettings.get(e, "exitHelpMessageAda"), u.requestAccountCommunicationHeaderHelpMessageAda = t.dynamicSettings.get(e, "requestAccountCommunicationHeaderHelpMessageAda"), u
        }, getMessageModelData: function (e, t) {
            var n, i;
            if (e.statusCode && e.accountId && (i = this.getMessageModel(t, "accountCommunication", e.statusCode, e.accountId)), n = this.getMessageFlags(e.statusCode, t), i) for (var o in n) n.hasOwnProperty(o) && (i[o] = n[o]);
            return i
        }, getStandInModeMessages: function (e) {
            var i = {};
            return function (e) {
                var t = n.DETAIL_TYPE, i = {};
                return i[t.CHECKING] = !0, i[t.SAVINGS] = !0, i[t.MONEY_MARKET] = !0, i[t.PREPAID_LITE] = !0, i[t.CERTIFICATE_OF_DEPOSIT] = !0, i[t.INDIVIDUAL_RETIREMENT_ARRANGMENT] = !0, i[t.CONSUMER_CREDIT_CARD] = !0, i[t.PRIVATE_LABEL_CONSUMER] = !0, i[t.OVERDRAFT_LINE_OF_CREDIT] = !0, !!i[e] && i[e]
            }(e.model.get("detailType")) && (i.addHeaderErrorMessage = !0, i.previousDayMessage = !0, e.model.set("previousDayMessage", !0), i.accountCommunicationHeader = t.dynamicSettings.get(e, "accountCommunicationHeader", "PREVIOUS_DAY_BALANCE"), i.importantAda = t.dynamicSettings.get(e, "importantAda")), e.context.application.broadcast("siteMessageFocus"), i
        }
    }
})),define("common/lib/mockServiceHelper", ["require", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/util");

    function n() {
        return "undefined" != typeof mock
    }

    function i(e) {
        return n() && (i = t.object.get(mock, "url")), i + e;
        var i
    }

    return {
        getMockUrl: i, getServiceCalls: function (e, o) {
            var r = {};
            return n() && e.forEach((function (e) {
                r[e.method] = function (e, n) {
                    var o = e.settings || {};
                    return {
                        settings: t.object.merge({
                            url: i(e.url),
                            xhrFields: {withCredentials: !1},
                            statusCodeField: "statusCode",
                            handleServerSideValidation: n
                        }, o)
                    }
                }(e, o)
            })), r
        }, isMocked: function (e) {
            return n() && !!mock && mock.enableMock && mock.mocks[e]
        }, mockExists: n
    }
})),define("common/lib/modal", ["require", "blue/$"], (function (e) {
    "use strict";
    var t, n = e("blue/$");
    return {
        show: function (e) {
            t = n(":focus"), e.attr("tabindex", "0").attr("aria-hidden", "false").addClass("ie-visible"), e.focus(), n(e).find("h1").attr("tabindex", "-1").focus(), n("#main").attr("aria-hidden", "true"), this.restrictFocus(e)
        }, hide: function (e) {
            n(e).find("h1").attr("tabindex", "-1"), e.attr("tabindex", "-1").attr("aria-hidden", "true").removeClass("ie-visible"), n("#main").attr("aria-hidden", "false"), t.focus()
        }, restrictFocus: function (e) {
            e.on("keydown", (function (t) {
                var i = e.find("a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, *[tabindex], *[contenteditable]").filter('*[tabindex != "-1"]:visible'),
                        o = n(":focus"), r = i.length, a = i.index(o);
                9 !== t.which || t.shiftKey ? 9 === t.which && t.shiftKey && 0 === a && (i.get(r - 1).focus(), t.preventDefault()) : a === r - 1 && (i.get(0).focus(), t.preventDefault())
            }))
        }
    }
})),define("common/lib/modalApi", ["require", "blue/is", "common/lib/focusUtil", "blue/$", "blue/util", "blue/declare", "blue/observable", "blue/observable", "blue-ui/template/modules/modal", "blue-ui/view/elements/icon", "blue-ui/view/elements/accessible", "blue-ui/view/elements/button", "blue-ui/template/modules/partials/modal", "blue-ui/view/elements/button", "common/lib/modal/restrictTabbing", "appkit-utilities/content/dcu"], (function (e) {
    "use strict";
    String.prototype.lcFirst = function () {
        return this.replace(this.charAt(0), this.charAt(0).toLowerCase())
    }, String.prototype.ucFirst = function () {
        return this.replace(this.charAt(0), this.charAt(0).toUpperCase())
    }, String.prototype.toCamelCase = function (e) {
        return this.toSnakeCase(e).lcFirst()
    }, String.prototype.toSnakeCase = function (e) {
        var t = "";
        return this.split(e).forEach((function (e) {
            t += e.toLowerCase().ucFirst()
        })), t
    };
    var t = e("blue/is"), n = e("common/lib/focusUtil"), i = e("blue/$"), o = e("blue/util").lang.defaults;
    return e("blue/declare")({
        constructor: function (r) {
            var a, s, c = {isDirty: {flag: !1, id: null, newModal: !1}, tgtData: null}, l = r, u = null,
                    d = function () {
                        return c.isDirty
                    }, m = function (e, t) {
                        if (c.isDirty = e, t = t || !1) try {
                            l.context.application.broadcast("trigger/setIsDirty", {data: e, value: ""})
                        } catch (e) {
                        }
                    }, f = function (e) {
                        Object.hasOwnProperty.call(e, "logName") && (l.context.logger.name = e.logName), l.context.logger[e.type || "info"](e.message)
                    }, p = function (e) {
                        var t, o = !1, r = {
                            init: function () {
                                (t = this).actionFunctions = {
                                    cancelFunction: function () {
                                    }, doNotCancelFunction: function () {
                                    }
                                }, t.setModalState = function (e, n) {
                                    t.model.lens("showModal").set("show" === e), t.output.emit("state", {
                                        data: {
                                            target: t,
                                            restartListener: n
                                        }, value: e + "CancelConfirmation"
                                    }), f({logName: "[modalApi]", message: "Component::init::" + e + "CancelConfirmation"})
                                }
                            }, showCancelConfirmation: function (e) {
                                f({
                                    logName: "[modalApi]",
                                    message: ["Component::showCancelConfirmation", t.model.lens("showModal").get(), e]
                                }), t.actionFunctions = e, t.elemToFocusAfterModalClose = e.elemToFocusAfterModalClose, t.focusIds = e.focusIds, t.setModalState("show", null), o = !1
                            }
                        };
                        return r["confirm" + (e = e.replace("_confirmation", "").toSnakeCase("_"))] = function () {
                            var e;
                            o || (o = !0, f({
                                logName: "[modalApi]",
                                message: ["Component::cancel", t.actionFunctions]
                            }), t.setModalState("hide", !1), t.actionFunctions.cancelFunction(), void 0 !== t.focusIds && ("string" == typeof t.focusIds.cancel ? (e = i("#" + t.focusIds.cancel), n.setFocus(i, e)) : (e = i(t.focusIds.cancel), n.setFocus(i, e)), f({
                                logName: "[modalApi]",
                                message: ["Component::focus", t.focusIds.cancel]
                            })))
                        }, r["doNot" + e] = function () {
                            var e;
                            if (!o) if (o = !0, f({
                                logName: "[modalApi]",
                                message: ["Component::doNotCancel", t.actionFunctions]
                            }), t.setModalState("hide", !0), t.actionFunctions.doNotCancelFunction(), void 0 !== t.focusIds) "string" == typeof t.focusIds.doNotCancel ? (e = i("#" + t.focusIds.doNotCancel), n.setFocus(i, e)) : (e = i(t.focusIds.doNotCancel), n.setFocus(i, e)), f({
                                logName: "[modalApi]",
                                message: ["Component::focus", t.focusIds.doNotCancel]
                            }); else if (t.elemToFocusAfterModalClose) {
                                var r, a, s = function (e) {
                                    return e ? "a[href],area[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),button:not([disabled]),iframe,object,embed,*[tabindex],*[contenteditable]".split(",").some((function (t) {
                                        return e.matches = e.matches || e.msMatchesSelector || e.matchesSelector || e.mozMatchesSelector || e.oMatchesSelector || e.webkitMatchesSelector, e.matches(t)
                                    })) : (f({
                                        logName: "[modalApi]",
                                        message: "Component::doNotCancel/isFocusable node not found"
                                    }), !1)
                                };
                                a = "string" == typeof t.elemToFocusAfterModalClose ? i(t.elemToFocusAfterModalClose)[0] : t.elemToFocusAfterModalClose;
                                var c = 0;
                                do {
                                    (r = s(a)) || (a = a.parentNode), c++
                                } while (!r && c < 20);
                                a.focus()
                            }
                        }, f({logName: "[modalApi]", message: ["component", r]}), r
                    }, g = function (e) {
                        var t = {
                            name: e.toUpperCase(),
                            data: {
                                showModal: {type: "OnOff"},
                                transactionAmount: {type: "Money"},
                                transactionInitiationDate: {type: "Date"},
                                transactionDueDate: {type: "Date"},
                                payeeName: {type: "Description"},
                                productId: {type: "Description"},
                                transferToAccountName: {type: "Description"},
                                memo: {type: "Description"},
                                cancellationReasonOptions: {
                                    type: "List",
                                    items: {cancellationReason: "Description", cancellationReasonId: "Description"}
                                },
                                cancellationReasonId: {type: "Description"},
                                cancellationReasonOptionsDisplayedState: {type: "OnOff"}
                            },
                            actions: {},
                            settings: {
                                cancellationReasonOptionsLabel: !0,
                                cancellationReasonOptionsPlaceholder: !0,
                                cancellationReasonOptionsMessage: !0,
                                cancellationReasonOptionsAdvisory: !0,
                                importantLabel: !0,
                                optionalLabel: !0,
                                memoLabel: !0,
                                memoAdvisory: !0,
                                onlinePaymentAdvisory: !0,
                                creditAccountPaymentMailOptionHeader: !0,
                                overnightMailPaymentAdvisory: !0,
                                regularMailPaymentAdvisory: !0,
                                chaseCardServicesOvernightMailingAddress: !0,
                                chaseCardServicesRegularMailingAddress: !0
                            }
                        };
                        return e = e.replace("_confirmation", "").toSnakeCase("_"), t.actions["confirm" + e] = !0, t.actions["doNot" + e] = !0, t.settings["confirm" + e + "Label"] = !0, t.settings["doNot" + e + "Label"] = !0, e = e.lcFirst(), t.settings[e + "ConfirmationHeader"] = !0, t.settings[e + "ConfirmationMessage"] = !0, t.settings[e + "ConfirmationAdvisory"] = !0, f({
                            logName: "[modalApi]",
                            message: ["spec", t]
                        }), t
                    }, y = function (t) {
                        return function () {
                            this.init = function () {
                                this.bridge = h(t), this.template = e("blue-ui/template/modules/modal"), this.views = {
                                    blueIcon: e("blue-ui/view/elements/icon"),
                                    blueAccessible: e("blue-ui/view/elements/accessible"),
                                    blueButton: e("blue-ui/view/elements/button")
                                }, this.partials = {modalContent: e("blue-ui/template/modules/partials/modal")}
                            }.bind(this), this.onReady = function () {
                                var e = this.rtemplate.get(), t = this.rtemplate.nodes[e.id], n = t.querySelector(".dialog");
                                this.rtemplate.get("rendered") || (n.clientHeight > t.clientHeight && n.classList.remove("vertical-center"), this.rtemplate.set("rendered", !0))
                            }.bind(this)
                        }
                    }, h = function (e) {
                        var t = {name: "modal", bindings: {}, triggers: {}};
                        return e = e.toLowerCase().replace("_confirmation", "").toSnakeCase("_"), t.triggers["confirm" + e] = {action: "confirm" + e}, t.triggers["doNot" + e] = {action: "doNot" + e}, f({
                            logName: "[modalApi]",
                            message: ["subViewWebSpec", t]
                        }), t
                    };
            return {
                getIsDirty: function () {
                    return d().flag
                }, getNewModalImpl: function () {
                    return d().newModal
                }, getDirtyId: function () {
                    return d().id
                }, getDirtyData: function () {
                    return d()
                }, getTgtData: function () {
                    return c.tgtData
                }, removeListener: function () {
                    l.components.listenerComponent && (l.components.listenerComponent.stopCancelListener(), l.components.listenerComponent.destroy())
                }, resetCancelData: function () {
                    c.tgtData = null, m({flag: !1, id: "", newModal: !1})
                }, setIsDirty: m, startListener: function (t) {
                    !function () {
                        f({
                            logName: "[modalApi]",
                            message: "createListener::remove all listeners..."
                        }), l.context.parent.children.forEach((function (e) {
                            e.controllerName && e.components && Object.hasOwnProperty.call(e.components, "listenerComponent") && (e.components.listenerComponent.stopCancelListener(), e.components.listenerComponent.destroy())
                        })), f({logName: "[modalApi]", message: "createListener::create listener..."});
                        l.register.components(l, [{
                            name: "listenerComponent",
                            model: e("blue/observable").Model({}),
                            spec: {name: "LISTENER", data: {}, actions: {}, settings: {}},
                            methods: {
                                init: function () {
                                    this.started = !1, this.startCancelListener = function (e) {
                                        this.started || this.output.emit("state", {
                                            data: e,
                                            value: "startCancelListener"
                                        }), this.started = !0
                                    }, this.stopCancelListener = function () {
                                        this.output.emit("state", {value: "stopCancelListener"}), this.started = !1
                                    }
                                }
                            }
                        }]), l.executeCAV([l.components.listenerComponent, function () {
                            this.bridge = {
                                name: "LISTENER",
                                bindings: {},
                                triggers: {}
                            }, this.handlers = {}, this.selectors = "", this.init = function () {
                                f({logName: "[modalApi]", message: "listenerView::init"});
                                var e = this;
                                e.bridge.on("state/startCancelListener", (function (t) {
                                    for (var n in t = t.data || {}, f({
                                        logName: "[modalApi]",
                                        message: "listenerView::start listeners"
                                    }), Object.prototype.hasOwnProperty.call(t, "handlers") && (e.handlers = t.handlers), Object.prototype.hasOwnProperty.call(t, "selectors") && (e.selectors = " " + t.selectors), e.handlers) Object.hasOwnProperty.call(e.handlers, n) && (f({
                                        logName: "[modalApi]",
                                        message: "listenerView::startCancelListener::setting " + n + " event for #dashboard-content " + e.selectors
                                    }), i("#dashboard-content" + e.selectors).unbind(n).bind(n, e.handlers[n]))
                                })), e.bridge.on("state/stopCancelListener", (function () {
                                    for (var t in e.handlers) Object.hasOwnProperty.call(e.handlers, t) && (f({
                                        logName: "[modalApi]",
                                        message: "listenerView::stopCancelListener::unsetting " + t + " event for #dashboard-content " + e.selectors
                                    }), i("#dashboard-content" + e.selectors).unbind(t))
                                }))
                            }
                        }, {append: !1, target: "#listener"}])
                    }(), l.components.listenerComponent.startCancelListener(t)
                }, stopListener: function () {
                    l.components.listenerComponent && l.components.listenerComponent.stopCancelListener()
                }, setTgtData: function (e, n) {
                    c.tgtData = e, (t.null(n) || t.empty(n) || n) && l.context.application.broadcast(d().id + "/setTgtData", {
                        data: e,
                        value: ""
                    })
                }, hideCancelConfirmationOverlay: function () {
                    a.doNotExit()
                }, showCancelConfirmation: function (t, r, c, d, m) {
                    m = o(m, {});
                    var h = o(m.objs, {});
                    h && h.spec && h.spec.data && !h.spec.data.showModal && (h.spec.data.showModal = {type: "OnOff"});
                    var v = [], b = o(h.templatePath, "dashboard/template/" + r + t.toCamelCase("_"));
                    var A = t.toCamelCase("_") + "Component", E = function (n) {
                        s = n;
                        var i = function (t) {
                            var n = l.model.get && l.model.get() || {};
                            return e("blue/observable").Model(Object.hasOwnProperty.call(n, t) && Object.hasOwnProperty.call(n[t], "showModal") ? n[t] : {showModal: !1})
                        }(A);
                        if (Object.hasOwnProperty.call(m, "modelProperties")) for (var r in m.modelProperties) Object.hasOwnProperty.call(m.modelProperties[r], "name") && Object.hasOwnProperty.call(m.modelProperties[r], "value") && i.set(m.modelProperties[r].name, m.modelProperties[r].value);
                        l.registry.registerComponent(A, {
                            spec: o(h.spec, g(t)),
                            model: i,
                            methods: o(h.component, p(t))
                        }, !0), a = l.components[A], Object.hasOwnProperty.call(m, "dynamicContent") && (u = o(u, e("appkit-utilities/content/dcu")), Object.hasOwnProperty.call(m.dynamicContent, "settings") ? function () {
                            for (var e in m.dynamicContent.settings) if (!isNaN(parseFloat(e)) && isFinite(e)) {
                                var t = {};
                                m.dynamicContent.settings[e].replaceObjs && (t = m.dynamicContent.settings[e].replaceObjs), Object.hasOwnProperty.call(m.dynamicContent.settings[e], "variant") ? u.dynamicSettings.set(l.components[A], m.dynamicContent.settings[e].setting, m.dynamicContent.settings[e].variant, t) : (v = Object.keys(t), "object" == typeof t[v[0]] ? v.forEach((function (n) {
                                    u.dynamicSettings.set(l.components[A], m.dynamicContent.settings[e].setting, n, t[n])
                                })) : u.dynamicSettings.set(l.components[A], m.dynamicContent.settings[e].setting, t))
                            }
                        }() : Object.hasOwnProperty.call(m.dynamicContent, "variant") ? u.dynamicSettings.set(l.components[A], m.dynamicContent.setting, m.dynamicContent.variant) : (v = Object.keys(m.dynamicContent.replaceObjs), "object" == typeof m.dynamicContent.replaceObjs[v[0]] ? v.forEach((function (e) {
                            u.dynamicSettings.set(l.components[A], m.dynamicContent.setting, e, m.dynamicContent.replaceObjs[e])
                        })) : u.dynamicSettings.set(l.components[A], m.dynamicContent.setting, m.dynamicContent.replaceObjs))), f({
                            logName: "[modalApi]",
                            message: ["createModal::after register..."]
                        }), a.model.set("showModal", !0), l.executeCAV([a, h.view || T(t, ".overlay"), {
                            append: !1,
                            target: ".overlay"
                        }]).then(function () {
                            f({
                                logName: "[modalApi]",
                                message: "createModal::execCAV complete"
                            }), a.showCancelConfirmation && a.showCancelConfirmation(c), h && h.cavCallback && "function" == typeof h.cavCallback && h.cavCallback.call(this)
                        }.bind(this))
                    }, T = function (t, o) {
                        return o = o || ".overlay", function () {
                            var r = this;
                            this.viewName = t.toCamelCase("_"), this.template = s, this.views = {
                                blueModal: y(t),
                                blueButton: e("blue-ui/view/elements/button")
                            }, this.decorators = {restrictTabbing: e("common/lib/modal/restrictTabbing")(this)}, this.init = function () {
                                r.bridge = h.webSpec || function (e) {
                                    var t = {
                                        name: e.toUpperCase(),
                                        bindings: {
                                            showModal: {direction: "BOTH"},
                                            transactionAmount: {direction: "BOTH"},
                                            transactionInitiationDate: {direction: "BOTH"},
                                            transactionDueDate: {direction: "BOTH"},
                                            payeeName: {direction: "BOTH"},
                                            productId: {direction: "BOTH"},
                                            transferToAccountName: {direction: "BOTH"},
                                            memo: {direction: "BOTH"},
                                            cancellationReasonOptions: {direction: "BOTH"},
                                            cancellationReasonId: {direction: "BOTH"},
                                            cancellationReasonOptionsDisplayedState: {direction: "BOTH"}
                                        },
                                        triggers: {}
                                    };
                                    return e = e.toLowerCase().replace("_confirmation", "").toSnakeCase("_"), t.triggers["confirm" + e] = {action: "confirm" + e}, t.triggers["doNot" + e] = {action: "doNot" + e}, f({
                                        logName: "[modalApi]",
                                        message: ["webSpec", t]
                                    }), t
                                }(t), r.bridge.on("state/showCancelConfirmation", (function () {
                                    f({
                                        logName: "[modalApi]",
                                        message: ["View::state/showConfirmation", o, r]
                                    }), r.controller && r.controller.components && r.controller.components.listenerComponent && r.controller.components.listenerComponent.stopCancelListener(), i(".overlay").show(), i(".jpui.progress.bar.animate").remove(), i(".success.animate.alert").removeClass("animate"), i(o).attr("aria-hidden", "false"), n.setFocus(i, o + " .modal-body h1.dialogTitle", 400), i("#dashboard-content").attr("aria-hidden", "true")
                                })), r.bridge.on("state/hideCancelConfirmation", (function (e) {
                                    f({
                                        logName: "[modalApi]",
                                        message: ["View::state/showConfirmation", e]
                                    }), r.model.showModal = !1, e.data.restartListener && r.controller && r.controller.components && r.controller.components.listenerComponent && r.controller.components.listenerComponent.startCancelListener(), i("#dashboard-content").attr("aria-hidden", "false"), i(o).attr("aria-hidden", "true")
                                })), f({logName: "[modalApi]", message: ["view", this]})
                            }, this.onReady = function () {
                                f({logName: "[modalApi]", message: "view onReady fired"})
                            }
                        }
                    };
                    try {
                        Object.hasOwnProperty.call(h, "template") ? E(h.template) : e([b], (function (e) {
                            E(e)
                        }))
                    } catch (e) {
                        f({logName: "[modalApi]", message: ["showCancelConfirmation caught", e]})
                    }
                }
            }
        }
    })
})),define("common/lib/modelUtil", ["blue/util", "require"], (function (e, t) {
    "use strict";
    var n = ["data", "states", "actions", "settings"];

    function i(e, t) {
        return e.reduce((function (e, n) {
            return e[n] = t, e
        }), {})
    }

    function o(e, n, i) {
        this.controller = e, this.componentKey = n, this._spec = t("bluespec/" + i), this._internalOverride = !1
    }

    return o.enrichComponent = function (e, t, n) {
        return new o(e, t, n)
    }, Object.assign(o.prototype, {
        _updateSection: function (e, t, i) {
            var o = this.controller;
            if (o.registry.hasComponent(this.componentKey)) throw new Error("Cannot enrich existing component; utility must be invoked before any direct component references");
            if (-1 === n.indexOf(e)) throw new Error(e + " is not a valid blue spec section");
            return Object.keys(t).forEach((function (n) {
                if ("$" !== n[0]) throw new Error("Enriched spec fields must be prefixed with $ to identify their usage in code.");
                this._spec.hasOwnProperty(e) || (this._spec[e] = {});
                var r = n.split("$").pop();
                this._spec[e].hasOwnProperty(r) && o.context.logger.warn("WARNING: " + e + " key " + n + " already exists on spec " + this._spec.name + "! In the future this will throw an error."), this._spec[e][n] = i instanceof Function ? i(t[n]) : t[n]
            }), this), this
        }, _apply: function () {
            if (this._internalOverride) return this;
            this.controller.registry.updateComponent(this.componentKey, {
                model: this.controller.model.lens(this.componentKey),
                spec: this._spec
            })
        }, data: function (e) {
            return this._updateSection("data", e, (function (e) {
                return "string" == typeof e ? {type: e, isBuiltInProperty: !0} : e
            }))._apply()
        }, states: function (e) {
            var t = e instanceof Array ? i(e, !0) : e;
            return this._updateSection("states", t)._apply()
        }, actions: function (e) {
            var t = e instanceof Array ? i(e, !0) : e;
            return this._updateSection("actions", t)._apply()
        }, spec: function (e) {
            this._internalOverride = !0, this.data(e.data || {}).states(e.states || {}).actions(e.actions || {}), this._internalOverride = !1, this._apply()
        }
    }), {
        lensAllComponents: function (e) {
            Object.keys(e.components).forEach((function (t) {
                e.registry.hasComponent(t) || e.registry.updateComponent(t, {model: e.model.lens(t)})
            }))
        },
        enrichComponent: o.enrichComponent,
        DataType: {Numbers: "Numbers", Description: "Description", OnOff: "OnOff", List: "List"}
    }
})),define("common/lib/mspUrlConfig", {
    detailType: {HLM: "HEL", RCM: "RCA", HEM: "HEO", ILM: "ILA"}, urlObj: {
        "/svc/rr/payments/secure/v1/billpay/merchantmultipayment/payee/list": {
            properties: ["accountType"],
            hasArray: !0
        },
        "/svc/rr/accounts/secure/v2/account/detail/vls/list": {properties: ["detail.detailType"]},
        "/svc/rr/accounts/secure/v4/dashboard/tiles/list": {properties: ["accountTileDetailType"], hasArray: !0},
        "/svc/rr/accounts/secure/v1/dashboard/overview/accounts/list": {properties: ["detailType"], hasArray: !0},
        "/svc/rl/accounts/secure/v1/app/data/list": {properties: ["accountTileDetailType"], hasArray: !0},
        "/svc/rr/accounts/secure/v2/dashboard/accounts/summary/print/list": {properties: ["detailType"], hasArray: !0},
        "/svc/rr/accounts/secure/v1/account/activity/download/options/list": {properties: ["detailType"], hasArray: !0},
        "/svc/rr/accounts/secure/v2/account/detail/hel/full/list": {properties: ["detail.detailType"]},
        "/svc/rr/accounts/secure/v2/account/detail/rca/full/list": {properties: ["detail.detailType"]},
        "/svc/rr/accounts/secure/v2/account/detail/heo/full/list": {properties: ["detail.detailType"]},
        "/svc/rr/accounts/secure/v2/account/detail/ila/full/list": {properties: ["detail.detailType"]},
        "/svc/rr/accounts/secure/v2/account/detail/card/list": {properties: ["detail.detailType"]},
        "/svc/rr/accounts/secure/v1/account/detail/inv/list": {properties: ["detailType"], hasArray: !0},
        "/svc/rl/accounts/secure/v1/user/metadata/list": {properties: ["accountTypes"], hasArray: !0},
        "/svc/rr/payments/secure/v1/billpay/mortgage/payment/list": {properties: ["accountType"]},
        "/svc/rr/payments/secure/v1/billpay/mortgage/autopayment/list": {properties: ["accountType"]},
        "/svc/rr/payments/secure/v1/billpay/personalloan/payment/activity/list": {properties: ["accountType"]},
        "/svc/rr/payments/secure/v1/billpay/personalloan/autopayment/list": {properties: ["accountType"]},
        "/svc/rr/documents/secure/v1/menu/list": {properties: ["type"], hasArray: !0},
        "/svc/rr/payments/secure/v2/billpay/multi/payment/add/options": {properties: ["accountType"]}
    }
}),define("common/lib/odsActivity/activityFeatureFlagUtil", ["require", "common/lib/constants"], (function (e) {
    "use strict";
    var t = {CARD: "CARD"}, n = e("common/lib/constants");
    return function () {
        return {
            isOdsActivityEligibleType: function (e, i, o) {
                return e && !!e.digitalStatementsActivityFlag && function (e, i, o) {
                    var r = o[n.DIGITAL_STATEMENT_FEATURE_FLAGS[i]];
                    return !!t[e] && r
                }(i && i.toUpperCase(), o, e)
            }, getServiceType: function (e, t, n, i) {
                return {DDA: this.isFeatureFlagEnabled(e, t, n), CARD: this.isOdsActivityEligibleType(e, n, i)}
            }, isFeatureFlagEnabled: function (e, t, i) {
                return !!e[n.ODS_SERVICE_FEATURE_FLAGS[t][i]]
            }
        }
    }
})),define("common/lib/payments/configureView", ["require", "blue/is", "blue/util", "blue/object/extend", "blue/log"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("blue/util").lang.defaults, i = e("blue/object/extend");
    return function (o, r) {
        var a = r.viewConfig;
        if (a) {
            var s = n(o.model, {});
            o.areaName = n(o.areaName, a.areaName), s.areaName = n(s.areaName, a.areaName), o.template = n(o.template, a.template), o.bridge = n(o.bridge, a.bridge), o.views = n(o.views, a.views), o.partials = n(o.partials, a.partials), o.decorators = n(o.decorators, a.decorators), o.logger = e("blue/log")("[" + r.name + "View]"), (0, t.object)(a.helpers) && i(s, a.helpers), o.model = s
        }
    }
})),define("common/lib/payments/controllerUtilsExt", ["require", "blue/util", "blue-view/nodeDictionary", "common/lib/externalCallApi", "common/lib/componentUtils"], (function (e) {
    "use strict";
    var t = e("blue/util"), n = t.lang.defaults, i = e("blue-view/nodeDictionary");
    return function (o, r) {
        function a() {
            var i = Array.prototype.slice.call(arguments), a = i.shift(),
                    s = n("object" == typeof i[0] && i.shift(), {}), c = i, l = r[a];
            if (!l) return Promise.reject(new TypeError(a + " is not configured"));
            var u = n(s.data, l.data);
            u && u.get && o.context.logger.error("model was provided to controllerUtilsExt where data (plain object) was expected. Continuing with error. componentKey", a);
            var d = n(s.spec, l.spec, {}), m = n(s.methods, l.methods, {}),
                    f = s.model || s.data && t.object.merge(s.data, {}) || l.data && t.object.merge(l.data, {}) || o.model && o.model.lens(),
                    p = n(s.name, a);
            if (o.registry.isComponent(p)) return f && o.registry.updateComponent(p, {model: f}), Promise.all([o.registry.getComponent(p)]);
            o.registry.registerComponent(p, {spec: d, methods: m, model: f, legacy: !0});
            var g, y, h = o.registry.getComponent(p);
            return g = h, y = new (e("common/lib/externalCallApi"))(o), Object.keys(y).forEach((function (e) {
                g[e] = y[e]
            })), h = g, Promise.all((c || []).map((function (e) {
                return new Promise((function (t) {
                    t(e.call(h))
                }))
            }))).then((function (e) {
                return e.unshift(h), e
            }))
        }

        return {
            isComponentAndViewLoaded: function (e, t) {
                var n = r[e], a = t && t.target || n && n.target || "#content", s = i.getViewAt(a);
                return !!s && (s = Array.isArray(s) ? s : [s], Boolean(n && (s || []).reduce((function (t, i) {
                    return !!t || i && i.componentName === e && o.registry.isComponent(e) && ("string" == typeof n.view || i.viewName === n.view.name || "anonymous" === i.viewName && !n.view.name)
                }), !1)))
            }, isComponentAndViewLoadedArea: function (e) {
                if (o.registry.isComponent(e) && o.registry.getComponent(e).__hasView) return Promise.resolve(o);
                var t = o.context.parent.controllers || {};
                return Promise.all(Object.keys(t).map((function (e) {
                    return t[e]
                }))).then((function (t) {
                    for (var n = 0; n < t.length; n++) if (t[n].registry.isComponent(e) && o.registry.getComponent(e).__hasView) return Promise.resolve(t[n]);
                    return Promise.reject(e)
                }))
            }, registerComponent: a, executeComponentAndView: function () {
                var e = Array.prototype.slice.call(arguments), t = e.shift(),
                        n = "object" == typeof e[0] && e.shift() || {}, s = e, c = r[t];
                if (!c) return Promise.reject(new TypeError(t + " is not configured"));
                var l = n.target || c.target || "#content", u = n.append || c.append || !1, d = n.view || c.view,
                        m = n.name || t;
                return a(t, n).then((function (e) {
                    var t = o.registry.getComponent(m);
                    if (o.registry.hasComponent(m) && t.__hasView) return e;
                    if (d && "string" != typeof d) {
                        var n = t && t.context, r = n && n.area, a = r && r.name;
                        d.areaName = d.areaName || a
                    }
                    return o.context.executeCAV([[t, d, {target: l, append: u}]]).then((function () {
                        var e = i.getViewAt(l);
                        e && (e.componentName = m)
                    })).then((function () {
                        return Promise.all((s || []).map((function (e) {
                            return new Promise((function (n) {
                                n(e.call(t))
                            }))
                        })))
                    })).then((function (e) {
                        return e.unshift(t), e
                    }))
                }))
            }, destroyComponentAndView: function (e) {
                return new Promise((function (t, n) {
                    try {
                        t(o.registry.destroyComponent(e))
                    } catch (e) {
                        n(e)
                    }
                }))
            }, destroyAllComponents: function (e) {
                var t = this;
                return Promise.all(Object.keys(o.components || {}).map((function (n) {
                    var i;
                    return -1 === (e || []).indexOf(n) && (i = t.destroyComponentAndView(n)), i
                })))
            }, displayError: function (t, n, i, r, a) {
                var s = this;
                return i = i || n + "ErrorHeader", r = r || n + "ErrorAdvisory", a = [n].concat(a || []), s.destroyAllComponents(a).then((function () {
                    return s.registerComponent(n, (function () {
                        e("common/lib/componentUtils")(this)
                    }))
                })).then((function () {
                    return s.executeComponentAndView(n)
                })).then((function (e) {
                    if ("SYSTEM_FAILURE" === e[0].displayError(i, r, t)) throw t
                })).catch((function (e) {
                    throw o.context.logger.warn("Exception while calling displayError", n, i, r, e), t
                }))
            }, loadModules: function (e, t) {
                if (!e) throw new TypeError("Missing modules config");
                return Promise.all(Object.keys(e).filter((function (n) {
                    return !!e[n].preauth == !!t
                })).map((function (t) {
                    return new Promise((function (n) {
                        var i, r, a = e[t];
                        o.context.privateState(a.path, (i = o.context.util.object.merge({target: a.target}, a.params), r = o.context.util.object.toString, Object.keys(i || {}).reduce((function (e, t) {
                            return e[t] = r(i[t]), e
                        }), {}))), n(t)
                    }))
                })))
            }
        }
    }
})),define("common/lib/selectorUtil", [], (function () {
    "use strict";
    var e = {
        blueFieldGroup: {
            dropdown: {prefix: "header-", suffix: "-styledselect"},
            input: {prefix: "", suffix: "-text-input-field"},
            inputValidate: {prefix: "", suffix: "-text-validate-input-field"}
        },
        blueStyledselect: {dropdown: {prefix: "header-", suffix: ""}},
        inputSelect: {dropdown: {prefix: "header-", suffix: ""}},
        blueButton: {prefix: "", suffix: ""},
        blueRadioButton: {prefix: "input-", suffix: ""}
    };

    function t(t, n, i) {
        var o = e[n] && (e[n][i] || e[n]);
        return o ? o.prefix + t + o.suffix : ""
    }

    return {
        getId: t, getIdSelector: function (e, n, i) {
            return "#" + t(e, n, i)
        }
    }
})),define("common/lib/viewFormatUtil", ["require", "moment-timezone", "appkit-utilities/content/dcu", "common/lib/utility/formatDateUtility", "common/lib/contentUtil", "appkit-utilities/formatters/number", "common/lib/momentTimeZoneData"], (function (e) {
    "use strict";
    var t, n, i = e("moment-timezone"), o = e("appkit-utilities/content/dcu"),
            r = e("common/lib/utility/formatDateUtility"), a = e("common/lib/contentUtil"),
            s = e("appkit-utilities/formatters/number");

    function c(e, i) {
        if (t || (n = a.getList("GLOBAL.monthAbbreviation", "monthId", "monthName", "app"), t = n.reduce((function (e, t) {
            return e[t.monthId.toUpperCase()] = t.monthName, e
        }), {})), n[0] && n[0].monthName && -1 !== ["CQNC", "CONTENT"].indexOf(n[0].monthName.toUpperCase())) return e;
        var r = e.split(" ")[0];
        return e = e.replace(r, t[r.toUpperCase()]), i && (e += " " + o.dynamicContent.getGlobal("timeZoneAbbreviation", {}, "EASTERN_TIME")), e
    }

    function l(e, t) {
        return isNaN(e) ? e || "" : s.currencyFormatter(e, {decimalPlaces: isNaN(t) ? 2 : t, dollarSign: !0})
    }

    return i.tz.add(e("common/lib/momentTimeZoneData")["America/New_York"]), {
        formatDateUtility: r, getRandomInt: function (e) {
            return Math.floor(Math.random() * Math.floor(e))
        }, dynamicContent: function (e, t) {
            return e && t && Object.keys(t).filter((function (e) {
                return t.hasOwnProperty(e)
            })).forEach((function (n) {
                e = e.replace(new RegExp("{{" + n + "}}", "g"), t[n])
            })), e
        }, formatISOTime: function (e) {
            var t = i(e, i.ISO_8601);
            t = i.tz && i.tz(t, "America/New_York");
            var n = o.dynamicContent.getGlobal("timeZoneAbbreviation", "EASTERN_TIME");
            return t && t.format("MM/DD/YYYY hh:mm:ss A") + " " + n
        }, formatDate: function (e, t) {
            return e && i(e, "YYYYMMDD").format(t || "l")
        }, formatDateWithContent: function (e, t, n) {
            var o = (n ? i.tz(e, "MM/DD/YYYY", "America/New_York") : i(e, "MM/DD/YYYY")).format(t || "ll");
            return "Invalid date" === o && "Invalid date" === (o = n ? i.tz(e, "YYYYMMDD", "America/New_York").format(t || "ll") : i(e, "YYYYMMDD").format(t || "ll")) ? String(e || "") : c(o, n)
        }, formatDateAndTime: function (e) {
            return e && i(e).format("MMM D, YYYY, h:mm:ss a")
        }, formatMoney: l, formatNumber: function (e, t) {
            return isNaN(e) ? e || "" : s.decimalFormatter(e, {decimalPlaces: isNaN(t) ? 2 : t, dollarSign: !1})
        }, maskValue: function (e, t, n, i) {
            return (e = e && String(e) || "").length ? function (e, t, n, i) {
                var o = "DATE" === t ? "**/**/**" : "***", r = "DATE" === t ? 2 : 4, a = e.substr(e.length - r);
                return n && (o = o.replace(/\*/g, n)), o += a, i && (o = "(" + o + ")"), o
            }(e, "DATE_OF_BIRTH" === t || 2 === e.length && "account" !== t ? "DATE" : "STR", n, i) : e
        }, addTrailingZeroes: function (e, t) {
            if (isNaN(Number(e))) return e;
            var n = String(e).split(".");
            return Array.isArray(n) && n.length > 1 && n[1].length > 4 ? e : (isNaN(t) && (t = 4), s.decimalFormatter(e, {decimalPlaces: t}))
        }, viewMoneyFormatter: function (e, t) {
            return isNaN(e) ? e : s.decimalFormatter(e, {decimalPlaces: isNaN(t) ? 2 : t})
        }, formatMoneyWithCurrencySymbol: function (e) {
            return ["", void 0].indexOf(e) > -1 ? "" : (e = parseFloat(e.toString().replace(/,/g, "")), s.currencyFormatter(e, {dollarSign: !0}))
        }, formatMoneySum: function (e, t) {
            return l(e.reduce((function (e, t) {
                return e + (t = Number(String(t).replace(/,/g, "")) || 0)
            }), 0), t)
        }
    }
})),define("common/lib/variation", ["require", "blue/util", "blue-app/settings", "common/lib/contentEvent"], (function (e) {
    "use strict";
    var t = e("blue/util").string.interpolate, n = e("blue-app/settings"), i = e("common/lib/contentEvent");
    return function (e, o, r, a, s) {
        var c = r || "";
        a && (c = r + "." + a);
        var l = function (e, t, i) {
            var o = n.get("LOCALIZED_CONTENT_app"),
                    r = n.get("LOCALIZED_CONTENT_" + e) || n.get("LOCALIZED_CONTENT_" + e.toLowerCase());
            return r && r[t] && r[t][i] || o && o[t] && o[t][i]
        }(e, o, c), u = l;
        return s && (u = t(l, s)), i.contentEvent.get({spec: {name: o}}, r, a), u
    }
})),define("bluespec/global", [], (function () {
    return {
        name: "GLOBAL", settings: {
            dateAda: !0,
            calendarHeaderAda: !0,
            calendarAdvisoryAda: !0,
            exitCalendarAda: !0,
            navigateAda: !0,
            monthAda: !0,
            dayOfWeekAda: !0,
            currentDateAda: !0,
            firstAvailableDateAda: !0,
            lastAvailableDateAda: !0,
            endOfMonthDateAda: !0,
            emulationModeLockedAda: !0,
            lossAda: !0,
            gainAda: !0,
            mayUpdateContentAda: !0,
            requestAdvisoryDetailsAda: !0,
            exitAdvisoryDetailsAda: !0,
            hasExpandedAda: !0,
            hasCollapsedAda: !0,
            requiredAda: !0,
            requestFilterToCalendarAda: !0,
            requestFilterFromCalendarAda: !0,
            searchAda: !0,
            searchSuggestionsAda: !0,
            clearSearchQueryAda: !0,
            showsContentBelowAda: !0,
            updatesContentBelowAda: !0,
            updatesContentAda: !0,
            updatesContentAboveAda: !0,
            selectionUpdatesContentBelowAda: !0,
            hidesContentBelowAda: !0,
            showsContentAboveAda: !0,
            hidesContentAboveAda: !0,
            contentLoadingAda: !0,
            contentLoadedAda: !0,
            showLinksBelowAda: !0,
            hideLinksBelowAda: !0,
            optionSelectedAda: !0,
            listOptionsNavigationAda: !0,
            optionsAda: !0,
            deleteAda: !0,
            selectedOptionsAda: !0,
            selectedAda: !0,
            unselectedAda: !0,
            currentSelectionAda: !0,
            endOfSelectionAda: !0,
            opensMenuAda: !0,
            closesMenuAda: !0,
            moreActionsAda: !0,
            maximumCharacterLimitReachedAda: !0,
            actionsAda: !0,
            beginHelpMessageAda: !0,
            endHelpMessageAda: !0,
            exitHelpMessageAda: !0,
            beginDialogAda: !0,
            endDialogAda: !0,
            exitDialogAda: !0,
            footnoteAda: !0,
            referrerAda: !0,
            errorAnnouncementAda: !0,
            errorCountAnnouncementAda: !0,
            checkmarkAda: !0,
            importantAda: !0,
            opensPdfAda: !0,
            opensNewWindowAda: !0,
            warningAda: !0,
            informationAda: !0,
            exitAda: !0,
            opensDialogAda: !0,
            opensInformationDialogAda: !0,
            waitAda: !0,
            overlayAnnouncementAda: !0,
            additionalItemsAda: !0,
            enrollConfirmationAdvisory: !0,
            oneTimePasswordMaxLimitErrorHeader: !0,
            commaSymbol: !0,
            periodSymbol: !0,
            hyphenSymbol: !0,
            asteriskSymbol: !0,
            atSymbol: !0,
            doubleHyphenSymbol: !0,
            currencySymbol: !0,
            minusSymbol: !0,
            plusSymbol: !0,
            openParenthesesSymbol: !0,
            closeParenthesesSymbol: !0,
            horizontalEllipsisSymbol: !0,
            upArrowSymbol: !0,
            downArrowSymbol: !0,
            forwardSlashSymbol: !0,
            leftChevronSymbol: !0,
            rightChevronSymbol: !0,
            colonSymbol: !0,
            percentageSymbol: !0,
            mathematicalSymbolPipeLabel: !0,
            mathematicalSymbolNumberSignLabel: !0,
            currencyDollarSymbol: !0,
            phoneNumberMaskSymbol: !0,
            backLabel: !0,
            cancelLabel: !0,
            nextLabel: !0,
            closeLabel: !0,
            submitLabel: !0,
            confirmLabel: !0,
            requestAdvisoryDetailsLabel: !0,
            exitAdvisoryDetailsLabel: !0,
            chasePersonalOnlineLabel: !0,
            jpMorganOnlineLabel: !0,
            onlineEnrollmentLabel: !0,
            commercialEnrollmentLabel: !0,
            disputeTransactionLabel: !0,
            reportProblemLabel: !0,
            californiaConsumerPrivacyActLabel: !0,
            forLabel: !0,
            childOnlinePrivacyProtectionActLabel: !0,
            progressBarStepPercentageCompleteAda: !0,
            month: !0,
            monthAbbreviation: !0,
            dayOfWeekSymbol: !0,
            dayOfWeek: !0,
            currencyName: !0,
            currencyAbbreviation: !0,
            emulationModeLabel: !0,
            dayOfMonth: !0,
            stateName: !0,
            provinceName: !0,
            militaryRegionName: !0,
            countryName: !0,
            postOfficeTypeName: !0,
            notAvailableLabel: !0,
            timeZoneAbbreviation: !0,
            noneLabel: !0,
            totalsLabel: !0,
            totalLabel: !0,
            fromLabel: !0,
            optionalLabel: !0,
            numbers: !0,
            monthsLabel: !0,
            dayLabel: !0,
            weekLabel: !0,
            quarterLabel: !0,
            yearLabel: !0,
            agoLabel: !0,
            vsLabel: !0,
            skipBackLabel: !0,
            relaxTableRowsLabel: !0,
            compactTableRowsLabel: !0,
            relaxTableRowsAda: !0,
            compactTableRowsAda: !0,
            informationDensityStatusAda: !0,
            transactionStatus: !0,
            transactionFrequency: !0,
            updatesTableAboveAda: !0,
            displayLanguageLabel: !0,
            copyrightLabel: !0,
            requestProfileSettingsMenuAda: !0,
            onLabel: !0,
            offLabel: !0,
            inProgressLabel: !0,
            atLabel: !0,
            pointsLabel: !0,
            pointsAbbreviationLabel: !0,
            ungroupedLabel: !0,
            thirdPartyApplicationName: !0,
            deviceChannelName: !0,
            printAda: !0,
            printLabel: !0,
            enDash: !0,
            emDash: !0,
            sortByAlphabeticalOrderAda: !0,
            sortByDateOrderAda: !0,
            sortByNumericalOrderAda: !0,
            sortByAmountOrderAda: !0,
            sortByDescriptionOrderAda: !0,
            sortByAccountNumberOrderAda: !0,
            sortByTransactionTypeOrderAda: !0,
            sortByAscendingAlphabeticalAda: !0,
            sortByDescendingAlphabeticalAda: !0,
            sortByLowestToHighestAda: !0,
            sortByHighestToLowestAda: !0,
            sortByAscendingOrderAda: !0,
            sortByDescendingOrderAda: !0,
            sortedByDateAscendingAda: !0,
            sortedByDateDescendingAda: !0,
            sortedByLowestToHighestAda: !0,
            sortedByHighestToLowestAda: !0,
            sortedByTextAscendingAda: !0,
            sortedByTextDescendingAda: !0,
            notSortedAda: !0,
            toLabel: !0,
            notApplicableLabel: !0,
            notApplicableAda: !0,
            applicableAda: !0,
            checksAllBelowAda: !0,
            unchecksAllBelowAda: !0,
            chaseCardAda: !0,
            doneLabel: !0,
            editLabel: !0,
            dateLabel: !0,
            okLabel: !0,
            orLabel: !0,
            skipBackToTopLabel: !0,
            verifyTaskLabel: !0,
            totalErrorsAda: !0,
            updateLabel: !0,
            saveLabel: !0,
            deleteLabel: !0,
            ofLabel: !0,
            accountLogoBrandingAda: !0,
            confirmationLabel: !0,
            formNotChangedErrorMessage: !0,
            beginEnglishOnlyHelpMessageAda: !0,
            endEnglishOnlyHelpMessageAda: !0,
            exitEnglishOnlyHelpMessageAda: !0,
            showsEnglishOnlyContentBelowAda: !0,
            hidesEnglishOnlyContentBelowAda: !0,
            englishOnlyRelaxTableRowsAda: !0,
            englishOnlyCompactTableRowsAda: !0,
            englishOnlyCurrentSelectionAda: !0,
            updatesEnglishOnlyContentBelowAda: !0,
            updatesEnglishOnlyContentAda: !0,
            opensEnglishOnlyMenuAda: !0,
            closesEnglishOnlyMenuAda: !0,
            endEnglishOnlyDialogAda: !0,
            exitEnglishOnlyDialogAda: !0,
            englishOnlyImportantAda: !0,
            englishOnlySortByAmountOrderAda: !0,
            businessCreditCardNicknameLabel: !0,
            featureTitle: !0,
            disableMicroBrowserLabel: !0,
            disableMicroBrowserAda: !0,
            searchQueryPlaceholder: !0,
            searchSuggestionsError: !0
        }
    }
})),define("common/lib/viewUtils", ["require", "blue/is", "common/lib/ada/setFocus", "common/lib/pageTitle", "blue-app/settings", "blue/$", "common/lib/selectorUtil", "blue/root", "appkit-utilities/common/mediaQueryListener", "common/lib/viewFormatUtil", "common/lib/variation", "blue/log", "bluespec/global", "common/lib/variation"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("common/lib/ada/setFocus"), i = e("common/lib/pageTitle"), o = e("blue-app/settings"),
            r = e("blue/$"), a = e("common/lib/selectorUtil"), s = e("blue/root"),
            c = e("appkit-utilities/common/mediaQueryListener"), l = r(s), u = {HEADER: {index: 0, subStr: "header-"}};

    function d(e) {
        return function (i) {
            if (t.defined(e)) {
                var o;
                if (Object.hasOwnProperty.call(i, "focus")) {
                    if (Object.hasOwnProperty.call(i, "focusSubStr")) {
                        var a = u[i.focusSubStr];
                        "string" == typeof i.focus && void 0 !== a && (i.focus = i.focus.insertAtFocusStr(a.index, a.subStr))
                    }
                    o = Object.hasOwnProperty.call(i, "sendData") ? e(i) : e(i.focus)
                }
                var c = o;
                o && "string" != typeof o && o.value && (c = o.value), t.defined(c) && !t.null(c) && n(c).then((function () {
                    Object.hasOwnProperty.call(i, "scrollIntoView") && function (e) {
                        var t, n = Math.floor(r("#header-inner-fixed-container").height()), i = 50,
                                o = r('main .inlinemodalheader, #pnt-tabs:not(".fadeOut")'),
                                a = Math.floor(r(e).offset().top);
                        1 === o.length ? i = Math.floor(o.height()) : o.length > 1 && o.each((function () {
                            i = r(this).height() > i ? r(this).height() : i
                        }));
                        var c = n + i;
                        t = a - c, (a < s.pageYOffset + c || a > s.pageYOffset + s.innerHeight - $(e).innerHeight()) && r("html, body").animate({scrollTop: t}, "fast")
                    }(c)
                }))
            }
        }
    }

    function m(e, t) {
        var n;
        "string" == typeof t ? t.indexOf(".") < 0 ? n = e.model[t] : "function" == typeof e.model.variation ? n = e.model.variation(t) : e.context.logger.error("self.model.variation must be a function") : e.context.logger.error('expected "title" parameter to be of type "string", found "' + typeof t + '"');
        var o = {h1: {value: n, isHtml: !0}};
        i.setTitle(e, o)
    }

    function f(e, t) {
        var n = '<span class="util accessible-text">' + t + "</span>", i = e.indexOf("</a>");
        return e.substring(0, i) + n + e.substring(i)
    }

    String.prototype.insertAtFocusStr = function (e, t) {
        return -1 === this.indexOf("#") ? this.substr(0, e) + t + this.substr(e) : 0 === this.indexOf("#") ? this.substr(0, e + 1) + t + this.substr(e + 1) : this
    };
    var p = function () {
        this.model = this.context.util.object.extend(this.model || {}, e("common/lib/viewFormatUtil"), {setAnchorHAT: f}), this.model.areaName && (this.model = this.context.util.object.merge(this.model, {variation: e("common/lib/variation").bind(void 0, this.model.areaName.toLowerCase(), this.bridge.spec.name)}))
    }, g = function (e) {
        var t = o.get("LOCALIZED_CONTENT_app").CHASE_ONLINE_MENU[e];
        t && i.setTitle(this, {h1: {value: t, isHtml: !0}})
    };
    return function (i, o, r, s, u) {
        var f, y = this;
        u = u || {}, y.logger = y && y.context && y.context.logger || e("blue/log")("[ViewUtil]"), p.call(y), y.bridge ? (t.function(r) ? (f = d(r.bind(y)), y.bridge.on("setFocus", (function (e) {
            f(e)
        }))) : y.bridge.on("setFocus", (function (e) {
            n(e)
        })), y.model.headerError = "", y.model.advisoryError = "", y.model.isWarning = !1, y.bridge.on("serverError", (function (e) {
            var t, i = e.data.header, o = e.data.advisory, r = e.data.target, a = [];
            r ? a.push(y.context.$("#" + r)) : (t = y.context.$(".alertContainer.serverErrorUtil").toArray(), Array.isArray(t) && t.forEach((function (e) {
                e = y.context.$(e), a.push(e)
            }))), a.forEach((function (t) {
                (t.length > 0 || !r) && (y.model.headerError = i, y.model.advisoryError = o, y.model.isWarning = e.data.isWarning, i || o ? (t.removeClass("util hidden"), n(t.selector + " h2")) : t.addClass("util hidden"))
            }))
        })), y.bridge.on("updateViewModel", (function (e) {
            y.model[e.key] = e.value
        })), y.bridge.on("state/updateViewModel", (function (e) {
            e.data && Object.keys(e.data).forEach((function (t) {
                y.model[t] = e.data[t]
            }))
        })), y.bridge.on("ready", (function () {
            t.string(i) && i.length > 0 && y.context.hybrid && n(i), t.string(s) && s.length > 0 && n(s), t.string(o) && o.length > 0 && m(y, o)
        })), u.dontMergeGlobalContent || Object.keys(e("bluespec/global").settings).forEach((function (t) {
            y.model[t] = y.model[t] || e("common/lib/variation")("app", "GLOBAL", t)
        })), y.model.showSkipLink = !0, y.bridge.on("validation/updateButtonValidation", (function (e) {
            y.model.showSkipLink = !e.isValid, y.model.isFormValid = e.isValid
        })), y.bridge.on("state/setDirtyFocus", (function (e) {
            var t = e.data, i = t && t.domEvent, o = i && i.target;
            o && ["DIV", "SPAN"].indexOf(o.nodeName) > -1 && "number" != typeof o.getAttribute("tabindex") && (o = o.parentElement);
            var r = o && o.id;
            n(r ? "#" + r : "a.jpui.closeWrap")
        })), y.blueErrorFocus = function (e) {
            var t = e.domEvent.target, n = e.context;
            n.validate = n.validate || t.dataset.specproperty || "", y.bridge.output.emit("trigger", {
                value: "setErrorWithAnalytics",
                data: n
            })
        }, y.setFocusFromView = function (e) {
            f(e)
        }, y.triggerComponentAction = function (e, t) {
            var n = t;
            t && t.hasOwnProperty("domEvent") && (n = y.context.util.object.merge(t.context, {
                focusId: t.domEvent.currentTarget && t.domEvent.currentTarget.id || t.domEvent.target && t.domEvent.target.id || void 0,
                viewEvent: t.domEvent
            })), y.bridge.output.emit("trigger", {value: e, data: n})
        }, y.isEnterOrClicked = function (e) {
            return 13 === e.domEvent.which || 13 === e.domEvent.keyCode || "mouse" === e.domEvent.pointerType || "touch" === e.domEvent.pointerType || "tap" === e.domEvent.pointerType
        }, y.domEventType = function (e) {
            var t = e.keyCode ? e.keyCode : e.domEvent.keyCode;
            return (13 === t || 32 === t || "click" === e.domEvent.type || "tap" === e.domEvent.type || "mouse" === e.domEvent.pointerType || "touch" === e.domEvent.pointerType) && (13 === t && e.domEvent.preventDefault(), !0)
        }, y.setPageTitleFromMenu = function (e) {
            g.call(y, e)
        }, y.setPageTitle = function (e) {
            m(y, e)
        }, y.setFocusFromDomEvt = function (e) {
            var t = e && e.target;
            t && ["DIV", "SPAN"].indexOf(t.nodeName) > -1 && "number" != typeof t.getAttribute("tabindex") && (t = t.parentElement);
            var i = t && t.id;
            n(i ? "#" + i : "a.jpui.closeWrap")
        }, y.model.getIdSelector = a.getIdSelector, y.model.getId = a.getId, setTimeout((function () {
            y && y.context && y.context.page && y.context.page.broadcast("setHeader")
        }), 0), u.enableBreakpointChange && (y.model.currentBreakpointVU = c.currentBreakpoint, y.breakPointChangeHandler = function () {
            c && y.model && (y.model.currentBreakpointVU = c.currentBreakpoint)
        }, l.on("breakpoint-change", y.breakPointChangeHandler))) : y.logger.error('The mixin "viewUtils" requires that the bridge is set on the view before it\'s mixed into the target view.\nYour accessibility AND your ability to change your view states are probably broken.\nPlease ensure that the bride is being set before the mixin "viewUtils" is applied to the view.\nIt is highly suggested that you accomplish this by setting the bridge outside of the view\'s "init" function and the "viewUtils" mixin is applied to the view inside of the "init" function.\nPlease refactor the following view to fix this issue:\n    ' + y.name)
    }
})),define("common/lib/validator/internal/utilities", ["require", "blue/util", "blue/$"], (function (e) {
    "use strict";
    var t = {}, n = e("blue/util"), i = e("blue/$");
    t.adaTab = !1, t.convertToSnakeCase = function (e) {
        return n.string.unCamelCase(e, "_")
    }, t.convertToCamelCase = function (e) {
        return n.string.camelCase(e)
    }, t.deepCopy = function (e) {
        var t = {};
        return i.extend(!0, t, e), t
    }, t.isArrowPress = function (e) {
        var t = e.keyCode || e.which;
        return 37 === t || 38 === t || 39 === t || 40 === t
    }, t.isALTPress = function (e) {
        return 18 === (e.keyCode || e.which)
    }, t.isShiftPress = function (e) {
        return 16 === (e.keyCode || e.which)
    }, t.isTabPress = function (e) {
        return 9 === (e.keyCode || e.which) && !e.shiftKey
    }, t.isShiftTabPress = function (e) {
        return 9 === (e.keyCode || e.which) && e.shiftKey
    }, t.isTabOrShiftTabPress = function (e) {
        return t.isTabPress(e) || t.isShiftTabPress(e)
    }, t.getAllValidatedProperties = function (e) {
        for (var n = (e = e || document).querySelectorAll("[validate],[data-validate]"), i = [], o = 0; o < n.length; o++) e = n[o], i.push(t.getPropertyFromNode(e));
        return i
    };
    var o = function (e, t) {
        return function (n) {
            return n[e] === t
        }
    }, r = function (e) {
        return o("keyCode", e)
    };
    return t.isBlurEvent = o("blur", "blur"), t.isFocus = !1, t.isFocusTab = !1, t.isFocusEvent = o("focus", "focus"), t.isKeydownEvent = o("keydown", "keydown"), t.isSubmitEvent = o("submit", "submit"), t.tabKeyPressed = r(9), t.enterKeyPressed = r(13), t.shiftKeyPressed = r(16), t.controlKeyPressed = r(17), t.altKeyPressed = r(18), t.commandKeyPressed = r(91), t.tabItem = null, t.isClick = !1, t.getPropertyFromNode = function (e) {
        return e.getAttribute("validate") || e.getAttribute("data-validate")
    }, t.getPropertyFromEvent = function (e) {
        return t.getPropertyFromNode(e.target)
    }, t.getNodeFromProperty = function (e, n) {
        return e = t.getProperty(e), (n = n || document).querySelector(['[validate="', e, '"]'].join("")) || n.querySelector(['[data-validate="', e, '"]'].join(""))
    }, t.fieldRequired = function (e, n) {
        e = t.getProperty(e);
        var i = t.getNodeFromProperty(e, n);
        if (i) return i.required
    }, t.getProperty = function (e) {
        return "object" == typeof e && null !== e ? e.list + "_" + e.index + "_" + e.property : e
    }, t
})),define("common/lib/validator/internal/errorBubble", ["require", "blue/$", "common/lib/validator/internal/utilities", "blue-view-ractive/ractive", "blue-ui/template/elements/label"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = e("common/lib/validator/internal/utilities"), i = e("blue-view-ractive/ractive"),
            o = e("blue-ui/template/elements/label"), r = function (e, t) {
                this.clientSideErrorId = t || "client-side-error", this.namespace = e, this.overErrorBubble = !1;
                this.errorBubble = new i({
                    template: o,
                    data: {
                        type: "clientSideError error pointing down noborder attached",
                        content: "",
                        arrowposition: "50",
                        legacy: !0
                    }
                })
            };
    return r.prototype.getProperty = function (e) {
        var t = e.split(".");
        return t.length >= 1 ? t.pop() : e
    }, r.prototype.id = function () {
        return this.clientSideErrorId
    }, r.prototype.show = function (e, i, o) {
        if (this.triggedByClick) this.triggedByClick = !1; else if (this.inputNode = o, this.remove(), i) {
            this.nodeInError = t(n.getNodeFromProperty(e, o)), e = this.getProperty(e);
            var r = this.namespace + "." + e + "Error";
            this.errorBubble.set("content", i);
            var a = t(this.errorBubble.toHTML());
            a.attr("id", this.clientSideErrorId ? this.clientSideErrorId + "Bubble" : "error-bubble").attr("style", "position:relative;width:100%;float:left;");
            var s = a.children();
            s.attr("id", this.clientSideErrorId).attr("data-attr", r), s.attr("aria-labelledby", ""), this.nodeInError.next()[0] && "BUTTON" === this.nodeInError.next()[0].tagName && "-1" === this.nodeInError.next().attr("tabindex") ? a.insertAfter(this.nodeInError.parent()) : a.insertAfter(this.nodeInError), a.addClass("spoc-bubble-margin"), "0px" === a.css("margin-top") && this.nodeInError.parent() && this.nodeInError.parent().offset() && a.css("margin-top", this.nodeInError.parent().offset().top - a.offset().top);
            var c = t('[id="' + this.clientSideErrorId + '"]');
            c.on("mouseenter", function () {
                this.overErrorBubble = !0
            }.bind(this)), c.on("mouseleave", function () {
                this.overErrorBubble = !1
            }.bind(this)), c.on("blur", function () {
                this.hide()
            }.bind(this)), c.on("focus", (function () {
                c.removeClass("util accessible-text")
            })), c.on("click", function (e) {
                return this.triggedByClick = !0, t(this)[0].nodeInError.focus(), e.stopPropagation(), !1
            }.bind(this)), c.on("keydown", function (e) {
                var n = e.keyCode || e.which;
                if (13 === n || 32 === n) return t(this)[0].nodeInError.focus(), e.stopPropagation(), !1
            }.bind(this))
        }
    }, r.prototype.hide = function (e) {
        (this.overErrorBubble || e) && (this.nodeInError.focus(), this.overErrorBubble = !1);
        var n = t('[id="' + this.clientSideErrorId + '"]');
        n.removeAttr("tabindex"), n.addClass("util accessible-text")
    }, r.prototype.remove = function (e) {
        (this.overErrorBubble || e) && (this.nodeInError.focus(), this.overErrorBubble = !1);
        var n = t('[id="' + this.clientSideErrorId + '"]');
        n.off(), n.parent().remove()
    }, r.prototype.showPlaceholder = function (e) {
        if (0 === t("#error-placeholder").length) {
            this.nodeInError = t(n.getNodeFromProperty(e));
            var i = t(this.errorBubble.toHTML());
            if ("" === i.html()) this.errorBubble.set("content", "&nbsp"), i.children().attr("class", "util accessible-text").attr("id", "error-placeholder").attr("aria-hidden", "true"), i.insertAfter(this.nodeInError)
        }
    }, r.prototype.removePlaceholder = function () {
        var e = t("#error-placeholder");
        e.off(), e.parent().remove()
    }, r.prototype.focus = function () {
        return t('[id="' + this.clientSideErrorId + '"]').focus(), !1
    }, r
})),define("common/lib/validator/validationEngineDecorator", ["require", "appkit-utilities/content/dcu", "blue/is", "common/lib/merchantBillPay/formatUtilityWrapper", "blue/log", "blue/$", "common/lib/validator/internal/errorBubble", "common/lib/validator/internal/utilities", "common/lib/validator/internal/utilities", "common/lib/validator/internal/utilities"], (function (e) {
    "use strict";
    var t = e("appkit-utilities/content/dcu"), n = e("blue/is"),
            i = e("common/lib/merchantBillPay/formatUtilityWrapper"), o = e("blue/log")("[validationEngineDecorator]"),
            r = e("blue/$");
    return function (a) {
        return function () {
            return function (s) {
                var c, l = e("common/lib/validator/internal/errorBubble"),
                        u = e("common/lib/validator/internal/utilities"), d = r(s), m = "[type=submit]",
                        f = a.areaName ? a.areaName : "", p = a.bridge.name.replace("-bridge", ""),
                        g = u.convertToSnakeCase(p).toUpperCase(), y = {};

                function h(e) {
                    return e.relatedTarget ? e.relatedTarget && "A" === e.relatedTarget.tagName && e.relatedTarget.id.toString().indexOf("Error") >= 0 : r(e.delegateTarget).is(".clientSideError") || r(e.delegateTarget).children(".clientSideError").length
                }

                function v(t) {
                    var n = t.isValid, i = t.property;
                    n ? function (t, n, i) {
                        var o = !0 === n ? "serverSideError" : "clientSideError",
                                a = (u = u || e("common/lib/validator/internal/utilities")).getNodeFromProperty(t, s),
                                c = A(t);
                        r(a).removeClass(o), a && a.id && d.find('label[for="' + a.id + '"]').css("color", "").removeClass(o), a && a.id && d.find('label[for="' + a.id + '"]').find(".optional") && d.find('label[for="' + a.id + '"]').find(".optional").removeAttr("style");
                        i || c.remove();
                        if (b(!1, a), a && a.id) {
                            var l = a.id;
                            0 === d.find('label[for="' + l + '"]').length && l.includes("header-") && (l = l.replace("header-", "select-"), d.find('label[for="' + l + '"]').css("color", "").removeClass(o), function (e) {
                                var t = e + "_clientSideErrorAda", n = d.find('label[for="' + e + '"]'),
                                        i = n.find('span[id="' + t + '"]');
                                n && n.length && i && i.length && i.remove()
                            }(l))
                        }
                    }(i) : function (t, n) {
                        var i = !0 === n ? "serverSideError" : "clientSideError",
                                o = (u = u || e("common/lib/validator/internal/utilities")).getNodeFromProperty(t, s);
                        if (r(o).addClass(i), o && o.id && d.find('label[for="' + o.id + '"]').css("color", "#bf2155").addClass(i), b(!0, o), o && o.id) {
                            var a = o.id;
                            0 === d.find('label[for="' + a + '"]').length && a.includes("header-") && (a = a.replace("header-", "select-"), d.find('label[for="' + a + '"]').css("color", "#bf2155").addClass(i), function (e) {
                                var t = e + "_clientSideErrorAda", n = d.find('label[for="' + e + '"]'),
                                        i = n.find('span[id="' + t + '"]');
                                if (n && n.length && i && !i.length) {
                                    var o = '<span id="' + t + '" class="util accessible-text">Error: </span>';
                                    n.prepend(o)
                                }
                            }(a))
                        }
                    }(i)
                }

                function b(e, t) {
                    if (t && t.id) {
                        var n = t.id + "_clientSideErrorAda", i = d.find('label[for="' + t.id + '"]'),
                                o = i.find('span[id="' + n + '"]');
                        if (e) if (i && i.length && o && !o.length) {
                            var a = '<span id="' + n + '" class="util accessible-text">Error: </span>';
                            i.prepend(a)
                        } else {
                            var s = r(t).attr("aria-label");
                            s && -1 === s.indexOf("Error: ") && r(t).attr("aria-label", "Error: " + s)
                        } else i && i.length && o && o.length && o.remove()
                    }
                }

                function A(e) {
                    var t = e + "Error", n = e && (y[t] || new l(g, t));
                    return y[t] = n, n
                }

                function E(e, n) {
                    return function () {
                        var i = u.convertToSnakeCase(g).toUpperCase();
                        "required" === n ? n = "MISSING_MANDATORY_DATA" : "format" === n && (n = "INVALID_FORMAT");
                        var o, r = e.split(".");
                        return o = 3 === r.length ? r[2] + "Error." + n : e + "Error." + n, f && (i = {
                            spec: {name: i},
                            context: {area: {areaName: f}}
                        }), t.dynamicSettings.get(i, o)
                    }
                }

                return a.bridge.on("validation/updateButtonValidation", (function (e) {
                    !function (e) {
                        if (s.querySelector(m) && "BUTTON" === s.querySelector(m).tagName) for (var t = s.querySelectorAll(m), n = 0; n < t.length; n++) {
                            var i = t[n];
                            e ? (i.disabled = !1, r(i).removeClass("disabled"), a.model.submitButtonDisabled = !1) : (i.disabled = !0, r(i).addClass("disabled"), a.model.submitButtonDisabled = !0)
                        } else o.debug("disableSubmitButtonUntilAllFieldsValid: Unable to disable/enable the form's submit button as directed by form isValid '" + e + "' because DOM query for selector submitButtonSelector '" + m + '\' returns none and/or returned match is not button tagName.  \nTip: If your form "form submit button" is not the default selector, e.g., type=submit, please manually set it with component.setSubmitButtonSelector().')
                    }(e.isValid)
                })), a.bridge.on("validation/clearErrorIndicators", (function (e) {
                    if (!n.array(e.properties)) throw new Error("properties has to be an array");
                    e.properties.forEach((function (e) {
                        v({isValid: !0, property: e})
                    }))
                })), a.bridge.on("validation/updateFieldValidation", (function (e) {
                    var n = e.validationObj, o = e.manageErrorBubble, r = e.setFocus;
                    n && (v(n), o && function (e, n) {
                        if (!e.isValid) {
                            "required" === e.message ? e.message = function () {
                                return E(e.property, "required")
                            } : "format" === e.message && (e.message = function () {
                                return E(e.property, "format")
                            });
                            var o = A(e.property), r = function (e, n) {
                                if ("function" == typeof e) return e(n);
                                if ("object" == typeof e && e.spec && e.key && e.variation) {
                                    var o = e.spec;
                                    return "string" == typeof e.spec && f && (o = {
                                        spec: {name: e.spec},
                                        context: {area: {areaName: f}}
                                    }), function () {
                                        return a.context.is.null(e.data) || a.context.is.undefined(e.data) ? t.dynamicSettings.get(o, e.key, e.variation) : function (e, n) {
                                            var o = {};
                                            for (var r in n.data) n.data.hasOwnProperty(r) && ("money" === n.data[r] ? o[r] = i.commonCurrency(a.model[r]) : o[r] = a.model[r]);
                                            return t.dynamicContent.get(e, n.key, n.variation, o)
                                        }(o, e)
                                    }
                                }
                                return e
                            }(e.message)();
                            o.show(e.property, r, s), n && o.focus()
                        }
                    }(n, r))
                })), a.bridge.on("setSubmitButtonSelector", (function (e) {
                    m = e.selector, o.debug("setSubmitButtonSelector set to " + m)
                })), function () {
                    if ("FORM" === s.tagName) s.setAttribute("novalidate", ""); else for (var e = s.querySelectorAll("form"), t = 0; t < e.length; t++) e[t].setAttribute("novalidate", "")
                }(), d.on("focusout", "[validate],[data-validate]", (function (e) {
                    var t = u.getPropertyFromEvent(e);
                    if (!t) {
                        var n = e.keyCode || e.which, i = A(t);
                        return u.isTab = !(9 !== n || e.shiftKey || e.altKey || null === e.target.getAttribute("validate") && null === e.target.getAttribute("data-validate")), u.isShiftTab = 9 === n && e.shiftKey && e.target.id !== i.id(), void (u.tabItem = "")
                    }
                    if (!h(e) && !function (e) {
                        if (e.relatedTarget) return e.relatedTarget.parentElement && r(e.relatedTarget.parentElement).is(".jpui.styledselect") || r(e.relatedTarget).is(".js-option.STYLED_SELECT");
                        if (e && e.target && e.target.parentElement && e.target.parentElement.parentElement) {
                            var t = e.target.parentElement.parentElement.classList;
                            return t.contains("styledselect") && t.contains("show")
                        }
                        return !1
                    }(e)) {
                        var o = !1;
                        u.isShiftTab || u.isTab && u.tabItem !== e.target.id || (o = !0), a.model.customShiftTabBypass ? a.model.customShiftTabBypass = !1 : a.bridge.output.emit("validation", {
                            value: "isFieldValid",
                            property: t,
                            setFocus: !0,
                            manageErrorBubble: o
                        })
                    }
                })), d.on("focus", ".tooltip", (function () {
                    c = !0
                })), d.on("focus", "[validate],[data-validate]", (function (e) {
                    var t = u.getPropertyFromEvent(e);
                    if (t) {
                        var n, i = !1;
                        if (!(u.isShiftTab && (i = !0), n = c, c = !1, n || h(e))) {
                            var o = u.getNodeFromProperty(t, s);
                            r(o).hasClass("clientSideError") && a.bridge.output.emit("validation", {
                                value: "isFieldValid",
                                property: t,
                                setFocus: i,
                                manageErrorBubble: !0
                            }), u.isShiftTab = !1, u.isTab = !1, u.tabItem = ""
                        }
                    }
                })), d.on("keydown", (function (e) {
                    var t = e.keyCode || e.which, n = u.getPropertyFromEvent(e), i = A(n), o = A(n);
                    o ? (u.isTab = !(9 !== t || e.shiftKey || e.altKey || null === e.target.getAttribute("validate") && null === e.target.getAttribute("data-validate")), u.isShiftTab = 9 === t && e.shiftKey && e.target.id !== o.id(), u.tabItem = e.target.id, (u.isTab || u.isShiftTab) && i.remove()) : u.isShiftTab = !1
                })), {
                    teardown: function () {
                        d.off("focus"), d.off("focusout"), d.off("keydown")
                    }
                }
            }
        }
    }
})),define("common/lib/validationEngineBlue", ["require", "blue-app/component/validator", "blue/util", "common/utility/dynamicContentUtil", "appkit-utilities/content/dcu", "common/lib/quickPay/qpFormatUtility", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue-app/component/validator"), n = e("blue/util"), i = e("common/utility/dynamicContentUtil"),
            o = e("appkit-utilities/content/dcu"), r = e("common/lib/quickPay/qpFormatUtility").formatCurrencyUtility,
            a = e("blue/util").lang.defaults;
    return function (e, s) {
        var c = this, l = {
            pageValidations: [],
            fieldValidations: {},
            fieldExclusions: [],
            validationConstraintErrorMessageMap: {}
        }, u = e.context.is, d = u.defined(e.inspect) && !u.defined(e.validate);
        e.validationTrackingInitialized || (e.validationTrackingInitialized = !0, d && e.output.on("dataValidation", (function (e) {
            var t = l.fieldValidations[e.value];
            t && f(t, e)
        })), e.runAllFieldLevelValidations = function (n) {
            var i;
            for (var o in l.fieldValidations) if (l.fieldValidations[o]) {
                var r = void 0;
                if (n) {
                    var a = e.spec || {}, s = a && a.data && a.data[o] && a.data[o].type;
                    try {
                        r = s && t.validate(e[o], s)
                    } catch (t) {
                        e.context.logger.warn("Exception while evaluating property", o, t)
                    }
                }
                (i = l.fieldValidations[o]).isValid = S(i, r), g(i)
            }
            p()
        }, e.clearErrorIndicators = function () {
            var t;
            for (var n in l.fieldValidations) l.fieldValidations[n] && ((t = l.fieldValidations[n]).currentError = {}, t.errorKey ? e.model.set(t.errorKey, "") : e.model.set(t.property + "Error", ""))
        }, e.registerValidation = function (t) {
            var n, i = void 0;
            if (!Array.isArray(t)) throw new Error("unable to create validation obj please make sure you pass in an array of fields");
            if (t.forEach((function (e) {
                (n = y(e)) && (i || (i = {}), i[n.property] = n)
            })), !i) throw new Error("unable to create validation obj during registerValidation", t);
            l.fieldValidations = i, !d && Object.keys(l.fieldValidations).forEach((function (t) {
                l.fieldValidations[t] && (l.fieldValidations[t].unListener = e.model.onValue(t, N.bind(c, t)))
            })), p()
        }, e.registerFormFieldExclusions = function (t) {
            t && e.context.is.array(t) && (l.fieldExclusions = t)
        }, e.addValidation = function (t) {
            var n, i;
            "string" == typeof t || "object" == typeof t && t.name ? n = [t] : Array.isArray(t) && (n = t), n.forEach((function (n) {
                var o, r;
                if ("object" == typeof n && n.name ? o = n.name : "string" == typeof n && (o = n), !l.fieldValidations[o]) {
                    if (!(i = y(n))) throw new Error("unable to create validation obj during addValidation", t);
                    l.fieldValidations[o] = i, !d && (r = o, l.fieldValidations[r] && (l.fieldValidations[r].unListener = e.model.onValue(r, N.bind(c, r))))
                }
            })), p()
        }, e.removeValidation = function (t, n) {
            (e.context.is.undefined(t) || e.context.is.array(t) && 0 === t.length ? Object.keys(l.fieldValidations) : e.context.is.array(t) ? t : [t]).forEach((function (t) {
                if (l.fieldValidations[t]) {
                    var i = l.fieldValidations[t];
                    !d && u.function(i.unListener) && i.unListener(), e.model.set(t + "Error", ""), i.currentError = {}, delete l.fieldValidations[t], n && f(i)
                }
            }))
        }, e.removeAllValidations = function () {
            d || Object.keys(l.fieldValidations).forEach((function (e) {
                u.function(l.fieldValidations[e].unListener) && l.fieldValidations[e].unListener()
            })), l.fieldValidations = {}, l.pageValidations = []
        }, e.isFieldValid = function (t) {
            var n;
            return !!e.context.is.undefined(t) || (n = l.fieldValidations[t], !!e.context.is.undefined(n) || n.isValid)
        }, e.setErrorWithAnalytics = function (t) {
            var n = t.validate || t.id && t.id.split("-")[0] || "";
            if ("input" === n && t && t.id && 0 === t.id.indexOf("input-") && (n = t.id.split("-")[1]), l.fieldValidations[n] && !l.fieldValidations[n].isValid) {
                var i = l.fieldValidations[n].currentError, r = o.dynamicContent.set(e, i.key, i.variation, i.props);
                r.trim().length <= 0 && (r = m.call(e, i.key, i.variation), e.model.set(i.key, r))
            }
        }, e.setValidationConstraintErrorMessageMap = function (e) {
            return l.validationConstraintErrorMessageMap = e
        });
        var m = function (e, t) {
            return this.context.logger.warn("Content Not Set for \nBundle:\t" + this.spec.name + "\nKey:\t" + e + "\nVariation:\t" + t), "Content Not Set for \nBundle: " + this.spec.name + "\tKey: " + e + "\tVariation: " + t
        };

        function f(e, t) {
            if (e && (e.isValid = S(e, t), g(e), e.customValidations && e.customValidations.length > 0)) for (var n, i = 0; i < e.customValidations.length; i++) if (e.customValidations[i].alsoValidate) {
                n = e.customValidations[i].alsoValidate;
                for (var o = 0; o < n.length; o++) l.fieldValidations[n[o]].isValid = S(l.fieldValidations[n[o]]), g(l.fieldValidations[n[o]])
            }
            p()
        }

        function p() {
            e.output.emit("validation", {value: "updateButtonValidation", isValid: _()})
        }

        function g(t) {
            t.isValid ? (t.errorKey ? e.model.set(t.errorKey, "") : e.model.set(t.property + "Error", ""), t.currentError = {}) : function (t) {
                var n, i, o, r = function (t) {
                    var n, i = t.message, o = void 0;
                    "function" == typeof i && (i = i.call(e));
                    if ("string" == typeof i && "required" === i) n = "MISSING_MANDATORY_DATA"; else if ("string" == typeof i && "format" === i) if (t.frameworkValidationObj && Array.isArray(t.frameworkValidationObj.failedConstraints)) {
                        var r = t.frameworkValidationObj.failedConstraints[0].type.toUpperCase();
                        n = l.validationConstraintErrorMessageMap && l.validationConstraintErrorMessageMap[t.property] && l.validationConstraintErrorMessageMap[t.property][r] || "INVALID_FORMAT"
                    } else n = "INVALID_FORMAT"; else "object" == typeof i && (n = a(i.variation, ""), o = a(i.data, o));
                    return {variation: n, data: o}
                }(t);
                if (i = a(t.errorKey, t.property + "Error"), n = r.variation, o = r.data, e.context.is.null(t.currentError) || e.context.is.undefined(t.currentError) || e.context.is.empty(t.currentError)) {
                    var s = {variation: n, key: i, props: {}};
                    t.currentError = s, h(t, i, n, o)
                } else t.currentError.variation !== n && (t.currentError.variation = n, h(t, i, n, o))
            }(t)
        }

        function y(t) {
            var n = void 0;
            if ("object" == typeof t && t.name ? (n = D(t.name), n = e.context.util.object.merge(n, t)) : "string" == typeof t && (n = D(t)), n) return n.isValid = S(n), g(n), n
        }

        function h(t, n, o, a) {
            var s = {}, c = i.getLocaleContent(e, n, o);
            if (a && "object" == typeof a) {
                for (var l in a) a.hasOwnProperty(l) && ("money" === a[l] ? s[l] = r.commonCurrency(e.model.get(l)) : s[l] = e.model.get(l));
                t.currentError.props = s, c = e.context.util.string.interpolate(c, s)
            }
            e.model.set(n, c || m.call(e, n, o))
        }

        function v(e) {
            return null != e && ("string" != typeof e || "" !== e.trim())
        }

        function b(t) {
            return t && n.array.find(t, (function (t) {
                return !t.validator.call(c, e)
            }))
        }

        function A(e) {
            var t, n = !0;
            return (t = b(e.customValidations)) && (e.message = t.message, n = !1), n
        }

        function E(t, n) {
            var i = !0;
            return n && !n.isValid && (v(e.model.get(t.property)) || t.isRequired) && (t.isValid = !1, t.message = "format", t.frameworkValidationObj = n.validation, i = !1), i
        }

        function T(t) {
            var n = !0;
            return v(e.model.get(t.property)) || t.requiredValidations && 0 !== t.requiredValidations.length || (t.isRequired ? (t.message = "required", n = !1) : (t.message = {}, n = !0)), n
        }

        function C(e) {
            var t, n = !0;
            return (t = b(e.requiredValidations)) && (e.message = t.message, n = !1), n
        }

        function S(t, n) {
            var i = !0, o = !t.isRequired && v(e.model.get(t.property));
            return (t.isRequired || o) && (i = [T, C, E, A].every((function (e) {
                return e(t, n)
            }))), i && (t.message = {}, t.currentError = {}, t.frameworkValidationObj = void 0), i
        }

        function _() {
            for (var t in l.fieldValidations) if (Object.hasOwnProperty.call(l.fieldValidations, t) && -1 === l.fieldExclusions.indexOf(t) && !l.fieldValidations[t].isValid) return !1;
            0 === l.pageValidations.length && s.pageValidators && s.pageValidators.length > 0 && (l.pageValidations = s.pageValidators);
            for (var n = 0; n < l.pageValidations.length; n++) if (!l.pageValidations[n].validator.call(c, e)) return !1;
            return !0
        }

        function O(e, t) {
            var n = [], i = t ? s.requiredValidations : s.customValidations;
            if (i) for (var o = 0; o < i.length; o++) for (var r = 0; r < i[o].fields.length; r++) i[o].fields[r] === e && n.push(i[o]);
            return n
        }

        function D(e) {
            return {
                property: e,
                isValid: !0,
                isRequired: I(e),
                customValidations: O(e, !1),
                requiredValidations: O(e, !0),
                message: {}
            }
        }

        function I(e) {
            if (s.requiredFields) for (var t = 0; t < s.requiredFields.length; t++) if (s.requiredFields[t] === e) return !0;
            return !1
        }

        function N(n) {
            var i, o = l.fieldValidations[n], r = e.spec || {}, a = r && r.data && r.data[n] && r.data[n].type;
            if (o) {
                try {
                    i = a && t.validate(e[n], a)
                } catch (t) {
                    e.context.logger.warn("Exception while evaluating property", n, t)
                }
                f(o, i)
            }
        }
    }
})),define("common/lib/validationEngine", ["require", "blue/is", "blue-app/component/validator"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("blue-app/component/validator");
    return function (e, i, o) {
        var r = this, a = {pageValidations: [], fieldValidations: {}, fieldExclusions: []}, s = o || "[type=submit]",
                c = t.defined(e.inspect) && !t.defined(e.validate);

        function l(e, n) {
            if (e) {
                var i = e.isValid;
                if (e.isValid = p(e, n), !i && e.isValid && d(e), e.customValidations && e.customValidations.length > 0) for (var o, r = 0; r < e.customValidations.length; r++) if (e.customValidations[r].alsoValidate) {
                    o = e.customValidations[r].alsoValidate;
                    for (var s = 0; s < o.length; s++) if (t.defined(a.fieldValidations[o[s]])) {
                        var c = a.fieldValidations[o[s]].isValid;
                        a.fieldValidations[o[s]].isValid = p(a.fieldValidations[o[s]]), !c && a.fieldValidations[o[s]].isValid && d(a.fieldValidations[o[s]])
                    }
                }
            }
            u()
        }

        function u() {
            e.output.emit("validation", {value: "updateButtonValidation", isValid: g()})
        }

        function d(t) {
            e.output.emit("validation", {
                value: "updateFieldValidation",
                validationObj: t,
                manageErrorBubble: !0,
                setFocus: !1
            })
        }

        function m(e) {
            return null != e && ("string" != typeof e || "" !== e.trim())
        }

        function f(n, i, o) {
            for (var a = n.property.split("."), s = 0; s < i.length; s++) if (!i[s].validator.call(r, e, a[1], a[2])) return "function" == typeof (o = i[s].message) && (o = o.call(e)), t.object(o) && !o.spec && (o.spec = e.spec.name), n.message = o, !1;
            return !0
        }

        function p(t, n) {
            var i = function (t) {
                var n = t.split(".");
                if (3 === n.length) return e.model.get(n[0]) && e.model.get(n[0])[n[1]] && e.model.get(n[0])[n[1]][n[2]];
                if (2 === n.length) return e.model.get(n[0]) && e.model.get(n[0])[n[1]];
                return e.model.get(n[0])
            }(t.property);
            if (Array.isArray(t.requiredValidations) && t.requiredValidations.length > 0) {
                if (!f(t, t.requiredValidations, void 0)) return !1
            } else {
                if (!m(i) && t.isRequired) return t.message = "required", !1;
                if (!m(i) && !t.isRequired) return t.message = {}, !0
            }
            if (m(i) && n && !function (t) {
                if (t.changePath && Array.isArray(t.validation)) return !e.context.util.array.find(t.validation, {dataPath: t.changePath});
                if (t.validation) return t.validation.isValid;
                return t.isValid
            }(n)) return t.isValid = !1, t.message = "format", !1;
            if (Array.isArray(t.customValidations) && t.customValidations.length > 0 && !f(t, t.customValidations, void 0)) return !1;
            return t.message = {}, !0
        }

        function g() {
            0 === a.pageValidations.length && i.pageValidators && i.pageValidators.length > 0 && (a.pageValidations = i.pageValidators);
            for (var t = 0; t < a.pageValidations.length; t++) if (!a.pageValidations[t].validator.call(r, e)) return !1;
            for (var n in a.fieldValidations) if (Object.hasOwnProperty.call(a.fieldValidations, n) && -1 === a.fieldExclusions.indexOf(n) && !a.fieldValidations[n].isValid) return !1;
            return !0
        }

        function y(e) {
            return -1 !== e.indexOf(".")
        }

        function h(e, t) {
            var n, o, r = [], a = t ? i.requiredValidations : i.customValidations;
            if (a) for (var s = 0; s < a.length; s++) for (var c = 0; c < a[s].fields.length; c++) y(n = a[s].fields[c]) ? (o = e.split("."), 2 !== (n = n.split(".")).length || o[0] !== n[0] || o[2] !== n[1] && o[1] !== n[1] || r.push(a[s])) : a[s].fields[c] === e && r.push(a[s]);
            return r
        }

        function v(e) {
            return {
                property: e,
                isValid: !0,
                isRequired: b(e),
                customValidations: h(e, !1),
                requiredValidations: h(e, !0),
                message: {}
            }
        }

        function b(e) {
            var t, n;
            if (i.requiredFields) for (var o = 0; o < i.requiredFields.length; o++) if (n = i.requiredFields[o], t = e.split("."), (n = n.split(".")) === e || t[0] === n[0] && t[2] === n[1]) return !0;
            return !1
        }

        function A(t) {
            if (a.fieldValidations[t] && !1 !== e.isValidationEnabled) {
                var i, o, r = a.fieldValidations[t], s = e.spec || {};
                try {
                    var c = t.split(".");
                    if (3 === c.length) {
                        var u = c[0], d = c[1], m = c[2];
                        i = s.data && s.data[u] && s.data[u].items && s.data[u].items[m] || "Description", o = n.validate(e[u][d][m], i)
                    } else o = (i = s && s.data && s.data[t] && s.data[t].type || "Description") && n.validate(e.model.get(t), i);
                    l(r, o)
                } catch (n) {
                    e.context.logger.warn("Exception while evaluating property", t, n)
                }
            }
        }

        e.output.emit("setSubmitButtonSelector", {selector: s}), e.validationTrackingInitialized || (e.validationTrackingInitialized = !0, c && e.output.on("dataValidation", (function (e) {
            var t = e && e.changePath.split("."), n = a.fieldValidations[t.length > 1 ? e.changePath : e.value];
            n && l(n, e)
        })), e.output.on("ready", (function () {
            e.input.on("validation/isFieldValid", (function (t) {
                t.property && e.output.emit("validation", {
                    value: "updateFieldValidation",
                    validationObj: a.fieldValidations[t.property],
                    manageErrorBubble: t.manageErrorBubble,
                    setFocus: t.setFocus
                })
            }))
        })), e.runAllFieldLevelValidations = function (t, n) {
            var i, o;
            for (var r in a.fieldValidations) a.fieldValidations[r] && (o = (i = a.fieldValidations[r]).isValid, i.isValid = p(i), (o && !i.isValid && t || i.isValid && !o || !i.isValid && n) && e.output.emit("validation", {
                value: "updateFieldValidation",
                validationObj: i,
                manageErrorBubble: !1
            }));
            u()
        }, e.forceErrorHighlighting = function (t) {
            var n, i, o = [];
            for (var r in "string" == typeof t ? o = [t] : Array.isArray(t) && (o = t), a.fieldValidations) a.fieldValidations[r] && (n = a.fieldValidations[r], i = !(o.length > 0) || o.indexOf(r) > -1, n && i && e.output.emit("validation", {
                value: "updateFieldValidation",
                validationObj: n,
                manageErrorBubble: !1
            }))
        }, e.clearErrorIndicators = function (n) {
            var i = n && Array.isArray(n) && n.length > 0 ? n : Object.keys(a.fieldValidations);
            t.array(i) && i.length > 0 && e.output.emit("validation", {value: "clearErrorIndicators", properties: i})
        }, e.registerValidation = function (t) {
            var n, i, o = {};
            if (e.removeAllValidations(), t) for (var s = 0; s < t.length; s++) (n = t[s]) && "" !== n && ((i = v(n)).isValid = p(i), o[n] = i);
            a.fieldValidations = o, !c && Object.keys(a.fieldValidations).forEach((function (t) {
                a.fieldValidations[t] && (a.fieldValidations[t].unListener = e.model.onValue(t, A.bind(r, t)))
            })), u()
        }, e.registerFormFieldExclusions = function (e) {
            e && t.array(e) && (a.fieldExclusions = e)
        }, e.addValidation = function (n) {
            (t.array(n) ? n : [n]).forEach((function (t) {
                var n;
                a.fieldValidations[t] || (a.fieldValidations[t] = v(t), a.fieldValidations[t].isValid = p(a.fieldValidations[t]), !c && (n = t, a.fieldValidations[n] && (a.fieldValidations[n].unListener = e.model.onValue(n, A.bind(r, n)))))
            })), u()
        }, e.removeValidation = function (e) {
            (t.undefined(e) || t.array(e) && 0 === e.length) && Object.keys(a.fieldValidations), (t.array(e) ? e : [e]).forEach((function (e) {
                if (a.fieldValidations[e]) {
                    var n = a.fieldValidations[e];
                    !c && t.function(n.unListener) && n.unListener(), delete a.fieldValidations[e], l(n)
                }
            }))
        }, e.removeAllValidations = function () {
            c || Object.keys(a.fieldValidations).forEach((function (e) {
                t.function(a.fieldValidations[e].unListener) && a.fieldValidations[e].unListener()
            })), a.fieldValidations = {}, a.pageValidations = []
        }, e.setSubmitButtonSelector = function (t) {
            s = t, e.output.emit("setSubmitButtonSelector", {selector: s})
        }, e.isFieldValid = function (t) {
            var n;
            return !!e.context.is.undefined(t) || (n = a.fieldValidations[t], !!e.context.is.undefined(n) || n.isValid)
        })
    }
})),define("common/lib/serverErrorUtil", ["require", "blue/is", "common/lib/validator/internal/utilities", "blue-app/settings"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("common/lib/validator/internal/utilities").convertToCamelCase,
            i = e("blue-app/settings");
    return function (e) {
        function o(o, r, a, s, c, l, u, d) {
            var m = ["SYSTEM_FAILURE", "NOT_MAPPED"], f = e.context.application.dcu.dynamicContent;

            function p(t, n) {
                var r, a, c, l, p = i.get("LOCALIZED_CONTENT_app").LOGON["logon" + n + ".SYSTEM_FAILURE"];
                if (o && "N/A" === o) r = e.context.util.string.interpolate(e.model.get(t) || "", s || {}); else if (o && -1 === m.indexOf(o) || !e.context.is.empty(s)) r = f.get(e, t, o, s); else {
                    a = e.context.area.name && e.context.area.name.toLowerCase(), c = e.spec.name;
                    r = function () {
                        try {
                            e.context.is.empty(s) && !o && (u || d) && (r = e[t]), r || (r = f.get(e, t, o, s))
                        } catch (t) {
                            return e.context.logger.debug("Error setting error message at serverErrorUtil:", e.context.logger.debug), p
                        }
                        return r || (l = m[1], r = i.get("LOCALIZED_CONTENT_" + a) && i.get("LOCALIZED_CONTENT_" + a)[c] && i.get("LOCALIZED_CONTENT_" + a)[c][t + "." + l] && f.get(e, t, l, s) || p), r
                    }()
                }
                return r || p
            }

            s = s || {};
            var g = {}, y = t.defined(u) ? u : n(e.spec.name) + "ErrorHeader",
                    h = t.defined(d || u) ? d || u : n(e.spec.name) + "ErrorAdvisory";
            if (g.header = c ? "" : p(y, "ErrorHeader"), g.advisory = l ? "" : p(h, "ErrorAdvisory"), g.target = r, g.isWarning = a, "NOT_MAPPED" === o && e.model.get("errorData")) {
                var v = e.model.get("errorData");
                g.advisory = g.advisory + " ( " + v.errorCode + " )"
            }
            e.context.controller.broadcast("serverError", {data: g})
        }

        e.showError = function (e, t, n, i, r) {
            o(e, t, !1, n, !1, !1, i, r)
        }, e.showWarning = function (e, t, n, i, r) {
            o(e, t, !0, n, !1, !1, i, r)
        }, e.showErrorWithoutMessage = function (e, t, n, i, r) {
            o(e, t, !1, n, !1, !0, i, r)
        }, e.showWarningWithoutMessage = function (e, t, n, i, r) {
            o(e, t, !0, n, !1, !0, i, r)
        }, e.showWarningWithoutVariation = function (e, t, n, i) {
            o("N/A", e, !0, i, !1, !1, t, n)
        }, e.clearAlert = function (t) {
            e.model.set("errorData", ""), e.context.controller.broadcast("serverError", {
                data: {
                    spec: e.spec && e.spec.name,
                    target: t
                }
            })
        }, e.context.on("clearServerError", (function () {
            e.clearAlert()
        })), e.context.on("serverError", (function (t) {
            e.output && e.output.emit("serverError", t)
        }))
    }
})),define("common/lib/payments/controllerUtils", ["require", "blue/$", "blue-app/settings", "blue/is", "blue-view/nodeDictionary", "common/lib/payments/controllerUtilsExt", "blue/util", "common/lib/API/contextValidation/contextValidationMixin", "common/lib/payments/configureView", "common/lib/viewUtils", "common/lib/validator/validationEngineDecorator", "common/lib/API/dataValidation/dataValidationAPI", "common/lib/validationEngineBlue", "common/lib/validationEngine", "common/lib/componentUtils", "common/lib/serverErrorUtil", "appkit-utilities/language/helper"], (function (e) {
    "use strict";
    return function (t, n, i) {
        var o = n, r = i, a = t, s = e("blue/$"), c = e("blue-app/settings"), l = e("blue/is"),
                u = e("blue-view/nodeDictionary"), d = [], m = {},
                f = e("common/lib/payments/controllerUtilsExt")(t, n), p = e("blue/util"), g = p.lang.defaults;

        function y(e) {
            return function (t) {
                return t && !e(t)
            }
        }

        function h() {
            return p.array.find([].slice.call(arguments), y(l.empty))
        }

        function v(e) {
            return e.setFocus ? function (t) {
                return e.setFocus.hasOwnProperty(t) ? e.setFocus[t] : t
            } : this.setFocus && "function" == typeof this.setFocus ? this.setFocus : function (e) {
                return e
            }
        }

        function b(e) {
            return "function" != typeof (e.view || e.name) && void 0 === e.viewConfig
        }

        function A(t) {
            var n = {}, i = t.view || t.name;
            if ("function" != typeof i) {
                if (!t.viewConfig) return i;
                i = function () {
                }
            }
            return function (o) {
                for (var r in function (o) {
                    n = {bridgeObj: {}, viewName: t.name}, i.call(n, o), e("common/lib/payments/configureView")(n, t);
                    var r = n.init || function () {
                    };
                    n.init = function () {
                        n.bridge && (this.bridge = n.bridge), r.call(this);
                        var i = {dontMergeGlobalContent: t.mergeGlobalSpec};
                        e("common/lib/viewUtils").call(this, t.adaFocusOnLoad || t.adaFocusOnPageLoad, t.windowTitle, v.call(this, t), t.adaFocusOnComponentLoad, i), t.useValidationEngineDecorator && (this.decorators = this.decorators || {}, t.customValidationEngineDecorator ? this.decorators.validator = t.customValidationEngineDecorator(this) : this.decorators.validator = e("common/lib/validator/validationEngineDecorator")(this))
                    }
                }(o), this.model = n.model, this.views = n.views || {}, delete n.bridgeObj, n) n.hasOwnProperty(r) && void 0 === this[r] && (this[r] = n[r])
            }
        }

        function E(t, n) {
            if (n.handleDirtyForm) {
                var i = g(n.handleDirtyFormFields, {});
                e("common/lib/API/dataValidation/dataValidationAPI")(t, i);
                for (var o = Object.keys(i), s = 0; s < o.length; s++) Object.hasOwnProperty.call(t.model.get(), o[s]) && t.setInitialDataForDirty(o[s], t.model.get()[o[s]])
            }
            n.customValidator && (n.useBlueValidator ? e("common/lib/validationEngineBlue")(t, n.customValidator) : n.customValidationEngine ? n.customValidationEngine(t, n.customValidator) : e("common/lib/validationEngine")(t, n.customValidator)), a.context.is.function(r) ? r(t) : (e("common/lib/componentUtils")(t), e("common/lib/serverErrorUtil")(t), t.getLanguage = e("appkit-utilities/language/helper"))
        }

        function T(e) {
            if ("string" != typeof e) throw new Error("key must be a string");
            var t = null, n = e.split("_");
            return n.length > 1 ? a.context.util.object.has(m, e) ? t = m[e] : ((t = o[n[0]]).name = e, m[e] = t) : (t = o[e], l.undefined(t) && (o[e] = {}, t = {}), t.name = e), t
        }

        function C(e) {
            if ("string" != typeof e) throw new Error("key must be a string");
            var t = T(e), n = t.preventReregister;
            return a.registry.hasComponent(e) && n ? a.components[e] : (t.data = a.context.util.object.merge(t.data, {}) || {}, t.mergeGlobalSpec && function (e) {
                var t = c.get("LOCALIZED_CONTENT_app");
                if (!t) throw new Error("unable to retrieve global data");
                t = t.GLOBAL || {}, Object.getOwnPropertyNames(t).forEach((function (n) {
                    "function" != typeof t[n] && void 0 === e.spec.settings[n] && void 0 === e.data[n] && (e.data[n] = t[n], e.spec.data[n] = {type: "Description"})
                }))
            }(t, a.context), t.model = s.extend(!0, {}, t.data), t.spec = t.spec || {}, a.registry.registerComponent(e, t, !0), a.registry.getComponent(e))
        }

        function S(e, t) {
            var n;
            return e && e.callBackData && t && (n = e.callBackData[t]), n
        }

        function _(e, t, n) {
            var i = e && e.split("_") || [], r = e && o[e] || e && i.length > 1 && o[i[0]];
            if (!r) return !1;
            var a = u.getViewAt(n || r.target || "#content"), s = d.indexOf(e) > -1, c = O(a, t, e);
            return s && !c && d.splice(d.indexOf(e), 1), c && s
        }

        function O(e, t, n) {
            var i = !1;
            if (a.context.is.null(e) || a.context.is.undefined(e) || a.context.is.empty(e) || !a.context.is.array(e)) {
                if (!a.context.is.null(e) && !a.context.is.undefined(e)) {
                    var r = n && o[n];
                    "automaticPaymentsView" === e.viewName && (e.viewName = "/dashboard/view/paymentsActivity/automaticPayments"), i = e.viewName === t || e.viewName === (r && r.viewName) || e.viewName === (r && r.view)
                }
            } else for (var s = 0; s < e.length; s++) {
                if (e[s].viewName === t) {
                    i = !0;
                    break
                }
            }
            return i
        }

        return "function" != typeof t.context.isDirty && e("common/lib/API/contextValidation/contextValidationMixin")(t), {
            updateConfigData: function (e, t, n) {
                if ("string" != typeof e) throw new Error("key must be a string");
                var i = null;
                if ((i = o[e]) && i.data) {
                    if ("string" != typeof t) throw new Error("key must be a string");
                    i.data[t] = n
                }
                o[e] = i
            },
            clearCav: function () {
                var e = [], t = [];
                e = 0 === arguments.length ? e.concat(d) : Array.isArray(arguments[0]) ? arguments[0].length && arguments[0] ? arguments[0] : e.concat(d) : Array.prototype.slice.call(arguments);
                for (var n = 0; n < e.length; n++) a.context.util.object.has(m, e[n]) && delete m[e[n]], d.indexOf(e[n]) > -1 ? (a.registry.hasComponent(e[n]) && t.push(a.registry.destroyComponent(e[n])), d.splice(d.indexOf(e[n]), 1), a.context.logger.info(e[n] + " cleared")) : a.context.logger.warn(e[n] + " could not be found in the loaded components!");
                return Promise.all(t)
            },
            executeCav: function (e, t) {
                var n, i, r, s, c = l.array(e) ? e : [e];
                return new Promise((function (e, l) {
                    var u, m = [], f = [], p = {}, g = [], y = [];
                    for (u = 0; u < c.length; u++) {
                        if ("string" != typeof c[u]) throw new Error("arguments must be strings in executeCav");
                        var v = h((p = T(c[u])).view, p.name), O = h(p.target, "#content");
                        _(p.name, v, O) || f.push(p)
                    }
                    if (f.length > 0) {
                        for (u = 0; u < f.length; u++) p = f[u], n = C(p.name), i = b(p) ? p.view || p.name : A(p), r = p.append || !1, s = h(p.target, "#content"), m.push([n, i, {
                            append: r,
                            target: s
                        }]), E(n, p);
                        a.executeCAV(m).then((function () {
                            for (Array.prototype.slice.call(c).forEach((function (e) {
                                -1 === d.indexOf(e) && d.unshift(e)
                            })), u = 0; u < f.length; u++) if (-1 === (p = f[u]).name.indexOf("_") && (o[p.name].viewName = p.name), p.callbacks) {
                                var n, i = a.registry.getComponent(p.name);
                                if ("string" == typeof p.callbacks) n = S(t, p.name), g.push(i[p.callbacks](n)), y.push(p.name); else {
                                    if (!Array.isArray(p.callbacks)) throw new Error("callbacks must be a string or array of strings");
                                    for (var r = 0; r < p.callbacks.length; r++) {
                                        if ("undefined" === p.callbacks[r]) throw new Error("callbacks must be a string or array of strings");
                                        n = S(t, p.name), g.push(i[p.callbacks[r]](n)), y.push(p.name)
                                    }
                                }
                            }
                            Promise.all(g).then((function () {
                                for (u = 0; u < y.length; u++) a.components[y[u]].formInitialized && a.components[y[u]].formInitialized(!0);
                                e()
                            })).catch((function (e) {
                                l(e)
                            }))
                        })).catch((function (e) {
                            l(e)
                        }))
                    } else e()
                }))
            },
            protectUrls: function (e, t, n) {
                e.forEach((function (e) {
                    if (a[e]) {
                        var i = a[e];
                        a[e] = function (e) {
                            t.call(a, e) ? i.call(a, e) : n.call(a, e)
                        }
                    }
                }))
            },
            setTargetToActivityId: function (e, t, n) {
                o[e].target = t + n
            },
            setTargetBasedOnPaymentId: function (e, t, n) {
                o[e] ? o[e].target = "#" + (n || "transaction") + "_" + t : a.context.logger.warn(e + " component not found")
            },
            setTargetToPaymentId: function (e) {
                o[e].target = "#transaction_" + a.context.state().action.params.paymentId
            },
            setTargetToBtId: function (e) {
                o[e].target = "#transaction_" + a.context.state().action.params.btId
            },
            createDynamicKeyBasedOnPaymentId: function (e, t) {
                return e + "_" + t
            },
            updateConfigTarget: function (e, t) {
                o[e] ? o[e].target = t : a.context.logger.warn(e + " component not found")
            },
            createDynamicKey: function (e) {
                return e + "_" + a.context.state().action.params.paymentId
            },
            createDynamicBalanceTransferActivityKey: function (e) {
                return e + "_" + a.context.state().action.params.btId
            },
            setTargetToBalanceTransferActivityId: function (e) {
                o[e].target = "#transaction_" + a.context.state().action.params.btId
            },
            isComponentViewLoaded: _,
            setTargetToAccountId: function (e) {
                o[e].target = "#account_" + a.context.state().action.params.accountId
            },
            getLoadedActions: function () {
                return d
            },
            reset: function () {
                d = [], m = {}
            },
            isPaymentActivityMenuLoaded: function () {
                return O(u.getViewAt("#content"), "paymentsActivityMenuView")
            },
            registerComponent: f.registerComponent,
            executeComponentAndView: f.executeComponentAndView,
            destroyComponentAndView: f.destroyComponentAndView,
            isComponentAndViewLoaded: f.isComponentAndViewLoaded,
            isComponentAndViewLoadedArea: f.isComponentAndViewLoadedArea,
            destroyAllComponents: f.destroyAllComponents,
            displayError: f.displayError,
            loadModules: f.loadModules,
            addMixins: E
        }
    }
})),define("common/lib/payments/format", ["require", "moment", "mout/string", "common/lib/merchantBillPay/formatUtilityWrapper", "common/lib/quickPay/qpFormatUtility"], (function (e) {
    "use strict";
    var t = e("moment"), n = e("mout/string"), i = e("common/lib/merchantBillPay/formatUtilityWrapper"),
            o = e("common/lib/quickPay/qpFormatUtility");
    return {
        dynamicContent: a, hat: function (e) {
            return a('<span class="util accessible-text">{{label}}</span>', {label: e})
        }, formatAccountMask: s, formatAccountName: function (e, t, n) {
            var i = s(t, n);
            return [e, " (", i, ")"].join("")
        }, formatAccountDisplay: function (e, t, n) {
            var o = (e || "") + " (..." + (t || "") + ")";
            void 0 !== n && (o += " " + function (e, t) {
                var n = 0;
                if (void 0 === e) return "";
                if (isNaN(Number(e))) return e;
                n = "number" == typeof e ? e : Number(e);
                var o = i.commonCurrency(n);
                !0 === t && (n >= 0 ? o = "<span class='AMOUNTPOS'>" + o + "</span>" : n < 0 && (o = "<span class='EDATALABELV'>" + o + "</span>"));
                return o
            }(n));
            return o
        }, formatAccountNameWithPrefix: function (e, t, n, i) {
            var o = s(t, i);
            return [e, " (", n, o, ")"].join("")
        }, replace: function (e, t, n) {
            return (e || "").replace(t, n)
        }, formatEmailMask: function (e, t, n) {
            if (void 0 !== e && void 0 !== t && void 0 !== n) return e + "...." + t + "@" + n;
            return ""
        }, formatEmailMaskPartial: function (e, t, n) {
            if ((e || t) && n) return (e || "") + "...." + (t || "") + "@" + n;
            return ""
        }, upperCase: function (e) {
            return n.upperCase(e)
        }, titleCase: function (e) {
            return (e || "").split(" ").map((function (e) {
                return n.sentenceCase(e)
            })).join(" ")
        }, formatDate: function (e, t) {
            return r(e, t || "l")
        }, formatDateExt: function (e, n, i) {
            return r(t(e, n), i)
        }, formatDateTime: r, formatMoney: function (e, t) {
            var n = 0;
            if (void 0 === e) return "";
            if (isNaN(Number(e))) return e;
            n = "number" == typeof e ? e : Number(e);
            var o = i.commonCurrency(n);
            return !0 === t && (n >= 0 ? o = "<span class='AMOUNTPOS'>" + o + "</span>" : n < 0 && (o = "<span class='EDATALABELV'>" + o + "</span>")), o
        }, formatCurrency: function (e, t) {
            var n = 0;
            return void 0 !== e && e ? isNaN(Number(e)) || Number(e) < 0 ? e : (n = Number(e), i.commonCurrency(n, t || "")) : ""
        }, stripCurrency: function (e) {
            var t = new RegExp("[^\\d.\\-]", "g"), n = e ? e.toString().replace(t, "") : "";
            return Number(n)
        }, changeDateFormat: function (e, n, i) {
            return void 0 === e || void 0 === n ? "" : void 0 === i ? t(e).format(n) : t(e, i, !0).format(n)
        }, formatPhone: function (e) {
            return o.formatPhoneUtility.formatPhone(e)
        }, formatPhoneMask: function (e) {
            return "XXX-XXX-" + (e = e || "").substr(e.length - 4)
        }, nvl: function (e, t) {
            return null == e ? t : e
        }
    };

    function r(e, n) {
        var i;
        return void 0 === e ? "" : ("string" == typeof e && /^[0-9]+$/.test(e) && (i = t(e, "YYYYMMDDHHmmssSSS")), i && i.isValid() || (i = t(e)), i.format(n))
    }

    function a(e, t) {
        return e && t && Object.keys(t).filter((function (e) {
            return t.hasOwnProperty(e)
        })).forEach((function (n) {
            e = e.replace(new RegExp("{{" + n + "}}", "g"), t[n])
        })), e
    }

    function s(e, t) {
        return e ? (e = String(e), /^[xX.]+.*$/.test(e) ? e.replace(/^[xX]/, "...") : e.length > 4 || t ? "..." + e.slice(-4) : "..." + e.slice(-2)) : e
    }
})),define("common/lib/payments/singleDoorUtility", [], (function () {
    "use strict";
    var e, t, n = !1, i = new Promise((function (n, i) {
        e = n, t = i
    }));
    return i.then((function () {
        n = !0
    })).catch((function () {
        n = !0
    })), {
        hasAllPermissions: !1,
        waitForPermissionsToUpdate: i,
        resolvePermissionsPromise: e,
        rejectPermissionsPromise: t,
        promiseResolved: n
    }
})),define("common/lib/payments/skipLinkHelper", ["require", "blue/device/platform", "blue/$", "appkit-utilities/view/util"], (function (e) {
    "use strict";
    var t = e("blue/device/platform"), n = e("blue/$"), i = e("appkit-utilities/view/util");
    return {
        setSkipLinkVisibility: function (e, t, o, r) {
            var a = e.domEvent.keyCode, s = e.domEvent.shiftKey, c = this.context.$(t);
            c.length || (c = n(t));
            var l = r ? this.context.$(r) : void 0, u = this.context.$(o);
            return u.length || (u = n(o)), !i.isSpaceOrEnterKey(e) && (s || 9 !== a || !c.attr("disabled") && !c.prop("disabled") ? (u.hide(), s ? r && l && setTimeout((function () {
                l.focus()
            }), 100) : setTimeout((function () {
                c.focus()
            }), 100), !0) : (u.show().focus(), !0))
        }, hideSkipLinkIos: function () {
            "iOS" === t.os.family && (this.model.showSkipLink = !1)
        }, hideSkipLinkMobile: function () {
            ["iOS", "Android"].indexOf(t.os.family) > -1 && (this.model.showSkipLink = !1)
        }
    }
})),define("common/lib/personalization", ["require", "blue-app/settings", "blue/is", "moment", "blue/store/enumerable/cookie", "blue-app/validate/var/ZIP_CODE_REGEX", "blue/declare"], (function (e) {
    "use strict";
    var t = e("blue-app/settings"), n = e("blue/is"), i = e("moment"),
            o = new (e("blue/store/enumerable/cookie"))(null, !0), r = e("blue-app/validate/var/ZIP_CODE_REGEX");
    return e("blue/declare")({
        constructor: function () {
            var e = t.get("persona");
            this.persona = e || {}
        }, getZipCode: function () {
            var e = o.get("_geoZip");
            return e && "default" !== e && !isNaN(e) || ((e = this.persona.zip) ? (e = e.substring(0, 5), r.test(e) || (e = "default")) : e = "default", o.set("_geoZip", e, null, "/", ".chase.com", !0)), e
        }, getEci: function () {
            return this.persona.ECI || null
        }, getSegment: function () {
            return this.persona.segment || null
        }, getLocale: function () {
            var e = this.persona.locale;
            return e && n.string(e) || (e = null), e && (e = e.replace("_", "-")), e || "en-us"
        }, getGeoImageFreshness: function () {
            var e = parseInt(i().format("M"));
            return o.set("_geoFreshness", e, null, "/", ".chase.com", !0), e
        }
    })
})),define("common/lib/printUtil", ["require", "blue/device/platform", "blue/$"], (function (e) {
    var t = e("blue/device/platform"), n = e("blue/$");
    return function () {
        if ("Safari" === t.name) {
            var e = n("#header-outer-container"), i = n(".accounts-list-fixed-container"), o = n("#pnt-tabs"), r = 0,
                    a = 0, s = 0, c = 0, l = 0;
            e && (r = e.height(), e.height(0)), i && i[0] && (a = i[0].style["padding-top"], s = i[0].style.height, i[0].style["padding-top"] = 0, i[0].style.height = 0), o && o[0] && (c = o.height(), l = o[0].style.top, o.height(0), o[0].style.top = 0), window.print(), e && e.height(r), i && i[0] && (i[0].style["padding-top"] = a, i[0].style.height = s), o && o[0] && (o.height(c), o[0].style.top = l)
        } else window.print()
    }
})),define("common/lib/profileRouteMapping", ["blue/log"], (function (e) {
    "use strict";
    var t = e("[profileRotueMapping]"), n = [{
        legacyAction: "overview.updateMyOverviewDetails",
        newAction: "overview.requestMyProfileOverview",
        newRoute: "#/dashboard/myProfileOverview/overview/requestMyProfileOverview"
    }, {
        legacyAction: "overview.updateMyUserId",
        newAction: "resetUserId.resetUserId",
        newRoute: "#/dashboard/myProfileSignInSecurity/resetUserId/resetUserId"
    }, {
        legacyAction: "overview.updateMyPassword",
        newAction: "resetPassword.resetPassword",
        newRoute: "#/dashboard/myProfileSignInSecurity/resetPassword/resetPassword"
    }, {
        legacyAction: "iPAddress.requestMyIPAddress",
        newAction: "myIPAddress.requestMyIPAddress",
        newRoute: "#/dashboard/myProfileSignInSecurity/myIPAddress/requestMyIPAddress"
    }, {
        legacyAction: "index.index",
        newAction: "overview.requestMyProfileOverview",
        newRoute: "#/dashboard/myProfileOverview/overview/requestMyProfileOverview"
    }, {
        legacyAction: "menu.index",
        newAction: "overview.requestMyProfileOverview",
        newRoute: "#/dashboard/myProfileOverview/overview/requestMyProfileOverview"
    }, {
        legacyAction: "emailAddressDetails.selectMyEmailDetails",
        newAction: "emailAddressDetails.selectMyEmailDetails",
        newRoute: "#/dashboard/myProfilePersonalDetails/emailAddressDetails/selectMyEmailDetails",
        featureFlag: "newEmailPageEnabled"
    }, {
        legacyAction: "accessSecurity.requestAccessSecurityHub",
        newAction: "accountSafeOverview.requestAccountSafeOverview",
        newRoute: "#/dashboard/myProfileAccountSafe/accountSafeOverview/requestAccountSafeOverview"
    }, {
        legacyAction: "myDevices.requestEnrolledDevices",
        newAction: "myDevices.requestEnrolledDevices",
        newRoute: "#/dashboard/myProfileAccountSafe/myDevices/requestEnrolledDevices",
        featureFlag: "newMyDevicesPageEnabled"
    }, {
        legacyAction: "securityCode.updateSecurityCode",
        newAction: "mySecurityCode.requestSecurityCode",
        newRoute: "#/dashboard/myProfileSignInSecurity/mySecurityCode/requestSecurityCode",
        featureFlag: "newSecurityCodePageEnabled"
    }, {
        legacyAction: "privacyPreferences.updateMyPrivacyPreferences",
        newAction: "privacyPreferences.updateMyPrivacyPreferences",
        newRoute: "#/dashboard/myProfilePersonalDetails/privacyPreferences/updateMyPrivacyPreferences",
        featureFlag: "newPrivacyPreferencesPageEnabled"
    }];

    function i(e, t) {
        return n.reduce((function (n, i) {
            return n || (i[t] === e ? i : null)
        }), null)
    }

    return {
        applyFeatureFlagConfiguration: function (e) {
            n.filter((function (e) {
                return !!e.featureFlag
            })).forEach((function (t) {
                t.isNewExperienceEnabled = e[t.featureFlag].getFeatureFlagValueSync()
            }))
        }, getPageRedirectStatus: function (e) {
            var t = i(e, "newAction");
            return !!t && !1 !== t.isNewExperienceEnabled
        }, getLegacyRouteOverride: function (e) {
            var t = i(e, "newAction");
            return !!t && "/" + ["dashboard", "profile"].concat(t.legacyAction.split(".")).join("/")
        }, getNewRouteOverride: function (e) {
            var n = i(e, "legacyAction");
            return n && !n.featureFlag && t.warn("deprecated profile action detected", e), !(!n || !1 === n.isNewExperienceEnabled) && n.newRoute
        }, alignToMenuRoutes: function (e, t) {
            var n = i(e.controller.name + "." + e.action.name, "legacyAction");
            return n ? t.routeToObject(n.newRoute) : e
        }
    }
})),define("common/lib/progressBar", [], (function () {
    "use strict";
    return {
        setProgressBarStep: function (e, t, n, i) {
            var o = [];
            e.context.dcu.dynamicSettings.set(e, "progressBarLabel", t), e.context.dcu.dynamicContent.set(e, "progressBarAda", {
                progressBarCurrentStep: n,
                progressBarTotalSteps: i
            });
            for (var r = 0; r < i; r++) o.push({accessibleText: "", active: r < n});
            e.model.set("progressBar", o)
        }
    }
})),define("common/utility/plugin", [], (function () {
    "use strict";
    return {
        isInstalled: function (e) {
            return this.getInfo(e).isInstalled
        }, getVersion: function (e) {
            return this.getInfo(e).version
        }, getInfo: function (e) {
            var t = this.PLUGINS[e], n = !1, i = null;
            if (this.supportsNavigatorPlugins()) {
                var o = this.findNavigatorPluginByName("RealPlayer" === e ? "RealPlayer Version Plugin" : e);
                o && (n = !0, i = this.getVersionFromPlugin(o))
            } else if (n = this.hasActiveXObject(this.PLUGINS[e] && this.PLUGINS[e].progID)) if (this.PLUGINS[e].getActiveXVersionInfo) i = this.PLUGINS[e].getActiveXVersionInfo(); else {
                var r = this.getProgIdForActiveXObject(this.PLUGINS[e].progID);
                i = this.getVersionFromPlugin(r)
            } else (i = this.getActiveXPluginByClassId(this.PLUGINS[e] && this.PLUGINS[e].classID)) && (i = i.replace(/,/g, ".")), n = null != i;
            var a = {};
            for (var s in t) Object.hasOwnProperty.call(t, s) && (a[s] = t[s]);
            return a.isInstalled = n, a.version = i, a.name = e, a
        }, PLUGINS: {
            Acrobat: {
                description: "Adobe Acrobat Plugin",
                progID: ["PDF.PdfCtrl.7", "PDF.PdfCtrl.6", "PDF.PdfCtrl.5", "PDF.PdfCtrl.4", "PDF.PdfCtrl.3", "AcroPDF.PDF.1"],
                classID: "CA8A9780-280D-11CF-A24D-444553540000"
            },
            QuickTime: {
                description: "QuickTime Plug-in",
                progID: ["QuickTimeCheckObject.QuickTimeCheck.1", "QuickTime.QuickTime"],
                classID: "02BF25D5-8C17-4B23-BC80-D3488ABDDC6B",
                getActiveXVersionInfo: function () {
                    var e = this.getProgIdForActiveXObject(this.PLUGINS.QuickTime.progID), t = new ActiveXObject(e),
                            n = t && t.QuickTimeVersion ? t.QuickTimeVersion.toString(16) : "";
                    return n.substring(0, 1) + "." + n.substring(1, 2) + "." + n.substring(2, 3)
                }
            },
            DivX: {
                description: "DivX Browser Plugin",
                progID: ["npdivx.DivXBrowserPlugin.1", "npdivx.DivXBrowserPlugin"],
                classID: "67DABFBF-D0AB-41fa-9C46-CC0F21721616"
            },
            Director: {
                description: "Macromedia Director",
                progID: ["SWCtl.SWCtl.11", "SWCtl.SWCtl.10", "SWCtl.SWCtl.9", "SWCtl.SWCtl.8", "SWCtl.SWCtl.7", "SWCtl.SWCtl.6", "SWCtl.SWCtl.5", "SWCtl.SWCtl.4"],
                classID: "166B1BCA-3F9C-11CF-8075-444553540000"
            },
            Flash: {
                description: "Macromedia Shockwave Flash",
                progID: ["ShockwaveFlash.ShockwaveFlash.9", "ShockwaveFlash.ShockwaveFlash.8.5", "ShockwaveFlash.ShockwaveFlash.8", "ShockwaveFlash.ShockwaveFlash.7", "ShockwaveFlash.ShockwaveFlash.6", "ShockwaveFlash.ShockwaveFlash.5", "ShockwaveFlash.ShockwaveFlash.4"],
                classID: "D27CDB6E-AE6D-11CF-96B8-444553540000"
            },
            VLC: {description: "VLC multimedia plugin", progID: [], classID: ""},
            "Windows Media": {
                description: "Windows Media Player Plug-in Dynamic Link Library",
                progID: ["WMPlayer.OCX", "MediaPlayer.MediaPlayer.1"],
                classID: "22D6f312-B0F6-11D0-94AB-0080C74C7E95",
                getActiveXVersionInfo: function () {
                    var e = this.getProgIdForActiveXObject(this.PLUGINS["Windows Media"].progID),
                            t = new ActiveXObject(e);
                    return t && t.versionInfo ? t.versionInfo : ""
                }
            },
            Java: {description: "Java Virtual Machine", progID: [], classID: "08B0E5C0-4FCB-11CF-AAA5-00401C608500"}
        }, supportsNavigatorPlugins: function () {
            return navigator.plugins && navigator.plugins.length > 0
        }, findNavigatorPluginByName: function (e) {
            if (this.supportsNavigatorPlugins()) for (var t = 0; t < navigator.plugins.length; ++t) {
                var n = navigator.plugins[t];
                if (-1 !== n.name.indexOf(e)) return n
            }
            return null
        }, getIEClientCaps: function () {
            var e = document.getElementById("__Plugin_ClientCaps");
            return e || ((e = document.createElement("DIV")).id = "__Plugin_ClientCaps", e.addBehavior && (e.addBehavior("#default#clientCaps"), document.body.appendChild(e)), e = document.getElementById("__Plugin_ClientCaps")), e
        }, getActiveXPluginByClassId: function (e) {
            if (!e) return null;
            e.match(/{[^}]+}/) || (e = "{" + e + "}");
            var t = this.getIEClientCaps();
            try {
                return t.getComponentVersion(e, "ComponentID") || null
            } catch (e) {
            }
            return null
        }, hasActiveXObject: function (e) {
            return null != (e = this.getProgIdForActiveXObject(e))
        }, getProgIdForActiveXObject: function (e) {
            if (!e) return null;
            for (var t = 0; t < e.length; t++) try {
                return e[t] || null
            } catch (e) {
            }
            return null
        }, getVersionFromPlugin: function (e) {
            e.name || (e = {name: e, description: name});
            var t = /[\d][\d.]*/.exec(e.name);
            return t && -1 === e.name.indexOf("Java") || (t = /[\d.]+/.exec(e.description)) ? t[0] : ""
        }
    }
})),define("common/utility/deviceSignature", ["require", "common/utility/plugin", "blue/is"], (function (e) {
    "use strict";
    var t = e("common/utility/plugin"), n = e("blue/is");
    return {
        getDeviceSignature: function () {
            var e = {};
            return e.navigator = this.getNavigatorProps(), e.plugins = this.getPlugins(), e.screen = this.getScreenProps(), e.extra = this.getExtraProps(), JSON.stringify(e)
        }, getNavigatorProps: function () {
            var e = {};
            for (var t in navigator) if (n.defined(navigator[t])) try {
                var i = navigator[t];
                "boolean" != typeof i && "number" != typeof i && "string" != typeof i && null !== i || (e[t] = i)
            } catch (e) {
            }
            return e
        }, getPlugins: function () {
            for (var e = [], n = new Array("Acrobat", "QuickTime", "DivX", "Director", "Windows Media", "Flash", "Java", "VLC"), i = 0, o = 0; o < n.length; o++) try {
                if (t.isInstalled(n[o])) {
                    var r = {}, a = t.getInfo(n[o]);
                    null != a && (r.name = a.description, r.version = a.version, e[i++] = r)
                }
            } catch (e) {
            }
            return e
        }, getScreenProps: function () {
            for (var e = ["availHeight", "availWidth", "colorDepth", "height", "pixelDepth", "width"], t = {}, n = 0; n < e.length; n++) try {
                var i = screen[e[n]];
                null != i && (t[e[n]] = i)
            } catch (e) {
            }
            return t
        }, getExtraProps: function () {
            var e = {}, t = null;
            if ("Microsoft Internet Explorer" === navigator.appName) try {
                t = ScriptEngineMajorVersion() + "." + ScriptEngineMinorVersion() + "." + ScriptEngineBuildVersion()
            } catch (e) {
            }
            null != t && (e.vbscript_ver = t), e.javascript_ver = "2.0";
            try {
                var n = new Date, i = n.toString();
                i.indexOf("PDT") > 0 || i.indexOf("MDT") > 0 || i.indexOf("CDT") > 0 || i.indexOf("EDT") > 0 || i.indexOf("Daylight") > 0 ? e.timezone = n.getTimezoneOffset() + 60 : e.timezone = n.getTimezoneOffset()
            } catch (t) {
                e.timezone = ""
            }
            return e
        }
    }
})),define("common/lib/randomize", ["require", "common/utility/deviceSignature"], (function (e) {
    "use strict";
    var t = e("common/utility/deviceSignature");
    return function (n) {
        var i = function (e) {
            return n.settings.get(e)
        }, o = function (e, t) {
            n.settings.set(e, t)
        }, r = function (e) {
            n.settings.remove(e)
        }, a = function () {
            return {
                siteId: i("defaultAuthSiteId"),
                userId: void 0,
                passwd: null,
                passwd_org: void 0,
                contextId: i("authLoginContextId"),
                deviceId: i("authDeviceId"),
                deviceSignature: t.getDeviceSignature(),
                deviceCookie: (n.config || {}).authDeviceCookie || i("defaultAuthDeviceCookie"),
                tokencode: i("authTokenCode"),
                externalData: i("authExternalData")
            }
        }, s = function () {
            var e = Math.floor(9e4 * Math.random()) + 1e4;
            return e += (new Date).getTime()
        };
        return {
            getSetting: i,
            setSetting: o,
            removeSetting: r,
            getRequestData: a,
            getRandomValue: s,
            invokeRandomizeCall: function (t) {
                e(["blue/dom"], (function (e) {
                    if (!(e.location.hash.indexOf("logoff") >= 0)) {
                        var n = a(),
                                c = {type: i("authResponseType"), auth_reqid: s(), auth_siteId: i("defaultAuthSiteId")},
                                l = {};
                        t.auth.randomizecache(c).then((function (e) {
                            e.forEach((function (e) {
                                n && n[e.inputId] ? l[e.randomParameter] = n[e.inputId] : l[e.randomParameter] = e.inputValue || null
                            })), l.type = i("authResponseType"), o("randomizedRequest", l), setTimeout((function () {
                                r("randomizedRequest")
                            }), i("randomizeCacheExpiration"))
                        }))
                    }
                }))
            }
        }
    }
})),define("common/lib/rewards/rewardsNavigationMixin", ["require", "common/lib/constants"], (function (e) {
    return function () {
        var t = this, n = e("common/lib/constants"), i = !1;
        t.getRewardNavigationObj = function (e) {
            var o = {
                CHASE_LOYALTY: {
                    navKey: i ? "chaseLoyaltySeeDetails" : "chaseLoyaltyRewardDetails",
                    params: {accountId: {params: t.accountId}}
                },
                REWARDS: {
                    navKey: "cardRewardsdetailsUrl",
                    focusTarget: "#rewardsDetailsInfoHeader",
                    params: {accountId: {accountId: t.accountId}}
                },
                DEFAULT: {navKey: "rewardsdetailUrl", params: {append: {params: t.accountId}}}
            };
            return n.CHASE_LOYALTY_CARDS.indexOf(e) > -1 ? o[n.REWARD_TYPE.CHASE_LOYALTY] : o[t.context.config.enableNavigationToClassicRewards ? n.REWARD_TYPE.DEFAULT : n.REWARD_TYPE.REWARDS]
        }, t.navigateToRewardsDetailsPage = function (e, n) {
            i = n || !1, t.context.application.broadcast("makeNavigation", t.getRewardNavigationObj(e))
        }, t.navigateToRewardsProgramDetails = function (e) {
            if (t.context.config.enableNavigationToClassicRewards) {
                var n = t.context.settings.get("RewardsProgramDetail") + "," + t.accountId;
                t.context.state(n)
            } else t.navigateToRewardsDetailsPage(e, !1)
        }
    }
})),define("common/lib/routeUtil", ["require", "blue/util"], (function (e) {
    "use strict";
    var t = {name: "", params: {}}, n = {app: t, area: t, controller: t, action: t, query: {string: "", params: {}}},
            i = e("blue/util").lang.defaults;
    return {
        isDefaultRoute: function (e) {
            return /^(#?(\/(dashboard(\/(index(\/(index)?)?)?)?)?)?)?$/.test(e)
        }, routeChangeScope: function (e, t) {
            var o = 0;
            if (e = i(e, n), t = i(t, n), "string" == typeof e || "string" == typeof t) throw new TypeError("Route must be provided as objects. Consider using context.routeToObject() before passing them into this function.");
            return t.app.name !== e.app.name && (o |= 16), t.area.name !== e.area.name && (o |= 8), t.controller.name !== e.controller.name && (o |= 4), t.action.name !== e.action.name && (o |= 2), t.query.string !== e.query.string && (o |= 1), o
        }
    }
})),define("common/lib/thirdPartyAccess/contentFormatter", ["blue/util", "blue/is", "common/utility/rapidash", "common/lib/constants"], (function (e, t, n, i) {
    "use strict";
    var o = i.ACCESS_STATUS;

    function r(e, t) {
        n.bindMethods(this), this.components = t, this.context = e
    }

    function a(e, t) {
        r.call(this, e.context, [e]), this.component = e, this.settings = t;
        var n = e.context.dcu.dynamicContent.getVariations(this.component, this.settings.tooltipContent);
        this.categoryTooltipVariations = Object.keys(n || {}).map((function (e) {
            return e.split(".").pop()
        }))
    }

    return r.prototype.getContent = function (e, t, n, i) {
        var o = this.context.dcu.dynamicSettings;
        return (i ? [i] : this.components).reduce((function (i, r) {
            return i || o.get(r, e, t, n) || ""
        }), "")
    }, r.prototype.formatContentMap = function (n) {
        var i = this;
        return Object.keys(e.object.filter(n, t.string)).forEach((function (e) {
            var t = /\[\[(.*)]]/.exec(n[e]);
            if (t) {
                var o = t[1].split("."), r = o[0], a = o[1];
                n[e] = i.getContent(r, a)
            }
        })), n
    }, n.subclass(r, a), a.prototype.isTooltipEligible = function (e, t) {
        return t ? (t.accessStatus === o.ACTIVE || t.accessStatus === o.DISABLED) && this.categoryTooltipVariations.includes(e) && !!this.getContent(this.settings.tooltipContent, e) : this.categoryTooltipVariations.includes(e)
    }, a.prototype.getContentModel = function (e, n) {
        var i = e[n] || {}, o = {thirdPartyAccessDetails: {thirdPartyApplicationName: e.content.displayName}};
        return Object.keys(i).map((function (r) {
            var a = {};
            return a[n] = i, a.displayName = i[r], a.tooltip = this.isTooltipEligible(r, e.accessToken) ? {
                id: e.applicationId + "-accessCategoryTooltip-" + r,
                action: t.function(this.settings.getAction) ? this.settings.getAction(r) : void 0,
                content: this.getContent(this.settings.tooltipContent, r, o),
                adaText: this.getContent(this.settings.adaText, r)
            } : null, a
        }), this)
    }, {ContentFormatter: r, AccessCategoryFormatter: a}
})),define("common/lib/thirdPartyAccess/service/aggregatorContent", ["require", "blue/util", "blue/deferred"], (function (e) {
    "use strict";
    var t = e("blue/util"), n = e("blue/deferred");
    return function (e) {
        var i = {}, o = {
            around: function (e) {
                var t = e.args[0].url;
                if (i[t]) return i[t];
                var o = new n;
                return i[t] = o.promise, e.proceed().then((function (e) {
                    return e || delete i[t], o.resolve(e), o.promise
                })).catch((function (e) {
                    return delete i[t], o.reject(e), o.promise
                }))
            }
        };

        function r(e) {
            return {
                settings: t.object.merge({
                    type: "GET",
                    disableCsrf: !0,
                    headers: {"x-jpmc-csrf-token": null},
                    xhrFields: {withCredentials: !1}
                }, a[e])
            }
        }

        var a = {
            getPermissions: {
                url: "https://{{domain}}/content/aggregator/digital-ui/{{language}}/general-permissions.json",
                handleSuccess: function (t) {
                    if (!(t instanceof Array)) throw e.logger.error("AEM permissions service failed", t), new Error("AEM permissions service failed");
                    return n = "permissionCd", i = "permissionDesc", (t || []).reduce((function (e, t) {
                        return e[t[n]] = t[i], e
                    }), {});
                    var n, i
                }
            },
            getContent: {
                url: "https://{{domain}}/content/aggregator/digital-ui/{{language}}/{{apiAppId}}.json",
                handleSuccess: function (e) {
                    if (!e.success) throw new Error("AEM content service failed", e);
                    var t = e.result.aggregatorapp, n = t.aggregator;
                    return {
                        apiAppId: t.name,
                        displayName: t.title,
                        logo: t.logo,
                        message: t.bodyText,
                        aggregatorName: n && n.title,
                        aggregatorLogo: n && n.logo
                    }
                }
            }
        };
        this.serviceInterceptors = [o], this.serviceCalls = {
            getPermissions: r("getPermissions"),
            getContent: r("getContent")
        }
    }
})),define("common/lib/utility/serviceUtil", ["require", "blue/util", "blue/$", "blue/is"], (function (e) {
    "use strict";
    var t = e("blue/util"), n = e("blue/$"), i = e("blue/is"), o = {
        callService: function (e, t) {
            return this.controller = e, this.services = t, function (n, i, o) {
                o = o || {}, i && (o.model = i.model);
                var r = t[n](o);
                return e.context.area.emit("serviceCall", {data: {promise: r}}), e.context.area.emit(n, {data: {promise: r}}), r
            }
        }, hasNotMappedCode: function (e) {
            return !!e.statusCodes && e.statusCodes.indexOf("NOT_MAPPED") > -1 || "NOT_MAPPED" === e.statusCode
        }, hasAllFailedServices: function (e) {
            var t = e.successServices || [], n = (e.failedServices || []).length > 0, i = 0 === t.length;
            return n && i
        }, isFailed: function (e) {
            return "ERROR" === e.code || o.hasAllFailedServices(e) || o.hasNotMappedCode(e)
        }, rejectFailedResponse: function (e) {
            return o.isFailed(e) ? Promise.reject(e) : e
        }, handleServiceErrorMixin: function (e, n) {
            var i = Object.keys(n), r = n.defaultMessage ? "defaultMessage" : void 0;
            return e.handleServiceError = function (e, a, s, c) {
                var l = e.statusCodes || [e.statusCode], u = t.array.intersection(a, i, l).shift();
                if ("string" == typeof u || o.isFailed(e)) {
                    var d = u || c || r;
                    if (d) {
                        var m = Object.assign({}, n[d]), f = this.context.dcu.dynamicContent;
                        m.errorHeader = f.get(this, m.headerKey + "." + m.headerVariation), m.errorAdvisory = f.get(this, m.advisoryKey + "." + m.advisoryVariation), s.call(this, m)
                    } else s.call(this);
                    return !0
                }
                return !1
            }.bind(e), e.handleServiceError
        }, DPSEncodeArray: function (e, n) {
            return n.map((function (n, i) {
                return t.string.interpolate("{{key}}[{{index}}]={{item}}", {key: e, item: n, index: i})
            })).join("&")
        }, DPSEncodeParams: function (e) {
            return Object.keys(e).map((function (t) {
                var i = e[t];
                if (i instanceof Array) return o.DPSEncodeArray(t, i) || void 0;
                var r = {};
                return r[t] = i, n.param(r)
            })).filter(i.defined).join("&")
        }
    };
    return o
})),define("common/lib/thirdPartyAccess/thirdPartyAppDetailProvider", ["blue/util", "common/lib/utility/serviceUtil", "blue/is", "common/utility/rapidash", "appkit-utilities/language/helper"], (function (e, t, n, i, o) {
    "use strict";

    function r(e) {
        i.bindMethods(this), this.contentService = e.context.services.aggregatorContent, this.language = o.getContentLanguage(), this.contentUrl = (window.cq5Url || "").split("://").pop(), this.logger = e.context.logger, this.appDetailBuilders = []
    }

    return r.prototype.use = function (e) {
        return Array.prototype.push.apply(this.appDetailBuilders, e), this
    }, r.prototype.buildAppDetail = function (e) {
        return e && e.apiAppId ? this.appDetailBuilders.reduce((function (t, n) {
            return t.then((function (t) {
                return n(t, e)
            }))
        }), Promise.resolve({
            apiAppId: e.apiAppId,
            applicationId: e.apiAppId.replace(/-/g, "_").replace(/[^A-Za-z0-9_]/g, ""),
            content: null
        })) : Promise.reject(new TypeError("Invalid app detail params! At least apiAppId is required: " + JSON.stringify(e)))
    }, r.prototype.setThirdPartyAccessDetail = function (t, n) {
        return Object.assign(t, e.object.pick(n, ["appVersion", "requiresQPEnrollment", "eligibleAccounts", "futureAccountsAccess", "sharePersonalInformationState", "virtualAccountNumberEligible"]))
    }, r.prototype.setAccessToken = function (t, n) {
        return Object.assign(t, {accessToken: Object.assign({applicationId: t.applicationId}, e.object.pick(n, ["token", "thirdPartyChannelId", "accessStartDate", "accessEndDate", "lastAccessed", "accessStatus", "removeAccessAllowed"]))})
    }, r.prototype.setAccessCategoryContent = function (e, t) {
        return this.contentService.getPermissions(this.getContentParams()).then((function (n) {
            return e.accessCategories = i.pick(n, t.accessCategoryList || []), e.removedAccessCategories = i.pick(n, t.removedAccessCategoryList || []), e.addedAccessCategories = i.pick(n, t.addedAccessCategoryList || []), e
        }))
    }, r.prototype.setThirdPartyAppContent = function (e) {
        var t = this.getContentParams({apiAppId: e.apiAppId});
        return this.contentService.getContent(t).then((function (t) {
            return e.content = t, e
        })).catch(this.handleContentFailure)
    }, r.prototype.handleContentFailure = function (e) {
        var t = (e.url || "").split("/").pop();
        return !e || 400 !== e.status && 404 !== e.status || this.logger.error("AEM Content Load for " + t + " failed! Ensure the app has been onboarded properly, this is unlikely to be a CXO defect."), Promise.reject(new Error(e.responseText || "Failed to load application content for " + t + "!"))
    }, r.prototype.getContentParams = function (e) {
        return Object.assign({clearParameters: !0, domain: this.contentUrl, language: this.language}, e || {})
    }, r
})),define("common/lib/urlParamUtil", ["require", "blue/is"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = /^[a-zA-Z0-9-_]{1,30}$/, i = /^[a-zA-Z0-9-_]+$/;

    function o(e, o) {
        var r;
        if (!t.string(o) || !n.test(o)) return !1;
        if (r = e[o], !t.string(r)) return !1;
        var a = decodeURIComponent(r), s = {};
        if (!this) throw new Error('isSafe called without being bound to "this"');
        var c = this.paramConfigOverrides;
        if (t.plainObject(c)) {
            var l = c[o];
            t.plainObject(l) && (s = l)
        }
        var u = s.maxLength || 30;
        return !(a.length > u) && function (e, n) {
            var o = e.validationRegex || i;
            return t.string(o) && (o = new RegExp(o)), !!o.test(n)
        }(s, a)
    }

    var r = function (e) {
        this.paramConfigOverrides = e || {}
    };
    return r.prototype.getParameterByName = function (e, t) {
        t || (t = window.location.href), e = e.replace(/[[\]]/g, "\\$&");
        var n = new RegExp("[?&]" + e + "(=([^&#]*)|&|#|$)", "i").exec(t);
        return n ? n[2] ? decodeURIComponent(n[2].replace(/\+/g, " ")) : "" : null
    }, r.prototype.insert = function (e, n, i) {
        var o, r, a = e, s = function (e) {
            try {
                return JSON.stringify(e)
            } catch (e) {
                return "not an object"
            }
        }, c = function (t) {
            var o = s(n), r = s(i);
            throw new Error([t, e, o, r].join(" "))
        };
        return t.string(e) || c("invalid url template"), t.plainObject(n) || c("invalid params"), t.plainObject(i) || c("invalid constraints"), o = Object.keys(n), Object.keys(i).forEach((function (e) {
            var t = -1 !== o.indexOf(e) ? encodeURIComponent(n[e]) : "";
            r = new RegExp("{{" + e + "}}", "g"), a = a.replace(r, t)
        })), a
    }, r.prototype.serialize = function (e) {
        return encodeURIComponent(JSON.stringify(this._secureUrlParams(e)))
    }, r.prototype.deserialize = function (e) {
        return this._validateUrlParams(JSON.parse(decodeURIComponent(e)))
    }, r.prototype._secureUrlParams = function (e) {
        var t, n = Object.keys(e);
        return n = (n = n.filter(o.bind(this, e))).slice(0, 20), t = {}, n.forEach((function (n) {
            t[n] = e[n]
        })), t
    }, r.prototype._validateUrlParams = function (e) {
        var n;
        if (!t.plainObject(e)) throw new Error("urlParams are not an object");
        if ((n = Object.keys(e)).length > 20) throw new Error("urlParams has too many params");
        return n.forEach((function (t) {
            if (!o.call(this, e, t)) throw new Error("urlParam is not safe")
        }), this), e
    }, r
})),define("common/lib/userInfo/accountInfo", ["require", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/util").object, n = {}, i = [], o = {}, r = {}, a = {
        default: {
            accountId: "accountId",
            accountName: "nickname",
            accountType: "accountTileType",
            detailType: "accountTileDetailType",
            accountMaskNumber: "mask",
            cardType: "cardType",
            accountOriginationCode: "accountOriginationCode",
            rewardProgramCode: "rewardProgramCode",
            isControlCard: "isControlCard"
        },
        accounts: {
            accountId: "id",
            accountName: "nickname",
            accountType: "categoryType",
            detailType: "accountType",
            accountMaskNumber: "mask"
        },
        gwmaccounts: {
            accountId: "accountId",
            accountName: "nickname",
            accountType: "accountCategoryType",
            detailType: "detailType",
            accountMaskNumber: "mask",
            controlCard: "controlCard",
            isControlCard: "controlCard"
        },
        overviewAccounts: {
            accountId: "id",
            accountName: "nickname",
            accountType: "groupType",
            detailType: "detailType",
            accountMaskNumber: "mask",
            isControlCard: "controlCard"
        }
    }, s = {DEPOSIT: "DDA"}, c = {ALA: "AUTOLOAN", ALS: "AUTOLEASE", HMG: "MORTGAGE"}, l = function () {
    };
    return l.prototype.updateProductInfo = function (e) {
        e.productInfos && e.productInfos.forEach((function (e) {
            e.productId && e.accountId && (o[e.accountId] = e.productId)
        }))
    }, l.prototype.updateAccountList = function (e, i) {
        if (e && e.length) {
            var r = a[i] || a.default;
            e.forEach((function (e) {
                var i = {};
                Object.keys(r).forEach((function (t) {
                    e[r[t]] && (i[t] = e[r[t]])
                })), o[e.accountId] && (i.productId = o[e.accountId], i.productCode = function (e) {
                    var t;
                    if (e) {
                        var n = e.split("-");
                        3 === n.length && (t = n[2])
                    }
                    return t
                }(i.productId)), s[i.accountType] && (i.accountType = s[i.accountType]), c[i.detailType] && (i.accountType = c[i.detailType]), e.tileDetail && (i.unfunded = e.tileDetail.unfunded);
                var a = e[r.accountId];
                n[a] ? n[a] = t.merge(n[a], i) : n[a] = i
            }))
        }
    }, l.prototype.updateCreditJourneyTile = function (e) {
        r = e
    }, l.prototype.getAccountById = function (e) {
        return n[e] ? t.deepClone(n[e]) : {}
    }, l.prototype.getAccountProductId = function (e) {
        return o[e] || ""
    }, l.prototype.getAccountsList = function () {
        return i.length || Object.keys(n).forEach((function (e) {
            i.push(n[e])
        })), t.deepClone(i)
    }, l.prototype.getCreditJourneyTile = function () {
        return r ? t.deepClone(r) : {}
    }, l
})),define("common/lib/userInfo/profileInfo", ["require", "blue/siteData", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/siteData"), n = e("blue/util").object, i = {
                profileType: {
                    personal: ["PER", "MUG"],
                    gemini: ["GEM", "GMC", "GNC", "GNM"],
                    multiTin: ["MUL", "GNM", "GMC"],
                    multiChannel: ["CHN"]
                },
                segmentType: {
                    commercial: ["CML", "CRE"],
                    smallBusiness: ["BMG", "BMS", "BPL", "BPS", "BOH", "BOS"],
                    businessBanking: ["BOH", "BMG", "BPL", "PVB", "WTH"],
                    jpmSecurities: ["PCB"],
                    privateBanking: ["PVB", "WTH"],
                    privateClient: ["PAF"],
                    cpoUser: ["CCI", "POH", "CCO", "PHP", "LCO", "AUT", "PMT", "PPL", "TLG", "PPP", "HEQ"],
                    languagePreferenceUnavailable: ["PCB", "WTH", "CML", "CRE", "PVB", "PCD"]
                },
                userType: {subUser: ["SU"]}
            }, o = {
                brandId: "brandId",
                userType: "userType",
                personId: "personId",
                profileId: "profileId",
                jumbo: "jumbo",
                segmentType: "segment",
                profileType: "profileType",
                currentServicePlan: "currentServicePlan",
                defaultLandingPage: "defaultLandingPage",
                productInfos: "productInfos",
                accountTypes: "accountTypes",
                zipCode: "zipCode",
                accountHolder: "accountHolder",
                applicant: "applicant"
            }, r = {JPMORGAN: "JPO", WEALTH: "CPO", PERSONAL: "CPO", BUSINESS: "CBO", COMMERCIAL: "CBO"}, a = {},
            s = function () {
            };
    return s.prototype.update = function (e, t) {
        e && Object.keys(o).forEach((function (t) {
            a[t] = e[o[t]]
        })), t && (a = Object.assign(a, t))
    }, s.prototype.getProfile = function () {
        return n.deepClone(a)
    }, s.prototype.getProspectType = function () {
        return !1 === a.accountHolder && !1 === a.applicant ? "CJ" : null
    }, s.prototype.getCurrentDateTime = function () {
        return a.currentDateTime
    }, s.prototype.hasAccountType = function (e) {
        var n = t.getData("accountTypes");
        return n && n.indexOf(e) > -1
    }, s.prototype.hasProduct = function (e) {
        var t = a.productInfos && a.productInfos.map((function (e) {
            return e.productId && e.productId.split("-")[0]
        }));
        return t && t.indexOf(e) > -1
    }, s.prototype.isBusinessUser = function () {
        return this.isCommercialUser() || this.isSmallBusinessUser()
    }, s.prototype.isPersonalUser = function () {
        return this._isAvailableinMapperList("profileType", "personal")
    }, s.prototype.isCommercialUser = function () {
        return this._isAvailableinMapperList("segmentType", "commercial")
    }, s.prototype.isBusinessBankingUser = function () {
        return this._isAvailableinMapperList("segmentType", "businessBanking")
    }, s.prototype.islanguagePreferenceUnavailableForUser = function () {
        return this._isAvailableinMapperList("segmentType", "languagePreferenceUnavailable")
    }, s.prototype.isJPOUser = function () {
        return this.isPrivateBankUser() || this.isJPMSecuritiesUser()
    }, s.prototype.isJumboUser = function () {
        return !!a.jumbo
    }, s.prototype.isGWMUser = s.prototype.isJPOUser, s.prototype.isJPMSecuritiesUser = function () {
        return this._isAvailableinMapperList("segmentType", "jpmSecurities")
    }, s.prototype.isPrivateBankUser = function () {
        return this._isAvailableinMapperList("segmentType", "privateBanking")
    }, s.prototype.isPrivateClientUser = function () {
        return this._isAvailableinMapperList("segmentType", "privateClient")
    }, s.prototype.isCPOUser = function () {
        return this._isAvailableinMapperList("segmentType", "cpoUser")
    }, s.prototype.isSmallBusinessUser = function () {
        return this._isAvailableinMapperList("segmentType", "smallBusiness")
    }, s.prototype.isGeminiUser = function () {
        return this._isAvailableinMapperList("profileType", "gemini")
    }, s.prototype.isMultiTinUser = function () {
        return this._isAvailableinMapperList("profileType", "multiTin")
    }, s.prototype.isFinancialAdvisor = function () {
        return t.getData("userType") && t.getData("userType").indexOf("FinancialAdvisor") > -1
    }, s.prototype.isInterestedParty = function () {
        return t.getData("userType") && t.getData("userType").indexOf("InterestedParty") > -1
    }, s.prototype.isEmulationUser = function () {
        return this.isFinancialAdvisor() || this.isInterestedParty()
    }, s.prototype.isGWMEmulationMode = function () {
        return this.isEmulationUser() && this.isGWMUser()
    }, s.prototype.isBrokerage2 = function () {
        var e = t.getData("accountTypes");
        return e && e.indexOf("BR2") > -1
    }, s.prototype.isManagedBrokerage = function () {
        var e = t.getData("accountTypes");
        return e && e.indexOf("WR2") > -1
    }, s.prototype.isMargin = function () {
        var e = t.getData("accountTypes");
        return e && e.indexOf("MAR") > -1
    }, s.prototype.isOlympic = function () {
        var e = t.getData("accountTypes");
        return e && e.indexOf("MAN") > -1
    }, s.prototype.isEligibleForRealTimeData = function () {
        return this.isBrokerage2() || this.isManagedBrokerage() || this.isMargin() || this.isOlympic()
    }, s.prototype.isOLCUser = function () {
        return a.accountTypes.indexOf("OLC") > -1
    }, s.prototype.isMultiChannelUser = function () {
        return this._isAvailableinMapperList("profileType", "multiChannel")
    }, s.prototype.isSubUser = function () {
        return this._isAvailableinMapperList("userType", "subUser")
    }, s.prototype.getSegment = function () {
        return a.segmentType
    }, s.prototype.getDefaultLandingPage = function () {
        return a.defaultLandingPage
    }, s.prototype._isAvailableinMapperList = function (e, t) {
        return -1 !== i[e][t].indexOf(a[e])
    }, s.prototype.isStatementsOnlyUser = function () {
        return this.isCommercialUser() && "STATEMENTS_ONLY" === this.getProfile().currentServicePlan
    }, s.prototype.isMinorUserGrammar = function () {
        return "MUG" === this.getProfile().profileType
    }, s.prototype.getZipCode = function () {
        return a.zipCode
    }, s.prototype.getAggregatedBrand = function () {
        return a.brandId && r[a.brandId]
    }, s
})),define("common/lib/userInfo/userInfo", ["require", "common/lib/userInfo/profileInfo", "common/lib/userInfo/accountInfo"], (function (e) {
    "use strict";
    var t, n, i = e("common/lib/userInfo/profileInfo"), o = e("common/lib/userInfo/accountInfo"), r = {},
            a = function () {
                this.profile = new i, this.account = new o
            };
    return a.prototype.update = function (e) {
        e && (!function (e) {
            var i = !1, o = !1, a = !1;
            (e.cache || []).forEach((function (e) {
                if (!(i && o && a)) {
                    var s = e.response || {};
                    !i && e.url.indexOf("user/metadata/list") > -1 && (t = s, i = !0), !o && e.url.indexOf("dashboard/tiles/list") > -1 && (n = s, o = !0), !a && e.url.indexOf("/overview/dashboard/profile") > -1 && (r.clientName = s.name, a = !0)
                }
            })), r.currentDateTime = e.currentDateTime
        }(e), this.updateProfile(t), n && (this.updateAccounts(n.accountTiles), n.creditJourneyTile && this.account.updateCreditJourneyTile(n.creditJourneyTile)))
    }, a.prototype.updateProfile = function (e) {
        e && (this.profile.update(e, r), this.account.updateProductInfo(e))
    }, a.prototype.updateAccounts = function (e, t) {
        e && this.account.updateAccountList(e, t)
    }, a
})),define("@bluespec/cxo/dist/spec/layout", [], (function () {
    return {name: "LAYOUT", states: {accountActivityDisplayedState: !0}, settings: {importantAda: !0}}
})),define("common/lib/utility/commonComponentsUtil", ["require", "blue/observable", "common/lib/elementObserver", "common/utility/rapidash", "@bluespec/cxo/dist/spec/layout", "common/component/modal", "blue/$"], (function (e) {
    "use strict";
    return function (t) {
        var n = t, i = e("blue/observable"), o = new (e("common/lib/elementObserver")),
                r = e("common/utility/rapidash"), a = {
                    MODAL: {
                        spec: e("@bluespec/cxo/dist/spec/layout"),
                        methods: e("common/component/modal"),
                        view: "modal"
                    }
                }, s = e("blue/$"), c = {};
        return {
            showSpinner: function (e) {
                var t = e.target || "#my-profile", i = s(t).find(".spinner-overlay").length, o = !1 !== e.append;
                n && (i || (o || s(e.target).empty(), c = e, n.context.application.emit("spinner:on", e)))
            }, hideSpinner: function (e) {
                e && !n.context.util.object.equals({}, e) || (e = c, n.context.logger.warn("Empty spinnerObject needs updating:", n.name)), n.context.application.emit("spinner:off", e)
            }, showModalDialog: function (e) {
                var t, r = s("#" + e.modalOptions.modalId), c = "#" + e.modalOptions.modalId + " .content",
                        l = e.modalOptions.target || "#my-profile";
                (t = n.components.modalDialogComponent) || (n.register.components(this, [{
                    name: "modalDialogComponent",
                    model: i.Model(e.modalOptions),
                    spec: a.MODAL.spec,
                    methods: a.MODAL.methods
                }]), t = n.components.modalDialogComponent), r || n.executeCAV([t, a.MODAL.view, {
                    target: l,
                    append: !0
                }]), o.isInserted(c, (function () {
                    n.executeCAV([e.component, e.componentViewPath, {target: c}]), t.show()
                }))
            }, hideModalDialog: function () {
                var e = n.components.modalDialogComponent;
                e && e.hide()
            }, exitModalTargetDecorator: function (e, t, n) {
                var i = this.context;
                n = n || {};
                var o = r.deepCopy(t);
                t && t.domEvent && t.domEvent.currentTarget && (o.domEvent.currentTargetCache = t.domEvent.currentTarget), i.Api.modal.getIsDirty() ? i.Api.modal.setTgtData({
                    action: function () {
                        e.call(this, o)
                    }.bind(this), data: n
                }) : (i.Api.modal.stopListener(), e.call(this, t))
            }, setFocus: function (e) {
                var t = 0, n = function () {
                    s(e).length > 0 ? s(e).focus() : t < 5 && (t++, setTimeout(n.bind(this), 1e3))
                };
                n()
            }
        }
    }
})),define("common/lib/utility/commonViewUtil", [], (function () {
    "use strict";
    return function () {
        return {
            repaintElementClass: function (e, t) {
                var n = document.querySelector(e);
                n && (n.classList.add(t), n.offsetHeight, n.classList.remove(t))
            }
        }
    }
})),define("common/lib/utility/contentLangUtility", ["require", "blue-app/settings"], (function (e) {
    "use strict";
    var t = e("blue-app/settings");

    function n(e, n) {
        t.set("language", e || "en-us"), t.set("contentLanguage", n || "en")
    }

    return function (e, t, i, o) {
        e ? function (e, t, i) {
            e.destroy(), t || !i ? n() : n(i + "-us", i)
        }(t, i, o) : function (e, t) {
            var i = e.read(), o = i && i.locale && ["en_us", "es_us"].indexOf(i.locale) > -1;
            t || !o ? n() : n(i.locale.replace("_", "-"), i.locale.split("_")[0])
        }(t, i)
    }
})),define("common/lib/utility/dynamicContentUtilWrapper", ["require", "blue/util", "common/utility/dynamicContentUtil", "blue/log"], (function (e) {
    "use strict";
    var t = {
        camelCase: e("blue/util").string.camelCase,
        dynamicContentUtil: e("common/utility/dynamicContentUtil"),
        DEFAULT: "Content",
        logger: e("blue/log")("[dynamicContentUtilWrapper]")
    };

    function n(e, n, u, d, m) {
        var f = function () {
            return void 0 !== m ? m : (t.logger.warn("no content found for " + n + "." + d), t.DEFAULT)
        };
        if ("string" == typeof e && -1 !== e.indexOf(".")) {
            var p = e.split(".");
            e = p[0], n = p[1] + "." + n
        }
        if (o(e)) return f();
        if (!(o(n) && o(u) && o(d))) return i(n) && o(u) && (o(d) || !i(d)) ? s(e, n, f) : i(n) && o(u) ? c(e, n, d, f) : i(n) && "object" == typeof u ? function (e, n, i, o, s) {
            e = r(e), function (e) {
                for (var t in e) Object.hasOwnProperty.call(e, t) && -1 !== t.indexOf(".") && l(e, t, e[t])
            }(i = i || {});
            var c = t.dynamicContentUtil.dynamicContent.get(e, n, i, o);
            return c = c || s(), a(e, n, c), a(e, t.camelCase(n), c), c
        }(e, n, u, d, f) : i(n) && "string" == typeof u ? c(e, n, u, f) : f();
        if (-1 !== e.indexOf(".")) {
            var g = e.split(".");
            return s(g.shift(), g.join("."), f)
        }
    }

    function i(e) {
        return !o(e) && "string" == typeof e
    }

    function o(e) {
        return "object" == typeof e && null === e || void 0 === e
    }

    function r(e) {
        return i(e) ? e = {spec: {name: e}} : e && "object" == typeof e && function (e) {
            e.spec && e.spec.name || !e.specName || (e.spec = {name: e.specName});
            e.context && e.context.area && e.context.area.areaName || !e.areaName || (e.context = {area: {areaName: e.areaName}})
        }(e), e
    }

    function a(e, t, n) {
        -1 !== t.indexOf(".") && (t = t.split(".")[0]), e && e.model && e.model.set && e.model.set(t, n), e[t] = n
    }

    function s(e, n, i) {
        if (e = r(e), -1 !== n.indexOf(".")) {
            var o = n.split(".");
            return c(e, o[0], o[1], i)
        }
        var a = function (e, n) {
            var i;
            return "object" == typeof e && ((i = e[n]) || (e && e.model && e.model.get && (i = e.model.get(n)), i || (i = t.dynamicContentUtil.dynamicSettings.get(e, n)))), i
        }(e, n);
        return a || i()
    }

    function c(e, n, i, o) {
        e = r(e);
        var s = t.dynamicContentUtil.dynamicSettings.get(e, n, i);
        return a(e, n, s = s || o()), a(e, t.camelCase(n), s), s
    }

    function l(e, t, n) {
        if (-1 === t.indexOf(".")) e[t] = n; else {
            var i = t.split(".");
            if (i.length >= 2) {
                var o = i[0], r = i[1];
                e[o] || (e[o] = {}), e[o][r] = n
            }
        }
    }

    var u = {};
    return u.getComponentArea = function (e, t) {
        return {spec: {name: t}, area: {name: e}}
    }, u.getContentFor = function (e, t, n, i, o) {
        var r = u.getComponentArea(e, t);
        return u.dynamicUtil(r, n, i, o)
    }, u.getGlobalContent = function (e, t, n) {
        return u.getContentFor("app", "GLOBAL", e, t, n)
    }, u.dynamicUtil = function (e, t, i, o) {
        return n(e, t, i, o)
    }, u.dynamicUtilWithDefaultValue = function (e) {
        var t = e;
        return function (e, i, o, r) {
            return n(e, i, o, r, t)
        }
    }, u.DEFAULT = t.DEFAULT, u
})),define("common/lib/utility/redirectUtil", [], (function () {
    var e = function (e, i, o, r) {
        e && e.context && e.context.controllerName && (e = e.context);
        var a = n(r);
        t(e, i, o + i + a)
    }, t = function (e, t, n) {
        try {
            t && e.routeHistory.saveRoute(t), e.state(n)
        } catch (t) {
            e.logger && e.logger.info("redirectUtil.redirect returned error: ", t.message)
        }
    }, n = function (e) {
        var t = "";
        for (var n in e) Object.hasOwnProperty.call(e, n) && (t += ";" + n + "=" + e[n]);
        return t
    };

    function i(e, t, n) {
        return e + (t ? "," : "") + n
    }

    return {
        redirect: function (n, i, o, r) {
            n && n.context && n.context.controllerName && (n = n.context), n && n.state && n.routeHistory && (i = i ? ";label=" + i : "", r && "string" == typeof r ? t(n, i, o + i + r) : r && "object" == typeof r && !Array.isArray(r) ? e(n, i, o, r) : t(n, i, o + i))
        }, payBillRedirectUrl: function (e, t) {
            var n;
            if (-1 < "personalLoanAutoAdd|personalLoanAutoMin|autopayPersonalLoan|autopayLineOfCredit|autoPayMortgageActiv|autoPayHEOActivAdd|autoPayHEOActivMin|autoPayHEOActivAuto|".indexOf(t.routeKey + "|")) return function (e, t) {
                var n, o;
                if (e && e.stringMap) n = e.stringMap, o = e.routeHistory; else {
                    if (!e || !e.settings) throw new Error("redirectUtil.paymentActivityRedirectUrl was not given a valid context");
                    n = e.settings, o = e.routeHistory
                }
                var r = n.get("payBills").urls.payBillsPayeeUrl + ";params=", a = !1;
                t.payeeId && (r = i(r, a, "t-") + t.payeeId, a = !0);
                void 0 !== t.payFromId && (r = i(r, a, "f-") + t.payFromId, a = !0);
                !0 === t.recur && (r = i(r, a, "r-1"), a = !0);
                !0 === t.newAccount && (r = i(r, a, "n-1"), a = !0);
                void 0 !== t.routeKey && (r = i(r, a, "k-") + t.routeKey, t.fromPage && (r = i(r, a, "m-") + t.fromPage), o.saveRoute(t.routeKey));
                return r
            }(e, t);
            if (-1 !== ["payCommercialLoanFacility", "payCommercialLoanObligation", "payCommercialLoanTermLoan", "payCommercialLoanUrl"].indexOf(t.routeKey)) return function (e, t) {
                var n = e.stringMap.get("payBills").urls.payCommercialLoanUrl;
                return n += ";loanId=", n += String(t.payeeId).replace("-", ""), n += ";loanType=", n += {
                    payCommercialLoanFacility: "FACILITY",
                    payCommercialLoanObligation: "OBLIGATION",
                    payCommercialLoanTermLoan: "TERMLOAN"
                }[t.routeKey]
            }(e, t);
            if (e && e.stringMap) n = e.stringMap; else {
                if (!e || !e.settings) throw new Error("redirectUtil.payBillsUrl was not given a valid context");
                n = e.settings
            }
            var o = n.get("pmb_Url_singlePaymentUrl");
            return t.payeeId && (o += ";payeeId=" + t.payeeId), t.noRefresh && (o += ";noRefresh=" + t.noRefresh), t.onExitReturnTo && (o += ";onExitReturnTo=" + t.onExitReturnTo), t.accountId && (o += ";accountId=" + t.accountId), t.mode && (o += ";mode=" + t.mode), t.businessCard && (o += ";cardType=" + ("personal" !== t.businessCard ? "BCC" : "PAC")), e.logger.info("Redirect payBillsUrl: ", o), o
        }, paymentActivityRedirectUrl: function (e, t) {
            var n, i;
            if (e && e.stringMap) n = e.stringMap, i = e.routeHistory; else {
                if (!e || !e.settings) throw new Error("redirectUtil.paymentActivityRedirectUrl was not given a valid context");
                n = e.settings, i = e.routeHistory ? e.routeHistory : e.context.routeHistory
            }
            var o = n.get("paymentsActivityRouteUrl");
            return t.baseUrlKey && (o = n.get(t.baseUrlKey) || o), t.routeKey && (o += ";route=" + t.routeKey, i.saveRoute(t.routeKey)), t.payeeType && (o += ";payeeType=" + t.payeeType), t.payeeId && (o += ";payeeId=" + t.payeeId), t.additionalOptions && (o += ";additionalOptions=" + t.additionalOptions), e.logger.info("PA Redirect Url: ", o), o
        }, navigateBacktoSavedRouteUrl: function (e, t) {
            var n = e.routeHistory.lastRoute(t), i = e.routeHistory.getHistory(t);
            return i && delete i.url, n
        }
    }
})),define("common/lib/utility/urlFormatInterceptor", ["require", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/util");
    return {
        before: function (e) {
            return e.url = t.string.interpolate(e.url, e.data), !0 === e.data.clearParameters && delete e.data, e
        }
    }
})),define("common/lib/viewHelper/dataHandlerUtility", ["require", "blue/log"], (function (e) {
    "use strict";
    var t = e("blue/log")("[dataHandlerUtility]"), n = function (e, t) {
        var n = e.currentSafeDataListeners || {}, i = n[t];
        "function" == typeof i && (i.call(e), delete n[t])
    };
    return {
        createSafeValueChangeListener: function (e, t, n) {
            return e.onData(t, (function (t) {
                t && "function" == typeof n && n.call(e, t)
            }))
        }, removeSafeValueListeners: function (e, t) {
            return t.forEach((function (e) {
                e && "function" == typeof e && e()
            })), []
        }, removeSafeDataListener: n, removeAllSafeDataListeners: function (e) {
            var t, n = e.currentSafeDataListeners || {};
            Object.keys(n).forEach((function (i) {
                "function" == typeof (t = n[i]) && (t.call(e), delete n[i])
            }))
        }, createSafeDataChangeListener: function (e, i, o, r) {
            e.currentSafeDataListeners = e.currentSafeDataListeners || {}, e.currentSafeDataListeners[i] && !r && (t.info("Found exisitng listner on key <" + i + "> will remove old listner for allowing new listner  controllerInstance:", e), n(e, i)), e.currentSafeDataListeners[i] && delete e.currentSafeDataListeners[i];
            var a = e.model.onValue(i, (function (t) {
                null != t && "function" == typeof o && o.call(e, t)
            }), {initial: !1, skipRepeats: !1});
            return e.currentSafeDataListeners[i] = a, a
        }
    }
})),define("common/lib/viewHelper/viewMixins", [], (function () {
    function e(e) {
        this.view = e, this.triggers = {}
    }

    return e.prototype.addSECTriggerFilters = function (e) {
        function t(e, t) {
            var n = e.domEvent || e;
            return t.includes("C") && "click" === n.type || t.includes("E") && "Enter" === n.key || t.includes("S") && "Space" === n.key
        }

        var n = this.view;
        return e.reduce((function (e, i) {
            var o, r, a, s;
            switch (typeof i) {
                case"string":
                    o = (s = i).split("_"), r = o[0], a = o[1], n[i] = function (e) {
                        t(e, r) && n.trigger(a, e)
                    };
                    break;
                case"object":
                    o = (s = Object.keys(i)[0]).split("_"), r = o[0], a = o[1], n[s] = function (e) {
                        t(e, r) && i[s].call(n, e)
                    }
            }
            return e[s] = {action: "view." + s}, e
        }), this.triggers), this
    }, e.prototype.addTriggers = function (e) {
        return Object.assign(this.triggers, e), this
    }, e.prototype.addOnFormSubmitADATriggerHandler = function (e) {
        return this.triggers.onFormSubmit = {action: "view.handleFormSubmit"}, this.view.handleFormSubmit = function () {
            this.trigger("validateForm"), this.triggerSubmitAda();
            var t = Array.prototype.slice.apply(arguments);
            e instanceof Function ? e.apply(this, t) : "string" == typeof e && this.trigger.apply(this, [e].concat(t))
        }, this
    }, e.prototype.addFormFieldTrackingTrigger = function () {
        return this.triggers.formFieldTracking = {action: "formField"}, this
    }, e.attach = function (t) {
        t.triggerBuilder = new e(t)
    }, e.prototype.getTriggers = function () {
        return this.triggers
    }, {
        applyTriggerBuilderMixin: e.attach, addFocusHandler: function (e) {
            var t = e.model.hasOwnProperty("focusPlacement") ? "focusPlacement" : "$focusPlacement";
            e.onData(t, (function (t) {
                t && t.selector && e.context.$(t.selector).length && e.context.$(t.selector).focus()
            }))
        }
    }
})),define("common/oobe/util/head", ["require", "blue/is", "blue/log"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("blue/log")("[oobe]");
    return function (e) {
        var i;
        return t.primitive(e) ? (n.warn("Invalid config found: " + e), t.undefined(e) || t.null(e) ? void 0 : ["CASCADE", [e]]) : t.array(e) ? (i = e.filter((function (e) {
            return t.primitive(e)
        }))).length ? ["CASCADE", i] : void 0 : t.plainObject(e) ? ["PARALLEL", Object.keys(e)] : void 0
    }
})),define("common/oobe/util/after", ["require", "blue/is", "blue/log"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("blue/log")("[oobe]");
    return function (e, i) {
        var o, r, a = i[e.route];
        if (t.defined(a)) switch (e.kind) {
            case"CASCADE":
                (o = a.indexOf(e.elm)) >= 0 && (a.splice(0, o + 1), t.empty(a) ? delete i[e.route] : a.some((function (e) {
                    return t.primitive(e)
                })) || (i[e.route] = a[0]));
                break;
            case"PARALLEL":
                r = a[e.elm], t.undefined(r) || (t.empty(r) ? delete i[e.route] : i[e.route] = r);
                break;
            default:
                n.warn("Kind: " + e.kind + " not handled currently. No config will be updated or deleted")
        }
        return i
    }
})),define("common/oobe/util/screenMatcher", [], (function () {
    "use strict";
    return function (e, t) {
        return e.filter((function (e) {
            return new RegExp(e).test(t.screen.currentURL)
        })).sort((function (e, t) {
            return t.length - e.length
        }))[0]
    }
})),define("common/oobe/configStore", ["require", "blue/store/enumerable/cookie"], (function (e) {
    "use strict";
    var t = new (e("blue/store/enumerable/cookie"))("oobe");
    return {
        get: function () {
            return t.get("config")
        }, set: function (e) {
            t.set("config", e, 1 / 0, "/")
        }, clear: function () {
            t.remove("config", "/")
        }
    }
})),define("common/oobe/util/rsyncConfig", ["require", "blue/is"], (function (e) {
    "use strict";
    var t = e("blue/is");
    return function (e, n) {
        var i;
        if (!e || !t.object(e)) return Promise.reject("Not a valid object: ", e);
        try {
            i = JSON.stringify(e)
        } catch (e) {
            return Promise.reject(e)
        }
        return n.services.oobeService.update({oobeState: i}).then((function (e) {
            return e && "SUCCESS" === e.code ? JSON.parse(i) : Promise.reject("Invalid response from server: ", e)
        }), (function (e) {
            return Promise.reject(e)
        })).catch((function (e) {
            return Promise.reject(e)
        }))
    }
})),define("common/oobe/config", [], (function () {
    "use strict";
    return {
        "dashboard/accounts": ["Global", "AccountCategories", "AccountTile", "ThingsYouCanDo", "GeneralNavigation"],
        "dashboard/overview": ["DashboardOverview", "GeneralNavigation"],
        "dashboard/profile/overview/updateMyOverviewDetails": ["Empty", "MailingAddress", "Alerts", "Travel"],
        "dashboard/quickPay/qpSend/initiateSend;seriesType=single": {
            Empty: {
                AddRecipient: ["RepeatingPayment", "SendMoneyRequestMoney"],
                ChooseRecipient: ["RepeatingPayment", "SendMoneyRequestMoney"]
            }
        },
        "dashboard/wires": ["WireSummary"],
        "dashboard/singleDoor": ["SingleDoor"]
    }
})),define("common/oobe/oobeProductUpdateDateConfig", {
    "dashboard/accounts": "2018.09.23",
    "dashboard/overview": "2018.09.23",
    "dashboard/profile/overview/updateMyOverviewDetails": "2018.09.23",
    "dashboard/quickPay/qpSend/initiateSend;seriesType=single": "2018.09.23",
    "dashboard/wires": "2018.09.23",
    "dashboard/singleDoor": "2018.11.12",
    oobeProductsUpdateDate: "2018.11.12"
}),define("common/oobe/util/lastOobeUpdateCheck", ["require", "common/oobe/config", "blue/util", "common/oobe/oobeProductUpdateDateConfig"], (function (e) {
    "use strict";
    var t = e("common/oobe/config"), n = e("blue/util").object, i = e("common/oobe/oobeProductUpdateDateConfig"),
            o = {};

    function r(e) {
        var t = "string" == typeof e && e.replace(/\./g, "-");
        return Number(new Date(t))
    }

    return new function () {
        this.lastOobeUpdateCheck = function (e) {
            return function (e, t) {
                return !e.oobeProductsUpdateDate || r(e.oobeProductsUpdateDate) < r(t)
            }(e, i.oobeProductsUpdateDate) ? (a = e, s = i.oobeProductsUpdateDate, c = e.oobeProductsUpdateDate, l = {}, Object.keys(o).length === Object.keys(i).length - 1 ? (a.oobeProductsUpdateDate = i.oobeProductsUpdateDate, a) : (c || (c = "2018.09.23"), Object.keys(i).forEach((function (e) {
                "oobeProductsUpdateDate" !== e && r(c) < r(i[e]) && (l[e] = t[e])
            })), l.oobeProductsUpdateDate = s, n.merge(a, l))) : e;
            var a, s, c, l
        }, this.setInitialObj = function (e) {
            return o = n.deepClone(e), this.lastOobeUpdateCheck(e)
        }
    }
})),define("common/oobe/util/flushConfig", ["require", "common/oobe/configStore", "blue/util", "common/oobe/util/rsyncConfig", "common/oobe/util/lastOobeUpdateCheck", "blue/log"], (function (e) {
    "use strict";
    var t = e("common/oobe/configStore"), n = e("blue/util").object, i = e("common/oobe/util/rsyncConfig"),
            o = e("common/oobe/util/lastOobeUpdateCheck").lastOobeUpdateCheck,
            r = e("blue/log")("[oobe/util/flushConfig]");
    return function (e, a, s) {
        var c = o(n.merge(e, a));
        return i(c, s).then((function (e) {
            t.set(e)
        }), (function (e) {
            r.error("rsyncConfig rejected", e)
        }))
    }
})),define("common/oobe/engine", ["require", "common/oobe/util/head", "common/oobe/util/after", "common/oobe/util/screenMatcher", "blue/is", "common/oobe/util/flushConfig"], (function (e) {
    "use strict";
    var t = e("common/oobe/util/head"), n = e("common/oobe/util/after"), i = e("common/oobe/util/screenMatcher"),
            o = e("blue/is"), r = e("common/oobe/util/flushConfig"), a = ["SingleDoor"];
    return function (e, s, c) {
        var l, u = s, d = {}, m = function (e) {
            var n, r = i(Object.keys(u), e);
            r && (d[r] = u[r], delete u[r], n = t(d[r]), o.empty(u) && o.function(l) && l()), n && o.array(n) && 2 === n.length && -1 !== a.indexOf(n[1][0]) && c.emit("oobeRequest", {
                route: r,
                kind: n[0],
                elm: n[1]
            })
        };
        c.on("oobeResponse", (function (e) {
            n(e, d), r(u, d, c)
        })), c.on("forceOOBEScreenCheck", m), l = e.onValue(m)
    }
})),define("common/oobe/filter", ["require", "common/utility/rapidash", "common/lib/accountInfo"], (function (e) {
    "use strict";
    var t, n = e("common/utility/rapidash"), i = ["GeneralNavigation", "DashboardOverview"], o = {
        filterSegmentType: function (e) {
            t.isGWMUser() && r(e, i)
        }
    }, r = function (e, t) {
        Object.keys(e).forEach((function (i) {
            var o = e[i];
            n.isObject(o) && r(o, t), Array.isArray(o) && (e[i] = o.filter((function (e) {
                return -1 === t.indexOf(e)
            })))
        }))
    };
    return function (n) {
        return t = new (e("common/lib/accountInfo")), Object.keys(o).forEach((function (e) {
            o[e](n)
        })), n
    }
})),define("common/oobe/util/readConfig", ["require", "common/oobe/config", "common/oobe/filter", "blue/log", "common/oobe/util/lastOobeUpdateCheck", "common/oobe/configStore"], (function (e) {
    "use strict";
    var t = e("common/oobe/config"), n = e("common/oobe/filter"), i = e("blue/log")("[oobe/util/readConfig]"),
            o = e("common/oobe/util/lastOobeUpdateCheck");
    return function (r, a) {
        var s = e("common/oobe/configStore").get();
        return r.services.oobeService.list().then((function (e) {
            if ("SUCCESS" === e.code) return e.oobeState ? o.setInitialObj(n(JSON.parse(decodeURIComponent(e.oobeState)))) : o.setInitialObj(a || s || n(t))
        }), (function (e) {
            i.error("Promise rejected retrieving remote oobe config: ", e), i.warn("oobe will be disabled")
        })).catch((function (e) {
            i.error("Exception reconstructing oobeState: ", e)
        }))
    }
})),define("common/oobe/bootstrap", ["require", "blue/log", "blue/store/enumerable/session", "blue/siteMode", "common/oobe/engine", "blue/is", "common/oobe/util/readConfig", "blue/siteData", "analytics/util/streamCollator"], (function (e) {
    "use strict";
    var t, n = e("blue/log")("[OOBE Bootstrap]"), i = new (e("blue/store/enumerable/session"))("oobe"),
            o = e("blue/siteMode").isModeEnabled, r = e("common/oobe/engine"), a = e("blue/is"),
            s = e("common/oobe/util/readConfig"), c = e("blue/siteData").getData;
    return function (l) {
        var u, d = {
            intercept: function () {
                l.context ? t = l.context.config : n.error("Application context not defined"), i.set("suppress_oobe", !0)
            }, dashboard: function () {
                if (l.context) {
                    if (!("true" !== (t = l.context.config).enableOOBE && !0 !== t.enableOOBE || c("userType") && c("userType").indexOf("FinancialAdvisor") > -1)) if (i.get("suppress_oobe")) i.remove("suppress_oobe"); else if (!l.maintenanceModes || !l.maintenanceModes.some((function (e) {
                        return o(e)
                    }))) return (u = Promise.all([s(l.context), e("analytics/util/streamCollator")(l.site, l.application)])).then((function (e) {
                        var t = e[0], n = e[1];
                        if (t && a.array(n)) return r(n[1], t, l.context)
                    })), u
                } else n.error("Application context not defined")
            }
        };
        return d[l.application] && d[l.application]()
    }
})),define("common/oobe/controllerMap", [], (function () {
    "use strict";
    return {
        "dashboard/profile/overview/updateMyOverviewDetails": ["profile/base"],
        "dashboard/accounts": ["accounts/accounts", "accounts/summary", "gallery/transactions", "header", "menu"],
        "dashboard/overview": ["overview/dashboardOverview", "menu"],
        "dashboard/quickPay/qpSend/initiateSend;seriesType=single": ["quickPay/qpSend"],
        "dashboard/wires": ["wires/scheduleWire"],
        "dashboard/singleDoor": ["singleDoor/singleDoorController"]
    }
})),define("common/oobe/elements", [], (function () {
    "use strict";
    return {
        MailingAddress: {
            contentKey: "myMailingAddressDetailsPromotionalMessage",
            selector: "#bottom-profile-selectMyMailingAddressDetails-link",
            position: "right-middle",
            focusElementSelector: "#title-profile-header",
            adaKey: "myMailingAddressDetailsPromotionalMessageAda",
            analyticsCloseAction: "exitMyMailingAddressDetailsPromotionalMessage"
        },
        Alerts: {
            contentKey: "myAlertsPromotionalMessage",
            selector: "#bottom-profile-manageMyAlerts-link",
            position: "right-middle",
            focusElementSelector: "#title-profile-header",
            adaKey: "myAlertsPromotionalMessageAda",
            analyticsCloseAction: "exitMyAlertsPromotionalMessage"
        },
        Travel: {
            contentKey: "myTravelPreferencesPromotionalMessage",
            selector: "#bottom-profile-updateMyTravelPreferences-link",
            parentSelector: "#bottom-profile-updateMyTravelPreferences-link",
            position: "right-middle",
            focusElementSelector: "#title-profile-header",
            adaKey: "myTravelPreferencesPromotionalMessageAda",
            analyticsCloseAction: "exitMyTravelPreferencesPromotionalMessage"
        },
        TransactionFilter: {
            contentKey: "transactionsQuickFilterPromotionalMessage",
            selector: "#accountOptions",
            position: "right-middle",
            focusElementSelector: "#ada-site-alerts-heading",
            adaKey: "transactionsQuickFilterPromotionalMessageAda",
            analyticsCloseAction: "exitTransactionsQuickFilterPromotionalMessage"
        },
        PrintAndDownload: {
            contentKey: "printOrDownloadDocumentPromotionalMessage",
            selector: "#account-print-or-download-tooltip",
            position: "left-middle",
            focusElementSelector: "#ada-site-alerts-heading",
            adaKey: "printOrDownloadDocumentPromotionalMessageAda",
            analyticsCloseAction: "exitPrintOrDownloadDocumentPromotionalMessage"
        },
        AccountTile: {
            contentKey: "accountTilePromotionalMessage",
            selector: ".account-tile.active",
            position: "right-middle",
            focusElementSelector: "#ada-site-alerts-heading",
            adaKey: "accountTilePromotionalMessageAda",
            analyticsCloseAction: "exitAccountTilePromotionalMessage"
        },
        ThingsYouCanDo: {
            contentKey: "thingsYouCanDoPromotionalMessage",
            selector: "#accountsThingsYouCanDo",
            position: "left-middle",
            focusElementSelector: "#ada-site-alerts-heading",
            adaKey: "thingsYouCanDoPromotionalMessageAda",
            analyticsCloseAction: "exitThingsYouCanDoPromotionalMessage"
        },
        AddRecipient: {
            contentKey: "payeeNamePromotionalMessage",
            selector: "#quickpay-add-recipient-tooltip",
            position: "right-middle",
            focusElementSelector: "#title-sendOrRequestMenuHeader",
            adaKey: "",
            analyticsCloseAction: "exitPayeeNamesPromotionalMessage"
        },
        ChooseRecipient: {
            contentKey: "payeeNamesPromotionalMessage",
            selector: "#quickpay-choose-recipient-tooltip",
            position: "right-middle",
            focusElementSelector: "#title-sendOrRequestMenuHeader",
            adaKey: "",
            analyticsCloseAction: "exitPayeeNamePromotionalMessage"
        },
        RepeatingPayment: {
            contentKey: "transactionRecurringPromotionalMessage",
            selector: "#repeatingPaymentToggleSwitch",
            position: "right-middle",
            focusElementSelector: "#title-sendOrRequestMenuHeader",
            adaKey: "myMailingAddressDetailsPromotionalMessageAda",
            analyticsCloseAction: "exitTransactionRecurringPromotionalMessage"
        },
        SendMoneyRequestMoney: {
            contentKey: "sendOrRequestMoneyPromotionalMessage",
            selector: "#quickpay-header-content .menu",
            position: "right-middle",
            focusElementSelector: "#title-sendOrRequestMenuHeader",
            adaKey: "sendOrRequestMoneyPromotionalMessageAda",
            analyticsCloseAction: "exitSendOrRequestMoneyPromotionalMessage"
        },
        AccountCategories: {
            contentKey: "accountCategoriesHelpMessage",
            selector: "#tileFilter",
            position: "right-middle",
            focusElementSelector: "#ada-site-alerts-heading",
            adaKey: "accountCategoriesHelpMessageAda",
            analyticsCloseAction: "exitAccountCategoriesHelpMessage"
        },
        GeneralNavigation: {
            contentKey: "navigationImprovementsPromotionalMessage",
            selector: ".main-menu-container.show-sm",
            position: "bottom-middle-aligned",
            focusElementSelector: "#ada-site-alerts-heading",
            adaKey: "navigationImprovementsPromotionalMessageAda",
            analyticsOpenAction: "navigationImprovementsPromotionalOverlay",
            analyticsCloseAction: "exitNavigationImprovementsPromotionalMessage"
        },
        WireSummary: {
            contentKey: "wireSummaryPromotionalMessage",
            selector: ".transferSummaryContainer",
            position: "left",
            focusElementSelector: "#makeWireTransferH2",
            adaKey: "wireSummaryPromotionalMessageAda",
            analyticsCloseAction: "exitWireSummaryPromotionalMessage"
        },
        SingleDoor: {}
    }
})),function (e, t) {
    "object" == typeof exports ? t(exports, require) : "function" == typeof define && define.amd ? define("common/tours/intro", ["exports", "require", "blue/$", "blue/log"], t) : t(e, require)
}(this, (function (e, t) {
    var n = t("blue/$"), i = t("blue/log")("[oobe]"), o = !1;

    function r(e) {
        this._targetElement = e, this._lastFocusedElement, this._options = {
            nextLabel: "Next &rarr;",
            prevLabel: "&larr; Back",
            skipLabel: "Skip",
            doneLabel: "Done",
            tooltipPosition: "bottom",
            tooltipClass: "",
            highlightClass: "",
            exitOnEsc: !0,
            exitOnOverlayClick: !0,
            showStepNumbers: !0,
            keyboardNavigation: !0,
            showButtons: !0,
            showBullets: !0,
            showProgress: !1,
            scrollToElement: !0,
            overlayOpacity: 1,
            positionPrecedence: ["bottom", "top", "right", "left"],
            disableInteraction: !1
        }
    }

    function a(e) {
        var t = [], i = this;
        if (!0, this._options.steps) for (var r = [], a = 0, c = this._options.steps.length; a < c; a++) {
            var m = s(this._options.steps[a]);
            if (m.step = t.length + 1, "string" == typeof m.element && (m.element = document.querySelector(m.element)), void 0 === m.element || null == m.element) {
                var f = document.querySelector(".introjsFloatingElement");
                null == f && ((f = document.createElement("div")).className = "introjsFloatingElement", document.body.appendChild(f)), m.element = f, m.position = "floating"
            }
            null != m.element && t.push(m)
        } else {
            if ((r = e.querySelectorAll("*[data-intro]")).length < 1) return !1;
            a = 0;
            for (var p = r.length; a < p; a++) {
                var g = r[a], h = parseInt(g.getAttribute("data-step"), 10);
                h > 0 && (t[h - 1] = {
                    element: g,
                    intro: g.getAttribute("data-intro"),
                    step: parseInt(g.getAttribute("data-step"), 10),
                    tooltipClass: g.getAttribute("data-tooltipClass"),
                    highlightClass: g.getAttribute("data-highlightClass"),
                    position: g.getAttribute("data-position") || this._options.tooltipPosition
                })
            }
            var v = 0;
            for (a = 0, p = r.length; a < p; a++) {
                if (null == (g = r[a]).getAttribute("data-step")) {
                    for (; void 0 !== t[v];) v++;
                    t[v] = {
                        element: g,
                        intro: g.getAttribute("data-intro"),
                        step: v + 1,
                        tooltipClass: g.getAttribute("data-tooltipClass"),
                        highlightClass: g.getAttribute("data-highlightClass"),
                        position: g.getAttribute("data-position") || this._options.tooltipPosition
                    }
                }
            }
        }
        for (var b = [], A = 0; A < t.length; A++) t[A] && b.push(t[A]);
        if ((t = b).sort((function (e, t) {
            return e.step - t.step
        })), i._introItems = t, o) return o = !1, void !1;
        if (E.call(i, e)) {
            l.call(i);
            e.querySelector(".introjs-skipbutton"), e.querySelector(".introjs-nextbutton");
            i._onKeyDown = function (t) {
                if (27 === t.keyCode && 1 == i._options.exitOnEsc) d.call(i, e), null != i._introExitCallback && i._introExitCallback.call(i); else if (37 === t.keyCode) u.call(i); else if (39 === t.keyCode) l.call(i); else if (13 === t.keyCode) {
                    var o = t.target || t.srcElement;
                    o && o.className.indexOf("introjs-prevbutton") > 0 ? u.call(i) : o && o.className.indexOf("introjs-skipbutton") > 0 ? (i._introItems.length - 1 === i._currentStep && "function" == typeof i._introCompleteCallback && i._introCompleteCallback.call(i), i._introItems.length - 1 !== i._currentStep && "function" == typeof i._introExitCallback && i._introExitCallback.call(i), d.call(i, e)) : l.call(i), t.preventDefault ? t.preventDefault() : t.returnValue = !1
                } else if (9 === t.keyCode && !i._options.overrideTabbing) {
                    var r = n(".introjs-tooltip"),
                            a = r.find("*").filter("a, button, :input, [tabindex]").not("[tabIndex=-1]").filter(":visible"),
                            s = r.find(":focus"), c = a.length, m = a.index(s);
                    t.shiftKey ? 0 === m && (a.get(c - 1).focus(), t.preventDefault()) : m === c - 1 && (a.get(0).focus(), t.preventDefault())
                }
            }, i._onResize = function (e) {
                y.call(i, document.querySelector(".introjs-helperLayer")), y.call(i, document.querySelector(".introjs-tooltipReferenceLayer"))
            }, window.addEventListener ? (this._options.keyboardNavigation && window.addEventListener("keydown", i._onKeyDown, !0), window.addEventListener("resize", i._onResize, !0)) : document.attachEvent && (this._options.keyboardNavigation && document.attachEvent("onkeydown", i._onKeyDown), document.attachEvent("onresize", i._onResize))
        }
        return !1, o = !1, !1
    }

    function s(e) {
        if (null == e || "object" != typeof e || void 0 !== e.nodeType) return e;
        var t = {};
        for (var n in e) t[n] = s(e[n]);
        return t
    }

    function c(e) {
        this._currentStep = e - 2, void 0 !== this._introItems && l.call(this)
    }

    function l() {
        if (this._direction = "forward", void 0 === this._currentStep ? this._currentStep = 0 : ++this._currentStep, this._introItems.length <= this._currentStep) return "function" == typeof this._introCompleteCallback && this._introCompleteCallback.call(this), void d.call(this, this._targetElement);
        var e = this._introItems[this._currentStep];
        void 0 !== this._introBeforeChangeCallback && this._introBeforeChangeCallback.call(this, e.element), v.call(this, e)
    }

    function u() {
        if (this._direction = "backward", 0 === this._currentStep) return !1;
        var e = this._introItems[--this._currentStep];
        void 0 !== this._introBeforeChangeCallback && this._introBeforeChangeCallback.call(this, e.element), v.call(this, e)
    }

    function d(e) {
        e || (e = document.getElementsByTagName("body")[0]);
        var t = e.querySelector(".introjs-overlay");
        if (null != t) {
            t.style.opacity = 0, setTimeout((function () {
                t.parentNode && t.parentNode.removeChild(t)
            }), 500);
            var n = e.querySelector(".introjs-helperLayer");
            n && n.parentNode.removeChild(n);
            var i = e.querySelector(".introjs-tooltipReferenceLayer");
            i && (document.querySelector("#dashboard-content").removeAttribute("aria-hidden"), i.parentNode.removeChild(i), this._lastFocusedElement && this._lastFocusedElement.focus());
            var o = e.querySelector(".introjs-disableInteraction");
            o && o.parentNode.removeChild(o);
            var r = document.querySelector(".introjsFloatingElement");
            r && r.parentNode.removeChild(r);
            var a = document.querySelector(".introjs-showElement");
            a && (a.className = a.className.replace(/introjs-[a-zA-Z]+/g, "").replace(/^\s+|\s+$/g, ""));
            var s = document.querySelectorAll(".introjs-fixParent");
            if (s && s.length > 0) for (var c = s.length - 1; c >= 0; c--) s[c].className = s[c].className.replace(/introjs-fixParent/g, "").replace(/^\s+|\s+$/g, "");
            window.removeEventListener ? window.removeEventListener("keydown", this._onKeyDown, !0) : document.detachEvent && document.detachEvent("onkeydown", this._onKeyDown), this._currentStep = void 0
        }
    }

    function m(e, t, n, i) {
        var o, r, a, s = "";
        if (t.style.top = null, t.style.right = null, t.style.bottom = null, t.style.left = null, t.style.marginLeft = null, t.style.marginTop = null, n.style.display = "inherit", void 0 !== i && null != i && (i.style.top = null, i.style.left = null), this._introItems[this._currentStep]) {
            s = "string" == typeof (o = this._introItems[this._currentStep]).tooltipClass ? o.tooltipClass : this._options.tooltipClass, t.className = ("introjs-tooltip " + s).replace(/^\s+|\s+$/g, "");
            s = this._options.tooltipClass;
            currentTooltipPosition = this._introItems[this._currentStep].position, "auto" != currentTooltipPosition && "auto" != this._options.tooltipPosition || "floating" != currentTooltipPosition && (currentTooltipPosition = f.call(this, e, t, currentTooltipPosition));
            var c = T(e), l = T(t).height, u = A();
            switch (t.className = "introjs-tooltip introjs-tooltip-" + currentTooltipPosition, currentTooltipPosition) {
                case"top":
                    t.style.left = "15px", t.style.top = "-" + (l + 10) + "px", n.className = "introjs-arrow bottom";
                    break;
                case"right":
                    t.style.left = T(e).width + 20 + "px", c.top + l > u.height && (n.className = "introjs-arrow left-bottom", t.style.top = "-" + (l - c.height - 20) + "px"), n.className = "introjs-arrow left";
                    break;
                case"right-middle":
                    t.style.left = T(e).width + "px", t.style.top = c.height / 2 + "px", n.className = "introjs-arrow left";
                    break;
                case"left":
                    1 == this._options.showStepNumbers && (t.style.top = "15px"), c.top + l > u.height ? (t.style.top = "-" + (l - c.height - 20) + "px", n.className = "introjs-arrow right-bottom") : n.className = "introjs-arrow right", t.style.right = c.width + 20 + "px";
                    break;
                case"left-middle":
                    t.style.right = c.width + 20 + "px", t.style.top = c.height / 2 - 5 + "px", n.className = "introjs-arrow right";
                    break;
                case"floating":
                    n.style.display = "none", r = T(t), t.style.left = "50%", t.style.top = "50%", t.style.marginLeft = "-" + r.width / 2 + "px", t.style.marginTop = "-" + r.height / 2 + "px", void 0 !== i && null != i && (i.style.left = "-" + (r.width / 2 + 18) + "px", i.style.top = "-" + (r.height / 2 + 18) + "px");
                    break;
                case"bottom-right-aligned":
                    n.className = "introjs-arrow top-right", t.style.right = "0px", t.style.bottom = "-" + (T(t).height + 10) + "px";
                    break;
                case"bottom-middle-aligned":
                    a = T(e), r = T(t), n.className = "introjs-arrow top-middle", t.style.left = a.width / 2 - r.width / 2 + "px", t.style.bottom = "-" + (r.height + 120) + "px";
                    break;
                case"bottom-left-aligned":
                case"bottom":
                default:
                    t.style.bottom = "-" + (T(t).height + 10) + "px", t.style.left = T(e).width / 2 - T(t).width / 2 + "px", n.className = "introjs-arrow top"
            }
            document.querySelector(".introjs-tooltiptext").focus()
        }
    }

    function f(e, t, n) {
        var i = this._options.positionPrecedence.slice(), o = A(), r = T(t).height + 10, a = T(t).width + 20, s = T(e),
                c = "floating";
        return s.left + a > o.width || s.left + s.width / 2 - a < 0 ? (p(i, "bottom"), p(i, "top")) : (s.height + s.top + r > o.height && p(i, "bottom"), s.top - r < 0 && p(i, "top")), s.width + s.left + a > o.width && p(i, "right"), s.left - a < 0 && p(i, "left"), i.length > 0 && (c = i[0]), n && "auto" != n && i.indexOf(n) > -1 && (c = n), c
    }

    function p(e, t) {
        e.indexOf(t) > -1 && e.splice(e.indexOf(t), 1)
    }

    function g() {
        setTimeout((function () {
            n(".introjs-overlay").remove(), n(".introjs-helperLayer").remove(), n(".introjs-tooltipReferenceLayer").remove(), n("body").removeClass("disableScrolling"), n(".introjs-showElement").removeClass("introjs-showElement"), n("#site-tour-wrapper").remove(), n("#site-tour-overlay").remove()
        }), 0)
    }

    function y(e) {
        if (this._introItems && this._introItems[this._currentStep]) {
            try {
                var t = this._introItems[this._currentStep].element, n = this._introItems[this._currentStep].position,
                        o = document.querySelector(".introjs-tooltip")
            } catch (e) {
                return i.log("tour failed with error", e), void g()
            }
            if (e) {
                var r = this._introItems[this._currentStep], a = T(r.element), s = 10;
                "floating" == r.position && (s = 0), e.setAttribute("style", "width: " + (a.width + s) + "px; height:" + (a.height + s) + "px; top:" + (a.top - 5) + "px;left: " + (a.left - 5) + "px;")
            }
            if (o) switch (n) {
                case"right-middle":
                    o.style.left = T(t).width + 20 + "px"
            }
        }
    }

    function h() {
        var e = document.querySelector(".introjs-disableInteraction");
        null === e && ((e = document.createElement("div")).className = "introjs-disableInteraction", this._targetElement.appendChild(e)), y.call(this, e)
    }

    function v(e) {
        void 0 !== this._introChangeCallback && this._introChangeCallback.call(this, e.element);
        var t = this, n = document.querySelector(".introjs-helperLayer"),
                o = document.querySelector(".introjs-tooltipReferenceLayer"), r = "introjs-helperLayer";
        T(e.element);
        if (this._options.exitSiteTourAda) {
            var a = document.createElement("span");
            a.className = "util accessible-text", a.innerHTML = this._options.exitSiteTourAda ? this._options.exitSiteTourAda : ""
        }
        var s = document.createElement("span");
        if (s.className = "util accessible-text", s.id = "tooltip-content-ada", "string" == typeof e.highlightClass && (r += " " + e.highlightClass), "string" == typeof this._options.highlightClass && (r += " " + this._options.highlightClass), null != n) {
            var c = o.querySelector(".introjs-helperNumberLayer"), f = o.querySelector(".introjs-tooltiptext"),
                    p = o.querySelector(".introjs-arrow"), v = o.querySelector(".introjs-tooltip"),
                    E = o.querySelector("#tooltip-content-ada"), S = o.querySelector(".introjs-skipbutton"),
                    _ = o.querySelector(".introjs-prevbutton"), O = o.querySelector(".introjs-nextbutton");
            if (n.className = r, v.style.opacity = 0, v.style.display = "none", null != c) {
                var D = this._introItems[e.step - 2 >= 0 ? e.step - 2 : 0];
                (null != D && "forward" == this._direction && "floating" == D.position || "backward" == this._direction && "floating" == e.position) && (c.style.opacity = 0)
            }
            y.call(t, n), y.call(t, o);
            var I = document.querySelectorAll(".introjs-fixParent");
            if (I && I.length > 0) for (var N = I.length - 1; N >= 0; N--) I[N].className = I[N].className.replace(/introjs-fixParent/g, "").replace(/^\s+|\s+$/g, "");
            var L = document.querySelector(".introjs-showElement");
            L.className = L.className.replace(/introjs-[a-zA-Z]+/g, "").replace(/^\s+|\s+$/g, ""), t._lastShowElementTimer && clearTimeout(t._lastShowElementTimer), t._lastShowElementTimer = setTimeout((function () {
                null != c && (c.innerHTML = e.step), f.innerHTML = e.intro, E.innerHTML = e.ada, v.style.display = "block", m.call(t, e.element, v, p, c), o.querySelector(".introjs-bullets li > a.active").className = "", o.querySelector('.introjs-bullets li > a[data-stepnumber="' + e.step + '"]').className = "active", o.querySelector(".introjs-progress .introjs-progressbar").setAttribute("style", "width:" + C.call(t) + "%;"), v.style.opacity = 1, c && (c.style.opacity = 1), O.tabIndex
            }), 350)
        } else {
            var M = document.createElement("div"), P = document.createElement("div"), w = document.createElement("div"),
                    R = document.createElement("div"), x = document.createElement("h1"),
                    k = document.createElement("div"), F = document.createElement("div"),
                    U = document.createElement("div");
            M.className = r, P.className = "introjs-tooltipReferenceLayer", document.querySelector("#dashboard-content").setAttribute("aria-hidden", "true"), this._lastFocusedElement = document.activeElement, y.call(t, M), y.call(t, P), this._targetElement.appendChild(M), this._targetElement.appendChild(P), w.className = "introjs-arrow", x.className = "introjs-tooltiptext", x.innerHTML = e.intro, x.tabIndex = -1, k.className = "introjs-bullets", !1 === this._options.showBullets && (k.style.display = "none");
            for (var V = document.createElement("ul"), B = (N = 0, this._introItems.length); N < B; N++) {
                var H = document.createElement("li"), q = document.createElement("a");
                q.onclick = function () {
                    t.goToStep(this.getAttribute("data-stepnumber"))
                }, N === e.step - 1 && (q.className = "active"), q.href = "javascript:void(0);", q.innerHTML = "&nbsp;", q.setAttribute("data-stepnumber", this._introItems[N].step), H.appendChild(q), V.appendChild(H)
            }
            k.appendChild(V), F.className = "introjs-progress", !1 === this._options.showProgress && (F.style.display = "none");
            var Y = document.createElement("div");
            if (Y.className = "introjs-progressbar", Y.setAttribute("style", "width:" + C.call(this) + "%;"), F.appendChild(Y), U.className = "introjs-tooltipbuttons", !1 === this._options.showButtons && (U.style.display = "none"), R.className = "introjs-tooltip", R.appendChild(x), s.innerHTML = e.ada, R.appendChild(s), R.appendChild(k), R.appendChild(F), 1 == this._options.showStepNumbers) {
                var j = document.createElement("span");
                j.className = "introjs-helperNumberLayer", j.innerHTML = e.step, P.appendChild(j)
            }
            R.appendChild(w), P.appendChild(R), (O = document.createElement("button")).onclick = function () {
                t._introItems.length - 1 != t._currentStep && l.call(t)
            }, O.innerHTML = this._options.nextLabel, (_ = document.createElement("button")).onclick = function () {
                0 != t._currentStep && u.call(t)
            }, _.innerHTML = this._options.prevLabel, (S = document.createElement("button")).className = "introjs-button introjs-skipbutton focus whiteOutline", S.innerHTML = this._options.skipLabel, S.onclick = function () {
                try {
                    t._introItems.length - 1 == t._currentStep && "function" == typeof t._introCompleteCallback && t._introCompleteCallback.call(t), t._introItems.length - 1 != t._currentStep && "function" == typeof t._introExitCallback && t._introExitCallback.call(t), d.call(t, t._targetElement)
                } catch (e) {
                    return i.log("exitTooltip failed", e), void g()
                }
            }, U.appendChild(S), this._introItems.length > 1 && (U.appendChild(_), U.appendChild(O)), R.appendChild(U), m.call(t, e.element, R, w, j)
        }
        !0 === this._options.disableInteraction && h.call(t), 0 == this._currentStep && this._introItems.length > 1 ? (_.className = "introjs-button introjs-prevbutton introjs-disabled", O.className = "introjs-button introjs-nextbutton focus whiteOutline", S.innerHTML = this._options.skipLabel, a && S.appendChild(a)) : 1 == this._introItems.length ? (S.innerHTML = e.exitLabel, a && S.appendChild(a), _.className = "introjs-button introjs-prevbutton focus whiteOutline", O.className = "introjs-button introjs-nextbutton introjs-disabled") : this._introItems.length - 1 == this._currentStep ? (S.innerHTML = this._options.doneLabel, a && S.appendChild(a), _.className = "introjs-button introjs-prevbutton focus whiteOutline", O.className = "introjs-button introjs-nextbutton introjs-disabled") : (_.className = "introjs-button introjs-prevbutton focus whiteOutline", O.className = "introjs-button introjs-nextbutton focus whiteOutline", S.innerHTML = this._options.skipLabel, a && S.appendChild(a)), e.element.className += " introjs-showElement";
        var G = b(e.element, "position");
        "absolute" !== G && "relative" !== G && (e.element.className += " introjs-relativePosition");
        for (var K = e.element.parentNode; null != K && "body" !== K.tagName.toLowerCase();) {
            var W = b(K, "z-index"), z = parseFloat(b(K, "opacity")),
                    X = b(K, "transform") || b(K, "-webkit-transform") || b(K, "-moz-transform") || b(K, "-ms-transform") || b(K, "-o-transform");
            (/[0-9]+/.test(W) || z < 1 || "none" !== X) && (K.className += " introjs-fixParent"), K = K.parentNode
        }
        if (!function (e) {
            var t = e.getBoundingClientRect();
            return t.top >= 0 && t.left >= 0 && t.bottom + 80 <= window.innerHeight && t.right <= window.innerWidth
        }(e.element) && !0 === this._options.scrollToElement) {
            var J = e.element.getBoundingClientRect(), $ = A().height, Z = J.bottom - (J.bottom - J.top),
                    Q = J.bottom - $;
            Z < 0 || e.element.clientHeight > $ ? window.scrollBy(0, Z - 130) : window.scrollBy(0, Q + 100)
        }
        void 0 !== this._introAfterChangeCallback && this._introAfterChangeCallback.call(this, e.element)
    }

    function b(e, t) {
        var n = "";
        return e.currentStyle ? n = e.currentStyle[t] : document.defaultView && document.defaultView.getComputedStyle && (n = document.defaultView.getComputedStyle(e, null).getPropertyValue(t)), n && n.toLowerCase ? n.toLowerCase() : n
    }

    function A() {
        if (null != window.innerWidth) return {width: window.innerWidth, height: window.innerHeight};
        var e = document.documentElement;
        return {width: e.clientWidth, height: e.clientHeight}
    }

    function E(e) {
        var t = document.createElement("div"), n = "", i = this;
        if (t.className = "introjs-overlay", "body" === e.tagName.toLowerCase()) n += "top: 0;bottom: 0; left: 0;right: 0;position: fixed;", t.setAttribute("style", n); else {
            var o = T(e);
            o && (n += "width: " + o.width + "px; height:" + o.height + "px; top:" + o.top + "px;left: " + o.left + "px;", t.setAttribute("style", n))
        }
        return e.appendChild(t), t.onclick = function () {
            1 == i._options.exitOnOverlayClick && (d.call(i, e), null != i._introExitCallback && i._introExitCallback.call(i))
        }, setTimeout((function () {
            n += "opacity: " + i._options.overlayOpacity.toString() + ";", t.setAttribute("style", n)
        }), 10), !0
    }

    function T(e) {
        var t = {};
        t.width = e.offsetWidth, t.height = e.offsetHeight;
        for (var n = 0, i = 0; e && !isNaN(e.offsetLeft) && !isNaN(e.offsetTop);) n += e.offsetLeft, i += e.offsetTop, e = e.offsetParent;
        return t.top = i, t.left = n, t
    }

    function C() {
        return parseInt(this._currentStep + 1, 10) / this._introItems.length * 100
    }

    var S = function (e) {
        if ("object" == typeof e) return new r(e);
        if ("string" == typeof e) {
            var t = document.querySelector(e);
            if (t) return new r(t);
            throw new Error("There is no element with given selector.")
        }
        return new r(document.body)
    };
    return S.version = "1.0.0", S.fn = r.prototype = {
        clone: function () {
            return new r(this)
        }, setOption: function (e, t) {
            return this._options[e] = t, this
        }, setOptions: function (e) {
            return this._options = function (e, t) {
                var n = {};
                for (var i in e) n[i] = e[i];
                for (var i in t) n[i] = t[i];
                return n
            }(this._options, e), this
        }, start: function () {
            return a.call(this, this._targetElement), this
        }, goToStep: function (e) {
            return c.call(this, e), this
        }, nextStep: function () {
            return l.call(this), this
        }, previousStep: function () {
            return u.call(this), this
        }, cancel: function () {
            o = !0
        }, exit: function () {
            return d.call(this, this._targetElement), this
        }, refresh: function () {
            return y.call(this, document.querySelector(".introjs-helperLayer")), y.call(this, document.querySelector(".introjs-tooltipReferenceLayer")), this
        }, onbeforechange: function (e) {
            if ("function" != typeof e) throw new Error("Provided callback for onbeforechange was not a function");
            return this._introBeforeChangeCallback = e, this
        }, onchange: function (e) {
            if ("function" != typeof e) throw new Error("Provided callback for onchange was not a function.");
            return this._introChangeCallback = e, this
        }, onafterchange: function (e) {
            if ("function" != typeof e) throw new Error("Provided callback for onafterchange was not a function");
            return this._introAfterChangeCallback = e, this
        }, oncomplete: function (e) {
            if ("function" != typeof e) throw new Error("Provided callback for oncomplete was not a function.");
            return this._introCompleteCallback = e, this
        }, onexit: function (e) {
            if ("function" != typeof e) throw new Error("Provided callback for onexit was not a function.");
            return this._introExitCallback = e, this
        }
    }, e.introJs = S, S
})),define("common/tours/singleTour", ["require", "common/lib/inview", "blue/$", "analytics/event/decisionedContentEvent", "blue/event/channel/component", "appkit-utilities/common/mediaQueryListener", "blue/siteMode", "common/tours/intro"], (function (e) {
    "use strict";
    e("common/lib/inview");
    var t, n, i = e("blue/$"), o = e("analytics/event/decisionedContentEvent"), r = e("blue/event/channel/component"),
            a = e("appkit-utilities/common/mediaQueryListener"), s = !1, c = e("blue/siteMode"),
            l = c.isModeEnabled("ecdStandIn"), u = c.isModeEnabled("readOnly"), d = e("common/tours/intro")(), m = {
                showStepNumbers: !1,
                overlayOpacity: 1,
                showBullets: !1,
                inview: !0,
                disableScrolling: !0,
                disableAnalytics: !1,
                customAnalyticsEvent: !1,
                delayTime: 1e3,
                delay: !0,
                exitOnOverlayClick: !1,
                onShow: null,
                onClose: null
            }, f = i("body"), p = i(window), g = {};
    return function (e, c) {
        return function (e, c) {
            if (e) {
                var y = {}, h = {};
                e.component && (y = e.component, h = y.context.application, delete e.component);
                var v = function (e) {
                    if (!E() && !u && !l) {
                        var t = i(m.steps[0].element), a = i("#header-outer-container");
                        setTimeout((function () {
                            if (!s) {
                                if (t.unbind("inview"), m.disableScrolling && f.addClass("disableScrolling"), n = "body-" + m.highlightClass, f.addClass(n), m.disableAnalytics || !y && !m.customAnalyticsEvent || (m.customAnalyticsEvent ? m.customAnalyticsEvent() : (g.toolTipElementSelector = m.steps[0].element, r.emit(new o(y, g)))), e && e < a.height()) {
                                    var i = a.height() - e + 50;
                                    p.scrollTop(p.scrollTop() + i)
                                }
                                t.is(":visible") && (d.start(), m.onShow && m.onShow(), p.on("breakpoint-change", A))
                            }
                        }), m.delay ? m.delayTime : 0), h.on({sendAdImpression: T, "blue:routeChange": C}), _()
                    }
                }, b = function () {
                    m.disableScrolling && f.removeClass("disableScrolling"), m.onClose && m.onClose(), f.removeClass(n), e.focusElementSelector && i(e.focusElementSelector).focus(), p.off("breakpoint-change", A), h.off("sendAdImpression", T), h.off("blue:routeChange", C), h.off("hideTooltipOnTimeoutModal", S), t.disconnect()
                }, A = function () {
                    E() && (d.exit(), b())
                }, E = function () {
                    return a.currentBreakpoint === a.BREAKPOINT.XS || a.currentBreakpoint === a.BREAKPOINT.SM
                }, T = function () {
                    d.refresh()
                }, C = function () {
                    s = !0, d.cancel(), d.exit(), b()
                }, S = function () {
                    d.exit(), b()
                }, _ = function () {
                    (t = new MutationObserver((function (e) {
                        e.forEach((function (e) {
                            e.addedNodes && e.addedNodes.length > 0 && d.refresh()
                        }))
                    }))).observe(i("#content")[0], {attributes: !0, childList: !0, characterData: !0})
                };
                return m.steps = [e], c && (m = i.extend(m, c)), d.setOptions(m).oncomplete(b).onexit(b), m.inview && i(m.steps[0].element).unbind("inview").one("inview", (function (e, t, n, i, o) {
                    v(o)
                })), {start: v}
            }
        }(e, c)
    }
})),define("common/oobe/singleTooltipUtil", ["require", "blue/$", "blue/util", "common/oobe/controllerMap", "common/oobe/elements", "common/tours/singleTour"], (function (e) {
    "use strict";
    var t = null, n = e("blue/$"), i = e("blue/util"), o = e("common/oobe/controllerMap"), r = {},
            a = e("common/oobe/elements"), s = e("common/tours/singleTour"), c = {}, l = {}, u = {}, d = !1,
            m = ["singleDoor/singleDoorController"];

    function f(e) {
        return n(e).length > 0
    }

    function p(e, t) {
        return e[t]
    }

    function g(e, n) {
        for (var i in t.on("hasCampaignMessage", (function () {
            d = !0
        })), t.on("oobeRequest", (function (e) {
            l[e.route] && 0 === o[e.route].length ? y(e) : (u = {})[e.route] = e
        })), e) {
            if (e.hasOwnProperty(i)) o[i].indexOf(n) < 0 ? (delete l[i], o[i] = r[i]) : o[i].splice(o[i].indexOf(n), 1), l[i] || (l[i] = {}, l[i].components = [], l[i].types = []), e[i].forEach((function (e) {
                var t = e.component, n = e.types ? e.types : ["Global"];
                t && (l[i].components.push(t), Array.isArray(n) && n.length && (l[i].types = l[i].types.concat(n), n.forEach((function (e) {
                    c[e] = t
                }))))
            }))
        }
        !function () {
            if (Object.keys(u).length) for (var e in l) if (u[e]) {
                0 === o[e].length && y(u[e]);
                break
            }
        }()
    }

    function y(e) {
        if (e && e.route && l[e.route]) for (var n = l[e.route], i = n.components.length - 1, o = i, r = function () {
            --o < 0 && (!function (e) {
                var n, i;
                if (d) return;
                for (var o = 0; o < e.elm.length; o++) {
                    if ("Empty" === e.elm[o]) {
                        e.elm = e.elm[o], t.emit("oobeResponse", e);
                        break
                    }
                    if (n = a[e.elm[o]], i = c[e.elm[o]], "Global" === e.elm[o] && c && c.Global) {
                        h(), e.elm = e.elm[o], t.emit("requestOutOfBoxExperience", {
                            autoInvoked: !0,
                            onShow: function () {
                                t.emit("oobeResponse", e)
                            }
                        });
                        break
                    }
                    if ("DashboardOverview" === e.elm[o] && c && c.DashboardOverview) {
                        h(), e.elm = e.elm[o], t.emit("requestDashboardOverviewTooltip", {
                            onShow: function () {
                                t.emit("oobeResponse", e)
                            }
                        });
                        break
                    }
                    if ("SingleDoor" === e.elm[o]) e.elm = e.elm[o], t.emit("openSingleDoorFlyout"), t.emit("oobeResponse", e); else if (i && f(n.selector)) {
                        h();
                        var r = n.parentSelector || n.selector;
                        e.elm = e.elm[o], s({
                            element: r,
                            intro: p(i, n.contentKey),
                            exitLabel: p(i, "exitLabel"),
                            ada: n.adaKey && p(i, n.adaKey),
                            position: n.position,
                            component: i,
                            focusElementSelector: n.focusElementSelector
                        }, {
                            onShow: function () {
                                var o = n.analyticsOpenAction ? i[n.analyticsOpenAction] : null;
                                o && o(), t.emit("oobeResponse", e)
                            }, onClose: function () {
                                var e = n.analyticsCloseAction ? i[n.analyticsCloseAction] : null;
                                e && e()
                            }, highlightClass: "single-tooltip-" + e.elm, delay: e.delay
                        });
                        break
                    }
                }
            }(e), u = {})
        }; i >= 0;) !(m = n.components[i]) || m.destroyed || m.__hasView || "undefined" === m.output ? r() : n.components[i].output.on("ready", r), i--;
        var m
    }

    function h() {
        t.broadcast("oobeInitiated")
    }

    return r = i.object.merge(o), {
        register: function (e, n, i) {
            t = i, e && n && -1 !== m.indexOf(n) && g(e, n)
        }
    }
})),define("common/service/helpers/serviceHelper", [], (function () {
    "use strict";
    return {
        getServiceList: function (e, t, n) {
            var i = {};
            return Object.keys(e).forEach((function (o) {
                i[o] = function (e, t, n) {
                    var i = {settings: {}};
                    "string" == typeof e && (e = {url: e});
                    e.isMock && n && (t = n);
                    return i.settings = e, i.settings.url = t + e.url, i
                }(e[o], t, n)
            })), i
        }
    }
})),define("common/service/interceptor/filterRequestParams", [], (function () {
    "use strict";
    return {
        around: function (e) {
            var t, n = {};
            e.args.length && (n = e.args[0], "object" == typeof (t = n.data) && Object.keys(t).forEach((function (e) {
                void 0 === t[e] && delete t[e]
            })));
            return e.proceed(n)
        }
    }
})),define("common/service/interceptor/loggedOffInterceptor", [], (function () {
    "use strict";
    return {
        around: function (e) {
            var t = this, n = this.context.logger;
            return new Promise((function (i, o) {
                t.context.application.userLoggedOff ? (n.debug("401 UNAUTHORIZED: skipping secure service call"), o()) : e.proceed().then((function (e) {
                    i(e)
                })).catch((function (e) {
                    401 === e.status && (t.context.application.userLoggedOff = !0, n.debug("401 UNAUTHORIZED: skipping future secure service calls")), o(e)
                }))
            }))
        }
    }
})),define("common/service/interceptor/mspIntercept", ["require", "blue/util", "blue/is", "common/lib/mspUrlConfig"], (function (e) {
    var t = e("blue/util"), n = e("blue/is"), i = e("common/lib/mspUrlConfig"), o = i.urlObj, r = i.detailType,
            a = function (e) {
                return e.replace(domainUrl, "")
            }, s = function (e, t) {
                var i = o[t].properties ? o[t].properties : [], a = Object.keys(r);
                return n.object(e) ? Object.keys(e).forEach((function (n) {
                    i.indexOf(n) >= 0 ? a.indexOf(e[n]) >= 0 && (e[n] = r[e[n]]) : e[n] = s(e[n], t)
                })) : n.array(e) ? e.forEach((function (n, i) {
                    e[i] = s(n, t)
                })) : r[e] && (e = r[e]), e
            };
    return {
        around: function (e) {
            var n;
            return new Promise((function (i, c) {
                var l;
                e.args[0].url && t.object.has(o, a(e.args[0].url)) && (l = a(e.args[0].url)), n = e.proceed();
                var u = function (e, n) {
                    var a = e ? i : c;
                    l ? o[l].hasArray ? a(s(n, l)) : (o[l].properties.forEach((function (e) {
                        var i = t.object.get(n, e);
                        r[i] && t.object.set(n, e, r[i])
                    })), a(n)) : a(n)
                };
                n.then((function (e) {
                    u(!0, e)
                }), (function (e) {
                    u(!1, e)
                }))
            }))
        }
    }
})),define("common/service/interceptor/retryOnError", [], (function () {
    return {
        around: function (e) {
            var t = function () {
            }, n = 0, i = 0, o = "", r = [];
            return new Promise((function (a, s) {
                var c = e.args.length && e.args[0];
                n = c.retryLimit || n, i = c.retryBufferInterval || i, o = c.retryHeader || o, r = c.retryStatusCodes || r;
                var l = function () {
                    e.proceed().then(a).catch((function (e) {
                        var a, c = e || {getResponseHeader: t};
                        o && (a = c.getResponseHeader(o), i = /^([1-9]|[1-9][0-9]|1[0-1][0-9]|120)$/.test(a) ? 1e3 * a : 0), n > 0 && i > 0 && function (e, t) {
                            return !(!Array.isArray(e) || !e.length) && e.indexOf(t) > -1
                        }(r, c.status) ? setTimeout((function () {
                            n--, l()
                        }), i) : s(e)
                    }))
                };
                l()
            }))
        }
    }
})),define("common/service/interceptor/spinner", ["require", "blue/$", "blue/util"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = e("blue/util").lang.defaults;
    return {
        around: function (e) {
            var i, o, r, a = (new Date).getTime() + Math.floor(100 * Math.random() + 1), s = e.args[0];
            i = "spinner_" + a;
            var c = s.data && ("string" == typeof s.data && {target: "#content"} || s.data.spinner);
            return c && (r = !0, "object" == typeof c ? (n(c.type, ""), c.name = n(c.name, "interceptor." + i), o = c.focusId) : c = {
                name: "interceptor." + i,
                type: ""
            }), delete s.data.spinner, new Promise((function (n, i) {
                r && e.target.context.application.emit("spinner:on", c), e.proceed().then((function (i) {
                    try {
                        n(i)
                    } catch (e) {
                    }
                    r && (e.target.context.application.emit("spinner:off", c), "string" == typeof o ? t("#" + o).focus() : o && "function" == typeof o.focus && (o.focus(), o = void 0))
                }), (function (t) {
                    try {
                        i(t)
                    } catch (e) {
                    }
                    r && e.target.context.application.emit("spinner:off", c), o = void 0
                })).catch((function (t) {
                    try {
                        i(t)
                    } catch (e) {
                    }
                    r && e.target.context.application.emit("spinner:off", c), o = void 0
                }))
            }))
        }
    }
})),define("common/service/interceptor/userProfileComplete", [], (function () {
    "use strict";
    return {
        around: function (e) {
            var t, n, i = this;
            return e.args.length && (n = (t = i.context.util.object.deepClone(e.args[0])) || {}), n.byPassUserProfileService ? e.proceed() : new Promise((function (n, o) {
                i.context.application.userProfileComplete.then((function () {
                    t && (e.args[0] = e.target.prerequest(t, t.data)), e.proceed().then((function (e) {
                        n(e)
                    })).catch((function (e) {
                        o(e)
                    }))
                })).catch((function (e) {
                    o(e)
                }))
            }))
        }
    }
})),define("common/service/keepAlive", [], (function () {
    "use strict";
    return function () {
        var e = {
            settings: {
                url: authUrl + "/svc/wr/accounts/secure/v1/user/session/extend",
                type: "POST",
                dataType: "json"
            }
        };
        this.serviceCalls = {userSessionExtend: e}
    }
})),define("common/service/signOut", ["require", "exports", "module"], (function (e, t, n) {
    "use strict";
    return function () {
        var e, t = n.config();
        t.authServiceCrossDomain ? e = t.authServiceCrossDomain : this.context && this.context.config && (e = this.context.config.authServiceCrossDomain);
        var i = function (t) {
                    this.type = "POST", this.dataType = "json", this.crossDomain = !1 !== e, this.url = t
                }, o = {settings: new i(authUrl + "/svc/wl/auth/signout")},
                r = {settings: new i(domainUrl + "/svc/rr/accounts/secure/v1/signout")};
        this.serviceCalls = {authSignout: o, accountsSignout: r}
    }
})),define("common/service/siteAvailability", ["require", "exports", "module"], (function (e, t, n) {
    "use strict";
    return function () {
        var e, t = n.config();
        t.authServiceCrossDomain ? e = t.authServiceCrossDomain : this.context && this.context.config && (e = this.context.config.authServiceCrossDomain);
        var i = {
            settings: new function () {
                this.type = "POST", this.dataType = "json", this.crossDomain = !1 !== e, this.url = domainUrl + "/svc/rl/accounts/public/v1/site/availability/list"
            }
        };
        this.serviceCalls = {siteAvailability: i}
    }
})),define("common/stickyTitle/headerUtility", [], (function () {
    "use strict";
    var e;
    return {
        adjustHeight: function () {
            "function" == typeof e && e()
        }, onAdjustHeight: function (t) {
            e = t
        }
    }
})),define("common/template/stickyTitle/stickyTitle", [], (function () {
    return {
        v: 4, t: [{
            t: 4, f: [{
                t: 7,
                e: "blueInlineModalHeader",
                m: [{n: "id", f: [{t: 2, r: "id"}], t: 13}, {
                    n: "title",
                    f: [{t: 2, r: "title"}],
                    t: 13
                }, {
                    n: "adaCloseText",
                    f: [{t: 4, f: [{t: 2, r: "adaCloseText"}], n: 50, r: "adaCloseText"}],
                    t: 13
                }, {n: "classes", f: [{t: 4, f: [{t: 2, r: "classes"}], n: 50, r: "classes"}], t: 13}, {
                    n: "type",
                    f: [{t: 2, r: "type"}],
                    t: 13
                }, {
                    n: "secondaryText",
                    f: [{t: 4, f: [{t: 2, r: "secondaryText"}], n: 50, r: "secondaryText"}],
                    t: 13
                }, {n: "rClick", f: [{t: 4, f: [{t: 2, r: "rClick"}], n: 50, r: "rClick"}], t: 13}, {
                    n: "rChange",
                    f: [{t: 4, f: [{t: 2, r: "rChange"}], n: 50, r: "rChange"}],
                    t: 13
                }, {
                    n: "rMouseover",
                    f: [{t: 4, f: ["r", {t: 2, r: "Mouseover"}], n: 50, r: "rMouseover"}],
                    t: 13
                }, {
                    n: "rMouseleave",
                    f: [{t: 4, f: [{t: 2, r: "rMouseleave"}], n: 50, r: "rMouseleave"}],
                    t: 13
                }, {n: "rKeydown", f: [{t: 4, f: [{t: 2, r: "rKeydown"}], n: 50, r: "rKeydown"}], t: 13}, {
                    n: "rKeyup",
                    f: [{t: 4, f: [{t: 2, r: "rKeyup"}], n: 50, r: "rKeyup"}],
                    t: 13
                }, {n: "rBlur", f: [{t: 4, f: [{t: 2, r: "rBlur"}], n: 50, r: "rBlur"}], t: 13}, {
                    n: "rFocus",
                    f: [{t: 4, f: [{t: 2, r: "rFocus"}], n: 50, r: "rFocus"}],
                    t: 13
                }, {
                    n: "blueOnComplete",
                    f: [{t: 4, f: [{t: 2, r: "blueOnComplete"}], n: 50, r: "blueOnComplete"}],
                    t: 13
                }, {n: "attributes", f: [{t: 2, r: "attributes"}], t: 13}]
            }], n: 50, x: {r: ["show", "byPassGlobalNavigation"], s: "_0||_1"}
        }], e: {}
    }
})),define("common/stickyTitle/webspec/stickyTitle", {
    name: "STICKY_TITLE",
    bindings: {},
    triggers: {teardown: {action: "view.onDestroy"}}
}),define("common/stickyTitle/stickyTitle", ["require", "common/lib/menu/menuState", "common/lib/menu/menuUtility", "common/stickyTitle/headerUtility", "common/template/stickyTitle/stickyTitle", "blue-ui/view/elements/inlinemodalheader", "common/stickyTitle/webspec/stickyTitle"], (function (e) {
    "use strict";
    var t = e("common/lib/menu/menuState"), n = e("common/lib/menu/menuUtility"),
            i = e("common/stickyTitle/headerUtility");
    return function () {
        this.template = e("common/template/stickyTitle/stickyTitle"), this.views = {blueInlineModalHeader: e("blue-ui/view/elements/inlinemodalheader")}, this.onReady = function () {
            i.adjustHeight(), t.stickyHeaderActive = !t.globalNavigationIsShowing, n.checkKeyAndReset("smoothStickyTransition")
        }, this.init = function () {
            this.bridge = e("common/stickyTitle/webspec/stickyTitle"), this.model.show = !t.globalNavigationIsShowing
        }, this.onDestroy = function () {
            t.stickyHeaderActive && n.registerAction("smoothStickyTransition", !0)
        }
    }
})),define("common/timeout/component/timeout", ["require", "appkit-utilities/analytics/overlay"], (function (e) {
    "use strict";
    var t = e("appkit-utilities/analytics/overlay");
    return {
        init: function () {
        }, requestSessionExtension: function () {
            t.hideOverlay(this, "sessionTimeoutOverlay", "requestSessionExtension"), this.destroyView(), this.context.bubble("timeoutComponent:requestSessionExtension")
        }, proceedToLogoff: function (e) {
            this.context.proceedToLogoff(e)
        }
    }
})),define("bluespec/session_timeout", [], (function () {
    return {
        name: "SESSION_TIMEOUT",
        actions: {requestSessionExtension: !0, proceedToLogoff: !0, returnToChaseOnline: !0},
        states: {sessionTimeoutOverlay: !0},
        settings: {
            sessionTimeoutHeader: !0,
            sessionTimeoutMessage: !0,
            requestSessionExtensionLabel: !0,
            proceedToLogoffLabel: !0,
            returnToChaseOnlineLabel: !0,
            closeSessionTimeoutNavigation: !0,
            closeSessionTimeoutNavigationAda: !0
        }
    }
})),define("common/timeout/controller/timeout", ["require", "bluespec/session_timeout", "common/timeout/component/timeout", "appkit-utilities/analytics/overlay", "blue-app/settings", "common/lib/accountInfo"], (function (e) {
    "use strict";
    return function (t) {
        var n = this, i = e("bluespec/session_timeout"), o = e("common/timeout/component/timeout"),
                r = e("appkit-utilities/analytics/overlay"), a = e("blue-app/settings"),
                s = new (e("common/lib/accountInfo"));
        this.init = function () {
            this.logoff.setAppContext(t.application), this.accountInfo = s, t.on({
                "timeout:showModal": this.showModal,
                "timeout:extendSession": this.extendSession,
                "timeout:proceedToLogoff": this.proceedToLogoff
            }), this.context.proceedToLogoff = this.proceedToLogoff
        }, this.showModal = function () {
            n.components && n.components.timeout ? (r.showOverlay(n.components.timeout, "sessionTimeoutOverlay"), n.executeCAV([n.components.timeout, "timeout/timeout", {
                target: "#sessionTimeoutModal",
                append: !1
            }])) : (n.register.components(n, [{
                name: "timeout",
                model: {},
                spec: i,
                methods: o
            }]), r.showOverlay(n.components.timeout, "sessionTimeoutOverlay"), n.executeCAV([n.components.timeout, "timeout/timeout", {
                target: "#sessionTimeoutModal",
                append: !1
            }]))
        }, this.extendSession = function () {
            n.context.services.userSessionExtendSvc.userSessionExtend().then((function (e) {
                t.logger.debug("Extend service called, result " + e.status), a.set({lastServiceCallTime: Date.now() ? Date.now() : (new Date).getTime()}, a.Type.USER)
            }), (function (e) {
                var i = e ? e.status : "?", o = e ? e.statusText : "Unknown";
                t.logger.debug("Error from service, logging off, status " + i + " message: " + o), n.proceedToLogoff({
                    isSessionTimeout: !1,
                    needToHideOverlay: !1
                })
            }))
        }, this.proceedToLogoff = function (e) {
            n.context.bubble("timeoutComponent:proceedToLogoff"), n.logOffThirdParty && n.logOffThirdParty.signOutAndRedirect ? n.logOffThirdParty.signOutAndRedirect() : (n.logoff.signOutAndRedirect(e.isSessionTimeout), n.context.is.defined(e.needToHideOverlay) && !e.needToHideOverlay || n.components && n.components.timeout && r.hideOverlay(n.components.timeout, "sessionTimeoutOverlay", "proceedToLogoff"), n.components && n.components.timeout && n.components.timeout.destroyView())
        }
    }
})),define("common/template/sessionTimeout", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "blueModal",
            m: [{n: "id", f: "sessionTimeoutDialog", t: 13}, {
                n: "beginModalAdaText",
                f: "beginModalAdaText",
                t: 13
            }, {n: "endModalAdaText", f: "endModalAdaText", t: 13}, {
                n: "cancelButtonId",
                f: "proceedToLogoff",
                t: 13
            }, {n: "modalWindow", f: "", t: 13}, {
                n: "cancelButtonClasses",
                f: "cancelButtonClasses",
                t: 13
            }, {n: "cancelButtonText", f: [{t: 2, r: "proceedToLogoffLabel"}], t: 13}, {
                n: "cancelButtonAdaText",
                f: [{t: 2, r: "cancelButtonAdaText"}],
                t: 13
            }, {n: "cancelButtonClick", f: "proceedToLogoff", t: 13}, {
                n: "confirmationButtonId",
                f: "requestSessionExtension",
                t: 13
            }, {
                n: "confirmationButtonClasses",
                f: ["confirmationButtonClasses ", {t: 4, f: ["jpmorgan"], n: 50, r: ".isGWMUser"}],
                t: 13
            }, {
                n: "confirmationButtonText",
                f: [{t: 2, r: "requestSessionExtensionLabel"}],
                t: 13
            }, {
                n: "confirmationButtonAdaText",
                f: [{t: 2, r: "confirmationButtonAdaText"}],
                t: 13
            }, {n: "confirmationButtonClick", f: "requestSessionExtension", t: 13}, {
                n: "dialogText",
                f: [{t: 3, r: "sessionTimeoutMessage"}],
                t: 13
            }, {n: "title", f: [{t: 2, r: "sessionTimeoutHeader"}], t: 13}]
        }]
    }
})),define("common/timeout/view/webspec/timeout", {
    name: "SESSION_TIMEOUT",
    bindings: {},
    triggers: {teardown: {action: "view.teardown"}}
}),define("common/timeout/view/timeout", ["require", "blue/$", "common/lib/accountInfo", "common/template/sessionTimeout", "blue-ui/view/modules/modal", "common/timeout/view/webspec/timeout"], (function (e) {
    "use strict";
    return function () {
        var t, n, i = e("blue/$"), o = new (e("common/lib/accountInfo"));
        this.template = e("common/template/sessionTimeout"), this.init = function () {
            (n = this).views = {blueModal: e("blue-ui/view/modules/modal")}, n.bridge = e("common/timeout/view/webspec/timeout"), t = document.activeElement ? i(document.activeElement) : i("body"), i("body").addClass("util no-scroll"), n.onReady = function () {
                n.onData("sessionTimeoutHeader", (function (e) {
                    e && n.context.$("h1").focus()
                }))
            }, n.model.isGWMUser = !!o.isGWMUser()
        }, this.teardown = function () {
            var e, n = i("body");
            n.removeClass("util no-scroll"), t.length ? t.focus() : (e = i("h1").first()).length > 0 ? e.focus() : n.focus()
        }
    }
})),define("common/utility/accountsUtility", ["require", "common/lib/constants"], (function (e) {
    "use strict";
    var t = e("common/lib/constants"), n = t.ACCOUNT_FILTERING.CBO_FILTER_CRITERIA_ID,
            i = t.ACCOUNT_FILTERING.CBO_ACCOUNTS_FILTER_CRITERIA_TYPE;

    function o(e, t, o) {
        var r = !1;
        return e === n.ALL_ACCOUNTS && (r = !0), t.isBusiness && e === n.ALL_BUSINESS && (r = !0), t.isBusiness || e !== n.ALL_PERSONAL || (r = !0), e === t.accountCategoryId && o === i.BUSINESS && (r = !0), r
    }

    function r(e) {
        var t = [];
        return Object.keys(e).forEach((function (n) {
            e[n] && t.push(e[n])
        })), t
    }

    function a(e, t, n) {
        var i = [];
        return e.forEach((function (e) {
            e.accountType === t && (e.allAccounts ? e.allAccounts.forEach((function (e) {
                n[e] && i.push(n[e])
            })) : e.accountCategories.forEach((function (e) {
                e.accounts.forEach((function (e) {
                    n[e] && i.push(n[e])
                }))
            })))
        })), i
    }

    function s(e, t, n) {
        var i = [];
        return e.forEach((function (e) {
            e.id === t && e.customGroupAccountIds.forEach((function (e) {
                n[e] && i.push(n[e])
            }))
        })), i
    }

    function c(e) {
        var t = [];
        return e.favorites.forEach((function (n) {
            t.push(e.accounts[n])
        })), t
    }

    function l(e, t, n) {
        var i = [], r = t.filterId || t.filterCriteriaId;
        return e.forEach((function (e) {
            o(r, e, t.filterType) && e.accountGroups.forEach((function (e) {
                e.accounts.forEach((function (e) {
                    n[e] && i.push(n[e])
                }))
            }))
        })), i
    }

    var u = function (e) {
        return e.filterType === n.ALL_ACCOUNTS || e.filterCriteriaId === n.ALL_ACCOUNTS
    }, d = function (e) {
        return e.filterType === n.ACCOUNT_GROUP
    }, m = function (e, t) {
        return (e.filterType === n.CUSTOM_GROUP || e.filterType === n.CUSTOM_ACCOUNTS) && t.customGroups
    }, f = function (e) {
        return e.filterCriteriaId === n.FAVORITES
    };
    return {
        getVisibleAccounts: function (e, t) {
            var n;
            t || (t = {
                filterId: (n = e).filterId || n.accountFilterId,
                filterCriteriaId: n.filterCriteriaId,
                filterType: n.accountFilterType || n.defaultGroupName
            });
            var i = {
                ALL_ACCOUNTS: [r, e.accounts],
                ACCOUNT_GROUP: [a, e.accountGroups, t.filterId, e.accounts],
                CUSTOM_GROUP: [s, e.customGroups, t.filterId, e.accounts],
                FAVORITES: [c, e],
                DEFAULT: [l, e.accountCategories, t, e.accounts]
            }[function (e, t) {
                return [{key: "ALL_ACCOUNTS", condition: u(e)}, {
                    key: "ACCOUNT_GROUP",
                    condition: d(e)
                }, {key: "CUSTOM_GROUP", condition: m(e, t)}, {key: "FAVORITES", condition: f(e)}, {
                    key: "DEFAULT",
                    condition: !0
                }].find((function (e) {
                    return e.condition
                })).key
            }(t, e)];
            return i[0](i[1], i[2], i[3])
        }, isGroupVisible: function (e, t, o) {
            var r = !1;
            return e === n.ALL_ACCOUNTS && (r = !0), o === i.ACCOUNT_GROUP && e === t.accountType && (r = !0), e === t.id && (r = !0), r
        }, isCategoryVisible: o, getFirstMortgageApplication: function (e) {
            var n = {};
            return e.pendingApplications && e.pendingApplications.length && (n = e.pendingApplications.find((function (e) {
                return e.accountType === t.DETAIL_TYPE.HMG
            }))), n && "0" !== n.applicationId ? n : void 0
        }
    }
})),define("common/utility/alertUtility", ["require", "blue-ui/utilities/common"], (function (e) {
    "use strict";
    var t = e("blue-ui/utilities/common");
    return {
        getFocusElementId: function (e) {
            return (t.isApple() && t.isTouch() ? "icon" : "inner") + "-" + e
        }
    }
})),define("common/utility/brandedFaviconUtil", ["require", "common/lib/constants", "appkit-utilities/common/mediaQueryListener"], (function (e) {
    "use strict";
    var t = e("common/lib/constants").BRAND_ID, n = e("appkit-utilities/common/mediaQueryListener");
    return {
        displayBrandedFavicon: function (e, i) {
            if (e === t.JPMORGAN) {
                var o = i + "/content/dam/cpo-static/images/jpm-favicon.ico";
                n.currentBreakpoint === n.BREAKPOINT.XS && (o = i + "/content/dam/cpo-static/images/jpmorgan-76x76.png"), document.querySelector('link[rel*="shortcut icon"]').href = o
            }
        }
    }
})),define("common/utility/canadaProvinceUtil", [], (function () {
    "use strict";
    return {
        mapCanadaProvince: function (e) {
            return "QC" === e ? e = "PQ" : "PQ" === e && (e = "QC"), "YT" === e ? e = "YK" : "YK" === e && (e = "YT"), e
        }
    }
})),define("common/utility/classicResourceUtil", ["require", "appkit-utilities/language/helper", "blue/root"], (function (e) {
    var t = e("appkit-utilities/language/helper"), n = e("blue/root");
    return {
        classicUtils: {
            getLanguage: function () {
                return t.getLanguage()
            }, getResourceUrl: function (e, t, n) {
                var i, o, r = {};
                return t && e && (o = t.CLASSIC_RESOURCE_URL || t.get("CLASSIC_RESOURCE_URL")) && (r = o[e]) ? (i = this.getConfiguredDomain(r, n), this.appendCipDomain(i)) : ""
            }, getResourceUrlWithParams: function (e, t, n) {
                var i, o, r, a, s, c = {};
                return t && e && e[0] && (c = (t.CLASSIC_RESOURCE_URL || t.get("CLASSIC_RESOURCE_URL"))[e[0]]) ? (i = this.getConfiguredDomain(c, n), o = c.paramName, a = c.addlParamName, r = e[1], o || (o = "AI"), s = e[2], i = this.appendQueryParameter(i, o, r), i = this.appendQueryParameter(i, a, s), this.appendCipDomain(i)) : ""
            }, getIframeUrl: function (e, t, n) {
                var i, o = {};
                return t && e && (o = t.get("IFRAME_URL")[e]) && (i = this.getConfiguredDomain(o, n)), i
            }, getConfiguredDomain: function (e, t) {
                if (t && t.classicDomains && e && e.domain) {
                    var n = t.classicDomains[e.domain.spanish], i = t.classicDomains[e.domain.english];
                    return (n && "es-us" === this.getLanguage() ? n : i) + e.path
                }
                return ""
            }, stringEndsWith: function (e, t) {
                return !!e && -1 !== e.indexOf(t, e.length - t.length)
            }, appendQueryParameter: function (e, t, n) {
                if (e && t && n) {
                    var i = -1 !== e.indexOf("?") ? "&" : "?";
                    e += i + t + "=" + encodeURI(n)
                }
                return e
            }, appendCipDomain: function (e) {
                if (e) {
                    var t = -1 !== e.indexOf("?") ? "&" : "?";
                    e += t + "cipDomain=" + this.getCipDomain()
                }
                return e
            }, getCipDomain: function () {
                return n.location.host
            }, getRandom: function () {
                var e = new Int32Array(1);
                return (n.crypto || n.msCrypto).getRandomValues(e), e[0]
            }
        }
    }
})),define("common/utility/webNativeMessenger", ["require", "appkit-utilities/jsBridge/index", "common/lib/jsBridge", "common/utility/rapidash", "common/lib/utility/hybridMixin"], (function (e) {
    "use strict";
    return function (t, n) {
        var i = {}, o = e("appkit-utilities/jsBridge/index"), r = e("common/lib/jsBridge"),
                a = e("common/utility/rapidash"), s = n.application.config;

        function c(e, t) {
            return t && a.isObject(e) && (e.oldJsBridgeMessage = t), e
        }

        function l(e, t, i) {
            n.logger.info("WebNativeMessenger : Eventname =" + e + " OldMessage =" + JSON.stringify(t) + " payLoad =" + JSON.stringify(i))
        }

        return e("common/lib/utility/hybridMixin").call(i), i.refreshProfile = function (e) {
            if (l("refreshProfile", e), !e || t("CR0420_releaseVersion") && s.enableRefreshProfile) {
                var r = c({}, e);
                o.messageToNative({command: "refreshProfile", params: r})
            } else i.dispatchHybridEvent(e, n)
        }, i.addCardToWallet = function (e) {
            l("addCardToWallet", "", e), t("CR0420_releaseVersion") && s.enableAddCardToWallet ? o.messageToNative({
                command: "addCardToWallet",
                params: e
            }) : i.dispatchHybridEvent("addCardToWallet", n, e)
        }, i.goToNativePage = function (e, r) {
            l("goToNativePage", r, e), !r || t("CR0420_releaseVersion") && s.enableGoToNativePage ? (e = function (e, t) {
                if (t) {
                    if (!a.isObject(e)) return {navKey: e, oldJsBridgeMessage: t};
                    e.oldJsBridgeMessage = t
                }
                return e
            }(e, r), o.messageToNative({command: "goToNativePage", params: e})) : i.dispatchHybridEvent(r, n, e)
        }, i.notifyToNative = function (e, r) {
            l("notifyToNative", r, e), !r || t("CR0420_releaseVersion") && s.enableNotifyToNative ? (e = c(e, r), o.messageToNative({
                command: "notifyToNative",
                params: e
            })) : r && i.dispatchHybridEvent(r, n, e)
        }, i.updateNativeNavigationBar = function (e) {
            l("updateNativeNavigationBar", "", e), r.updateCurrentNativeNavigationBarButtons(e), e.isUsingAppkitJsBridge = !1, t("CR0420_releaseVersion") && s.enableUpdateNativeNavigationBar ? (e = function (e) {
                return e && e.pageTitle && (e = c(e, "changeScreenTitle")), e
            }(e)).isUsingAppkitJsBridge = !0 : e && e.pageTitle && i.dispatchHybridEvent("changeScreenTitle", n, e.pageTitle), o.messageToNative({
                command: "updateNativeNavigationBarButtons",
                params: e
            })
        }, i.setShouldNativeHandleButtonPressedCallBack = function (e, n) {
            for (var i in l("setShouldNativeHandleButtonPressedCallBack", n, e), (!n || t("CR0420_releaseVersion") && s.enableShouldNativeHandleButtonPressed) && o.jsHandlers.setShouldNativeHandleButtonPressedCallBack(e), n) n.hasOwnProperty(i) && (r[i] = n[i])
        }, i.getPDFDocument = function (e, r) {
            l("getPDFDocument", r, e), !r || t("CR0420_releaseVersion") && s.enableGetPdfDocument ? (e = c(e, r), o.messageToNative({
                command: "getPDFDocument",
                params: e
            })) : i.dispatchHybridEvent(r, n, e)
        }, i.externalBrowser = function (e, r) {
            l("externalBrowser", r, e), !r || t("CR0420_releaseVersion") && s.enableExternalBrowser ? (e = c(e, r), o.messageToNative({
                command: "externalBrowser",
                params: e
            })) : i.dispatchHybridEvent(r, n, e)
        }, i.onFinish = function (e) {
            l("onFinish", "", e), t("CR0420_releaseVersion") && s.enableOnFinish ? o.messageToNative({
                command: "onFinish",
                params: e
            }) : e ? i.dispatchHybridEvent("onFinish", n, "", e.requireRefresh, e.nativeHamburgerMenuSelectedOption, e.exitToNativeEntryPoint) : i.dispatchHybridEvent("onFinish", n)
        }, i.releaseSpinner = function () {
            o.messageToNative({command: "releaseSpinner"})
        }, i
    }
})),define("common/utility/hybridHelper", ["require", "common/utility/rapidash", "common/utility/webNativeMessenger"], (function (e) {
    "use strict";
    var t = e("common/utility/rapidash"), n = {MOD: "android", MON: "ios"}, i = {
        ios: {
            CR0120_releaseVersion: "4.100",
            CR0320_releaseVersion: "4.150",
            MR1020_releaseVersion: "4.160",
            CR0420_releaseVersion: "4.170"
        },
        android: {
            CR0120_releaseVersion: "4.100",
            CR0320_releaseVersion: "4.150",
            MR1020_releaseVersion: "4.160",
            CR0420_releaseVersion: "4.170"
        }
    };
    return function (o, r) {
        var a = {};
        return a.isMobileVersionAtOrAbove = function (e) {
            var a, s = function (e) {
                return e && n[e]
            }(r);
            return o.logger.info("isMobileVersionAtOrAbove: channel = " + r), !!s && (a = t.isObject(e) ? i[s][e[s]] : i[s][e], o.application.nativeAppVer >= a)
        }, a.webNativeMessenger = e("common/utility/webNativeMessenger")(a.isMobileVersionAtOrAbove, o), a
    }
})),define("common/utility/printUtil", ["require", "blue/log", "blue/$"], (function (e) {
    var t = e("blue/log")("[printUtil]"), n = e("blue/$"), i = "", o = "",
            r = [".jpui.checkbox", ".jpui.radiobutton", ".jpui.select", ".jpui.dropdown", ".jpui.error alert", ".jpui.inverted dark alert", ".jpui.button", ".jpui.angledown icon", ".jpui.angleup icon", ".jpui.angle right icon", ".jpui.angle left icon", ".jpui.link", ".jpui.navbar", ".jpui.tooltip", ".jpui.fieldgroup", ".jpui.datepicker", ".jpui.styledselect", ".field>.labelWrap"],
            a = [];

    function s(e) {
        return e.domEvent.srcElement ? e.domEvent.srcElement : e.domEvent.target
    }

    function c(e) {
        n(n(e).find(r.toString(","))).hide(), n(n(e).find(a.toString(","))).show(), n(n(e).find("th>a")).show(), o = e ? e.outerHTML : "", n(n(e).find(r.toString(","))).show()
    }

    function l(e, t) {
        var i, s, c, l;
        if (t = t.length ? t : "print-util", !(i = n(e).closest("tr").length ? n(e).closest("tr") : n(e).closest(t).length ? n(e).closest(t).parent() : null)) return !1;
        s = n(i).clone(), n(n(s[0]).find(r.toString(","))).hide(), n(n(s[0]).find(a.toString(","))).show(), c = document.createElement("div"), l = document.createElement("br"), c.appendChild(s[0] ? s[0] : ""), c.appendChild(l), o = c ? c.outerHTML : "", n(n(s[0]).find(r.toString(","))).show()
    }

    function u(e, t) {
        var i, o = n(e).closest("tbody"), r = n(o).closest("table"), a = n(r).clone(), s = n(o).clone();
        n(a[0]).find("tbody").remove(), a.append(s), n(a).find(".util.accessible-text").addClass("print-hide"), t ? ((i = n("." + t).clone()).empty(), n(i)[0].appendChild(a[0]), c(i[0])) : c(a[0])
    }

    function d(e, i) {
        var o, r, a, s = document.createElement("div"), l = function (t) {
            var o, r, a, c, l, u, d, m, f, p = {}, g = [];
            if (n(t + " :checkbox:checked").each((function () {
                g.push(n(this))
            })), 0 !== n(t + " :checkbox").length && 0 === g.length) return !1;
            if (0 === i.targetIds.length ? (p = n(t).closest("table").clone(), i.targetClass && (p = n(t).closest("." + i.targetClass).clone())) : (o = n(n(t).parent()[0].parentNode.previousElementSibling).clone(), n(s).append(o), p = n(t).clone()), n(p).find(".util.accessible-text").addClass("print-hide"), 0 !== n(t + " :checkbox").length) {
                for (n(p[0]).find("tbody").remove(), f = 0; f < g.length; f++) r = n(g[f]).closest("tbody").clone(), a = n(r).find("tr").remove()[0], r.append(a), p.append(r);
                n(s).append(p)
            } else c = {}, l = n(e).closest("tbody"), u = n(l).closest("table"), i.targetClass ? (c = n(u).closest("." + i.targetClass).clone(), p = n(c).find("table")) : p = n(u).clone(), d = n(l).clone(), n(p[0]).find("tbody").remove(), m = n(d).find("tr").remove(), d.append(m[0]), p.append(d), i.targetClass ? n(s).append(c) : n(s).append(p);
            return !0
        }, u = !1;
        if (0 === i.targetIds.length) u = l(e); else for (a = 0; a < i.targetIds.length; a++) o = i.targetIds[a], document.getElementById(o) && (r = l("#" + o), u = u || r);
        u ? c(s) : t.info("Error: cannot find a transaction.")
    }

    function m(e, i, o) {
        var r = document.createElement("div");
        (function (e) {
            var t, a, c, l, u, d, m, f;
            return i && (f = n("." + i).clone()), a = n(s(e)).closest("tbody"), c = n(a).closest("table"), t = n(c).clone(), n(t).find(".util.accessible-text").addClass("print-hide"), l = n(a).clone(), u = n(s(e)).closest("tr"), d = n(u).prev().clone(), m = u.clone(), i && n(f[0]).find("table").remove(), n(t[0]).find("tbody").remove(), n(l[0]).find("tr").remove(), o && l.append(d), l.append(m), t.append(l), i ? (f.append(t), n(r).append(f)) : n(r).append(t), !0
        })(e) ? c(r) : t.info("Error: cannot find a transaction.")
    }

    function f(e, i) {
        var s, c, l, u = i.targetClass ? n("." + i.targetClass) : i.targetIds ? n("#" + i.targetIds[0]) : null;
        if (u || (u = n(e).closest(".print-util")), u.length > 10) window.print(); else {
            if (!(u.length <= 10)) return t.info("Error: give a unique selector to print."), !1;
            if (l = u.clone(), n(l[0]).find(r.toString(",")).hide(), n(l[0]).find(a.toString(",")).show(), s = document.createElement("br"), c = document.createElement("div"), !l[0]) return t.info("Error: invalid selector."), !1;
            c.appendChild(l[0] ? l[0] : ""), c.appendChild(s), o = c ? c.outerHTML : "", n(l[0]).find(r.toString(",")).show()
        }
    }

    function p(e) {
        if (!e.targetIds) return t.info("Error: give at least one valid selector to print."), !1;
        var i, s, c = e.printDivId ? e.printDivId : "printView", l = document.createElement("div"),
                u = document.createElement("table"), d = document.createElement("tbody");
        for (u.appendChild(d), l.appendChild(u), l.setAttribute("class", "multiple-target-selector-print-view"), l.setAttribute("id", c), i = 0; i < e.targetIds.length; i++) {
            if (!(s = n("#" + e.targetIds[i]).closest("tr").clone())[0]) return t.info("Error: invalid selector."), !1;
            n(s[0]).find(r.toString(",")).hide(), n(s[0]).find(a.toString(",")).show(), d.appendChild(s[0] ? s[0] : "")
        }
        o = l ? l.outerHTML : "", n(s[0]).find(r.toString(",")).show()
    }

    return {
        printPage: function (e, c, g) {
            return function (e, t) {
                return new Promise((function (n, i) {
                    var o = "#" + (e.domEvent ? e.domEvent.target.id : e.target.id);
                    t || i(new Error('Error: invalid element ("' + t + '") passed.')), "#" === o && (e.domEvent.srcElement ? (e.domEvent.srcElement.id = e.domEvent.srcElement.id ? e.domEvent.srcElement.id : "printUtil-tempId", o += e.domEvent.srcElement.id) : (e.domEvent.target.id = e.domEvent.target.id ? e.domEvent.target.id : "printUtil-tempId", o += e.domEvent.target.id)), "#" === o ? i(new Error("No element found")) : n(o)
                }))
            }(e, c).then((function (y) {
                var h;
                (h = n("head").clone()).find("script").remove(), i = h[0].innerHTML, g || (g = {
                    ignoreClasses: [],
                    targetClass: "",
                    targetIds: []
                }), g.targetClass || (g.targetClass = ""), g.ignoreClasses && g.ignoreClasses.length > 0 && (r = r.concat(g.ignoreClasses)), g.ignoreInputClasses && (r = []), g.addClasses && g.addClasses.length > 0 && (a = a.concat(g.addClasses)), c = c.toLowerCase();
                var v = {
                    dataset: l.bind(null, y, g.targetClass),
                    transaction: d.bind(this, y, g),
                    datasetwithtransaction: u.bind(null, y, g.targetClass),
                    createtransactionpreviewwithtr: m.bind(null, e, g.targetClass, g.dataSet),
                    selector: f.bind(null, y, g),
                    selectorwithmultipletargets: p.bind(null, g)
                };
                return v[c] ? (v[c] && v[c](), o.length > 0 ? new Promise((function (t) {
                    !function (e, t, r) {
                        var a, c, l, u;
                        (a = document.createElement("iframe")).name = "frame1", a.style.position = "absolute", a.style.top = "-1000000px", document.body.appendChild(a), (c = a.contentWindow ? a.contentWindow : a.contentDocument.document ? a.contentDocument.document : a.contentDocument).document.open(), c.document.write("<html>"), c.document.write(i), c.document.write("<body>"), (l = n("#header-outer-container").clone()).css("align:center"), l.addClass("util print-background-none segment-bpl"), l.find(".print-logo ").css("margin", "auto"), r.targetIds && r.targetIds.length && -1 !== r.targetIds.indexOf("collections-payment-table") ? l.find("#header-container").css("padding-left", "0") : l.find("#header-container").css("padding-left", n(a).width() - n(a).width() / 4), l.css("display", "block"), u = l[0] ? l[0].outerHTML : "", c.document.write(u), c.document.write(o + "</body></html>"), c.document.close(), -1 !== c.document.documentElement.innerHTML.search("</body>") && setTimeout((function () {
                            window.frames.frame1.focus(), window.frames.frame1.print(), document.body.contains(a) && (setTimeout((function () {
                                t && t.context && t.context.isSafari ? n(a).empty() : document.body.removeChild(a)
                            }), 1e3), setTimeout((function () {
                                r.focusBack ? n("#" + r.focusBack).focus() : n(s(t)).focus()
                            }), 200), e())
                        }), 500), i = "", o = ""
                    }(t, e, g)
                })) : void t.info("Error: empty HTML CONTENTS.")) : (t.info('Error: invalid element ("' + c + '") passed.'), !1)
            }))
        }
    }
})),define("common/utility/sharedData", [], (function () {
    "use strict";
    var e = {};
    return {
        set: function (t, n) {
            if (t && "string" == typeof t) return e[t] = n, n
        }, get: function (t) {
            if (t && "string" == typeof t) return e[t]
        }, clean: function () {
            e = {}
        }
    }
})),define("common/utility/siteAvailabilityHelper", ["require", "blue/log", "blue/declare"], (function (e) {
    "use strict";
    var t = e("blue/log")("[siteAvailability]");
    return e("blue/declare")({
        constructor: function (e) {
            this.siteAvailabilitySvc = e && e.siteAvailabilityService
        }, getInternationalizationSplash: function (e) {
            return new Promise(function (n) {
                var i = !1;
                (this.siteAvailabilitySvc || e.services.siteAvailabilityService).siteAvailability().then((function (e) {
                    e && e.siteFeatures && (i = !!e.siteFeatures.internationalizationSplash), t.debug("SiteAvailablityService internationalizationSplash: " + i), n(i)
                }))
            }.bind(this))
        }
    })
})),define("common/video/config", [], (function () {
    "use strict";
    return {
        modalVideoUrl: "/dashboard/modalVideo/index",
        transcriptUrl: "#/dashboard/transcript/index",
        apiUrl: "https://api.brightcove.com/services/library",
        token: "j4RbijdcdJ-JoWKOSkLT-JuLAcmcvbmBdyBSOBDpyl698IQTKGCYtg..",
        libBase: "https://players.brightcove.net/",
        libEnd: "_default/index.min",
        videos: [{
            videoPlacement: "modal_custom",
            videoContId: "vjs_video_default",
            videoClass: "video-js jpmc-video vjs-default-skin",
            style: "video-div-modal",
            inactivityTimeout: 4e4
        }]
    }
})),define("common/video/webspec/gallery", {
    name: "gallery",
    bindings: {},
    triggers: {complete: {action: "view.onComplete"}}
}),define("common/template/video/gallery", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "div",
            m: [{n: "id", f: [{t: 2, r: "id"}], t: 13}, {n: "class", f: ["jpui gallery ", {t: 2, r: "size"}], t: 13}],
            f: [{
                t: 7,
                e: "a",
                m: [{n: "id", f: ["anchor-", {t: 2, r: "id"}], t: 13}, {
                    n: "class",
                    f: "gallery-anchor",
                    t: 13
                }, {n: "href", f: [{t: 2, x: {r: ["href", "utils"], s: "_0||_1.noHref()"}}], t: 13}, {
                    n: "tap",
                    f: "rClick",
                    t: 70
                }],
                f: [{
                    t: 7,
                    e: "img",
                    m: [{n: "id", f: ["image-", {t: 2, r: "id"}], t: 13}, {
                        n: "src",
                        f: [{t: 2, r: "imageUrl"}],
                        t: 13
                    }, {n: "class", f: "thumbnail", t: 13}, {n: "alt", f: "", t: 13}]
                }, " ", {
                    t: 4,
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "overlay-container", t: 13}],
                        f: [{
                            t: 7,
                            e: "span",
                            m: [{
                                n: "class",
                                f: ["jpui-video-play-icon overlay ", {t: 2, r: "customizeIcon"}],
                                t: 13
                            }, {n: "aria-hidden", f: "true", t: 13}]
                        }]
                    }],
                    n: 50,
                    r: "overlay"
                }, " ", {
                    t: 4,
                    f: [{
                        t: 7,
                        e: "h3",
                        m: [{n: "id", f: ["header-", {t: 2, r: "id"}], t: 13}, {
                            n: "class",
                            f: "gallery-header",
                            t: 13
                        }],
                        f: [{t: 3, r: "header"}, {
                            t: 7,
                            e: "blueAccessible",
                            m: [{n: "id", f: ["accessible-", {t: 2, r: "id"}], t: 13}, {
                                n: "adatext",
                                f: [{t: 2, r: "adaText"}],
                                t: 13
                            }]
                        }, {
                            t: 7,
                            e: "blueIcon",
                            m: [{n: "type", f: "progressright", t: 13}, {n: "classes", f: "header-link-icon", t: 13}]
                        }]
                    }],
                    n: 50,
                    r: "header"
                }]
            }, " ", {
                t: 4,
                f: [{
                    t: 7,
                    e: "p",
                    m: [{n: "id", f: ["caption-", {t: 2, r: "id"}], t: 13}, {n: "class", f: "gallery-caption", t: 13}],
                    f: [{t: 3, r: "caption"}, " ", {
                        t: 4,
                        f: [{t: 7, e: "span", f: [{t: 2, r: "videoLength"}]}],
                        n: 50,
                        r: "videoLength"
                    }]
                }],
                n: 50,
                r: "caption"
            }]
        }], e: {}
    }
})),define("common/video/gallery", ["require", "blue-ui/utilities/instantiateView", "blue-ui/utilities/common", "common/video/webspec/gallery", "common/template/video/gallery", "blue-ui/view/elements/image", "blue-ui/view/elements/accessible", "blue-ui/view/elements/icon"], (function (e) {
    "use strict";
    var t = e("blue-ui/utilities/instantiateView"), n = e("blue-ui/utilities/common");
    return function () {
        this._hasRendered = !1, this._hasCompleted = !1, this.init = function () {
            this.bridge = e("common/video/webspec/gallery"), this.template = e("common/template/video/gallery"), this.views = {
                blueImage: e("blue-ui/view/elements/image"),
                blueAccessible: e("blue-ui/view/elements/accessible"),
                blueIcon: e("blue-ui/view/elements/icon")
            }, this.model.utils = n.DOMUtils, n.settings = n.settings || n.getSettings.call(this), n.settings.componentCheck && (this.isBlueUI = !0)
        }, this.onReady = t.setRender.bind(this, {rClick: ".gallery .gallery-anchor"}), this.onComplete = function () {
            this._hasCompleted || (n.addAttr(this.rtemplate), this._hasCompleted = !0)
        }
    }
})),define("common/video/media", ["require", "common/video/config", "appkit-utilities/language/helper", "blue/is", "blue/$"], (function (e) {
    "use strict";
    var t = e("common/video/config"), n = e("appkit-utilities/language/helper"), i = e("blue/is"), o = e("blue/$");
    return {
        updateBCRequire: function (e, n) {
            requirejs.undef("bc"), requirejs.config({paths: {bc: t.libBase.concat(e, "/", n, t.libEnd)}})
        }, mergeVideoObject: function (e, t, n) {
            var r;
            if (i.defined(e) && i.defined(t)) {
                var a = this.getVideoData(t);
                i.defined(a) && a.length > 0 && (r = o.extend(e, a[0], n))
            }
            return r
        }, getVideoData: function (e) {
            return t.videos.filter((function (t) {
                return t.videoPlacement === e
            }))
        }, disposePlayer: function (e) {
            var t = this.getVideoData(e);
            i.defined(t) && t.length > 0 && window.videojs.players[t[0].videoContId].dispose()
        }, createDivIcon: function (e) {
            var t = document.createElement("div");
            for (var n in e) e.hasOwnProperty(n) && t.setAttribute(n, e[n]);
            return t
        }, setLanguage: function (e, t) {
            if ("en-us" !== n.getLanguage()) {
                var o = n.getLanguage().split("-");
                this.addLanguage(o, t), o.length > 0 && i.defined(e.languages()[o[0]]) && e.language(o[0])
            }
        }, addLanguage: function (e, t) {
            i.defined(e) && e.length > 0 && window.videojs.addLanguage(e[0], t)
        }
    }
})),define("common/view/common/accountsPageTitle", ["require", "common/lib/pageTitle"], (function (e) {
    return function (t) {
        var n;
        n = {h1: {value: t}}, e("common/lib/pageTitle").setTitle(this, n)
    }
})),define("common/template/common/backNavHeader", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "div",
            m: [{
                n: "class",
                f: [{t: 4, f: [{t: 2, r: ".hideInProfileHybrid", s: !0}], n: 50, r: ".hideInProfileHybrid"}, {
                    t: 4,
                    n: 51,
                    f: [{t: 4, f: [{t: 2, r: ".classes", s: !0}], n: 50, r: ".classes"}],
                    l: 1
                }],
                t: 13
            }],
            f: [{
                t: 7, e: "div", m: [{n: "class", f: "backNavHeader", t: 13}], f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "id", f: "back-nav-hdr-container", t: 13}, {n: "class", f: "headerContainer", t: 13}],
                    f: [{
                        t: 4,
                        f: [{
                            t: 7,
                            e: "h1",
                            m: [{n: "class", f: "headerText headerText--withCloseIcon", t: 13}],
                            f: [{t: 3, x: {r: ["sanitizer", ".title"], s: "_0.sanitizeHTML(_1)"}}]
                        }, {
                            t: 7,
                            e: "a",
                            m: [{n: "href", f: "javascript:void(0);", t: 13}, {
                                n: "class",
                                f: "backIconLink util focus innerWhiteOutline",
                                t: 13
                            }, {
                                n: "click",
                                f: {
                                    n: [{t: 4, f: [{t: 2, r: "onClick", s: !0}], n: 50, r: ".onClick"}, {
                                        t: 4,
                                        n: 50,
                                        f: [{t: 2, r: ".clickHandler", s: !0}],
                                        r: ".clickHandler",
                                        l: 1
                                    }, {t: 4, n: 51, f: ["defaultAction"], l: 1}], d: []
                                },
                                t: 70
                            }, {n: "exit", f: "hubMenu", t: 13}],
                            f: [{
                                t: 4,
                                f: [{
                                    t: 7,
                                    e: "span",
                                    m: [{n: "class", f: "util accessible-text", t: 13}],
                                    f: [{t: 3, r: ".adatext"}]
                                }],
                                n: 50,
                                r: ".adatext"
                            }, {
                                t: 4,
                                n: 53,
                                f: [{t: 8, r: "blueIcon"}],
                                x: {
                                    r: ["touch"],
                                    s: '{type:"close",classes:"backIcon"+(_0?" touch":""),id:"blueBackIcon"}'
                                }
                            }]
                        }],
                        n: 50,
                        r: ".hybrid"
                    }, {
                        t: 4,
                        n: 51,
                        f: [{
                            t: 7,
                            e: "a",
                            m: [{n: "href", f: "javascript:void(0);", t: 13}, {
                                n: "class",
                                f: "backIconLink util focus innerWhiteOutline",
                                t: 13
                            }, {
                                n: "click",
                                f: {
                                    n: [{t: 4, f: [{t: 2, r: "onClick", s: !0}], n: 50, r: ".onClick"}, {
                                        t: 4,
                                        n: 50,
                                        f: [{t: 2, r: ".clickHandler", s: !0}],
                                        r: ".clickHandler",
                                        l: 1
                                    }, {t: 4, n: 51, f: ["defaultAction"], l: 1}], d: []
                                },
                                t: 70
                            }, {n: "exit", f: "hubMenu", t: 13}],
                            f: [{
                                t: 4,
                                f: [{
                                    t: 7,
                                    e: "span",
                                    m: [{n: "class", f: "util accessible-text", t: 13}, {
                                        n: "exit",
                                        f: "hubMenu",
                                        t: 13
                                    }],
                                    f: [{t: 3, r: ".adatext"}]
                                }],
                                n: 50,
                                r: ".adatext"
                            }, {
                                t: 4,
                                n: 53,
                                f: [{t: 8, r: "blueIcon"}],
                                x: {
                                    r: ["touch"],
                                    s: '{type:"progressleft",classes:"backIcon"+(_0?" touch":""),id:"blueBackIcon"}'
                                }
                            }]
                        }, {
                            t: 7,
                            e: "h1",
                            m: [{n: "tabindex", f: "-1", t: 13}, {
                                n: "id",
                                f: "back-nav-header-title",
                                t: 13
                            }, {n: "aria-live", f: "assertive", t: 13}, {n: "class", f: "headerText", t: 13}],
                            f: [{t: 3, x: {r: ["sanitizer", ".title"], s: "_0.sanitizeHTML(_1)"}}]
                        }],
                        l: 1
                    }]
                }]
            }, " ", {t: 7, e: "div", m: [{n: "class", f: "bodyOffset", t: 13}]}]
        }], e: {}
    }
})),define("common/view/webspec/common/backNavHeader", {
    name: "BACK_NAV_HEADER",
    bindings: {},
    triggers: {defaultAction: {action: "view.defaultAction", preventDefault: !0}, teardown: {action: "view.onTeardown"}}
}),define("common/view/common/backNavHeader", ["require", "blue/root", "blue-ui/utilities/isTouch", "common/lib/utility/hybridMixin", "common/template/common/backNavHeader", "common/view/webspec/common/backNavHeader", "blue-ui/template/elements/icon"], (function (e) {
    "use strict";
    var t = e("blue/root"), n = e("blue-ui/utilities/isTouch"), i = e("common/lib/utility/hybridMixin");
    return function () {
        this.template = e("common/template/common/backNavHeader"), this.init = function () {
            i.call(this), this.bridge = e("common/view/webspec/common/backNavHeader"), this.partials = {blueIcon: e("blue-ui/template/elements/icon")}
        }, this.defaultAction = function () {
            this.bridge.output.emit("trigger", {value: "handleFormDirty"})
        }, this.onReady = function () {
            this.model.hybrid = t.hybrid, this.rtemplate.set("hybrid", t.hybrid), this.model.touch = n(), this.rtemplate.set("hideInProfileHybrid", t.hybrid ? "hideInProfileHybrid" : ""), this.dispatchHybridEvent("changeScreenTitle", this.context, this.rtemplate.get("title")), t.window.document.getElementById("back-nav-hdr-container").getElementsByClassName("backIcon")[0].setAttribute("exit", "hubnav")
        }
    }
})),define("common/template/common/blueAlertWrap", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", m: [{n: "class", f: "blueAlertWrap", t: 13}], f: [{
                t: 7,
                e: "blueAlert",
                m: [{n: "id", f: [{t: 2, r: "id"}], t: 13}, {n: "type", f: [{t: 2, r: "type"}], t: 13}, {
                    n: "message",
                    f: [{t: 2, r: "message"}],
                    t: 13
                }, {n: "classes", f: [{t: 2, r: "classes"}], t: 13}, {
                    n: "primary",
                    f: [{t: 2, r: "primary"}],
                    t: 13
                }, {n: "role", f: [{t: 2, r: "role"}], t: 13}, {n: "title", f: [{t: 2, r: "title"}], t: 13}, {
                    n: "icon",
                    f: [{t: 2, r: "icon"}],
                    t: 13
                }, {n: "plainTextHeader", f: [{t: 2, r: "plainTextHeader"}], t: 13}, {
                    n: "accessibleTextIcon",
                    f: [{t: 2, r: "accessibleTextIcon"}],
                    t: 13
                }, {n: "closeText", f: [{t: 2, r: "closeText"}], t: 13}, {
                    n: "content",
                    f: [{t: 2, r: "content"}],
                    t: 13
                }, {n: "focusOnRender", f: "false", t: 13}, {
                    n: "openText",
                    f: [{t: 2, r: "openText"}],
                    t: 13
                }, {n: "closedText", f: [{t: 2, r: "closedText"}], t: 13}, {
                    n: "suppressMessageToggle",
                    f: [{t: 2, r: "suppressMessageToggle"}],
                    t: 13
                }, {n: "rTitleClick", f: [{t: 2, r: "rTitleClick"}], t: 13}, {
                    n: "rIconClick",
                    f: [{t: 2, r: "rIconClick"}],
                    t: 13
                }, {n: "rLinkClick", f: [{t: 2, r: "rLinkClick"}], t: 13}, {
                    n: "rMessageClick",
                    f: [{t: 2, r: "rMessageClick"}],
                    t: 13
                }, {n: "rCloseClick", f: [{t: 2, r: "rCloseClick"}], t: 13}, {
                    n: "doNotPreventDefaultEvent",
                    f: [{t: 2, r: "preventDefaultEvent"}],
                    t: 13
                }]
            }, " "]
        }]
    }
})),define("common/view/webspec/common/blueAlertWrap", {
    name: "BLUEALERTWRAP",
    bindings: {value: {direction: "BOTH"}},
    triggers: {complete: {action: "view.onComplete"}}
}),define("common/view/common/blueAlertWrap", ["require", "common/utility/adaUtility", "blue/$", "common/template/common/blueAlertWrap", "common/view/webspec/common/blueAlertWrap", "blue-ui/view/elements/alert"], (function (e) {
    "use strict";
    var t = e("common/utility/adaUtility"), n = e("blue/$");
    return function () {
        this.viewId = "BlueAlertWrap", this.init = function () {
            this.viewName = "BlueAlertWrap", this.template = e("common/template/common/blueAlertWrap"), this.bridge = e("common/view/webspec/common/blueAlertWrap"), this.partials = {}, this.views = {blueAlert: e("blue-ui/view/elements/alert")}
        };
        var i = function () {
            var e = n("#inner-" + this.rtemplate.get("id"));
            e.attr("tabIndex", -1), e.focus();
            var i = this.rtemplate.get("noScrollForFocusOnRender"), o = this.rtemplate.get("scrollTopLevel");
            if (i || t.scrollTop.call(this, e, o), !this.rtemplate.get("doNotPreventDefaultEvent")) try {
                event && event.preventDefault()
            } catch (e) {
            }
        };
        this.onComplete = function () {
            i.call(this)
        }
    }
})),define("common/view/webspec/common/buttonFooterCollection", {
    name: "BUTTONFOOTERCOLLECTION",
    bindings: {isXS: {direction: "BOTH"}},
    triggers: {complete: {action: "view.onComplete"}, teardown: {action: "view.teardown"}}
}),define("common/template/common/buttonFooterCollection", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "div",
            f: [" ", {
                t: 4,
                f: [{
                    t: 7,
                    e: "blueStickyFooter",
                    m: [{n: "parentId", f: [{t: 2, r: ".parentId"}], t: 13}, {
                        n: "id",
                        f: ["sticky-", {t: 2, r: ".parentId"}],
                        t: 13
                    }],
                    f: [{t: 7, e: "div", m: [{n: "class", f: "row", t: 13}], f: [{t: 8, r: "buttonCollection"}]}]
                }],
                n: 50,
                r: ".stickyFooter"
            }, {t: 4, n: 51, f: [{t: 8, r: "buttonCollection"}], l: 1}],
            p: {
                buttonCollection: [{
                    t: 4, f: [{
                        t: 4, f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: [{t: 2, r: ".containerColumnClasses"}], t: 13}],
                            f: [{
                                t: 4,
                                f: [{
                                    t: 4,
                                    f: [{
                                        t: 7,
                                        e: "div",
                                        m: [{n: "id", f: [{t: 2, r: ".skipContainerID"}], t: 13}],
                                        f: [{
                                            t: 7,
                                            e: "blueSkipLink",
                                            m: [{n: "id", f: [{t: 2, r: ".skipID"}], t: 13}, {
                                                n: "skipSelector",
                                                f: [{t: 2, r: ".skipSelector"}],
                                                t: 13
                                            }, {n: "label", f: [{t: 2, r: ".skipLabel"}], t: 13}, {
                                                n: "rKeydown",
                                                f: [{t: 2, r: ".skiprKeydown"}],
                                                t: 13
                                            }, {n: "rClick", f: [{t: 2, r: ".skiprClick"}], t: 13}, {
                                                n: "classes",
                                                f: [{t: 2, r: ".skipClasses"}],
                                                t: 13
                                            }, {n: "adatext", f: [{t: 2, r: ".skipAdatext"}], t: 13}, {
                                                n: "rFocus",
                                                f: [{t: 2, r: ".skiprFocus"}],
                                                t: 13
                                            }]
                                        }]
                                    }],
                                    n: 50,
                                    r: "~/showSkipLink"
                                }],
                                r: "~/skipLink"
                            }, " ", {
                                t: 7,
                                e: "blueButton",
                                m: [{n: "id", f: [{t: 2, r: ".id"}], t: 13}, {
                                    n: "classes",
                                    f: [{t: 2, r: ".classes"}],
                                    t: 13
                                }, {n: "type", f: [{t: 2, r: ".type"}], t: 13}, {
                                    n: "label",
                                    f: [{t: 2, r: ".label"}],
                                    t: 13
                                }, {n: "disabled", f: [{t: 2, r: ".disabled"}], t: 13}, {
                                    n: "adatext",
                                    f: [{t: 2, r: ".adatext"}],
                                    t: 13
                                }, {n: "role", f: [{t: 2, r: ".role"}], t: 13}, {
                                    n: "rClick",
                                    f: [{t: 2, r: ".rClick"}],
                                    t: 13
                                }, {n: "rChange", f: [{t: 2, r: ".rChange"}], t: 13}, {
                                    n: "rBlur",
                                    f: [{t: 2, r: ".rBlur"}],
                                    t: 13
                                }, {n: "rFocus", f: [{t: 2, r: ".rFocus"}], t: 13}, {
                                    n: "rMouseover",
                                    f: [{t: 2, r: ".rMouseover"}],
                                    t: 13
                                }, {n: "rMouseleave", f: [{t: 2, r: ".rMouseleave"}], t: 13}, {
                                    n: "rKeydown",
                                    f: [{t: 2, r: ".rKeydown"}],
                                    t: 13
                                }, {n: "rKeyup", f: [{t: 2, r: ".rKeyup"}], t: 13}, {
                                    n: "value",
                                    f: [{t: 2, r: ".value"}],
                                    t: 13
                                }]
                            }]
                        }], r: ".primaryButton"
                    }, " ", {
                        t: 4,
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: [{t: 2, r: ".containerColumnClasses"}], t: 13}],
                            f: [{
                                t: 7,
                                e: "blueButton",
                                m: [{n: "id", f: [{t: 2, r: ".id"}], t: 13}, {
                                    n: "classes",
                                    f: [{t: 2, r: ".classes"}],
                                    t: 13
                                }, {n: "type", f: [{t: 2, r: ".type"}], t: 13}, {
                                    n: "label",
                                    f: [{t: 2, r: ".label"}],
                                    t: 13
                                }, {n: "disabled", f: [{t: 2, r: ".disabled"}], t: 13}, {
                                    n: "adatext",
                                    f: [{t: 2, r: ".adatext"}],
                                    t: 13
                                }, {n: "role", f: [{t: 2, r: ".role"}], t: 13}, {
                                    n: "rClick",
                                    f: [{t: 2, r: ".rClick"}],
                                    t: 13
                                }, {n: "rChange", f: [{t: 2, r: ".rChange"}], t: 13}, {
                                    n: "rBlur",
                                    f: [{t: 2, r: ".rBlur"}],
                                    t: 13
                                }, {n: "rFocus", f: [{t: 2, r: ".rFocus"}], t: 13}, {
                                    n: "rMouseover",
                                    f: [{t: 2, r: ".rMouseover"}],
                                    t: 13
                                }, {n: "rMouseleave", f: [{t: 2, r: ".rMouseleave"}], t: 13}, {
                                    n: "rKeydown",
                                    f: [{t: 2, r: ".rKeydown"}],
                                    t: 13
                                }, {n: "rKeyup", f: [{t: 2, r: ".rKeyup"}], t: 13}, {
                                    n: "value",
                                    f: [{t: 2, r: ".value"}],
                                    t: 13
                                }]
                            }]
                        }],
                        i: "i",
                        x: {r: ["secondaryButtons"], s: "(_0||[]).concat().reverse()"}
                    }], n: 50, r: "isXS"
                }, " ", {
                    t: 4,
                    f: [{
                        t: 4,
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: [{t: 2, r: ".containerColumnClasses"}], t: 13}],
                            f: [{
                                t: 7,
                                e: "blueButton",
                                m: [{n: "id", f: [{t: 2, r: ".id"}], t: 13}, {
                                    n: "classes",
                                    f: [{t: 2, r: ".classes"}],
                                    t: 13
                                }, {n: "type", f: [{t: 2, r: ".type"}], t: 13}, {
                                    n: "label",
                                    f: [{t: 2, r: ".label"}],
                                    t: 13
                                }, {n: "disabled", f: [{t: 2, r: ".disabled"}], t: 13}, {
                                    n: "adatext",
                                    f: [{t: 2, r: ".adatext"}],
                                    t: 13
                                }, {n: "role", f: [{t: 2, r: ".role"}], t: 13}, {
                                    n: "rClick",
                                    f: [{t: 2, r: ".rClick"}],
                                    t: 13
                                }, {n: "rChange", f: [{t: 2, r: ".rChange"}], t: 13}, {
                                    n: "rBlur",
                                    f: [{t: 2, r: ".rBlur"}],
                                    t: 13
                                }, {n: "rFocus", f: [{t: 2, r: ".rFocus"}], t: 13}, {
                                    n: "rMouseover",
                                    f: [{t: 2, r: ".rMouseover"}],
                                    t: 13
                                }, {n: "rMouseleave", f: [{t: 2, r: ".rMouseleave"}], t: 13}, {
                                    n: "rKeydown",
                                    f: [{t: 2, r: ".rKeydown"}],
                                    t: 13
                                }, {n: "rKeyup", f: [{t: 2, r: ".rKeyup"}], t: 13}, {
                                    n: "value",
                                    f: [{t: 2, r: ".value"}],
                                    t: 13
                                }]
                            }]
                        }],
                        i: "i",
                        r: "secondaryButtons"
                    }, " ", {
                        t: 4, f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: [{t: 2, r: ".containerColumnClasses"}], t: 13}],
                            f: [{
                                t: 4,
                                f: [{
                                    t: 4,
                                    f: [{
                                        t: 7,
                                        e: "div",
                                        m: [{n: "id", f: [{t: 2, r: ".skipContainerID"}], t: 13}],
                                        f: [{
                                            t: 7,
                                            e: "blueSkipLink",
                                            m: [{n: "id", f: [{t: 2, r: ".skipID"}], t: 13}, {
                                                n: "skipSelector",
                                                f: [{t: 2, r: ".skipSelector"}],
                                                t: 13
                                            }, {n: "label", f: [{t: 2, r: ".skipLabel"}], t: 13}, {
                                                n: "rKeydown",
                                                f: [{t: 2, r: ".skiprKeydown"}],
                                                t: 13
                                            }, {n: "rClick", f: [{t: 2, r: ".skiprClick"}], t: 13}, {
                                                n: "classes",
                                                f: [{t: 2, r: ".skipClasses"}],
                                                t: 13
                                            }, {n: "adatext", f: [{t: 2, r: ".skipAdatext"}], t: 13}, {
                                                n: "rFocus",
                                                f: [{t: 2, r: ".skiprFocus"}],
                                                t: 13
                                            }]
                                        }]
                                    }],
                                    n: 50,
                                    r: "~/showSkipLink"
                                }],
                                r: "~/skipLink"
                            }, " ", {
                                t: 7,
                                e: "blueButton",
                                m: [{n: "id", f: [{t: 2, r: ".id"}], t: 13}, {
                                    n: "classes",
                                    f: [{t: 2, r: ".classes"}],
                                    t: 13
                                }, {n: "type", f: [{t: 2, r: ".type"}], t: 13}, {
                                    n: "label",
                                    f: [{t: 2, r: ".label"}],
                                    t: 13
                                }, {n: "disabled", f: [{t: 2, r: ".disabled"}], t: 13}, {
                                    n: "adatext",
                                    f: [{t: 2, r: ".adatext"}],
                                    t: 13
                                }, {n: "role", f: [{t: 2, r: ".role"}], t: 13}, {
                                    n: "rClick",
                                    f: [{t: 2, r: ".rClick"}],
                                    t: 13
                                }, {n: "rChange", f: [{t: 2, r: ".rChange"}], t: 13}, {
                                    n: "rBlur",
                                    f: [{t: 2, r: ".rBlur"}],
                                    t: 13
                                }, {n: "rFocus", f: [{t: 2, r: ".rFocus"}], t: 13}, {
                                    n: "rMouseover",
                                    f: [{t: 2, r: ".rMouseover"}],
                                    t: 13
                                }, {n: "rMouseleave", f: [{t: 2, r: ".rMouseleave"}], t: 13}, {
                                    n: "rKeydown",
                                    f: [{t: 2, r: ".rKeydown"}],
                                    t: 13
                                }, {n: "rKeyup", f: [{t: 2, r: ".rKeyup"}], t: 13}, {
                                    n: "value",
                                    f: [{t: 2, r: ".value"}],
                                    t: 13
                                }]
                            }]
                        }], r: ".primaryButton"
                    }],
                    n: 50,
                    x: {r: ["isXS"], s: "!_0"}
                }]
            }
        }], e: {}
    }
})),define("common/view/common/buttonFooterCollection", ["require", "appkit-utilities/common/mediaQueryListener", "blue/$", "blue/root", "common/view/webspec/common/buttonFooterCollection", "common/template/common/buttonFooterCollection", "blue-ui/view/elements/button", "blue-ui/view/elements/stickyfooter", "blue-ui/view/elements/skiplink"], (function (e) {
    "use strict";
    return function () {
        var t = this, n = e("appkit-utilities/common/mediaQueryListener"), i = e("blue/$"), o = e("blue/root");
        this.viewId = "ButtonFooterCollection", this.bridge = e("common/view/webspec/common/buttonFooterCollection"), this.template = e("common/template/common/buttonFooterCollection"), this.init = function () {
            this.viewName = "ButtonFooterCollection", this.model = {isXS: n.currentBreakpoint === n.BREAKPOINT.XS}, this.views = {
                blueButton: e("blue-ui/view/elements/button"),
                blueStickyFooter: e("blue-ui/view/elements/stickyfooter"),
                blueSkipLink: e("blue-ui/view/elements/skiplink")
            }
        }, this.onReady = function () {
            t.model.isXS = n.currentBreakpoint === n.BREAKPOINT.XS, i(o).on("breakpoint-change", (function () {
                t.model.isXS = n.currentBreakpoint === n.BREAKPOINT.XS
            }))
        }, this.teardown = function () {
            i(o).off("breakpoint-change")
        }
    }
})),define("common/template/common/dataSet", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "dl",
            m: [{n: "class", f: ["dataset row ", {t: 2, r: ".type"}, " ", {t: 2, r: ".classes"}], t: 13}, {
                t: 4,
                f: [{n: "tabindex", f: "0", t: 13}],
                n: 50,
                r: ".isTabbable"
            }],
            f: [{
                t: 4, f: [{
                    t: 4, f: [{
                        t: 7,
                        e: "dt",
                        m: [{
                            n: "class",
                            f: ["dataLabel", {
                                t: 4,
                                f: [" col-", {t: 2, r: "key"}, "-", {t: 2, rx: {r: "grid", m: [{t: 30, n: "key"}]}}],
                                i: "key",
                                r: ".grid"
                            }, {
                                t: 4,
                                f: [" col-", {t: 2, r: "key"}, "-right-", {
                                    t: 2,
                                    rx: {r: "offset", m: [{t: 30, n: "key"}]}
                                }],
                                i: "key",
                                r: ".offset"
                            }, {
                                t: 4,
                                f: [" clear-left-lg clear-left-md clear-left-sm clear-left-xs"],
                                n: 50,
                                x: {r: ["type"], s: '_0=="stack"'}
                            }, {
                                t: 4,
                                n: 51,
                                f: [{t: 4, f: ["clear-left-", {t: 2, x: {r: ["."], s: '_0+" "'}}], r: ".clearleft"}],
                                l: 1
                            }, " ", {t: 2, r: ".labelClasses"}],
                            t: 13
                        }],
                        f: [{
                            t: 7,
                            e: "span",
                            m: [{n: "class", f: "wrap", t: 13}],
                            f: [{
                                t: 4,
                                f: [{t: 7, e: "span", f: [{t: 2, r: ".label"}]}],
                                n: 50,
                                r: ".label"
                            }, " ", {
                                t: 4,
                                f: [{
                                    t: 7,
                                    e: "span",
                                    m: [{n: "class", f: "optional", t: 13}],
                                    f: [{t: 2, r: ".optionalText"}]
                                }],
                                n: 50,
                                r: ".isOptional"
                            }, " ", {
                                t: 4,
                                f: [{
                                    t: 7,
                                    e: "blueTooltip",
                                    m: [{
                                        n: "id",
                                        f: [{
                                            t: 4,
                                            f: [{t: 2, x: {r: [".id"], s: '_0+"_tooltip"'}}],
                                            n: 50,
                                            r: ".id"
                                        }, {
                                            t: 4,
                                            n: 50,
                                            f: [{t: 2, x: {r: ["id", "datasetIndex"], s: '_0+_1+"_tooltip"'}}],
                                            r: "id",
                                            l: 1
                                        }, {
                                            t: 4,
                                            n: 51,
                                            f: [{t: 2, x: {r: ["datasetIndex"], s: '_0+"_tooltip"'}}],
                                            l: 1
                                        }],
                                        t: 13
                                    }, {
                                        n: "triggerContent",
                                        f: [{t: 2, r: ".triggerContent"}],
                                        t: 13
                                    }, {
                                        n: "positionTo",
                                        f: [{t: 2, x: {r: [".positionTooltip"], s: '_0||"top"'}}],
                                        t: 13
                                    }, {n: "adaOpenText", f: [{t: 2, r: ".openAdatext"}], t: 13}, {
                                        n: "adaBeginText",
                                        f: [{t: 2, r: ".beginAdatext"}],
                                        t: 13
                                    }, {n: "adaCloseText", f: [{t: 2, r: ".closeAdatext"}], t: 13}, {
                                        n: "content",
                                        f: [{t: 3, x: {r: ["sanitizer", ".toolTipContent"], s: "_0.sanitizeHTML(_1)"}}],
                                        t: 13
                                    }, {n: "adaEndText", f: [{t: 2, r: ".endAdatext"}], t: 13}, {
                                        n: "rClick",
                                        f: [{t: 4, f: [{t: 2, r: ".rClick"}], n: 50, r: ".rClick"}],
                                        t: 13
                                    }, {
                                        n: "rCloseIconClick",
                                        f: [{t: 4, f: [{t: 2, r: ".rCloseIconClick"}], n: 50, r: ".rCloseIconClick"}],
                                        t: 13
                                    }, {
                                        n: "rMouseover",
                                        f: [{t: 4, f: [{t: 2, r: ".rMouseover"}], n: 50, r: ".rMouseover"}],
                                        t: 13
                                    }]
                                }],
                                n: 50,
                                r: ".toolTipContent"
                            }]
                        }]
                    }, " ", {
                        t: 7,
                        e: "dd",
                        m: [{
                            n: "class",
                            f: ["dataValue", {
                                t: 4,
                                f: [" col-", {t: 2, r: "key"}, "-", {
                                    t: 2,
                                    rx: {r: "grid", m: [{t: 30, n: "key"}]}
                                }, " ", {
                                    t: 4,
                                    f: ["col-", {t: 2, r: "key"}, "-left-", {
                                        t: 2,
                                        rx: {r: "grid", m: [{t: 30, n: "key"}]}
                                    }],
                                    n: 50,
                                    x: {r: ["type"], s: '_0!="stack"'}
                                }],
                                i: "key",
                                r: ".grid"
                            }, " ", {t: 2, r: ".valueClasses"}],
                            t: 13
                        }, {
                            n: "id",
                            f: [{t: 4, f: [{t: 2, r: ".id"}], n: 50, r: ".id"}, {
                                t: 4,
                                n: 50,
                                f: [{t: 2, x: {r: ["id", "datasetIndex"], s: "_0+_1"}}],
                                r: "id",
                                l: 1
                            }],
                            t: 13
                        }],
                        f: [{
                            t: 4, f: [{
                                t: 4, f: [{
                                    t: 7,
                                    e: "span",
                                    m: [{
                                        t: 4,
                                        f: [{
                                            n: "class",
                                            f: ["currency ", {t: 2, r: ".symbolPosition"}, {t: 2, r: ".sign"}],
                                            t: 13
                                        }],
                                        n: 50,
                                        r: ".symbolPosition"
                                    }],
                                    f: [{
                                        t: 4,
                                        f: [{t: 7, e: "span", f: [{t: 2, r: ".currency"}]}],
                                        n: 50,
                                        r: ".currency"
                                    }, {
                                        t: 7,
                                        e: "span",
                                        m: [{n: "class", f: "DATA", t: 13}, {
                                            t: 4,
                                            f: [{n: "tabindex", f: "0", t: 13}],
                                            n: 50,
                                            r: ".isTabbable"
                                        }],
                                        f: [{
                                            t: 3,
                                            x: {r: ["sanitizer", ".value"], s: "_0.sanitizeHTML(_1.toString())"}
                                        }]
                                    }, " ", {
                                        t: 4,
                                        f: [{t: 7, e: "br"}, {
                                            t: 7,
                                            e: "span",
                                            m: [{n: "class", f: "NOTE", t: 13}],
                                            f: [{t: 2, r: ".additionalText"}]
                                        }],
                                        n: 50,
                                        r: ".additionalText"
                                    }, " ", {
                                        t: 4,
                                        f: [{
                                            t: 7,
                                            e: "blueTooltip",
                                            m: [{
                                                n: "id",
                                                f: [{
                                                    t: 4,
                                                    f: [{t: 2, x: {r: [".id"], s: '_0+"_tooltip"'}}],
                                                    n: 50,
                                                    r: ".id"
                                                }, {
                                                    t: 4,
                                                    n: 50,
                                                    f: [{t: 2, x: {r: ["id", "datasetIndex"], s: '_0+_1+"_tooltip"'}}],
                                                    r: "id",
                                                    l: 1
                                                }, {
                                                    t: 4,
                                                    n: 51,
                                                    f: [{t: 2, x: {r: ["datasetIndex"], s: '_0+"_tooltip"'}}],
                                                    l: 1
                                                }],
                                                t: 13
                                            }, {
                                                n: "triggerContent",
                                                f: [{t: 2, r: ".triggerContent"}],
                                                t: 13
                                            }, {
                                                n: "positionTo",
                                                f: [{t: 2, x: {r: [".positionTooltip"], s: '_0||"top"'}}],
                                                t: 13
                                            }, {
                                                n: "adaOpenText",
                                                f: [{t: 2, r: ".openAdatext"}],
                                                t: 13
                                            }, {
                                                n: "adaBeginText",
                                                f: [{t: 2, r: ".beginAdatext"}],
                                                t: 13
                                            }, {
                                                n: "adaCloseText",
                                                f: [{t: 2, r: ".closeAdatext"}],
                                                t: 13
                                            }, {
                                                n: "content",
                                                f: [{
                                                    t: 3,
                                                    x: {
                                                        r: ["sanitizer", ".toolTipValueContent"],
                                                        s: "_0.sanitizeHTML(_1)"
                                                    }
                                                }],
                                                t: 13
                                            }, {n: "adaEndText", f: [{t: 2, r: ".endAdatext"}], t: 13}, {
                                                n: "rClick",
                                                f: [{t: 4, f: [{t: 2, r: ".rClick"}], n: 50, r: ".rClick"}],
                                                t: 13
                                            }, {
                                                n: "rCloseIconClick",
                                                f: [{
                                                    t: 4,
                                                    f: [{t: 2, r: ".rCloseIconClick"}],
                                                    n: 50,
                                                    r: ".rCloseIconClick"
                                                }],
                                                t: 13
                                            }, {
                                                n: "rMouseover",
                                                f: [{t: 4, f: [{t: 2, r: ".rMouseover"}], n: 50, r: ".rMouseover"}],
                                                t: 13
                                            }]
                                        }],
                                        n: 50,
                                        r: ".toolTipValueContent"
                                    }]
                                }], r: ".value"
                            }], n: 50, x: {r: [".value"], s: 'typeof _0!=="undefined"'}
                        }, {
                            t: 4,
                            n: 50,
                            f: [{
                                t: 4,
                                f: [" ", {
                                    t: 7,
                                    e: "mds-definition-link",
                                    m: [{
                                        n: "id",
                                        f: ["definition-link-", {t: 2, r: "id"}, "-", {t: 2, r: "datasetIndex"}],
                                        t: 13
                                    }, {
                                        n: "definition-text",
                                        f: [{t: 2, r: "definitionText"}],
                                        t: 13
                                    }, {
                                        n: "tooltip-message",
                                        f: [{t: 2, r: "tooltipMessage"}],
                                        t: 13
                                    }, {n: "tooltip-placement", f: "below", t: 13}, {
                                        n: "click",
                                        f: {n: [{t: 2, r: "onClick"}], d: []},
                                        t: 70
                                    }],
                                    f: []
                                }, " ", {
                                    t: 7,
                                    e: "span",
                                    f: [{
                                        t: 7,
                                        e: "span",
                                        m: [{
                                            n: "id",
                                            f: ["#definition-link-", {t: 2, r: "id"}, "-", {
                                                t: 2,
                                                r: "datasetIndex"
                                            }, "-printText"],
                                            t: 13
                                        }, {n: "class", f: "DATA hide-xs util print-show-block", t: 13}],
                                        f: [{t: 2, r: ".definitionText"}]
                                    }]
                                }],
                                n: 54,
                                r: ".definitionLink"
                            }],
                            r: ".definitionLink",
                            l: 1
                        }, " ", {t: 8, r: "prompt"}]
                    }], n: 51, r: ".skip"
                }], i: "datasetIndex", r: "dataset"
            }, " "]
        }], e: {}
    }
})),define("common/view/webspec/common/dataSet", {
    name: "DATASET",
    bindings: {},
    triggers: {complete: {action: "view.onComplete"}}
}),define("common/template/common/_prompt", [], (function () {
    return {
        v: 4, t: [{
            t: 4, f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: "prompt NOTE", t: 13}],
                f: [{
                    t: 7,
                    e: "span",
                    m: [{
                        n: "id",
                        f: [{t: 4, f: [{t: 2, r: ".promptId"}], n: 50, r: ".promptId"}, {
                            t: 4,
                            n: 51,
                            f: [{t: 2, x: {r: ["id"], s: '_0+"_prompt"'}}],
                            l: 1
                        }],
                        t: 13
                    }],
                    f: [{t: 4, f: [{t: 16}], n: 50, r: ".yieldPrompt"}, {
                        t: 4,
                        n: 51,
                        f: [{
                            t: 7,
                            e: "blueFieldhelpertext",
                            m: [{n: "id", f: [{t: 2, r: ".id"}, "-helpertext"], t: 13}, {
                                n: "text",
                                f: [{t: 3, x: {r: ["sanitizer", ".prompt"], s: "_0.sanitizeHTML(_1)"}}],
                                t: 13
                            }, {n: "additionalText", f: [{t: 2, r: ".promptOptional"}], t: 13}, {
                                n: "content",
                                f: [{t: 4, f: ["true"], n: 50, r: ".promptTooltipContent"}],
                                t: 13
                            }]
                        }],
                        l: 1
                    }]
                }, " ", {
                    t: 4, f: [{
                        t: 7,
                        e: "blueTooltip",
                        m: [{
                            n: "id",
                            f: [{t: 4, f: [{t: 2, x: {r: ["id"], s: '_0+"_promptTooltip"'}}], n: 50, r: "id"}],
                            t: 13
                        }, {
                            n: "content",
                            f: [{t: 3, x: {r: ["sanitizer", ".promptTooltipContent"], s: "_0.sanitizeHTML(_1)"}}],
                            t: 13
                        }, {
                            n: "classes",
                            f: [{t: 4, f: [{t: 2, r: ".promptTooltipClasses"}], n: 50, r: ".promptTooltipclasses"}],
                            t: 13
                        }, {
                            n: "edgeDetection",
                            f: [{t: 2, r: ".promptTooltipEdgeDetection"}],
                            t: 13
                        }, {n: "adaOpenText", f: [{t: 2, r: ".promptTooltipAdaOpenText"}], t: 13}, {
                            n: "adaBeginText",
                            f: [{t: 2, r: ".promptTooltipAdaBeginText"}],
                            t: 13
                        }, {n: "adaCloseText", f: [{t: 2, r: ".promptTooltipAdaCloseText"}], t: 13}, {
                            n: "adaEndText",
                            f: [{t: 2, r: ".promptTooltipAdaEndText"}],
                            t: 13
                        }, {n: "rClick", f: [{t: 2, r: ".promptTooltipRClick"}], t: 13}, {
                            n: "rChange",
                            f: [{t: 2, r: ".promptTooltipRChange"}],
                            t: 13
                        }, {n: "rBlur", f: [{t: 2, r: ".promptTooltipRBlur"}], t: 13}, {
                            n: "rFocus",
                            f: [{t: 2, r: ".promptTooltipRFocus"}],
                            t: 13
                        }, {n: "rMouseover", f: [{t: 2, r: ".promptTooltipRMouseover"}], t: 13}, {
                            n: "rMouseleave",
                            f: [{t: 2, r: ".promptTooltipRMouseleave"}],
                            t: 13
                        }, {n: "rKeydown", f: [{t: 2, r: ".promptTooltipRKeydown"}], t: 13}, {
                            n: "rKeyup",
                            f: [{t: 2, r: ".promptTooltipRKeyup"}],
                            t: 13
                        }, {n: "additionalContent", f: [{t: 2, r: ".promptTooltipAdditionalContent"}], t: 13}]
                    }], n: 50, r: ".promptTooltipContent"
                }]
            }], n: 50, x: {r: [".prompt", ".yieldPrompt"], s: "_0||_1"}
        }], e: {}
    }
})),define("common/view/common/dataSet", ["require", "common/template/common/dataSet", "common/view/webspec/common/dataSet", "common/template/common/_prompt", "blue-ui/view/elements/accessible", "blue-ui/view/modules/tooltip"], (function (e) {
    "use strict";
    return function () {
        this.viewId = "DataSet", this.init = function () {
            this.viewName = "DataSet", this.template = e("common/template/common/dataSet"), this.bridge = e("common/view/webspec/common/dataSet"), this.partials = {prompt: e("common/template/common/_prompt")}, this.views = {
                blueAccessible: e("blue-ui/view/elements/accessible"),
                blueTooltip: e("blue-ui/view/modules/tooltip")
            }, this.onComplete = function () {
                if (-1 !== navigator.userAgent.indexOf("Firefox") && -1 === this.rtemplate.get("type").indexOf("stack")) {
                    var e = this.$element.find(".dataValue"), t = null, n = e.addClass("nowrap").height();
                    e.removeClass("nowrap");
                    var i, o, r = [];
                    for (i = 0; i < e.length; i++) (t = $(e[i])).height() > n && r.push(t);
                    for (i = 0; i < r.length; i++) {
                        var a = r[i], s = a.position().top;
                        for (o = 0; o < e.length; o++) (t = $(e[o])).position().top === s && t.prev().css("margin-bottom", a.height() + 20 + "px")
                    }
                }
            }
        }
    }
})),define("common/template/common/printerIcon", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "a",
            m: [{n: "href", f: "javascript:void(0);", t: 13}, {n: "class", f: "printerclass", t: 13}, {
                n: "click",
                f: {n: [{t: 2, r: ".rClick"}], d: []},
                t: 70
            }, {n: "keydown", f: {n: [{t: 2, r: ".rKeydown"}], d: []}, t: 70}, {
                n: "blur",
                f: {n: [{t: 2, r: ".rBlur"}], d: []},
                t: 70
            }, {n: "style", f: "text-decoration:none", t: 13}],
            f: [{
                t: 4,
                n: 53,
                f: [{t: 8, r: "blueIconwrap"}],
                x: {
                    r: [".id", ".classes", ".type", ".adatext", ".tabValue"],
                    s: "{id:_0,classes:_1,type:_2,adatext:_3,tabValue:_4}"
                }
            }]
        }],
        e: {}
    }
})),define("common/view/common/printerIcon", ["require", "common/template/common/printerIcon", "blue-ui/template/elements/iconwrap", "blue-ui/template/elements/icon", "blue-ui/view/webspec/elements/iconwrap"], (function (e) {
    "use strict";
    return function () {
        this.template = e("common/template/common/printerIcon"), this.partials = {
            blueIconwrap: e("blue-ui/template/elements/iconwrap"),
            blueIcon: e("blue-ui/template/elements/icon")
        }, this.bridge = e("blue-ui/view/webspec/elements/iconwrap"), this.init = function () {
        }
    }
})),define("common/view/format", ["require", "moment", "mout/number/currencyFormat", "common/lib/merchantBillPay/formatUtilityWrapper"], (function (e) {
    var t = e("moment"), n = e("mout/number/currencyFormat"), i = e("common/lib/merchantBillPay/formatUtilityWrapper");
    return {
        dynamicContent: function (e, t) {
            return e && t && Object.keys(t).filter((function (e) {
                return t.hasOwnProperty(e)
            })).forEach((function (n) {
                e = e.replace(new RegExp("{{" + n + "}}", "g"), t[n])
            })), e
        }, formatDate: function (e, n) {
            return e && t(e, "YYYYMMDD").format(n || "l")
        }, formatMoney: function (e) {
            return void 0 === e ? "" : isNaN(Number(e)) ? e : "number" == typeof e ? i.commonCurrency(e) : i.commonCurrency(Number(e))
        }, formatNumber: function (e) {
            return void 0 === e ? "" : isNaN(Number(e)) ? e : n("number" == typeof e ? e : Number(e), 2)
        }
    }
})),define("common/view/globalSpecMixin", ["require", "bluespec/global", "appkit-utilities/content/dcu"], (function (e) {
    "use strict";
    var t = e("bluespec/global"), n = e("appkit-utilities/content/dcu");
    return function () {
        var e = this;
        e.model = e.model || {}, Object.keys(t.settings).forEach((function (t) {
            e.model[t] = e.model[t] || n.dynamicContent.getGlobal(t)
        }))
    }
})),define("common/view/hybrid/start", ["blue/root", "blue/log"], (function () {
    return function (e) {
        var t = require("blue/root"), n = require("blue/log")("[HYBRID_SKINNY_APP]");
        this.viewName = "hybridStart", this.template = "<div></div>", this.init = function () {
            this.bridge = {name: "HybridStart", bindings: {}, triggers: {render: {action: "view.onReady"}}}
        }, this.onReady = function () {
            if (t.getHybridHeartBeat = function () {
                return {status: !0}
            }, !0 === this.model.enabledOnThisPod) {
                e.site.timerMark("hybrid-skinny-complete");
                var i = e.site.timerReport();
                e.is.string(i) && -1 !== i.indexOf("page-start") && n.error(i), n.error("hybridStart view ready"), "function" == typeof window.getHybridHeartBeat && n.error("getHybridHeartBeat function available")
            }
            this.model.hashTarget && (window.location.hash = this.model.hashTarget + (this.model.hashArgs ? this.model.hashArgs : "")), e.page.$("#twitter-speedBump").remove()
        }
    }
})),define("common/template/common/loader", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "div",
            m: [{n: "id", f: [{t: 2, r: ".id", s: !0}], t: 13}],
            f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: [{t: 4, f: ["util hidden"], n: 51, r: "display"}], t: 13}],
                f: [{t: 16}]
            }, " ", {
                t: 4,
                f: [{
                    t: 4,
                    f: [{
                        t: 7,
                        e: "blueLoader",
                        m: [{n: "id", f: ["loader-", {t: 2, r: ".id", s: !0}], t: 13}, {
                            n: "classes",
                            f: [{t: 2, r: "classes"}],
                            t: 13
                        }, {
                            n: "adaAriaLiveContent",
                            f: [{t: 4, f: [{t: 2, r: ".adaAriaLiveContent"}], n: 50, r: ".adaAriaLiveContent"}, {
                                t: 4,
                                n: 51,
                                f: ["false"],
                                l: 1
                            }],
                            t: 13
                        }]
                    }],
                    n: 51,
                    r: "error"
                }],
                n: 51,
                r: "display"
            }]
        }]
    }
})),define("common/view/webspec/loader", {
    name: "loader",
    bindings: {isIOS: {}, display: {}},
    triggers: {teardown: {action: "view.teardown"}}
}),define("common/view/loader", ["require", "appkit-utilities/view/viewFirstAda", "blue/$", "common/lib/focusUtil", "common/template/common/loader", "common/view/webspec/loader", "blue-ui/view/elements/loader"], (function (e) {
    "use strict";
    var t = e("appkit-utilities/view/viewFirstAda"), n = e("blue/$"), i = e("common/lib/focusUtil");
    return function () {
        var o = this;
        o.template = e("common/template/common/loader"), o.init = function () {
            o.bridge = e("common/view/webspec/loader"), o.model.contentLoadingAda = o.context.globalContent.contentLoadingAda, o.contentLoadedAda = o.context.globalContent.contentLoadedAda, o.contentLoadingAda = o.context.globalContent.contentLoadingAda, o.model.reset = !1, o.contentLoading = !0
        }, o.views = {blueLoader: e("blue-ui/view/elements/loader")}, o.onReady = function () {
            var e = o.model.id || "content", r = o.model.adaAriaLiveContent, a = o.model.focusOnContentDisplay,
                    s = o.model.isGlobalLoader;
            r && (t.call(o, "display", e, o.contentLoadedAda), s && setTimeout((function () {
                o.contentLoading && (t.call(o, "display", e, o.contentLoadingAda), o.triggerViewFirstAda(), o.resetViewFirstAda(), t.call(o, "display", e, o.contentLoadedAda))
            }), 2e3)), o.onData("display", (function (t) {
                if (t && (o.triggerViewFirstAda && o.triggerViewFirstAda(), a)) {
                    var r = "#" + e + " " + a;
                    i.setFocus(n, r, 100)
                }
            }), {context: o}), o.onData("reset", (function (e) {
                e && o.resetViewFirstAda && o.resetViewFirstAda()
            }), {context: o})
        }, o.teardown = function () {
            (o.contentLoading = !1, void 0 === o.model.display) && (o.model.adaAriaLiveContent && o.triggerViewFirstAda && o.triggerViewFirstAda())
        }
    }
})),define("common/view/webspec/progressBar", {
    name: "PROGRESS",
    bindings: {},
    triggers: {}
}),define("common/template/progressBar/progressBar", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "div",
            m: [{n: "id", f: [{t: 2, r: "id"}], t: 13}, {n: "class", f: "progress u-no-outline", t: 13}, {
                n: "tabindex",
                f: "-1",
                t: 13
            }],
            f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: "row", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12 col-sm-6 clear-padding", t: 13}],
                    f: [{
                        t: 4,
                        f: [{
                            t: 7,
                            e: "h2",
                            f: [{t: 2, r: "progressTitle"}, " ", {
                                t: 7,
                                e: "span",
                                m: [{n: "class", f: "util high-contrast", t: 13}],
                                f: [{t: 2, r: "progressTitleAda"}]
                            }]
                        }],
                        n: 50,
                        r: "progressTitle"
                    }, " ", {t: 4, f: [{t: 16}], n: 50, r: "progressTitleYield"}]
                }, " ", {
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12 col-sm-6 progress-padding", t: 13}],
                    f: [{
                        t: 4,
                        f: [{
                            t: 7,
                            e: "blueProgress",
                            m: [{n: "id", f: [{t: 2, r: "~/id"}, "-progressBar"], t: 13}, {
                                n: "classes",
                                f: [{t: 2, r: "./classes"}],
                                t: 13
                            }, {n: "accessibleText", f: [{t: 2, r: "./accessibleText"}], t: 13}, {
                                n: "classes",
                                f: [{t: 2, r: "./classes"}],
                                t: 13
                            }, {n: "steps", f: [{t: 2, r: "./steps"}], t: 13}]
                        }],
                        r: "progressBar"
                    }]
                }]
            }]
        }]
    }
})),define("common/view/progressBar", ["require", "common/view/webspec/progressBar", "common/template/progressBar/progressBar", "blue-ui/view/elements/progress"], (function (e) {
    "use strict";
    return function () {
        this.viewId = "progressBar", this.init = function () {
            this.viewName = "progressBar", this.bridge = e("common/view/webspec/progressBar"), this.template = e("common/template/progressBar/progressBar")
        }, this.views = {blueProgress: e("blue-ui/view/elements/progress")}
    }
})),define("common/view/webspec/signout", {
    name: "LOGON",
    bindings: {},
    triggers: {}
}),define("common/voc/util/invitation", ["require", "exports", "module", "blue/is", "common/voc/util/session"], (function (e, t, n) {
    "use strict";
    var i = e("blue/is"), o = e("common/voc/util/session");
    return {
        setInvitation: function () {
            var e = o.getConditions(), t = o.getValue("screensVisited"), n = [];
            i.undefined(t) || i.undefined(e) || (e.forEach((function (e) {
                i.defined(t[e.invitationId]) && i.defined(e.count) && t[e.invitationId] >= e.count && (i.undefined(o.getValue("invitationId")) || o.getValue("invitationId") !== e.invitationId) && n.push(e)
            })), n.length > 0 && o.setValue("invitationsToBeSampled", n))
        }, getConfig: function () {
            return n.config()
        }, getSampleRate: function (e, t) {
            return e && e[t] ? parseFloat(e[t], 10) : 0
        }
    }
})),define("common/voc/config/invitationDesktopBusiness", ["require", "common/voc/util/invitation"], (function (e) {
    "use strict";
    var t = e("common/voc/util/invitation").getConfig().vocSampleRates;
    return {
        surveys: [{
            invitationId: "BCCSurvey",
            screens: [{screen: "/"}],
            count: 3,
            sampleRate: t && t.card ? parseFloat(t.cbo, 10) : 0,
            priority: 500,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_6hFezEwwjsfYfdP"
        }, {
            invitationId: "ToggleCardOnFileSurvey",
            screens: [{screen: "requestCardOnFile"}],
            count: 1,
            sampleRate: t && t.toggleCard ? parseFloat(t.toggleCard, 10) : 0,
            priority: 700,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_3mTSqlNHhWc9OcZ/"
        }, {
            invitationId: "MSASurvey",
            screens: [{screen: "msa"}, {screen: "merchantServices"}],
            count: 2,
            sampleRate: t && t.msa ? parseFloat(t.msa, 10) : 0,
            priority: 600,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_5oQSvcZR7WbZOCx/"
        }]
    }
})),define("common/voc/config/invitationDesktopCommercial", ["require", "common/voc/util/invitation"], (function (e) {
    "use strict";
    var t = e("common/voc/util/invitation").getConfig().vocSampleRates;
    return {
        surveys: [{
            invitationId: "CMLbusiness_Survey",
            screens: [{screen: "/"}],
            count: 3,
            sampleRate: t && t.cml ? parseFloat(t.cml, 10) : 0,
            priority: 500,
            suppressionDuration: 180,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_0CnpE2iWz3iDC05"
        }, {
            invitationId: "ToggleCardOnFileSurvey",
            screens: [{screen: "requestCardOnFile"}],
            count: 1,
            sampleRate: t && t.toggleCard ? parseFloat(t.toggleCard, 10) : 0,
            priority: 700,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_3mTSqlNHhWc9OcZ/"
        }]
    }
})),define("common/voc/config/invitationDesktopDefault", ["require", "common/voc/util/invitation"], (function (e) {
    "use strict";
    var t = e("common/voc/util/invitation"), n = t.getConfig().vocSampleRates;
    return {
        surveys: [{
            invitationId: "LongitudinalSurvey",
            screens: [{screen: "/"}],
            count: 1,
            sampleRate: t.getSampleRate(n, "longitudinal"),
            priority: 500,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_4Pm7SD4aIJZgADP"
        }, {
            invitationId: "ToggleCardOnFileSurvey",
            screens: [{screen: "requestCardOnFile"}],
            count: 1,
            sampleRate: t.getSampleRate(n, "toggleCard"),
            priority: 700,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_3mTSqlNHhWc9OcZ/"
        }, {
            invitationId: "AppointmentConfirmedSurvey",
            count: 1,
            sampleRate: t.getSampleRate(n, "appointmentConfirmed"),
            priority: 800,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_bOgWDoFG7VI7hqJ",
            manualOnly: !0
        }, {
            invitationId: "AppointmentDirtyExitSurvey",
            count: 1,
            sampleRate: t.getSampleRate(n, "appointmentDirtyExit"),
            priority: 800,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_9v2v0yyEtuR0K1v",
            manualOnly: !0
        }, {
            invitationId: "ETDDisputesSurvey",
            count: 1,
            sampleRate: t.getSampleRate(n, "etdDisputes"),
            priority: 800,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_dmdvFLKUKk3Y4PH",
            manualOnly: !0
        }]
    }
})),define("common/voc/config/invitationTabletBusiness", ["require", "common/voc/util/invitation"], (function (e) {
    "use strict";
    var t = e("common/voc/util/invitation").getConfig().vocSampleRates;
    return {
        surveys: [{
            invitationId: "BCCSurvey",
            screens: [{screen: "/"}],
            count: 3,
            sampleRate: t && t.cbo ? parseFloat(t.cbo, 10) : 0,
            priority: 500,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_6hFezEwwjsfYfdP"
        }, {
            invitationId: "ToggleCardOnFileSurvey",
            screens: [{screen: "requestCardOnFile"}],
            count: 1,
            sampleRate: t && t.toggleCard ? parseFloat(t.toggleCard, 10) : 0,
            priority: 700,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_3mTSqlNHhWc9OcZ/"
        }, {
            invitationId: "MSASurvey",
            screens: [{screen: "msa"}, {screen: "merchantServices"}],
            count: 2,
            sampleRate: t && t.msa ? parseFloat(t.msa, 10) : 0,
            priority: 600,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_5oQSvcZR7WbZOCx/"
        }]
    }
})),define("common/voc/config/invitationTabletCommercial", ["require", "common/voc/util/invitation"], (function (e) {
    "use strict";
    var t = e("common/voc/util/invitation").getConfig().vocSampleRates;
    return {
        surveys: [{
            invitationId: "CMLbusiness_Survey",
            screens: [{screen: "/"}],
            count: 3,
            sampleRate: t && t.cml ? parseFloat(t.cml, 10) : 0,
            priority: 500,
            suppressionDuration: 180,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_0CnpE2iWz3iDC05"
        }, {
            invitationId: "ToggleCardOnFileSurvey",
            screens: [{screen: "requestCardOnFile"}],
            count: 1,
            sampleRate: t && t.toggleCard ? parseFloat(t.toggleCard, 10) : 0,
            priority: 700,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_3mTSqlNHhWc9OcZ/"
        }]
    }
})),define("common/voc/config/invitationTabletDefault", ["require", "common/voc/util/invitation"], (function (e) {
    "use strict";
    var t = e("common/voc/util/invitation"), n = t.getConfig().vocSampleRates;
    return {
        surveys: [{
            invitationId: "LongitudinalSurvey",
            screens: [{screen: "/"}],
            count: 1,
            sampleRate: t.getSampleRate(n, "longitudinal"),
            priority: 500,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_4Pm7SD4aIJZgADP"
        }, {
            invitationId: "ToggleCardOnFileSurvey",
            screens: [{screen: "requestCardOnFile"}],
            count: 1,
            sampleRate: t.getSampleRate(n, "toggleCard"),
            priority: 700,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_3mTSqlNHhWc9OcZ/"
        }, {
            invitationId: "AppointmentConfirmedSurvey",
            count: 1,
            sampleRate: t.getSampleRate(n, "appointmentConfirmed"),
            priority: 800,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_bOgWDoFG7VI7hqJ",
            manualOnly: !0
        }, {
            invitationId: "AppointmentDirtyExitSurvey",
            count: 1,
            sampleRate: t.getSampleRate(n, "appointmentDirtyExit"),
            priority: 800,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_9v2v0yyEtuR0K1v",
            manualOnly: !0
        }, {
            invitationId: "ETDDisputesSurvey",
            count: 1,
            sampleRate: t.getSampleRate(n, "etdDisputes"),
            priority: 800,
            suppressionDuration: 90,
            surveyUrl: "https://survey.experience.chase.com/jfe/form/SV_dmdvFLKUKk3Y4PH",
            manualOnly: !0
        }]
    }
})),define("common/voc/rule/invitationSuppression", ["require", "blue/is", "common/voc/util/session", "blue/store/enumerable/cookie", "blue-app/settings"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("common/voc/util/session"), i = new (e("blue/store/enumerable/cookie"))(null, !0);
    return function (o, r) {
        delete o.appContext;
        var a = e("blue-app/settings").get("persona");
        if (a && ("PVB" === a.segment || "PCB" === a.segment || "WTH" === a.segment)) return r();
        if (t.defined(i.get("fsr.r"))) {
            if (t.undefined(n.getValue("isInvitationAccepted"))) return r();
            if (!1 === n.getValue("isInvitationAccepted")) return n.cleanSession(), r()
        }
        return o
    }
})),define("common/voc/util/screenMatcher", [], (function () {
    "use strict";
    return function (e, t) {
        return e.some((function (e) {
            return new RegExp(e.toLowerCase()).test(t.screen.currentURL.toLowerCase())
        }))
    }
})),define("common/voc/util/screens", ["require", "blue/is", "common/voc/util/screenMatcher", "common/voc/util/session"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("common/voc/util/screenMatcher"), i = e("common/voc/util/session");
    return {
        setScreensVisited: function (e) {
            if (e.screen.id.toLowerCase() !== i.getValue("lastScreenVisited") && !new RegExp("overlay").test(e.screen.id.toLowerCase())) {
                var o, r = {}, a = "action" === e.payload.eventType, s = i.getConditions();
                t.defined(i.getValue("screensVisited")) && (r = i.getValue("screensVisited")), t.defined(s) && s.length > 0 && s.forEach((function (i) {
                    i.manualOnly || i.screens.forEach((function (s) {
                        n([s.screen], e) && (a ? t.defined(s.actions) && s.actions.forEach((function (n) {
                            t.defined(n.action) && t.defined(e.payload.action) && e.payload.action === n.action && (o = s.screen + "@" + e.payload.action, t.defined(r[o]) ? r[o]++ : r[o] = 1)
                        })) : t.defined(r) && (t.defined(r[i.invitationId]) ? r[i.invitationId]++ : r[i.invitationId] = 1, t.defined(r[s.screen]) ? r[s.screen]++ : r[s.screen] = 1))
                    }))
                })), i.setValue("screensVisited", r), i.setValue("lastScreenVisited", e.screen.id.toLowerCase())
            }
        }, manuallySetScreensVisited: function (e) {
            var n = {}, o = i.getConditions(), r = i.getValue("screensVisited");
            t.defined(r) && (n = r), t.defined(o) && o.length > 0 && o.forEach((function (i) {
                i.invitationId === e.invitationId && (t.defined(n[e.invitationId]) ? n[e.invitationId]++ : n[e.invitationId] = 1)
            })), i.setValue("screensVisited", n), i.setValue("lastScreenVisited", e.invitationId)
        }
    }
})),define("common/voc/rule/screenTracking", ["require", "common/voc/util/screens"], (function (e) {
    "use strict";
    var t = e("common/voc/util/screens");
    return function (e) {
        return delete e.appContext, e.manualTrigger ? t.manuallySetScreensVisited(e) : t.setScreensVisited(e), e
    }
})),define("common/voc/rule/setInvitation", ["require", "common/voc/util/invitation"], (function (e) {
    "use strict";
    var t = e("common/voc/util/invitation");
    return function (e, n, i) {
        return delete e.appContext, e.manualTrigger || "screen" === e.payload.eventType ? (t.setInvitation(), e) : i
    }
})),define("common/voc/rule/samplingRate", ["require", "blue/is", "common/voc/util/session"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("common/voc/util/session");
    return function (e) {
        var i, o;
        if (delete e.appContext, t.defined(n.getValue("invitationsToBeSampled")) && (e.manualTrigger || "screen" === e.payload.eventType && !new RegExp("overlay").test(e.screen.id.toLowerCase()))) {
            i = Math.random(), o = n.getValue("invitationsToBeSampled");
            for (var r = 0; r < o.length; r++) if (o[r].sampleRate >= i) {
                n.setValue("invitationId", o[r].invitationId), n.removeConditions(o[r].priority), n.removeValue("invitationsToBeSampled");
                break
            }
        }
        return e
    }
})),define("common/voc/rule/blacklisted", ["require", "blue/is", "common/voc/util/session", "common/voc/util/screenMatcher", "blue/siteData", "common/voc/config/common"], (function (e) {
    "use strict";
    var t = e("blue/is"), n = e("common/voc/util/session"), i = e("common/voc/util/screenMatcher"),
            o = e("blue/siteData"), r = e("common/voc/config/common").blacklisted;
    return function (e) {
        if (delete e.appContext, e.manualTrigger) return n.setValue("isBlacklisted", !1), e;
        if (t.undefined(n.getValue("isInvitationAccepted")) && "screen" === e.payload.eventType) {
            var a = "BUSINESS" === o.getData("brandId") ? r.cbo : "COMMERCIAL" === o.getData("brandId") ? r.cml : r.cpo;
            n.setValue("isBlacklisted", i(a, e))
        } else n.removeValue("isBlacklisted");
        return e
    }
})),define("common/voc/rule/displayInvitation", ["require", "common/voc/util/session", "blue/is", "blue/store/enumerable/cookie", "common/voc/util/survey"], (function (e) {
    "use strict";
    var t = e("common/voc/util/session"), n = e("blue/is"), i = new (e("blue/store/enumerable/cookie"))(null, !0),
            o = e("common/voc/util/survey"), r = {};
    return function (e) {
        if (r = e.appContext, delete e.appContext, n.undefined(t.getValue("isInvitationAccepted")) && !1 === t.getValue("isBlacklisted") && n.defined(t.getValue("invitationId")) && n.undefined(t.getValue("invitationShown"))) {
            var a = o.getSurvey(t.getValue("invitationId"));
            n.defined(a) && i.set("fsr.r", a.invitationId, new Date((new Date).setDate((new Date).getDate() + a.suppressionDuration))), t.setValue("isInvitationAccepted", null), e.vocData && t.setValue("customVars", e.vocData), r.broadcast("showInvitationDialog")
        }
        return e
    }
})),define("common/voc/config/rules", ["require", "common/voc/rule/invitationSuppression", "common/voc/rule/screenTracking", "common/voc/rule/setInvitation", "common/voc/rule/samplingRate", "common/voc/rule/blacklisted", "common/voc/rule/displayInvitation"], (function (e) {
    "use strict";
    return {
        invitationSuppression: e("common/voc/rule/invitationSuppression"),
        screenTracking: e("common/voc/rule/screenTracking"),
        setInvitation: e("common/voc/rule/setInvitation"),
        samplingRate: e("common/voc/rule/samplingRate"),
        blacklisted: e("common/voc/rule/blacklisted"),
        displayInvitation: e("common/voc/rule/displayInvitation")
    }
})),define("common/voc/rulesMapper", ["require", "blue/is"], (function (e) {
    "use strict";
    var t = e("blue/is");
    return function (e, n, i) {
        var o, r, a = e.asEventStream();
        return t.undefined(n) ? a : (o = Object.keys(n).reduce((function (e, t) {
            return e.map((function (e) {
                return e.appContext = i, n[t](e, (function () {
                    return r(), !1
                }), !1)
            })).filter((function (e) {
                return e
            }))
        }), a), r = o.onValue((function () {
        })), o)
    }
})),define("common/voc/decisionEngine", ["require", "blue/observable", "common/voc/rulesMapper", "blue/event/channel", "common/voc/config/rules", "blue/is", "common/voc/util/invitation", "analytics/util/streamCollator"], (function (e) {
    "use strict";
    var t = e("blue/observable"), n = e("common/voc/rulesMapper"), i = new (e("blue/event/channel")),
            o = e("common/voc/config/rules"), r = e("blue/is"), a = e("common/voc/util/invitation").getConfig();
    return function () {
        var s = arguments[1], c = arguments[0].appContext;
        e("analytics/util/streamCollator")(arguments[0].site, arguments[0].application).then((function (e) {
            var l;
            r.array(e) && a.enableVoc ? (l = e.slice(0, 2), i.plug(t.merge(l)), n(i, o, c)) : i.destroy(), r.function(s) && s(i)
        }))
    }
})),define("common/voc/manualTrigger", ["require", "common/voc/config/rules", "common/voc/util/invitation", "blue/is"], (function (e) {
    "use strict";
    var t = e("common/voc/config/rules"), n = e("common/voc/util/invitation").getConfig(), i = e("blue/is");
    return function (e, o, r) {
        if (n.enableVoc && !i.undefined(t)) {
            var a = {manualTrigger: !0, invitationId: o, vocData: r}, s = Object.keys(t), c = !1;
            s.forEach((function (n) {
                c || (a.appContext = e, t[n](a, (function () {
                    return c = !0
                }), c))
            }))
        }
    }
})),define("common/riskBasedOtp/customValidator/riskBasedOtpValidator", [], (function () {
    "use strict";
    return {
        identificationCode: [{
            validator: function (e, t) {
                var n = 8 === (t[e.item] ? t[e.item].trim() : "").length;
                return {isValid: n, errorType: n ? "" : "INVALID_FORMAT_" + t.configuration.contentVariation}
            }, type: "custom"
        }], requiredFields: ["identificationCode"]
    }
})),define("common/riskBasedOtp/component/riskBasedOtp", ["require", "appkit-utilities/validation/componentValidate", "common/riskBasedOtp/customValidator/riskBasedOtpValidator", "appkit-utilities/validation/variations", "common/lib/utility/hybridMixin"], (function (e) {
    "use strict";
    return function (t) {
        var n, i = this, o = e("appkit-utilities/validation/componentValidate"),
                r = e("common/riskBasedOtp/customValidator/riskBasedOtpValidator"),
                a = e("appkit-utilities/validation/variations"), s = e("common/lib/utility/hybridMixin"),
                c = function (e, n) {
                    var o = i.configuration.callbacks[e];
                    t.is.function(o) && o(n)
                };
        i.setHybridHeader = function () {
            t.hybrid && !i.configuration.setHybridNotRequired && (i.hybridNavigation = !!i.configuration.hybridNavigation, i.dispatchHybridEvent("updateNativeNavigationBarButtons", t, {
                showCancel: i.hybridNavigation,
                showBackArrow: !1
            }), setTimeout((function () {
                i.dispatchHybridEvent("changeScreenTitle", t, i.mobileAppHeader)
            }), 50), t.jsBridge.leaveFlow = function () {
                return i.cancelTask(), !0
            }, t.jsBridge.isHybridBackButtonRequired = function () {
                return i.cancelTask(), !0
            })
        }, i.init = function () {
            var e = i.configuration.contentVariation;
            n = i.configuration.flowSteps, s.call(i), function (e) {
                var n = t.dcu.dynamicContent, o = n.get;
                n.setMultiple(i, [{
                    key: "authenticationProcessHeader",
                    variation: e
                }, {key: "authenticationProcessMessage", variation: e}, {
                    key: "provideIdentificationCodeMessage",
                    variation: e
                }, {key: "authenticationProcessAdvisory", variation: e}, {
                    key: "verificationHeader",
                    variation: e
                }, {key: "verificationAdvisory", variation: e}, {
                    key: "identificationCodeLabel",
                    variation: e
                }, {key: "resendIdentificationCodeHeader", variation: e}, {
                    key: "mobileAppHeader",
                    variation: e
                }]), i.model.set("phoneNumberMaskSymbol", n.getGlobal("phoneNumberMaskSymbol", "UNITED_STATES")), t.globalContentMixin.call(i, ["horizontalEllipsisSymbol", "atSymbol", "errorAnnouncementAda", "importantAda", "warningAda"]), i.identificationDeliveryMethodOptionsLabel = {
                    PHONE: o(i, "identificationDeliveryMethodOptionsLabel", "PHONE"),
                    EMAIL: o(i, "identificationDeliveryMethodOptionsLabel", "EMAIL")
                }, i.identificationDeliveryMethodOptionName = {
                    SMS: o(i, "identificationDeliveryMethodOptionName", "TEXT"),
                    VOICE: o(i, "identificationDeliveryMethodOptionName", "VOICE"),
                    TEXT_SCHEDULE_TRANSFER: o(i, "identificationDeliveryMethodOptionName", "TEXT_SCHEDULE_TRANSFER"),
                    VOICE_SCHEDULE_TRANSFER: o(i, "identificationDeliveryMethodOptionName", "VOICE_SCHEDULE_TRANSFER")
                }
            }(e), function (e) {
                t.variations = t.util.object.merge(a, {
                    identificationCode: {
                        NotBlank: "MISSING_MANDATORY_DATA_" + e,
                        Integer: "INVALID_FORMAT_" + e
                    }
                }), t.requiredErrorType = "MISSING_MANDATORY_DATA_" + e, o.call(i, t, r)
            }(e), i.setHybridHeader()
        }, i.cancelTask = function (e) {
            var n = e && t.util.object.get(e, "domEvent.target");
            c("handleOtpCancel", n)
        }, i.verifyTask = function () {
            if (0 === i.identificationDeliveryMethodOptions.length) i.provideIdentificationCode(); else {
                var e = i.identificationDeliveryMethodOptionId.split("-");
                t.sendOtp(e[0], e[1])
            }
        }, i.confirmTask = function () {
            ({
                true: function () {
                    i.validateFormData(), i.v.isFormValid ? t.verifyOtp() : (i.focusOnIdentificationCodeField = !1, i.focusOnIdentificationCodeField = !0)
                }, false: t.verifyOtp
            })[!!i.configuration.hasEnabledButtonValidation]()
        }, i.resendIdentificationCode = function () {
            t.getOtpContactsList()
        }, i.provideIdentificationCode = function () {
            t.getOtpPrefixList()
        }, i.handleResendCodeClick = function (e) {
            "resendIdentificationCode" === t.util.object.get(e, "domEvent.target.dataset.attr") && i.resendIdentificationCode()
        }, i.showServiceError = function (e) {
            var n = i.configuration && i.configuration.statusCodeToVariationMap;
            if (n) {
                var o = n[e] || n.SYSTEM_FAILURE;
                t.dcu.dynamicContent.setMultiple(i, [{
                    key: "authenticationProcessErrorHeader",
                    variation: o
                }, {key: "authenticationProcessErrorAdvisory", variation: o}])
            }
        }, i.handleContactListSuccess = function () {
            if (i.progressCurrentStep === n.enterCode && i.navigateResendOtp(), i.removeFromWhiteList("identificationCode"), i.configuration.showNoContactMsg && !i.model.get("hasIdentificationDeliveryOptions")) {
                t.dcu.dynamicContent.setMultiple(i, [{
                    key: "authenticationProcessHeader",
                    variation: "INVALID_MOBILE_PHONE"
                }, {key: "authenticationProcessMessage", variation: "INVALID_MOBILE_PHONE"}])
            }
        }, i.navigateResendOtp = function () {
            c("handleResendOtp"), i.progressCurrentStep = n.requestCode, i.identificationCode = ""
        }, i.handleSendAndPrefixListSuccess = function () {
            c("handleOtpSend"), i.progressCurrentStep = n.enterCode, i.addToWhiteList("identificationCode")
        }, i.handleMaxOtpReached = function () {
            c("handleMaxOtpReached")
        }, i.handleVerifyOtpSuccess = function () {
            c("handleOtpVerify", i.model.get("otpLocked"))
        }, i.skipBack = function () {
        }
    }
})),define("common/riskBasedOtp/service/mappers/riskBasedOtp", [], (function () {
    "use strict";
    return function (e) {
        var t = function (e) {
            var t = e[0], n = "";
            return n = t.isPhone ? t.textEnabled ? "SMS" : "VOICE" : "TEXT", t.identificationDeliveryMethodOptionId + "-" + n
        };
        return {
            riskBasedOtpServices: {
                getOtpContactsList: {
                    success: function (n, i, o, r) {
                        var a = [], s = r.get(e + ".phoneNumberMaskSymbol"), c = r.get(e + ".horizontalEllipsisSymbol"),
                                l = r.get(e + ".atSymbol");
                        i.phones && i.phones.forEach((function (e, t, n) {
                            a.push({
                                isPhone: !0,
                                firstOfType: 0 === t,
                                lastOfType: t === n.length - 1,
                                identificationDeliveryMethodOptionId: e.contactId,
                                identificationDeliveryMethodOptionName: s + e.phone,
                                textEnabled: e.textEnabled,
                                extension: e.extension
                            })
                        })), i.emails && i.emails.forEach((function (e, t) {
                            a.push({
                                isPhone: !1,
                                firstOfType: 0 === t,
                                identificationDeliveryMethodOptionId: e.contactId,
                                identificationDeliveryMethodOptionName: e.prefix + c + e.suffix + l + e.domain
                            })
                        })), r.update(e, {
                            identificationDeliveryMethodOptions: a,
                            identificationDeliveryMethodOptionId: a.length > 0 && t(a),
                            hasIdentificationDeliveryOptions: a.length > 0,
                            serviceSuccess: "getOtpContactsList"
                        })
                    }, failure: function (t, n, i, o) {
                        o.set(e + ".serviceError", n)
                    }
                }, sendOtp: {
                    success: function (t, n, i, o) {
                        o.update(e, {otpPrefix: n.prefix, serviceSuccess: "sendOtp"})
                    }, failure: function (t, n, i, o) {
                        n && "MAX_OTP_REACHED" === n.statusCode ? o.update(e, {serviceSuccess: "maxOtpReached"}) : o.set(e + ".serviceError", n)
                    }
                }, verifyOtp: {
                    success: function (t, n, i, o) {
                        o.set(e + ".serviceSuccess", "verifyOtp")
                    }, failure: function (t, n, i, o) {
                        n && "VERIFY_LOCKED" === n.statusCode ? o.update(e, {
                            otpLocked: !0,
                            serviceSuccess: "verifyOtp"
                        }) : o.set(e + ".serviceError", n)
                    }
                }, getOtpPrefixList: {
                    success: function (t, n, i, o) {
                        n.prefix ? o.update(e, {
                            otpPrefix: n.prefix,
                            serviceSuccess: "getOtpPrefixList",
                            hasIdentificationDeliveryOptions: !0
                        }) : (n.statusCode = "ACTIVATION_CODE_REQUIRED", o.get(e + ".configuration.showNoContactMsg") && (n.statusCode = o.get(e + ".hasIdentificationDeliveryOptions") ? "ACTIVATION_CODE_NOT_SENT" : "NO_CONTACTS"), o.set(e + ".serviceError", n))
                    }, failure: function (t, n, i, o) {
                        o.set(e + ".serviceError", n)
                    }
                }
            }
        }
    }
})),define("common/riskBasedOtp/controller/riskBasedOtp", ["require", "common/riskBasedOtp/service/mappers/riskBasedOtp"], (function (e) {
    "use strict";
    return function (t) {
        var n = this, i = e("common/riskBasedOtp/service/mappers/riskBasedOtp"), o = "riskBasedOtp", r = function () {
            n.components[o].handleSendAndPrefixListSuccess()
        }, a = {
            getOtpContactsList: function () {
                n.components[o].handleContactListSuccess()
            }, sendOtp: r, maxOtpReached: function () {
                n.components[o].handleMaxOtpReached()
            }, getOtpPrefixList: r, verifyOtp: function () {
                n.components[o].handleVerifyOtpSuccess()
            }
        };
        n.onInit = function () {
            n.dataProvider.mappers = new i(o), n.model.onValue(o + ".serviceSuccess", (function (e) {
                a[e] && a[e]()
            }), {initial: !1, skipRepeats: !1}), n.model.onValue(o + ".serviceError", (function (e) {
                e && (n.components[o].showServiceError(e.statusCode), "VERIFY_OTP_REQUIRED" === e.statusCode && n.components[o].navigateResendOtp())
            }), {initial: !1, skipRepeats: !1})
        }, n.requestCode = function () {
            var e = [];
            return n.registry.hasComponent(o) || (n.registry.updateComponent(o, {model: n.model.lens(o)}), e.push([n.components[o], n.model.get(o + ".configuration.viewReference"), {target: "#risk-based-otp-container"}]), t.getOtpContactsList()), n.model.set(o + ".progressCurrentStep", n.model.get(o + ".configuration.flowSteps.requestCode")), e
        }, t.getOtpContactsList = function () {
            n.model.set(o + ".serviceError", ""), n.dataProvider.request("riskBasedOtpServices.getOtpContactsList", {
                reasonCode: "RISK_BASED",
                otpKey: n.model.get(o + ".configuration.otpKey"),
                spinner: {}
            })
        }, t.sendOtp = function (e, i) {
            n.model.set(o + ".serviceError", "");
            var r = t.services.riskBasedOtpServices.settings("sendOtp");
            r.url = r.url.replace(/:version/, t.sendOtpServiceVersion ? "v2" : "v1"), t.services.riskBasedOtpServices.settings("sendOtp", r), n.dataProvider.request("riskBasedOtpServices.sendOtp", {
                reasonCode: "RISK_BASED",
                otpKey: n.model.get(o + ".configuration.otpKey"),
                otpMethod: i,
                contactId: e,
                spinner: {}
            })
        }, t.verifyOtp = function () {
            n.model.set(o + ".serviceError", ""), n.dataProvider.request("riskBasedOtpServices.verifyOtp", {
                reasonCode: "RISK_BASED",
                otpKey: n.model.get(o + ".configuration.otpKey"),
                prefix: n.model.get(o + ".otpPrefix"),
                value: n.model.get(o + ".identificationCode"),
                spinner: {}
            })
        }, t.getOtpPrefixList = function () {
            n.model.set(o + ".serviceError", ""), n.dataProvider.request("riskBasedOtpServices.getOtpPrefixList", {
                reasonCode: "RISK_BASED",
                otpKey: n.model.get(o + ".configuration.otpKey"),
                spinner: {}
            })
        }, t.area.riskBasedOtp = {
            initialize: function (e) {
                var t = {configuration: {flowSteps: {requestCode: 0, enterCode: 1}}};
                ["contentVariation", "stickyButtonsParentId", "otpKey", "viewReference", "statusCodeToVariationMap", "hasEnabledButtonValidation", "showNoContactMsg", "hybridNavigation", "setHybridNotRequired", "callbacks"].forEach((function (n) {
                    t.configuration[n] = e[n]
                })), n.model.update(o, t)
            }, requestCode: function () {
                t.privateState("./requestCode")
            }, destroy: function () {
                n.registry.destroyComponent(o).then((function () {
                    n.model.set(o, {})
                }))
            }, setHybridHeader: function () {
                n.registry.hasComponent(o) && n.components[o].setHybridHeader()
            }
        }
    }
})),define("common/riskBasedOtp/service/riskBasedOtpServices", ["require", "common/interceptor/serverValidationStatusInterceptor", "common/service/interceptor/loggedOffInterceptor", "common/service/interceptor/spinner"], (function (e) {
    "use strict";
    var t = e("common/interceptor/serverValidationStatusInterceptor"),
            n = e("common/service/interceptor/loggedOffInterceptor"), i = e("common/service/interceptor/spinner");
    return function (e) {
        var o = e.config.serviceUrl, r = e.util.object.merge, a = {settings: {statusCodeField: "statusCode"}};
        this.serviceInterceptors = [t, n, i], this.serviceCalls = {
            getOtpContactsList: r({}, a, {settings: {url: o + "/svc/wl/auth/secure/v1/otp/contacts/list"}}),
            sendOtp: r({}, a, {settings: {url: o + "/svc/wl/auth/secure/:version/otp/send"}}),
            verifyOtp: r({}, a, {settings: {url: o + "/svc/wl/auth/secure/v1/otp/verify"}}),
            getOtpPrefixList: r({}, a, {settings: {url: o + "/svc/wl/auth/secure/v1/otp/prefix/list"}})
        }
    }
})),define("bluespec/authentication_process", [], (function () {
    return {
        name: "AUTHENTICATION_PROCESS",
        data: {
            identificationDeliveryMethodOptions: {
                type: "List",
                items: {
                    identificationDeliveryMethodOptionName: "Description",
                    identificationDeliveryMethodOptionId: "Description"
                }
            },
            identificationDeliveryMethodOptionId: {type: "Description"},
            identificationCode: {type: "IdentificationCode"},
            mailingAddress: {type: "Description"},
            password: {type: "Password"},
            securityToken: {type: "RSAToken"}
        },
        actions: {
            verifyTask: !0,
            confirmTask: !0,
            provideIdentificationCode: !0,
            cancelIdentificationCode: !0,
            cancelTask: !0,
            initiateTask: !0,
            optOut: !0,
            resendIdentificationCode: !0,
            verifySecurityCode: !0,
            requestIdentificationDeliveryMethodOptionsHelpMessage: !0,
            requestInitiationMessageHelpMessage: !0,
            skipBack: !0,
            exitAuthenticationProcess: !0,
            print: !0,
            addPayorDetails: !0,
            requestVerifyActivationCode: !0,
            requestIdentificationCode: !0
        },
        states: {exitConfirmationOverlay: !0, optOutConfirmationOverlay: !0},
        settings: {
            progressBarStepName: !0,
            requestIdentificationDeliveryMethodOptionsHelpMessageAda: !0,
            backLabel: !0,
            cancelLabel: !0,
            nextLabel: !0,
            skipBackLabel: !0,
            identificationCodeLabel: !0,
            resendIdentificationCodeLabel: !0,
            resendIdentificationCodeHeader: !0,
            resendIdentificationCodeMessage: !0,
            passwordLabel: !0,
            securityTokenLabel: !0,
            verificationCodeSendingLabel: !0,
            verificationCodeSentLabel: !0,
            identificationCodeSentHeader: !0,
            identificationCodeSentAdvisory: !0,
            initiationMessage: !0,
            initiationMessageHelpMessage: !0,
            requestInitiationMessageHelpMessageAda: !0,
            mailingAddressLabel: !0,
            identificationDeliveryMethodMessage: !0,
            identificationDeliveryMethodOptionName: !0,
            identificationDeliveryMethodOptionsLabel: !0,
            identificationDeliveryMethodOptionsError: !0,
            identificationDeliveryMethodOptionsHelpMessage: !0,
            identificationDeliveryMethodOptionsAdvisory: !0,
            mobileAppHeader: !0,
            authenticationProcessHeader: !0,
            authenticationProcessMessage: !0,
            noPhoneNumberAvailableMessage: !0,
            verificationHeader: !0,
            verificationAdvisory: !0,
            provideIdentificationCodeMessage: !0,
            optOutLabel: !0,
            authenticationProcessAdvisory: !0,
            identificationCodeError: !0,
            passwordError: !0,
            securityTokenError: !0,
            exitAda: !0,
            printAda: !0,
            authenticationProcessErrorHeader: !0,
            authenticationProcessErrorAdvisory: !0,
            mobilePageTitle: !0,
            requestVerifyActivationCodeLabel: !0,
            callUsAnytimeHeader: !0,
            callUsAnytimeMessage: !0
        }
    }
})),define("common/riskBasedOtp/spec/riskBasedOtp", ["require", "blue/util", "bluespec/authentication_process"], (function (e) {
    "use strict";
    return e("blue/util").object.merge(e("bluespec/authentication_process"), {
        states: {
            progressCurrentStep: !0,
            configuration: !0,
            serviceError: !0,
            focusOnIdentificationCodeField: !0,
            hasIdentificationDeliveryOptions: !0,
            hybridNavigation: !0,
            setHybridNotRequired: !0
        }
    })
})),define("common/riskBasedOtp/view/spec/riskBasedOtp", {
    name: "AUTHENTICATION_PROCESS",
    defaultBindings: !0,
    preventDefault: !0,
    bindings: {
        configuration: {},
        progressCurrentStep: {},
        serviceError: {},
        focusOnIdentificationCodeField: {},
        identificationDeliveryMethodOptionId: {direction: "BOTH"},
        identificationCode: {direction: "BOTH"},
        hasIdentificationDeliveryOptions: {},
        hybridNavigation: {},
        setHybridNotRequired: {}
    },
    triggers: {
        handleResendCodeClick: {action: "handleResendCodeClick"},
        formFieldTracking: {action: "formField"},
        skipBack: {preventDefault: !1},
        filterIdentificationCode: {action: "view.filterIdentificationCode"}
    }
}),define("common/authenticationProcess/validator/authenticationProcessValidator", [], (function () {
    "use strict";
    return {
        requiredFields: ["password", "securityToken"],
        customValidations: [],
        requiredValidations: [],
        pageValidators: [],
        runFieldLevelRequired: !0,
        disableSubmitButtonUntilAllFieldsValidLive: !0
    }
})),define("common/authenticationProcess/component/authenticationProcess", ["require", "common/utility/deviceSignature", "common/lib/componentErrorMixin", "common/utility/dynamicContentUtil", "common/lib/validationEngineBlue", "common/authenticationProcess/validator/authenticationProcessValidator"], (function (e) {
    "use strict";
    var t = {
                criticalerror: "INVALID_TOKEN",
                lockederror: "INVALID_TOKEN",
                replayerror: "INVALID_TOKEN",
                sesexperror: "INVALID_TOKEN",
                rsasecondcode: "INVALID_TOKEN",
                otperror: "INVALID_TOKEN",
                otprequired: "INVALID_TOKEN",
                suspiciouserror: "INVALID_TOKEN",
                inactiveerror: "INVALID_TOKEN",
                suspenderror: "INVALID_TOKEN",
                frauderror: "INVALID_TOKEN",
                invalid: "INVALID_TOKEN",
                expired: "INVALID_TOKEN",
                secauth: "INVALID_TOKEN"
            }, n = {
                criticalerror: "INVALID_ACTIVATION_CODE",
                lockederror: "INVALID_ACTIVATION_CODE",
                replayerror: "INVALID_ACTIVATION_CODE",
                sesexperror: "INVALID_ACTIVATION_CODE",
                rsasecondcode: "INVALID_ACTIVATION_CODE",
                otperror: "INVALID_ACTIVATION_CODE",
                otprequired: "INVALID_ACTIVATION_CODE",
                suspiciouserror: "INVALID_ACTIVATION_CODE",
                inactiveerror: "INVALID_ACTIVATION_CODE",
                suspenderror: "INVALID_ACTIVATION_CODE",
                frauderror: "INVALID_ACTIVATION_CODE",
                invalid: "INVALID_ACTIVATION_CODE",
                expired: "INVALID_ACTIVATION_CODE",
                secauth: "INVALID_ACTIVATION_CODE"
            }, i = e("common/utility/deviceSignature"), o = e("common/lib/componentErrorMixin"),
            r = e("common/utility/dynamicContentUtil").dynamicSettings, a = e("common/lib/validationEngineBlue"),
            s = e("common/authenticationProcess/validator/authenticationProcessValidator");
    return function (e) {
        var c = this, l = null, u = null, d = null, m = 0;

        function f() {
        }

        function p() {
            c.resetError(), c.identificationDeliveryMethodOptionsError = "", c.identificationDeliveryMethodOptionsAdvisory = "", c.identificationCode = "", c.password = "", c.securityToken = "", m = 0
        }

        function g() {
            return e.services.authenticationProcessService.getOTPContactList({reasonCode: c.reasonCode}).then((function (e) {
                return c.identificationDeliveryMethodOptions = function (e) {
                    return (e && e.phones || []).map((function (e) {
                        return {
                            identificationDeliveryMethodOptionId: e.contactId,
                            identificationDeliveryMethodOptionName: e.phone,
                            textEnabled: e.textEnabled
                        }
                    }))
                }(e), function (e) {
                    if (!e) return e;
                    return e
                }(e)
            })).then((function (e) {
                return !e || "SUCCESS" !== e.code || e.statusCode ? Promise.reject(e) : e
            })).then((function (e) {
                if (!e || !e.phones || 0 === e.phones.length) {
                    r.set(c, "identificationDeliveryMethodOptionsError", "MFA_REQUIRED_NO_PHONE_NUMBERS"), r.set(c, "identificationDeliveryMethodOptionsAdvisory", "MFA_REQUIRED_NO_PHONE_NUMBERS")
                }
            }))
        }

        function y() {
            var t = e.util.object.get(e, "areaSettings.settingsStore.otpConfigs.maxOtpCheckRequired") ? "sendOTPRequestV2" : "sendOTPRequest";
            return e.services.authenticationProcessService[t]({contactId: u, otpMethod: d, reasonCode: c.reasonCode})
        }

        function h() {
            var t = c.cancelRoute || e.settings.get("dashboardUrl");
            return e.entryPointTracker && e.entryPointTracker.get("achfileuploadOTPUrl") && (t = e.settings.get(e.entryPointTracker.get("achfileuploadOTPUrl")), e.entryPointTracker.clear()), e.state(t), e.controller.broadcast("destroyAll")
        }

        c.init = function () {
            var t;
            o(c, "authenticationProcessErrorHeader", "authenticationProcessErrorAdvisory"), a(c, s), c.registerValidation(["identificationCode", "password", "securityToken"]), e.globalContentMixin && e.globalContentMixin.call(c, ["okLabel"]), e.memoryStore && (t = e.memoryStore.get("hybridNavigation")) && c.output.emit("updateViewModel", {data: {hybridNavigation: t}}), c.reset = p, c.display = g
        }, c.initiateTask = function (t) {
            return "ENTER" === t.context ? c.exitAuth() : e.routeHistory.goBack()
        }, c.confirmTask = function (o) {
            var r = o && o.context, a = r && r.password, s = r && r.securityToken, f = r && r.identificationCode,
                    p = a && s, g = l && f;
            return e.services.authenticationProcessService.getUserMetadataList().then((function (t) {
                var n = e.stringMap.get("overview"), o = {
                    auth_otpreason: c.auth_otpreason,
                    auth_otpkey: t && t.personId,
                    auth_siteId: n.SITE_ID,
                    auth_deviceSignature: i.getDeviceSignature(),
                    auth_deviceCookie: e.config.authDeviceCookie || n.DEVICE_COOKIE,
                    auth_contextId: "verifyotp",
                    type: n.TYPE
                };
                if (p) o.auth_passwd = a, o.auth_tokencode = s; else {
                    if (!g) return Promise.reject(new Error("Unable to determine authenticationMethod for OTP"));
                    o.auth_otpprefix = l, o.auth_otp = f
                }
                return e.services.authenticationProcessService.authenticationLogin(o)
            })).then((function (e) {
                return function (e, t) {
                    if (!e) return e;
                    return e.statusCode = (t || {})[e.response] || e.statusCode, e.code = {success: "SUCCESS"}[e.response] || "", e
                }(e, p ? t : n)
            })).then((function (e) {
                return !e || "SUCCESS" !== e.code || e.statusCode ? Promise.reject(e) : e
            })).then((function () {
                return u = null, d = null, e.state(c.confirmRoute || e.settings.get("dashboardUrl")), e.controller.broadcast("destroyAll")
            })).catch((function (t) {
                if (g && 3 == ++m) {
                    var n = e.state();
                    return n.action = {
                        name: "initiateActivationCode",
                        params: {statusCode: "IDENTIFICATION_CODE_INVALID"}
                    }, e.state(n)
                }
                return Promise.reject(t)
            })).catch((function (t) {
                e.logger.warn("Error while calling confirmTask", t), c.setError(t)
            })).catch((function (t) {
                e.logger.error("Error while calling confirmTask. Error message may not have been displayed.", t)
            }))
        }, c.verifyTask = function (t) {
            var n = t && t.context;
            u = n && n.contactId, d = n && n.contactMethod;
            var i = {};
            return Promise.resolve(c.context.application.emit("spinner:on", i)).then((function () {
                return y()
            })).then((function (t) {
                if ("MAX_OTP_REACHED" !== t.statusCode) {
                    l = t.prefix;
                    var n = e.state();
                    return n.action = {name: "verifyActivationCode"}, e.state(n)
                }
                c.output.emit("updateViewModel", {data: {step: "ERROR"}}), t.statusCode = "MAXIMUM_ATTEMPTS_REACHED_FOR_OTP", c.setError(t)
            })).catch((function (t) {
                e.logger.warn("Error while calling verifyTask", t), c.setError(t)
            })).catch((function (t) {
                e.logger.error("Error while calling verifyTask. Error message may not have been displayed.", t)
            })).then((function () {
                c.context.application.emit("spinner:off", i)
            }))
        }, c.cancelTask = function (t) {
            var n = e.contextValidationAPI || {}, i = n && n.showConfirmationOverlay;
            return e.isDirty() ? i(e.controller, c, "exitConfirmationOverlay", h, (function () {
                var e = t && t.context, n = e && e.id;
                c.output.emit("setFocus", {focus: n})
            })) : h()
        }, c.exitAuthenticationProcess = function () {
            h()
        }, c.exitAuth = function () {
            return e.state(c.cancelRoute || e.settings.get("dashboardUrl")), e.controller.broadcast("destroyAll")
        }, c.resendIdentificationCode = function () {
            return new Promise((function (e, t) {
                u && d || t({statusCode: "MFA_REQUIRED_NO_PHONE_NUMBERS"}), e()
            })).then((function () {
                c.output.emit("updateViewModel", {data: {resendingStep: "start"}})
            })).then((function () {
                return y()
            })).then((function (e) {
                "MAX_OTP_REACHED" === e.statusCode ? (c.output.emit("updateViewModel", {data: {step: "ERROR"}}), c.setError("MAXIMUM_ATTEMPTS_REACHED_FOR_OTP")) : (l = e.prefix, c.output.emit("updateViewModel", {data: {resendingStep: "done"}}))
            })).catch((function (t) {
                if (t && "MFA_REQUIRED_NO_PHONE_NUMBERS" === t.statusCode) {
                    var n = e.state();
                    return n.action.name = "initiateActivationCode", e.state(n)
                }
                e.logger.warn("Exception while calling resendIdentificationCode.", t), c.setError(t, {}, "verifyRecipientsAuthError"), c.output.emit("updateViewModel", {data: {resendingStep: "idle"}})
            })).catch((function (t) {
                e.logger.error("Exception while calling resendIdentificationCode. Error message have not been displayed.", t)
            }))
        }, c.provideIdentificationCode = function () {
            return e.services.authenticationProcessService.getOTPPrefix({reasonCode: c.reasonCode}).then((function (e) {
                if (!e.prefix) return Promise.reject({statusCode: "ACTIVATION_CODE_REQUIRED"});
                l = e.prefix
            })).then((function () {
                var t = e.state();
                return t.action = {name: "verifyActivationCode"}, e.state(t)
            })).catch((function (t) {
                e.logger.warn("Error while calling provideIdentificationCode", t), c.setError(t)
            })).catch((function (t) {
                e.logger.error("Error while calling provideIdentificationCode. Error message may not have been displayed.", t)
            }))
        }, c.optOut = f, c.requestInitiationMessageHelpMessage = f, c.requestIdentificationDeliveryMethodOptionsHelpMessage = f, c.skipBack = f
    }
})),define("common/authenticationProcess/controller/authenticationProcess", ["require", "common/lib/routeUtil", "blue/util", "common/lib/payments/controllerUtils"], (function (e) {
    "use strict";
    var t = e("common/lib/routeUtil"), n = e("blue/util").lang.defaults;
    return function (i) {
        var o, r = this, a = i.areaSettings && i.areaSettings.get("otpConfigs") || {};

        function s() {
            var e = "";
            return "function" == typeof a.contentOverride ? e = a.contentOverride.call(r) : "string" == typeof a.contentOverride && (e = a.contentOverride), e
        }

        function c() {
            for (var e, o, s, c = i.routeToObject(i.routeHistory.lastRoute(0)), l = (o = r.confirmRoute || a.confirmRoute) && i.routeToObject(o), u = (e = r.cancelRoute || a.cancelRoute) && i.routeToObject(e), d = 1; s = i.routeHistory.lastRoute(d); d++) {
                var m = i.routeToObject(s);
                if (t.routeChangeScope(m, c) > 2) {
                    if (l = n(l, m), u = n(u, i.routeToObject(i.routeHistory.lastRoute(d + 1))), hybrid) {
                        var f = s.split("=")[1], p = u.query;
                        p.params = n(p.params, {}), p.params.payeeType = f
                    }
                    break
                }
            }
            return t.routeChangeScope(u, c) <= 2 && (i.logger.error("cancelRoute itself is an OTP route. Resetting cancelRoute to landing page. Continuing with error.", "cancelRoute =", u, "currentRoute =", c), u = i.settings.get("dashboardUrl")), !l || t.routeChangeScope(l, c) <= 2 ? (i.logger.error("confirmRoute either does not exist or itself is an OTP route.", {
                cancelRoute: u,
                confirmRoute: l,
                currentRoute: c
            }), i.state(i.settings.get("dashboardUrl"))) : {currentRoute: c, confirmRoute: l, cancelRoute: u}
        }

        function l(e) {
            var t = r.componentConfigs || a.componentConfigs,
                    n = e && e.target || t.authenticationProcessContainer.target, i = c(), s = i.confirmRoute,
                    l = i.cancelRoute;
            return o.executeComponentAndView("authenticationProcessContainer", {target: n}).then((function () {
                if (t.authenticationProcessHeader) return o.executeComponentAndView("authenticationProcessHeader").then((function (e) {
                    e[0].cancelRoute = l
                }))
            })).then((function () {
                return o.executeComponentAndView("authenticationProcess", (function () {
                    this.registerValidation(["identificationCode", "password", "securityToken"])
                })).then((function (e) {
                    var t = e[0];
                    return t.reset(), t.reasonCode = r.reasonCode || a.reasonCode, t.cancelRoute = l, t.confirmRoute = s, t.auth_otpreason = a.auth_otpreason || 4, t
                }))
            }))
        }

        i.isDirty = function () {
            var e = r.components, t = e && e.authenticationProcess;
            return t && (t.identificationCode || t.password || t.securityToken)
        }, r.init = function () {
            var t = r.componentConfigs || a.componentConfigs;
            if (!t || !t.authenticationProcess || !t.authenticationProcessContainer) return i.logger.error("componentConfigs is not properly configured for authenticationProcess controller");
            t.authenticationProcessHeader || i.logger.info("authenticationProcessHeader is not configured for authenticationProcess controller"), o = e("common/lib/payments/controllerUtils")(r, t), i.on({
                destroyAll: function () {
                    r.registry && r.registry.destroyComponents()
                }
            })
        }, r.initiateActivationCode = function (e) {
            var t = e && e.statusCode, n = o.isComponentAndViewLoaded("authenticationProcessContainer");
            return l(e).then((function () {
                var e = r.components.authenticationProcess;
                return e.addValidation(["identificationCode"]), e.removeValidation(["password", "securityToken"]), e.output.emit("updateViewModel", {
                    data: {
                        showSkipLink: !0,
                        contentOverride: r.contentOverride || s(),
                        authenticationMethod: "ACTIVATION_CODE",
                        step: "ENTER"
                    }
                }), t && e.setError({statusCode: t}), e.display()
            })).then((function () {
                var e = n ? "h2" : "h1";
                r.components.authenticationProcess.output.emit("setFocus", {focus: e})
            })).catch((function (e) {
                i.logger.warn("Error while calling initiateActivationCode", e), r.components.authenticationProcess.setError(e)
            })).catch((function (e) {
                i.logger.error("Error while calling initiateActivationCode. Error message may not have been displayed", e)
            }))
        }, r.verifyActivationCode = function () {
            if (!o.isComponentAndViewLoaded("authenticationProcessContainer")) return i.logger.warn("User took unexpected actions during OTP workflow."), i.state(r.context.settings.get("dashboardUrl"));
            var e = r.components.authenticationProcess;
            e.resetError(), e.output.emit("updateViewModel", {
                data: {
                    authenticationMethod: "ACTIVATION_CODE",
                    contentOverride: "",
                    step: "VERIFY"
                }
            }), e.output.emit("setFocus", {focus: "h2"}), r.components.authenticationProcessHeader.model.set("pageState", "payorOTPActivation")
        }, r.verifySecurityToken = function (e) {
            var t = o.isComponentAndViewLoaded("authenticationProcessContainer");
            return l(e).then((function () {
                var e = r.components.authenticationProcess;
                e.addValidation(["password", "securityToken"]), e.removeValidation(["identificationCode"]), e.output.emit("updateViewModel", {
                    data: {
                        contentOverride: r.contentOverride || s(),
                        authenticationMethod: "SECURITY_TOKEN",
                        step: "VERIFY"
                    }
                })
            })).then((function () {
                var e = t ? "h2" : "h1";
                r.components.authenticationProcess.output.emit("setFocus", {focus: e})
            })).catch((function (e) {
                i.logger.warn("Error while calling verifySecurityToken", e), r.components.authenticationProcess.setError()
            })).catch((function (e) {
                i.logger.error("Error while calling verifySecurityToken. Error message may not have been displayed", e)
            }))
        }
    }
})),define("common/authenticationProcess/service/authenticationProcessService", [], (function () {
    "use strict";

    function e(e) {
        return {settings: {url: e, type: "POST", statusCodeField: "issueCode"}}
    }

    return function () {
        this.serviceCalls = {
            getOTPContactList: e(domainUrl + "/svc/wl/auth/secure/v1/otp/contacts/list"),
            sendOTPRequest: e(domainUrl + "/svc/wl/auth/secure/v1/otp/send"),
            sendOTPRequestV2: e(domainUrl + "/svc/wl/auth/secure/v2/otp/send"),
            sendOTPVerify: e(domainUrl + "/svc/wl/auth/secure/v1/otp/verify"),
            getOTPPrefix: e(domainUrl + "/svc/wl/auth/secure/v1/otp/prefix/list"),
            getUserMetadataList: e(domainUrl + "/svc/rl/accounts/secure/v1/user/metadata/list"),
            authenticationLogin: e(domainUrl + "/auth/fcc/login")
        }
    }
})),define("common/authenticationProcess/service/interceptor/authenticationProcess", [], (function () {
    "use strict";
    return function (e) {
        if ("function" != typeof e) {
            var t = e || {};
            e = function (e) {
                return t[e && e.statusCode]
            }
        }
        return {
            around: function (t) {
                return new Promise((function (n, i) {
                    return t.proceed().then((function (n) {
                        var i = t.target.context, o = e(n || {}, i);
                        return o ? (o && i && i.state && i.state(o), Promise.reject(n)) : n
                    })).then(n, i)
                }))
            }
        }
    }
})),define("common/authenticationProcess/view/spec/authenticationProcess", {
    name: "AUTHENTICATION_PROCESS",
    preventDefault: !0,
    bindings: {
        step: {direction: "BOTH"},
        identificationDeliveryMethodOptions: {},
        identificationCode: {direction: "BOTH"},
        password: {direction: "BOTH"},
        securityToken: {direction: "BOTH"}
    },
    triggers: {
        validateActivationCode: {action: "view.validateActivationCode"},
        changeContact: {action: "view.changeContact"},
        verifyTask: {action: "view.verifyTask"},
        provideIdentificationCode: {action: "view.provideIdentificationCode"},
        formFieldTracking: {action: "formField"}
    }
}),define("common/template/authenticationProcess/authenticationProcess", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "div",
            f: [{
                t: 4,
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "row common alertHeaderContainer", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "col-lg-12 col-md-12 col-sm-12 col-xs-12", t: 13}],
                        f: [{
                            t: 7,
                            e: "blueAlert",
                            m: [{n: "id", f: "authenticationProcessErrorHeader", t: 13}, {
                                n: "type",
                                f: "inverted error",
                                t: 13
                            }, {n: "primary", f: "true", t: 13}, {
                                n: "icon",
                                f: "exclamation-color error",
                                t: 13
                            }, {n: "title", f: [{t: 2, r: "authenticationProcessErrorHeader"}], t: 13}, {
                                n: "message",
                                f: [{t: 2, r: "authenticationProcessErrorAdvisory"}],
                                t: 13
                            }, {n: "classes", f: "common opacity-solid", t: 13}, {
                                n: "accessibleTextIcon",
                                f: [{t: 2, r: "importantAda", s: !0}],
                                t: 13
                            }, {n: "focusOnRender", f: "true", t: 13}]
                        }]
                    }]
                }],
                n: 50,
                r: "authenticationProcessErrorHeader"
            }, " ", {t: 8, r: "authenticationProcessMessage"}, " ", {
                t: 4,
                f: [{t: 8, r: "activationCodeEnter"}],
                n: 50,
                x: {r: ["step"], s: '_0==="ENTER"'}
            }, {
                t: 4,
                n: 50,
                f: [{t: 8, r: "activationCodeVerify"}],
                x: {r: ["step", "authenticationMethod"], s: '_0==="VERIFY"&&_1==="ACTIVATION_CODE"'},
                l: 1
            }, {
                t: 4,
                n: 50,
                f: [" ", {t: 8, r: "securityToken"}],
                x: {r: ["step", "authenticationMethod"], s: '_0==="VERIFY"&&_1==="SECURITY_TOKEN"'},
                l: 1
            }, {
                t: 4,
                n: 50,
                f: [" ", {
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-lg-12 util right aligned", t: 13}],
                    f: [{
                        t: 7,
                        e: "mds-button",
                        m: [{n: "id", f: "exitAuthenticationProcessBtn", t: 13}, {
                            n: "text",
                            f: [{t: 2, r: "~/okLabel"}],
                            t: 13
                        }, {n: "click", f: "exitAuthenticationProcess", t: 70}]
                    }]
                }],
                x: {r: ["step"], s: '_0==="ERROR"'},
                l: 1
            }]
        }], e: {}
    }
})),define("common/template/authenticationProcess/authenticationProcessMessage", [], (function () {
    return {
        v: 4,
        t: [{
            t: 4,
            f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: "row common formHeader", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-lg-10 col-md-10 col-sm-12 col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "h2",
                        m: [{n: "id", f: "authenticationProcessHeaderId", t: 13}, {
                            n: "tabindex",
                            f: "-1",
                            t: 13
                        }, {n: "class", f: "H2", t: 13}],
                        f: [{
                            t: 3,
                            x: {
                                r: ["sanitizer", "variation", "contentOverride"],
                                s: '_0.sanitizeHTML(_1("authenticationProcessHeader",_2))'
                            }
                        }]
                    }]
                }]
            }, " ", {
                t: 7,
                e: "div",
                m: [{n: "class", f: "row common formHeader", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-lg-10 col-md-10 col-sm-12 col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "BODY", t: 13}],
                        f: [{
                            t: 3,
                            x: {
                                r: ["sanitizer", "variation", "contentOverride"],
                                s: '_0.sanitizeHTML(_1("authenticationProcessMessage",_2))'
                            }
                        }]
                    }]
                }]
            }],
            n: 50,
            x: {r: ["step"], s: '_0==="ENTER"'}
        }, {
            t: 4,
            n: 50,
            f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: "row common formHeader", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-lg-10 col-md-10 col-sm-12 col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "h2",
                        m: [{n: "id", f: "verifyRecipientsAuthHeader", t: 13}, {
                            n: "tabindex",
                            f: "-1",
                            t: 13
                        }, {n: "class", f: "H2", t: 13}],
                        f: [{
                            t: 2,
                            x: {
                                r: ["variation", "contentOverride", "authenticationMethod"],
                                s: '_0("verificationHeader",_1||_2)'
                            }
                        }]
                    }]
                }]
            }, " ", {
                t: 7,
                e: "div",
                m: [{n: "class", f: "row common formHeader", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-lg-10 col-md-10 col-sm-12 col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "BODY", t: 13}],
                        f: [{
                            t: 2,
                            x: {
                                r: ["variation", "contentOverride", "authenticationMethod"],
                                s: '_0("verificationAdvisory",_1||_2)'
                            }
                        }]
                    }]
                }]
            }],
            x: {r: ["step"], s: '_0==="VERIFY"'},
            l: 1
        }],
        e: {}
    }
})),define("common/template/authenticationProcess/activationCodeEnter", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", m: [{n: "class", f: "content-section", t: 13}], f: [{
                t: 7, e: "div", m: [{n: "class", f: "section", t: 13}], f: [{
                    t: 7,
                    e: "form",
                    m: [{n: "role", f: "form", t: 13}, {
                        n: "id",
                        f: "initiateRecipientAuthentication",
                        t: 13
                    }, {
                        n: "class",
                        f: "util print-background-none recipientAuthentication-container",
                        t: 13
                    }, {n: "submit", f: "verifyTask", t: 70}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "row common formHeader", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-sm-3 col-xs-12", t: 13}],
                            f: [{
                                t: 7,
                                e: "span",
                                m: [{n: "class", f: "data-value-label left DATABOLD", t: 13}],
                                f: [{t: 2, r: "identificationDeliveryMethodOptionsLabel"}]
                            }, " ", {
                                t: 7,
                                e: "blueTooltip",
                                m: [{n: "id", f: "identificationDeliveryMethodOptionsAda", t: 13}, {
                                    n: "content",
                                    f: [{t: 2, r: "identificationDeliveryMethodOptionsHelpMessage", s: !0}],
                                    t: 13
                                }, {
                                    n: "adaOpenText",
                                    f: [{t: 2, r: "requestIdentificationDeliveryMethodOptionsHelpMessageAda", s: !0}],
                                    t: 13
                                }, {
                                    n: "adaBeginText",
                                    f: [{t: 2, r: "beginHelpMessageAda", s: !0}],
                                    t: 13
                                }, {
                                    n: "adaCloseText",
                                    f: [{t: 2, r: "exitHelpMessageAda", s: !0}],
                                    t: 13
                                }, {n: "adaEndText", f: [{t: 2, r: "endHelpMessageAda", s: !0}], t: 13}, {
                                    n: "rClick",
                                    f: "requestIdentificationDeliveryMethodOptionsHelpMessage",
                                    t: 13
                                }]
                            }]
                        }]
                    }, " ", {
                        t: 4, f: [{
                            t: 7, e: "div", m: [{n: "class", f: "row", t: 13}], f: [{
                                t: 7,
                                e: "fieldset",
                                m: [{n: "class", f: "fieldset", t: 13}, {
                                    n: "id",
                                    f: ["initiateRecipientsAuthenticationFieldSet-", {t: 2, r: "i"}],
                                    t: 13
                                }],
                                f: [{
                                    t: 7,
                                    e: "legend",
                                    m: [{n: "class", f: "col-xs-12 col-sm-2 radiobuttonLabel", t: 13}],
                                    f: [{
                                        t: 7,
                                        e: "blueFieldLabel",
                                        m: [{
                                            n: "id",
                                            f: ["identificationDeliveryMethodOptionId-", {
                                                t: 2,
                                                r: "identificationDeliveryMethodOptionId"
                                            }],
                                            t: 13
                                        }, {
                                            n: "label",
                                            f: [{
                                                t: 2,
                                                x: {
                                                    r: ["formatPhoneMask", "identificationDeliveryMethodOptionName"],
                                                    s: "_0(_1)"
                                                }
                                            }],
                                            t: 13
                                        }]
                                    }]
                                }, " ", {
                                    t: 4,
                                    f: [{
                                        t: 7,
                                        e: "div",
                                        m: [{n: "class", f: "col-xs-4 col-sm-2", t: 13}],
                                        f: [{
                                            t: 7,
                                            e: "blueRadiobutton",
                                            m: [{
                                                n: "analyticsId",
                                                f: ["identificationDeliveryMethodOptions-", {
                                                    t: 2,
                                                    r: "identificationDeliveryMethodOptionId"
                                                }, "_SMS"],
                                                t: 13
                                            }, {
                                                n: "id",
                                                f: ["requestRecipientActivationBySMS-", {
                                                    t: 2,
                                                    r: "identificationDeliveryMethodOptionId"
                                                }],
                                                t: 13
                                            }, {
                                                n: "groupName",
                                                f: "identificationDeliveryMethodOptions",
                                                t: 13
                                            }, {n: "value", f: "SMS", t: 13}, {
                                                n: "accessibleText",
                                                f: [{
                                                    t: 2,
                                                    x: {
                                                        r: ["formatPhoneMask", "identificationDeliveryMethodOptionName"],
                                                        s: "_0(_1)"
                                                    }
                                                }],
                                                t: 13
                                            }, {
                                                n: "label",
                                                f: [{
                                                    t: 2,
                                                    x: {
                                                        r: ["variation"],
                                                        s: '_0("identificationDeliveryMethodOptionName","TEXT")'
                                                    },
                                                    s: !0
                                                }],
                                                t: 13
                                            }, {
                                                n: "contactId",
                                                f: [{t: 2, r: "identificationDeliveryMethodOptionId"}],
                                                t: 13
                                            }, {
                                                n: "rChange",
                                                f: "['validateActivationCode', 'formFieldTracking','changeContact']",
                                                t: 13
                                            }, {n: "rFocus", f: "['formFieldTracking']", t: 13}]
                                        }]
                                    }],
                                    n: 50,
                                    r: "textEnabled"
                                }, " ", {
                                    t: 7,
                                    e: "div",
                                    m: [{n: "class", f: "col-xs-4 col-sm-2", t: 13}],
                                    f: [{
                                        t: 7,
                                        e: "blueRadiobutton",
                                        m: [{
                                            n: "analyticsId",
                                            f: ["identificationDeliveryMethodOptions-", {
                                                t: 2,
                                                r: "identificationDeliveryMethodOptionId"
                                            }, "_VOICE"],
                                            t: 13
                                        }, {
                                            n: "id",
                                            f: ["requestRecipientActivationByVOICE-", {
                                                t: 2,
                                                r: "identificationDeliveryMethodOptionId"
                                            }],
                                            t: 13
                                        }, {
                                            n: "groupName",
                                            f: "identificationDeliveryMethodOptions",
                                            t: 13
                                        }, {n: "value", f: "VOICE", t: 13}, {
                                            n: "accessibleText",
                                            f: [{
                                                t: 2,
                                                x: {
                                                    r: ["formatPhoneMask", "identificationDeliveryMethodOptionName"],
                                                    s: "_0(_1)"
                                                }
                                            }],
                                            t: 13
                                        }, {
                                            n: "label",
                                            f: [{
                                                t: 2,
                                                x: {
                                                    r: ["variation"],
                                                    s: '_0("identificationDeliveryMethodOptionName","VOICE")'
                                                },
                                                s: !0
                                            }],
                                            t: 13
                                        }, {
                                            n: "contactId",
                                            f: [{t: 2, r: "identificationDeliveryMethodOptionId"}],
                                            t: 13
                                        }, {
                                            n: "rChange",
                                            f: "['validateActivationCode', 'formFieldTracking','changeContact']",
                                            t: 13
                                        }, {n: "rFocus", f: "['formFieldTracking']", t: 13}]
                                    }]
                                }]
                            }]
                        }], n: 52, i: "i", r: "identificationDeliveryMethodOptions"
                    }, " ", {
                        t: 4,
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "row common alertHeaderContainer", t: 13}],
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "col-lg-12 col-md-12 col-sm-12 col-xs-12", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "blueAlert",
                                    m: [{n: "id", f: "identificationDeliveryMethodOptionsError", t: 13}, {
                                        n: "type",
                                        f: "inverted error",
                                        t: 13
                                    }, {n: "primary", f: "true", t: 13}, {
                                        n: "icon",
                                        f: "exclamation-color error",
                                        t: 13
                                    }, {
                                        n: "title",
                                        f: [{t: 2, r: "identificationDeliveryMethodOptionsError"}],
                                        t: 13
                                    }, {
                                        n: "message",
                                        f: [{t: 2, r: "identificationDeliveryMethodOptionsAdvisory"}],
                                        t: 13
                                    }, {n: "classes", f: "common opacity-solid", t: 13}, {
                                        n: "accessibleTextIcon",
                                        f: [{t: 2, r: "importantAda", s: !0}],
                                        t: 13
                                    }, {n: "focusOnRender", f: "true", t: 13}]
                                }]
                            }]
                        }],
                        n: 50,
                        r: "identificationDeliveryMethodOptionsError"
                    }, " ", {
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "row", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-xs-12 common formHeader", t: 13}],
                            f: [{
                                t: 7,
                                e: "blueLink",
                                m: [{n: "id", f: "provideIdentificationCode", t: 13}, {
                                    n: "content",
                                    f: [{
                                        t: 3,
                                        x: {
                                            r: ["sanitizer", "provideIdentificationCodeMessage"],
                                            s: "_0.sanitizeHTML(_1)"
                                        }
                                    }],
                                    t: 13
                                }, {n: "rClick", f: "provideIdentificationCode", t: 13}, {
                                    n: "endIcon",
                                    f: "progressright",
                                    t: 13
                                }]
                            }]
                        }, " "]
                    }, " ", {
                        t: 7,
                        e: "footer",
                        m: [{n: "class", f: "row", t: 13}, {n: "role", f: "presentation", t: 13}],
                        f: [{
                            t: 4,
                            f: [{
                                t: 7,
                                e: "buttonFooterCollection",
                                m: [{
                                    n: "primaryButton",
                                    f: [{
                                        t: 2,
                                        x: {
                                            r: ["nextLabel", "showSkipLink"],
                                            s: '{id:"verifyTask",label:_0,type:"submit",disabled:_1,classes:"fluid primary",containerColumnClasses:"col-xs-12 col-sm-3"}'
                                        }
                                    }],
                                    t: 13
                                }, {
                                    n: "secondaryButtons",
                                    f: [{
                                        t: 2,
                                        x: {
                                            r: ["cancelLabel"],
                                            s: '[{type:"button",id:"cancelTask",label:_0,rClick:"cancelTask",classes:"fluid secondary",containerColumnClasses:"col-sm-offset-6 col-xs-12 col-sm-3"}]'
                                        }
                                    }],
                                    t: 13
                                }, {n: "showSkipLink", f: [{t: 2, r: "showSkipLink"}], t: 13}, {
                                    n: "skipLink",
                                    f: [{
                                        t: 2,
                                        x: {
                                            r: ["skipBackLabel"],
                                            s: '{skipContainerID:"blueSkiplinkContainer",skipID:"skipLinkId",skipSelector:"form input",skipLabel:_0,skiprKeydown:"skipBack",skipClasses:"form-skipLink"}'
                                        }
                                    }],
                                    t: 13
                                }]
                            }],
                            n: 50,
                            r: "identificationDeliveryMethodOptions"
                        }, {
                            t: 4,
                            n: 51,
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "col-sm-offset-9 col-xs-12 col-sm-3", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "blueButton",
                                    m: [{n: "id", f: "cancelTask", t: 13}, {
                                        n: "label",
                                        f: [{t: 2, r: "cancelLabel", s: !0}],
                                        t: 13
                                    }, {n: "classes", f: "primary fluid", t: 13}, {
                                        n: "rClick",
                                        f: "cancelTask",
                                        t: 13
                                    }, {
                                        n: "adatext",
                                        f: [{
                                            t: 2,
                                            x: {r: ["variation", "contentOverride"], s: '_0("exitAda",_1)'},
                                            s: !0
                                        }],
                                        t: 13
                                    }]
                                }]
                            }],
                            l: 1
                        }]
                    }]
                }]
            }]
        }], e: {}
    }
})),define("common/template/authenticationProcess/activationCodeVerify", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", m: [{n: "class", f: "content-section", t: 13}], f: [{
                t: 7, e: "div", m: [{n: "class", f: "section", t: 13}], f: [{
                    t: 7,
                    e: "form",
                    m: [{n: "class", f: "common form", t: 13}, {n: "role", f: "form", t: 13}, {
                        n: "id",
                        f: "send-sec-code-form",
                        t: 13
                    }, {n: "novalidate", f: 0, t: 13}, {n: "submit", f: "confirmTask", t: 70}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "row", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{
                                n: "class",
                                f: "col-sm-3 col-sm-offset-1 col-xs-12 activationCodeLabel field center",
                                t: 13
                            }],
                            f: [{
                                t: 7,
                                e: "blueFieldLabel",
                                m: [{n: "id", f: "identificationCodeLabel", t: 13}, {
                                    n: "inputId",
                                    f: "identificationCode-input-field",
                                    t: 13
                                }, {
                                    n: "label",
                                    f: [{t: 2, r: "identificationCodeLabel", s: !0}],
                                    t: 13
                                }, {
                                    n: "errorLabelAda",
                                    f: [{t: 2, r: "errorAnnouncementAda", s: !0}],
                                    t: 13
                                }, {
                                    n: "classes",
                                    f: [{t: 4, f: ["clientSideError"], n: 50, r: "authenticationProcessErrorHeader"}],
                                    t: 13
                                }]
                            }]
                        }, " ", {
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-sm-4 col-xs-12", t: 13}],
                            f: [{
                                t: 7,
                                e: "blueInput",
                                m: [{n: "id", f: "identificationCode", t: 13}, {
                                    n: "analyticsId",
                                    f: "identificationCode",
                                    t: 13
                                }, {n: "classes", f: "center column", t: 13}, {
                                    n: "showErrorHighlighting",
                                    f: [{t: 2, x: {r: ["authenticationProcessErrorHeader"], s: "!!_0"}}],
                                    t: 13
                                }, {n: "name", f: "identificationCode", t: 13}, {
                                    n: "maxLength",
                                    f: "8",
                                    t: 13
                                }, {n: "autocomplete", f: "off", t: 13}, {
                                    n: "value",
                                    f: [{t: 2, r: "identificationCode"}],
                                    t: 13
                                }, {n: "required", f: "true", t: 13}, {
                                    n: "errorMessage",
                                    f: [{t: 2, r: "identificationCodeError"}],
                                    t: 13
                                }, {n: "addValidation", f: "true", t: 13}, {
                                    n: "rErrorBubbleFocus",
                                    f: "validationEngineErrorFocus",
                                    t: 13
                                }, {n: "validate", f: "identificationCode", t: 13}, {
                                    n: "rFocus",
                                    f: "formFieldTracking",
                                    t: 13
                                }, {n: "rChange", f: "formFieldTracking", t: 13}, {
                                    n: "labelErrorPrefix",
                                    f: [{t: 2, r: "errorAnnouncementAda"}],
                                    t: 13
                                }, {n: ",", f: 0, t: 13}]
                            }]
                        }, " ", {
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-sm-4 col-xs-12", t: 13}],
                            f: [{
                                t: 4,
                                f: [{
                                    t: 7,
                                    e: "blueLink",
                                    m: [{n: "id", f: "resendActivationCodeLink", t: 13}, {
                                        n: "content",
                                        f: [{t: 2, r: "resendIdentificationCodeLabel", s: !0}],
                                        t: 13
                                    }, {n: "rClick", f: "resendIdentificationCode", t: 13}, {
                                        n: "endIcon",
                                        f: "progressright",
                                        t: 13
                                    }, {n: "classes", f: "resendActivationLink", t: 13}]
                                }],
                                n: 50,
                                x: {r: ["resendingStep"], s: '_0==="idle"'}
                            }, {
                                t: 4,
                                n: 50,
                                f: [{
                                    t: 7,
                                    e: "div",
                                    m: [{n: "id", f: "resendActivationCodeProgress", t: 13}, {
                                        n: "tabindex",
                                        f: "-1",
                                        t: 13
                                    }],
                                    f: [{t: 2, r: "verificationCodeSendingLabel"}]
                                }],
                                x: {r: ["resendingStep"], s: '_0==="start"'},
                                l: 1
                            }, {
                                t: 4,
                                n: 50,
                                f: [" ", {
                                    t: 7,
                                    e: "blueSpinner",
                                    m: [{n: "id", f: "resendActivationCodeProgress", t: 13}, {
                                        n: "inline",
                                        f: "true",
                                        t: 13
                                    }, {n: "accessibleText", f: [{t: 2, r: "waitAda"}], t: 13}]
                                }],
                                x: {r: ["resendingStep"], s: '_0==="waiting"'},
                                l: 1
                            }, {
                                t: 4,
                                n: 50,
                                f: [" ", {
                                    t: 7,
                                    e: "div",
                                    m: [{n: "id", f: "resendActivationCodeProgress", t: 13}, {
                                        n: "tabindex",
                                        f: "-1",
                                        t: 13
                                    }, {n: "style", f: "float:left", t: 13}],
                                    f: [{
                                        t: 7,
                                        e: "blueIconWrap",
                                        m: [{n: "type", f: "checkmark", t: 13}, {
                                            n: "adatext",
                                            f: [{t: 2, r: "checkmarkAda"}],
                                            t: 13
                                        }]
                                    }, " ", {t: 2, r: "verificationCodeSentLabel"}]
                                }],
                                x: {r: ["resendingStep"], s: '_0==="done"'},
                                l: 1
                            }]
                        }]
                    }, " ", {
                        t: 7,
                        e: "footer",
                        m: [{n: "class", f: "row", t: 13}, {n: "role", f: "presentation", t: 13}],
                        f: [{
                            t: 7,
                            e: "buttonFooterCollection",
                            m: [{
                                n: "primaryButton",
                                f: [{
                                    t: 2,
                                    x: {
                                        r: ["nextLabel", "showSkipLink"],
                                        s: '{id:"nextBtn",label:_0,type:"submit",classes:"fluid primary",disabled:_1,containerColumnClasses:"col-xs-12 col-sm-3"}'
                                    }
                                }],
                                t: 13
                            }, {
                                n: "secondaryButtons",
                                f: [{
                                    t: 2,
                                    x: {
                                        r: ["cancelLabel", "backLabel"],
                                        s: '[{type:"button",id:"cancelBtn",label:_0,rClick:"cancelTask",classes:"fluid secondary",containerColumnClasses:"col-xs-12 col-sm-3"},{type:"button",id:"backBtn",label:_1,rClick:"initiateTask",classes:"fluid secondary",containerColumnClasses:"col-sm-3 col-sm-offset-3 col-xs-12"}]'
                                    }
                                }],
                                t: 13
                            }, {n: "showSkipLink", f: [{t: 2, r: "showSkipLink"}], t: 13}, {
                                n: "skipLink",
                                f: [{
                                    t: 2,
                                    x: {
                                        r: ["skipBackLabel"],
                                        s: '{skipContainerID:"blueSkiplinkContainer",skipID:"skipLinkId",skipSelector:"form input",skipLabel:_0,skiprKeydown:"skipBack",skiprClick:"skipBack",skipClasses:"form-skipLink"}'
                                    }
                                }],
                                t: 13
                            }]
                        }]
                    }]
                }]
            }]
        }], e: {}
    }
})),define("common/template/authenticationProcess/securityToken", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "form",
            m: [{n: "id", f: "rsaTokenForm", t: 13}, {n: "submit", f: "confirmTask", t: 70}, {
                n: "autocomplete",
                f: "off",
                t: 13
            }, {n: "novalidate", f: 0, t: 13}, {n: "class", f: "common form", t: 13}],
            f: [{
                t: 7,
                e: "blueFieldGroup",
                m: [{n: "id", f: "password", t: 13}, {n: "groupType", f: "textbox horizontal", t: 13}, {
                    n: "label",
                    f: [{t: 2, r: "passwordLabel", s: !0}],
                    t: 13
                }, {n: "inputValue", f: [{t: 2, r: "password"}], t: 13}, {
                    n: "input",
                    f: [{
                        t: 2,
                        x: {
                            r: ["passwordError"],
                            s: '{name:"password",type:"password",rBlur:["formFieldTracking"],required:"true",errorMessage:_0,addValidation:true,rErrorBubbleFocus:"validationEngineErrorFocus",validate:"password",rFocus:"formFieldTracking",rChange:"formFieldTracking"}'
                        }
                    }],
                    t: 13
                }, {n: "inputColumns", f: "col-xs-12 col-sm-6", t: 13}, {
                    n: "labelColumns",
                    f: "col-xs-12 col-sm-4",
                    t: 13
                }]
            }, " ", {
                t: 7,
                e: "blueFieldGroup",
                m: [{n: "id", f: "securityToken", t: 13}, {n: "groupType", f: "textbox horizontal", t: 13}, {
                    n: "label",
                    f: [{t: 2, r: "securityTokenLabel", s: !0}],
                    t: 13
                }, {n: "inputValue", f: [{t: 2, r: "securityToken"}], t: 13}, {
                    n: "input",
                    f: [{
                        t: 2,
                        x: {
                            r: ["securityTokenError"],
                            s: '{name:"securityToken",type:"text",maxLength:"6",rBlur:["formFieldTracking"],rFocus:"formFieldTracking",rChange:"formFieldTracking",required:"true",errorMessage:_0,addValidation:true,rErrorBubbleFocus:"validationEngineErrorFocus",validate:"securityToken"}'
                        }
                    }],
                    t: 13
                }, {n: "inputColumns", f: "col-xs-12 col-sm-6", t: 13}, {
                    n: "labelColumns",
                    f: "col-xs-12 col-sm-4",
                    t: 13
                }]
            }, " ", {
                t: 7,
                e: "footer",
                m: [{n: "class", f: "row", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{
                        n: "class",
                        f: "col-lg-offset-4 col-md-offset-4 col-sm-offset-5 col-md-2 col-sm-3 col-xs-12 hide-xs show-sm",
                        t: 13
                    }],
                    f: [{
                        t: 7,
                        e: "blueButton",
                        m: [{n: "id", f: "cancelBtn", t: 13}, {
                            n: "label",
                            f: [{t: 2, r: "cancelLabel", s: !0}],
                            t: 13
                        }, {n: "rClick", f: "cancelTask", t: 13}, {
                            n: "classes",
                            f: "fluid secondary",
                            t: 13
                        }, {
                            n: "adatext",
                            f: [{t: 2, x: {r: ["variation", "contentOverride"], s: '_0("exitAda",_1)'}, s: !0}],
                            t: 13
                        }]
                    }]
                }, " ", {
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-md-2 col-sm-3 col-xs-12", t: 13}],
                    f: [{
                        t: 4,
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "id", f: "blueSkiplinkContainer", t: 13}],
                            f: [{
                                t: 7,
                                e: "blueSkiplink",
                                m: [{n: "id", f: "skipLinkId", t: 13}, {
                                    n: "skipSelector",
                                    f: "form input",
                                    t: 13
                                }, {n: "label", f: [{t: 2, r: "skipBackLabel"}], t: 13}, {
                                    n: "rKeydown",
                                    f: "skipBack",
                                    t: 13
                                }]
                            }]
                        }],
                        n: 50,
                        r: "showSkipLink"
                    }, " ", {
                        t: 7,
                        e: "blueButton",
                        m: [{n: "id", f: "btnNext", t: 13}, {
                            n: "label",
                            f: [{t: 2, r: "nextLabel", s: !0}],
                            t: 13
                        }, {n: "classes", f: "fluid primary", t: 13}, {n: "type", f: "submit", t: 13}, {
                            n: "disabled",
                            f: [{t: 2, r: "showSkipLink"}],
                            t: 13
                        }]
                    }, " "]
                }, " ", {
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12 show-xs hide-sm", t: 13}],
                    f: [{
                        t: 7,
                        e: "blueButton",
                        m: [{n: "id", f: "cancelBtn", t: 13}, {
                            n: "label",
                            f: [{t: 2, r: "cancelLabel", s: !0}],
                            t: 13
                        }, {n: "rClick", f: "cancelTask", t: 13}, {
                            n: "classes",
                            f: "fluid secondary",
                            t: 13
                        }, {
                            n: "adatext",
                            f: [{t: 2, x: {r: ["variation", "contentOverride"], s: '_0("exitAda",_1)'}, s: !0}],
                            t: 13
                        }]
                    }]
                }]
            }]
        }], e: {}
    }
})),define("common/template/authenticationProcess/hybrid/authenticationProcess", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "div",
            f: [{
                t: 4,
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "row", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "col-xs-12 otp-header", t: 13}],
                        f: [{
                            t: 7,
                            e: "blueAlert",
                            m: [{n: "id", f: "authenticationProcessErrorHeader", t: 13}, {
                                n: "type",
                                f: "inverted error",
                                t: 13
                            }, {n: "primary", f: "true", t: 13}, {
                                n: "icon",
                                f: "exclamation-color error",
                                t: 13
                            }, {n: "title", f: [{t: 2, r: ".authenticationProcessErrorHeader"}], t: 13}, {
                                n: "message",
                                f: [{t: 2, r: ".authenticationProcessErrorAdvisory"}],
                                t: 13
                            }, {n: "classes", f: "common opacity-solid", t: 13}, {
                                n: "accessibleTextIcon",
                                f: [{t: 2, r: ".importantAda", s: !0}],
                                t: 13
                            }, {n: "focusOnRender", f: "true", t: 13}]
                        }]
                    }]
                }],
                n: 50,
                r: ".authenticationProcessErrorHeader"
            }, " ", {t: 8, r: "authenticationProcessMessage"}, " ", {
                t: 4,
                f: [{t: 8, r: "activationCodeEnter"}],
                n: 50,
                x: {r: [".step"], s: '_0==="ENTER"'}
            }, {
                t: 4,
                n: 50,
                f: [{t: 8, r: "activationCodeVerify"}],
                x: {r: [".step", ".authenticationMethod"], s: '_0==="VERIFY"&&_1==="ACTIVATION_CODE"'},
                l: 1
            }, {
                t: 4,
                n: 50,
                f: [" ", {t: 8, r: "securityToken"}],
                x: {r: [".step", ".authenticationMethod"], s: '_0==="VERIFY"&&_1==="SECURITY_TOKEN"'},
                l: 1
            }]
        }], e: {}
    }
})),define("common/template/authenticationProcess/hybrid/authenticationProcessMessage", [], (function () {
    return {
        v: 4,
        t: [{
            t: 4,
            f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: "row", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "h2",
                        m: [{n: "tabindex", f: "-1", t: 13}, {n: "class", f: "H3R otp-header", t: 13}],
                        f: [{
                            t: 3,
                            x: {
                                r: ["sanitizer", "variation", "contentOverride"],
                                s: '_0.sanitizeHTML(_1("authenticationProcessHeader",_2))'
                            }
                        }]
                    }]
                }]
            }, " ", {
                t: 7,
                e: "div",
                m: [{n: "class", f: "row", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "DATALABELH otp-header", t: 13}],
                        f: [{
                            t: 3,
                            x: {
                                r: ["sanitizer", "variation", "contentOverride"],
                                s: '_0.sanitizeHTML(_1("authenticationProcessMessage",_2))'
                            }
                        }]
                    }]
                }]
            }],
            n: 50,
            x: {r: ["step"], s: '_0==="ENTER"'}
        }, {
            t: 4,
            n: 50,
            f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: "row", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "h2",
                        m: [{n: "tabindex", f: "-1", t: 13}, {n: "class", f: "H3R otp-header", t: 13}],
                        f: [{
                            t: 2,
                            x: {
                                r: ["variation", "contentOverride", "authenticationMethod"],
                                s: '_0("verificationHeader",_1||_2)'
                            }
                        }]
                    }]
                }]
            }],
            x: {r: ["step", "authenticationMethod"], s: '_0==="VERIFY"&&_1==="ACTIVATION_CODE"'},
            l: 1
        }, {
            t: 4,
            n: 50,
            f: [" ", {
                t: 7,
                e: "div",
                m: [{n: "class", f: "row", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "h2",
                        m: [{n: "tabindex", f: "-1", t: 13}, {n: "class", f: "H3R otp-header", t: 13}],
                        f: [{
                            t: 2,
                            x: {
                                r: ["variation", "contentOverride", "authenticationMethod"],
                                s: '_0("verificationHeader",_1||_2)'
                            }
                        }]
                    }]
                }]
            }, " ", {
                t: 7,
                e: "div",
                m: [{n: "class", f: "row", t: 13}],
                f: [{
                    t: 7,
                    e: "div",
                    m: [{n: "class", f: "col-xs-12", t: 13}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "PRIMARYLABEL otp-header", t: 13}],
                        f: [{
                            t: 2,
                            x: {
                                r: ["variation", "contentOverride", "authenticationMethod"],
                                s: '_0("verificationAdvisory",_1||_2)'
                            }
                        }]
                    }]
                }]
            }],
            x: {r: ["step", "authenticationMethod"], s: '_0==="VERIFY"&&_1==="SECURITY_TOKEN"'},
            l: 1
        }],
        e: {}
    }
})),define("common/template/authenticationProcess/hybrid/activationCodeEnter", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", m: [{n: "class", f: "content-section", t: 13}], f: [{
                t: 7, e: "div", m: [{n: "class", f: "section", t: 13}], f: [{
                    t: 7,
                    e: "form",
                    m: [{n: "role", f: "form", t: 13}, {n: "id", f: "otpAuthenticationGeneration", t: 13}, {
                        n: "submit",
                        f: "verifyTask",
                        t: 70
                    }],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "row", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-xs-12 otp-header otp-options", t: 13}],
                            f: [{
                                t: 7,
                                e: "span",
                                m: [{n: "class", f: "BODYLABEL", t: 13}],
                                f: [{t: 2, r: ".identificationDeliveryMethodOptionsLabel", s: !0}]
                            }, " ", {
                                t: 7,
                                e: "blueTooltip",
                                m: [{n: "id", f: "identificationDeliveryMethod", t: 13}, {
                                    n: "content",
                                    f: [{t: 2, r: ".identificationDeliveryMethodOptionsHelpMessage", s: !0}],
                                    t: 13
                                }, {
                                    n: "adaOpenText",
                                    f: [{t: 2, r: ".requestIdentificationDeliveryMethodOptionsHelpMessageAda", s: !0}],
                                    t: 13
                                }, {
                                    n: "adaBeginText",
                                    f: [{t: 2, r: ".beginHelpMessageAda", s: !0}],
                                    t: 13
                                }, {
                                    n: "adaCloseText",
                                    f: [{t: 2, r: ".exitHelpMessageAda", s: !0}],
                                    t: 13
                                }, {n: "adaEndText", f: [{t: 2, r: ".endHelpMessageAda", s: !0}], t: 13}, {
                                    n: "rClick",
                                    f: "requestIdentificationDeliveryMethodOptionsHelpMessage",
                                    t: 13
                                }]
                            }]
                        }]
                    }, " ", {
                        t: 4, f: [{
                            t: 7, e: "div", m: [{n: "class", f: "row", t: 13}], f: [{
                                t: 7,
                                e: "fieldset",
                                m: [{n: "class", f: "fieldset", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "div",
                                    m: [{n: "class", f: "col-xs-4", t: 13}],
                                    f: [{
                                        t: 7,
                                        e: "label",
                                        m: [{n: "class", f: "DATA", t: 13}],
                                        f: [{
                                            t: 2,
                                            x: {
                                                r: ["formatPhoneMask", "./identificationDeliveryMethodOptionName"],
                                                s: "_0(_1)"
                                            }
                                        }]
                                    }]
                                }, " ", {
                                    t: 4,
                                    f: [{
                                        t: 7,
                                        e: "div",
                                        m: [{n: "class", f: "col-xs-4", t: 13}],
                                        f: [{
                                            t: 7,
                                            e: "blueRadiobutton",
                                            m: [{
                                                n: "analyticsId",
                                                f: ["identificationDeliveryMethodOptions-", {t: 2, r: "i"}],
                                                t: 13
                                            }, {
                                                n: "id",
                                                f: ["requestRecipientActivationBySMS-", {
                                                    t: 2,
                                                    r: "identificationDeliveryMethodOptionId"
                                                }],
                                                t: 13
                                            }, {
                                                n: "groupName",
                                                f: "identificationDeliveryMethodOptions",
                                                t: 13
                                            }, {n: "value", f: "SMS", t: 13}, {
                                                n: "accessibleText",
                                                f: [{
                                                    t: 2,
                                                    x: {
                                                        r: ["formatPhoneMask", "identificationDeliveryMethodOptionName"],
                                                        s: "_0(_1)"
                                                    }
                                                }],
                                                t: 13
                                            }, {
                                                n: "label",
                                                f: [{
                                                    t: 2,
                                                    x: {
                                                        r: ["variation"],
                                                        s: '_0("identificationDeliveryMethodOptionName","TEXT")'
                                                    },
                                                    s: !0
                                                }],
                                                t: 13
                                            }, {
                                                n: "contactId",
                                                f: [{t: 2, r: "identificationDeliveryMethodOptionId"}],
                                                t: 13
                                            }, {
                                                n: "rChange",
                                                f: "['validateActivationCode', 'formFieldTracking','changeContact']",
                                                t: 13
                                            }, {n: "rFocus", f: "['formFieldTracking']", t: 13}, {
                                                n: "classes",
                                                f: "DATA",
                                                t: 13
                                            }]
                                        }]
                                    }],
                                    n: 50,
                                    r: "./textEnabled"
                                }, " ", {
                                    t: 7,
                                    e: "div",
                                    m: [{n: "class", f: "col-xs-4", t: 13}],
                                    f: [{
                                        t: 7,
                                        e: "blueRadiobutton",
                                        m: [{
                                            n: "analyticsId",
                                            f: ["identificationDeliveryMethodOptions-", {t: 2, r: "i"}],
                                            t: 13
                                        }, {
                                            n: "id",
                                            f: ["requestRecipientActivationByVOICE-", {
                                                t: 2,
                                                r: "identificationDeliveryMethodOptionId"
                                            }],
                                            t: 13
                                        }, {
                                            n: "groupName",
                                            f: "identificationDeliveryMethodOptions",
                                            t: 13
                                        }, {n: "value", f: "VOICE", t: 13}, {
                                            n: "accessibleText",
                                            f: [{
                                                t: 2,
                                                x: {
                                                    r: ["formatPhoneMask", "identificationDeliveryMethodOptionName"],
                                                    s: "_0(_1)"
                                                }
                                            }],
                                            t: 13
                                        }, {
                                            n: "label",
                                            f: [{
                                                t: 2,
                                                x: {
                                                    r: ["variation"],
                                                    s: '_0("identificationDeliveryMethodOptionName","VOICE")'
                                                },
                                                s: !0
                                            }],
                                            t: 13
                                        }, {
                                            n: "contactId",
                                            f: [{t: 2, r: "identificationDeliveryMethodOptionId"}],
                                            t: 13
                                        }, {
                                            n: "rChange",
                                            f: "['validateActivationCode', 'formFieldTracking','changeContact']",
                                            t: 13
                                        }, {n: "rFocus", f: "['formFieldTracking']", t: 13}, {
                                            n: "classes",
                                            f: "DATA",
                                            t: 13
                                        }]
                                    }]
                                }]
                            }]
                        }], n: 52, i: "i", r: ".identificationDeliveryMethodOptions"
                    }, " ", {
                        t: 4,
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "row", t: 13}],
                            f: [{
                                t: 7,
                                e: "div",
                                m: [{n: "class", f: "col-xs-12 otp-options", t: 13}],
                                f: [{
                                    t: 7,
                                    e: "blueAlert",
                                    m: [{n: "id", f: "identificationDeliveryMethodOptionsError", t: 13}, {
                                        n: "type",
                                        f: "inverted error",
                                        t: 13
                                    }, {n: "primary", f: "true", t: 13}, {
                                        n: "icon",
                                        f: "exclamation-color error",
                                        t: 13
                                    }, {
                                        n: "title",
                                        f: [{t: 2, r: ".identificationDeliveryMethodOptionsError"}],
                                        t: 13
                                    }, {
                                        n: "message",
                                        f: [{t: 2, r: ".identificationDeliveryMethodOptionsAdvisory"}],
                                        t: 13
                                    }, {
                                        n: "accessibleTextIcon",
                                        f: [{t: 2, r: ".importantAda", s: !0}],
                                        t: 13
                                    }, {n: "focusOnRender", f: "true", t: 13}]
                                }]
                            }]
                        }],
                        n: 50,
                        r: ".identificationDeliveryMethodOptionsError"
                    }, " ", {
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "row", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-xs-12 otp-generated-code code-link", t: 13}],
                            f: [{
                                t: 7,
                                e: "blueLink",
                                m: [{n: "id", f: "provideIdentificationCode", t: 13}, {
                                    n: "content",
                                    f: [{
                                        t: 3,
                                        x: {
                                            r: ["sanitizer", ".provideIdentificationCodeMessage"],
                                            s: "_0.sanitizeHTML(_1)"
                                        }
                                    }],
                                    t: 13
                                }, {n: "rClick", f: "provideIdentificationCode", t: 13}, {
                                    n: "classes",
                                    f: "DATALINK",
                                    t: 13
                                }, {n: "endIcon", f: "progressright", t: 13}]
                            }]
                        }]
                    }, " ", {t: 4, f: [{t: 8, r: "otpButtons"}], n: 50, r: "identificationDeliveryMethodOptions"}]
                }]
            }]
        }], e: {}
    }
})),define("common/template/authenticationProcess/hybrid/activationCodeVerify", [], (function () {
    return {
        v: 4, t: [{
            t: 7, e: "div", m: [{n: "class", f: "content-section", t: 13}], f: [{
                t: 7, e: "div", m: [{n: "class", f: "section", t: 13}], f: [{
                    t: 7,
                    e: "form",
                    m: [{n: "class", f: "form", t: 13}, {n: "role", f: "form", t: 13}, {
                        n: "id",
                        f: "otpAuthenticationVerification",
                        t: 13
                    }, {n: "novalidate", f: 0, t: 13}, {n: "submit", f: "confirmTask", t: 70}],
                    f: [{
                        t: 7,
                        e: "div",
                        m: [{n: "class", f: "row", t: 13}],
                        f: [{
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-xs-12 otp-header", t: 13}],
                            f: [{
                                t: 7,
                                e: "blueFieldLabel",
                                m: [{n: "id", f: "identificationCodeLabel", t: 13}, {
                                    n: "inputId",
                                    f: "identificationCode-input-field",
                                    t: 13
                                }, {
                                    n: "label",
                                    f: [{t: 2, r: ".identificationCodeLabel", s: !0}],
                                    t: 13
                                }, {
                                    n: "errorLabelAda",
                                    f: [{t: 2, r: ".errorAnnouncementAda", s: !0}],
                                    t: 13
                                }, {
                                    n: "classes",
                                    f: ["DATALABELV ", {
                                        t: 4,
                                        f: ["clientSideError"],
                                        n: 50,
                                        r: "authenticationProcessErrorHeader"
                                    }],
                                    t: 13
                                }]
                            }]
                        }, " ", {
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-xs-12", t: 13}],
                            f: [{
                                t: 7,
                                e: "blueInput",
                                m: [{n: "id", f: "identificationCode", t: 13}, {
                                    n: "analyticsId",
                                    f: "identificationCode",
                                    t: 13
                                }, {n: "classes", f: "DATA", t: 13}, {
                                    n: "showErrorHighlighting",
                                    f: [{t: 2, x: {r: ["authenticationProcessErrorHeader"], s: "!!_0"}}],
                                    t: 13
                                }, {n: "name", f: "identificationCode", t: 13}, {
                                    n: "maxLength",
                                    f: "8",
                                    t: 13
                                }, {n: "autocomplete", f: "off", t: 13}, {
                                    n: "value",
                                    f: [{t: 2, r: ".identificationCode"}],
                                    t: 13
                                }, {n: "required", f: "true", t: 13}, {
                                    n: "errorMessage",
                                    f: [{t: 2, r: ".identificationCodeError"}],
                                    t: 13
                                }, {n: "addValidation", f: "true", t: 13}, {
                                    n: "rErrorBubbleFocus",
                                    f: "validationEngineErrorFocus",
                                    t: 13
                                }, {n: "validate", f: "identificationCode", t: 13}, {
                                    n: "rFocus",
                                    f: "formFieldTracking",
                                    t: 13
                                }, {n: "rChange", f: "formFieldTracking", t: 13}, {
                                    n: "labelErrorPrefix",
                                    f: [{t: 2, r: "errorAnnouncementAda"}],
                                    t: 13
                                }, {n: ",", f: 0, t: 13}]
                            }]
                        }, " ", {
                            t: 7,
                            e: "div",
                            m: [{n: "class", f: "col-xs-12 opt-request-code opt-generated-code", t: 13}],
                            f: [{
                                t: 4,
                                f: [{
                                    t: 7,
                                    e: "blueLink",
                                    m: [{n: "id", f: "resendActivationCodeLink", t: 13}, {
                                        n: "content",
                                        f: [{t: 2, r: ".resendIdentificationCodeLabel", s: !0}],
                                        t: 13
                                    }, {n: "rClick", f: "resendIdentificationCode", t: 13}, {
                                        n: "endIcon",
                                        f: "progressright",
                                        t: 13
                                    }, {n: "classes", f: "DATALINK resendActivationLink", t: 13}]
                                }],
                                n: 50,
                                x: {r: ["resendingStep"], s: '_0==="idle"'}
                            }, {
                                t: 4,
                                n: 50,
                                f: [{
                                    t: 7,
                                    e: "div",
                                    m: [{n: "id", f: "resendActivationCodeProgress", t: 13}, {
                                        n: "tabindex",
                                        f: "-1",
                                        t: 13
                                    }],
                                    f: [{t: 2, r: ".verificationCodeSendingLabel"}]
                                }],
                                x: {r: ["resendingStep"], s: '_0==="start"'},
                                l: 1
                            }, {
                                t: 4,
                                n: 50,
                                f: [" ", {
                                    t: 7,
                                    e: "blueSpinner",
                                    m: [{n: "id", f: "resendActivationCodeProgress", t: 13}, {
                                        n: "inline",
                                        f: "true",
                                        t: 13
                                    }, {n: "accessibleText", f: [{t: 2, r: ".waitAda"}], t: 13}]
                                }],
                                x: {r: ["resendingStep"], s: '_0==="waiting"'},
                                l: 1
                            }, {
                                t: 4,
                                n: 50,
                                f: [" ", {
                                    t: 7,
                                    e: "div",
                                    m: [{n: "id", f: "resendActivationCodeProgress", t: 13}, {
                                        n: "tabindex",
                                        f: "-1",
                                        t: 13
                                    }],
                                    f: [{
                                        t: 7,
                                        e: "blueIconWrap",
                                        m: [{n: "type", f: "checkmark", t: 13}, {
                                            n: "adatext",
                                            f: [{t: 2, r: ".checkmarkAda"}],
                                            t: 13
                                        }]
                                    }, " ", {t: 2, r: ".verificationCodeSentLabel"}]
                                }],
                                x: {r: ["resendingStep"], s: '_0==="done"'},
                                l: 1
                            }]
                        }]
                    }, " ", {t: 8, r: "otpButtons"}]
                }]
            }]
        }], e: {}
    }
})),define("common/template/authenticationProcess/hybrid/securityToken", [], (function () {
    return {
        v: 4, t: [{
            t: 7,
            e: "form",
            m: [{n: "id", f: "rsaTokenForm", t: 13}, {n: "submit", f: "confirmTask", t: 70}, {
                n: "autocomplete",
                f: "off",
                t: 13
            }, {n: "novalidate", f: 0, t: 13}, {n: "class", f: "otp-header", t: 13}],
            f: [{
                t: 7,
                e: "blueFieldGroup",
                m: [{n: "id", f: "password", t: 13}, {n: "groupType", f: "textbox horizontal", t: 13}, {
                    n: "label",
                    f: [{t: 2, r: ".passwordLabel", s: !0}],
                    t: 13
                }, {n: "inputValue", f: [{t: 2, r: ".password"}], t: 13}, {
                    n: "input",
                    f: [{
                        t: 2,
                        x: {
                            r: ["passwordError", "errorAnnouncementAda"],
                            s: '{name:"password",type:"password",rBlur:["formFieldTracking"],required:"true",errorMessage:_0,addValidation:true,rErrorBubbleFocus:"validationEngineErrorFocus",validate:"password",rFocus:"formFieldTracking",rChange:"formFieldTracking",labelErrorPrefix:_1}'
                        }
                    }],
                    t: 13
                }, {n: "inputColumns", f: "col-xs-12 INPUTFIELD", t: 13}, {
                    n: "labelColumns",
                    f: "col-xs-12 DATALABELV",
                    t: 13
                }]
            }, " ", {
                t: 7,
                e: "blueFieldGroup",
                m: [{n: "id", f: "securityToken", t: 13}, {n: "groupType", f: "textbox horizontal", t: 13}, {
                    n: "label",
                    f: [{t: 2, r: ".securityTokenLabel", s: !0}],
                    t: 13
                }, {n: "inputValue", f: [{t: 2, r: ".securityToken"}], t: 13}, {
                    n: "input",
                    f: [{
                        t: 2,
                        x: {
                            r: ["securityTokenError", "errorAnnouncementAda"],
                            s: '{name:"securityToken",type:"text",maxLength:"6",rBlur:["formFieldTracking"],rFocus:"formFieldTracking",rChange:"formFieldTracking",required:"true",errorMessage:_0,addValidation:true,rErrorBubbleFocus:"validationEngineErrorFocus",validate:"securityToken",labelErrorPrefix:_1}'
                        }
                    }],
                    t: 13
                }, {n: "inputColumns", f: "col-xs-12 INPUTFIELD", t: 13}, {
                    n: "labelColumns",
                    f: "col-xs-12 DATALABELV",
                    t: 13
                }]
            }, " ", {t: 8, r: "otpButtons"}]
        }], e: {}
    }
})),define("common/template/authenticationProcess/hybrid/buttons", [], (function () {
    return {
        v: 4,
        t: [{
            t: 4,
            f: [{
                t: 7,
                e: "div",
                m: [{n: "class", f: "hybrid-buttons-row", t: 13}],
                f: [{
                    t: 7,
                    e: "blueButton",
                    m: [{n: "id", f: "nextAction", t: 13}, {
                        n: "label",
                        f: [{t: 2, r: ".nextLabel"}],
                        t: 13
                    }, {n: "classes", f: "primary nextBtn col-sm-8 col-xs-12", t: 13}, {
                        n: "type",
                        f: "submit",
                        t: 13
                    }, {n: "disabled", f: [{t: 2, r: ".showSkipLink"}], t: 13}]
                }]
            }],
            n: 50,
            r: "hybridNavigation"
        }, {
            t: 4,
            n: 51,
            f: [{
                t: 7,
                e: "div",
                m: [{n: "id", f: "otpActions", t: 13}, {
                    n: "class",
                    f: "row hybrid-sticky-btn-container hybrid-otp-buttons",
                    t: 13
                }],
                f: [{
                    t: 7,
                    e: "blueStickyButtons",
                    m: [{n: "id", f: "nextBtn", t: 13}, {
                        n: "classes",
                        f: "hybrid-sticky-buttons bottomButtonFlexContainer",
                        t: 13
                    }, {n: "parentId", f: "otpActions", t: 13}, {
                        n: "buttons",
                        f: [{
                            t: 2,
                            x: {
                                r: [".nextLabel", ".showSkipLink"],
                                s: '{button1:{id:"nextAction",label:_0,classes:"primary nextBtn bottomButtonFlex",type:"submit",disabled:_1}}'
                            }
                        }],
                        t: 13
                    }]
                }]
            }],
            l: 1
        }],
        e: {}
    }
})),define("common/authenticationProcess/view/authenticationProcess", ["require", "blue/$", "common/lib/payments/format", "common/lib/viewUtils", "blue/util", "common/lib/ada/setFocus", "common/lib/jsBridge", "common/authenticationProcess/view/spec/authenticationProcess", "blue-ui/view/elements/alert", "blue-ui/view/elements/button", "blue-ui/view/collections/fieldgroup", "blue-ui/view/elements/fieldlabel", "blue-ui/view/elements/iconwrap", "blue-ui/view/elements/input", "blue-ui/view/elements/link", "blue-ui/view/elements/radiobutton", "blue-ui/view/elements/skiplink", "blue-ui/view/elements/spinner", "blue-ui/view/modules/tooltip", "common/view/common/buttonFooterCollection", "blue-ui/view/elements/stickybuttons", "common/template/authenticationProcess/authenticationProcess", "common/template/authenticationProcess/authenticationProcessMessage", "common/template/authenticationProcess/activationCodeEnter", "common/template/authenticationProcess/activationCodeVerify", "common/template/authenticationProcess/securityToken", "common/template/authenticationProcess/hybrid/authenticationProcess", "common/template/authenticationProcess/hybrid/authenticationProcessMessage", "common/template/authenticationProcess/hybrid/activationCodeEnter", "common/template/authenticationProcess/hybrid/activationCodeVerify", "common/template/authenticationProcess/hybrid/securityToken", "common/template/authenticationProcess/hybrid/buttons", "common/lib/variation"], (function (e) {
    "use strict";
    var t = e("blue/$"), n = e("common/lib/payments/format"), i = e("common/lib/viewUtils"),
            o = e("blue/util").object.merge, r = e("common/lib/ada/setFocus"), a = e("common/lib/jsBridge");
    return function s(c) {
        var l = this, u = null, d = null;

        function m() {
            a.changeScreenTitle(l.model.variation("mobilePageTitle", l.model.step), l.context);
            var e = l.model.hybridNavigation, t = l.model.step;
            a.updateNativeNavigationBarButtons(l.context, {
                showHamburgerMenu: !1,
                showCancel: !!e,
                showBackArrow: !e || "VERIFY" === t,
                showProfileSettings: !1
            });
            var n = "SECURITY_TOKEN" === l.model.authenticationMethod ? "ENTER" : t;
            e && (a.leaveFlow = function () {
                return l.trigger("initiateTask", "ENTER"), !0
            }), a.isHybridBackButtonRequired = function () {
                return l.trigger("initiateTask", n), !0
            }
        }

        s.areaName ? l.areaName = s.areaName : c.logger.error("AuthenticationProcessView.areaName is not available.", "Be sure to use controllerUtils.executeComponentAndView() to render this view.", "In componentConfigs, required-in this constructor instead of declaring it as a string."), l.bridge = e("common/authenticationProcess/view/spec/authenticationProcess"), l.views = {
            blueAlert: e("blue-ui/view/elements/alert"),
            blueButton: e("blue-ui/view/elements/button"),
            blueFieldGroup: e("blue-ui/view/collections/fieldgroup"),
            blueFieldLabel: e("blue-ui/view/elements/fieldlabel"),
            blueIconWrap: e("blue-ui/view/elements/iconwrap"),
            blueInput: e("blue-ui/view/elements/input"),
            blueLink: e("blue-ui/view/elements/link"),
            blueRadiobutton: e("blue-ui/view/elements/radiobutton"),
            blueSkiplink: e("blue-ui/view/elements/skiplink"),
            blueSpinner: e("blue-ui/view/elements/spinner"),
            blueTooltip: e("blue-ui/view/modules/tooltip"),
            buttonFooterCollection: e("common/view/common/buttonFooterCollection"),
            blueStickyButtons: e("blue-ui/view/elements/stickybuttons")
        }, hybrid ? (l.template = e("common/template/authenticationProcess/hybrid/authenticationProcess"), l.partials = {
            authenticationProcessMessage: e("common/template/authenticationProcess/hybrid/authenticationProcessMessage"),
            activationCodeEnter: e("common/template/authenticationProcess/hybrid/activationCodeEnter"),
            activationCodeVerify: e("common/template/authenticationProcess/hybrid/activationCodeVerify"),
            securityToken: e("common/template/authenticationProcess/hybrid/securityToken"),
            otpButtons: e("common/template/authenticationProcess/hybrid/buttons")
        }) : (l.template = e("common/template/authenticationProcess/authenticationProcess"), l.partials = {
            authenticationProcessMessage: e("common/template/authenticationProcess/authenticationProcessMessage"),
            activationCodeEnter: e("common/template/authenticationProcess/activationCodeEnter"),
            activationCodeVerify: e("common/template/authenticationProcess/activationCodeVerify"),
            securityToken: e("common/template/authenticationProcess/securityToken")
        }), l.init = function () {
            var a = "achPayments" === s.areaName ? s.areaName.toLowerCase() : s.areaName;
            i.call(l, "", "", (function (e) {
                return {cancelBtn: "#cancelBtn"}[e] || e
            })), l.model = o(l.model, n, {
                authenticationMethod: "",
                showSkipLink: !0,
                step: "ENTER",
                resendingStep: "idle",
                identificationCode: "",
                password: "",
                securityToken: "",
                contentOverride: "",
                hybridNavigation: !1
            }, {variation: e("common/lib/variation").bind(void 0, a, "AUTHENTICATION_PROCESS")}), l.bridge.on("updateViewModel", (function (e) {
                var t = e && e.data;
                Object.keys(t).forEach((function (e) {
                    l.model[e] = t[e]
                }))
            })), l.bridge.on("ready", (function () {
                l.onData("resendingStep", (function (e) {
                    try {
                        var n = t(":focus")[0], i = n && n.length, o = n && n.id;
                        c.logger.debug(e, n), "start" === e && "resendActivationCodeLink" === o ? r("#resendActivationCodeProgress") : "done" !== e || o || i ? "done" === e && "resendActivationCodeProgress" === o && r("#resendActivationCodeProgress") : r("#resendActivationCodeProgress")
                    } catch (e) {
                        c.logger.error("Error while calling onData(resendingStep)", e)
                    }
                }))
            })), l.onData("step", (function () {
                hybrid && m()
            })), l.onData("hybridNavigation", (function () {
                hybrid && m()
            }))
        }, l.verifyTask = function (e) {
            l.model.showSkipLink = !0, e.context.contactId = u, e.context.contactMethod = d, l.model.resendingStep = "idle", l.bridge.output.emit("trigger", {
                value: "verifyTask",
                data: e
            })
        }, l.provideIdentificationCode = function (e) {
            l.model.resendingStep = "idle", l.bridge.output.emit("trigger", {
                value: "provideIdentificationCode",
                data: e
            })
        }, l.validateActivationCode = function (e) {
            "" !== e.context.value && (l.model.showSkipLink = !1)
        }, l.changeContact = function (e) {
            u = e.context.contactId, d = e.context.value
        }
    }
})),define("common/template/authenticationProcess/authenticationProcessContainer", [], (function () {
    return {
        v: 4,
        t: [{
            t: 7,
            e: "section",
            m: [{n: "class", f: "wireRecipient payments container-fluid", t: 13}],
            f: [{t: 7, e: "div", m: [{n: "id", f: "authenticationProcessHeader", t: 13}]}, " ", {
                t: 7,
                e: "div",
                m: [{n: "id", f: "authenticationProcess", t: 13}]
            }]
        }]
    }
})),define("common/authenticationProcess/view/authenticationProcessContainer", ["require", "common/template/authenticationProcess/authenticationProcessContainer"], (function (e) {
    "use strict";
    return function () {
        this.template = e("common/template/authenticationProcess/authenticationProcessContainer")
    }
})),define("common/kit/main", [], (function () {
}));