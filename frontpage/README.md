# Web3 Talents front page

The public landing page, living **outside Moodle** so it can grow into a real
marketing site without being tied to Moodle's theme layer, Mustache renderer or
release cycle.

Its only connection to Moodle is a login link.

## Running it

No build step, no dependencies, no network requests. Open `index.html`, or serve
the folder:

```bash
python3 -m http.server 4000
```

## Layout

```
index.html    the page
styles.css    the compiled Web3 Talents styles
app.js        the two outbound URLs (login, apply)
assets/       27 images
reference/    the original Moodle-side source, kept for reference
```

## Where this came from

The design and markup are the Figma implementation as it renders in Moodle, not a
reinterpretation of it. The Moodle page was fetched, and:

- the page body was lifted out of Moodle's chrome
- `styles.css` was extracted from Moodle's compiled stylesheet, keeping every
  `web3t-*` rule plus the `:root` tokens, the media queries wrapping them, and the
  keyframes they animate — 677 rules, down from 1.16 MB of Bootstrap and Boost
- `image.php` URLs were rewritten to `assets/`
- links to the sibling Moodle pages became in-page anchors, since those pages do
  not exist here
- the interaction script (mobile nav, card stack, sliders, accordions) came across
  unchanged; it was already dependency-free

So it looks and behaves as it did inside Moodle, with nothing left pointing back at
the server except the login link.

## Outbound links

Both live at the top of `app.js`:

```js
const MOODLE_LOGIN_URL = 'http://130.61.104.92:8080/login/index.php';
const APPLY_URL = '';   // the external application form
```

`APPLY_URL` is empty for now, so "Apply Now" falls back to the login page rather
than dead-ending. Set it once the application form exists.

## Why there is no sign-up link

Students cannot self-register, by design. On the Moodle side `registerauth` is empty
and `/login/signup.php` returns 404. Admission runs through the accepted-applicant
roster in `local_web3talents`: applicants are imported from a form export, an admin
creates their accounts, and they get an activation email. A sign-up link would route
around that.

So: "Apply Now" goes to the application form, "Login" goes to Moodle.

## reference/

`overview.php`, `overview.mustache` and `home.scss` — the Moodle-side originals.
Nothing here uses them; they are kept so the SCSS source is at hand while this page
is rebuilt, since `styles.css` is compiled output and not pleasant to edit by hand.

**That is the main thing to fix next.** Editing 107 KB of compiled CSS is painful.
Porting `reference/home.scss` into a proper SCSS source with a small build step, or
rewriting the styles cleanly, should come before much more work lands here.

## Deploying

Static files. An Nginx or Caddy vhost on the same OCI box works, as does object
storage or any static host. If it shares a server with Moodle, give the front page
the apex domain and put Moodle on a subdomain so visitors reach this first.
