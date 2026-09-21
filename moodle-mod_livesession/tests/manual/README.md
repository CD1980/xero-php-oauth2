# Manual browser smoke test

`browser-smoke.js` drives a real browser against a live Moodle and checks that the
Zoom Meeting SDK actually loads and registers on the page. It exists because the
failure it guards against cannot be caught by PHPUnit: Moodle puts RequireJS on every
page, and a UMD bundle that sees `define.amd` registers itself as an anonymous AMD
module instead of setting a global. The script loads perfectly and the SDK never
appears. Only a real page can show that.

## Running it

```bash
npm install playwright          # or use a global install
node browser-smoke.js
```

Edit the URL, username and password at the top for your site. Point the plugin's
**Meeting SDK URL** setting at a local stand-in first if you do not want to pull
Zoom's real 3.9 MB bundle; any file shaped like a UMD module will reproduce the
condition:

```js
(function (root, factory) {
    if (typeof exports === 'object' && typeof module !== 'undefined') {
        module.exports = factory();
    } else if (typeof define === 'function' && define.amd) {
        define([], factory);          // the branch that swallows the SDK
    } else {
        root.ZoomMtgEmbedded = factory();
    }
}(globalThis, function () {
    return {createClient: () => ({init(){}, join(){}, on(){}})};
}));
```

## What a pass looks like

```
STATUS TEXT: You are in the meeting. Your attendance is being recorded.
GLOBALS AFTER LOAD: {"ZoomMtgEmbedded":"object", ... ,"defineAmd":"object"}
```

`defineAmd` must still be `object` afterwards: the fix hides `define.amd` only for the
duration of the script load and must put it back, or the rest of Moodle's JavaScript
breaks.
