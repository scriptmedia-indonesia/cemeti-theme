<?php
// Run: php tests/flows-media.php — pictures of imported posts: finding them by file path, repairing missing media.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
qa_reset_data();
CemetiWP::$caps = true;
CemetiWP::$loose_pictures = false; // This suite makes real attachments; a number that is no post is a missing picture.

/* ---- stubs of what WordPress does with files */
$uploads = sys_get_temp_dir() . '/cemeti-qa-uploads-' . getmypid();
@mkdir( $uploads, 0777, true );
if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	function wp_get_upload_dir() { return array( 'basedir' => $GLOBALS['qa_uploads'], 'baseurl' => 'http://new.test/wp-content/uploads' ); }
	function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
	function download_url( $url, $timeout = 300 ) {
		$GLOBALS['qa_downloads'][] = $url;
		if ( false !== strpos( $url, '/neterr' ) ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' );
		}
		if ( ! isset( $GLOBALS['qa_remote'][ $url ] ) ) {
			return new WP_Error( 'http_404', 'Not Found' );
		}
		$tmp = tempnam( sys_get_temp_dir(), 'qadl' );
		file_put_contents( $tmp, $GLOBALS['qa_remote'][ $url ] );
		return $tmp;
	}
	function wp_check_filetype( $f ) { return array( 'type' => 'image/jpeg', 'ext' => 'jpg' ); }
	function wp_insert_attachment( $args, $file = false, $parent = 0 ) {
		return CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => $args['post_title'], 'post_parent' => $parent, 'meta' => array( '_wp_attached_file' => str_replace( $GLOBALS['qa_uploads'] . '/', '', $file ) ) ) );
	}
	function wp_generate_attachment_metadata( $id, $file ) { $GLOBALS['qa_sized'][] = $id; return array( 'file' => $file ); }
	function wp_update_attachment_metadata( $id, $m ) { return true; }
}
$GLOBALS['qa_uploads'] = $uploads;
$GLOBALS['qa_downloads'] = $GLOBALS['qa_sized'] = array();
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$GLOBALS['qa_remote'] = array(
	'https://cemeti.art/wp-content/uploads/2021/06/a.jpg'  => $png,
	'https://cemeti.art/wp-content/uploads/2021/06/b.jpg'  => $png,
	'https://cemeti.art/wp-content/uploads/2021/06/html.jpg' => '<html>not an image</html>',
	'https://cemeti.art/wp-content/uploads/2021/06/moved.jpg' => $png,
);

/* ---- finding a picture by its file path, whatever the host or the size */
$att = CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'a', 'meta' => array( '_wp_attached_file' => '2021/06/a.jpg' ) ) );
ok( $att === cemeti_media_find_by_url( 'https://cemeti.art/wp-content/uploads/2021/06/a.jpg' ), 'media: found by path (old host)' );
ok( $att === cemeti_media_find_by_url( 'http://new.test/wp-content/uploads/2021/06/a-300x200.jpg?w=1024' ), 'media: found by path, size suffix and query dropped, other host' );
ok( 0 === cemeti_media_find_by_url( 'https://cemeti.art/wp-content/uploads/2021/06/nope.jpg' ) && 0 === cemeti_media_find_by_url( 'https://x.test/other/a.jpg' ), 'media: unknown picture and non-upload address give 0' );
$scaled = CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 's', 'meta' => array( '_wp_attached_file' => '2021/07/big-scaled.jpg' ) ) );
ok( $scaled === cemeti_media_find_by_url( 'https://cemeti.art/wp-content/uploads/2021/07/big.jpg' ), 'media: the "-scaled" original is found for the plain name' );
ok( 0 === cemeti_media_find_by_url( 'https://cemeti.art/wp-content/uploads/../../etc/passwd' ), 'media: a path with ".." is refused' );
ok( '2020/01/museum-2017..jpg' === cemeti_media_rel_path( 'https://x.test/cemeti/wp-content/uploads/2020/01/museum-2017..jpg?w=853' ) && '' === cemeti_media_rel_path( 'https://x.test/wp-content/uploads/a/../b.jpg' ), 'media: a file name with two dots is fine (it was refused), a folder ".." is not' );

