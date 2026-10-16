#!/usr/bin/env bash
# Runs every rfs-ai-seo test script against the staging site.
set -u
cd /var/www/vhosts/rfsdev.co.uk/httpdocs/atsdiamondtools || exit 1
fail=0
for t in wp-content/plugins/rfs-ai-seo/tests/test-*.php; do
	echo "== $t"
	wp eval-file "$t" --skip-themes || fail=1
done
exit $fail
