#!/usr/bin/env bash
# Proves the tests can fail: plants one deliberate bug at a time in the theme and checks that a test notices.
# Every line below must say "caught". If one says NOT CAUGHT, a behaviour has no real test: add one.
#   bash tests/mutation-check.sh          (takes about two minutes; needs Playwright for the two browser mutations)
# The theme file is restored after every mutation, also if you stop the script (Ctrl-C).
set -u
cd "$(dirname "$0")/.."
T=themes/scriptmedia-cemeti
backup=""; target=""
restore() { [ -n "$backup" ] && [ -f "$backup" ] && cp "$backup" "$target"; }
trap 'restore; exit 130' INT TERM
trap restore EXIT

missed=0
# mutate <label> <file> <old> <new> <test...>
mutate() {
  local label="$1"; target="$2"; local old="$3" new="$4"; shift 4
  backup=$(mktemp)
  cp "$target" "$backup"
  if ! python3 - "$target" "$old" "$new" <<'PY'
import sys
f, old, new = sys.argv[1:4]
s = open(f).read()
if old not in s:
    sys.exit(3)
open(f, 'w').write(s.replace(old, new, 1))
PY
  then
    printf '  %-48s SKIPPED (the code it mutates has changed; update this line)\n' "$label"; cp "$backup" "$target"; return
  fi
  local caught=""
  for t in "$@"; do
    if [ "$t" = browser ]; then out=$(php tests/render-pages.php >/dev/null 2>&1; node tests/browser/browser-qa.js 2>&1); code=$?
    else out=$(php "tests/$t.php" 2>&1); code=$?; fi
    if [ $code -ne 0 ]; then caught="$t"; break; fi
  done
  cp "$backup" "$target"; rm -f "$backup"; backup=""
  if [ -n "$caught" ]; then printf '  %-48s caught by %s\n' "$label" "$caught"; else printf '  %-48s NOT CAUGHT\n' "$label"; missed=$((missed+1)); fi
}

