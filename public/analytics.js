window.dataLayer = window.dataLayer || [];
function gtag() {
    window.dataLayer.push(arguments);
}
gtag('consent', 'default', {
    analytics_storage: 'granted',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied'
});
gtag('set', {
    page_location: window.location.origin + window.location.pathname,
    page_referrer: '',
    allow_google_signals: false,
    allow_ad_personalization_signals: false,
    cookie_expires: 60 * 60 * 24 * 180,
    cookie_update: false
});
window.dataLayer.push({'gtm.start': Date.now(), event: 'gtm.js'});
const container = document.createElement('script');
container.async = true;
container.src = 'https://www.googletagmanager.com/gtm.js?id=GTM-PV3L5P45';
document.head.appendChild(container);