/* ---- the old wp-image number is only trusted when it is the same file */
$other = CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'x', 'meta' => array( '_wp_attached_file' => '2020/01/something-else.jpg' ) ) );
ok( 0 === cemeti_media_img_id( 'https://cemeti.art/wp-content/uploads/2021/06/missing.jpg', 'wp-image-' . $other ), 'media: a wp-image number that names ANOTHER file is not used' );
ok( $other === cemeti_media_img_id( 'https://cemeti.art/wp-content/uploads/2021/09/something-else-150x150.jpg', 'wp-image-' . $other ), 'media: a wp-image number of the same file is accepted' );
ok( $att === cemeti_media_img_id( 'https://cemeti.art/wp-content/uploads/2021/06/a.jpg', 'wp-image-99999' ), 'media: the address wins over a wrong number' );

/* ---- the split keeps pictures that are not in the Media Library (nothing vanishes) */
$img = static function ( $name ) { return '<figure><img src="https://cemeti.art/wp-content/uploads/2021/06/' . $name . '.jpg" class="wp-image-77"></figure>'; };
$html = '<p>Intro text of the exhibition.</p><h2>Foto Karya</h2>' . $img( 'a' ) . $img( 'lost' ) . '<h2>Dokumentasi</h2>' . $img( 'lost2' );
$s = cemeti_restructure_content( $html, 0 );
ok( array( $att ) === $s['gallery'], 'split: the picture that exists goes to the gallery' );
ok( 2 === $s['missing'] && 2 === substr_count( $s['foreword'], '<img' ) && false !== strpos( $s['foreword'], 'lost.jpg' ) && false !== strpos( $s['foreword'], 'lost2.jpg' ), 'split: pictures that are not in the library stay in the text and are counted' );
ok( 1 === substr_count( $s['foreword'], 'Intro text' ), 'split: the text is intact' );

/* ---- convert a post: the missing count is kept for the report and cleared on revert */
$post = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Ghost Light', 'post_content' => $html, 'post_date' => '2021-06-16 10:00:00' ) );
cemeti_restructure_post( $post, 'exhibition' );
ok( 2 === (int) get_post_meta( $post, '_cemeti_media_missing', true ) && array( $att ) === get_post_meta( $post, 'cemeti_gallery', true ), 'convert: gallery filled, missing pictures counted on the post' );

/* ---- repair: restore the file of an attachment that exists without its file */
$gone = CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'b', 'meta' => array( '_wp_attached_file' => '2021/06/b.jpg' ) ) );
ok( 'restored' === cemeti_media_restore_attachment( $gone ) && file_exists( $uploads . '/2021/06/b.jpg' ) && in_array( $gone, $GLOBALS['qa_sized'], true ), 'repair: a missing file is downloaded from the old site and its sizes are made' );
ok( 'ok' === cemeti_media_restore_attachment( $gone ), 'repair: a file that is there is not downloaded again' );
$n = count( $GLOBALS['qa_downloads'] );
$bad = CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'h', 'meta' => array( '_wp_attached_file' => '2021/06/html.jpg' ) ) );
$msg = cemeti_media_restore_attachment( $bad );
ok( 'ok' !== $msg && 'restored' !== $msg && false !== strpos( $msg, 'not a picture' ) && ! file_exists( $uploads . '/2021/06/html.jpg' ), 'repair: an HTML page saved as a picture is refused and not stored' );
$none = CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'g', 'meta' => array( '_wp_attached_file' => '2021/06/gone.jpg' ) ) );
ok( false !== strpos( cemeti_media_restore_attachment( $none ), '404' ) || false !== strpos( cemeti_media_restore_attachment( $none ), 'Not Found' ), 'repair: a file the old site no longer has gives a readable error' );
$trav = CemetiWP::post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 't', 'meta' => array( '_wp_attached_file' => '../../evil.php' ) ) );
ok( 'ok' !== cemeti_media_restore_attachment( $trav ) && ! file_exists( dirname( $uploads ) . '/evil.php' ), 'repair: a path that climbs out of the uploads folder is refused' );

