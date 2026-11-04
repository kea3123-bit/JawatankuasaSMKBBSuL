<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$allowed=['https://kea3123-bit.github.io','https://farshoffs.github.io'];
if ($origin!=='') {
    $originHost=strtolower((string)parse_url($origin,PHP_URL_HOST));
    $selfHost=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $same=$originHost!==''&&$originHost===$selfHost;
    if (!$same&&!in_array($origin,$allowed,true)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'Origin not authorized']); exit;
    }
    header('Access-Control-Allow-Origin: '.$origin);
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Max-Age: 600');
    header('Vary: Origin',false);
}
if (($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}
if (($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'POST sahaja']);exit;}
if (stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/json')===false){http_response_code(415);echo json_encode(['ok'=>false,'error'=>'JSON required']);exit;}

try{
    require_once __DIR__.'/_lib.php';
    $in=json_decode((string)file_get_contents('php://input'),true,128,JSON_THROW_ON_ERROR);
    if(!is_array($in)||!is_string($in['fn']??null)||!is_array($in['args']??null)) throw new InvalidArgumentException('Request tidak sah.');
    $result=jt_rpc($in['fn'],$in['args']);
    echo json_encode(['ok'=>true,'result'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){
    $safe=$e instanceof PDOException?jt_db_public_error($e):$e->getMessage();
    $ref=strtoupper(bin2hex(random_bytes(4)));
    error_log('[Jawatankuasa API '.$ref.'] '.get_class($e).' '.$e->getMessage());
    http_response_code($e instanceof InvalidArgumentException?400:422);
    echo json_encode(['ok'=>false,'error'=>$safe,'reference'=>$ref],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function jt_rpc(string $fn,array $a){
    switch($fn){
      case 'getApiStatus': return ['healthy'=>true,'backend'=>'PHP/MySQL','auth'=>'eKeberadaan MySQL API'];
      case 'jtGetBootstrap': return jt_get_bootstrap((string)($a[0]??''),!empty($a[1]),(int)($a[2]??0));
      case 'jtGetTeacherDetail': return jt_get_teacher_detail((string)($a[0]??''),(string)($a[1]??''),(int)($a[2]??0));
      case 'jtGetAdminRules': return jt_get_admin_rules((string)($a[0]??''));
      case 'jtGetAdminTeachers': return jt_get_admin_teachers((string)($a[0]??''),!empty($a[1]));
      case 'jtGetAdminCommittees': return jt_get_admin_committees((string)($a[0]??''),(int)($a[1]??0));
      case 'jtGetAdminData': return jt_get_admin_data((string)($a[0]??''));
      case 'jtGetSessions': return jt_get_sessions((string)($a[0]??''));
      case 'jtGetSessionDashboard': return jt_get_session_dashboard((string)($a[0]??''),(int)($a[1]??0),(int)($a[2]??0));
      case 'jtAdminCloneSession': return jt_admin_clone_session((string)($a[0]??''),(int)($a[1]??0),(int)($a[2]??0));
      case 'jtAdminSetSessionStatus': return jt_admin_set_session_status((string)($a[0]??''),(int)($a[1]??0),(string)($a[2]??''));
      case 'jtAdminBulkReassign': return jt_admin_bulk_reassign((string)($a[0]??''),is_array($a[1]??null)?$a[1]:[],(string)($a[2]??''),(int)($a[3]??0));
      case 'jtAdminBulkDeactivate': return jt_admin_bulk_deactivate((string)($a[0]??''),is_array($a[1]??null)?$a[1]:[],(int)($a[2]??0));
      case 'jtGetCommitteeRoles': return jt_get_committee_roles((string)($a[0]??''),(string)($a[1]??''),(int)($a[2]??0));
      case 'jtAdminUpdatePoint': return jt_admin_update_point((string)($a[0]??''),(string)($a[1]??''),$a[2]??0,(string)($a[3]??$a[1]??''));
      case 'jtAdminDeletePoint': return jt_admin_delete_point((string)($a[0]??''),(string)($a[1]??''));
      case 'jtAdminUpdateTeacher': return jt_admin_update_teacher((string)($a[0]??''),(string)($a[1]??''),(string)($a[2]??''));
      case 'jtAdminSaveCommittee': return jt_admin_save_committee((string)($a[0]??''),is_array($a[1]??null)?$a[1]:[]);
      case 'jtAdminDeleteCommittee': return jt_admin_delete_committee((string)($a[0]??''),(string)($a[1]??''));
      case 'jtAdminSaveAssignment': return jt_admin_save_assignment((string)($a[0]??''),is_array($a[1]??null)?$a[1]:[]);
      case 'jtAdminDeactivateAssignment': return jt_admin_deactivate_assignment((string)($a[0]??''),(string)($a[1]??''));
      default: throw new RuntimeException('Fungsi API Jawatankuasa tidak disokong: '.$fn);
    }
}
function jt_user_public(array $u): array {
    return [
      'name'=>(string)($u['name']??''),'email'=>(string)($u['email']??''),
      'category'=>(string)($u['category']??''),'jobTitle'=>(string)($u['jobTitle']??''),
      'isAdmin'=>!empty($u['isAdmin']),'adminSource'=>(string)($u['adminSource']??'')
    ];
}
function jt_get_sessions(string $token): array {
    $u=jt_user($token,false);
    $sql=!empty($u['isAdmin'])
      ? "SELECT * FROM sessions ORDER BY session_year DESC"
      : "SELECT * FROM sessions WHERE status IN ('PUBLISHED','ARCHIVED') ORDER BY session_year DESC";
    $rows=jt_rows($sql);
    $sessions=array_map(static fn($r)=>[
      'year'=>(int)$r['session_year'],'label'=>(string)$r['label'],'status'=>(string)$r['status'],
      'sourceYear'=>$r['source_year']!==null?(int)$r['source_year']:null,
      'editable'=>in_array((string)$r['status'],['DRAFT','REVIEW'],true)
    ],$rows);
    return ['ok'=>true,'sessions'=>$sessions,'defaultYear'=>!empty($u['isAdmin'])?jt_admin_session_year():jt_public_session_year()];
}
function jt_get_bootstrap(string $token,bool $includeSearch,int $requestedYear=0): array {
    $u=jt_user($token,false);$year=jt_resolve_session_year($u,$requestedYear);$stats=jt_teacher_stats($year);
    $public=array_map(static fn($t)=>['id'=>$t['id'],'name'=>$t['name'],'jobTitle'=>$t['jobTitle'],'count'=>$t['count']],$stats);
    $s=jt_settings();$session=jt_session_row($year);$sessionData=jt_get_sessions($token);
    $out=[
      'ok'=>true,'user'=>jt_user_public($u),'teachers'=>!empty($u['isAdmin'])?$stats:$public,
      'school'=>$s['SCHOOL_NAME']??'SMK Bandar Baru Sungai Lalang','year'=>$year,
      'session'=>['year'=>$year,'label'=>(string)($session['label']??('Sesi '.$year)),'status'=>(string)($session['status']??'PUBLISHED'),'editable'=>in_array((string)($session['status']??''),['DRAFT','REVIEW'],true)],
      'sessions'=>$sessionData['sessions']
    ];
    if($includeSearch){
        $out['searchPeople']=array_map(static fn($r)=>['id'=>(string)$r['person_id'],'name'=>(string)$r['nama'],'group'=>(string)$r['kumpulan'],'jobTitle'=>(string)$r['jawatan_hakiki']],jt_rows("SELECT person_id,nama,kumpulan,jawatan_hakiki FROM guru WHERE aktif=1 AND kumpulan IN ('Guru','AKP') ORDER BY nama"));
        $records=[];
        foreach(jt_rows("SELECT p.person_id,p.nama,p.committee_id,p.jawatankuasa,p.peranan,p.kod_skor,p.bidang,p.mata,j.manual_ref,j.parent_committee_id,j.component_code,j.hierarchy_level
          FROM pelantikan p JOIN guru g ON g.person_id=p.person_id LEFT JOIN jawatankuasa j ON j.committee_id=p.committee_id
          WHERE p.aktif=1 AND p.session_year=? AND g.aktif=1 ORDER BY p.nama,p.jawatankuasa",[$year]) as $r){
            $x=['personId'=>(string)$r['person_id'],'name'=>(string)$r['nama'],'committeeId'=>(string)$r['committee_id'],'committee'=>(string)$r['jawatankuasa'],'role'=>(string)$r['peranan'],'field'=>(string)$r['bidang'],
                'manualRef'=>(string)($r['manual_ref']??''),'parentId'=>(string)($r['parent_committee_id']??''),'component'=>(string)($r['component_code']??''),'hierarchyLevel'=>(int)($r['hierarchy_level']??1)];
            if(!empty($u['isAdmin']))$x['points']=(float)$r['mata'];
            $records[]=$x;
        }
        $out['searchRecords']=$records;
        $out['searchCommittees']=array_map(static fn($r)=>[
          'id'=>(string)$r['committee_id'],'code'=>(string)($r['kod_manual']??''),'manualRef'=>(string)($r['manual_ref']??''),
          'name'=>(string)$r['nama_jawatankuasa'],'field'=>(string)$r['bidang'],'component'=>(string)($r['component_code']??''),
          'parentId'=>(string)($r['parent_committee_id']??''),'hierarchyLevel'=>(int)($r['hierarchy_level']??1),'sortOrder'=>(int)($r['sort_order']??0)
        ],jt_rows('SELECT * FROM jawatankuasa WHERE aktif=1 AND session_year=? ORDER BY sort_order,manual_ref,nama_jawatankuasa',[$year]));
    }
    return $out;
}
function jt_get_teacher_detail(string $token,string $personId,int $requestedYear=0): array {
    $u=jt_user($token,false);$year=jt_resolve_session_year($u,$requestedYear);$p=jt_one('SELECT * FROM guru WHERE person_id=?',[$personId]);
    if(!$p) throw new RuntimeException('Individu tidak dijumpai.');
    $assign=[];
    foreach(jt_rows('SELECT p.*,j.manual_ref,j.component_code,j.hierarchy_level FROM pelantikan p LEFT JOIN jawatankuasa j ON j.committee_id=p.committee_id WHERE p.person_id=? AND p.session_year=? AND p.aktif=1 ORDER BY j.sort_order,p.bidang,p.jawatankuasa,p.peranan',[$personId,$year]) as $r){
        $x=['assignmentId'=>(string)$r['assignment_id'],'committeeId'=>(string)$r['committee_id'],'committee'=>(string)$r['jawatankuasa'],'role'=>(string)$r['peranan'],'scoreCode'=>(string)$r['kod_skor'],'field'=>(string)$r['bidang'],'type'=>(string)$r['jenis'],'note'=>(string)($r['catatan']??''),
            'portfolioBasis'=>(string)($r['portfolio_basis']??''),'manualRef'=>(string)($r['manual_ref']??''),'component'=>(string)($r['component_code']??''),'hierarchyLevel'=>(int)($r['hierarchy_level']??1)];
        if(!empty($u['isAdmin']))$x['points']=(float)$r['mata'];
        $assign[]=$x;
    }
    $teacher=['personId'=>$personId,'name'=>(string)$p['nama'],'group'=>(string)$p['kumpulan'],'jobTitle'=>(string)$p['jawatan_hakiki'],'email'=>(string)($p['emel']??''),'taskCount'=>count($assign),'sessionYear'=>$year];
    if(!empty($u['isAdmin']))$teacher['totalPoints']=array_sum(array_map(static fn($x)=>(float)($x['points']??0),$assign));
    return ['ok'=>true,'teacher'=>$teacher,'assignments'=>$assign,'session'=>jt_session_row($year)];
}
function jt_get_admin_rules(string $token): array {
    $u=jt_user($token,true);$usage=[];$activeUsage=[];
    foreach(jt_rows('SELECT kod_skor,COUNT(*) c,SUM(CASE WHEN aktif=1 THEN 1 ELSE 0 END) active_c FROM pelantikan GROUP BY kod_skor') as $r){$usage[(string)$r['kod_skor']]=(int)$r['c'];$activeUsage[(string)$r['kod_skor']]=(int)$r['active_c'];}
    $rules=[];foreach(jt_rows('SELECT kod_skor,mata FROM point_rules WHERE aktif=1 ORDER BY mata DESC,kod_skor') as $r)$rules[]=['code'=>(string)$r['kod_skor'],'points'=>(float)$r['mata'],'usageCount'=>$usage[(string)$r['kod_skor']]??0,'activeUsageCount'=>$activeUsage[(string)$r['kod_skor']]??0];
    return ['ok'=>true,'user'=>jt_user_public($u),'rules'=>$rules];
}
function jt_get_admin_teachers(string $token,bool $forceSync): array {
    $u=jt_user($token,true);$sync=$forceSync?jt_sync_roster_from_eke($u,$token):null;
    $teachers=array_map(static fn($r)=>['id'=>(string)$r['person_id'],'name'=>(string)$r['nama'],'jobTitle'=>(string)$r['jawatan_hakiki'],'email'=>(string)($r['emel']??'')],jt_rows("SELECT person_id,nama,jawatan_hakiki,emel FROM guru WHERE kumpulan='Guru' AND aktif=1 ORDER BY nama"));
    return ['ok'=>true,'user'=>jt_user_public($u),'sync'=>$sync,'teachers'=>$teachers];
}
function jt_get_admin_committees(string $token,int $requestedYear=0): array {
    $u=jt_user($token,true);$year=jt_resolve_session_year($u,$requestedYear);$usage=[];$activeUsage=[];
    foreach(jt_rows('SELECT committee_id,COUNT(*) c,SUM(CASE WHEN aktif=1 THEN 1 ELSE 0 END) active_c FROM pelantikan WHERE session_year=? GROUP BY committee_id',[$year]) as $r){$usage[(string)$r['committee_id']]=(int)$r['c'];$activeUsage[(string)$r['committee_id']]=(int)$r['active_c'];}
    $list=[];foreach(jt_rows('SELECT * FROM jawatankuasa WHERE aktif=1 AND session_year=? ORDER BY sort_order,manual_ref,nama_jawatankuasa',[$year]) as $r)$list[]=[
      'id'=>(string)$r['committee_id'],'code'=>(string)($r['kod_manual']??''),'manualRef'=>(string)($r['manual_ref']??''),'name'=>(string)$r['nama_jawatankuasa'],'field'=>(string)$r['bidang'],
      'component'=>(string)($r['component_code']??''),'parentId'=>(string)($r['parent_committee_id']??''),'hierarchyLevel'=>(int)($r['hierarchy_level']??1),'sortOrder'=>(int)($r['sort_order']??0),
      'sourceRef'=>(string)($r['source_ref']??''),'usageCount'=>$usage[(string)$r['committee_id']]??0,'activeUsageCount'=>$activeUsage[(string)$r['committee_id']]??0
    ];
    return ['ok'=>true,'user'=>jt_user_public($u),'year'=>$year,'session'=>jt_session_row($year),'committees'=>$list];
}
function jt_get_admin_data(string $token): array {
    $year=jt_admin_session_year();return ['ok'=>true,'year'=>$year,'rules'=>jt_get_admin_rules($token)['rules'],'teachers'=>jt_get_admin_teachers($token,false)['teachers'],'committees'=>jt_get_admin_committees($token,$year)['committees']];
}
function jt_admin_update_point(string $token,string $code,$points,string $newName): array {
    $u=jt_user($token,true);$code=trim($code);$newName=trim($newName);$points=(float)$points;
    if($code===''||$newName===''||$points<0||$points>100) throw new InvalidArgumentException('Nama jawatan atau mata tidak sah.');
    $old=jt_one('SELECT * FROM point_rules WHERE kod_skor=?',[$code]);if(!$old)throw new RuntimeException('Jawatan tidak dijumpai.');
    if(jt_norm($newName)!==jt_norm($code)&&jt_one('SELECT kod_skor FROM point_rules WHERE UPPER(TRIM(kod_skor))=UPPER(TRIM(?)) LIMIT 1',[$newName])) throw new RuntimeException('Nama jawatan baharu sudah wujud.');
    $pdo=jt_db();$pdo->beginTransaction();
    try{
        $count=(int)(jt_one('SELECT COUNT(*) c FROM pelantikan WHERE peranan=? OR kod_skor=?',[$code,$code])['c']??0);
        jt_exec('UPDATE point_rules SET kod_skor=?,mata=?,sumber=NULL,catatan=NULL WHERE kod_skor=?',[$newName,$points,$code]);
        jt_exec('UPDATE pelantikan SET peranan=?,kod_skor=?,mata=?,updated_at=?,updated_by=? WHERE peranan=? OR kod_skor=?',[$newName,$newName,$points,jt_now(),$u['email'],$code,$newName]);
        $teachers=jt_rebuild_stats();
        jt_audit($u,'UPDATE_POINT_RULE','POINT_RULE',$code,['before'=>['name'=>$code,'points'=>(float)$old['mata']],'after'=>['name'=>$newName,'points'=>$points],'assignmentsUpdated'=>$count]);
        $pdo->commit();
        return ['ok'=>true,'oldName'=>$code,'name'=>$newName,'points'=>$points,'assignmentsUpdated'=>$count,'teachers'=>$teachers];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_delete_point(string $token,string $code): array {
    $u=jt_user($token,true);$code=trim($code);if($code==='')throw new InvalidArgumentException('Nama jawatan diperlukan.');
    $rule=jt_one('SELECT * FROM point_rules WHERE kod_skor=? AND aktif=1',[$code]);if(!$rule)throw new RuntimeException('Jawatan tidak dijumpai atau sudah dipadam.');
    $active=(int)(jt_one('SELECT COUNT(*) c FROM pelantikan WHERE kod_skor=? AND aktif=1',[$code])['c']??0);
    if($active>0)throw new RuntimeException('Jawatan ini masih digunakan oleh '.$active.' pelantikan aktif. Padam atau tukar pelantikan tersebut dahulu.');
    jt_exec('UPDATE point_rules SET aktif=0 WHERE kod_skor=?',[$code]);
    jt_audit($u,'DELETE_POINT_RULE','POINT_RULE',$code,['mode'=>'soft-delete','points'=>(float)$rule['mata']]);
    return ['ok'=>true,'code'=>$code];
}
function jt_admin_update_teacher(string $token,string $personId,string $jobTitle): array {
    $u=jt_user($token,true);$jobTitle=trim($jobTitle);if($jobTitle==='')throw new InvalidArgumentException('Jawatan hakiki diperlukan.');
    $p=jt_one('SELECT jawatan_hakiki FROM guru WHERE person_id=?',[$personId]);if(!$p)throw new RuntimeException('Guru tidak dijumpai.');
    jt_exec('UPDATE guru SET jawatan_hakiki=? WHERE person_id=?',[$jobTitle,$personId]);
    jt_audit($u,'UPDATE_TEACHER','GURU',$personId,['before'=>$p['jawatan_hakiki'],'after'=>$jobTitle]);
    return ['ok'=>true,'personId'=>$personId,'jobTitle'=>$jobTitle];
}
function jt_admin_save_committee(string $token,array $p): array {
    $u=jt_user($token,true);$id=trim((string)($p['id']??$p['committeeId']??''));$name=trim((string)($p['name']??''));$code=trim((string)($p['code']??''));$field=trim((string)($p['field']??''));
    if($name===''||$field==='')throw new InvalidArgumentException('Nama jawatankuasa dan Bidang diperlukan.');
    if(jt_one('SELECT committee_id FROM jawatankuasa WHERE aktif=1 AND UPPER(TRIM(nama_jawatankuasa))=UPPER(TRIM(?)) AND committee_id<>? LIMIT 1',[$name,$id]))throw new RuntimeException('Nama jawatankuasa ini sudah wujud.');
    $pdo=jt_db();$pdo->beginTransaction();
    try{
        if($id!==''){
            $old=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=?',[$id]);if(!$old)throw new RuntimeException('Jawatankuasa tidak dijumpai.');
            $count=(int)(jt_one('SELECT COUNT(*) c FROM pelantikan WHERE committee_id=?',[$id])['c']??0);
            jt_exec('UPDATE jawatankuasa SET kod_manual=?,nama_jawatankuasa=?,bidang=? WHERE committee_id=?',[$code,$name,$field,$id]);
            jt_exec('UPDATE pelantikan SET jawatankuasa=?,bidang=?,updated_at=?,updated_by=? WHERE committee_id=?',[$name,$field,jt_now(),$u['email'],$id]);
            jt_audit($u,'UPDATE_COMMITTEE','JAWATANKUASA',$id,['before'=>$old,'after'=>['CommitteeID'=>$id,'KodManual'=>$code,'NamaJawatankuasa'=>$name,'Bidang'=>$field],'assignmentsUpdated'=>$count]);
            $pdo->commit();return ['ok'=>true,'mode'=>'update','committee'=>['id'=>$id,'code'=>$code,'name'=>$name,'field'=>$field,'usageCount'=>$count],'assignmentsUpdated'=>$count];
        }
        $id=jt_next_id('jawatankuasa','committee_id','J',3);
        jt_exec('INSERT INTO jawatankuasa (committee_id,kod_manual,nama_jawatankuasa,bidang,aktif) VALUES (?,?,?,?,1)',[$id,$code,$name,$field]);
        jt_audit($u,'ADD_COMMITTEE','JAWATANKUASA',$id,['after'=>['CommitteeID'=>$id,'KodManual'=>$code,'NamaJawatankuasa'=>$name,'Bidang'=>$field]]);
        $pdo->commit();return ['ok'=>true,'mode'=>'add','committee'=>['id'=>$id,'code'=>$code,'name'=>$name,'field'=>$field,'usageCount'=>0],'assignmentsUpdated'=>0];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_delete_committee(string $token,string $id): array {
    $u=jt_user($token,true);$id=trim($id);if($id==='')throw new InvalidArgumentException('ID jawatankuasa diperlukan.');
    $row=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=? AND aktif=1',[$id]);if(!$row)throw new RuntimeException('Jawatankuasa tidak dijumpai atau sudah dipadam.');
    $active=(int)(jt_one('SELECT COUNT(*) c FROM pelantikan WHERE committee_id=? AND aktif=1',[$id])['c']??0);
    if($active>0)throw new RuntimeException('Jawatankuasa ini masih mempunyai '.$active.' pelantikan aktif. Padam pelantikan ahli dahulu sebelum memadam jawatankuasa.');
    jt_exec('UPDATE jawatankuasa SET aktif=0 WHERE committee_id=?',[$id]);
    jt_audit($u,'DELETE_COMMITTEE','JAWATANKUASA',$id,['mode'=>'soft-delete','name'=>$row['nama_jawatankuasa']]);
    return ['ok'=>true,'committeeId'=>$id];
}
function jt_admin_save_assignment(string $token,array $p): array {
    $u=jt_user($token,true);
    $personId=trim((string)($p['personId']??''));$committeeId=trim((string)($p['committeeId']??''));$score=trim((string)($p['scoreCode']??''));
    if($personId===''||$committeeId===''||$score==='')throw new InvalidArgumentException('Guru, jawatankuasa dan jawatan diperlukan.');
    $person=jt_one("SELECT * FROM guru WHERE person_id=? AND kumpulan='Guru' AND aktif=1",[$personId]);if(!$person)throw new RuntimeException('Guru tidak dijumpai atau tidak aktif.');
    $comm=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=? AND aktif=1',[$committeeId]);if(!$comm)throw new RuntimeException('Jawatankuasa tidak dijumpai atau tidak aktif.');
    $rule=jt_one('SELECT * FROM point_rules WHERE kod_skor=? AND aktif=1',[$score]);if(!$rule)throw new RuntimeException('Jawatan / nilai mata tidak sah.');
    $id=trim((string)($p['assignmentId']??''));$old=$id!==''?jt_one('SELECT * FROM pelantikan WHERE assignment_id=?',[$id]):null;
    if($id!==''&&!$old)throw new RuntimeException('Pelantikan tidak dijumpai.');if($id==='')$id=jt_next_id('pelantikan','assignment_id','A',4);
    $jenis=$old?(string)$old['jenis']:'Terus';$note=trim((string)($p['note']??''));
    $values=[$person['person_id'],$person['nama'],'Guru',$comm['committee_id'],$comm['nama_jawatankuasa'],$score,$score,$rule['mata'],$comm['bidang'],$jenis,$note,'',0,1,jt_now(),$u['email']];
    $pdo=jt_db();$pdo->beginTransaction();
    try{
        if($old)jt_exec('UPDATE pelantikan SET person_id=?,nama=?,kumpulan=?,committee_id=?,jawatankuasa=?,peranan=?,kod_skor=?,mata=?,bidang=?,jenis=?,catatan=?,mapping_note=?,perlu_semakan=?,aktif=?,updated_at=?,updated_by=? WHERE assignment_id=?',[...$values,$id]);
        else jt_exec('INSERT INTO pelantikan (assignment_id,person_id,nama,kumpulan,committee_id,jawatankuasa,peranan,kod_skor,mata,bidang,jenis,catatan,mapping_note,perlu_semakan,aktif,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,...$values]);
        $teachers=jt_rebuild_stats();
        jt_audit($u,$old?'UPDATE_ASSIGNMENT':'ADD_ASSIGNMENT','PELANTIKAN',$id,['before'=>$old,'after'=>['personId'=>$personId,'committeeId'=>$committeeId,'scoreCode'=>$score]]);
        $pdo->commit();
        return ['ok'=>true,'assignmentId'=>$id,'assignment'=>['assignmentId'=>$id,'committeeId'=>$committeeId,'committee'=>$comm['nama_jawatankuasa'],'role'=>$score,'scoreCode'=>$score,'points'=>(float)$rule['mata'],'field'=>$comm['bidang'],'note'=>$note],'teachers'=>$teachers];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_deactivate_assignment(string $token,string $id): array {
    $u=jt_user($token,true);$old=jt_one('SELECT * FROM pelantikan WHERE assignment_id=?',[$id]);if(!$old)throw new RuntimeException('Pelantikan tidak dijumpai.');
    jt_exec('UPDATE pelantikan SET aktif=0,updated_at=?,updated_by=? WHERE assignment_id=?',[jt_now(),$u['email'],$id]);
    $teachers=jt_rebuild_stats();jt_audit($u,'DEACTIVATE_ASSIGNMENT','PELANTIKAN',$id,['before'=>$old]);
    return ['ok'=>true,'assignmentId'=>$id,'before'=>$old,'teachers'=>$teachers];
}