mutate "status: last day not inclusive"            $T/inc/status.php "return \$today <= \$end ? 'current' : 'previous';" "return \$today < \$end ? 'current' : 'previous';" flows-content
mutate "fields: nonce check removed"               $T/inc/fields.php "! wp_verify_nonce( sanitize_text_field( wp_unslash( \$_POST['cemeti_fields_nonce'] ) ), 'cemeti_save_fields' )" "false" flows-content
mutate "form: honeypot ignored"                    $T/inc/applications.php "! empty( \$_POST['cemeti_website'] ) ||" "false ||" flows-apply
mutate "form: PDF content check removed"           $T/inc/applications.php "return '%PDF-' === \$head ? '' : 'invalid';" "return '';" flows-apply m4-logic
mutate "form: download path guard removed"         $T/inc/applications.php "0 !== strpos( \$path, \$root . DIRECTORY_SEPARATOR ) || " "" flows-apply
mutate "form: closed Open Call still accepted"     $T/inc/applications.php "'previous' !== cemeti_get_status( \$open_call_id )" "true" flows-apply render-pages
mutate "form: PDFs kept after deleting an entry"   $T/inc/applications.php "wp_delete_file( \$path );
		}
	}
}
add_action( 'before_delete_post'" "}
	}
}
add_action( 'before_delete_post'" flows-apply
mutate "related: mirror link not written"          $T/inc/relations.php "cemeti_store_related( \$other, \$key, \$theirs );
			\$kept[] = \$other;" "\$kept[] = \$other;" flows-content
mutate "seo: description not escaped"              $T/inc/seo.php "esc_attr( \$description )" "\$description" flows-content
mutate "seo: summary keeps markup"                 $T/inc/seo.php "preg_replace( '/<[^>]*>/', ' ', preg_replace( '/\\[[^\\]]*\\]/', ' ', (string) \$text ) )" "preg_replace( '/\\[[^\\]]*\\]/', ' ', (string) \$text )" flows-content m5-logic
mutate "search form: query printed raw"            $T/template-parts/search-overlay.php "esc_attr( get_search_query( false ) )" "get_search_query( false )" render-pages
mutate "archive: newest-first order lost"          $T/inc/queries.php "'by_date' => 'DESC'" "'by_date' => 'ASC'" render-pages flows-content
mutate "security: REST user list exposed"          $T/inc/security.php "unset( \$endpoints['/wp/v2/users'], \$endpoints['/wp/v2/users/(?P<id>[\\d]+)'] );" "" flows-content m5-logic
mutate "converter: language not stored"            $T/inc/migration.php "update_post_meta( \$post_id, '_cemeti_lang', \$info['lang'] );" "" flows-migration flows-polylang
mutate "converter: date range reversed"            $T/inc/migration-rules.php "\$end < \$start ? array( \$start, \$start ) : array( \$start, \$end );" "array( \$end, \$start );" flows-migration migration-logic
mutate "i18n: a string lacks English"              $T/inc/i18n.php "'close'              => array( 'id' => 'Tutup', 'en' => 'Close' )," "'close'              => array( 'id' => 'Tutup', 'en' => '' )," static-checks
mutate "pages: not created on activation"         $T/inc/pages.php "add_action( 'after_switch_theme', 'cemeti_create_pages_on_switch', 20 );" "" flows-pages
mutate "pages: existing page overwritten"         $T/inc/pages.php "if ( ! \$parent ) {" "if ( true ) {" flows-pages
mutate "polylang: types listed with a wrong value"  $T/inc/content-model.php "\$translatable[ \$type ] = \$type;" "\$translatable[ \$type ] = 'cemeti';" flows-polylang
mutate "pictures: first picture not used"         $T/inc/templates.php "if ( preg_match( '/wp-image-(\\d+)/', \$content, \$match ) ) {" "if ( false ) {" flows-content
mutate "old language links kept in the text"      $T/inc/templates.php "add_filter( 'the_content', 'cemeti_filter_language_links', 5 );" "" render-pages flows-content
mutate "pages: Indonesian page not recognised"      $T/inc/pages.php "\$aliases = cemeti_page_slug_aliases();" "\$aliases = array();" flows-pages flows-languages
mutate "open call: the Active tick ignored"         $T/inc/applications.php "return '1' === \$flag; // The team's choice" "return true; // The team's choice" m4-logic flows-content
mutate "home: the <h1> removed"                    $T/front-page.php '<h1 class="screen-reader-text"><?php echo esc_html( cemeti_option( '"'"'institution_name'"'"' ) ); ?></h1>' "" render-pages
mutate "header: logo without alt text"             $T/header.php 'fetchpriority="high" alt="<?php echo esc_attr( cemeti_option( '"'"'institution_name'"'"' ) ); ?>">' 'fetchpriority="high">' render-pages
mutate "split: the saved original overwritten"        $T/inc/restructure.php "if ( '' === (string) get_post_meta( \$post_id, '_cemeti_original_content', true ) ) {" "if ( true ) {" flows-reconvert
mutate "media: picture found only by the old number"  $T/inc/media.php "	if ( ! \$id ) {
		\$id = cemeti_media_find_by_url( \$src );
	}" "" flows-media flows-content
mutate "media: any old wp-image number is trusted"    $T/inc/media.php "if ( '' === \$src || '' === \$file || cemeti_media_basename_key( \$file ) === cemeti_media_basename_key( \$src ) ) {" "if ( true ) {" flows-media
mutate "media: downloads from any website"            $T/inc/media.php "	\$host = (string) wp_parse_url( (string) \$url, PHP_URL_HOST );" "	return true;
	\$host = (string) wp_parse_url( (string) \$url, PHP_URL_HOST );" flows-media
mutate "split: missing pictures dropped from the text" $T/inc/restructure.php "\$total_missing += count( \$unfound );
			\$keep[]         = '<p>' . implode( ' ', \$unfound ) . '</p>';" "\$total_missing += count( \$unfound );" flows-restructure flows-media
mutate "split: headings inside block groups ignored"  $T/inc/restructure.php "if ( \$div->parentNode && false === strpos( \$class, 'spacer' )" "if ( false && \$div->parentNode && false === strpos( \$class, 'spacer' )" flows-restructure
mutate "split: long text in picture sections dropped" $T/inc/restructure.php "} elseif ( ! \$ids && '' !== trim( \$node->textContent ) ) {" "} elseif ( false ) {" flows-restructure
if node -e "require('playwright')" >/dev/null 2>&1; then
  mutate "css: menu panel full width"              $T/assets/css/layout.css "width: min(31.5rem, 100% - 2 * var(--gutter));" "width: 100%;" browser
  mutate "js: Escape no longer closes panels"      $T/assets/js/navigation.js "if ( event.key === 'Escape' ) {" "if ( event.key === 'F13' ) {" browser
else
  echo "  (browser mutations skipped: Playwright not installed)"
fi

echo
[ $missed -eq 0 ] && echo "Every planted bug was caught." || echo "$missed planted bug(s) were NOT caught."
exit $missed
