var Lab6Socket = (() => {
  var __create = Object.create;
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __getProtoOf = Object.getPrototypeOf;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __commonJS = (cb, mod) => function __require() {
    return mod || (0, cb[__getOwnPropNames(cb)[0]])((mod = { exports: {} }).exports, mod), mod.exports;
  };
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
    // If the importer is in node compatibility mode or this is not an ESM
    // file that has been converted to a CommonJS file using a Babel-
    // compatible transform (i.e. "__esModule" has not been set), then set
    // "default" to the CommonJS "module.exports" for node compatibility.
    isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
    mod
  ));
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // node_modules/pusher-js/dist/web/pusher.js
  var require_pusher = __commonJS({
    "node_modules/pusher-js/dist/web/pusher.js"(exports, module) {
      (function webpackUniversalModuleDefinition(root, factory) {
        if (typeof exports === "object" && typeof module === "object")
          module.exports = factory();
        else if (typeof define === "function" && define.amd)
          define([], factory);
        else if (typeof exports === "object")
          exports["Pusher"] = factory();
        else
          root["Pusher"] = factory();
      })(self, () => {
        return (
          /******/
          (() => {
            var __webpack_modules__ = {
              /***/
              594(__unused_webpack_module, exports2) {
                "use strict";
                var __extends = this && this.__extends || /* @__PURE__ */ (function() {
                  var extendStatics = function(d2, b2) {
                    extendStatics = Object.setPrototypeOf || { __proto__: [] } instanceof Array && function(d3, b3) {
                      d3.__proto__ = b3;
                    } || function(d3, b3) {
                      for (var p2 in b3) if (b3.hasOwnProperty(p2)) d3[p2] = b3[p2];
                    };
                    return extendStatics(d2, b2);
                  };
                  return function(d2, b2) {
                    extendStatics(d2, b2);
                    function __() {
                      this.constructor = d2;
                    }
                    d2.prototype = b2 === null ? Object.create(b2) : (__.prototype = b2.prototype, new __());
                  };
                })();
                Object.defineProperty(exports2, "__esModule", { value: true });
                var INVALID_BYTE = 256;
                var Coder = (
                  /** @class */
                  (function() {
                    function Coder2(_paddingCharacter) {
                      if (_paddingCharacter === void 0) {
                        _paddingCharacter = "=";
                      }
                      this._paddingCharacter = _paddingCharacter;
                    }
                    Coder2.prototype.encodedLength = function(length) {
                      if (!this._paddingCharacter) {
                        return (length * 8 + 5) / 6 | 0;
                      }
                      return (length + 2) / 3 * 4 | 0;
                    };
                    Coder2.prototype.encode = function(data) {
                      var out = "";
                      var i2 = 0;
                      for (; i2 < data.length - 2; i2 += 3) {
                        var c2 = data[i2] << 16 | data[i2 + 1] << 8 | data[i2 + 2];
                        out += this._encodeByte(c2 >>> 3 * 6 & 63);
                        out += this._encodeByte(c2 >>> 2 * 6 & 63);
                        out += this._encodeByte(c2 >>> 1 * 6 & 63);
                        out += this._encodeByte(c2 >>> 0 * 6 & 63);
                      }
                      var left = data.length - i2;
                      if (left > 0) {
                        var c2 = data[i2] << 16 | (left === 2 ? data[i2 + 1] << 8 : 0);
                        out += this._encodeByte(c2 >>> 3 * 6 & 63);
                        out += this._encodeByte(c2 >>> 2 * 6 & 63);
                        if (left === 2) {
                          out += this._encodeByte(c2 >>> 1 * 6 & 63);
                        } else {
                          out += this._paddingCharacter || "";
                        }
                        out += this._paddingCharacter || "";
                      }
                      return out;
                    };
                    Coder2.prototype.maxDecodedLength = function(length) {
                      if (!this._paddingCharacter) {
                        return (length * 6 + 7) / 8 | 0;
                      }
                      return length / 4 * 3 | 0;
                    };
                    Coder2.prototype.decodedLength = function(s2) {
                      return this.maxDecodedLength(s2.length - this._getPaddingLength(s2));
                    };
                    Coder2.prototype.decode = function(s2) {
                      if (s2.length === 0) {
                        return new Uint8Array(0);
                      }
                      var paddingLength = this._getPaddingLength(s2);
                      var length = s2.length - paddingLength;
                      var out = new Uint8Array(this.maxDecodedLength(length));
                      var op = 0;
                      var i2 = 0;
                      var haveBad = 0;
                      var v0 = 0, v1 = 0, v2 = 0, v3 = 0;
                      for (; i2 < length - 4; i2 += 4) {
                        v0 = this._decodeChar(s2.charCodeAt(i2 + 0));
                        v1 = this._decodeChar(s2.charCodeAt(i2 + 1));
                        v2 = this._decodeChar(s2.charCodeAt(i2 + 2));
                        v3 = this._decodeChar(s2.charCodeAt(i2 + 3));
                        out[op++] = v0 << 2 | v1 >>> 4;
                        out[op++] = v1 << 4 | v2 >>> 2;
                        out[op++] = v2 << 6 | v3;
                        haveBad |= v0 & INVALID_BYTE;
                        haveBad |= v1 & INVALID_BYTE;
                        haveBad |= v2 & INVALID_BYTE;
                        haveBad |= v3 & INVALID_BYTE;
                      }
                      if (i2 < length - 1) {
                        v0 = this._decodeChar(s2.charCodeAt(i2));
                        v1 = this._decodeChar(s2.charCodeAt(i2 + 1));
                        out[op++] = v0 << 2 | v1 >>> 4;
                        haveBad |= v0 & INVALID_BYTE;
                        haveBad |= v1 & INVALID_BYTE;
                      }
                      if (i2 < length - 2) {
                        v2 = this._decodeChar(s2.charCodeAt(i2 + 2));
                        out[op++] = v1 << 4 | v2 >>> 2;
                        haveBad |= v2 & INVALID_BYTE;
                      }
                      if (i2 < length - 3) {
                        v3 = this._decodeChar(s2.charCodeAt(i2 + 3));
                        out[op++] = v2 << 6 | v3;
                        haveBad |= v3 & INVALID_BYTE;
                      }
                      if (haveBad !== 0) {
                        throw new Error("Base64Coder: incorrect characters for decoding");
                      }
                      return out;
                    };
                    Coder2.prototype._encodeByte = function(b2) {
                      var result = b2;
                      result += 65;
                      result += 25 - b2 >>> 8 & 0 - 65 - 26 + 97;
                      result += 51 - b2 >>> 8 & 26 - 97 - 52 + 48;
                      result += 61 - b2 >>> 8 & 52 - 48 - 62 + 43;
                      result += 62 - b2 >>> 8 & 62 - 43 - 63 + 47;
                      return String.fromCharCode(result);
                    };
                    Coder2.prototype._decodeChar = function(c2) {
                      var result = INVALID_BYTE;
                      result += (42 - c2 & c2 - 44) >>> 8 & -INVALID_BYTE + c2 - 43 + 62;
                      result += (46 - c2 & c2 - 48) >>> 8 & -INVALID_BYTE + c2 - 47 + 63;
                      result += (47 - c2 & c2 - 58) >>> 8 & -INVALID_BYTE + c2 - 48 + 52;
                      result += (64 - c2 & c2 - 91) >>> 8 & -INVALID_BYTE + c2 - 65 + 0;
                      result += (96 - c2 & c2 - 123) >>> 8 & -INVALID_BYTE + c2 - 97 + 26;
                      return result;
                    };
                    Coder2.prototype._getPaddingLength = function(s2) {
                      var paddingLength = 0;
                      if (this._paddingCharacter) {
                        for (var i2 = s2.length - 1; i2 >= 0; i2--) {
                          if (s2[i2] !== this._paddingCharacter) {
                            break;
                          }
                          paddingLength++;
                        }
                        if (s2.length < 4 || paddingLength > 2) {
                          throw new Error("Base64Coder: incorrect padding");
                        }
                      }
                      return paddingLength;
                    };
                    return Coder2;
                  })()
                );
                exports2.Coder = Coder;
                var stdCoder = new Coder();
                function encode(data) {
                  return stdCoder.encode(data);
                }
                exports2.encode = encode;
                function decode(s2) {
                  return stdCoder.decode(s2);
                }
                exports2.decode = decode;
                var URLSafeCoder = (
                  /** @class */
                  (function(_super) {
                    __extends(URLSafeCoder2, _super);
                    function URLSafeCoder2() {
                      return _super !== null && _super.apply(this, arguments) || this;
                    }
                    URLSafeCoder2.prototype._encodeByte = function(b2) {
                      var result = b2;
                      result += 65;
                      result += 25 - b2 >>> 8 & 0 - 65 - 26 + 97;
                      result += 51 - b2 >>> 8 & 26 - 97 - 52 + 48;
                      result += 61 - b2 >>> 8 & 52 - 48 - 62 + 45;
                      result += 62 - b2 >>> 8 & 62 - 45 - 63 + 95;
                      return String.fromCharCode(result);
                    };
                    URLSafeCoder2.prototype._decodeChar = function(c2) {
                      var result = INVALID_BYTE;
                      result += (44 - c2 & c2 - 46) >>> 8 & -INVALID_BYTE + c2 - 45 + 62;
                      result += (94 - c2 & c2 - 96) >>> 8 & -INVALID_BYTE + c2 - 95 + 63;
                      result += (47 - c2 & c2 - 58) >>> 8 & -INVALID_BYTE + c2 - 48 + 52;
                      result += (64 - c2 & c2 - 91) >>> 8 & -INVALID_BYTE + c2 - 65 + 0;
                      result += (96 - c2 & c2 - 123) >>> 8 & -INVALID_BYTE + c2 - 97 + 26;
                      return result;
                    };
                    return URLSafeCoder2;
                  })(Coder)
                );
                exports2.URLSafeCoder = URLSafeCoder;
                var urlSafeCoder = new URLSafeCoder();
                function encodeURLSafe(data) {
                  return urlSafeCoder.encode(data);
                }
                exports2.encodeURLSafe = encodeURLSafe;
                function decodeURLSafe(s2) {
                  return urlSafeCoder.decode(s2);
                }
                exports2.decodeURLSafe = decodeURLSafe;
                exports2.encodedLength = function(length) {
                  return stdCoder.encodedLength(length);
                };
                exports2.maxDecodedLength = function(length) {
                  return stdCoder.maxDecodedLength(length);
                };
                exports2.decodedLength = function(s2) {
                  return stdCoder.decodedLength(s2);
                };
              },
              /***/
              978(__unused_webpack_module, exports2) {
                "use strict";
                var __webpack_unused_export__;
                __webpack_unused_export__ = { value: true };
                var INVALID_UTF16 = "utf8: invalid string";
                var INVALID_UTF8 = "utf8: invalid source encoding";
                function encode(s2) {
                  var arr = new Uint8Array(encodedLength(s2));
                  var pos = 0;
                  for (var i2 = 0; i2 < s2.length; i2++) {
                    var c2 = s2.charCodeAt(i2);
                    if (c2 < 128) {
                      arr[pos++] = c2;
                    } else if (c2 < 2048) {
                      arr[pos++] = 192 | c2 >> 6;
                      arr[pos++] = 128 | c2 & 63;
                    } else if (c2 < 55296) {
                      arr[pos++] = 224 | c2 >> 12;
                      arr[pos++] = 128 | c2 >> 6 & 63;
                      arr[pos++] = 128 | c2 & 63;
                    } else {
                      i2++;
                      c2 = (c2 & 1023) << 10;
                      c2 |= s2.charCodeAt(i2) & 1023;
                      c2 += 65536;
                      arr[pos++] = 240 | c2 >> 18;
                      arr[pos++] = 128 | c2 >> 12 & 63;
                      arr[pos++] = 128 | c2 >> 6 & 63;
                      arr[pos++] = 128 | c2 & 63;
                    }
                  }
                  return arr;
                }
                __webpack_unused_export__ = encode;
                function encodedLength(s2) {
                  var result = 0;
                  for (var i2 = 0; i2 < s2.length; i2++) {
                    var c2 = s2.charCodeAt(i2);
                    if (c2 < 128) {
                      result += 1;
                    } else if (c2 < 2048) {
                      result += 2;
                    } else if (c2 < 55296) {
                      result += 3;
                    } else if (c2 <= 57343) {
                      if (i2 >= s2.length - 1) {
                        throw new Error(INVALID_UTF16);
                      }
                      i2++;
                      result += 4;
                    } else {
                      throw new Error(INVALID_UTF16);
                    }
                  }
                  return result;
                }
                __webpack_unused_export__ = encodedLength;
                function decode(arr) {
                  var chars = [];
                  for (var i2 = 0; i2 < arr.length; i2++) {
                    var b2 = arr[i2];
                    if (b2 & 128) {
                      var min = void 0;
                      if (b2 < 224) {
                        if (i2 >= arr.length) {
                          throw new Error(INVALID_UTF8);
                        }
                        var n1 = arr[++i2];
                        if ((n1 & 192) !== 128) {
                          throw new Error(INVALID_UTF8);
                        }
                        b2 = (b2 & 31) << 6 | n1 & 63;
                        min = 128;
                      } else if (b2 < 240) {
                        if (i2 >= arr.length - 1) {
                          throw new Error(INVALID_UTF8);
                        }
                        var n1 = arr[++i2];
                        var n2 = arr[++i2];
                        if ((n1 & 192) !== 128 || (n2 & 192) !== 128) {
                          throw new Error(INVALID_UTF8);
                        }
                        b2 = (b2 & 15) << 12 | (n1 & 63) << 6 | n2 & 63;
                        min = 2048;
                      } else if (b2 < 248) {
                        if (i2 >= arr.length - 2) {
                          throw new Error(INVALID_UTF8);
                        }
                        var n1 = arr[++i2];
                        var n2 = arr[++i2];
                        var n3 = arr[++i2];
                        if ((n1 & 192) !== 128 || (n2 & 192) !== 128 || (n3 & 192) !== 128) {
                          throw new Error(INVALID_UTF8);
                        }
                        b2 = (b2 & 15) << 18 | (n1 & 63) << 12 | (n2 & 63) << 6 | n3 & 63;
                        min = 65536;
                      } else {
                        throw new Error(INVALID_UTF8);
                      }
                      if (b2 < min || b2 >= 55296 && b2 <= 57343) {
                        throw new Error(INVALID_UTF8);
                      }
                      if (b2 >= 65536) {
                        if (b2 > 1114111) {
                          throw new Error(INVALID_UTF8);
                        }
                        b2 -= 65536;
                        chars.push(String.fromCharCode(55296 | b2 >> 10));
                        b2 = 56320 | b2 & 1023;
                      }
                    }
                    chars.push(String.fromCharCode(b2));
                  }
                  return chars.join("");
                }
                exports2.D4 = decode;
              },
              /***/
              721(module2, __unused_webpack_exports, __webpack_require__2) {
                module2.exports = __webpack_require__2(207)["default"];
              },
              /***/
              207(__unused_webpack_module, __webpack_exports__2, __webpack_require__2) {
                "use strict";
                __webpack_require__2.d(__webpack_exports__2, {
                  "default": () => (
                    /* binding */
                    pusher
                  )
                });
                ;
                class ScriptReceiverFactory {
                  constructor(prefix2, name) {
                    this.lastId = 0;
                    this.prefix = prefix2;
                    this.name = name;
                  }
                  create(callback) {
                    this.lastId++;
                    var number = this.lastId;
                    var id = this.prefix + number;
                    var name = this.name + "[" + number + "]";
                    var called = false;
                    var callbackWrapper = function() {
                      if (!called) {
                        callback.apply(null, arguments);
                        called = true;
                      }
                    };
                    this[number] = callbackWrapper;
                    return { number, id, name, callback: callbackWrapper };
                  }
                  remove(receiver) {
                    delete this[receiver.number];
                  }
                }
                var ScriptReceivers = new ScriptReceiverFactory("_pusher_script_", "Pusher.ScriptReceivers");
                ;
                var Defaults = {
                  VERSION: "8.6.0",
                  PROTOCOL: 7,
                  wsPort: 80,
                  wssPort: 443,
                  wsPath: "",
                  httpHost: "sockjs.pusher.com",
                  httpPort: 80,
                  httpsPort: 443,
                  httpPath: "/pusher",
                  stats_host: "stats.pusher.com",
                  authEndpoint: "/pusher/auth",
                  authTransport: "ajax",
                  activityTimeout: 12e4,
                  pongTimeout: 3e4,
                  unavailableTimeout: 1e4,
                  userAuthentication: {
                    endpoint: "/pusher/user-auth",
                    transport: "ajax"
                  },
                  channelAuthorization: {
                    endpoint: "/pusher/auth",
                    transport: "ajax"
                  },
                  cdn_http: "http://js.pusher.com",
                  cdn_https: "https://js.pusher.com",
                  dependency_suffix: ""
                };
                const defaults = Defaults;
                ;
                class DependencyLoader {
                  constructor(options) {
                    this.options = options;
                    this.receivers = options.receivers || ScriptReceivers;
                    this.loading = {};
                  }
                  load(name, options, callback) {
                    var self2 = this;
                    if (self2.loading[name] && self2.loading[name].length > 0) {
                      self2.loading[name].push(callback);
                    } else {
                      self2.loading[name] = [callback];
                      var request = runtime.createScriptRequest(self2.getPath(name, options));
                      var receiver = self2.receivers.create(function(error) {
                        self2.receivers.remove(receiver);
                        if (self2.loading[name]) {
                          var callbacks = self2.loading[name];
                          delete self2.loading[name];
                          var successCallback = function(wasSuccessful) {
                            if (!wasSuccessful) {
                              request.cleanup();
                            }
                          };
                          for (var i3 = 0; i3 < callbacks.length; i3++) {
                            callbacks[i3](error, successCallback);
                          }
                        }
                      });
                      request.send(receiver);
                    }
                  }
                  getRoot(options) {
                    var cdn;
                    var protocol2 = runtime.getDocument().location.protocol;
                    if (options && options.useTLS || protocol2 === "https:") {
                      cdn = this.options.cdn_https;
                    } else {
                      cdn = this.options.cdn_http;
                    }
                    return cdn.replace(/\/*$/, "") + "/" + this.options.version;
                  }
                  getPath(name, options) {
                    return this.getRoot(options) + "/" + name + this.options.suffix + ".js";
                  }
                }
                ;
                var DependenciesReceivers = new ScriptReceiverFactory("_pusher_dependencies", "Pusher.DependenciesReceivers");
                var Dependencies = new DependencyLoader({
                  cdn_http: defaults.cdn_http,
                  cdn_https: defaults.cdn_https,
                  version: defaults.VERSION,
                  suffix: defaults.dependency_suffix,
                  receivers: DependenciesReceivers
                });
                ;
                const urlStore = {
                  baseUrl: "https://pusher.com",
                  urls: {
                    authenticationEndpoint: {
                      path: "/docs/channels/server_api/authenticating_users"
                    },
                    authorizationEndpoint: {
                      path: "/docs/channels/server_api/authorizing-users/"
                    },
                    javascriptQuickStart: {
                      path: "/docs/javascript_quick_start"
                    },
                    triggeringClientEvents: {
                      path: "/docs/client_api_guide/client_events#trigger-events"
                    },
                    encryptedChannelSupport: {
                      fullUrl: "https://github.com/pusher/pusher-js/tree/cc491015371a4bde5743d1c87a0fbac0feb53195#encrypted-channel-support"
                    }
                  }
                };
                const buildLogSuffix = function(key) {
                  const urlPrefix = "See:";
                  const urlObj = urlStore.urls[key];
                  if (!urlObj)
                    return "";
                  let url;
                  if (urlObj.fullUrl) {
                    url = urlObj.fullUrl;
                  } else if (urlObj.path) {
                    url = urlStore.baseUrl + urlObj.path;
                  }
                  if (!url)
                    return "";
                  return `${urlPrefix} ${url}`;
                };
                const url_store = { buildLogSuffix };
                ;
                var AuthRequestType;
                (function(AuthRequestType2) {
                  AuthRequestType2["UserAuthentication"] = "user-authentication";
                  AuthRequestType2["ChannelAuthorization"] = "channel-authorization";
                })(AuthRequestType || (AuthRequestType = {}));
                ;
                class BadEventName extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class BadChannelName extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class RequestTimedOut extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class TransportPriorityTooLow extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class TransportClosed extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class UnsupportedFeature extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class UnsupportedTransport extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class UnsupportedStrategy extends Error {
                  constructor(msg) {
                    super(msg);
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                class HTTPAuthError extends Error {
                  constructor(status, msg) {
                    super(msg);
                    this.status = status;
                    Object.setPrototypeOf(this, new.target.prototype);
                  }
                }
                ;
                const ajax = function(context, query, authOptions, authRequestType, callback) {
                  const xhr = runtime.createXHR();
                  xhr.open("POST", authOptions.endpoint, true);
                  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                  for (var headerName in authOptions.headers) {
                    xhr.setRequestHeader(headerName, authOptions.headers[headerName]);
                  }
                  if (authOptions.headersProvider != null) {
                    let dynamicHeaders = authOptions.headersProvider();
                    for (var headerName in dynamicHeaders) {
                      xhr.setRequestHeader(headerName, dynamicHeaders[headerName]);
                    }
                  }
                  xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                      if (xhr.status === 200) {
                        let data;
                        let parsed = false;
                        try {
                          data = JSON.parse(xhr.responseText);
                          parsed = true;
                        } catch (e3) {
                          callback(new HTTPAuthError(200, `JSON returned from ${authRequestType.toString()} endpoint was invalid, yet status code was 200. Data was: ${xhr.responseText}`), null);
                        }
                        if (parsed) {
                          callback(null, data);
                        }
                      } else {
                        let suffix = "";
                        switch (authRequestType) {
                          case AuthRequestType.UserAuthentication:
                            suffix = url_store.buildLogSuffix("authenticationEndpoint");
                            break;
                          case AuthRequestType.ChannelAuthorization:
                            suffix = `Clients must be authorized to join private or presence channels. ${url_store.buildLogSuffix("authorizationEndpoint")}`;
                            break;
                        }
                        callback(new HTTPAuthError(xhr.status, `Unable to retrieve auth string from ${authRequestType.toString()} endpoint - received status: ${xhr.status} from ${authOptions.endpoint}. ${suffix}`), null);
                      }
                    }
                  };
                  xhr.send(query);
                  return xhr;
                };
                const xhr_auth = ajax;
                ;
                function encode(s2) {
                  return btoa2(utob(s2));
                }
                var fromCharCode = String.fromCharCode;
                var b64chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
                var b64tab = {};
                for (var i2 = 0, l2 = b64chars.length; i2 < l2; i2++) {
                  b64tab[b64chars.charAt(i2)] = i2;
                }
                var cb_utob = function(c2) {
                  var cc = c2.charCodeAt(0);
                  return cc < 128 ? c2 : cc < 2048 ? fromCharCode(192 | cc >>> 6) + fromCharCode(128 | cc & 63) : fromCharCode(224 | cc >>> 12 & 15) + fromCharCode(128 | cc >>> 6 & 63) + fromCharCode(128 | cc & 63);
                };
                var utob = function(u2) {
                  return u2.replace(/[^\x00-\x7F]/g, cb_utob);
                };
                var cb_encode = function(ccc) {
                  var padlen = [0, 2, 1][ccc.length % 3];
                  var ord = ccc.charCodeAt(0) << 16 | (ccc.length > 1 ? ccc.charCodeAt(1) : 0) << 8 | (ccc.length > 2 ? ccc.charCodeAt(2) : 0);
                  var chars = [
                    b64chars.charAt(ord >>> 18),
                    b64chars.charAt(ord >>> 12 & 63),
                    padlen >= 2 ? "=" : b64chars.charAt(ord >>> 6 & 63),
                    padlen >= 1 ? "=" : b64chars.charAt(ord & 63)
                  ];
                  return chars.join("");
                };
                var btoa2 = typeof window !== "undefined" && window.btoa || function(b2) {
                  return b2.replace(/[\s\S]{1,3}/g, cb_encode);
                };
                ;
                class Timer {
                  constructor(set, clear, delay, callback) {
                    this.clear = clear;
                    this.timer = set(() => {
                      if (this.timer) {
                        this.timer = callback(this.timer);
                      }
                    }, delay);
                  }
                  isRunning() {
                    return this.timer !== null;
                  }
                  ensureAborted() {
                    if (this.timer) {
                      this.clear(this.timer);
                      this.timer = null;
                    }
                  }
                }
                const abstract_timer = Timer;
                ;
                function timers_clearTimeout(timer) {
                  window.clearTimeout(timer);
                }
                function timers_clearInterval(timer) {
                  window.clearInterval(timer);
                }
                class OneOffTimer extends abstract_timer {
                  constructor(delay, callback) {
                    super(setTimeout, timers_clearTimeout, delay, function(timer) {
                      callback();
                      return null;
                    });
                  }
                }
                class PeriodicTimer extends abstract_timer {
                  constructor(delay, callback) {
                    super(setInterval, timers_clearInterval, delay, function(timer) {
                      callback();
                      return timer;
                    });
                  }
                }
                ;
                var Util = {
                  now() {
                    if (Date.now) {
                      return Date.now();
                    } else {
                      return (/* @__PURE__ */ new Date()).valueOf();
                    }
                  },
                  defer(callback) {
                    return new OneOffTimer(0, callback);
                  },
                  method(name, ...args) {
                    var boundArguments = Array.prototype.slice.call(arguments, 1);
                    return function(object) {
                      return object[name].apply(object, boundArguments.concat(arguments));
                    };
                  }
                };
                const util = Util;
                ;
                function extend(target, ...sources) {
                  for (var i3 = 0; i3 < sources.length; i3++) {
                    var extensions = sources[i3];
                    for (var property in extensions) {
                      if (property === "__proto__" || property === "constructor" || property === "prototype") {
                        continue;
                      }
                      if (extensions[property] && extensions[property].constructor && extensions[property].constructor === Object) {
                        target[property] = extend(target[property] || {}, extensions[property]);
                      } else {
                        target[property] = extensions[property];
                      }
                    }
                  }
                  return target;
                }
                function stringify() {
                  var m2 = ["Pusher"];
                  for (var i3 = 0; i3 < arguments.length; i3++) {
                    if (typeof arguments[i3] === "string") {
                      m2.push(arguments[i3]);
                    } else {
                      m2.push(safeJSONStringify(arguments[i3]));
                    }
                  }
                  return m2.join(" : ");
                }
                function arrayIndexOf(array, item) {
                  var nativeIndexOf = Array.prototype.indexOf;
                  if (array === null) {
                    return -1;
                  }
                  if (nativeIndexOf && array.indexOf === nativeIndexOf) {
                    return array.indexOf(item);
                  }
                  for (var i3 = 0, l3 = array.length; i3 < l3; i3++) {
                    if (array[i3] === item) {
                      return i3;
                    }
                  }
                  return -1;
                }
                function objectApply(object, f2) {
                  for (var key in object) {
                    if (Object.prototype.hasOwnProperty.call(object, key)) {
                      f2(object[key], key, object);
                    }
                  }
                }
                function keys(object) {
                  var keys2 = [];
                  objectApply(object, function(_2, key) {
                    keys2.push(key);
                  });
                  return keys2;
                }
                function values(object) {
                  var values2 = [];
                  objectApply(object, function(value) {
                    values2.push(value);
                  });
                  return values2;
                }
                function apply(array, f2, context) {
                  for (var i3 = 0; i3 < array.length; i3++) {
                    f2.call(context || window, array[i3], i3, array);
                  }
                }
                function map(array, f2) {
                  var result = [];
                  for (var i3 = 0; i3 < array.length; i3++) {
                    result.push(f2(array[i3], i3, array, result));
                  }
                  return result;
                }
                function mapObject(object, f2) {
                  var result = {};
                  objectApply(object, function(value, key) {
                    result[key] = f2(value);
                  });
                  return result;
                }
                function filter(array, test) {
                  test = test || function(value) {
                    return !!value;
                  };
                  var result = [];
                  for (var i3 = 0; i3 < array.length; i3++) {
                    if (test(array[i3], i3, array, result)) {
                      result.push(array[i3]);
                    }
                  }
                  return result;
                }
                function filterObject(object, test) {
                  var result = {};
                  objectApply(object, function(value, key) {
                    if (test && test(value, key, object, result) || Boolean(value)) {
                      result[key] = value;
                    }
                  });
                  return result;
                }
                function flatten(object) {
                  var result = [];
                  objectApply(object, function(value, key) {
                    result.push([key, value]);
                  });
                  return result;
                }
                function any(array, test) {
                  for (var i3 = 0; i3 < array.length; i3++) {
                    if (test(array[i3], i3, array)) {
                      return true;
                    }
                  }
                  return false;
                }
                function collections_all(array, test) {
                  for (var i3 = 0; i3 < array.length; i3++) {
                    if (!test(array[i3], i3, array)) {
                      return false;
                    }
                  }
                  return true;
                }
                function encodeParamsObject(data) {
                  return mapObject(data, function(value) {
                    if (value === null) {
                      return "";
                    }
                    if (typeof value === "object") {
                      value = safeJSONStringify(value);
                    }
                    return encodeURIComponent(encode(value.toString()));
                  });
                }
                function buildQueryString(data) {
                  var params = filterObject(data, function(value) {
                    return value !== void 0;
                  });
                  var query = map(flatten(encodeParamsObject(params)), util.method("join", "=")).join("&");
                  return query;
                }
                function decycleObject(object) {
                  var objects = [], paths = [];
                  return (function derez(value, path) {
                    var i3, name, nu;
                    switch (typeof value) {
                      case "object":
                        if (!value) {
                          return null;
                        }
                        for (i3 = 0; i3 < objects.length; i3 += 1) {
                          if (objects[i3] === value) {
                            return { $ref: paths[i3] };
                          }
                        }
                        objects.push(value);
                        paths.push(path);
                        if (Object.prototype.toString.apply(value) === "[object Array]") {
                          nu = [];
                          for (i3 = 0; i3 < value.length; i3 += 1) {
                            nu[i3] = derez(value[i3], path + "[" + i3 + "]");
                          }
                        } else {
                          nu = {};
                          for (name in value) {
                            if (Object.prototype.hasOwnProperty.call(value, name)) {
                              nu[name] = derez(value[name], path + "[" + JSON.stringify(name) + "]");
                            }
                          }
                        }
                        return nu;
                      case "number":
                      case "string":
                      case "boolean":
                        return value;
                    }
                  })(object, "$");
                }
                function safeJSONStringify(source) {
                  try {
                    return JSON.stringify(source);
                  } catch (e3) {
                    return JSON.stringify(decycleObject(source));
                  }
                }
                ;
                class Logger {
                  constructor() {
                    this.globalLog = (message) => {
                      if (window.console && window.console.log) {
                        window.console.log(message);
                      }
                    };
                  }
                  debug(...args) {
                    this.log(this.globalLog, args);
                  }
                  warn(...args) {
                    this.log(this.globalLogWarn, args);
                  }
                  error(...args) {
                    this.log(this.globalLogError, args);
                  }
                  globalLogWarn(message) {
                    if (window.console && window.console.warn) {
                      window.console.warn(message);
                    } else {
                      this.globalLog(message);
                    }
                  }
                  globalLogError(message) {
                    if (window.console && window.console.error) {
                      window.console.error(message);
                    } else {
                      this.globalLogWarn(message);
                    }
                  }
                  log(defaultLoggingFunction, ...args) {
                    var message = stringify.apply(this, arguments);
                    if (pusher.log) {
                      pusher.log(message);
                    } else if (pusher.logToConsole) {
                      const log = defaultLoggingFunction.bind(this);
                      log(message);
                    }
                  }
                }
                const logger = new Logger();
                ;
                var jsonp = function(context, query, authOptions, authRequestType, callback) {
                  if (authOptions.headers !== void 0 || authOptions.headersProvider != null) {
                    logger.warn(`To send headers with the ${authRequestType.toString()} request, you must use AJAX, rather than JSONP.`);
                  }
                  var callbackName = context.nextAuthCallbackID.toString();
                  context.nextAuthCallbackID++;
                  var document2 = context.getDocument();
                  var script = document2.createElement("script");
                  context.auth_callbacks[callbackName] = function(data) {
                    callback(null, data);
                  };
                  var callback_name = "Pusher.auth_callbacks['" + callbackName + "']";
                  script.src = authOptions.endpoint + "?callback=" + encodeURIComponent(callback_name) + "&" + query;
                  var head = document2.getElementsByTagName("head")[0] || document2.documentElement;
                  head.insertBefore(script, head.firstChild);
                };
                const jsonp_auth = jsonp;
                ;
                class ScriptRequest {
                  constructor(src) {
                    this.src = src;
                  }
                  send(receiver) {
                    var self2 = this;
                    var errorString = "Error loading " + self2.src;
                    self2.script = document.createElement("script");
                    self2.script.id = receiver.id;
                    self2.script.src = self2.src;
                    self2.script.type = "text/javascript";
                    self2.script.charset = "UTF-8";
                    if (self2.script.addEventListener) {
                      self2.script.onerror = function() {
                        receiver.callback(errorString);
                      };
                      self2.script.onload = function() {
                        receiver.callback(null);
                      };
                    } else {
                      self2.script.onreadystatechange = function() {
                        if (self2.script.readyState === "loaded" || self2.script.readyState === "complete") {
                          receiver.callback(null);
                        }
                      };
                    }
                    if (self2.script.async === void 0 && document.attachEvent && /opera/i.test(navigator.userAgent)) {
                      self2.errorScript = document.createElement("script");
                      self2.errorScript.id = receiver.id + "_error";
                      self2.errorScript.text = receiver.name + "('" + errorString + "');";
                      self2.script.async = self2.errorScript.async = false;
                    } else {
                      self2.script.async = true;
                    }
                    var head = document.getElementsByTagName("head")[0];
                    head.insertBefore(self2.script, head.firstChild);
                    if (self2.errorScript) {
                      head.insertBefore(self2.errorScript, self2.script.nextSibling);
                    }
                  }
                  cleanup() {
                    if (this.script) {
                      this.script.onload = this.script.onerror = null;
                      this.script.onreadystatechange = null;
                    }
                    if (this.script && this.script.parentNode) {
                      this.script.parentNode.removeChild(this.script);
                    }
                    if (this.errorScript && this.errorScript.parentNode) {
                      this.errorScript.parentNode.removeChild(this.errorScript);
                    }
                    this.script = null;
                    this.errorScript = null;
                  }
                }
                ;
                class JSONPRequest {
                  constructor(url, data) {
                    this.url = url;
                    this.data = data;
                  }
                  send(receiver) {
                    if (this.request) {
                      return;
                    }
                    var query = buildQueryString(this.data);
                    var url = this.url + "/" + receiver.number + "?" + query;
                    this.request = runtime.createScriptRequest(url);
                    this.request.send(receiver);
                  }
                  cleanup() {
                    if (this.request) {
                      this.request.cleanup();
                    }
                  }
                }
                ;
                var getAgent = function(sender, useTLS) {
                  return function(data, callback) {
                    var scheme = "http" + (useTLS ? "s" : "") + "://";
                    var url = scheme + (sender.host || sender.options.host) + sender.options.path;
                    var request = runtime.createJSONPRequest(url, data);
                    var receiver = runtime.ScriptReceivers.create(function(error, result) {
                      ScriptReceivers.remove(receiver);
                      request.cleanup();
                      if (result && result.host) {
                        sender.host = result.host;
                      }
                      if (callback) {
                        callback(error, result);
                      }
                    });
                    request.send(receiver);
                  };
                };
                var jsonp_timeline_jsonp = {
                  name: "jsonp",
                  getAgent
                };
                const jsonp_timeline = jsonp_timeline_jsonp;
                ;
                function getGenericURL(baseScheme, params, path) {
                  var scheme = baseScheme + (params.useTLS ? "s" : "");
                  var host = params.useTLS ? params.hostTLS : params.hostNonTLS;
                  return scheme + "://" + host + path;
                }
                function getGenericPath(key, queryString) {
                  var path = "/app/" + key;
                  var query = "?protocol=" + defaults.PROTOCOL + "&client=js&version=" + defaults.VERSION + (queryString ? "&" + queryString : "");
                  return path + query;
                }
                var ws = {
                  getInitial: function(key, params) {
                    var path = (params.httpPath || "") + getGenericPath(key, "flash=false");
                    return getGenericURL("ws", params, path);
                  }
                };
                var http = {
                  getInitial: function(key, params) {
                    var path = (params.httpPath || "/pusher") + getGenericPath(key);
                    return getGenericURL("http", params, path);
                  }
                };
                var sockjs = {
                  getInitial: function(key, params) {
                    return getGenericURL("http", params, params.httpPath || "/pusher");
                  },
                  getPath: function(key, params) {
                    return getGenericPath(key);
                  }
                };
                ;
                class CallbackRegistry {
                  constructor() {
                    this._callbacks = {};
                  }
                  get(name) {
                    return this._callbacks[prefix(name)];
                  }
                  add(name, callback, context) {
                    var prefixedEventName = prefix(name);
                    this._callbacks[prefixedEventName] = this._callbacks[prefixedEventName] || [];
                    this._callbacks[prefixedEventName].push({
                      fn: callback,
                      context
                    });
                  }
                  remove(name, callback, context) {
                    if (!name && !callback && !context) {
                      this._callbacks = {};
                      return;
                    }
                    var names = name ? [prefix(name)] : keys(this._callbacks);
                    if (callback || context) {
                      this.removeCallback(names, callback, context);
                    } else {
                      this.removeAllCallbacks(names);
                    }
                  }
                  removeCallback(names, callback, context) {
                    apply(names, function(name) {
                      this._callbacks[name] = filter(this._callbacks[name] || [], function(binding) {
                        return callback && callback !== binding.fn || context && context !== binding.context;
                      });
                      if (this._callbacks[name].length === 0) {
                        delete this._callbacks[name];
                      }
                    }, this);
                  }
                  removeAllCallbacks(names) {
                    apply(names, function(name) {
                      delete this._callbacks[name];
                    }, this);
                  }
                }
                function prefix(name) {
                  return "_" + name;
                }
                ;
                class Dispatcher {
                  constructor(failThrough) {
                    this.callbacks = new CallbackRegistry();
                    this.global_callbacks = [];
                    this.failThrough = failThrough;
                  }
                  bind(eventName, callback, context) {
                    this.callbacks.add(eventName, callback, context);
                    return this;
                  }
                  bind_global(callback) {
                    this.global_callbacks.push(callback);
                    return this;
                  }
                  unbind(eventName, callback, context) {
                    this.callbacks.remove(eventName, callback, context);
                    return this;
                  }
                  unbind_global(callback) {
                    if (!callback) {
                      this.global_callbacks = [];
                      return this;
                    }
                    this.global_callbacks = filter(this.global_callbacks || [], (c2) => c2 !== callback);
                    return this;
                  }
                  unbind_all() {
                    this.unbind();
                    this.unbind_global();
                    return this;
                  }
                  emit(eventName, data, metadata) {
                    for (var i3 = 0; i3 < this.global_callbacks.length; i3++) {
                      this.global_callbacks[i3](eventName, data);
                    }
                    var callbacks = this.callbacks.get(eventName);
                    var args = [];
                    if (metadata) {
                      args.push(data, metadata);
                    } else if (data) {
                      args.push(data);
                    }
                    if (callbacks && callbacks.length > 0) {
                      for (var i3 = 0; i3 < callbacks.length; i3++) {
                        callbacks[i3].fn.apply(callbacks[i3].context || window, args);
                      }
                    } else if (this.failThrough) {
                      this.failThrough(eventName, data);
                    }
                    return this;
                  }
                }
                ;
                class TransportConnection extends Dispatcher {
                  constructor(hooks2, name, priority, key, options) {
                    super();
                    this.initialize = runtime.transportConnectionInitializer;
                    this.hooks = hooks2;
                    this.name = name;
                    this.priority = priority;
                    this.key = key;
                    this.options = options;
                    this.state = "new";
                    this.timeline = options.timeline;
                    this.activityTimeout = options.activityTimeout;
                    this.id = this.timeline.generateUniqueID();
                  }
                  handlesActivityChecks() {
                    return Boolean(this.hooks.handlesActivityChecks);
                  }
                  supportsPing() {
                    return Boolean(this.hooks.supportsPing);
                  }
                  connect() {
                    if (this.socket || this.state !== "initialized") {
                      return false;
                    }
                    var url = this.hooks.urls.getInitial(this.key, this.options);
                    try {
                      this.socket = this.hooks.getSocket(url, this.options);
                    } catch (e3) {
                      util.defer(() => {
                        this.onError(e3);
                        this.changeState("closed");
                      });
                      return false;
                    }
                    this.bindListeners();
                    logger.debug("Connecting", { transport: this.name, url });
                    this.changeState("connecting");
                    return true;
                  }
                  close() {
                    if (this.socket) {
                      this.socket.close();
                      return true;
                    } else {
                      return false;
                    }
                  }
                  send(data) {
                    if (this.state === "open") {
                      util.defer(() => {
                        if (this.socket) {
                          this.socket.send(data);
                        }
                      });
                      return true;
                    } else {
                      return false;
                    }
                  }
                  ping() {
                    if (this.state === "open" && this.supportsPing()) {
                      this.socket.ping();
                    }
                  }
                  onOpen() {
                    if (this.hooks.beforeOpen) {
                      this.hooks.beforeOpen(this.socket, this.hooks.urls.getPath(this.key, this.options));
                    }
                    this.changeState("open");
                    this.socket.onopen = void 0;
                  }
                  onError(error) {
                    this.emit("error", { type: "WebSocketError", error });
                    this.timeline.error(this.buildTimelineMessage({ error: error.toString() }));
                  }
                  onClose(closeEvent) {
                    if (closeEvent) {
                      this.changeState("closed", {
                        code: closeEvent.code,
                        reason: closeEvent.reason,
                        wasClean: closeEvent.wasClean
                      });
                    } else {
                      this.changeState("closed");
                    }
                    this.unbindListeners();
                    this.socket = void 0;
                  }
                  onMessage(message) {
                    this.emit("message", message);
                  }
                  onActivity() {
                    this.emit("activity");
                  }
                  bindListeners() {
                    this.socket.onopen = () => {
                      this.onOpen();
                    };
                    this.socket.onerror = (error) => {
                      this.onError(error);
                    };
                    this.socket.onclose = (closeEvent) => {
                      this.onClose(closeEvent);
                    };
                    this.socket.onmessage = (message) => {
                      this.onMessage(message);
                    };
                    if (this.supportsPing()) {
                      this.socket.onactivity = () => {
                        this.onActivity();
                      };
                    }
                  }
                  unbindListeners() {
                    if (this.socket) {
                      this.socket.onopen = void 0;
                      this.socket.onerror = void 0;
                      this.socket.onclose = void 0;
                      this.socket.onmessage = void 0;
                      if (this.supportsPing()) {
                        this.socket.onactivity = void 0;
                      }
                    }
                  }
                  changeState(state2, params) {
                    this.state = state2;
                    this.timeline.info(this.buildTimelineMessage({
                      state: state2,
                      params
                    }));
                    this.emit(state2, params);
                  }
                  buildTimelineMessage(message) {
                    return extend({ cid: this.id }, message);
                  }
                }
                ;
                class Transport {
                  constructor(hooks2) {
                    this.hooks = hooks2;
                  }
                  isSupported(environment) {
                    return this.hooks.isSupported(environment);
                  }
                  createConnection(name, priority, key, options) {
                    return new TransportConnection(this.hooks, name, priority, key, options);
                  }
                }
                ;
                var WSTransport = new Transport({
                  urls: ws,
                  handlesActivityChecks: false,
                  supportsPing: false,
                  isInitialized: function() {
                    return Boolean(runtime.getWebSocketAPI());
                  },
                  isSupported: function() {
                    return Boolean(runtime.getWebSocketAPI());
                  },
                  getSocket: function(url) {
                    return runtime.createWebSocket(url);
                  }
                });
                var httpConfiguration = {
                  urls: http,
                  handlesActivityChecks: false,
                  supportsPing: true,
                  isInitialized: function() {
                    return true;
                  }
                };
                var streamingConfiguration = extend({
                  getSocket: function(url) {
                    return runtime.HTTPFactory.createStreamingSocket(url);
                  }
                }, httpConfiguration);
                var pollingConfiguration = extend({
                  getSocket: function(url) {
                    return runtime.HTTPFactory.createPollingSocket(url);
                  }
                }, httpConfiguration);
                var xhrConfiguration = {
                  isSupported: function() {
                    return runtime.isXHRSupported();
                  }
                };
                var XHRStreamingTransport = new Transport(extend({}, streamingConfiguration, xhrConfiguration));
                var XHRPollingTransport = new Transport(extend({}, pollingConfiguration, xhrConfiguration));
                var Transports = {
                  ws: WSTransport,
                  xhr_streaming: XHRStreamingTransport,
                  xhr_polling: XHRPollingTransport
                };
                const transports = Transports;
                ;
                var SockJSTransport = new Transport({
                  file: "sockjs",
                  urls: sockjs,
                  handlesActivityChecks: true,
                  supportsPing: false,
                  isSupported: function() {
                    return true;
                  },
                  isInitialized: function() {
                    return window.SockJS !== void 0;
                  },
                  getSocket: function(url, options) {
                    return new window.SockJS(url, null, {
                      js_path: Dependencies.getPath("sockjs", {
                        useTLS: options.useTLS
                      }),
                      ignore_null_origin: options.ignoreNullOrigin
                    });
                  },
                  beforeOpen: function(socket, path) {
                    socket.send(JSON.stringify({
                      path
                    }));
                  }
                });
                var xdrConfiguration = {
                  isSupported: function(environment) {
                    var yes = runtime.isXDRSupported(environment.useTLS);
                    return yes;
                  }
                };
                var XDRStreamingTransport = new Transport(extend({}, streamingConfiguration, xdrConfiguration));
                var XDRPollingTransport = new Transport(extend({}, pollingConfiguration, xdrConfiguration));
                transports.xdr_streaming = XDRStreamingTransport;
                transports.xdr_polling = XDRPollingTransport;
                transports.sockjs = SockJSTransport;
                const transports_transports = transports;
                ;
                class NetInfo extends Dispatcher {
                  constructor() {
                    super();
                    var self2 = this;
                    if (typeof window !== "undefined" && window.addEventListener !== void 0) {
                      window.addEventListener("online", function() {
                        self2.emit("online");
                      }, false);
                      window.addEventListener("offline", function() {
                        self2.emit("offline");
                      }, false);
                    }
                  }
                  isOnline() {
                    if (window.navigator.onLine === void 0) {
                      return true;
                    } else {
                      return window.navigator.onLine;
                    }
                  }
                }
                var Network = new NetInfo();
                ;
                class AssistantToTheTransportManager {
                  constructor(manager, transport, options) {
                    this.manager = manager;
                    this.transport = transport;
                    this.minPingDelay = options.minPingDelay;
                    this.maxPingDelay = options.maxPingDelay;
                    this.pingDelay = void 0;
                  }
                  createConnection(name, priority, key, options) {
                    options = extend({}, options, {
                      activityTimeout: this.pingDelay
                    });
                    var connection = this.transport.createConnection(name, priority, key, options);
                    var openTimestamp = null;
                    var onOpen = function() {
                      connection.unbind("open", onOpen);
                      connection.bind("closed", onClosed);
                      openTimestamp = util.now();
                    };
                    var onClosed = (closeEvent) => {
                      connection.unbind("closed", onClosed);
                      if (closeEvent.code === 1002 || closeEvent.code === 1003) {
                        this.manager.reportDeath();
                      } else if (!closeEvent.wasClean && openTimestamp) {
                        var lifespan = util.now() - openTimestamp;
                        if (lifespan < 2 * this.maxPingDelay) {
                          this.manager.reportDeath();
                          this.pingDelay = Math.max(lifespan / 2, this.minPingDelay);
                        }
                      }
                    };
                    connection.bind("open", onOpen);
                    return connection;
                  }
                  isSupported(environment) {
                    return this.manager.isAlive() && this.transport.isSupported(environment);
                  }
                }
                ;
                const Protocol = {
                  decodeMessage: function(messageEvent) {
                    try {
                      var messageData = JSON.parse(messageEvent.data);
                      var pusherEventData = messageData.data;
                      if (typeof pusherEventData === "string") {
                        try {
                          pusherEventData = JSON.parse(messageData.data);
                        } catch (e3) {
                        }
                      }
                      var pusherEvent = {
                        event: messageData.event,
                        channel: messageData.channel,
                        data: pusherEventData
                      };
                      if (messageData.user_id) {
                        pusherEvent.user_id = messageData.user_id;
                      }
                      return pusherEvent;
                    } catch (e3) {
                      throw { type: "MessageParseError", error: e3, data: messageEvent.data };
                    }
                  },
                  encodeMessage: function(event) {
                    return JSON.stringify(event);
                  },
                  processHandshake: function(messageEvent) {
                    var message = Protocol.decodeMessage(messageEvent);
                    if (message.event === "pusher:connection_established") {
                      if (!message.data.activity_timeout) {
                        throw "No activity timeout specified in handshake";
                      }
                      return {
                        action: "connected",
                        id: message.data.socket_id,
                        activityTimeout: message.data.activity_timeout * 1e3
                      };
                    } else if (message.event === "pusher:error") {
                      return {
                        action: this.getCloseAction(message.data),
                        error: this.getCloseError(message.data)
                      };
                    } else {
                      throw "Invalid handshake";
                    }
                  },
                  getCloseAction: function(closeEvent) {
                    if (closeEvent.code < 4e3) {
                      if (closeEvent.code >= 1002 && closeEvent.code <= 1004) {
                        return "backoff";
                      } else {
                        return null;
                      }
                    } else if (closeEvent.code === 4e3) {
                      return "tls_only";
                    } else if (closeEvent.code < 4100) {
                      return "refused";
                    } else if (closeEvent.code < 4200) {
                      return "backoff";
                    } else if (closeEvent.code < 4300) {
                      return "retry";
                    } else {
                      return "refused";
                    }
                  },
                  getCloseError: function(closeEvent) {
                    if (closeEvent.code !== 1e3 && closeEvent.code !== 1001) {
                      return {
                        type: "PusherError",
                        data: {
                          code: closeEvent.code,
                          message: closeEvent.reason || closeEvent.message
                        }
                      };
                    } else {
                      return null;
                    }
                  }
                };
                const protocol = Protocol;
                ;
                class Connection extends Dispatcher {
                  constructor(id, transport) {
                    super();
                    this.id = id;
                    this.transport = transport;
                    this.activityTimeout = transport.activityTimeout;
                    this.bindListeners();
                  }
                  handlesActivityChecks() {
                    return this.transport.handlesActivityChecks();
                  }
                  send(data) {
                    return this.transport.send(data);
                  }
                  send_event(name, data, channel) {
                    var event = { event: name, data };
                    if (channel) {
                      event.channel = channel;
                    }
                    logger.debug("Event sent", event);
                    return this.send(protocol.encodeMessage(event));
                  }
                  ping() {
                    if (this.transport.supportsPing()) {
                      this.transport.ping();
                    } else {
                      this.send_event("pusher:ping", {});
                    }
                  }
                  close() {
                    this.transport.close();
                  }
                  bindListeners() {
                    var listeners = {
                      message: (messageEvent) => {
                        var pusherEvent;
                        try {
                          pusherEvent = protocol.decodeMessage(messageEvent);
                        } catch (e3) {
                          this.emit("error", {
                            type: "MessageParseError",
                            error: e3,
                            data: messageEvent.data
                          });
                        }
                        if (pusherEvent !== void 0) {
                          logger.debug("Event recd", pusherEvent);
                          switch (pusherEvent.event) {
                            case "pusher:error":
                              this.emit("error", {
                                type: "PusherError",
                                data: pusherEvent.data
                              });
                              break;
                            case "pusher:ping":
                              this.emit("ping");
                              break;
                            case "pusher:pong":
                              this.emit("pong");
                              break;
                          }
                          this.emit("message", pusherEvent);
                        }
                      },
                      activity: () => {
                        this.emit("activity");
                      },
                      error: (error) => {
                        this.emit("error", error);
                      },
                      closed: (closeEvent) => {
                        unbindListeners();
                        if (closeEvent && closeEvent.code) {
                          this.handleCloseEvent(closeEvent);
                        }
                        this.transport = null;
                        this.emit("closed");
                      }
                    };
                    var unbindListeners = () => {
                      objectApply(listeners, (listener, event) => {
                        this.transport.unbind(event, listener);
                      });
                    };
                    objectApply(listeners, (listener, event) => {
                      this.transport.bind(event, listener);
                    });
                  }
                  handleCloseEvent(closeEvent) {
                    var action = protocol.getCloseAction(closeEvent);
                    var error = protocol.getCloseError(closeEvent);
                    if (error) {
                      this.emit("error", error);
                    }
                    if (action) {
                      this.emit(action, { action, error });
                    }
                  }
                }
                ;
                class Handshake {
                  constructor(transport, callback) {
                    this.transport = transport;
                    this.callback = callback;
                    this.bindListeners();
                  }
                  close() {
                    this.unbindListeners();
                    this.transport.close();
                  }
                  bindListeners() {
                    this.onMessage = (m2) => {
                      this.unbindListeners();
                      var result;
                      try {
                        result = protocol.processHandshake(m2);
                      } catch (e3) {
                        this.finish("error", { error: e3 });
                        this.transport.close();
                        return;
                      }
                      if (result.action === "connected") {
                        this.finish("connected", {
                          connection: new Connection(result.id, this.transport),
                          activityTimeout: result.activityTimeout
                        });
                      } else {
                        this.finish(result.action, { error: result.error });
                        this.transport.close();
                      }
                    };
                    this.onClosed = (closeEvent) => {
                      this.unbindListeners();
                      var action = protocol.getCloseAction(closeEvent) || "backoff";
                      var error = protocol.getCloseError(closeEvent);
                      this.finish(action, { error });
                    };
                    this.transport.bind("message", this.onMessage);
                    this.transport.bind("closed", this.onClosed);
                  }
                  unbindListeners() {
                    this.transport.unbind("message", this.onMessage);
                    this.transport.unbind("closed", this.onClosed);
                  }
                  finish(action, params) {
                    this.callback(extend({ transport: this.transport, action }, params));
                  }
                }
                ;
                class TimelineSender {
                  constructor(timeline, options) {
                    this.timeline = timeline;
                    this.options = options || {};
                  }
                  send(useTLS, callback) {
                    if (this.timeline.isEmpty()) {
                      return;
                    }
                    this.timeline.send(runtime.TimelineTransport.getAgent(this, useTLS), callback);
                  }
                }
                ;
                class Channel extends Dispatcher {
                  constructor(name, pusher2) {
                    super(function(event, data) {
                      logger.debug("No callbacks on " + name + " for " + event);
                    });
                    this.name = name;
                    this.pusher = pusher2;
                    this.subscribed = false;
                    this.subscriptionPending = false;
                    this.subscriptionCancelled = false;
                  }
                  authorize(socketId, callback) {
                    return callback(null, { auth: "" });
                  }
                  trigger(event, data) {
                    if (event.indexOf("client-") !== 0) {
                      throw new BadEventName("Event '" + event + "' does not start with 'client-'");
                    }
                    if (!this.subscribed) {
                      var suffix = url_store.buildLogSuffix("triggeringClientEvents");
                      logger.warn(`Client event triggered before channel 'subscription_succeeded' event . ${suffix}`);
                    }
                    return this.pusher.send_event(event, data, this.name);
                  }
                  disconnect() {
                    this.subscribed = false;
                    this.subscriptionPending = false;
                  }
                  handleEvent(event) {
                    var eventName = event.event;
                    var data = event.data;
                    if (eventName === "pusher_internal:subscription_succeeded") {
                      this.handleSubscriptionSucceededEvent(event);
                    } else if (eventName === "pusher_internal:subscription_count") {
                      this.handleSubscriptionCountEvent(event);
                    } else if (eventName.indexOf("pusher_internal:") !== 0) {
                      var metadata = {};
                      this.emit(eventName, data, metadata);
                    }
                  }
                  handleSubscriptionSucceededEvent(event) {
                    this.subscriptionPending = false;
                    this.subscribed = true;
                    if (this.subscriptionCancelled) {
                      this.pusher.unsubscribe(this.name);
                    } else {
                      this.emit("pusher:subscription_succeeded", event.data);
                    }
                  }
                  handleSubscriptionCountEvent(event) {
                    if (event.data.subscription_count) {
                      this.subscriptionCount = event.data.subscription_count;
                    }
                    this.emit("pusher:subscription_count", event.data);
                  }
                  subscribe() {
                    if (this.subscribed) {
                      return;
                    }
                    this.subscriptionPending = true;
                    this.subscriptionCancelled = false;
                    this.authorize(this.pusher.connection.socket_id, (error, data) => {
                      if (error) {
                        this.subscriptionPending = false;
                        logger.error(error.toString());
                        this.emit("pusher:subscription_error", Object.assign({}, {
                          type: "AuthError",
                          error: error.message
                        }, error instanceof HTTPAuthError ? { status: error.status } : {}));
                      } else {
                        this.pusher.send_event("pusher:subscribe", {
                          auth: data.auth,
                          channel_data: data.channel_data,
                          channel: this.name
                        });
                      }
                    });
                  }
                  unsubscribe() {
                    this.subscribed = false;
                    this.pusher.send_event("pusher:unsubscribe", {
                      channel: this.name
                    });
                  }
                  cancelSubscription() {
                    this.subscriptionCancelled = true;
                  }
                  reinstateSubscription() {
                    this.subscriptionCancelled = false;
                  }
                }
                ;
                class PrivateChannel extends Channel {
                  authorize(socketId, callback) {
                    return this.pusher.config.channelAuthorizer({
                      channelName: this.name,
                      socketId
                    }, callback);
                  }
                }
                ;
                class Members {
                  constructor() {
                    this.reset();
                  }
                  get(id) {
                    if (Object.prototype.hasOwnProperty.call(this.members, id)) {
                      return {
                        id,
                        info: this.members[id]
                      };
                    } else {
                      return null;
                    }
                  }
                  each(callback) {
                    objectApply(this.members, (member, id) => {
                      callback(this.get(id));
                    });
                  }
                  setMyID(id) {
                    this.myID = id;
                  }
                  onSubscription(subscriptionData) {
                    this.members = subscriptionData.presence.hash;
                    this.count = subscriptionData.presence.count;
                    this.me = this.get(this.myID);
                  }
                  addMember(memberData) {
                    if (this.get(memberData.user_id) === null) {
                      this.count++;
                    }
                    this.members[memberData.user_id] = memberData.user_info;
                    return this.get(memberData.user_id);
                  }
                  removeMember(memberData) {
                    var member = this.get(memberData.user_id);
                    if (member) {
                      delete this.members[memberData.user_id];
                      this.count--;
                    }
                    return member;
                  }
                  reset() {
                    this.members = {};
                    this.count = 0;
                    this.myID = null;
                    this.me = null;
                  }
                }
                ;
                var __awaiter = function(thisArg, _arguments, P2, generator) {
                  function adopt(value) {
                    return value instanceof P2 ? value : new P2(function(resolve) {
                      resolve(value);
                    });
                  }
                  return new (P2 || (P2 = Promise))(function(resolve, reject) {
                    function fulfilled(value) {
                      try {
                        step(generator.next(value));
                      } catch (e3) {
                        reject(e3);
                      }
                    }
                    function rejected(value) {
                      try {
                        step(generator["throw"](value));
                      } catch (e3) {
                        reject(e3);
                      }
                    }
                    function step(result) {
                      result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected);
                    }
                    step((generator = generator.apply(thisArg, _arguments || [])).next());
                  });
                };
                class PresenceChannel extends PrivateChannel {
                  constructor(name, pusher2) {
                    super(name, pusher2);
                    this.members = new Members();
                  }
                  authorize(socketId, callback) {
                    super.authorize(socketId, (error, authData) => __awaiter(this, void 0, void 0, function* () {
                      if (!error) {
                        authData = authData;
                        if (authData.channel_data != null) {
                          var channelData = JSON.parse(authData.channel_data);
                          this.members.setMyID(channelData.user_id);
                        } else {
                          yield this.pusher.user.signinDonePromise;
                          if (this.pusher.user.user_data != null) {
                            this.members.setMyID(this.pusher.user.user_data.id);
                          } else {
                            let suffix = url_store.buildLogSuffix("authorizationEndpoint");
                            logger.error(`Invalid auth response for channel '${this.name}', expected 'channel_data' field. ${suffix}, or the user should be signed in.`);
                            callback("Invalid auth response");
                            return;
                          }
                        }
                      }
                      callback(error, authData);
                    }));
                  }
                  handleEvent(event) {
                    var eventName = event.event;
                    if (eventName.indexOf("pusher_internal:") === 0) {
                      this.handleInternalEvent(event);
                    } else {
                      var data = event.data;
                      var metadata = {};
                      if (event.user_id) {
                        metadata.user_id = event.user_id;
                      }
                      this.emit(eventName, data, metadata);
                    }
                  }
                  handleInternalEvent(event) {
                    var eventName = event.event;
                    var data = event.data;
                    switch (eventName) {
                      case "pusher_internal:subscription_succeeded":
                        this.handleSubscriptionSucceededEvent(event);
                        break;
                      case "pusher_internal:subscription_count":
                        this.handleSubscriptionCountEvent(event);
                        break;
                      case "pusher_internal:member_added":
                        var addedMember = this.members.addMember(data);
                        this.emit("pusher:member_added", addedMember);
                        break;
                      case "pusher_internal:member_removed":
                        var removedMember = this.members.removeMember(data);
                        if (removedMember) {
                          this.emit("pusher:member_removed", removedMember);
                        }
                        break;
                    }
                  }
                  handleSubscriptionSucceededEvent(event) {
                    this.subscriptionPending = false;
                    this.subscribed = true;
                    if (this.subscriptionCancelled) {
                      this.pusher.unsubscribe(this.name);
                    } else {
                      this.members.onSubscription(event.data);
                      this.emit("pusher:subscription_succeeded", this.members);
                    }
                  }
                  disconnect() {
                    this.members.reset();
                    super.disconnect();
                  }
                }
                var utf8 = __webpack_require__2(978);
                var base64 = __webpack_require__2(594);
                ;
                class EncryptedChannel extends PrivateChannel {
                  constructor(name, pusher2, nacl) {
                    super(name, pusher2);
                    this.key = null;
                    this.nacl = nacl;
                  }
                  authorize(socketId, callback) {
                    super.authorize(socketId, (error, authData) => {
                      if (error) {
                        callback(error, authData);
                        return;
                      }
                      let sharedSecret = authData["shared_secret"];
                      if (!sharedSecret) {
                        callback(new Error(`No shared_secret key in auth payload for encrypted channel: ${this.name}`), null);
                        return;
                      }
                      this.key = (0, base64.decode)(sharedSecret);
                      delete authData["shared_secret"];
                      callback(null, authData);
                    });
                  }
                  trigger(event, data) {
                    throw new UnsupportedFeature("Client events are not currently supported for encrypted channels");
                  }
                  handleEvent(event) {
                    var eventName = event.event;
                    var data = event.data;
                    if (eventName.indexOf("pusher_internal:") === 0 || eventName.indexOf("pusher:") === 0) {
                      super.handleEvent(event);
                      return;
                    }
                    this.handleEncryptedEvent(eventName, data);
                  }
                  handleEncryptedEvent(event, data) {
                    if (!this.key) {
                      logger.debug("Received encrypted event before key has been retrieved from the authEndpoint");
                      return;
                    }
                    if (!data.ciphertext || !data.nonce) {
                      logger.error("Unexpected format for encrypted event, expected object with `ciphertext` and `nonce` fields, got: " + data);
                      return;
                    }
                    let cipherText = (0, base64.decode)(data.ciphertext);
                    if (cipherText.length < this.nacl.secretbox.overheadLength) {
                      logger.error(`Expected encrypted event ciphertext length to be ${this.nacl.secretbox.overheadLength}, got: ${cipherText.length}`);
                      return;
                    }
                    let nonce = (0, base64.decode)(data.nonce);
                    if (nonce.length < this.nacl.secretbox.nonceLength) {
                      logger.error(`Expected encrypted event nonce length to be ${this.nacl.secretbox.nonceLength}, got: ${nonce.length}`);
                      return;
                    }
                    let bytes = this.nacl.secretbox.open(cipherText, nonce, this.key);
                    if (bytes === null) {
                      logger.debug("Failed to decrypt an event, probably because it was encrypted with a different key. Fetching a new key from the authEndpoint...");
                      this.authorize(this.pusher.connection.socket_id, (error, authData) => {
                        if (error) {
                          logger.error(`Failed to make a request to the authEndpoint: ${authData}. Unable to fetch new key, so dropping encrypted event`);
                          return;
                        }
                        bytes = this.nacl.secretbox.open(cipherText, nonce, this.key);
                        if (bytes === null) {
                          logger.error(`Failed to decrypt event with new key. Dropping encrypted event`);
                          return;
                        }
                        this.emit(event, this.getDataToEmit(bytes));
                        return;
                      });
                      return;
                    }
                    this.emit(event, this.getDataToEmit(bytes));
                  }
                  getDataToEmit(bytes) {
                    let raw = (0, utf8.D4)(bytes);
                    try {
                      return JSON.parse(raw);
                    } catch (_a) {
                      return raw;
                    }
                  }
                }
                ;
                class ConnectionManager extends Dispatcher {
                  constructor(key, options) {
                    super();
                    this.state = "initialized";
                    this.connection = null;
                    this.key = key;
                    this.options = options;
                    this.timeline = this.options.timeline;
                    this.usingTLS = this.options.useTLS;
                    this.errorCallbacks = this.buildErrorCallbacks();
                    this.connectionCallbacks = this.buildConnectionCallbacks(this.errorCallbacks);
                    this.handshakeCallbacks = this.buildHandshakeCallbacks(this.errorCallbacks);
                    var Network2 = runtime.getNetwork();
                    Network2.bind("online", () => {
                      this.timeline.info({ netinfo: "online" });
                      if (this.state === "connecting" || this.state === "unavailable") {
                        this.retryIn(0);
                      }
                    });
                    Network2.bind("offline", () => {
                      this.timeline.info({ netinfo: "offline" });
                      if (this.connection) {
                        this.sendActivityCheck();
                      }
                    });
                    this.updateStrategy();
                  }
                  switchCluster(key) {
                    this.key = key;
                    this.updateStrategy();
                    this.retryIn(0);
                  }
                  connect() {
                    if (this.connection || this.runner) {
                      return;
                    }
                    if (!this.strategy.isSupported()) {
                      this.updateState("failed");
                      return;
                    }
                    this.updateState("connecting");
                    this.startConnecting();
                    this.setUnavailableTimer();
                  }
                  send(data) {
                    if (this.connection) {
                      return this.connection.send(data);
                    } else {
                      return false;
                    }
                  }
                  send_event(name, data, channel) {
                    if (this.connection) {
                      return this.connection.send_event(name, data, channel);
                    } else {
                      return false;
                    }
                  }
                  disconnect() {
                    this.disconnectInternally();
                    this.updateState("disconnected");
                  }
                  isUsingTLS() {
                    return this.usingTLS;
                  }
                  startConnecting() {
                    var callback = (error, handshake) => {
                      if (error) {
                        this.runner = this.strategy.connect(0, callback);
                      } else {
                        if (handshake.action === "error") {
                          this.emit("error", {
                            type: "HandshakeError",
                            error: handshake.error
                          });
                          this.timeline.error({ handshakeError: handshake.error });
                        } else {
                          this.abortConnecting();
                          this.handshakeCallbacks[handshake.action](handshake);
                        }
                      }
                    };
                    this.runner = this.strategy.connect(0, callback);
                  }
                  abortConnecting() {
                    if (this.runner) {
                      this.runner.abort();
                      this.runner = null;
                    }
                  }
                  disconnectInternally() {
                    this.abortConnecting();
                    this.clearRetryTimer();
                    this.clearUnavailableTimer();
                    if (this.connection) {
                      var connection = this.abandonConnection();
                      connection.close();
                    }
                  }
                  updateStrategy() {
                    this.strategy = this.options.getStrategy({
                      key: this.key,
                      timeline: this.timeline,
                      useTLS: this.usingTLS
                    });
                  }
                  retryIn(delay) {
                    this.timeline.info({ action: "retry", delay });
                    if (delay > 0) {
                      this.emit("connecting_in", Math.round(delay / 1e3));
                    }
                    this.retryTimer = new OneOffTimer(delay || 0, () => {
                      this.disconnectInternally();
                      this.connect();
                    });
                  }
                  clearRetryTimer() {
                    if (this.retryTimer) {
                      this.retryTimer.ensureAborted();
                      this.retryTimer = null;
                    }
                  }
                  setUnavailableTimer() {
                    this.unavailableTimer = new OneOffTimer(this.options.unavailableTimeout, () => {
                      this.updateState("unavailable");
                    });
                  }
                  clearUnavailableTimer() {
                    if (this.unavailableTimer) {
                      this.unavailableTimer.ensureAborted();
                    }
                  }
                  sendActivityCheck() {
                    this.stopActivityCheck();
                    this.connection.ping();
                    this.activityTimer = new OneOffTimer(this.options.pongTimeout, () => {
                      this.timeline.error({ pong_timed_out: this.options.pongTimeout });
                      this.retryIn(0);
                    });
                  }
                  resetActivityCheck() {
                    this.stopActivityCheck();
                    if (this.connection && !this.connection.handlesActivityChecks()) {
                      this.activityTimer = new OneOffTimer(this.activityTimeout, () => {
                        this.sendActivityCheck();
                      });
                    }
                  }
                  stopActivityCheck() {
                    if (this.activityTimer) {
                      this.activityTimer.ensureAborted();
                    }
                  }
                  buildConnectionCallbacks(errorCallbacks) {
                    return extend({}, errorCallbacks, {
                      message: (message) => {
                        this.resetActivityCheck();
                        this.emit("message", message);
                      },
                      ping: () => {
                        this.send_event("pusher:pong", {});
                      },
                      activity: () => {
                        this.resetActivityCheck();
                      },
                      error: (error) => {
                        this.emit("error", error);
                      },
                      closed: () => {
                        this.abandonConnection();
                        if (this.shouldRetry()) {
                          this.retryIn(1e3);
                        }
                      }
                    });
                  }
                  buildHandshakeCallbacks(errorCallbacks) {
                    return extend({}, errorCallbacks, {
                      connected: (handshake) => {
                        this.activityTimeout = Math.min(this.options.activityTimeout, handshake.activityTimeout, handshake.connection.activityTimeout || Infinity);
                        this.clearUnavailableTimer();
                        this.setConnection(handshake.connection);
                        this.socket_id = this.connection.id;
                        this.updateState("connected", { socket_id: this.socket_id });
                      }
                    });
                  }
                  buildErrorCallbacks() {
                    let withErrorEmitted = (callback) => {
                      return (result) => {
                        if (result.error) {
                          this.emit("error", { type: "WebSocketError", error: result.error });
                        }
                        callback(result);
                      };
                    };
                    return {
                      tls_only: withErrorEmitted(() => {
                        this.usingTLS = true;
                        this.updateStrategy();
                        this.retryIn(0);
                      }),
                      refused: withErrorEmitted(() => {
                        this.disconnect();
                      }),
                      backoff: withErrorEmitted(() => {
                        this.retryIn(1e3);
                      }),
                      retry: withErrorEmitted(() => {
                        this.retryIn(0);
                      })
                    };
                  }
                  setConnection(connection) {
                    this.connection = connection;
                    for (var event in this.connectionCallbacks) {
                      this.connection.bind(event, this.connectionCallbacks[event]);
                    }
                    this.resetActivityCheck();
                  }
                  abandonConnection() {
                    if (!this.connection) {
                      return;
                    }
                    this.stopActivityCheck();
                    for (var event in this.connectionCallbacks) {
                      this.connection.unbind(event, this.connectionCallbacks[event]);
                    }
                    var connection = this.connection;
                    this.connection = null;
                    return connection;
                  }
                  updateState(newState, data) {
                    var previousState = this.state;
                    this.state = newState;
                    if (previousState !== newState) {
                      var newStateDescription = newState;
                      if (newStateDescription === "connected") {
                        newStateDescription += " with new socket ID " + data.socket_id;
                      }
                      logger.debug("State changed", previousState + " -> " + newStateDescription);
                      this.timeline.info({ state: newState, params: data });
                      this.emit("state_change", { previous: previousState, current: newState });
                      this.emit(newState, data);
                    }
                  }
                  shouldRetry() {
                    return this.state === "connecting" || this.state === "connected";
                  }
                }
                ;
                class Channels {
                  constructor() {
                    this.channels = {};
                  }
                  add(name, pusher2) {
                    if (!this.channels[name]) {
                      this.channels[name] = createChannel(name, pusher2);
                    }
                    return this.channels[name];
                  }
                  all() {
                    return values(this.channels);
                  }
                  find(name) {
                    return this.channels[name];
                  }
                  remove(name) {
                    var channel = this.channels[name];
                    delete this.channels[name];
                    return channel;
                  }
                  disconnect() {
                    objectApply(this.channels, function(channel) {
                      channel.disconnect();
                    });
                  }
                }
                function createChannel(name, pusher2) {
                  if (name.indexOf("private-encrypted-") === 0) {
                    if (pusher2.config.nacl) {
                      return factory.createEncryptedChannel(name, pusher2, pusher2.config.nacl);
                    }
                    let errMsg = "Tried to subscribe to a private-encrypted- channel but no nacl implementation available";
                    let suffix = url_store.buildLogSuffix("encryptedChannelSupport");
                    throw new UnsupportedFeature(`${errMsg}. ${suffix}`);
                  } else if (name.indexOf("private-") === 0) {
                    return factory.createPrivateChannel(name, pusher2);
                  } else if (name.indexOf("presence-") === 0) {
                    return factory.createPresenceChannel(name, pusher2);
                  } else if (name.indexOf("#") === 0) {
                    throw new BadChannelName('Cannot create a channel with name "' + name + '".');
                  } else {
                    return factory.createChannel(name, pusher2);
                  }
                }
                ;
                var Factory = {
                  createChannels() {
                    return new Channels();
                  },
                  createConnectionManager(key, options) {
                    return new ConnectionManager(key, options);
                  },
                  createChannel(name, pusher2) {
                    return new Channel(name, pusher2);
                  },
                  createPrivateChannel(name, pusher2) {
                    return new PrivateChannel(name, pusher2);
                  },
                  createPresenceChannel(name, pusher2) {
                    return new PresenceChannel(name, pusher2);
                  },
                  createEncryptedChannel(name, pusher2, nacl) {
                    return new EncryptedChannel(name, pusher2, nacl);
                  },
                  createTimelineSender(timeline, options) {
                    return new TimelineSender(timeline, options);
                  },
                  createHandshake(transport, callback) {
                    return new Handshake(transport, callback);
                  },
                  createAssistantToTheTransportManager(manager, transport, options) {
                    return new AssistantToTheTransportManager(manager, transport, options);
                  }
                };
                const factory = Factory;
                ;
                class TransportManager {
                  constructor(options) {
                    this.options = options || {};
                    this.livesLeft = this.options.lives || Infinity;
                  }
                  getAssistant(transport) {
                    return factory.createAssistantToTheTransportManager(this, transport, {
                      minPingDelay: this.options.minPingDelay,
                      maxPingDelay: this.options.maxPingDelay
                    });
                  }
                  isAlive() {
                    return this.livesLeft > 0;
                  }
                  reportDeath() {
                    this.livesLeft -= 1;
                  }
                }
                ;
                class SequentialStrategy {
                  constructor(strategies, options) {
                    this.strategies = strategies;
                    this.loop = Boolean(options.loop);
                    this.failFast = Boolean(options.failFast);
                    this.timeout = options.timeout;
                    this.timeoutLimit = options.timeoutLimit;
                  }
                  isSupported() {
                    return any(this.strategies, util.method("isSupported"));
                  }
                  connect(minPriority, callback) {
                    var strategies = this.strategies;
                    var current = 0;
                    var timeout = this.timeout;
                    var runner = null;
                    var tryNextStrategy = (error, handshake) => {
                      if (handshake) {
                        callback(null, handshake);
                      } else {
                        current = current + 1;
                        if (this.loop) {
                          current = current % strategies.length;
                        }
                        if (current < strategies.length) {
                          if (timeout) {
                            timeout = timeout * 2;
                            if (this.timeoutLimit) {
                              timeout = Math.min(timeout, this.timeoutLimit);
                            }
                          }
                          runner = this.tryStrategy(strategies[current], minPriority, { timeout, failFast: this.failFast }, tryNextStrategy);
                        } else {
                          callback(true);
                        }
                      }
                    };
                    runner = this.tryStrategy(strategies[current], minPriority, { timeout, failFast: this.failFast }, tryNextStrategy);
                    return {
                      abort: function() {
                        runner.abort();
                      },
                      forceMinPriority: function(p2) {
                        minPriority = p2;
                        if (runner) {
                          runner.forceMinPriority(p2);
                        }
                      }
                    };
                  }
                  tryStrategy(strategy, minPriority, options, callback) {
                    var timer = null;
                    var runner = null;
                    if (options.timeout > 0) {
                      timer = new OneOffTimer(options.timeout, function() {
                        runner.abort();
                        callback(true);
                      });
                    }
                    runner = strategy.connect(minPriority, function(error, handshake) {
                      if (error && timer && timer.isRunning() && !options.failFast) {
                        return;
                      }
                      if (timer) {
                        timer.ensureAborted();
                      }
                      callback(error, handshake);
                    });
                    return {
                      abort: function() {
                        if (timer) {
                          timer.ensureAborted();
                        }
                        runner.abort();
                      },
                      forceMinPriority: function(p2) {
                        runner.forceMinPriority(p2);
                      }
                    };
                  }
                }
                ;
                class BestConnectedEverStrategy {
                  constructor(strategies) {
                    this.strategies = strategies;
                  }
                  isSupported() {
                    return any(this.strategies, util.method("isSupported"));
                  }
                  connect(minPriority, callback) {
                    return connect2(this.strategies, minPriority, function(i3, runners) {
                      return function(error, handshake) {
                        runners[i3].error = error;
                        if (error) {
                          if (allRunnersFailed(runners)) {
                            callback(true);
                          }
                          return;
                        }
                        apply(runners, function(runner) {
                          runner.forceMinPriority(handshake.transport.priority);
                        });
                        callback(null, handshake);
                      };
                    });
                  }
                }
                function connect2(strategies, minPriority, callbackBuilder) {
                  var runners = map(strategies, function(strategy, i3, _2, rs) {
                    return strategy.connect(minPriority, callbackBuilder(i3, rs));
                  });
                  return {
                    abort: function() {
                      apply(runners, abortRunner);
                    },
                    forceMinPriority: function(p2) {
                      apply(runners, function(runner) {
                        runner.forceMinPriority(p2);
                      });
                    }
                  };
                }
                function allRunnersFailed(runners) {
                  return collections_all(runners, function(runner) {
                    return Boolean(runner.error);
                  });
                }
                function abortRunner(runner) {
                  if (!runner.error && !runner.aborted) {
                    runner.abort();
                    runner.aborted = true;
                  }
                }
                ;
                class WebSocketPrioritizedCachedStrategy {
                  constructor(strategy, transports2, options) {
                    this.strategy = strategy;
                    this.transports = transports2;
                    this.ttl = options.ttl || 1800 * 1e3;
                    this.usingTLS = options.useTLS;
                    this.timeline = options.timeline;
                  }
                  isSupported() {
                    return this.strategy.isSupported();
                  }
                  connect(minPriority, callback) {
                    var usingTLS = this.usingTLS;
                    var info = fetchTransportCache(usingTLS);
                    var cacheSkipCount = info && info.cacheSkipCount ? info.cacheSkipCount : 0;
                    var strategies = [this.strategy];
                    if (info && info.timestamp + this.ttl >= util.now()) {
                      var transport = this.transports[info.transport];
                      if (transport) {
                        if (["ws", "wss"].includes(info.transport) || cacheSkipCount > 3) {
                          this.timeline.info({
                            cached: true,
                            transport: info.transport,
                            latency: info.latency
                          });
                          strategies.push(new SequentialStrategy([transport], {
                            timeout: info.latency * 2 + 1e3,
                            failFast: true
                          }));
                        } else {
                          cacheSkipCount++;
                        }
                      }
                    }
                    var startTimestamp = util.now();
                    var runner = strategies.pop().connect(minPriority, function cb(error, handshake) {
                      if (error) {
                        flushTransportCache(usingTLS);
                        if (strategies.length > 0) {
                          startTimestamp = util.now();
                          runner = strategies.pop().connect(minPriority, cb);
                        } else {
                          callback(error);
                        }
                      } else {
                        storeTransportCache(usingTLS, handshake.transport.name, util.now() - startTimestamp, cacheSkipCount);
                        callback(null, handshake);
                      }
                    });
                    return {
                      abort: function() {
                        runner.abort();
                      },
                      forceMinPriority: function(p2) {
                        minPriority = p2;
                        if (runner) {
                          runner.forceMinPriority(p2);
                        }
                      }
                    };
                  }
                }
                function getTransportCacheKey(usingTLS) {
                  return "pusherTransport" + (usingTLS ? "TLS" : "NonTLS");
                }
                function fetchTransportCache(usingTLS) {
                  var storage = runtime.getLocalStorage();
                  if (storage) {
                    try {
                      var serializedCache = storage[getTransportCacheKey(usingTLS)];
                      if (serializedCache) {
                        return JSON.parse(serializedCache);
                      }
                    } catch (e3) {
                      flushTransportCache(usingTLS);
                    }
                  }
                  return null;
                }
                function storeTransportCache(usingTLS, transport, latency, cacheSkipCount) {
                  var storage = runtime.getLocalStorage();
                  if (storage) {
                    try {
                      storage[getTransportCacheKey(usingTLS)] = safeJSONStringify({
                        timestamp: util.now(),
                        transport,
                        latency,
                        cacheSkipCount
                      });
                    } catch (e3) {
                    }
                  }
                }
                function flushTransportCache(usingTLS) {
                  var storage = runtime.getLocalStorage();
                  if (storage) {
                    try {
                      delete storage[getTransportCacheKey(usingTLS)];
                    } catch (e3) {
                    }
                  }
                }
                ;
                class DelayedStrategy {
                  constructor(strategy, { delay: number }) {
                    this.strategy = strategy;
                    this.options = { delay: number };
                  }
                  isSupported() {
                    return this.strategy.isSupported();
                  }
                  connect(minPriority, callback) {
                    var strategy = this.strategy;
                    var runner;
                    var timer = new OneOffTimer(this.options.delay, function() {
                      runner = strategy.connect(minPriority, callback);
                    });
                    return {
                      abort: function() {
                        timer.ensureAborted();
                        if (runner) {
                          runner.abort();
                        }
                      },
                      forceMinPriority: function(p2) {
                        minPriority = p2;
                        if (runner) {
                          runner.forceMinPriority(p2);
                        }
                      }
                    };
                  }
                }
                ;
                class IfStrategy {
                  constructor(test, trueBranch, falseBranch) {
                    this.test = test;
                    this.trueBranch = trueBranch;
                    this.falseBranch = falseBranch;
                  }
                  isSupported() {
                    var branch = this.test() ? this.trueBranch : this.falseBranch;
                    return branch.isSupported();
                  }
                  connect(minPriority, callback) {
                    var branch = this.test() ? this.trueBranch : this.falseBranch;
                    return branch.connect(minPriority, callback);
                  }
                }
                ;
                class FirstConnectedStrategy {
                  constructor(strategy) {
                    this.strategy = strategy;
                  }
                  isSupported() {
                    return this.strategy.isSupported();
                  }
                  connect(minPriority, callback) {
                    var runner = this.strategy.connect(minPriority, function(error, handshake) {
                      if (handshake) {
                        runner.abort();
                      }
                      callback(error, handshake);
                    });
                    return runner;
                  }
                }
                ;
                function testSupportsStrategy(strategy) {
                  return function() {
                    return strategy.isSupported();
                  };
                }
                var getDefaultStrategy = function(config, baseOptions, defineTransport2) {
                  var definedTransports = {};
                  function defineTransportStrategy(name, type, priority, options, manager) {
                    var transport = defineTransport2(config, name, type, priority, options, manager);
                    definedTransports[name] = transport;
                    return transport;
                  }
                  var ws_options = Object.assign({}, baseOptions, {
                    hostNonTLS: config.wsHost + ":" + config.wsPort,
                    hostTLS: config.wsHost + ":" + config.wssPort,
                    httpPath: config.wsPath
                  });
                  var wss_options = Object.assign({}, ws_options, {
                    useTLS: true
                  });
                  var sockjs_options = Object.assign({}, baseOptions, {
                    hostNonTLS: config.httpHost + ":" + config.httpPort,
                    hostTLS: config.httpHost + ":" + config.httpsPort,
                    httpPath: config.httpPath
                  });
                  var timeouts = {
                    loop: true,
                    timeout: 15e3,
                    timeoutLimit: 6e4
                  };
                  var ws_manager = new TransportManager({
                    minPingDelay: 1e4,
                    maxPingDelay: config.activityTimeout
                  });
                  var streaming_manager = new TransportManager({
                    lives: 2,
                    minPingDelay: 1e4,
                    maxPingDelay: config.activityTimeout
                  });
                  var ws_transport = defineTransportStrategy("ws", "ws", 3, ws_options, ws_manager);
                  var wss_transport = defineTransportStrategy("wss", "ws", 3, wss_options, ws_manager);
                  var sockjs_transport = defineTransportStrategy("sockjs", "sockjs", 1, sockjs_options);
                  var xhr_streaming_transport = defineTransportStrategy("xhr_streaming", "xhr_streaming", 1, sockjs_options, streaming_manager);
                  var xdr_streaming_transport = defineTransportStrategy("xdr_streaming", "xdr_streaming", 1, sockjs_options, streaming_manager);
                  var xhr_polling_transport = defineTransportStrategy("xhr_polling", "xhr_polling", 1, sockjs_options);
                  var xdr_polling_transport = defineTransportStrategy("xdr_polling", "xdr_polling", 1, sockjs_options);
                  var ws_loop = new SequentialStrategy([ws_transport], timeouts);
                  var wss_loop = new SequentialStrategy([wss_transport], timeouts);
                  var sockjs_loop = new SequentialStrategy([sockjs_transport], timeouts);
                  var streaming_loop = new SequentialStrategy([
                    new IfStrategy(testSupportsStrategy(xhr_streaming_transport), xhr_streaming_transport, xdr_streaming_transport)
                  ], timeouts);
                  var polling_loop = new SequentialStrategy([
                    new IfStrategy(testSupportsStrategy(xhr_polling_transport), xhr_polling_transport, xdr_polling_transport)
                  ], timeouts);
                  var http_loop = new SequentialStrategy([
                    new IfStrategy(testSupportsStrategy(streaming_loop), new BestConnectedEverStrategy([
                      streaming_loop,
                      new DelayedStrategy(polling_loop, { delay: 4e3 })
                    ]), polling_loop)
                  ], timeouts);
                  var http_fallback_loop = new IfStrategy(testSupportsStrategy(http_loop), http_loop, sockjs_loop);
                  var wsStrategy;
                  if (baseOptions.useTLS) {
                    wsStrategy = new BestConnectedEverStrategy([
                      ws_loop,
                      new DelayedStrategy(http_fallback_loop, { delay: 2e3 })
                    ]);
                  } else {
                    wsStrategy = new BestConnectedEverStrategy([
                      ws_loop,
                      new DelayedStrategy(wss_loop, { delay: 2e3 }),
                      new DelayedStrategy(http_fallback_loop, { delay: 5e3 })
                    ]);
                  }
                  return new WebSocketPrioritizedCachedStrategy(new FirstConnectedStrategy(new IfStrategy(testSupportsStrategy(ws_transport), wsStrategy, http_fallback_loop)), definedTransports, {
                    ttl: 18e5,
                    timeline: baseOptions.timeline,
                    useTLS: baseOptions.useTLS
                  });
                };
                const default_strategy = getDefaultStrategy;
                ;
                function transport_connection_initializer() {
                  var self2 = this;
                  self2.timeline.info(self2.buildTimelineMessage({
                    transport: self2.name + (self2.options.useTLS ? "s" : "")
                  }));
                  if (self2.hooks.isInitialized()) {
                    self2.changeState("initialized");
                  } else if (self2.hooks.file) {
                    self2.changeState("initializing");
                    Dependencies.load(self2.hooks.file, { useTLS: self2.options.useTLS }, function(error, callback) {
                      if (self2.hooks.isInitialized()) {
                        self2.changeState("initialized");
                        callback(true);
                      } else {
                        if (error) {
                          self2.onError(error);
                        }
                        self2.onClose();
                        callback(false);
                      }
                    });
                  } else {
                    self2.onClose();
                  }
                }
                ;
                var hooks = {
                  getRequest: function(socket) {
                    var xdr = new window.XDomainRequest();
                    xdr.ontimeout = function() {
                      socket.emit("error", new RequestTimedOut());
                      socket.close();
                    };
                    xdr.onerror = function(e3) {
                      socket.emit("error", e3);
                      socket.close();
                    };
                    xdr.onprogress = function() {
                      if (xdr.responseText && xdr.responseText.length > 0) {
                        socket.onChunk(200, xdr.responseText);
                      }
                    };
                    xdr.onload = function() {
                      if (xdr.responseText && xdr.responseText.length > 0) {
                        socket.onChunk(200, xdr.responseText);
                      }
                      socket.emit("finished", 200);
                      socket.close();
                    };
                    return xdr;
                  },
                  abortRequest: function(xdr) {
                    xdr.ontimeout = xdr.onerror = xdr.onprogress = xdr.onload = null;
                    xdr.abort();
                  }
                };
                const http_xdomain_request = hooks;
                ;
                const MAX_BUFFER_LENGTH = 256 * 1024;
                class HTTPRequest extends Dispatcher {
                  constructor(hooks2, method, url) {
                    super();
                    this.hooks = hooks2;
                    this.method = method;
                    this.url = url;
                  }
                  start(payload) {
                    this.position = 0;
                    this.xhr = this.hooks.getRequest(this);
                    this.unloader = () => {
                      this.close();
                    };
                    runtime.addUnloadListener(this.unloader);
                    this.xhr.open(this.method, this.url, true);
                    if (this.xhr.setRequestHeader) {
                      this.xhr.setRequestHeader("Content-Type", "application/json");
                    }
                    this.xhr.send(payload);
                  }
                  close() {
                    if (this.unloader) {
                      runtime.removeUnloadListener(this.unloader);
                      this.unloader = null;
                    }
                    if (this.xhr) {
                      this.hooks.abortRequest(this.xhr);
                      this.xhr = null;
                    }
                  }
                  onChunk(status, data) {
                    while (true) {
                      var chunk = this.advanceBuffer(data);
                      if (chunk) {
                        this.emit("chunk", { status, data: chunk });
                      } else {
                        break;
                      }
                    }
                    if (this.isBufferTooLong(data)) {
                      this.emit("buffer_too_long");
                    }
                  }
                  advanceBuffer(buffer) {
                    var unreadData = buffer.slice(this.position);
                    var endOfLinePosition = unreadData.indexOf("\n");
                    if (endOfLinePosition !== -1) {
                      this.position += endOfLinePosition + 1;
                      return unreadData.slice(0, endOfLinePosition);
                    } else {
                      return null;
                    }
                  }
                  isBufferTooLong(buffer) {
                    return this.position === buffer.length && buffer.length > MAX_BUFFER_LENGTH;
                  }
                }
                ;
                var State;
                (function(State2) {
                  State2[State2["CONNECTING"] = 0] = "CONNECTING";
                  State2[State2["OPEN"] = 1] = "OPEN";
                  State2[State2["CLOSED"] = 3] = "CLOSED";
                })(State || (State = {}));
                const state = State;
                ;
                var autoIncrement = 1;
                class HTTPSocket {
                  constructor(hooks2, url) {
                    this.hooks = hooks2;
                    this.session = randomNumber(1e3) + "/" + randomString(8);
                    this.location = getLocation(url);
                    this.readyState = state.CONNECTING;
                    this.openStream();
                  }
                  send(payload) {
                    return this.sendRaw(JSON.stringify([payload]));
                  }
                  ping() {
                    this.hooks.sendHeartbeat(this);
                  }
                  close(code, reason) {
                    this.onClose(code, reason, true);
                  }
                  sendRaw(payload) {
                    if (this.readyState === state.OPEN) {
                      try {
                        runtime.createSocketRequest("POST", getUniqueURL(getSendURL(this.location, this.session))).start(payload);
                        return true;
                      } catch (e3) {
                        return false;
                      }
                    } else {
                      return false;
                    }
                  }
                  reconnect() {
                    this.closeStream();
                    this.openStream();
                  }
                  onClose(code, reason, wasClean) {
                    this.closeStream();
                    this.readyState = state.CLOSED;
                    if (this.onclose) {
                      this.onclose({
                        code,
                        reason,
                        wasClean
                      });
                    }
                  }
                  onChunk(chunk) {
                    if (chunk.status !== 200) {
                      return;
                    }
                    if (this.readyState === state.OPEN) {
                      this.onActivity();
                    }
                    var payload;
                    var type = chunk.data.slice(0, 1);
                    switch (type) {
                      case "o":
                        payload = JSON.parse(chunk.data.slice(1) || "{}");
                        this.onOpen(payload);
                        break;
                      case "a":
                        payload = JSON.parse(chunk.data.slice(1) || "[]");
                        for (var i3 = 0; i3 < payload.length; i3++) {
                          this.onEvent(payload[i3]);
                        }
                        break;
                      case "m":
                        payload = JSON.parse(chunk.data.slice(1) || "null");
                        this.onEvent(payload);
                        break;
                      case "h":
                        this.hooks.onHeartbeat(this);
                        break;
                      case "c":
                        payload = JSON.parse(chunk.data.slice(1) || "[]");
                        this.onClose(payload[0], payload[1], true);
                        break;
                    }
                  }
                  onOpen(options) {
                    if (this.readyState === state.CONNECTING) {
                      if (options && options.hostname) {
                        this.location.base = replaceHost(this.location.base, options.hostname);
                      }
                      this.readyState = state.OPEN;
                      if (this.onopen) {
                        this.onopen();
                      }
                    } else {
                      this.onClose(1006, "Server lost session", true);
                    }
                  }
                  onEvent(event) {
                    if (this.readyState === state.OPEN && this.onmessage) {
                      this.onmessage({ data: event });
                    }
                  }
                  onActivity() {
                    if (this.onactivity) {
                      this.onactivity();
                    }
                  }
                  onError(error) {
                    if (this.onerror) {
                      this.onerror(error);
                    }
                  }
                  openStream() {
                    this.stream = runtime.createSocketRequest("POST", getUniqueURL(this.hooks.getReceiveURL(this.location, this.session)));
                    this.stream.bind("chunk", (chunk) => {
                      this.onChunk(chunk);
                    });
                    this.stream.bind("finished", (status) => {
                      this.hooks.onFinished(this, status);
                    });
                    this.stream.bind("buffer_too_long", () => {
                      this.reconnect();
                    });
                    try {
                      this.stream.start();
                    } catch (error) {
                      util.defer(() => {
                        this.onError(error);
                        this.onClose(1006, "Could not start streaming", false);
                      });
                    }
                  }
                  closeStream() {
                    if (this.stream) {
                      this.stream.unbind_all();
                      this.stream.close();
                      this.stream = null;
                    }
                  }
                }
                function getLocation(url) {
                  var parts = /([^\?]*)\/*(\??.*)/.exec(url);
                  return {
                    base: parts[1],
                    queryString: parts[2]
                  };
                }
                function getSendURL(url, session) {
                  return url.base + "/" + session + "/xhr_send";
                }
                function getUniqueURL(url) {
                  var separator = url.indexOf("?") === -1 ? "?" : "&";
                  return url + separator + "t=" + +/* @__PURE__ */ new Date() + "&n=" + autoIncrement++;
                }
                function replaceHost(url, hostname) {
                  var urlParts = /(https?:\/\/)([^\/:]+)((\/|:)?.*)/.exec(url);
                  return urlParts[1] + hostname + urlParts[3];
                }
                function randomNumber(max) {
                  return runtime.randomInt(max);
                }
                function randomString(length) {
                  var result = [];
                  for (var i3 = 0; i3 < length; i3++) {
                    result.push(randomNumber(32).toString(32));
                  }
                  return result.join("");
                }
                const http_socket = HTTPSocket;
                ;
                var http_streaming_socket_hooks = {
                  getReceiveURL: function(url, session) {
                    return url.base + "/" + session + "/xhr_streaming" + url.queryString;
                  },
                  onHeartbeat: function(socket) {
                    socket.sendRaw("[]");
                  },
                  sendHeartbeat: function(socket) {
                    socket.sendRaw("[]");
                  },
                  onFinished: function(socket, status) {
                    socket.onClose(1006, "Connection interrupted (" + status + ")", false);
                  }
                };
                const http_streaming_socket = http_streaming_socket_hooks;
                ;
                var http_polling_socket_hooks = {
                  getReceiveURL: function(url, session) {
                    return url.base + "/" + session + "/xhr" + url.queryString;
                  },
                  onHeartbeat: function() {
                  },
                  sendHeartbeat: function(socket) {
                    socket.sendRaw("[]");
                  },
                  onFinished: function(socket, status) {
                    if (status === 200) {
                      socket.reconnect();
                    } else {
                      socket.onClose(1006, "Connection interrupted (" + status + ")", false);
                    }
                  }
                };
                const http_polling_socket = http_polling_socket_hooks;
                ;
                var http_xhr_request_hooks = {
                  getRequest: function(socket) {
                    var Constructor = runtime.getXHRAPI();
                    var xhr = new Constructor();
                    xhr.onreadystatechange = xhr.onprogress = function() {
                      switch (xhr.readyState) {
                        case 3:
                          if (xhr.responseText && xhr.responseText.length > 0) {
                            socket.onChunk(xhr.status, xhr.responseText);
                          }
                          break;
                        case 4:
                          if (xhr.responseText && xhr.responseText.length > 0) {
                            socket.onChunk(xhr.status, xhr.responseText);
                          }
                          socket.emit("finished", xhr.status);
                          socket.close();
                          break;
                      }
                    };
                    return xhr;
                  },
                  abortRequest: function(xhr) {
                    xhr.onreadystatechange = null;
                    xhr.abort();
                  }
                };
                const http_xhr_request = http_xhr_request_hooks;
                ;
                var HTTP = {
                  createStreamingSocket(url) {
                    return this.createSocket(http_streaming_socket, url);
                  },
                  createPollingSocket(url) {
                    return this.createSocket(http_polling_socket, url);
                  },
                  createSocket(hooks2, url) {
                    return new http_socket(hooks2, url);
                  },
                  createXHR(method, url) {
                    return this.createRequest(http_xhr_request, method, url);
                  },
                  createRequest(hooks2, method, url) {
                    return new HTTPRequest(hooks2, method, url);
                  }
                };
                const http_http = HTTP;
                ;
                http_http.createXDR = function(method, url) {
                  return this.createRequest(http_xdomain_request, method, url);
                };
                const web_http_http = http_http;
                ;
                var Runtime = {
                  nextAuthCallbackID: 1,
                  auth_callbacks: {},
                  ScriptReceivers,
                  DependenciesReceivers,
                  getDefaultStrategy: default_strategy,
                  Transports: transports_transports,
                  transportConnectionInitializer: transport_connection_initializer,
                  HTTPFactory: web_http_http,
                  TimelineTransport: jsonp_timeline,
                  getXHRAPI() {
                    return window.XMLHttpRequest;
                  },
                  getWebSocketAPI() {
                    return window.WebSocket || window.MozWebSocket;
                  },
                  setup(PusherClass) {
                    if (typeof window !== "undefined") {
                      window.Pusher = PusherClass;
                      var initializeOnDocumentBody = () => {
                        this.onDocumentBody(PusherClass.ready);
                      };
                      if (!window.JSON) {
                        Dependencies.load("json2", {}, initializeOnDocumentBody);
                      } else {
                        initializeOnDocumentBody();
                      }
                    }
                  },
                  getDocument() {
                    return document;
                  },
                  getProtocol() {
                    return this.getDocument().location.protocol;
                  },
                  getAuthorizers() {
                    return { ajax: xhr_auth, jsonp: jsonp_auth };
                  },
                  onDocumentBody(callback) {
                    if (document.body) {
                      callback();
                    } else {
                      setTimeout(() => {
                        this.onDocumentBody(callback);
                      }, 0);
                    }
                  },
                  createJSONPRequest(url, data) {
                    return new JSONPRequest(url, data);
                  },
                  createScriptRequest(src) {
                    return new ScriptRequest(src);
                  },
                  getLocalStorage() {
                    try {
                      return window.localStorage;
                    } catch (e3) {
                      return void 0;
                    }
                  },
                  createXHR() {
                    if (this.getXHRAPI()) {
                      return this.createXMLHttpRequest();
                    } else {
                      return this.createMicrosoftXHR();
                    }
                  },
                  createXMLHttpRequest() {
                    var Constructor = this.getXHRAPI();
                    return new Constructor();
                  },
                  createMicrosoftXHR() {
                    return new ActiveXObject("Microsoft.XMLHTTP");
                  },
                  getNetwork() {
                    return Network;
                  },
                  createWebSocket(url) {
                    var Constructor = this.getWebSocketAPI();
                    return new Constructor(url);
                  },
                  createSocketRequest(method, url) {
                    if (this.isXHRSupported()) {
                      return this.HTTPFactory.createXHR(method, url);
                    } else if (this.isXDRSupported(url.indexOf("https:") === 0)) {
                      return this.HTTPFactory.createXDR(method, url);
                    } else {
                      throw "Cross-origin HTTP requests are not supported";
                    }
                  },
                  isXHRSupported() {
                    var Constructor = this.getXHRAPI();
                    return Boolean(Constructor) && new Constructor().withCredentials !== void 0;
                  },
                  isXDRSupported(useTLS) {
                    var protocol2 = useTLS ? "https:" : "http:";
                    var documentProtocol = this.getProtocol();
                    return Boolean(window["XDomainRequest"]) && documentProtocol === protocol2;
                  },
                  addUnloadListener(listener) {
                    if (window.addEventListener !== void 0) {
                      window.addEventListener("pagehide", listener, false);
                    } else if (window.attachEvent !== void 0) {
                      window.attachEvent("onunload", listener);
                    }
                  },
                  removeUnloadListener(listener) {
                    if (window.addEventListener !== void 0) {
                      window.removeEventListener("pagehide", listener, false);
                    } else if (window.detachEvent !== void 0) {
                      window.detachEvent("onunload", listener);
                    }
                  },
                  randomInt(max) {
                    const crypto2 = window.crypto || window["msCrypto"];
                    const limit = Math.floor(Math.pow(2, 32) / max) * max;
                    let random;
                    do {
                      random = crypto2.getRandomValues(new Uint32Array(1))[0];
                    } while (random >= limit);
                    return random % max;
                  }
                };
                const runtime = Runtime;
                ;
                var TimelineLevel;
                (function(TimelineLevel2) {
                  TimelineLevel2[TimelineLevel2["ERROR"] = 3] = "ERROR";
                  TimelineLevel2[TimelineLevel2["INFO"] = 6] = "INFO";
                  TimelineLevel2[TimelineLevel2["DEBUG"] = 7] = "DEBUG";
                })(TimelineLevel || (TimelineLevel = {}));
                const level = TimelineLevel;
                ;
                class Timeline {
                  constructor(key, session, options) {
                    this.key = key;
                    this.session = session;
                    this.events = [];
                    this.options = options || {};
                    this.sent = 0;
                    this.uniqueID = 0;
                  }
                  log(level2, event) {
                    if (level2 <= this.options.level) {
                      this.events.push(extend({}, event, { timestamp: util.now() }));
                      if (this.options.limit && this.events.length > this.options.limit) {
                        this.events.shift();
                      }
                    }
                  }
                  error(event) {
                    this.log(level.ERROR, event);
                  }
                  info(event) {
                    this.log(level.INFO, event);
                  }
                  debug(event) {
                    this.log(level.DEBUG, event);
                  }
                  isEmpty() {
                    return this.events.length === 0;
                  }
                  send(sendfn, callback) {
                    var data = extend({
                      session: this.session,
                      bundle: this.sent + 1,
                      key: this.key,
                      lib: "js",
                      version: this.options.version,
                      cluster: this.options.cluster,
                      features: this.options.features,
                      timeline: this.events
                    }, this.options.params);
                    this.events = [];
                    sendfn(data, (error, result) => {
                      if (!error) {
                        this.sent++;
                      }
                      if (callback) {
                        callback(error, result);
                      }
                    });
                    return true;
                  }
                  generateUniqueID() {
                    this.uniqueID++;
                    return this.uniqueID;
                  }
                }
                ;
                class TransportStrategy {
                  constructor(name, priority, transport, options) {
                    this.name = name;
                    this.priority = priority;
                    this.transport = transport;
                    this.options = options || {};
                  }
                  isSupported() {
                    return this.transport.isSupported({
                      useTLS: this.options.useTLS
                    });
                  }
                  connect(minPriority, callback) {
                    if (!this.isSupported()) {
                      return failAttempt(new UnsupportedStrategy(), callback);
                    } else if (this.priority < minPriority) {
                      return failAttempt(new TransportPriorityTooLow(), callback);
                    }
                    var connected = false;
                    var transport = this.transport.createConnection(this.name, this.priority, this.options.key, this.options);
                    var handshake = null;
                    var onInitialized = function() {
                      transport.unbind("initialized", onInitialized);
                      transport.connect();
                    };
                    var onOpen = function() {
                      handshake = factory.createHandshake(transport, function(result) {
                        connected = true;
                        unbindListeners();
                        callback(null, result);
                      });
                    };
                    var onError = function(error) {
                      unbindListeners();
                      callback(error);
                    };
                    var onClosed = function() {
                      unbindListeners();
                      var serializedTransport;
                      serializedTransport = safeJSONStringify(transport);
                      callback(new TransportClosed(serializedTransport));
                    };
                    var unbindListeners = function() {
                      transport.unbind("initialized", onInitialized);
                      transport.unbind("open", onOpen);
                      transport.unbind("error", onError);
                      transport.unbind("closed", onClosed);
                    };
                    transport.bind("initialized", onInitialized);
                    transport.bind("open", onOpen);
                    transport.bind("error", onError);
                    transport.bind("closed", onClosed);
                    transport.initialize();
                    return {
                      abort: () => {
                        if (connected) {
                          return;
                        }
                        unbindListeners();
                        if (handshake) {
                          handshake.close();
                        } else {
                          transport.close();
                        }
                      },
                      forceMinPriority: (p2) => {
                        if (connected) {
                          return;
                        }
                        if (this.priority < p2) {
                          if (handshake) {
                            handshake.close();
                          } else {
                            transport.close();
                          }
                        }
                      }
                    };
                  }
                }
                function failAttempt(error, callback) {
                  util.defer(function() {
                    callback(error);
                  });
                  return {
                    abort: function() {
                    },
                    forceMinPriority: function() {
                    }
                  };
                }
                ;
                const { Transports: strategy_builder_Transports } = runtime;
                var defineTransport = function(config, name, type, priority, options, manager) {
                  var transportClass = strategy_builder_Transports[type];
                  if (!transportClass) {
                    throw new UnsupportedTransport(type);
                  }
                  var enabled = (!config.enabledTransports || arrayIndexOf(config.enabledTransports, name) !== -1) && (!config.disabledTransports || arrayIndexOf(config.disabledTransports, name) === -1);
                  var transport;
                  if (enabled) {
                    options = Object.assign({ ignoreNullOrigin: config.ignoreNullOrigin }, options);
                    transport = new TransportStrategy(name, priority, manager ? manager.getAssistant(transportClass) : transportClass, options);
                  } else {
                    transport = strategy_builder_UnsupportedStrategy;
                  }
                  return transport;
                };
                var strategy_builder_UnsupportedStrategy = {
                  isSupported: function() {
                    return false;
                  },
                  connect: function(_2, callback) {
                    var deferred = util.defer(function() {
                      callback(new UnsupportedStrategy());
                    });
                    return {
                      abort: function() {
                        deferred.ensureAborted();
                      },
                      forceMinPriority: function() {
                      }
                    };
                  }
                };
                ;
                function validateOptions(options) {
                  if (options == null) {
                    throw "You must pass an options object";
                  }
                  if (options.cluster == null) {
                    throw "Options object must provide a cluster";
                  }
                  if ("disableStats" in options) {
                    logger.warn("The disableStats option is deprecated in favor of enableStats");
                  }
                }
                ;
                const composeChannelQuery = (params, authOptions) => {
                  var query = "socket_id=" + encodeURIComponent(params.socketId);
                  for (var key in authOptions.params) {
                    query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(authOptions.params[key]);
                  }
                  if (authOptions.paramsProvider != null) {
                    let dynamicParams = authOptions.paramsProvider();
                    for (var key in dynamicParams) {
                      query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(dynamicParams[key]);
                    }
                  }
                  return query;
                };
                const UserAuthenticator = (authOptions) => {
                  if (typeof runtime.getAuthorizers()[authOptions.transport] === "undefined") {
                    throw `'${authOptions.transport}' is not a recognized auth transport`;
                  }
                  return (params, callback) => {
                    const query = composeChannelQuery(params, authOptions);
                    runtime.getAuthorizers()[authOptions.transport](runtime, query, authOptions, AuthRequestType.UserAuthentication, callback);
                  };
                };
                const user_authenticator = UserAuthenticator;
                ;
                const channel_authorizer_composeChannelQuery = (params, authOptions) => {
                  var query = "socket_id=" + encodeURIComponent(params.socketId);
                  query += "&channel_name=" + encodeURIComponent(params.channelName);
                  for (var key in authOptions.params) {
                    query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(authOptions.params[key]);
                  }
                  if (authOptions.paramsProvider != null) {
                    let dynamicParams = authOptions.paramsProvider();
                    for (var key in dynamicParams) {
                      query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(dynamicParams[key]);
                    }
                  }
                  return query;
                };
                const ChannelAuthorizer = (authOptions) => {
                  if (typeof runtime.getAuthorizers()[authOptions.transport] === "undefined") {
                    throw `'${authOptions.transport}' is not a recognized auth transport`;
                  }
                  return (params, callback) => {
                    const query = channel_authorizer_composeChannelQuery(params, authOptions);
                    runtime.getAuthorizers()[authOptions.transport](runtime, query, authOptions, AuthRequestType.ChannelAuthorization, callback);
                  };
                };
                const channel_authorizer = ChannelAuthorizer;
                ;
                const ChannelAuthorizerProxy = (pusher2, authOptions, channelAuthorizerGenerator) => {
                  const deprecatedAuthorizerOptions = {
                    authTransport: authOptions.transport,
                    authEndpoint: authOptions.endpoint,
                    auth: {
                      params: authOptions.params,
                      headers: authOptions.headers
                    }
                  };
                  return (params, callback) => {
                    const channel = pusher2.channel(params.channelName);
                    const channelAuthorizer = channelAuthorizerGenerator(channel, deprecatedAuthorizerOptions);
                    channelAuthorizer.authorize(params.socketId, callback);
                  };
                };
                ;
                function getConfig(opts, pusher2) {
                  let config = {
                    activityTimeout: opts.activityTimeout || defaults.activityTimeout,
                    cluster: opts.cluster,
                    httpPath: opts.httpPath || defaults.httpPath,
                    httpPort: opts.httpPort || defaults.httpPort,
                    httpsPort: opts.httpsPort || defaults.httpsPort,
                    pongTimeout: opts.pongTimeout || defaults.pongTimeout,
                    statsHost: opts.statsHost || defaults.stats_host,
                    unavailableTimeout: opts.unavailableTimeout || defaults.unavailableTimeout,
                    wsPath: opts.wsPath || defaults.wsPath,
                    wsPort: opts.wsPort || defaults.wsPort,
                    wssPort: opts.wssPort || defaults.wssPort,
                    enableStats: getEnableStatsConfig(opts),
                    httpHost: getHttpHost(opts),
                    useTLS: shouldUseTLS(opts),
                    wsHost: getWebsocketHost(opts),
                    userAuthenticator: buildUserAuthenticator(opts),
                    channelAuthorizer: buildChannelAuthorizer(opts, pusher2)
                  };
                  if ("disabledTransports" in opts)
                    config.disabledTransports = opts.disabledTransports;
                  if ("enabledTransports" in opts)
                    config.enabledTransports = opts.enabledTransports;
                  if ("ignoreNullOrigin" in opts)
                    config.ignoreNullOrigin = opts.ignoreNullOrigin;
                  if ("timelineParams" in opts)
                    config.timelineParams = opts.timelineParams;
                  if ("nacl" in opts) {
                    config.nacl = opts.nacl;
                  }
                  return config;
                }
                function getHttpHost(opts) {
                  if (opts.httpHost) {
                    return opts.httpHost;
                  }
                  if (opts.cluster) {
                    return `sockjs-${opts.cluster}.pusher.com`;
                  }
                  return defaults.httpHost;
                }
                function getWebsocketHost(opts) {
                  if (opts.wsHost) {
                    return opts.wsHost;
                  }
                  return getWebsocketHostFromCluster(opts.cluster);
                }
                function getWebsocketHostFromCluster(cluster) {
                  return `ws-${cluster}.pusher.com`;
                }
                function shouldUseTLS(opts) {
                  if (runtime.getProtocol() === "https:") {
                    return true;
                  } else if (opts.forceTLS === false) {
                    return false;
                  }
                  return true;
                }
                function getEnableStatsConfig(opts) {
                  if ("enableStats" in opts) {
                    return opts.enableStats;
                  }
                  if ("disableStats" in opts) {
                    return !opts.disableStats;
                  }
                  return false;
                }
                const hasCustomHandler = (auth) => {
                  return "customHandler" in auth && auth["customHandler"] != null;
                };
                function buildUserAuthenticator(opts) {
                  const userAuthentication = Object.assign(Object.assign({}, defaults.userAuthentication), opts.userAuthentication);
                  if (hasCustomHandler(userAuthentication)) {
                    return userAuthentication["customHandler"];
                  }
                  return user_authenticator(userAuthentication);
                }
                function buildChannelAuth(opts, pusher2) {
                  let channelAuthorization;
                  if ("channelAuthorization" in opts) {
                    channelAuthorization = Object.assign(Object.assign({}, defaults.channelAuthorization), opts.channelAuthorization);
                  } else {
                    channelAuthorization = {
                      transport: opts.authTransport || defaults.authTransport,
                      endpoint: opts.authEndpoint || defaults.authEndpoint
                    };
                    if ("auth" in opts) {
                      if ("params" in opts.auth)
                        channelAuthorization.params = opts.auth.params;
                      if ("headers" in opts.auth)
                        channelAuthorization.headers = opts.auth.headers;
                    }
                    if ("authorizer" in opts) {
                      channelAuthorization.customHandler = ChannelAuthorizerProxy(pusher2, channelAuthorization, opts.authorizer);
                    }
                  }
                  return channelAuthorization;
                }
                function buildChannelAuthorizer(opts, pusher2) {
                  const channelAuthorization = buildChannelAuth(opts, pusher2);
                  if (hasCustomHandler(channelAuthorization)) {
                    return channelAuthorization["customHandler"];
                  }
                  return channel_authorizer(channelAuthorization);
                }
                ;
                class WatchlistFacade extends Dispatcher {
                  constructor(pusher2) {
                    super(function(eventName, data) {
                      logger.debug(`No callbacks on watchlist events for ${eventName}`);
                    });
                    this.pusher = pusher2;
                    this.bindWatchlistInternalEvent();
                  }
                  handleEvent(pusherEvent) {
                    pusherEvent.data.events.forEach((watchlistEvent) => {
                      this.emit(watchlistEvent.name, watchlistEvent);
                    });
                  }
                  bindWatchlistInternalEvent() {
                    this.pusher.connection.bind("message", (pusherEvent) => {
                      var eventName = pusherEvent.event;
                      if (eventName === "pusher_internal:watchlist_events") {
                        this.handleEvent(pusherEvent);
                      }
                    });
                  }
                }
                ;
                function flatPromise() {
                  let resolve, reject;
                  const promise = new Promise((res, rej) => {
                    resolve = res;
                    reject = rej;
                  });
                  return { promise, resolve, reject };
                }
                const flat_promise = flatPromise;
                ;
                class UserFacade extends Dispatcher {
                  constructor(pusher2) {
                    super(function(eventName, data) {
                      logger.debug("No callbacks on user for " + eventName);
                    });
                    this.signin_requested = false;
                    this.user_data = null;
                    this.serverToUserChannel = null;
                    this.signinDonePromise = null;
                    this._signinDoneResolve = null;
                    this._onAuthorize = (err, authData) => {
                      if (err) {
                        logger.warn(`Error during signin: ${err}`);
                        this.emit("pusher:signin_error", Object.assign({}, {
                          type: "AuthError",
                          error: err.message
                        }, err instanceof HTTPAuthError ? { status: err.status } : {}));
                        this._cleanup();
                        return;
                      }
                      this.pusher.send_event("pusher:signin", {
                        auth: authData.auth,
                        user_data: authData.user_data
                      });
                    };
                    this.pusher = pusher2;
                    this.pusher.connection.bind("state_change", ({ previous, current }) => {
                      if (previous !== "connected" && current === "connected") {
                        this._signin();
                      }
                      if (previous === "connected" && current !== "connected") {
                        this._cleanup();
                        this._newSigninPromiseIfNeeded();
                      }
                    });
                    this.watchlist = new WatchlistFacade(pusher2);
                    this.pusher.connection.bind("message", (event) => {
                      var eventName = event.event;
                      if (eventName === "pusher:signin_success") {
                        this._onSigninSuccess(event.data);
                      }
                      if (this.serverToUserChannel && this.serverToUserChannel.name === event.channel) {
                        this.serverToUserChannel.handleEvent(event);
                      }
                    });
                  }
                  signin() {
                    if (this.signin_requested) {
                      return;
                    }
                    this.signin_requested = true;
                    this._signin();
                  }
                  _signin() {
                    if (!this.signin_requested) {
                      return;
                    }
                    this._newSigninPromiseIfNeeded();
                    if (this.pusher.connection.state !== "connected") {
                      return;
                    }
                    this.pusher.config.userAuthenticator({
                      socketId: this.pusher.connection.socket_id
                    }, this._onAuthorize);
                  }
                  _onSigninSuccess(data) {
                    try {
                      this.user_data = JSON.parse(data.user_data);
                    } catch (e3) {
                      logger.error(`Failed parsing user data after signin: ${data.user_data}`);
                      this._cleanup();
                      return;
                    }
                    if (typeof this.user_data.id !== "string" || this.user_data.id === "") {
                      logger.error(`user_data doesn't contain an id. user_data: ${this.user_data}`);
                      this._cleanup();
                      return;
                    }
                    this._signinDoneResolve();
                    this._subscribeChannels();
                  }
                  _subscribeChannels() {
                    const ensure_subscribed = (channel) => {
                      if (channel.subscriptionPending && channel.subscriptionCancelled) {
                        channel.reinstateSubscription();
                      } else if (!channel.subscriptionPending && this.pusher.connection.state === "connected") {
                        channel.subscribe();
                      }
                    };
                    this.serverToUserChannel = new Channel(`#server-to-user-${this.user_data.id}`, this.pusher);
                    this.serverToUserChannel.bind_global((eventName, data) => {
                      if (eventName.indexOf("pusher_internal:") === 0 || eventName.indexOf("pusher:") === 0) {
                        return;
                      }
                      this.emit(eventName, data);
                    });
                    ensure_subscribed(this.serverToUserChannel);
                  }
                  _cleanup() {
                    this.user_data = null;
                    if (this.serverToUserChannel) {
                      this.serverToUserChannel.unbind_all();
                      this.serverToUserChannel.disconnect();
                      this.serverToUserChannel = null;
                    }
                    if (this.signin_requested) {
                      this._signinDoneResolve();
                    }
                  }
                  _newSigninPromiseIfNeeded() {
                    if (!this.signin_requested) {
                      return;
                    }
                    if (this.signinDonePromise && !this.signinDonePromise.done) {
                      return;
                    }
                    const { promise, resolve, reject: _2 } = flat_promise();
                    promise.done = false;
                    const setDone = () => {
                      promise.done = true;
                    };
                    promise.then(setDone).catch(setDone);
                    this.signinDonePromise = promise;
                    this._signinDoneResolve = resolve;
                  }
                }
                ;
                class Pusher2 {
                  static ready() {
                    Pusher2.isReady = true;
                    for (var i3 = 0, l3 = Pusher2.instances.length; i3 < l3; i3++) {
                      Pusher2.instances[i3].connect();
                    }
                  }
                  static getClientFeatures() {
                    return keys(filterObject({ ws: runtime.Transports.ws }, function(t2) {
                      return t2.isSupported({});
                    }));
                  }
                  constructor(app_key, options) {
                    checkAppKey(app_key);
                    validateOptions(options);
                    this.key = app_key;
                    this.options = options;
                    this.config = getConfig(this.options, this);
                    this.channels = factory.createChannels();
                    this.global_emitter = new Dispatcher();
                    this.sessionID = runtime.randomInt(1e9);
                    this.timeline = new Timeline(this.key, this.sessionID, {
                      cluster: this.config.cluster,
                      features: Pusher2.getClientFeatures(),
                      params: this.config.timelineParams || {},
                      limit: 50,
                      level: level.INFO,
                      version: defaults.VERSION
                    });
                    if (this.config.enableStats) {
                      this.timelineSender = factory.createTimelineSender(this.timeline, {
                        host: this.config.statsHost,
                        path: "/timeline/v2/" + runtime.TimelineTransport.name
                      });
                    }
                    var getStrategy = (options2) => {
                      return runtime.getDefaultStrategy(this.config, options2, defineTransport);
                    };
                    this.connection = factory.createConnectionManager(this.key, {
                      getStrategy,
                      timeline: this.timeline,
                      activityTimeout: this.config.activityTimeout,
                      pongTimeout: this.config.pongTimeout,
                      unavailableTimeout: this.config.unavailableTimeout,
                      useTLS: Boolean(this.config.useTLS)
                    });
                    this.connection.bind("connected", () => {
                      this.subscribeAll();
                      if (this.timelineSender) {
                        this.timelineSender.send(this.connection.isUsingTLS());
                      }
                    });
                    this.connection.bind("message", (event) => {
                      var eventName = event.event;
                      var internal = eventName.indexOf("pusher_internal:") === 0;
                      if (event.channel) {
                        var channel = this.channel(event.channel);
                        if (channel) {
                          channel.handleEvent(event);
                        }
                      }
                      if (!internal) {
                        this.global_emitter.emit(event.event, event.data);
                      }
                    });
                    this.connection.bind("connecting", () => {
                      this.channels.disconnect();
                    });
                    this.connection.bind("disconnected", () => {
                      this.channels.disconnect();
                    });
                    this.connection.bind("error", (err) => {
                      logger.warn(err);
                    });
                    Pusher2.instances.push(this);
                    this.timeline.info({ instances: Pusher2.instances.length });
                    this.user = new UserFacade(this);
                    if (Pusher2.isReady) {
                      this.connect();
                    }
                  }
                  switchCluster(options) {
                    const { appKey, cluster } = options;
                    this.key = appKey;
                    this.options = Object.assign(Object.assign({}, this.options), { cluster });
                    this.config = getConfig(this.options, this);
                    this.connection.switchCluster(this.key);
                  }
                  channel(name) {
                    return this.channels.find(name);
                  }
                  allChannels() {
                    return this.channels.all();
                  }
                  connect() {
                    this.connection.connect();
                    if (this.timelineSender) {
                      if (!this.timelineSenderTimer) {
                        var usingTLS = this.connection.isUsingTLS();
                        var timelineSender = this.timelineSender;
                        this.timelineSenderTimer = new PeriodicTimer(6e4, function() {
                          timelineSender.send(usingTLS);
                        });
                      }
                    }
                  }
                  disconnect() {
                    this.connection.disconnect();
                    if (this.timelineSenderTimer) {
                      this.timelineSenderTimer.ensureAborted();
                      this.timelineSenderTimer = null;
                    }
                  }
                  bind(event_name, callback, context) {
                    this.global_emitter.bind(event_name, callback, context);
                    return this;
                  }
                  unbind(event_name, callback, context) {
                    this.global_emitter.unbind(event_name, callback, context);
                    return this;
                  }
                  bind_global(callback) {
                    this.global_emitter.bind_global(callback);
                    return this;
                  }
                  unbind_global(callback) {
                    this.global_emitter.unbind_global(callback);
                    return this;
                  }
                  unbind_all(callback) {
                    this.global_emitter.unbind_all();
                    return this;
                  }
                  subscribeAll() {
                    var channelName;
                    for (channelName in this.channels.channels) {
                      if (this.channels.channels.hasOwnProperty(channelName)) {
                        this.subscribe(channelName);
                      }
                    }
                  }
                  subscribe(channel_name) {
                    var channel = this.channels.add(channel_name, this);
                    if (channel.subscriptionPending && channel.subscriptionCancelled) {
                      channel.reinstateSubscription();
                    } else if (!channel.subscriptionPending && this.connection.state === "connected") {
                      channel.subscribe();
                    }
                    return channel;
                  }
                  unsubscribe(channel_name) {
                    var channel = this.channels.find(channel_name);
                    if (channel && channel.subscriptionPending) {
                      channel.cancelSubscription();
                    } else {
                      channel = this.channels.remove(channel_name);
                      if (channel && channel.subscribed) {
                        channel.unsubscribe();
                      }
                    }
                  }
                  send_event(event_name, data, channel) {
                    return this.connection.send_event(event_name, data, channel);
                  }
                  shouldUseTLS() {
                    return this.config.useTLS;
                  }
                  signin() {
                    this.user.signin();
                  }
                }
                Pusher2.instances = [];
                Pusher2.isReady = false;
                Pusher2.logToConsole = false;
                Pusher2.Runtime = runtime;
                Pusher2.ScriptReceivers = runtime.ScriptReceivers;
                Pusher2.DependenciesReceivers = runtime.DependenciesReceivers;
                Pusher2.auth_callbacks = runtime.auth_callbacks;
                const pusher = Pusher2;
                function checkAppKey(key) {
                  if (key === null || key === void 0) {
                    throw "You must pass your app key when you instantiate Pusher.";
                  }
                }
                runtime.setup(Pusher2);
              }
              /******/
            };
            var __webpack_module_cache__ = {};
            function __webpack_require__(moduleId) {
              var cachedModule = __webpack_module_cache__[moduleId];
              if (cachedModule !== void 0) {
                return cachedModule.exports;
              }
              var module2 = __webpack_module_cache__[moduleId] = {
                /******/
                // no module.id needed
                /******/
                // no module.loaded needed
                /******/
                exports: {}
                /******/
              };
              __webpack_modules__[moduleId].call(module2.exports, module2, module2.exports, __webpack_require__);
              return module2.exports;
            }
            (() => {
              __webpack_require__.d = (exports2, definition) => {
                for (var key in definition) {
                  if (__webpack_require__.o(definition, key) && !__webpack_require__.o(exports2, key)) {
                    Object.defineProperty(exports2, key, { enumerable: true, get: definition[key] });
                  }
                }
              };
            })();
            (() => {
              __webpack_require__.o = (obj, prop) => Object.prototype.hasOwnProperty.call(obj, prop);
            })();
            var __webpack_exports__ = __webpack_require__(721);
            return __webpack_exports__;
          })()
        );
      });
    }
  });

  // main.js
  var main_exports = {};
  __export(main_exports, {
    connect: () => connect
  });

  // node_modules/laravel-echo/dist/echo.js
  var e = class {
    constructor() {
      this.notificationCreatedEvent = ".Illuminate\\Notifications\\Events\\BroadcastNotificationCreated";
    }
    listenForWhisper(e3, t2) {
      return this.listen(".client-" + e3, t2);
    }
    notification(e3) {
      return this.listen(this.notificationCreatedEvent, e3);
    }
    stopListeningForNotification(e3) {
      return this.stopListening(this.notificationCreatedEvent, e3);
    }
    stopListeningForWhisper(e3, t2) {
      return this.stopListening(".client-" + e3, t2);
    }
  };
  var t = class {
    constructor(e3) {
      this.namespace = e3;
    }
    format(e3) {
      return [".", "\\"].includes(e3.charAt(0)) ? e3.substring(1) : (this.namespace && (e3 = this.namespace + "." + e3), e3.replace(/\./g, "\\"));
    }
    setNamespace(e3) {
      this.namespace = e3;
    }
  };
  function n(e3) {
    try {
      return Reflect.construct(String, [], e3), true;
    } catch {
      return false;
    }
  }
  var r = class extends e {
    constructor(e3, n2, r2) {
      super(), this.listeners = /* @__PURE__ */ new Map(), this.subscribedCallbacks = [], this.errorCallbacks = [], this.name = e3, this.options = n2, this.whisperer = r2, this.eventFormatter = new t(this.options.namespace);
    }
    listen(e3, t2) {
      let n2 = this.eventFormatter.format(e3);
      return this.listeners.set(n2, [...this.listeners.get(n2) ?? [], t2]), this;
    }
    stopListening(e3, t2) {
      let n2 = this.eventFormatter.format(e3);
      if (!t2) this.listeners.delete(n2);
      else {
        let e4 = (this.listeners.get(n2) ?? []).filter((e5) => e5 !== t2);
        e4.length === 0 ? this.listeners.delete(n2) : this.listeners.set(n2, e4);
      }
      return this;
    }
    subscribed(e3) {
      return this.subscribedCallbacks.push(e3), this;
    }
    error(e3) {
      return this.errorCallbacks.push(e3), this;
    }
    whisper(e3, t2) {
      throw Error("Public Mercure channels do not support whisper(): use a private or presence channel, whose members are granted a whisper topic.");
    }
    listenForWhisper(e3, t2) {
      return console.warn(`listenForWhisper("${e3}"): public Mercure channels cannot receive whispers, so this listener will never fire.`), super.listenForWhisper(e3, t2);
    }
    dispatch(e3, t2) {
      (this.listeners.get(e3) ?? []).forEach((e4) => e4(t2));
    }
    notifySubscribed() {
      this.subscribedCallbacks.forEach((e3) => e3());
    }
    notifyError(e3) {
      this.errorCallbacks.forEach((t2) => t2(e3));
    }
  };
  var i = class extends r {
    whisper(e3, t2) {
      if (!this.whisperer) throw Error(`whisper("${e3}"): this channel was constructed without a connector to publish through.`);
      return this.whisperer.whisper(this.name, e3, t2), this;
    }
    listenForWhisper(e3, t2) {
      return this.whisperer?.listenForWhispers(this.name), this.listen(".client-" + e3, t2);
    }
  };
  var a = class extends i {
  };
  var o = class extends i {
    constructor(...e3) {
      super(...e3), this.hereCallbacks = [], this.joiningCallbacks = [], this.leavingCallbacks = [], this.members = /* @__PURE__ */ new Map(), this.memberCounts = /* @__PURE__ */ new Map(), this.seeded = false;
    }
    here(e3) {
      return this.hereCallbacks.push(e3), this.seeded && this.invoke(e3, this.uniqueMembers()), this;
    }
    joining(e3) {
      return this.joiningCallbacks.push(e3), this;
    }
    leaving(e3) {
      return this.leavingCallbacks.push(e3), this;
    }
    setInitialMembers(e3) {
      this.members = new Map(e3.map(([e4, t2]) => [e4, s(t2)])), this.memberCounts = /* @__PURE__ */ new Map();
      for (let e4 of this.members.values()) this.memberCounts.set(e4.key, (this.memberCounts.get(e4.key) ?? 0) + 1);
      this.seeded = true, this.hereCallbacks.forEach((e4) => this.invoke(e4, this.uniqueMembers()));
    }
    applySubscriptionEvent(e3, t2, n2) {
      if (t2) {
        if (this.members.has(e3)) return;
        let t3 = s(n2 ?? {}), r2 = (this.memberCounts.get(t3.key) ?? 0) + 1;
        this.members.set(e3, t3), this.memberCounts.set(t3.key, r2), r2 === 1 && this.joiningCallbacks.forEach((e4) => this.invoke(e4, t3.info));
      } else {
        let t3 = this.members.get(e3);
        if (!t3) return;
        let n3 = (this.memberCounts.get(t3.key) ?? 1) - 1;
        this.members.delete(e3), n3 <= 0 ? (this.memberCounts.delete(t3.key), this.leavingCallbacks.forEach((e4) => this.invoke(e4, t3.info))) : this.memberCounts.set(t3.key, n3);
      }
    }
    uniqueMembers() {
      let e3 = /* @__PURE__ */ new Set(), t2 = [];
      for (let n2 of this.members.values()) e3.has(n2.key) || (e3.add(n2.key), t2.push(n2.info));
      return t2;
    }
    invoke(e3, t2) {
      try {
        e3(t2);
      } catch (e4) {
        console.error(`A "${this.name}" presence callback threw:`, e4);
      }
    }
  };
  function s(e3) {
    if (typeof e3 == "object" && e3 && "user_id" in e3 && typeof e3.user_id == "string") {
      let t2 = e3;
      return {
        key: "user:" + t2.user_id,
        info: t2.user_info
      };
    }
    return {
      key: "payload:" + (JSON.stringify(e3) ?? "undefined"),
      info: e3
    };
  }
  var c = class extends e {
    constructor(e3, n2, r2) {
      super(), this.name = n2, this.pusher = e3, this.options = r2, this.eventFormatter = new t(this.options.namespace), this.subscribe();
    }
    subscribe() {
      this.subscription = this.pusher.subscribe(this.name);
    }
    unsubscribe() {
      this.pusher.unsubscribe(this.name);
    }
    listen(e3, t2) {
      return this.on(this.eventFormatter.format(e3), t2), this;
    }
    listenToAll(e3) {
      return this.subscription.bind_global((t2, n2) => {
        if (t2.startsWith("pusher:")) return;
        let r2 = String(this.options.namespace ?? "").replace(/\./g, "\\");
        e3(t2.startsWith(r2) ? t2.substring(r2.length + 1) : "." + t2, n2);
      }), this;
    }
    stopListening(e3, t2) {
      return t2 ? this.subscription.unbind(this.eventFormatter.format(e3), t2) : this.subscription.unbind(this.eventFormatter.format(e3)), this;
    }
    stopListeningToAll(e3) {
      return e3 ? this.subscription.unbind_global(e3) : this.subscription.unbind_global(), this;
    }
    subscribed(e3) {
      return this.on("pusher:subscription_succeeded", () => {
        e3();
      }), this;
    }
    error(e3) {
      return this.on("pusher:subscription_error", (t2) => {
        e3(t2);
      }), this;
    }
    on(e3, t2) {
      return this.subscription.bind(e3, t2), this;
    }
  };
  var l = class extends c {
    whisper(e3, t2) {
      return this.pusher.channels.channels[this.name].trigger(`client-${e3}`, t2), this;
    }
  };
  var u = class extends c {
    whisper(e3, t2) {
      return this.pusher.channels.channels[this.name].trigger(`client-${e3}`, t2), this;
    }
  };
  var d = class extends l {
    here(e3) {
      return this.on("pusher:subscription_succeeded", (t2) => {
        e3(Object.keys(t2.members).map((e4) => t2.members[e4]));
      }), this;
    }
    joining(e3) {
      return this.on("pusher:member_added", (t2) => {
        e3(t2.info);
      }), this;
    }
    whisper(e3, t2) {
      return this.pusher.channels.channels[this.name].trigger(`client-${e3}`, t2), this;
    }
    leaving(e3) {
      return this.on("pusher:member_removed", (t2) => {
        e3(t2.info);
      }), this;
    }
  };
  var f = class extends e {
    constructor(e3, n2, r2) {
      super(), this.events = {}, this.listeners = {}, this.name = n2, this.socket = e3, this.options = r2, this.eventFormatter = new t(this.options.namespace), this.subscribe();
    }
    subscribe() {
      this.socket.emit("subscribe", {
        channel: this.name,
        auth: this.options.auth || {}
      });
    }
    unsubscribe() {
      this.unbind(), this.socket.emit("unsubscribe", {
        channel: this.name,
        auth: this.options.auth || {}
      });
    }
    listen(e3, t2) {
      return this.on(this.eventFormatter.format(e3), t2), this;
    }
    stopListening(e3, t2) {
      return this.unbindEvent(this.eventFormatter.format(e3), t2), this;
    }
    subscribed(e3) {
      return this.on("connect", (t2) => {
        e3(t2);
      }), this;
    }
    error(e3) {
      return this;
    }
    on(e3, t2) {
      return this.listeners[e3] = this.listeners[e3] || [], this.events[e3] || (this.events[e3] = (t3, n2) => {
        this.name === t3 && this.listeners[e3] && this.listeners[e3].forEach((e4) => e4(n2));
      }, this.socket.on(e3, this.events[e3])), this.listeners[e3].push(t2), this;
    }
    unbind() {
      Object.keys(this.events).forEach((e3) => {
        this.unbindEvent(e3);
      });
    }
    unbindEvent(e3, t2) {
      this.listeners[e3] = this.listeners[e3] || [], t2 && (this.listeners[e3] = this.listeners[e3].filter((e4) => e4 !== t2)), t2 && this.listeners[e3].length !== 0 || (this.events[e3] && (this.socket.removeListener(e3, this.events[e3]), delete this.events[e3]), delete this.listeners[e3]);
    }
  };
  var p = class extends f {
    whisper(e3, t2) {
      return this.socket.emit("client event", {
        channel: this.name,
        event: `client-${e3}`,
        data: t2
      }), this;
    }
  };
  var m = class extends p {
    here(e3) {
      return this.on("presence:subscribed", (t2) => {
        e3(t2.map((e4) => e4.user_info));
      }), this;
    }
    joining(e3) {
      return this.on("presence:joining", (t2) => e3(t2.user_info)), this;
    }
    whisper(e3, t2) {
      return this.socket.emit("client event", {
        channel: this.name,
        event: `client-${e3}`,
        data: t2
      }), this;
    }
    leaving(e3) {
      return this.on("presence:leaving", (t2) => e3(t2.user_info)), this;
    }
  };
  var h = class extends e {
    subscribe() {
    }
    unsubscribe() {
    }
    listen(e3, t2) {
      return this;
    }
    listenToAll(e3) {
      return this;
    }
    stopListening(e3, t2) {
      return this;
    }
    subscribed(e3) {
      return this;
    }
    error(e3) {
      return this;
    }
    on(e3, t2) {
      return this;
    }
  };
  var g = class extends h {
    whisper(e3, t2) {
      return this;
    }
  };
  var _ = class extends h {
    whisper(e3, t2) {
      return this;
    }
  };
  var v = class extends g {
    here(e3) {
      return this;
    }
    joining(e3) {
      return this;
    }
    whisper(e3, t2) {
      return this;
    }
    leaving(e3) {
      return this;
    }
  };
  var y = class e2 {
    static {
      this._defaultOptions = {
        auth: { headers: {} },
        authEndpoint: "/broadcasting/auth",
        userAuthentication: {
          endpoint: "/broadcasting/user-auth",
          headers: {}
        },
        csrfToken: null,
        bearerToken: null,
        host: null,
        key: null,
        namespace: "App.Events"
      };
    }
    constructor(e3) {
      this.setOptions(e3), this.connect();
    }
    setOptions(t2) {
      this.options = {
        ...e2._defaultOptions,
        ...t2,
        broadcaster: t2.broadcaster
      };
      let n2 = this.csrfToken();
      n2 && (this.options.auth.headers["X-CSRF-TOKEN"] = n2, this.options.userAuthentication.headers["X-CSRF-TOKEN"] = n2), n2 = this.options.bearerToken, n2 && (this.options.auth.headers.Authorization = "Bearer " + n2, this.options.userAuthentication.headers.Authorization = "Bearer " + n2);
    }
    csrfToken() {
      return typeof window < "u" && window.Laravel?.csrfToken ? window.Laravel.csrfToken : this.options.csrfToken ? this.options.csrfToken : typeof document < "u" && typeof document.querySelector == "function" ? document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? null : null;
    }
  };
  var b = "/.well-known/mercure";
  var x = 3e4;
  var S = 100;
  var C = "mercure";
  var w = "private-encrypted-";
  var T = "client-";
  var E = class extends y {
    constructor(...e3) {
      super(...e3), this.channels = /* @__PURE__ */ Object.create(null), this.eventSource = null, this.lastEventId = "", this.whisperEventSources = /* @__PURE__ */ new Map(), this.whisperLastEventIds = /* @__PURE__ */ new Map(), this.whisperListening = /* @__PURE__ */ new Set(), this.clientEventsEnabled = false, this.topicPrefix = "", this.whisperQueue = Promise.resolve(), this.id = I(), this.status = "disconnected", this.statusListeners = [], this.refreshing = false, this.refreshPending = false, this.authorizedChannels = /* @__PURE__ */ new Set(), this.subscribedNotified = /* @__PURE__ */ new Set(), this.seededPresenceChannels = /* @__PURE__ */ new Set(), this.pendingSubscriptionEvents = /* @__PURE__ */ new Map(), this.channelJwks = /* @__PURE__ */ new Map(), this.importedKeys = /* @__PURE__ */ new Map(), this.dispatchQueue = Promise.resolve(), this.epoch = 0, this.reconnectDelay = 1e3, this.reconnectTimer = null, this.tokenTtl = null, this.lastAuthAt = 0, this.tokenRefreshTimer = null;
    }
    connect() {
      let e3 = typeof window < "u";
      if (this.options.host == null && !e3) throw Error('Mercure connector: the "host" option (the hub URL) is required outside a browser.');
      try {
        this.url = new URL(this.options.host ?? b, e3 ? window.location.origin : void 0);
      } catch {
        throw Error(`Mercure connector: the "host" option ("${String(this.options.host)}") is not a valid hub URL.`);
      }
    }
    eventSourceClass() {
      let e3 = this.options.eventSource ?? globalThis.EventSource;
      if (e3 === void 0) throw Error('Mercure connector: EventSource is not available globally; pass an implementation via the "eventSource" option.');
      return e3;
    }
    subscriptionsPath() {
      return `${this.url.pathname.replace(/\/$/, "")}/subscriptions`;
    }
    channelTopic(e3) {
      return this.topicPrefix === "" ? e3 : `${this.topicPrefix}channel/${F(e3)}`;
    }
    whisperTopic(e3) {
      return `${this.topicPrefix}whisper/${F(e3)}`;
    }
    channelNameFromTopic(e3) {
      if (this.topicPrefix === "") return e3;
      let t2 = `${this.topicPrefix}channel/`;
      if (!e3.startsWith(t2)) return null;
      try {
        return decodeURIComponent(e3.slice(t2.length));
      } catch {
        return null;
      }
    }
    listen(e3, t2, n2) {
      return this.channel(e3).listen(t2, n2);
    }
    channel(e3) {
      return this.channels[e3] || (this.channels[e3] = new r(e3, this.options, this), this.refresh()), this.channels[e3];
    }
    privateChannel(e3) {
      let t2 = "private-" + e3;
      return this.channels[t2] || (this.channels[t2] = new i(t2, this.options, this), this.refresh()), this.channels[t2];
    }
    encryptedPrivateChannel(e3) {
      let t2 = w + e3;
      return this.channels[t2] || (this.channels[t2] = new a(t2, this.options, this), this.refresh()), this.channels[t2];
    }
    presenceChannel(e3) {
      let t2 = "presence-" + e3;
      return this.channels[t2] || (this.channels[t2] = new o(t2, this.options, this), this.refresh()), this.channels[t2];
    }
    leave(e3) {
      [
        "",
        "private-",
        w,
        "presence-"
      ].forEach((t2) => this.leaveChannel(t2 + e3));
    }
    leaveChannel(e3) {
      this.channels[e3] && (delete this.channels[e3], this.forgetChannelState(e3), this.refresh());
    }
    socketId() {
      return this.id;
    }
    whisper(e3, t2, n2) {
      let r2 = this.channels[e3];
      r2 && (this.clientEventsEnabled ? this.whisperQueue = this.whisperQueue.then(() => this.publishWhisper(e3, T + t2, n2).catch((t3) => {
        this.channels[e3]?.notifyError(t3);
      })) : r2.notifyError(/* @__PURE__ */ Error(`whisper("${t2}"): the server did not grant client events; enable the "client_events" option of the Mercure broadcasting connection.`)));
    }
    listenForWhispers(e3) {
      !this.whisperListening.has(e3) && this.channels[e3] && (this.whisperListening.add(e3), this.syncWhisperEventSources());
    }
    async publishWhisper(e3, t2, n2) {
      let r2;
      r2 = e3.startsWith(w) ? JSON.stringify({
        channels: [e3],
        data: await this.encrypt(e3, JSON.stringify({
          event: t2,
          payload: n2,
          socket: this.id
        }))
      }) : JSON.stringify({
        channels: [e3],
        event: t2,
        payload: n2,
        socket: this.id
      });
      let i2 = () => fetch(`${this.url.origin}${this.url.pathname}`, {
        method: "POST",
        credentials: "include",
        body: new URLSearchParams({
          topic: this.whisperTopic(e3),
          data: r2,
          private: "on"
        })
      }), a2 = await i2();
      if (a2.status === 401 && this.channels[e3]) {
        let t3 = await this.authenticate(Object.keys(this.channels));
        t3.ok && (t3.denied.length > 0 && (this.evict(t3.denied, /* @__PURE__ */ Error(`The broadcasting auth request denied access to [${t3.denied.join(", ")}].`)), this.refresh()), this.channels[e3] && (a2 = await i2()));
      }
      if (!a2.ok) throw Error(`The whisper publish on "${e3}" failed with HTTP ${a2.status}.`);
    }
    connectionStatus() {
      return this.status;
    }
    onConnectionChange(e3) {
      return this.statusListeners.push(e3), () => {
        this.statusListeners = this.statusListeners.filter((t2) => t2 !== e3);
      };
    }
    disconnect() {
      this.epoch++, this.channels = /* @__PURE__ */ Object.create(null), this.authorizedChannels.clear(), this.subscribedNotified.clear(), this.seededPresenceChannels.clear(), this.pendingSubscriptionEvents.clear(), this.channelJwks.clear(), this.importedKeys.clear(), this.lastEventId = "", this.whisperLastEventIds.clear(), this.whisperListening.clear(), this.clientEventsEnabled = false, this.topicPrefix = "", this.reconnectTimer &&= (clearTimeout(this.reconnectTimer), null), this.reconnectDelay = 1e3, this.tokenRefreshTimer &&= (clearTimeout(this.tokenRefreshTimer), null), this.eventSource?.close(), this.eventSource = null, this.closeWhisperEventSources(), this.setStatus("disconnected");
    }
    setStatus(e3) {
      this.status = e3, this.statusListeners.forEach((t2) => t2(e3));
    }
    forgetChannelState(e3) {
      this.authorizedChannels.delete(e3), this.subscribedNotified.delete(e3), this.seededPresenceChannels.delete(e3), this.pendingSubscriptionEvents.delete(e3), this.channelJwks.delete(e3), this.importedKeys.delete(e3), this.whisperEventSources.get(e3)?.close(), this.whisperEventSources.delete(e3), this.whisperLastEventIds.delete(e3), this.whisperListening.delete(e3);
    }
    evict(e3, t2) {
      e3.forEach((e4) => {
        let n2 = this.channels[e4];
        n2 && (delete this.channels[e4], this.forgetChannelState(e4), n2.notifyError(t2));
      });
    }
    notifyAllError(e3) {
      Object.values(this.channels).forEach((t2) => t2.notifyError(e3));
    }
    guardedChannelNames() {
      return Object.keys(this.channels).filter((e3) => e3.startsWith("private-") || e3.startsWith("presence-"));
    }
    refresh() {
      this.runExclusive(() => this.doRefresh());
    }
    runExclusive(e3) {
      this.refreshing ? this.refreshPending = true : (this.refreshing = true, e3().catch((e4) => {
        this.notifyAllError(e4), Object.keys(this.channels).length > 0 && (this.setStatus("reconnecting"), this.scheduleReconnect());
      }).finally(() => {
        this.refreshing = false, this.refreshPending && (this.refreshPending = false, this.refresh());
      }));
    }
    scheduleTokenRefresh() {
      if (this.tokenTtl === null) return;
      this.tokenRefreshTimer && clearTimeout(this.tokenRefreshTimer);
      let e3 = this.epoch;
      this.tokenRefreshTimer = setTimeout(() => {
        this.tokenRefreshTimer = null, e3 === this.epoch && Object.keys(this.channels).length !== 0 && (this.tokenTtl !== null && Date.now() - this.lastAuthAt >= this.tokenTtl * 1e3 ? this.refresh() : this.runExclusive(async () => {
          let t2 = await this.authenticate(Object.keys(this.channels));
          e3 === this.epoch && (t2.ok ? (t2.denied.length > 0 && (this.evict(t2.denied, /* @__PURE__ */ Error(`The broadcasting auth request denied access to [${t2.denied.join(", ")}].`)), this.refresh()), this.scheduleTokenRefresh()) : (this.notifyAllError(t2.error), this.scheduleReconnect()));
        }));
      }, Math.max(this.tokenTtl * 0.8 * 1e3, 5e3));
    }
    scheduleReconnect() {
      if (this.reconnectTimer) return;
      let e3 = this.epoch;
      this.reconnectTimer = setTimeout(() => {
        this.reconnectTimer = null, e3 === this.epoch && Object.keys(this.channels).length !== 0 && this.refresh();
      }, this.reconnectDelay), this.reconnectDelay = Math.min(this.reconnectDelay * 2, x);
    }
    async doRefresh() {
      let e3 = this.epoch;
      if (await Promise.resolve(), e3 !== this.epoch) return;
      this.refreshPending = false;
      let t2 = Object.keys(this.channels);
      if (t2.length === 0) {
        this.eventSource?.close(), this.eventSource = null, this.closeWhisperEventSources(), this.authorizedChannels.clear(), this.setStatus("disconnected");
        return;
      }
      t2.length > S && (this.evict(t2.slice(S), /* @__PURE__ */ Error(`Mercure broadcasting authorizes at most ${S} channels per connection.`)), t2 = t2.slice(0, S)), this.eventSource || this.setStatus("connecting");
      let n2 = await this.authenticate(t2);
      if (e3 !== this.epoch) return;
      if (!n2.ok) {
        let e4 = t2.filter((e5) => !this.authorizedChannels.has(e5) && this.channels[e5]);
        if (n2.rejected && e4.length > 0) {
          this.evict(e4, /* @__PURE__ */ Error(`The broadcasting auth request was denied (HTTP ${String(n2.status)}) after joining [${e4.join(", ")}].`)), this.refreshPending = true;
          return;
        }
        this.notifyAllError(n2.error), this.setStatus("reconnecting"), this.scheduleReconnect();
        return;
      }
      if (this.reconnectTimer &&= (clearTimeout(this.reconnectTimer), null), n2.denied.length > 0 && (this.evict(n2.denied, /* @__PURE__ */ Error(`The broadcasting auth request denied access to [${n2.denied.join(", ")}].`)), t2 = t2.filter((e4) => this.channels[e4]), t2.length === 0)) {
        this.eventSource?.close(), this.eventSource = null, this.closeWhisperEventSources(), this.authorizedChannels.clear(), this.setStatus("disconnected");
        return;
      }
      this.authorizedChannels = new Set(t2), this.scheduleTokenRefresh(), t2.filter((e4) => this.channels[e4] instanceof o && !this.seededPresenceChannels.has(e4) && !this.pendingSubscriptionEvents.has(e4)).forEach((e4) => this.pendingSubscriptionEvents.set(e4, []));
      let r2 = this.buildSubscribeUrl(t2.map((e4) => this.channelTopic(e4)), t2.filter((e4) => this.channels[e4] instanceof o).map((e4) => this.subscriptionEventsPattern(e4)), this.lastEventId), i2 = this.eventSourceClass();
      this.eventSource?.close();
      let a2 = new i2(r2.toString(), { withCredentials: true });
      this.eventSource = a2, a2.onopen = () => {
        this.eventSource === a2 && (this.reconnectDelay = 1e3, this.setStatus("connected"), t2.forEach((e4) => {
          let t3 = this.channels[e4];
          t3 && !this.subscribedNotified.has(e4) && (this.subscribedNotified.add(e4), t3.notifySubscribed());
        }), this.seedPresenceChannels(e3));
      }, a2.onerror = (e4) => {
        this.eventSource === a2 && (this.notifyAllError(e4), this.setStatus("reconnecting"), a2.readyState === i2.CLOSED && (this.subscribedNotified.clear(), this.seededPresenceChannels.clear(), this.pendingSubscriptionEvents.clear(), this.scheduleReconnect()));
      }, a2.onmessage = (e4) => {
        this.eventSource === a2 && this.handleMessage(e4);
      }, a2.addEventListener(C, (e4) => {
        this.eventSource === a2 && this.handleSubscriptionEvent(e4);
      }), this.syncWhisperEventSources();
    }
    buildSubscribeUrl(e3, t2, n2) {
      let r2 = new URL(this.url);
      return r2.search = "", e3.forEach((e4) => r2.searchParams.append("match", e4)), t2.forEach((e4) => r2.searchParams.append("match_urlpattern", e4)), n2 && r2.searchParams.set("last_event_id", n2), r2;
    }
    closeWhisperEventSources() {
      this.whisperEventSources.forEach((e3) => e3.close()), this.whisperEventSources.clear();
    }
    syncWhisperEventSources() {
      let e3 = this.eventSourceClass().CLOSED, t2 = new Set(this.clientEventsEnabled ? this.guardedChannelNames().filter((e4) => this.authorizedChannels.has(e4) && this.whisperListening.has(e4)) : []);
      this.whisperEventSources.forEach((n2, r2) => {
        t2.has(r2) && n2.readyState !== e3 || (n2.close(), this.whisperEventSources.delete(r2));
      }), this.clientEventsEnabled ? t2.forEach((e4) => {
        this.whisperEventSources.has(e4) || this.openWhisperEventSource(e4);
      }) : this.whisperLastEventIds.clear();
    }
    openWhisperEventSource(e3) {
      let t2 = this.eventSourceClass(), n2 = new t2(this.buildSubscribeUrl([this.whisperTopic(e3)], [], this.whisperLastEventIds.get(e3) ?? "").toString(), { withCredentials: true });
      this.whisperEventSources.set(e3, n2), n2.onmessage = (t3) => {
        this.whisperEventSources.get(e3) === n2 && (this.whisperLastEventIds.set(e3, t3.lastEventId), this.handleWhisperMessage(t3, e3));
      }, n2.onerror = () => {
        this.whisperEventSources.get(e3) === n2 && n2.readyState === t2.CLOSED && this.scheduleReconnect();
      };
    }
    async seedPresenceChannels(e3) {
      await Promise.all([...this.pendingSubscriptionEvents.keys()].map(async (t2) => {
        let n2 = this.channels[t2];
        if (!(n2 instanceof o)) this.pendingSubscriptionEvents.delete(t2);
        else try {
          let r2 = await this.fetchPresenceSnapshot(t2);
          if (e3 !== this.epoch) return;
          this.seededPresenceChannels.add(t2), n2.setInitialMembers(r2), (this.pendingSubscriptionEvents.get(t2) ?? []).forEach(([e4, t3, r3]) => n2.applySubscriptionEvent(e4, t3, r3));
        } catch (e4) {
          n2.notifyError(e4);
        } finally {
          this.pendingSubscriptionEvents.delete(t2);
        }
      }));
    }
    handleMessage(e3) {
      this.lastEventId = e3.lastEventId;
      let t2 = O(e3.data);
      if (!t2 || !Array.isArray(t2.channels)) return;
      let n2 = t2.channels.filter((e4) => typeof e4 == "string");
      if (typeof t2.data == "string") {
        let e4 = t2.data;
        n2.filter((e5) => e5.startsWith(w) && D(this.channels, e5)).forEach((t3) => {
          this.dispatchQueue = this.dispatchQueue.then(() => this.dispatchEncrypted(t3, e4));
        });
      } else t2.socket && t2.socket === this.id || typeof t2.event == "string" && n2.forEach((e4) => {
        !e4.startsWith(w) && D(this.channels, e4) && this.channels[e4].dispatch(t2.event, t2.payload);
      });
    }
    handleWhisperMessage(e3, t2) {
      let n2 = O(e3.data);
      if (n2 && Array.isArray(n2.channels) && n2.channels.length === 1 && n2.channels[0] === t2 && D(this.channels, t2)) {
        if (typeof n2.data == "string") {
          let e4 = n2.data;
          t2.startsWith(w) && (this.dispatchQueue = this.dispatchQueue.then(() => this.dispatchEncrypted(t2, e4, true)));
        } else n2.socket && n2.socket === this.id || typeof n2.event == "string" && n2.event.startsWith(T) && !t2.startsWith(w) && this.channels[t2].dispatch(n2.event, n2.payload);
      }
    }
    async dispatchEncrypted(e3, t2, n2 = false) {
      let r2 = this.channels[e3];
      if (r2) try {
        let i2 = O(await this.decrypt(e3, t2));
        if (!i2 || typeof i2.event != "string") throw Error(`The decrypted update on "${e3}" carries no broadcast envelope.`);
        if (n2 && !i2.event.startsWith(T) || i2.socket && i2.socket === this.id) return;
        r2.dispatch(i2.event, i2.payload);
      } catch (e4) {
        r2.notifyError(e4);
      }
    }
    async decrypt(e3, t2) {
      let n2 = t2.split(".");
      if (n2.length !== 5 || n2[1] !== "") throw Error(`The update on "${e3}" is not a direct-encryption compact JWE.`);
      let [r2, , i2, a2, o2] = n2, s2 = O(j.decode(N(r2)));
      if (s2?.alg !== "dir" || s2?.enc !== "A256GCM") throw Error(`The update on "${e3}" uses an unsupported JWE algorithm.`);
      let c2 = N(a2), l2 = N(o2), u2 = new Uint8Array(c2.length + l2.length);
      u2.set(c2), u2.set(l2, c2.length);
      let d2 = await crypto.subtle.decrypt({
        name: "AES-GCM",
        iv: N(i2),
        additionalData: A.encode(r2),
        tagLength: 128
      }, await this.channelKey(e3), u2);
      return j.decode(d2);
    }
    async encrypt(e3, t2) {
      let n2 = M, r2 = crypto.getRandomValues(/* @__PURE__ */ new Uint8Array(12)), i2 = new Uint8Array(await crypto.subtle.encrypt({
        name: "AES-GCM",
        iv: r2,
        additionalData: A.encode(n2),
        tagLength: 128
      }, await this.channelKey(e3), A.encode(t2))), a2 = i2.slice(0, -16), o2 = i2.slice(-16);
      return [
        n2,
        "",
        P(r2),
        P(a2),
        P(o2)
      ].join(".");
    }
    channelKey(e3) {
      let t2 = this.channelJwks.get(e3);
      if (!t2 || typeof t2.k != "string") return Promise.reject(/* @__PURE__ */ Error(`No decryption key is known for "${e3}": the auth endpoint returned none. Is "encryption_key" configured server-side?`));
      let n2 = this.importedKeys.get(e3);
      if (n2 && n2.k === t2.k) return n2.key;
      let r2 = crypto.subtle.importKey("jwk", t2, { name: "AES-GCM" }, false, ["encrypt", "decrypt"]);
      return this.importedKeys.set(e3, {
        k: t2.k,
        key: r2
      }), r2;
    }
    handleSubscriptionEvent(e3) {
      this.lastEventId = e3.lastEventId;
      let t2 = O(e3.data);
      if (!t2 || typeof t2.match != "string" || typeof t2.subscriber != "string" || typeof t2.active != "boolean" || t2.match_type !== "exact" || t2.active && t2.payload == null) return;
      let n2 = this.channelNameFromTopic(t2.match);
      if (n2 === null) return;
      let r2 = this.pendingSubscriptionEvents.get(n2);
      if (r2) {
        r2.push([
          t2.subscriber,
          t2.active,
          t2.payload
        ]);
        return;
      }
      if (!D(this.channels, n2)) return;
      let i2 = this.channels[n2];
      i2 instanceof o && i2.applySubscriptionEvent(t2.subscriber, t2.active, t2.payload);
    }
    async fetchPresenceSnapshot(e3) {
      let t2 = await fetch(`${this.url.origin}${this.subscriptionsPath()}/exact/${F(this.channelTopic(e3))}`, { credentials: "include" });
      if (!t2.ok) throw Error(`The subscription snapshot request failed with HTTP ${t2.status}.`);
      return ((await t2.json()).subscriptions ?? []).filter((e4) => e4.active && e4.payload != null).map((e4) => [e4.subscriber, e4.payload]);
    }
    async authenticate(e3) {
      let t2, n2 = null;
      try {
        let r2 = await fetch(this.options.authEndpoint, {
          method: "POST",
          credentials: "include",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...this.options.auth.headers
          },
          body: JSON.stringify({ channel_names: e3 })
        });
        if (r2.ok) return this.lastAuthAt = Date.now(), {
          ok: true,
          rejected: false,
          status: r2.status,
          denied: this.readAuthResponse(await k(r2))
        };
        n2 = r2.status, t2 = /* @__PURE__ */ Error(`The broadcasting auth request to "${this.options.authEndpoint}" failed with HTTP ${r2.status}.`);
      } catch (e4) {
        t2 = /* @__PURE__ */ Error(`The broadcasting auth request to "${this.options.authEndpoint}" failed: ${e4 instanceof Error ? e4.message : String(e4)}.`);
      }
      return {
        ok: false,
        rejected: n2 !== null && n2 < 500,
        status: n2,
        denied: [],
        error: t2
      };
    }
    readAuthResponse(e3) {
      let t2 = e3 ?? {}, n2 = Number(t2.expires_in);
      this.tokenTtl = Number.isFinite(n2) && n2 > 0 ? n2 : null, this.topicPrefix = typeof t2.topic_prefix == "string" ? t2.topic_prefix : "", this.clientEventsEnabled = t2.client_events === true, this.channelJwks.clear();
      let r2 = [];
      return Array.isArray(t2.channel_names) && t2.channel_names.forEach((e4) => {
        let t3 = e4 ?? {};
        typeof t3.name == "string" && (t3.denied === true ? r2.push(t3.name) : t3.jwk !== null && typeof t3.jwk == "object" && this.channelJwks.set(t3.name, t3.jwk));
      }), r2;
    }
    subscriptionEventsPattern(e3) {
      return `${this.subscriptionsPath()}/:match_type/${F(this.channelTopic(e3))}/:subscriber`;
    }
  };
  function D(e3, t2) {
    return Object.prototype.hasOwnProperty.call(e3, t2);
  }
  function O(e3) {
    try {
      return JSON.parse(e3);
    } catch {
      return null;
    }
  }
  async function k(e3) {
    try {
      return await e3.json();
    } catch {
      return null;
    }
  }
  var A = new TextEncoder();
  var j = new TextDecoder();
  var M = P(A.encode(JSON.stringify({
    alg: "dir",
    enc: "A256GCM"
  })));
  function N(e3) {
    let t2 = e3.replace(/-/g, "+").replace(/_/g, "/") + "=".repeat((4 - e3.length % 4) % 4);
    return Uint8Array.from(atob(t2), (e4) => e4.charCodeAt(0));
  }
  function P(e3) {
    return btoa(String.fromCharCode(...e3)).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
  }
  function F(e3) {
    return encodeURIComponent(e3).replace(/[!'()*]/g, (e4) => "%" + e4.charCodeAt(0).toString(16).toUpperCase());
  }
  function I() {
    return typeof crypto < "u" && typeof crypto.randomUUID == "function" ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now().toString(36);
  }
  var L = class extends y {
    constructor(...e3) {
      super(...e3), this.channels = {};
    }
    connect() {
      if (this.options.client !== void 0) this.pusher = this.options.client;
      else if (this.options.Pusher) this.pusher = new this.options.Pusher(this.options.key, this.options);
      else if (typeof window < "u" && window.Pusher !== void 0) this.pusher = new window.Pusher(this.options.key, this.options);
      else throw Error("Pusher client not found. Should be globally available or passed via options.client");
    }
    signin() {
      this.pusher.signin();
    }
    listen(e3, t2, n2) {
      return this.channel(e3).listen(t2, n2);
    }
    channel(e3) {
      return this.channels[e3] || (this.channels[e3] = new c(this.pusher, e3, this.options)), this.channels[e3];
    }
    privateChannel(e3) {
      return this.channels["private-" + e3] || (this.channels["private-" + e3] = new l(this.pusher, "private-" + e3, this.options)), this.channels["private-" + e3];
    }
    encryptedPrivateChannel(e3) {
      return this.channels["private-encrypted-" + e3] || (this.channels["private-encrypted-" + e3] = new u(this.pusher, "private-encrypted-" + e3, this.options)), this.channels["private-encrypted-" + e3];
    }
    presenceChannel(e3) {
      return this.channels["presence-" + e3] || (this.channels["presence-" + e3] = new d(this.pusher, "presence-" + e3, this.options)), this.channels["presence-" + e3];
    }
    leave(e3) {
      [
        e3,
        "private-" + e3,
        "private-encrypted-" + e3,
        "presence-" + e3
      ].forEach((e4) => {
        this.leaveChannel(e4);
      });
    }
    leaveChannel(e3) {
      this.channels[e3] && (this.channels[e3].unsubscribe(), delete this.channels[e3]);
    }
    socketId() {
      return this.pusher.connection.socket_id;
    }
    connectionStatus() {
      let e3 = this.pusher.connection.state;
      switch (e3) {
        case "connected":
        case "connecting":
          return e3;
        case "failed":
        case "unavailable":
          return "failed";
        default:
          return "disconnected";
      }
    }
    onConnectionChange(e3) {
      let t2 = () => {
        e3(this.connectionStatus());
      }, n2 = [
        "state_change",
        "connected",
        "disconnected"
      ];
      return n2.forEach((e4) => {
        this.pusher.connection.bind(e4, t2);
      }), () => {
        n2.forEach((e4) => {
          this.pusher.connection.unbind(e4, t2);
        });
      };
    }
    disconnect() {
      this.pusher.disconnect();
    }
  };
  var R = class extends y {
    constructor(...e3) {
      super(...e3), this.channels = {};
    }
    connect() {
      let e3 = this.getSocketIO();
      this.socket = e3(this.options.host ?? void 0, this.options), this.socket.io.on("reconnect", () => {
        Object.values(this.channels).forEach((e4) => {
          e4.subscribe();
        });
      });
    }
    getSocketIO() {
      if (this.options.client !== void 0) return this.options.client;
      if (typeof window < "u" && window.io !== void 0) return window.io;
      throw Error("Socket.io client not found. Should be globally available or passed via options.client");
    }
    listen(e3, t2, n2) {
      return this.channel(e3).listen(t2, n2);
    }
    channel(e3) {
      return this.channels[e3] || (this.channels[e3] = new f(this.socket, e3, this.options)), this.channels[e3];
    }
    privateChannel(e3) {
      return this.channels["private-" + e3] || (this.channels["private-" + e3] = new p(this.socket, "private-" + e3, this.options)), this.channels["private-" + e3];
    }
    presenceChannel(e3) {
      return this.channels["presence-" + e3] || (this.channels["presence-" + e3] = new m(this.socket, "presence-" + e3, this.options)), this.channels["presence-" + e3];
    }
    leave(e3) {
      [
        e3,
        "private-" + e3,
        "presence-" + e3
      ].forEach((e4) => {
        this.leaveChannel(e4);
      });
    }
    leaveChannel(e3) {
      this.channels[e3] && (this.channels[e3].unsubscribe(), delete this.channels[e3]);
    }
    socketId() {
      return this.socket.id;
    }
    connectionStatus() {
      return this.socket.connected ? "connected" : this.socket.io._reconnecting ? "reconnecting" : this.socket.id === void 0 ? "connecting" : "disconnected";
    }
    onConnectionChange(e3) {
      let t2 = () => {
        e3(this.connectionStatus());
      }, n2 = [
        "connect",
        "disconnect",
        "connect_error",
        "reconnect_attempt",
        "reconnect",
        "reconnect_error",
        "reconnect_failed"
      ];
      return n2.forEach((e4) => {
        this.socket.on(e4, t2);
      }), () => {
        n2.forEach((e4) => {
          this.socket.off(e4, t2);
        });
      };
    }
    disconnect() {
      this.socket.disconnect();
    }
  };
  var z = class extends y {
    constructor(...e3) {
      super(...e3), this.channels = {};
    }
    connect() {
    }
    listen(e3, t2, n2) {
      return new h();
    }
    channel(e3) {
      return new h();
    }
    privateChannel(e3) {
      return new g();
    }
    encryptedPrivateChannel(e3) {
      return new _();
    }
    presenceChannel(e3) {
      return new v();
    }
    leave(e3) {
    }
    leaveChannel(e3) {
    }
    socketId() {
      return "fake-socket-id";
    }
    connectionStatus() {
      return "connected";
    }
    onConnectionChange(e3) {
      return () => {
      };
    }
    disconnect() {
    }
  };
  var B = class {
    constructor(e3) {
      this.options = e3, this.connect(), this.options.withoutInterceptors || this.registerInterceptors();
    }
    channel(e3) {
      return this.connector.channel(e3);
    }
    connect() {
      if (this.options.broadcaster === "reverb") this.connector = new L({
        ...this.options,
        cluster: ""
      });
      else if (this.options.broadcaster === "pusher") this.connector = new L(this.options);
      else if (this.options.broadcaster === "ably") this.connector = new L({
        ...this.options,
        cluster: "",
        broadcaster: "pusher"
      });
      else if (this.options.broadcaster === "socket.io") this.connector = new R(this.options);
      else if (this.options.broadcaster === "mercure") this.connector = new E(this.options);
      else if (this.options.broadcaster === "null") this.connector = new z(this.options);
      else if (typeof this.options.broadcaster == "function" && n(this.options.broadcaster)) this.connector = new this.options.broadcaster(this.options);
      else throw Error(`Broadcaster ${typeof this.options.broadcaster} ${String(this.options.broadcaster)} is not supported.`);
    }
    disconnect() {
      this.connector.disconnect();
    }
    join(e3) {
      return this.connector.presenceChannel(e3);
    }
    leave(e3) {
      this.connector.leave(e3);
    }
    leaveChannel(e3) {
      this.connector.leaveChannel(e3);
    }
    leaveAllChannels() {
      for (let e3 in this.connector.channels) this.leaveChannel(e3);
    }
    listen(e3, t2, n2) {
      return this.connector.listen(e3, t2, n2);
    }
    private(e3) {
      return this.connector.privateChannel(e3);
    }
    encryptedPrivate(e3) {
      if (this.connectorSupportsEncryptedPrivateChannels(this.connector)) return this.connector.encryptedPrivateChannel(e3);
      throw Error(`Broadcaster ${typeof this.options.broadcaster} ${String(this.options.broadcaster)} does not support encrypted private channels.`);
    }
    connectorSupportsEncryptedPrivateChannels(e3) {
      return e3 instanceof L || e3 instanceof E || e3 instanceof z;
    }
    socketId() {
      return this.connector.socketId();
    }
    connectionStatus() {
      return this.connector.connectionStatus();
    }
    registerInterceptors() {
      typeof Vue < "u" && Vue?.http && this.registerVueRequestInterceptor(), typeof axios == "function" && this.registerAxiosRequestInterceptor(), typeof jQuery == "function" && this.registerjQueryAjaxSetup(), typeof Turbo == "object" && this.registerTurboRequestInterceptor();
    }
    registerVueRequestInterceptor() {
      Vue.http.interceptors.push((e3, t2) => {
        this.socketId() && e3.headers.set("X-Socket-ID", this.socketId()), t2();
      });
    }
    registerAxiosRequestInterceptor() {
      axios.interceptors.request.use((e3) => (this.socketId() && (e3.headers["X-Socket-Id"] = this.socketId()), e3));
    }
    registerjQueryAjaxSetup() {
      jQuery.ajax !== void 0 && jQuery.ajaxPrefilter((e3, t2, n2) => {
        this.socketId() && n2.setRequestHeader("X-Socket-Id", this.socketId());
      });
    }
    registerTurboRequestInterceptor() {
      document.addEventListener("turbo:before-fetch-request", (e3) => {
        e3.detail.fetchOptions.headers["X-Socket-Id"] = this.socketId();
      });
    }
  };

  // main.js
  var import_pusher_js = __toESM(require_pusher());
  function connect({ key, token, linkId, onEvent, onSubscribed, onState, onError }) {
    window.Pusher = import_pusher_js.default;
    const echo = new B({
      broadcaster: "reverb",
      key,
      wsHost: location.hostname,
      wsPort: Number(location.port || 80),
      wssPort: Number(location.port || 443),
      forceTLS: location.protocol === "https:",
      enabledTransports: ["ws", "wss"],
      disableStats: true,
      authorizer: (channel) => ({ authorize: (socketId, callback) => {
        fetch("/api/lab6/broadcasting/auth", {
          method: "POST",
          cache: "no-store",
          headers: { "Content-Type": "application/json", Accept: "application/json", Authorization: "Bearer " + token },
          body: JSON.stringify({ socket_id: socketId, channel_name: channel.name })
        }).then(async (response) => {
          if (!response.ok) throw new Error("\u063A\u064A\u0631 \u0645\u0633\u0645\u0648\u062D \u0628\u0627\u0644\u0627\u0634\u062A\u0631\u0627\u0643 \u0641\u064A \u0642\u0646\u0627\u0629 \u0627\u0644\u0644\u064A\u0646\u0643.");
          return response.json();
        }).then((data) => callback(null, data)).catch((error) => {
          callback(error, null);
          onError(error);
        });
      } })
    });
    const connection = echo.connector.pusher.connection;
    connection.bind("state_change", (state) => onState(state.current));
    echo.private("links." + linkId).listen(".LinkCountUpdated", onEvent).subscribed(onSubscribed).error(onError);
    return { disconnect() {
      echo.disconnect();
    } };
  }
  return __toCommonJS(main_exports);
})();
/*! Bundled license information:

pusher-js/dist/web/pusher.js:
  (*!
   * Pusher JavaScript Library v8.6.0
   * https://pusher.com/
   *
   * Copyright 2020, Pusher
   * Released under the MIT licence.
   *)
*/
