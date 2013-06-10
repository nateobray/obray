(function (d) { d.fn.htmlClean = function (y) { return this.each(function () { var z = d(this); if (this.value) { this.value = d.htmlClean(this.value, y) } else { this.innerHTML = d.htmlClean(this.innerHTML, y) } }) }; d.htmlClean = function (D, z) { z = d.extend({}, d.htmlClean.defaults, z); var E = /<(\/)?(\w+:)?([\w]+)([^>]*)>/gi; var J = /(\w+)=(".*?"|'.*?'|[^\s>]*)/gi; var R; var L = new r(); var C = [L]; var F = L; var K = false; if (z.bodyOnly) { if (R = /<body[^>]*>((\n|.)*)<\/body>/i.exec(D)) { D = R[1] } } D = D.concat("<xxx>"); var O; while (R = E.exec(D)) { var T = new j(R[3], R[1], R[4], z); var G = D.substring(O, R.index); if (G.length > 0) { var B = F.children[F.children.length - 1]; if (F.children.length > 0 && n(B = F.children[F.children.length - 1])) { F.children[F.children.length - 1] = B.concat(G) } else { F.children.push(G) } } O = E.lastIndex; if (T.isClosing) { if (k(C, [T.name])) { C.pop(); F = C[C.length - 1] } } else { var y = new r(T); var A; while (A = J.exec(T.rawAttributes)) { if (A[1].toLowerCase() == "style" && z.replaceStyles) { var H = !T.isInline; for (var N = 0; N < z.replaceStyles.length; N++) { if (z.replaceStyles[N][0].test(A[2])) { if (!H) { T.render = false; H = true } F.children.push(y); C.push(y); F = y; T = new j(z.replaceStyles[N][1], "", "", z); y = new r(T) } } } if (T.allowedAttributes != null && (T.allowedAttributes.length == 0 || d.inArray(A[1], T.allowedAttributes) > -1)) { y.attributes.push(new a(A[1], A[2])) } } d.each(T.requiredAttributes, function () { var U = this.toString(); if (!y.hasAttribute(U)) { y.attributes.push(new a(U, "")) } }); for (var M = 0; M < z.replace.length; M++) { for (var Q = 0; Q < z.replace[M][0].length; Q++) { var P = typeof (z.replace[M][0][Q]) == "string"; if ((P && z.replace[M][0][Q] == T.name) || (!P && z.replace[M][0][Q].test(R))) { T.render = false; F.children.push(y); C.push(y); F = y; T = new j(z.replace[M][1], R[1], R[4], z); y = new r(T); y.attributes = F.attributes; M = z.replace.length; break } } } var I = true; if (!F.isRoot) { if (F.tag.isInline && !T.isInline) { I = false } else { if (F.tag.disallowNest && T.disallowNest && !T.requiredParent) { I = false } else { if (T.requiredParent) { if (I = k(C, T.requiredParent)) { F = C[C.length - 1] } } } } } if (I) { F.children.push(y); if (T.toProtect) { while (tagMatch2 = E.exec(D)) { var S = new j(tagMatch2[3], tagMatch2[1], tagMatch2[4], z); if (S.isClosing && S.name == T.name) { y.children.push(RegExp.leftContext.substring(O)); O = E.lastIndex; break } } } else { if (!T.isSelfClosing && !T.isNonClosing) { C.push(y); F = y } } } } } return d.htmlClean.trim(w(L, z).join("")) }; d.htmlClean.defaults = { bodyOnly: true, allowedTags: [], removeTags: ["basefont", "center", "dir", "font", "frame", "frameset", "iframe", "isindex", "menu", "noframes", "s", "strike", "u"], allowedAttributes: [], removeAttrs: [], allowedClasses: [], format: false, formatIndent: 0, replace: [[["b", "big"], "strong"], [["i"], "em"]], replaceStyles: [[/font-weight:\s*bold/i, "strong"], [/font-style:\s*italic/i, "em"], [/vertical-align:\s*super/i, "sup"], [/vertical-align:\s*sub/i, "sub"]] }; function h(B, A, z, y) { if (!B.tag.isInline && z.length > 0) { z.push("\n"); for (i = 0; i < y; i++) { z.push("\t") } } } function w(C, I) { var z = [], F = C.attributes.length == 0, A; var D = this.name.concat(C.tag.rawAttributes == undefined ? "" : C.tag.rawAttributes); var H = C.tag.render && (I.allowedTags.length == 0 || d.inArray(C.tag.name, I.allowedTags) > -1) && (I.removeTags.length == 0 || d.inArray(C.tag.name, I.removeTags) == -1); if (!C.isRoot && H) { z.push("<"); z.push(C.tag.name); d.each(C.attributes, function () { if (d.inArray(this.name, I.removeAttrs) == -1) { var J = RegExp(/^(['"]?)(.*?)['"]?$/).exec(this.value); var K = J[2]; var L = J[1] || "'"; if (this.name == "class") { K = d.grep(K.split(" "), function (M) { return d.grep(I.allowedClasses, function (N) { return N[0] == M && (N.length == 1 || d.inArray(C.tag.name, N[1]) > -1) }).length > 0 }).join(" "); L = "'" } if (K != null && (K.length > 0 || d.inArray(this.name, C.tag.requiredAttributes) > -1)) { z.push(" "); z.push(this.name); z.push("="); z.push(L); z.push(K); z.push(L) } } }) } if (C.tag.isSelfClosing) { if (H) { z.push(" />") } F = false } else { if (C.tag.isNonClosing) { F = false } else { if (!C.isRoot && H) { z.push(">") } var A = I.formatIndent++; if (C.tag.toProtect) { var E = d.htmlClean.trim(C.children.join("")).replace(/<br>/ig, "\n"); z.push(E); F = E.length == 0 } else { var E = []; for (var B = 0; B < C.children.length; B++) { var y = C.children[B]; var G = d.htmlClean.trim(c(n(y) ? y : y.childrenToString())); if (q(y)) { if (B > 0 && G.length > 0 && (v(y) || e(C.children[B - 1]))) { E.push(" ") } } if (n(y)) { if (G.length > 0) { E.push(G) } } else { if (B != C.children.length - 1 || y.tag.name != "br") { if (I.format) { h(y, I, E, A) } E = E.concat(w(y, I)) } } } I.formatIndent--; if (E.length > 0) { if (I.format && E[0] != "\n") { h(C, I, z, A) } z = z.concat(E); F = false } } if (!C.isRoot && H) { if (I.format) { h(C, I, z, A - 1) } z.push("</"); z.push(C.tag.name); z.push(">") } } } if (!C.tag.allowEmpty && F) { return [] } return z } function k(y, A, z) { z = z || 1; if (d.inArray(y[y.length - z].tag.name, A) > -1) { return true } else { if (y.length - (z + 1) > 0 && k(y, A, z + 1)) { y.pop(); return true } } return false } function r(y) { if (y) { this.tag = y; this.isRoot = false } else { this.tag = new j("root"); this.isRoot = true } this.attributes = []; this.children = []; this.hasAttribute = function (z) { for (var A = 0; A < this.attributes.length; A++) { if (this.attributes[A].name == z) { return true } } return false }; this.childrenToString = function () { return this.children.join("") }; return this } function a(y, z) { this.name = y; this.value = z; return this } function j(A, E, D, z) { this.name = A.toLowerCase(); this.isSelfClosing = d.inArray(this.name, l) > -1; this.isNonClosing = d.inArray(this.name, s) > -1; this.isClosing = (E != undefined && E.length > 0); this.isInline = d.inArray(this.name, t) > -1; this.disallowNest = d.inArray(this.name, p) > -1; this.requiredParent = f[d.inArray(this.name, f) + 1]; this.allowEmpty = d.inArray(this.name, b) > -1; this.toProtect = d.inArray(this.name, g) > -1; this.rawAttributes = D; this.requiredAttributes = x[d.inArray(this.name, x) + 1]; if (z) { if (!z.tagAttributesCache) { z.tagAttributesCache = [] } if (d.inArray(this.name, z.tagAttributesCache) == -1) { var y = m[d.inArray(this.name, m) + 1].slice(0); for (var C = 0; C < z.allowedAttributes.length; C++) { var B = z.allowedAttributes[C][0]; if ((z.allowedAttributes[C].length == 1 || d.inArray(this.name, z.allowedAttributes[C][1]) > -1) && d.inArray(B, y) == -1) { y.push(B) } } z.tagAttributesCache.push(this.name); z.tagAttributesCache.push(y) } this.allowedAttributes = z.tagAttributesCache[d.inArray(this.name, z.tagAttributesCache) + 1] } this.render = true; return this } function v(y) { while (o(y) && y.children.length > 0) { y = y.children[0] } return n(y) && y.length > 0 && d.htmlClean.isWhitespace(y.charAt(0)) } function e(y) { while (o(y) && y.children.length > 0) { y = y.children[y.children.length - 1] } return n(y) && y.length > 0 && d.htmlClean.isWhitespace(y.charAt(y.length - 1)) } function n(y) { return y.constructor == String } function q(y) { return n(y) || y.tag.isInline } function o(y) { return y.constructor == r } function c(y) { return y.replace(/&nbsp;|\n/g, " ").replace(/\s\s+/g, " ") } d.htmlClean.trim = function (y) { return d.htmlClean.trimStart(d.htmlClean.trimEnd(y)) }; d.htmlClean.trimStart = function (y) { return y.substring(d.htmlClean.trimStartIndex(y)) }; d.htmlClean.trimStartIndex = function (y) { for (var z = 0; z < y.length - 1 && d.htmlClean.isWhitespace(y.charAt(z)); z++) { } return z }; d.htmlClean.trimEnd = function (y) { return y.substring(0, d.htmlClean.trimEndIndex(y)) }; d.htmlClean.trimEndIndex = function (z) { for (var y = z.length - 1; y >= 0 && d.htmlClean.isWhitespace(z.charAt(y)); y--) { } return y + 1 }; d.htmlClean.isWhitespace = function (y) { return d.inArray(y, u) != -1 }; var t = ["a", "abbr", "acronym", "address", "b", "big", "br", "button", "caption", "cite", "code", "del", "em", "font", "hr", "i", "input", "img", "ins", "label", "legend", "map", "q", "s", "samp", "select", "small", "span", "strike", "strong", "sub", "sup", "tt", "u", "var"]; var p = ["h1", "h2", "h3", "h4", "h5", "h6", "p", "th", "td"]; var b = ["th", "td"]; var f = [null, "li", ["ul", "ol"], "dt", ["dl"], "dd", ["dl"], "td", ["tr"], "th", ["tr"], "tr", ["table", "thead", "tbody", "tfoot"], "thead", ["table"], "tbody", ["table"], "tfoot", ["table"]]; var g = ["script", "style", "pre", "code"]; var l = ["br", "hr", "img", "link", "meta"]; var s = ["!doctype", "?xml"]; var m = [["class"], "?xml", [], "!doctype", [], "a", ["accesskey", "class", "href", "name", "title", "rel", "rev", "type", "tabindex"], "abbr", ["class", "title"], "acronym", ["class", "title"], "blockquote", ["cite", "class"], "button", ["class", "disabled", "name", "type", "value"], "del", ["cite", "class", "datetime"], "form", ["accept", "action", "class", "enctype", "method", "name"], "input", ["accept", "accesskey", "alt", "checked", "class", "disabled", "ismap", "maxlength", "name", "size", "readonly", "src", "tabindex", "type", "usemap", "value"], "img", ["alt", "class", "height", "src", "width"], "ins", ["cite", "class", "datetime"], "label", ["accesskey", "class", "for"], "legend", ["accesskey", "class"], "link", ["href", "rel", "type"], "meta", ["content", "http-equiv", "name", "scheme", "charset"], "map", ["name"], "optgroup", ["class", "disabled", "label"], "option", ["class", "disabled", "label", "selected", "value"], "q", ["class", "cite"], "script", ["src", "type"], "select", ["class", "disabled", "multiple", "name", "size", "tabindex"], "style", ["type"], "table", ["class", "summary"], "th", ["class", "colspan", "rowspan"], "td", ["class", "colspan", "rowspan"], "textarea", ["accesskey", "class", "cols", "disabled", "name", "readonly", "rows", "tabindex"]]; var x = [[], "img", ["alt"]]; var u = [" ", " ", "\t", "\n", "\r", "\f"] })(jQuery);