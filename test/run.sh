#!/usr/bin/env bash
#
# The checks that need nothing but PHP and Node. Run before every deploy.
#
# The browser checks live in test/browser and are NOT run here, because they
# need Playwright, which is deliberately not a dependency of this repo (the
# landing page's production install would otherwise pull a browser). Run them
# with:
#     npm i -D playwright && npx playwright install chromium
#     php test/preview.php
#     node test/browser/layout.mjs && node test/browser/wordmark.mjs
set -uo pipefail
cd "$(dirname "$0")/.."

fail=0
step() {
	printf '\n\033[1m== %s\033[0m\n' "$1"; shift
	"$@" || fail=1
}

step "PHP syntax" bash -c '
	bad=0
	while read -r f; do php -l "$f" >/dev/null || { echo "  LINT FAIL $f"; bad=1; }; done \
		< <(find blog -name "*.php")
	[ $bad -eq 0 ] && echo "  every PHP file parses"
	exit $bad'

step "Theme templates render"      php test/render.test.php
step "SEO and AI metadata"         php test/seo.test.php
step "robots.txt policy"           node test/robots.test.js
step "Health check path"           php test/healthz.test.php
step "Stylesheets and tokens"      php test/css.test.php
step "Palette contrast"            php test/palette.test.php
step "Proxy: /blog passthrough"    node test/proxy.test.js

printf '\n\033[1m== X-Forwarded-Proto parsing\033[0m\n'
protofail=0
while IFS='|' read -r header expected label; do
	got=$(php test/proxy-proto.test.php "$header")
	if [ "$got" = "$expected" ]; then printf '  ok    %-44s HTTPS %s\n' "$label" "$got"
	else printf '  FAIL  %-44s got %s want %s\n' "$label" "$got" "$expected"; protofail=1; fi
done <<'CASES'
https,http|on|Railway edge + internal hop
https|on|single TLS proxy
https, http|on|list with a space
HTTPS,http|on|uppercase from a proxy
http|off|genuinely plain HTTP
http,https|off|client was HTTP
--|off|header absent
CASES
[ $protofail -eq 0 ] || fail=1

if [ $fail -eq 0 ]; then printf '\n\033[32mall checks passed\033[0m\n'; else printf '\n\033[31mFAILURES\033[0m\n'; fi
exit $fail
