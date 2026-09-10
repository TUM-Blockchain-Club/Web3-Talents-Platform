# Web3 Talents front page

The public landing page. It lives **outside Moodle** on purpose: it is expected to
grow into a real marketing site, and it should not be constrained by Moodle's theme
layer, its Mustache renderer, or its release cycle.

Its only connection to Moodle is a single link into the login page.

## Running it

There is no build step and no dependencies. Open `index.html` in a browser, or serve
the folder:

```bash
python3 -m http.server 4000
```

## Layout

```
index.html    the page
styles.css    all styling; brand tokens are the :root block at the top
app.js        one constant, MOODLE_LOGIN_URL, plus the footer year
assets/       images carried over from the Moodle theme
reference/    the original Figma-imported implementation, kept for content only
```

## Pointing it at Moodle

Every login control carries `data-login` and gets its href from one constant:

```js
const MOODLE_LOGIN_URL = 'http://130.61.104.92:8080/login/index.php';
```

Change that line when Moodle moves to its real domain. Nothing else refers to Moodle.

## Why there is no "sign up" button

Students cannot self-register, deliberately. On the Moodle side `registerauth` is
empty and `/login/signup.php` returns 404. Accounts are created by an admin from the
accepted-applicant roster (`local_web3talents`), which is what gates admission to the
cohort. A signup link would route around that.

The intended path is: applicants fill in an external form, accepted ones are imported
as a roster, an admin creates their accounts, and they receive an activation email.
This page is where they land afterwards to log in.

## reference/

`overview.php`, `overview.mustache` and `home.scss` are the original Figma import from
the Moodle theme. They are **not** wired into anything here and are not meant to be
revived as-is — they are kept so the copy, the speaker details and the section
structure are not lost while this page is rebuilt properly.

Delete the folder once nothing in it is needed.

## Deploying

Static files, so anything works: an Nginx or Caddy vhost on the same OCI box, object
storage, or a static host. If it ends up on the same server as Moodle, give it the
apex domain and put Moodle on a subdomain, so the front page is what visitors reach
first.