/* ---- repair a post: create library items for pictures that have none, set the featured image */
unlink( $uploads . '/2021/06/b.jpg' );
$p2 = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Two', 'post_content' => '<p>x</p><img src="https://cemeti.art/wp-content/uploads/2021/06/b-300x300.jpg" class="wp-image-5"><img src="https://cemeti.art/wp-content/uploads/2021/06/zzz.jpg">', 'meta' => array( '_thumbnail_id' => 4242 ) ) );
$one = cemeti_media_repair_post( $p2 );
ok( 1 === $one['thumb'] && (int) get_post_meta( $p2, '_thumbnail_id', true ) === $gone && is_file( $uploads . '/2021/06/b.jpg' ), 'repair post: an invalid featured image (old ID) becomes the first picture of the text, file restored' );
ok( 1 === $one['missing'] && 1 === count( $one['failed'] ) && 0 === $one['created'], 'repair post: the picture the old site no longer has is reported, not hidden (' . json_encode( $one ) . ')' );
ok( 1 === (int) get_post_meta( $p2, '_cemeti_media_missing', true ), 'repair post: the post remembers how many pictures are still missing' );
$p3 = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Three', 'post_content' => '<img src="https://cemeti.art/wp-content/uploads/2021/06/a.jpg">', 'meta' => array( '_thumbnail_id' => $att ) ) );
$two = cemeti_media_repair_post( $p3 );
ok( 0 === $two['thumb'] && 0 === $two['missing'] && $att === (int) get_post_meta( $p3, '_thumbnail_id', true ), 'repair post: a valid featured image is left alone' );
$p4 = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Four', 'post_content' => '<img src="https://cemeti.art/wp-content/uploads/2021/06/a.jpg">' ) );
$before = count( CemetiWP::$posts );
$three = cemeti_media_repair_post( $p4 );
ok( 1 === $three['thumb'] && $att === (int) get_post_meta( $p4, '_thumbnail_id', true ) && count( CemetiWP::$posts ) === $before, 'repair post: a post with no featured image gets the picture that is already in the library (no duplicate)' );
$p5 = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Five', 'post_content' => '<img src="https://evil.example/wp-content/uploads/2021/06/a2.jpg">' ) );
$dl = count( $GLOBALS['qa_downloads'] );
$four = cemeti_media_repair_post( $p5 );
ok( count( $GLOBALS['qa_downloads'] ) === $dl && 1 === $four['missing'], 'repair post: pictures of other websites are never downloaded' );

/* ---- a text whose address was replaced by THIS site's (search-and-replace / importer): the file is still fetched from the old site */
$p7 = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Seven', 'post_content' => '<img src="http://cemeti.test/cemeti/wp-content/uploads/2021/06/moved.jpg">' ) );
$seven = cemeti_media_repair_post( $p7 );
ok( 1 === $seven['created'] && 0 === $seven['missing'] && is_file( $uploads . '/2021/06/moved.jpg' ) && 1 === $seven['thumb'], 'repair post: a picture whose address names this site is downloaded from the old site' );
ok( 1 === count( $four['failed'] ) && false !== strpos( $four['failed'][0], 'Skipped' ), 'repair post: a skipped picture says why (never silent)' );

/* ---- a step never runs past its time (Cloudflare cuts a page after 100 s) and never marks what it did not finish */
cemeti_media_clock( 'set', microtime( true ) - 1 ); // The time is already up.
$dl = count( $GLOBALS['qa_downloads'] );
$p8 = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Eight', 'post_content' => '<img src="https://cemeti.art/wp-content/uploads/2021/06/a.jpg"><img src="https://cemeti.art/wp-content/uploads/2021/06/b.jpg">' ) );
$eight = cemeti_media_repair_post( $p8 );
ok( $eight['incomplete'] && count( $GLOBALS['qa_downloads'] ) === $dl && '' === (string) get_post_meta( $p8, '_thumbnail_id', true ), 'time: out of time the post is left unfinished, nothing is downloaded or changed' );
cemeti_media_clock( 'reset' );
$step_posts = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Nine', 'post_content' => '<img src="https://cemeti.art/wp-content/uploads/2021/06/neterr1.jpg"><img src="https://cemeti.art/wp-content/uploads/2021/06/neterr2.jpg"><img src="https://cemeti.art/wp-content/uploads/2021/06/neterr3.jpg"><img src="https://cemeti.art/wp-content/uploads/2021/06/neterr4.jpg"><img src="https://cemeti.art/wp-content/uploads/2021/06/neterr5.jpg">' ) );
foreach ( CemetiWP::$meta as $mid => $mm ) { unset( CemetiWP::$meta[ $mid ]['_cemeti_media_post'] ); }
delete_option( 'cemeti_media_report' );
$dl = count( $GLOBALS['qa_downloads'] );
$left = cemeti_media_step( 'posts', 12.0 );
$tries = array_filter( array_slice( $GLOBALS['qa_downloads'], $dl ), static function ( $u ) { return false !== strpos( $u, '/neterr' ); } );
ok( -1 === $left && count( $tries ) <= 3 && '' === (string) get_post_meta( $step_posts, '_cemeti_media_post', true ), 'network: after three timeouts the step stops (no more waiting), the post stays unmarked (' . count( $tries ) . ' tries)' );
ok( false !== strpos( implode( ' ', cemeti_media_report()['errors'] ), 'could not connect' ), 'network: the report says the server cannot reach the old site' );
CemetiWP::$posts[ $step_posts ]['post_content'] = '<p>done</p>';

