/* Web3 Talents front page behaviour.
 *
 * The only thing this page needs to know about Moodle is where to send someone who
 * wants to log in. Keeping that in one constant means moving Moodle to a new domain
 * is a one-line change here rather than a find-and-replace through the markup.
 *
 * There is deliberately no "sign up" destination: accounts are created by an admin
 * from the accepted-applicant roster, and Moodle's own /login/signup.php is disabled
 * (registerauth is empty, and the page returns 404). Adding a signup link would
 * bypass the admissions process entirely.
 */

const MOODLE_LOGIN_URL = 'http://130.61.104.92:8080/login/index.php';

for (const link of document.querySelectorAll('[data-login]')) {
    link.href = MOODLE_LOGIN_URL;
}

document.getElementById('year').textContent = String(new Date().getFullYear());
