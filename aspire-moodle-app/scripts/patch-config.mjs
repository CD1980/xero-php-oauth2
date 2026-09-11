#!/usr/bin/env node
/**
 * Applies branding/brand.json to a checked-out Moodle app tree.
 *
 * Rewrites only the specific keys/attributes we own, leaving everything else in
 * moodle.config.json and config.xml untouched so upstream changes to those files
 * keep merging cleanly.
 *
 * Usage: node scripts/patch-config.mjs <path-to-app-tree>
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const appDir = process.argv[2];
if (!appDir) {
    console.error('usage: patch-config.mjs <path-to-app-tree>');
    process.exit(1);
}

const here = dirname(fileURLToPath(import.meta.url));
const brand = JSON.parse(readFileSync(join(here, '..', 'branding', 'brand.json'), 'utf8'));

if (brand.site.url.includes('moodle.aeat.com.au')) {
    console.warn('WARNING: brand.json still holds the placeholder site URL. '
        + 'Set site.url to the real Moodle site before shipping a build.');
}

/* ---------- moodle.config.json ---------- */

const configPath = join(appDir, 'moodle.config.json');
const config = JSON.parse(readFileSync(configPath, 'utf8'));

Object.assign(config, {
    app_id: brand.app.appId,
    appname: brand.app.name,
    versionname: brand.app.versionName,
    versioncode: brand.app.versionCode,
    customurlscheme: brand.app.customUrlScheme,
    privacypolicy: brand.site.privacyPolicy,
    notificoncolor: brand.colors.notificationIcon,
    appstores: { android: brand.stores.android, ios: brand.stores.ios },

    // Pin the app to the Aspire site: pre-fill the URL, hide the site finder,
    // and refuse any other host. `sites` entries are matched as regexes by the
    // app, so the URL has to be escaped.
    sites: [{
        name: brand.site.name,
        url: brand.site.url,
        alias: brand.site.name,
    }],
    onlyallowlistedsites: brand.site.lockToThisSiteOnly,
    multisitesdisplay: brand.site.lockToThisSiteOnly ? '' : 'list',

    // Show the Aspire logo on the login screen and in the header.
    forceLoginLogo: true,
    showTopLogo: 'online',
});

writeFileSync(configPath, JSON.stringify(config, null, 4) + '\n');
console.log(`patched ${configPath}`);

/* ---------- package.json ---------- */

const pkgPath = join(appDir, 'package.json');
const pkg = JSON.parse(readFileSync(pkgPath, 'utf8'));

pkg.name = brand.app.appId.split('.').pop();
pkg.version = brand.app.versionName;
pkg.description = brand.app.description;

// The deep-link scheme is a Cordova plugin variable, not a config.xml
// preference — this is the only place that actually sets it.
const scheme = pkg.cordova?.plugins?.['cordova-plugin-customurlscheme'];
if (!scheme) throw new Error('package.json: cordova-plugin-customurlscheme not found');
scheme.URL_SCHEME = brand.app.customUrlScheme;

writeFileSync(pkgPath, JSON.stringify(pkg, null, 4) + '\n');
console.log(`patched ${pkgPath}`);

/* ---------- config.xml ---------- */

const xmlPath = join(appDir, 'config.xml');
let xml = readFileSync(xmlPath, 'utf8');
const before = xml;

const replaceTag = (tag, value) => {
    const re = new RegExp(`(<${tag}(?:\\s[^>]*)?>)[\\s\\S]*?(</${tag}>)`);
    if (!re.test(xml)) throw new Error(`config.xml: no <${tag}> element found`);
    xml = xml.replace(re, `$1${value}$2`);
};

const replaceAttr = (attr, value) => {
    const re = new RegExp(`(<widget[^>]*?\\s${attr}=")[^"]*(")`);
    if (!re.test(xml)) throw new Error(`config.xml: no ${attr} attribute on <widget>`);
    xml = xml.replace(re, `$1${value}$2`);
};

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

replaceAttr('id', brand.app.appId);
replaceAttr('version', brand.app.versionName);
replaceAttr('versionCode', String(brand.app.versionCode));
replaceAttr('android-versionCode', String(brand.app.versionCode));
replaceAttr('ios-CFBundleVersion', `${brand.app.versionName}.0`);

replaceTag('name', esc(brand.app.name));
replaceTag('description', esc(brand.app.description));

xml = xml.replace(
    /<author[^>]*>[\s\S]*?<\/author>/,
    `<author email="${esc(brand.app.authorEmail)}" href="${esc(brand.app.authorHref)}">${esc(brand.app.authorName)}</author>`,
);

if (xml === before) throw new Error('config.xml: nothing changed, patterns are stale');
writeFileSync(xmlPath, xml);
console.log(`patched ${xmlPath}`);
