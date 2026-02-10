function AuthMinderPlugin() {
}

AuthMinderPlugin.plugin = null;
AuthMinderPlugin.pluginMissing = false;
AuthMinderPlugin.E_SUCCESS = 1000;
AuthMinderPlugin.prototype.getPlugin = function () {
    if (AuthMinderPlugin.pluginMissing) {
        return null;
    }
    if (AuthMinderPlugin.plugin == null) {
        this.loadPlugin();
    }
    if (AuthMinderPlugin.plugin != null) {
        if (!("GetDeviceDNA" in AuthMinderPlugin.plugin)) {
            aotpLog("Reloading the plugin");
            this.removePlugin();
            this.loadPlugin();
        }
    }
    return AuthMinderPlugin.plugin;
};
AuthMinderPlugin.prototype.isPluginInstalled = function () {
    try {
        return window.ActiveXObject ? new ActiveXObject("CA.AuthMinder") != null : typeof navigator.plugins['CA Technologies AuthMinder'] != "undefined";
    } catch (e) {
    }
    return false;
};
AuthMinderPlugin.prototype.loadPlugin = function () {
    aotpLog("Attempting to load the plugin");
    if (this.isPluginInstalled()) {
        try {
            if (document.getElementById("arcotjsapiPlugin") == null) {
                var div = document.createElement("div");
                div.setAttribute("id", "arcotjsapiPluginDiv");
                div.setAttribute("display", "none");
                div.innerHTML = "<object id=\"arcotjsapiPlugin\" type=\"application/x-caauthminder\" width=\"0\" height=\"0\"></object>";
                document.body.appendChild(div);
            }
            var plugin = document.getElementById("arcotjsapiPlugin");
            if (plugin && "GetVersion" in plugin) {
                var version = plugin.GetVersion();
                aotpLog("Plugin found, version is: " + version);
                var props = {version: "1", DNA: null};
                var status = plugin.GetDeviceDNA(props);
                if (status == AuthMinderPlugin.E_SUCCESS) {
                    aotpLog("Plugin loaded and is working. Version: " + plugin.GetVersion());
                    aotpLog("DeviceID is: " + props.DNA);
                    AuthMinderPlugin.plugin = plugin;
                    return;
                } else {
                    aotpLog("Plugin returned: " + status);
                }
            } else {
                aotpLog("Plugin did not load");
            }
        } catch (e) {
            aotpLog("Exception while loading plugin: " + e);
        }
    } else {
        aotpLog("Plugin is not present");
    }
    aotpLog("Plugin not available");
    AuthMinderPlugin.plugin = null;
    AuthMinderPlugin.pluginMissing = true;
};
AuthMinderPlugin.prototype.removePlugin = function () {
    var div = document.getElementById("arcotjsapiPluginDiv");
    if (div != null) {
        document.body.removeChild(div);
    }
    AuthMinderPlugin.plugin = null;
};

function StoreBase(props) {
    if (props && "inheriting" in props && props.inheriting) {
        return;
    }
    this.availableImpls = [];
    var allImpls = [];
    if (props && "storageType" in props && props.storageType) {
        allImpls.push(new props.storageType);
    } else {
        allImpls.push(new StoreImplPlugin);
        allImpls.push(new StoreImplLocalStorage);
        allImpls.push(new StoreImplUserData);
        allImpls.push(new StoreImplCookies);
    }
    for (var i = 0; i < allImpls.length; i++) {
        var impl = allImpls[i];
        if (typeof impl.getType == "function" && typeof impl.loadAll == "function" && typeof impl.load == "function" && typeof impl.remove == "function" && typeof impl.save == "function" && this.isAvailable(impl)) {
            this.availableImpls.push(impl);
        }
    }
    var nImpls = this.availableImpls.length;
    if (nImpls == 0) {
        throw"No store available";
    }
    this.impl = this.availableImpls[0];
    var doMigration = props && "autoMigrate" in props ? props.autoMigrate : true;
    if (doMigration) {
        this.migrate();
    }
}

StoreBase.prototype.getType = function () {
    return this.impl ? this.impl.getType() : null;
};
StoreBase.prototype.loadAll = function (props) {
    var keyArray = [];
    var dataArray = [];
    try {
        var myProps = {};
        var n = this.impl.loadAll(myProps);
        for (var i = 0; i < n; i++) {
            var str = myProps.values[i];
            if (str != null) {
                var key = myProps.keys[i];
                if (this.isValidEntry(key, str)) {
                    keyArray.push(key);
                    dataArray.push(this.deserialize(str));
                }
            }
        }
    } catch (e) {
    }
    props.keys = keyArray;
    props.values = dataArray;
    return keyArray.length;
};
StoreBase.prototype.load = function (key) {
    try {
        var str = this.impl.load(key);
        if (str == null) {
            return null;
        }
        if (str && this.isValidEntry(key, str)) {
            return this.deserialize(str);
        }
    } catch (e) {
    }
    return null;
};
StoreBase.prototype.remove = function (key) {
    try {
        return this.impl.remove(key);
    } catch (e) {
    }
    return false;
};
StoreBase.prototype.save = function (key, value) {
    try {
        return this.impl.save(key, this.serialize(value));
    } catch (e) {
    }
    return false;
};
StoreBase.prototype.serialize = function (blob) {
    throw"serialize not overridden";
};
StoreBase.prototype.deserialize = function (str) {
    throw"deserialize not overridden";
};
StoreBase.prototype.isValidEntry = function (key, str) {
    throw"isValidEntry not overridden";
};
StoreBase.prototype.isAvailable = function (impl) {
    var working = false;
    var testKey = "arcottest_" + impl.getType();
    var testData = (new Date).getTime().toString();
    try {
        impl.save(testKey, testData);
        working = impl.load(testKey) == testData;
        impl.remove(testKey);
    } catch (e) {
    }
    return working;
};
StoreBase.prototype.sbDeviceLock = function () {
    if (typeof this.deviceLock == "function") {
        this.deviceLock.apply(this, arguments);
    }
};
StoreBase.prototype.sbDeviceUnlock = function () {
    if (typeof this.deviceUnlock == "function") {
        this.deviceUnlock.apply(this, arguments);
    }
};
StoreBase.prototype.migrate = function () {
    var storeCount = this.availableImpls.length;
    aotpLog("migrateKeys: number of stores: " + storeCount);
    if (storeCount < 2) {
        return;
    }
    var masterStore = this.availableImpls[0];
    var masterProps = {};
    masterStore.loadAll(masterProps);
    var masterKeys = {};
    var mKeys = masterProps.keys;
    for (var i = 0; i < mKeys.length; i++) {
        masterKeys[mKeys[i]] = 1;
    }
    for (var s = 1; s < this.availableImpls.length; s++) {
        var thisStore = this.availableImpls[s];
        var props = {};
        var nKeys = thisStore.loadAll(props);
        var keys = props.keys;
        var values = props.values;
        for (var i = 0; i < nKeys; i++) {
            var key = keys[i];
            var str = values[i];
            if (str && this.isValidEntry(key, str)) {
                aotpLog("migrateKeys:  key: " + key);
                if (!(key in masterKeys)) {
                    var obj = this.deserialize(str);
                    this.sbDeviceUnlock(obj, thisStore.getType());
                    this.sbDeviceLock(obj, masterStore.getType());
                    str = this.serialize(obj);
                    aotpLog("migrateKeys: adding enter to master store: " + key);
                    masterStore.save(key, str);
                    masterKeys[key] = 1;
                }
                aotpLog("migrateKeys: removing entry from lesser store: " + key);
                thisStore.remove(key);
            }
        }
    }
};

function StoreString(props) {
    StoreBase.call(this, props);
    return;
}

StoreString.prototype = new StoreBase({inheriting: true});
StoreString.prototype.serialize = function (str) {
    return str;
};
StoreString.prototype.deserialize = function (str) {
    return str;
};
StoreString.prototype.isValidEntry = function (key, str) {
    return true;
};

function StoreImplCookies() {
}

StoreImplCookies.prototype.getType = function () {
    return "cookie";
};
StoreImplCookies.prototype.loadAll = function (props) {
    var keys = [];
    var cookies = [];
    var ca = document.cookie.split(";");
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == " ") {
            c = c.substring(1, c.length);
        }
        var name_value = c.split("=");
        if (name_value != null && name_value[1] != null) {
            keys.push(name_value[0]);
            cookies.push(unescape(name_value[1]));
        }
    }
    props.keys = keys;
    props.values = cookies;
    return keys.length;
};
StoreImplCookies.prototype.load = function (key) {
    var ca = document.cookie.split(";");
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == " ") {
            c = c.substring(1, c.length);
        }
        if (c.indexOf(key + "=") == 0) {
            return unescape(c.substring(key.length + 1, c.length));
        }
    }
    return null;
};
StoreImplCookies.prototype.remove = function (key) {
    var exdate = new Date(0);
    document.cookie = key + "=;expires=" + exdate.toGMTString();
    return true;
};
StoreImplCookies.prototype.save = function (key, value) {
    var escapedValue = value ? escape(value) : "";
    var exdate = new Date;
    exdate.setDate(exdate.getDate() + 180);
    document.cookie = key + "=" + escapedValue + ";expires=" + exdate.toGMTString();
    return true;
};

