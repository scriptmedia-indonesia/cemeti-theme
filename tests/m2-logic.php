<?php
// Run: php tests/m2-logic.php  (stubs WordPress; checks sanitizing, status and two-way relations).
define('ABSPATH','/'); define('CEMETI_DIR', dirname(__DIR__).'/themes/scriptmedia-cemeti'); define('CEMETI_VERSION','t');
$GLOBALS['meta']=[]; $GLOBALS['types']=[]; $GLOBALS['today']='20260315';
function add_action(){} function add_filter(){} function sanitize_file_name($s){return $s;}
function get_post_meta($i,$k,$s=false){return $GLOBALS['meta'][$i][$k]??'';}
function update_post_meta($i,$k,$v){$GLOBALS['meta'][$i][$k]=$v;} function delete_post_meta($i,$k){unset($GLOBALS['meta'][$i][$k]);}
function get_post_type($i){return $GLOBALS['types'][$i]??'exhibition';} function get_the_ID(){return 1;}
function wp_date($f){return $GLOBALS['today'];}
function sanitize_text_field($s){return trim(strip_tags($s));} function sanitize_textarea_field($s){return trim(strip_tags($s));} function esc_url_raw($s){return $s;} function absint($v){return abs((int)$v);}
function cemeti_current_lang(){return 'en';} function cemeti_t($k){return $k;}
foreach(['core-bridge','fields','relations','status','dates'] as $f) require CEMETI_DIR."/inc/$f.php";
$fail=0; function ok($c,$m){global $fail; echo ($c?'PASS ':'FAIL ').$m."\n"; if(!$c)$fail++;}
$d=cemeti_field_def('exhibition','start_date');
ok(cemeti_sanitize_field($d,'2026-03-06')==='20260306','date Y-m-d -> Ymd');
ok(cemeti_sanitize_field($d,'2026-02-30')==='','invalid date rejected');
ok(cemeti_sanitize_field(['type'=>'images'],'3, 3,x,0,7')===[3,7],'images csv');
ok(cemeti_sanitize_field(['type'=>'relation','max'=>4],[1,2,3,4,5,2])===[1,2,3,4],'relation max 4 + unique');
$rep=cemeti_field_def('exhibition','biographies');
$rows=cemeti_sanitize_field($rep,[['name'=>'A','photo'=>'9','bio'=>''],['name'=>'','photo'=>'0','bio'=>''],'bad']);
ok(count($rows)===1 && $rows[0]['photo']===9,'repeater drops empty rows');
// status
$GLOBALS['meta'][1]=['cemeti_start_date'=>'20260306','cemeti_end_date'=>'20260418'];
ok(cemeti_get_status(1)==='current','current inside window');
$GLOBALS['today']='20260305'; ok(cemeti_get_status(1)==='upcoming','upcoming before start');
$GLOBALS['today']='20260419'; ok(cemeti_get_status(1)==='previous','previous after end');
$GLOBALS['today']='20260418'; ok(cemeti_get_status(1)==='current','end date inclusive');
$GLOBALS['types'][2]='open_call'; $GLOBALS['meta'][2]=['cemeti_deadline'=>'20260320']; $GLOBALS['today']='20260315';
ok(cemeti_get_status(2)==='current','open call before deadline'); $GLOBALS['today']='20260321'; ok(cemeti_get_status(2)==='previous','open call after deadline');
$GLOBALS['types'][3]='journal'; ok(cemeti_get_status(3)==='','journal has no status');
$mq=cemeti_status_meta_query('current','exhibition'); ok(count($mq)===3 && $mq['relation']==='AND','current meta query');
ok(cemeti_status_meta_query('upcoming','open_call')===[],'open call has no upcoming');
// relations
$GLOBALS['meta']=[];
$r=cemeti_sync_relations(10,'related',[11,12]);
cemeti_store_related(10,'related',$r); ok($r===[11,12] && cemeti_related_ids(11)===[10] && cemeti_related_ids(12)===[10],'two-way link created');
$r=cemeti_sync_relations(10,'related',[0+12]); cemeti_store_related(10,'related',$r);
ok(cemeti_related_ids(11)===[],'removal unlinks other side');
foreach([21,22,23,24] as $o) cemeti_store_related(30,'related',array_merge(cemeti_related_ids(30),[$o])); // 30 full
$r=cemeti_sync_relations(40,'related',[30]);
ok($r===[],'link to a full entry is dropped');
$r=cemeti_sync_relations(40,'related',[1,2,3,4,5,6]);
ok(count($r)===4,'cap 4 on own list');
$r=cemeti_sync_relations(50,'related',[50,51]); ok($r===[51],'self link ignored');
cemeti_store_related(60,'related',[61]); cemeti_store_related(61,'related',[60]);
cemeti_unlink_deleted_post(60); ok(cemeti_related_ids(61)===[],'delete unlinks');
echo $fail? "\n$fail FAILED\n":"\nALL OK\n"; exit($fail?1:0);