/* ---- the original text of a converted post is used (its pictures are not in the shortened body) */
$p6 = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Six', 'post_content' => '<p>short</p>', 'meta' => array( '_cemeti_original_content' => '<h2>Foto Karya</h2><img src="https://cemeti.art/wp-content/uploads/2021/06/a.jpg">' ) ) );
$five = cemeti_media_repair_post( $p6 );
ok( 1 === $five['thumb'] && $att === (int) get_post_meta( $p6, '_thumbnail_id', true ), 'repair post: pictures of the kept original text count too' );

/* ---- steps: time-boxed, resumable, reported */
delete_option( 'cemeti_media_report' );
foreach ( CemetiWP::$meta as $id => $m ) { unset( CemetiWP::$meta[ $id ]['_cemeti_media_state'] ); }
$left = cemeti_media_step( 'files', 18.0 );
$rep = cemeti_media_report();
ok( 0 === $left && $rep['failed'] >= 3 && $rep['ok'] + $rep['restored'] >= 2 && $rep['errors'], 'steps: every attachment is checked once, failures are listed (' . json_encode( array_slice( $rep, 0, 3 ) ) . ')' );
$left = cemeti_media_step( 'files', 18.0 );
ok( 0 === $left && $rep === cemeti_media_report(), 'steps: running again changes nothing' );
$left = cemeti_media_step( 'posts', 18.0 );
ok( 0 === $left && cemeti_media_report()['posts'] > 0, 'steps: posts are repaired and counted' );

/* ---- the cards use the same resolver (no wrong picture from an old number) */
$card = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Card', 'post_content' => '<img src="https://cemeti.art/wp-content/uploads/2021/06/a.jpg" class="wp-image-' . $other . '">' ) );
ok( $att === (int) get_post_thumbnail_id( $card ), 'cards: the fallback picture is the one named by the file path, not by the old number' );
$card2 = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Card2', 'post_content' => '<img src="https://cemeti.art/wp-content/uploads/2021/06/unknown-file.jpg" class="wp-image-' . $other . '">' ) );
ok( ! get_post_thumbnail_id( $card2 ), 'cards: an old number that belongs to another file gives no picture instead of a wrong one' );

/* ---- the page's own runner (admin-ajax) */
function qa_ajax( $post ) {
	$_POST = $_REQUEST = $post;
	try { cemeti_media_ajax(); } catch ( CemetiJson $e ) { return $e; } catch ( CemetiDie $e ) { return $e; }
	return null;
}
$r = qa_ajax( array( 'phase' => 'files' ) );
ok( $r instanceof CemetiDie, 'ajax: a request without the nonce is refused' );
CemetiWP::$caps = false;
$r = qa_ajax( array( 'nonce' => 'valid', 'phase' => 'files' ) );
ok( $r instanceof CemetiJson && 403 === $r->status && ! $r->payload['success'], 'ajax: a user who may not manage options gets 403' );
CemetiWP::$caps = true;
delete_option( 'cemeti_media_report' );
$r = qa_ajax( array( 'nonce' => 'valid', 'phase' => 'files' ) );
ok( $r instanceof CemetiJson && $r->payload['success'] && isset( $r->payload['data']['remaining'], $r->payload['data']['report']['ok'] ) && false === $r->payload['data']['stopped'], 'ajax: a step answers with remaining and the report' );
$r = qa_ajax( array( 'nonce' => 'valid', 'phase' => 'posts', 'fresh' => '1' ) );
ok( 0 === $r->payload['data']['remaining'] && 'posts' === $r->payload['data']['phase'], 'ajax: the posts step runs and reports nothing left' );
$r = qa_ajax( array( 'nonce' => 'valid', 'phase' => 'reset' ) );
ok( 'done' === $r->payload['data']['phase'] && 0 === cemeti_media_report()['posts'], 'ajax: start over clears the report' );
$r = qa_ajax( array( 'nonce' => 'valid', 'phase' => 'nonsense' ) );
ok( 'files' === $r->payload['data']['phase'], 'ajax: an unknown phase falls back to restoring files' );

qa_done();