function StoreImplLocalStorage() {
    try {
        var dummy = localStorage.length;
    } catch (e) {
        aotpLog("StoreLocalImpl: localStorage: " + e);
    }
}

StoreImplLocalStorage.prototype.getType = function () {
    return "LocalStorage";
};
StoreImplLocalStorage.prototype.loadAll = function (props) {
    if (!this.available()) {
        return null;
    }
    var keyIndex = 0;
    var keys = [];
    var values = [];
    var key, value;
    while (keyIndex < 999999) {
        try {
            key = localStorage.key(keyIndex++);
        } catch (e) {
            break;
        }
        if (key == null) {
            break;
        }
        value = localStorage.getItem(key);
        keys.push(key);
        values.push(value);
    }
    props.keys = keys;
    props.values = values;
    return keys.length;
};
StoreImplLocalStorage.prototype.load = function (key) {
    if (!this.available()) {
        return null;
    }
    return localStorage.getItem(key);
};
StoreImplLocalStorage.prototype.remove = function (key) {
    if (!this.available()) {
        return null;
    }
    try {
        localStorage.removeItem(key);
    } catch (e) {
        return false;
    }
    return true;
};
StoreImplLocalStorage.prototype.save = function (key, value) {
    if (!this.available()) {
        return null;
    }
    try {
        localStorage.setItem(key, value);
    } catch (e) {
        return false;
    }
    return true;
};
StoreImplLocalStorage.prototype.available = function () {
    try {
        if (typeof localStorage == "undefined") {
            return false;
        }
    } catch (e) {
        return false;
    }
    return true;
};

function StoreImplPlugin() {
    this.AMP = null;
}

StoreImplPlugin.prototype.getType = function () {
    return "plugin";
};
StoreImplPlugin.prototype.loadAll = function (props) {
    var plugin = this.getPlugin();
    if (plugin == null) {
        return null;
    }
    var keys = plugin.GetKeys();
    var values = [];
    if (keys != null) {
        for (var i = 0; i < keys.length; i++) {
            try {
                var o = {key: keys[i], value: null};
                var status = plugin.Get(o);
                if (status == AuthMinderPlugin.E_SUCCESS) {
                    values.push(o.value);
                } else {
                    values.push(null);
                }
            } catch (e) {
                values.push(null);
            }
        }
    }
    props.keys = keys;
    props.values = values;
    return keys.length;
};
StoreImplPlugin.prototype.load = function (key) {
    var plugin = this.getPlugin();
    if (plugin == null) {
        return null;
    }
    var o = {key: key, value: null};
    var status = plugin.Get(o);
    if (status != AuthMinderPlugin.E_SUCCESS) {
        return null;
    }
    return o.value;
};
StoreImplPlugin.prototype.remove = function (key) {
    var plugin = this.getPlugin();
    if (plugin == null) {
        return false;
    }
    var status = plugin.Delete(key);
    return status == AuthMinderPlugin.E_SUCCESS;
};
StoreImplPlugin.prototype.save = function (key, value) {
    var plugin = this.getPlugin();
    if (plugin == null) {
        return false;
    }
    var status = plugin.Store({key: key, value: value});
    return status == AuthMinderPlugin.E_SUCCESS;
};
StoreImplPlugin.prototype.getPlugin = function getPlugin() {
    if (this.AMP == null) {
        this.AMP = new AuthMinderPlugin;
    }
    return this.AMP ? this.AMP.getPlugin() : null;
};

function StoreImplUserData() {
    this.maxage = 0;
    this.userdata = null;
}

