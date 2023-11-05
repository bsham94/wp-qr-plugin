# Wordpress QR Code Plugin

Simple Wordpress plugin for protecting pages with QR Codes.

### How it works

Set a post to user_profile
Add the shortcode [qr_shortcode] to the post with category user_profile. Your post should now be protected. Only the author of the page and admins can view the post without scanning the corresponding QR Code. Everyone else gets redirected to the home url of the Wordpress site.

### Setup

This plugin requires setting up a API_KEY, ENCRYPTION_KEY, and IV and storing them in a .env file.
example:
ENCRYPTION_KEY=DaaGt48veUdh0!@3463><t2Q
API_KEY=38Diehf301g38Dha2@!jd2
IV=AOd0fhPad^qf&h29
