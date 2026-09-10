/* Web3 Talents front page — outbound links.
 *
 * This page is static, so the two places it hands off to are kept here as
 * constants rather than being spread through the markup. Moving Moodle to its
 * real domain, or publishing the application form, is a one-line change.
 */

// Where "Login" goes. Update when Moodle moves off the staging IP.
const MOODLE_LOGIN_URL = 'http://130.61.104.92:8080/login/index.php';

// Where "Apply Now" goes — the external application form (Tally or similar).
//
// Deliberately NOT a Moodle signup link: self-registration is disabled
// (registerauth is empty, /login/signup.php returns 404) because admission runs
// through the accepted-applicant roster in local_web3talents. A signup link
// would route around the selection process.
//
// While this is empty, apply buttons fall back to the login page so nothing is
// a dead end. Set it as soon as the form exists.
const APPLY_URL = '';

for (const el of document.querySelectorAll('[data-login]')) {
    el.href = MOODLE_LOGIN_URL;
}

for (const el of document.querySelectorAll('[data-apply]')) {
    el.href = APPLY_URL || MOODLE_LOGIN_URL;
}