StoreImplUserData.prototype.getType = function () {
    return "userData";
};
StoreImplUserData.prototype.loadAll = function (props) {
    if (!this.checkUserDataLoaded()) {
        return 0;
    }
    var keys = [];
    var values = [];
    try {
        var attributes = this.userdata.xmlDocument.firstChild.attributes;
        var len = attributes.length;
        for (var i = 0; i < len; i++) {
            var attribute = attributes[i];
            keys.push(attribute.nodeName);
            values.push(attribute.nodeValue);
        }
    } catch (e) {
        aotpLog("Exception in userData loadall: " + e);
    }
    props.keys = keys;
    props.values = values;
    return keys.length;
};
StoreImplUserData.prototype.load = function (key) {
    if (!this.checkUserDataLoaded()) {
        return null;
    }
    return this.userdata.getAttribute(key);
};
StoreImplUserData.prototype.remove = function (key) {
    if (!this.checkUserDataLoaded()) {
        return false;
    }
    this.userdata.removeAttribute(key);
    this.userdata.save("ArcotUserDataStorage");
    return true;
};
StoreImplUserData.prototype.save = function (key, value) {
    if (!this.checkUserDataLoaded()) {
        return false;
    }
    this.userdata.setAttribute(key, value);
    this.userdata.save("ArcotUserDataStorage");
    return true;
};
StoreImplUserData.prototype.checkUserDataLoaded = function () {
    if (this.userdata == null) {
        this.loadUserData();
    }
    return this.userdata ? true : false;
};
StoreImplUserData.prototype.loadUserData = function () {
    aotpLog("Attempting to load the UserData");
    try {
        this.userdata = document.createElement("div");
        this.userdata.setAttribute("id", "arcotuserdataDiv");
        this.userdata.setAttribute("display", "none");
        this.userdata.style.display = "none";
        this.userdata.style.behavior = "url('#default#userData')";
        document.body.appendChild(this.userdata);
        if (this.maxage) {
            var now = (new Date).getTime();
            var expires = now + this.maxage * 1000;
            this.userdata.expires = (new Date(expires)).toUTCString();
        }
        this.userdata.load("ArcotUserDataStorage");
    } catch (e) {
        aotpLog("Exception while loading userData: " + e);
        this.userdata = null;
    }
};
StoreImplUserData.prototype.removeUserData = function () {
    if (this.userdata != null) {
        document.body.removeChild(this.userdata);
    }
    this.userdata = null;
};
if (typeof ca == "undefined") {
    ca = {};
}
ca.base = {};
ca.base.dom = {};
ca.base.format = {};
ca.base.store = {};
ca.base.util = {};
ca.base.format.Html = {
    write: function (obj) {
        var type;
        if (obj == undefined || obj == null) {
            type = "null";
        } else if (obj.constructor == Array) {
            type = "array";
        } else {
            type = typeof obj;
        }
        var val;
        switch (type) {
            case"string":
                val = this.writeString(obj);
                break;
            case"number":
                val = this.writeNumber(obj);
                break;
            case"boolean":
                val = this.writeBoolean(obj);
                break;
            case"array":
                val = this.writeArray(obj);
                break;
            case"object":
                val = this.writeObject(obj);
                break;
            case"null":
                val = null;
                break;
            default:
                break;
        }
        return val;
    }, writeString: function (obj) {
        return obj;
    }, writeNumber: function (obj) {
        return obj;
    }, writeBoolean: function (obj) {
        return obj;
    }, writeArray: function (obj) {
        var arr = new Array;
        arr.push("<table border=\"1\" cellspacing=\"0\">");
        var str;
        for (var i = 0; i < obj.length; i++) {
            str = "<tr><td>" + i + "</td><td>" + obj[i] + "</td></tr>";
            arr.push(str);
        }
        arr.push("</table>");
        return arr.join("");
    }, writeObject: function (obj) {
        var arr = new Array;
        arr.push("<table border=\"1\" cellspacing=\"0\">");
        var str, val;
        for (var name in obj) {
            val = obj[name];
            str = "<tr><td>" + name + "</td><td>" + this.write(val) + "</td></tr>";
            arr.push(str);
        }
        arr.push("</table>");
        return arr.join("");
    }
};
ca.base.format.Json = {
    write: function (obj) {
        var type;
        if (obj == undefined || obj == null) {
            type = "null";
        } else if (obj.constructor == Array) {
            type = "array";
        } else {
            type = typeof obj;
        }
        var val;
        switch (type) {
            case"string":
                val = this.writeString(obj);
                break;
            case"number":
                val = this.writeNumber(obj);
                break;
            case"boolean":
                val = this.writeBoolean(obj);
                break;
            case"array":
                val = this.writeArray(obj);
                break;
            case"object":
                val = this.writeObject(obj);
                break;
            case"null":
                val = null;
                break;
            default:
                break;
        }
        return val;
    }, writeString: function (obj) {
        return "\"" + this.jsonescape(obj) + "\"";
    }, writeNumber: function (obj) {
        return obj;
    }, writeBoolean: function (obj) {
        return obj;
    }, writeArray: function (obj) {
        var arr = new Array;
        for (var i = 0; i < obj.length; i++) {
            arr.push(this.write(obj[i]));
        }
        return "[" + arr.join(",") + "]";
    }, writeObject: function (obj) {
        var arr = new Array;
        var str, val;
        for (var name in obj) {
            val = obj[name];
            str = "\"" + this.jsonescape(name) + "\":" + this.write(val);
            arr.push(str);
        }
        return "{" + arr.join(",") + "}";
    }, jsonescape: function (obj) {
        return obj.replace(/([\"\\])/g, "\\$1");
    }
};
ca.base.util.Browser = {
    name: null, family: null, processed: false, getName: function () {
        this.process();
        return this.name;
    }, getFamily: function () {
        this.process();
        return this.family;
    }, process: function () {
        if (this.processed) {
            return;
        }
        var ua = navigator.userAgent;
        if (!ua) {
            return;
        }
        ua = ua.toLowerCase();
        var f, n;
        if (ua.indexOf("avant") != -1) {
            n = "Avant";
        } else if (ua.indexOf("msie") != -1) {
            n = "MSIE";
        } else if (ua.indexOf("firefox") != -1) {
            n = "Firefox";
        } else if (ua.indexOf("chrome") != -1) {
            n = "Chrome";
        } else if (ua.indexOf("safari") != -1) {
            n = "Safari";
        } else if (ua.indexOf("mozilla") != -1) {
            n = "Mozilla";
        } else if (ua.indexOf("opera") != -1) {
            n = "Opera";
        }
        if (n) {
            f = n == "MSIE" ? "MSIE" : "Netscape";
        } else {
            n = "Unknown";
            f = "Unknown";
        }
        this.family = f;
        this.name = n;
        this.processed = true;
    }
};
ca.base.util.FlashPlayerVersion = function (arrVersion) {
    this.major = arrVersion[0] != null ? parseInt(arrVersion[0]) : 0;
    this.minor = arrVersion[1] != null ? parseInt(arrVersion[1]) : 0;
    this.rev = arrVersion[2] != null ? parseInt(arrVersion[2]) : 0;
    this.versionIsValid = function (fv) {
        if (this.major < fv.major) {
            return false;
        }
        if (this.major > fv.major) {
            return true;
        }
        if (this.minor < fv.minor) {
            return false;
        }
        if (this.minor > fv.minor) {
            return true;
        }
        if (this.rev < fv.rev) {
            return false;
        }
        return true;
    };
};
ca.base.util.Flash = {
    FLASH_HTML: "<OBJECT WIDTH=\"0\" HEIGHT=\"0\" ALIGN=\"\" classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\"   CODEBASE=\"//download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,5,0,0\" id=\"@flash_id@\"><PARAM NAME=\"allowScriptAccess\" VALUE=\"always\" /><PARAM NAME=\"quality\" VALUE=\"high\"/><PARAM NAME=\"bgcolor\" VALUE=\"#ffffff\"/><PARAM NAME=\"movie\" VALUE=\"@flash_file@\"/><PARAM NAME=\"flashvars\" VALUE=\"@flash_vars@\"/><EMBED WIDTH=\"0\" HEIGHT=\"0\" ALIGN=\"\" TYPE=\"application/x-shockwave-flash\" PLUGINSPAGE=\"//www.macromedia.com/go/getflashplayer\" ALLOWSCRIPTACCESS=\"always\" QUALITY=\"high\" BGCOLOR=\"#ffffff\" NAME=\"@flash_id@\" SRC=\"@flash_file@\" FLASHVARS=\"@flash_vars@\"></EMBED></OBJECT>",
    create: function (id, file, vars) {
        var div = document.createElement("div");
        document.body.appendChild(div);
        var html = this.FLASH_HTML;
        html = html.replace(/@flash_id@/g, id);
        html = html.replace(/@flash_file@/g, file);
        html = html.replace(/@flash_vars@/g, vars);
        div.innerHTML = html;
    },
    getHandle: function (id) {
        if (window.document[id]) {
            return window.document[id];
        }
        if (navigator.appName.indexOf("Microsoft") != -1) {
            return document.getElementById(id);
        }
        if (document.embeds && document.embeds[id]) {
            return document.embeds[id];
        }
        return null;
    },
    getFlashPlayerVersion: function () {
        var PlayerVersion = new ca.base.util.FlashPlayerVersion([0, 0, 0]);
        if (navigator.plugins && navigator.mimeTypes.length) {
            var x = navigator.plugins['Shockwave Flash'];
            if (x && x.description) {
                PlayerVersion = new ca.base.util.FlashPlayerVersion(x.description.replace(/([a-zA-Z]|\s)+/, "").replace(/(\s+r|\s+b[0-9]+)/, ".").split("."));
            }
        } else if (navigator.userAgent && navigator.userAgent.indexOf("Windows CE") >= 0) {
            var axo = 1;
            var counter = 3;
            while (axo) {
                try {
                    counter++;
                    axo = new ActiveXObject("ShockwaveFlash.ShockwaveFlash." + counter);
                    PlayerVersion = new ca.base.util.FlashPlayerVersion([counter, 0, 0]);
                } catch (e) {
                    axo = null;
                }
            }
        } else {
            try {
                var axo = new ActiveXObject("ShockwaveFlash.ShockwaveFlash.7");
            } catch (e) {
                try {
                    var axo = new ActiveXObject("ShockwaveFlash.ShockwaveFlash.6");
                    PlayerVersion = new ca.base.util.FlashPlayerVersion([6, 0, 21]);
                    axo.AllowScriptAccess = "always";
                } catch (e) {
                    if (PlayerVersion.major == 6) {
                        return PlayerVersion;
                    }
                }
                try {
                    axo = new ActiveXObject("ShockwaveFlash.ShockwaveFlash");
                } catch (e) {
                }
            }
            if (axo != null) {
                PlayerVersion = new ca.base.util.FlashPlayerVersion(axo.GetVariable("$version").split(" ")[1].split(","));
            }
        }
        return PlayerVersion;
    }
};
ca.base.util.Logger = function (name) {
    this.debug = false;
    this.name = name;
    this.arr = new Array;
    this.console = null;
    this.log = function (msg) {
        msg = this.name + " - " + msg;
        this.arr.push(msg);
        if (!this.debug) {
            return;
        }
        if (!this.console) {
            this.console = ca.base.util.Console.getConsole();
        }
        this.console.log(msg);
    };
    this.getLogs = function (separator) {
        var s = "\n";
        if (arguments.length != 0) {
            s = arguments[0];
        }
        return this.arr.join(s);
    };
};
ca.base.util.Console = {
    obj: null, getConsole: function () {
        if (!this.obj) {
            this.obj = window.console ? window.console : this.createConsole();
        }
        return this.obj;
    }, createConsole: function () {
        var div = document.createElement("div");
        div.innerHTML = "<h3>Log Console</h3>";
        var ta = document.createElement("textarea");
        div.appendChild(ta);
        ta.style.border = "1px solid";
        ta.style.height = "500px";
        ta.style.width = "700px";
        ta.style.overflow = "auto";
        ta.style.padding = "5px";
        if (document.body) {
            document.body.appendChild(div);
        }
        var obj = {
            log: function (msg) {
                ta.value += msg + "\n";
            }
        };
        return obj;
    }
};
ca.base.util.RMSleep = {
    sleep: function (msec) {
        var tmpmsec = msec;
        tmpmsec += (new Date).getTime();
        while (new Date < tmpmsec) {
        }
    }
};
ca.base.util.Mask = {
    msg: "Please wait...", opacity: 0.5, maskCreated: false, obj: null, show: function (flag) {
        if (!this.maskCreated) {
            this.obj = this.createMask();
            this.resize();
            this.maskCreated = true;
        }
        this.obj.style.display = flag ? "block" : "none";
    }, createMask: function () {
        var div = document.createElement("div");
        div.style.display = "none";
        div.style.position = "absolute";
        div.style.top = "0px";
        div.style.left = "0px";
        div.style.backgroundColor = "#C0C0C0";
        div.style.zIndex = "1000";
        div.innerHTML = "<span style=\"opacity:1; filter:alpha(opacity=100); background-color:#FFFF00\">" + this.msg + "</span>";
        var ua = navigator.userAgent.toLowerCase();
        if (ua.indexOf("msie") == -1) {
            div.style.opacity = this.opacity;
        } else {
            div.style.filter = "alpha(opacity=" + this.opacity * 100 + ")";
        }
        document.body.appendChild(div);
        return div;
    }, resize: function () {
        if (!this.obj) {
            return;
        }
        var h = 600, w = 700;
        if (window.innerWidth) {
            h = window.innerHeight;
            w = window.innerWidth;
        } else if (document.documentElement && document.documentElement.clientWidth) {
            h = document.documentElement.clientHeight;
            w = document.documentElement.clientWidth;
        } else if (document.body && document.body.clientWidth) {
            h = document.body.clientHeight;
            w = document.body.clientWidth;
        }
        this.obj.style.height = h + "px";
        this.obj.style.width = w + "px";
    }
};
window.onresize = function () {
    ca.base.util.Mask.resize();
};
ca.base.util.ArcotJSBN = {
    BI_RM: "0123456789abcdefghijklmnopqrstuvwxyz", int2char: function (n) {
        return this.BI_RM.charAt(n);
    }
};
ca.base.util.ArcotBase64 = {
    b64map: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/", b64padding: "=", hex2b64: function (h) {
        var i;
        var c;
        var ret = "";
        for (i = 0; i + 3 <= h.length; i += 3) {
            c = parseInt(h.substring(i, i + 3), 16);
            ret += this.b64map.charAt(c >> 6) + this.b64map.charAt(c & 63);
        }
        if (i + 1 == h.length) {
            c = parseInt(h.substring(i, i + 1), 16);
            ret += this.b64map.charAt(c << 2);
        } else if (i + 2 == h.length) {
            c = parseInt(h.substring(i, i + 2), 16);
            ret += this.b64map.charAt(c >> 2) + this.b64map.charAt((c & 3) << 4);
        }
        while ((ret.length & 3) > 0) {
            ret += this.b64padding;
        }
        return ret;
    }, b64tohex: function (s) {
        var ret = "";
        var i;
        var k = 0;
        var slop;
        for (i = 0; i < s.length; ++i) {
            if (s.charAt(i) == this.b64padding) {
                break;
            }
            v = this.b64map.indexOf(s.charAt(i));
            if (v < 0) {
                continue;
            }
            if (k == 0) {
                ret += ca.base.util.ArcotJSBN.int2char(v >> 2);
                slop = v & 3;
                k = 1;
            } else if (k == 1) {
                ret += ca.base.util.ArcotJSBN.int2char(slop << 2 | v >> 4);
                slop = v & 15;
                k = 2;
            } else if (k == 2) {
                ret += ca.base.util.ArcotJSBN.int2char(slop);
                ret += ca.base.util.ArcotJSBN.int2char(v >> 2);
                slop = v & 3;
                k = 3;
            } else {
                ret += ca.base.util.ArcotJSBN.int2char(slop << 2 | v >> 4);
                ret += ca.base.util.ArcotJSBN.int2char(v & 15);
                k = 0;
            }
        }
        if (k == 1) {
            ret += ca.base.util.ArcotJSBN.int2char(slop << 2);
        }
        return ret;
    }, b64toBA: function (s) {
        var h = this.b64tohex(s);
        var i;
        var a = new Array;
        for (i = 0; 2 * i < h.length; ++i) {
            a[i] = parseInt(h.substring(2 * i, 2 * i + 2), 16);
        }
        return a;
    }
};
ca.base.util.Mobile = {
    REGEX1: /android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i,
    REGEX2: /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i,
    SMARTPHONE: {Android: /Android/i, BlackBerry: /BlackBerry/i, iOS: /iP(hone|od|ad)/i, Windows: /IEMobile/i},
    mobileFlag: false,
    smartName: null,
    processed: false,
    isMobile: function () {
        this.process();
        return this.mobileFlag;
    },
    isSmart: function () {
        this.process();
        return this.smartName != null;
    },
    getSmartName: function () {
        this.process();
        return this.smartName;
    },
    process: function () {
        if (this.processed) {
            return;
        }
        var ua = navigator.userAgent || navigator.vendor || window.opera;
        if (!ua) {
            return;
        }
        this.mobileFlag = this.REGEX1.test(ua) || this.REGEX2.test(ua.substr(0, 4));
        for (var name in this.SMARTPHONE) {
            var regex = this.SMARTPHONE[name];
            if (regex.test(ua)) {
                this.smartName = name;
                break;
            }
        }
        this.processed = true;
    }
};
var _rmclient_instance_ = null;
gmescDefaultNumberOfIterations = 2;
gmescDefaultCalibrationDuration = 150;
gmescDefaultIntervalDelay = 30;
if (typeof ca == "undefined") {
    ca = {};
}
ca.rm = {};
ca.rm.job = {};
ca.rm.format = {};
ca.rm.store = {};
ca.rm.util = {};
ca.rm.Client = function () {
    var VERSION = "2.1.2";
    var DEVICEID_NAME = "deviceid";
    var jobList = ["browser", "clientcaps", "plugin", "screen", "system", "mesc", "hmid"];
    var jobListWithFonts = ["browser", "clientcaps", "plugin", "screen", "system", "mesc", "fonts", "hmid"];
    var showBusy;
    var storeName;
    var jobs;
    var axoList = null;
    var fontList = null;
    var format = "json";
    var didname = null;
    var flashPath = "";
    var noFlash = false;
    var flashDataStoreName = null;
    var baseURL = null;
    var mescmaxIterations;
    var mesccalibrationDuration;
    var mescintervalDelay;
    var storeImpl = null;
    var props = {};
    var dna = {};
    var timeTaken = 0;
    var externalIP = "";
    this.getVersion = function () {
        return VERSION;
    };
    this.setProperty = function (key, val) {
        key = key.toLowerCase();
        switch (key) {
            case"activex":
                axoList = val;
                break;
            case"debug":
                RMLogger.debug = val;
                break;
            case"didname":
                didname = val;
                setDIDNameInStore();
                break;
            case"flashpath":
                flashPath = val;
                break;
            case"fonts":
                fontList = val;
                break;
            case"format":
                format = val;
                break;
            case"jobs":
                jobs = val;
                break;
            case"showbusy":
                showBusy = val;
                break;
            case"store":
                setStore(val);
                break;
            case"externalip":
                externalIP = val;
                break;
            case"mescmaxiterations":
                mescmaxIterations = val;
                break;
            case"mesccalibrationduration":
                mesccalibrationDuration = val;
                break;
            case"mescintervaldelay":
                mescintervalDelay = val;
                break;
            case"noflash":
                noFlash = val;
                break;
            case"flashdatastorename":
                flashDataStoreName = val;
                break;
            case"baseurl":
                baseURL = val;
            default:
                break;
        }
        props[key] = val;
    };
    this.getProperty = function (key) {
        return props[key];
    };
    this.loadFlash = function (callback) {
        ca.rm.job.Flash.init(callback);
    };
    this.processDNA = function () {
        if (!document.body) {
            alert("Warning: <BODY> tag is not defined.\nRiskMinder Client needs this tag to be present.");
            return;
        }
        if (showBusy) {
            ca.base.util.Mask.show(true);
        }
        RMLogger.log("***** Start Configuration*****");
        for (var key in props) {
            RMLogger.log(key + "=" + props[key]);
        }
        RMLogger.log("*****End Configuration*****");
        var startTime = (new Date).getTime();
        dna = {};
        dna.version = "2.1.2";
        dna.ExternalIP = externalIP;
        if (!noFlash) {
        }
        var job, name;
        for (var i = 0; i < jobs.length; i++) {
            job = null;
            name = jobs[i];
            switch (name) {
                case"activex":
                    break;
                case"browser":
                    job = ca.rm.job.Browser;
                    break;
                case"clientcaps":
                    job = ca.rm.job.ClientCaps;
                    break;
                case"fonts":
                    job = ca.rm.job.Flash;
                    break;
                case"plugin":
                    job = ca.rm.job.Plugin;
                    break;
                case"screen":
                    job = ca.rm.job.Screen;
                    break;
                case"system":
                    job = ca.rm.job.System;
                    break;
                case"mesc":
                    job = ca.rm.job.MESC;
                    break;
                case"hmid":
                    job = ca.rm.job.HMID;
                    break;
                default:
                    break;
            }
            doJob(name, job);
        }
        endTime = (new Date).getTime();
        timeTaken = endTime - startTime;
        RMLogger.log("Finished DNA collection.");
        RMLogger.log("Total time: " + timeTaken);
        RMLogger.log("Done!");
        if (showBusy) {
            ca.base.util.Mask.show(false);
        }
    };
    this.getDNA = function () {
        RMLogger.log("Formatting DNA...");
        var formatter = null;
        switch (format) {
            case"json":
                formatter = ca.rm.format.Json;
                break;
            case"html":
                formatter = ca.rm.format.Html;
                break;
            default:
                RMLogger.log("Error: Invalid formatter '" + format + "'");
                break;
        }
        var tempDna = ca.rm.format.Convert.process(dna);
        var str = !formatter ? null : formatter.process(tempDna);
        RMLogger.log("DNA = " + str);
        RMLogger.log("Done!");
        return str;
    };
    this.getTimeTaken = function () {
        return timeTaken;
    };
    this.getBaseURL = function () {
        return baseURL;
    };
    this.setDID = function (value) {
        if (!hasStore()) {
            return;
        }
        storeImpl.save(didname, value);
    };
    this.getDID = function () {
        var ret;
        if (!hasStore()) {
            return null;
        }
        ret = storeImpl.load(didname);
        if (ret == null || ret == "undefined") {
            var fdsname = "ArcotDataStore";
            if (flashDataStoreName != null) {
                fdsname = flashDataStoreName;
            }
            ret = ca.rm.job.Flash.getCookie(didname, fdsname);
            if (ret != null && ret != "undefined") {
                ret = unescape(ret);
                this.setDID(ret);
            }
        }
        return ret;
    };
    this.deleteDID = function () {
        if (!hasStore()) {
            return false;
        }
        storeImpl.remove(didname);
    };
    this.getLogs = function () {
        return RMLogger.getLogs();
    };
    this.flashNotRequired = function () {
        return noFlash;
    };
    this.getMescMaxIters = function () {
        return mescmaxIterations;
    };
    this.getMescCalibrationDuration = function () {
        return mesccalibrationDuration;
    };
    this.getMescIntervalDelay = function () {
        return mescintervalDelay;
    };

    function setDIDNameInStore() {
        if (storeImpl) {
            storeImpl.setDIDName(didname);
        }
    }

    function hasStore() {
        if (storeImpl) {
            RMLogger.log("Using store: " + storeName);
            return true;
        } else {
            RMLogger.log("Error: Store not set");
            return false;
        }
    }

    function setStore(name) {
        var storeprops = {};
        storeName = name;
        switch (name) {
            case"cookie":
                storeprops.storageType = StoreImplCookies;
                break;
            case"localstorage":
                storeprops.storageType = StoreImplLocalStorage;
                break;
            case"plugin":
                storeprops.storageType = StoreImplPlugin;
                break;
            case"default":
                break;
            default:
                RMLogger.log("Error: Invalid store '" + name + "'");
                break;
        }
        storeImpl = new ca.rm.store.StoreDID(storeprops);
        if (didname != "undefined" && didname != null) {
            storeImpl.setDIDName(didname);
        }
        RMLogger.log("Store type: " + storeName);
    }

    function doJob(name, job) {
        if (!job) {
            RMLogger.log("Error: Job '" + name + "' not found");
            return;
        }
        RMLogger.log("Processing: " + name + "...");
        var startTime = (new Date).getTime();
        var result = null;
        try {
            switch (name) {
                case"activex":
                    result = job.process(axoList);
                    break;
                default:
                    result = job.process();
                    break;
            }
        } catch (e) {
            RMLogger.log("Error: " + e.name + " - " + e.message);
        }
        if (result) {
            cleanup(result);
            dna[name] = result;
        }
        var endTime = (new Date).getTime();
        var timeTaken = endTime - startTime;
        RMLogger.log("Time taken: " + timeTaken + " ms");
    }

    function cleanup(obj) {
        var arr = new Array;
        var val;
        for (var key in obj) {
            val = obj[key];
            if (typeof val == "undefined" || val == null) {
                eval("delete obj." + key);
            }
        }
    }

    mescmaxIterations = gmescDefaultNumberOfIterations;
    mesccalibrationDuration = gmescDefaultCalibrationDuration;
    mescintervalDelay = gmescDefaultIntervalDelay;
    this.setProperty("debug", false);
    this.setProperty("didname", DEVICEID_NAME);
    this.setProperty("format", "json");
    this.setProperty("jobs", jobListWithFonts);
    this.setProperty("showbusy", false);
    this.setProperty("store", "default");
    _rmclient_instance_ = this;
};
RMLogger = new ca.base.util.Logger("RMClient");
if (typeof arcotrf == "undefined") {
    var arcotrf = {};
}
arcotrf.ArcotRFClient = function () {
    this.client = new ca.rm.Client;
};
arcotrf.ArcotRFClient.prototype.client;
arcotrf.ArcotRFClient.prototype.callback;
arcotrf.ArcotRFClient.prototype.lsoVal;
arcotrf.ArcotRFClient.prototype.load = function (callbackFunc, wrapperDivId, flashCookie, dna, flashDNA, javaDNA, pluginDNA, wrapperDivDisplayStyle) {
    if (callbackFunc) {
        callbackFunc();
    }
};
arcotrf.ArcotRFClient.prototype.getError = function () {
    return "This method is deprecated";
};
arcotrf.ArcotRFClient.prototype.getLog = function () {
    return "This method is deprecated";
};
arcotrf.ArcotRFClient.prototype.setFlashCookie = function (cookieNm, cookieVal, onDone) {
    this.client.setProperty("didname", cookieNm);
    this.client.setDID(cookieVal);
    if (onDone) {
        onDone();
    }
};
arcotrf.ArcotRFClient.prototype.getFlashCookie = function (cookieNm, onDone) {
    this.client.setProperty("didname", cookieNm);
    this.lsoVal = this.client.getDID();
    if (onDone) {
        onDone();
    }
};
arcotrf.ArcotRFClient.prototype.getFlashCookieValue = function () {
    return this.lsoVal;
};
arcotrf.ArcotRFClient.prototype.getBrowserCookie = function (name) {
    this.client.setProperty("didname", name);
    return this.client.getDID();
};
arcotrf.ArcotRFClient.prototype.setBrowserCookie = function (name, value, days2live, domain) {
    this.client.setProperty("didname", name);
    this.client.setDID(value);
};
arcotrf.ArcotRFClient.prototype.deleteBrowserCookie = function (name) {
    this.client.setProperty("didname", name);
    this.client.deleteDID();
};
arcotrf.ArcotRFClient.prototype.analyzeDeviceDNA = function (onDone, maxDurationMSec) {
    this.client.processDNA();
    if (onDone) {
        onDone();
    }
};
arcotrf.ArcotRFClient.prototype.getDNAString = function (formatCode) {
    this.client.setProperty("format", "json");
    return this.client.getDNA();
};
arcotrf.ArcotRFClient.prototype.formatAsHTML = function () {
    this.client.setProperty("format", "html");
    return this.client.getDNA();
};
arcotrf.ArcotRFClient.prototype.getDNAExecutionTime = function () {
    return this.client.getTimeTaken();
};
arcotrf.ArcotRFClient.prototype.getMESCValue = function () {
    return -1;
};
arcotrf.ArcotRFClient.prototype.getMESCIterationsCompleted = function () {
    return -1;
};
arcotrf.ArcotRFClient.prototype.getDESCValue = function () {
    return -1;
};
arcotrf.ArcotRFClient.prototype.setDNAConfiguration = function (externalIPAddr, internalIPDivID, macDivID, ieClientCapsDivId) {
};
arcotrf.ArcotRFClient.prototype.setMESCConfiguration = function (isMESCOn, numberOfIterations, calibrationDuration, intervalDelay) {
    this.client.setProperty("mescmaxiterations", numberOfIterations);
    this.client.setProperty("mesccalibrationduration", calibrationDuration);
    this.client.setProperty("mescintervaldelay", intervalDelay);
};
arcotrf.ArcotRFClient.prototype.setDESCConfiguration = function (isDESCOn, numberOfIterations, calibrationDuration, neverUseFlash) {
};
arcotrf.ArcotRFClient.prototype.setJavaAppletPathURL = function (appletBaseURL) {
};
arcotrf.ArcotRFClient.prototype.setFlashParams = function (flashDivId, movieUrl, maxRetries, retryPeriodMSec) {
};
ca.rm.format.Json = {
    process: function (obj) {
        return ca.base.format.Json.write(obj);
    }
};
ca.rm.format.Html = {
    process: function (obj) {
        return ca.base.format.Html.write(obj);
    }
};
ca.rm.format.Convert = {
    process: function (obj) {
        if (obj == null) {
            return null;
        }
        var dna = {};
        var mfp = {};
        dna.VERSION = _rmclient_instance_.getVersion();
        dna.MFP = mfp;
        for (var key in obj) {
            switch (key) {
                case"browser":
                    mfp.Browser = obj[key];
                    break;
                case"clientcaps":
                    mfp.IEPlugins = obj[key];
                    break;
                case"plugin":
                    mfp.NetscapePlugins = obj[key];
                    break;
                case"screen":
                    mfp.Screen = obj[key];
                    break;
                case"system":
                    mfp.System = obj[key];
                    break;
                case"version":
                    break;
                case"ExternalIP":
                    dna.ExternalIP = obj[key];
                    break;
                case"mesc":
                    dna.MESC = obj[key];
                    break;
                case"fonts":
                    dna['Flash Attributes'] = obj[key];
                    break;
                case"hmid":
                    mfp.HMID = obj[key];
                    break;
                default:
                    break;
            }
        }
        return dna;
    }
};
ca.rm.job.ActiveX = {
    axoList: ["Adobe Acrobat", "Flash", "Java", "QuickTime", "RealPlayer", "ShockWave", "SilverLight", "VLC Player", "Windows Media Player"],
    process: function () {
        var info = {};
        if (typeof ActiveXObject == "undefined") {
            return info;
        }
        var axoData = ca.rm.job.AxoData;
        var axoTask = ca.rm.job.AxoTask;
        var names;
        if (arguments.length < 1) {
            names = this.axoList;
        } else {
            names = arguments[0];
        }
        if (!names) {
            return info;
        }
        var name, version, item;
        for (var i = 0; i < names.length; i++) {
            name = names[i];
            item = axoData[name];
            task = axoTask[name];
            if (!item) {
                continue;
            }
            version = task(item);
            info[name] = version ? version : "0";
        }
        return info;
    }
};
ca.rm.job.AxoTask = {
    'Adobe Acrobat': function (item) {
        var axo = ca.rm.job.AxoHelper.findAxo(item.progid);
        if (!axo) {
            return null;
        }
        try {
            var v = 0;
            var m = axo.GetVersions().match(/\d+([\.|_|-]\d+)*/g);
            for (var i = 0; i < m.length; i++) {
                if (m[i] > v) {
                    v = m[i];
                }
            }
            return v;
        } catch (e) {
            return "";
        }
    }, Flash: function (item) {
        var axo = ca.rm.job.AxoHelper.getAxo(item.progid);
        if (!axo) {
            return null;
        }
        try {
            return axo.ShockwaveVersion("");
        } catch (e) {
            return "";
        }
    }, Java: function (item) {
        var versions = new Array("1.10.0", "1.9.0", "1.8.0", "1.7.0", "1.6.0", "1.5.0", "1.4.2");
        var axo = null, i = 0;
        for (i = 0; i < versions.length && !axo; i++) {
            var progid = item.progid + "." + versions[i] + ".0";
            axo = ca.rm.job.AxoHelper.getAxo(progid);
        }
        return axo ? versions[i] : null;
    }, QuickTime: function (item) {
        var axo = ca.rm.job.AxoHelper.getAxo(item.progid);
        if (!axo) {
            return null;
        }
        return axo.QuickTimeVersion ? axo.QuickTimeVersion : "";
    }, RealPlayer: function (item) {
        var axo = ca.rm.job.AxoHelper.findAxo(item.progid);
        if (!axo) {
            return null;
        }
        try {
            return axo.GetVersionInfo();
        } catch (e) {
            return "";
        }
    }, ShockWave: function (item) {
        var axo = ca.rm.job.AxoHelper.getAxo(item.progid);
        if (!axo) {
            return null;
        }
        try {
            return axo.GetVariable("$version");
        } catch (e) {
            return "";
        }
    }, SilverLight: function (item) {
        var axo = ca.rm.job.AxoHelper.getAxo(item.progid);
        if (!axo) {
            return null;
        }
        var major, minor, i, max = 20;
        i = 0;
        while (axo.isVersionSupported(i + ".0") && i < max) {
            i++;
        }
        major = --i;
        i = 0;
        while (axo.isVersionSupported(major + "." + i) && i < max) {
            i++;
        }
        var minor = --i;
        str = major + "." + minor;
        return str;
    }, 'VLC Player': function (item) {
        var axo = ca.rm.job.AxoHelper.getAxo(item.progid);
        if (!axo) {
            return null;
        }
        return axo.VersionInfo ? axo.VersionInfo : "";
    }, 'Windows Media Player': function (item) {
        var axo = ca.rm.job.AxoHelper.getAxo(item.progid);
        if (!axo) {
            return null;
        }
        return axo.VersionInfo ? axo.VersionInfo : "";
    }
};
ca.rm.job.AxoHelper = {
    findAxo: function (list) {
        var axo = null;
        for (var i = 0; i < list.length && !axo; i++) {
            axo = this.getAxo(list[i]);
        }
        return axo;
    }, getAxo: function (name) {
        var axo = null;
        try {
            axo = new ActiveXObject(name);
        } catch (e) {
        }
        return axo;
    }
};
ca.rm.job.AxoData = {
    'Adobe Acrobat': {
        clsid: "CA8A9780-280D-11CF-A24D-444553540000",
        mimes: ["application/pdf"],
        progid: ["AcroPDF.PDF", "PDF.PdfCtrl"]
    },
    Flash: {clsid: "166B1BCA-3F9C-11CF-8075-444553540000", mimes: ["application/x-director"], progid: "SWCtl.SWCtl"},
    Java: {
        clsid: "8AD9C840-044E-11D1-B3E9-00805F499D93",
        mimes: ["application/x-java-applet"],
        progid: "JavaWebStart.isInstalled",
        ext: ["1.7.0", "1.6.0", "1.5.0", "1.4.2"]
    },
    QuickTime: {
        clsid: "02BF25D5-8C17-4B23-BC80-D3488ABDDC6B",
        mimes: ["video/quicktime", "application/x-quicktimeplayer", "image/x-macpaint", "image/x-quicktime"],
        progid: "QuickTime.QuickTime"
    },
    RealPlayer: {
        clsid: "CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA",
        mimes: ["audio/x-pn-realaudio-plugin"],
        progid: ["rmocx.RealPlayer G2 Control", "rmocx.RealPlayer G2 Control.1", "RealPlayer.RealPlayer(tm) ActiveX Control (32-bit)", "RealVideo.RealVideo(tm) ActiveX Control (32-bit)", "RealPlayer"]
    },
    ShockWave: {
        clsid: "D27CDB6E-AE6D-11CF-96B8-444553540000",
        mimes: ["application/x-shockwave-flash"],
        progid: "ShockwaveFlash.ShockwaveFlash"
    },
    SilverLight: {mimes: ["application/x-silverlight"], progid: "AgControl.AgControl"},
    'VLC Player': {
        clsid: "9BE31822-FDAD-461B-AD51-BE1D1C159921",
        mimes: ["application/x-vlc-plugin"],
        progid: "VideoLAN.VLCPlugin"
    },
    'Windows Media Player': {
        clsid: "6BF52A52-394A-11D3-B153-00C04F79FAA6",
        mimes: ["application/x-mplayer2", "application/asx", "application/x-ms-wmp"],
        progid: "wmplayer.ocx"
    }
};
ca.rm.job.Browser = {
    process: function () {
        var info = {};
        info.UserAgent = navigator.userAgent;
        info.Vendor = navigator.vendor;
        info.VendorSubID = navigator.vendorSub;
        info.BuildID = navigator.buildID ? navigator.buildID : navigator.productSub;
        info.CookieEnabled = navigator.cookieEnabled;
        return info;
    }
};
ca.rm.job.ClientCaps = {
    ccID: "IEClientCaps", ccDiv: null, componentMap: {
        AddressBook: "{7790769C-0471-11D2-AF11-00C04FA35D02}",
        AolArtImageFormat: "{47F67D00-9E55-11D1-BAEF-00C04FC2D130}",
        BrowsingPack: "{3AF36230-A269-11D1-B5BF-0000F8051515}",
        DHTMLDataBinding: "{9381D8F2-0288-11D0-9501-00AA00B911A5}",
        DHTMLDataBindingJCLs: "{4F216970-C90C-11D1-B5C7-0000F8051515}",
        DirectAnimation: "{283807B5-2C60-11D0-A31D-00AA00B92C03}",
        DirectAnimationJCLs: "{4F216970-C90C-11D1-B5C7-0000F8051515}",
        DirectShow: "{44BBA848-CC51-11CF-AAFA-00AA00B6015C}",
        IEBrowser: "{89820200-ECBD-11CF-8B85-00AA005B4383}",
        IEBrowserEnhancements: "{630B1DA0-B465-11D1-9948-00C04F98BBC9}",
        IEHelp: "{45EA75A0-A269-11D1-B5BF-0000F8051515}",
        IEHelpEngine: "{DE5AED00-A4BF-11D1-9948-00C04F98BBC9}",
        IEJCLs: "{08B0E5C0-4FCB-11CF-AAA5-00401C608555}",
        InternetConnWizard: "{5A8D6EE0-3E18-11D0-821E-444553540000}",
        LanguageAutoSelection: "{76C19B50-F0C8-11CF-87CC-0020AFEECF20}",
        MacromediaFlash: "{D27CDB6E-AE6D-11CF-96B8-444553540000}",
        MacromediaShockwaveDirector: "{2A202491-F00D-11CF-87CC-0020AFEECF20}",
        MSVM: "{08B0E5C0-4FCB-11CF-AAA5-00401C608500}",
        NetMeetingNT: "{44BBA842-CC51-11CF-AAFA-00AA00B6015B}",
        OfflineBrowsingPack: "{3AF36230-A269-11D1-B5BF-0000F8051515}",
        OutlookExpress: "{44BBA840-CC51-11CF-AAFA-00AA00B6015C}",
        TaskScheduler: "{CC2A9BA0-3BDD-11D0-821E-444553540000}",
        TextArabic: "{76C19B38-F0C8-11CF-87CC-0020AFEECF20}",
        TextChineseSimplified: "{76C19B34-F0C8-11CF-87CC-0020AFEECF20}",
        TextChineseTraditional: "{76C19B33-F0C8-11CF-87CC-0020AFEECF20}",
        TextHebrew: "{76C19B36-F0C8-11CF-87CC-0020AFEECF20}",
        TextJapanese: "{76C19B30-F0C8-11CF-87CC-0020AFEECF20}",
        TextKorean: "{76C19B31-F0C8-11CF-87CC-0020AFEECF20}",
        TextPanEuropean: "{76C19B32-F0C8-11CF-87CC-0020AFEECF20}",
        TextThai: "{76C19B35-F0C8-11CF-87CC-0020AFEECF20}",
        TextVietnamese: "{76C19B37-F0C8-11CF-87CC-0020AFEECF20}",
        Uniscribe: "{3BF42070-B3B1-11D1-B5C5-0000F8051515}",
        VectorGraphicsRendering: "{10072CEC-8CC1-11D1-986E-00A0C955B42F}",
        VisualBasicScripting: "{4F645220-306D-11D2-995D-00C04F98BBC9}",
        VRML20Viewer: "{90A7533D-88FE-11D0-9DBE-0000C0411FC3}",
        Wallet: "{1CDEE860-E95B-11CF-B1B0-00AA00BBAD66}",
        WebFolders: "{73FA19D0-2D75-11D2-995D-00C04F98BBC9}",
        WindowsDektopUpdate: "{89820200-ECBD-11CF-8B85-00AA005B4340}",
        WindowsMediaPlayer: "{22D6F312-B0F6-11D0-94AB-0080C74C7E95}",
        WindowsMediaPlayerRealNetwork: "{23064720-C4F8-11D1-994D-00C04F98BBC9}"
    }, addClientCaps: function () {
        if (this.getClientCaps()) {
            return;
        }
        this.ccDiv = document.createElement("div");
        this.ccDiv.innerHTML = "<IE:clientCaps style=\"behavior:url(#default#clientcaps)\" id=\"" + this.ccID + "\"/>";
        document.body.appendChild(this.ccDiv);
    }, removeClientCaps: function () {
        if (!this.ccDiv) {
            return;
        }
        document.body.removeChild(this.ccDiv);
        this.ccDiv = null;
    }, getClientCaps: function () {
        return document.getElementById(this.ccID);
    }, process: function () {
        var info = {};
        var browserFamily = ca.base.util.Browser.getFamily();
        if (browserFamily != "MSIE") {
            return info;
        }
        var commonPluginKeys = ["Flash", "QuickTime", "Shockwave", "WindowsMediaPlayer", "Silverlight", "Java"];
        for (var i = 0; i < commonPluginKeys.length; i++) {
            var pluginName = commonPluginKeys[i];
            var pluginVersion = "";
            try {
                pluginVersion = PluginDetect.getVersion(pluginName);
            } catch (e1) {
                RMLogger.log(e1.message);
            }
            info[pluginName] = pluginVersion;
        }
        this.addClientCaps();
        var cc = this.getClientCaps();
        info.VBVersion = ScriptEngineMajorVersion() + "." + ScriptEngineMinorVersion() + "." + ScriptEngineBuildVersion();
        info.ConnectionType = cc.connectionType;
        for (var key in this.componentMap) {
            var val = cc.getComponentVersion(this.componentMap[key], "ComponentID");
            if (!val || val.length == 0) {
                continue;
            }
            info[key] = val;
        }
        this.removeClientCaps();
        return info;
    }
};
var FLASH_REQ_VERSION_MAJ = 8;
var FLASH_REQ_VERSION_MIN = 5;
var FLASH_REQ_VERSION_REV = 0;
ca.rm.job.Flash = {
    ID: "riskminderclient",
    FILE: "devicedna/riskminder-client.swf",
    VARS: "readyCallback=flashReadyCallback&errorCallback=flashErrorCallback",
    flashCreated: false,
    getCookie: function (cookieName, cookieStoreName) {
        var cookie = null;
        if (_rmclient_instance_.flashNotRequired()) {
            return null;
        }
        var obj = ca.base.util.Flash.getHandle(this.ID);
        if (!obj) {
            RMLogger.log("Error: Flash object unavailable");
            return null;
        }
        try {
            cookie = obj.getCookie(cookieName, cookieStoreName);
        } catch (e) {
            RMLogger.log("Error: Unable to invoke Flash methods");
            RMLogger.log("Error: " + e.name + " - " + e.message);
        }
        return cookie;
    },
    process: function () {
        var p;
        var info = {};
        if (_rmclient_instance_.flashNotRequired()) {
            return null;
        }
        var obj = ca.base.util.Flash.getHandle(this.ID);
        if (!obj) {
            RMLogger.log("Error: Flash object unavailable");
            return null;
        }
        try {
            info.Fonts = obj.getFontList();
            info.Camera = obj.getCameraList();
            info.Microphone = obj.getMicrophoneList();
            info.Capabilities = obj.getCapabilities();
        } catch (e) {
            RMLogger.log("Error: Unable to invoke Flash methods");
            RMLogger.log("Error: " + e.name + " - " + e.message);
        }
        return info;
    },
    init: function (callback) {
        if (_rmclient_instance_.flashNotRequired()) {
            return;
        }
        var fpv = ca.base.util.Flash.getFlashPlayerVersion();
        var fpreqv = new ca.base.util.FlashPlayerVersion([FLASH_REQ_VERSION_MAJ, FLASH_REQ_VERSION_MIN, FLASH_REQ_VERSION_REV]);
        var validFPVersion = fpv.versionIsValid(fpreqv);
        if (!validFPVersion) {
            callback(false);
            return;
        }
        var movieURL = _rmclient_instance_.getBaseURL() + "/" + this.FILE;
        if (!this.flashCreated) {
            ca.base.util.Flash.create(this.ID, movieURL, this.VARS);
            this.flashCreated = true;
        }
        checkFlashLoaded(0, callback);
    }
};
flashLoaded = false;
flashReadyCallback = function () {
    flashLoaded = true;
};
checkFlashLoaded = function (count, callback) {
    if (count > 4) {
        RMLogger.log("Error: Flash loading timed out");
        callback(false);
        return;
    }
    if (flashLoaded) {
        callback(true);
        return;
    }
    count++;
    setTimeout(function () {
        checkFlashLoaded(count, callback);
    }, 50);
};
ca.rm.job.Font = {
    fontList: ["cursive", "monospace", "serif", "sans-serif", "fantasy", "default", "Arial", "Arial Black", "Arial Narrow", "Arial Rounded MT Bold", "Bookman Old Style", "Bradley Hand ITC", "Century", "Century Gothic", "Comic Sans MS", "Courier", "Courier New", "Georgia", "Gentium", "Impact", "King", "Lucida Console", "Modena", "Monotype Corsiva", "Papyrus", "Tahoma", "TeX", "Times", "Times New Roman", "Trebuchet MS", "Verdana", "Verona"],
    process: function () {
        var info = new Array;
        var names;
        if (arguments.length < 1) {
            names = this.fontList;
        } else {
            names = arguments[0];
        }
        if (!names) {
            return info;
        }
        var detective = new ca.rm.job.Detector;
        var font;
        for (var i = 0; i < names.length; i++) {
            font = names[i];
            if (detective.detect(font)) {
                info.push(font);
            }
        }
        return info;
    }
};
ca.rm.job.Detector = function () {
    var baseFonts = ["monospace", "sans-serif", "serif"];
    var testString = "mmmmmmmmmmlli";
    var testSize = "72px";
    var h = document.getElementsByTagName("body")[0];
    var s = document.createElement("span");
    s.style.fontSize = testSize;
    s.innerHTML = testString;
    var defaultWidth = {};
    var defaultHeight = {};
    for (var index in baseFonts) {
        s.style.fontFamily = baseFonts[index];
        h.appendChild(s);
        defaultWidth[baseFonts[index]] = s.offsetWidth;
        defaultHeight[baseFonts[index]] = s.offsetHeight;
        h.removeChild(s);
    }

    function detect(font) {
        var detected = false;
        for (var index in baseFonts) {
            s.style.fontFamily = font + "," + baseFonts[index];
            h.appendChild(s);
            var matched = s.offsetWidth != defaultWidth[baseFonts[index]] || s.offsetHeight != defaultHeight[baseFonts[index]];
            h.removeChild(s);
            detected = detected || matched;
        }
        return detected;
    }

    this.detect = detect;
};
ca.rm.job.HMID = {
    process: function () {
        var info = {};
        var props;
        try {
            props = this.getHMID();
            if (props != null) {
                info.Version = props.version;
                info.MID = props.DNA;
            } else {
                return null;
            }
        } catch (e) {
            RMLogger.log("Error: " + e.name + " - " + e.message);
        }
        return info;
    }, getHMID: function () {
        var props;
        var status;
        var plugin = (new AuthMinderPlugin).getPlugin();
        if (plugin) {
            props = {version: "1", DNA: null};
            status = plugin.GetDeviceDNA(props);
            if (status == AuthMinderPlugin.E_SUCCESS) {
                return props;
            } else {
                RMLogger.log("HMID: AM Plugin returned error:" + status);
                return null;
            }
        } else {
            RMLogger.log("HMID: Unable to detect AM Plugin");
            return null;
        }
    }
};
ca.rm.job.MESC = {
    isMESCEnabled: true,
    mescInstance: null,
    calibrationStartTime: 0,
    runAgainTimerHandle: null,
    stopRunTimerHandle: null,
    mescIterationCount: 0,
    mescValue: null,
    mesccalibrationDuration: 0,
    mescmaxIterations: 0,
    mescintervalDelay: 0,
    stopNow: false,
    stopRun: function () {
        try {
            stopRunTimerHandle = null;
            var endTime = (new Date).getTime();
            var elapsedTime = endTime - calibrationStartTime;
            console.info("MESC stopRun --- updating mescValue with elapsed " + elapsedTime);
            this.mescValue = this.mescValue + ";ldi=" + elapsedTime;
        } catch (e) {
        }
    },
    calculateMESC: function () {
        var num_iter = 0;
        try {
            var currentTime = (new Date).getTime();
            var endTime = (new Date).getTime() + this.mesccalibrationDuration;
            while (currentTime < endTime) {
                num_iter++;
                currentTime = (new Date).getTime();
            }
        } catch (e) {
            RMLogger.log("Error: Unable to invoke MESC method");
            RMLogger.log("Error: " + e.name + " - " + e.message);
        }
        return num_iter;
    },
    getAverageMESC: function () {
        var numberOfSamples = 1;
        var average = 0;
        try {
            var total = 0;
            for (var i = 0; i < numberOfSamples; i++) {
                var sample = this.calculateMESC();
                total += sample;
            }
            average = Math.round(total / numberOfSamples);
        } catch (e) {
        }
        return average;
    },
    clearTimers: function () {
        stopNow = true;
        if (runAgainTimerHandle != null) {
            clearTimeout(runAgainTimerHandle);
            runAgainTimerHandle = null;
        }
        if (stopRunTimerHandle != null) {
            clearTimeout(stopRunTimerHandle);
            stopRun();
        }
    },
    newCollectMESCFunc: function () {
        this.mescIterationCount = 0;
        try {
            var newVal = this.getAverageMESC();
            this.mescValue += ";mesc=" + newVal;
            this.mescIterationCount += 1;
            while (this.mescIterationCount < this.mescmaxIterations) {
                var delayMaker = ca.base.util.RMSleep;
                delayMaker.sleep(this.mescintervalDelay);
                newVal = this.getAverageMESC();
                this.mescValue += ";mesc=" + newVal;
                this.mescIterationCount += 1;
            }
        } catch (e) {
        }
    },
    process: function () {
        var info = {};
        this.mescmaxIterations = _rmclient_instance_.getMescMaxIters();
        this.mesccalibrationDuration = _rmclient_instance_.getMescCalibrationDuration();
        this.mescintervalDelay = _rmclient_instance_.getMescIntervalDelay();
        this.mescValue = "mi=" + this.mescmaxIterations + ";cd=" + this.mesccalibrationDuration + ";id=" + this.mescintervalDelay;
        try {
            this.newCollectMESCFunc();
            info.mesc = this.mescValue;
            return info;
        } catch (e) {
        }
    }
};
ca.rm.job.Plugin = {
    process: function () {
        var info = {};
        if (typeof google != "undefined") {
            try {
                var gearsVersion = google.gears.factory.getBuildInfo();
                info.Gears = gearsVersion;
            } catch (e) {
            }
        }
        if (typeof crypto != "undefined" && crypto.version) {
            info['Personal Security Manager'] = crypto.version;
        }
        if (!navigator.plugins || !navigator.plugins.length) {
            return info;
        }
        var regex = /\d+([\.|_|-|,]\d+)+/g;
        var str, match, version;
        for (var i = 0; i < navigator.plugins.length; i++) {
            var plugin = navigator.plugins[i];
            if (plugin.version) {
                version = plugin.version;
            } else {
                str = plugin.name + " | " + plugin.description;
                match = str.match(regex);
                version = match ? match[0] : "";
            }
            info[plugin.name] = version;
        }
        return info;
    }, getJSVersion: function () {
        var parent = document.getElementsByTagName("head")[0] || document.getElementsByTagName("body")[0];
        for (var i = 0; i < 9; ++i) {
            var script = document.createElement("script");
            if (i == 0) {
                script.language = "Javascript";
            } else {
                script.language = "Javascript1." + i;
            }
            script.text = "caJsVersion = 1." + i + ";";
            parent.appendChild(script);
            parent.removeChild(script);
        }
        return caJsVersion;
    }, getVersionFromString: function (str) {
    }
};
ca.rm.job.Screen = {
    process: function () {
        var info = {};
        info.FullHeight = screen.height;
        info.AvlHeight = screen.availHeight;
        info.FullWidth = screen.width;
        info.AvlWidth = screen.availWidth;
        info.BufferDepth = screen.bufferDepth;
        info.ColorDepth = screen.colorDepth;
        info.PixelDepth = screen.pixelDepth;
        info.DeviceXDPI = screen.deviceXDPI;
        info.DeviceYDPI = screen.deviceYDPI;
        info.FontSmoothing = screen.fontSmoothingEnabled;
        info.UpdateInterval = screen.updateInterval;
        return info;
    }
};
ca.rm.job.System = {
    process: function () {
        var info = {};
        info.Platform = navigator.platform;
        info.OSCPU = navigator.oscpu ? navigator.oscpu : navigator.cpuClass;
        info.systemLanguage = navigator.language;
        info.userLanguage = navigator.userLanguage;
        info.Timezone = (new Date).getTimezoneOffset();
        return info;
    }
};
ca.rm.store.StoreDID = function (props) {
    this.devicelocker = props && "devicelocker" in props ? props.devicelocker : null;
    StoreBase.call(this, props);
};
ca.rm.store.StoreDID.prototype = new StoreBase({inheriting: true});
ca.rm.store.StoreDID.base = StoreBase.prototype;
ca.rm.store.StoreDID.prototype.serialize = function (did) {
    return escape(did);
};
ca.rm.store.StoreDID.prototype.deserialize = function (didStr) {
    return unescape(didStr);
};
ca.rm.store.StoreDID.prototype.isValidEntry = function (key, data) {
    if (this.didname != "undefined" && this.didname != null && key == this.didname) {
        return true;
    } else {
        return false;
    }
};
ca.rm.store.StoreDID.prototype.setDIDName = function (name) {
    this.didname = name;
    ca.rm.store.StoreDID.base.migrate.call(this);
};