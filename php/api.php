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
      case 'jtAdminUpdatePoint': return jt_admin_update_point((string)($a[0]??''),(string)($a[1]??''),$a[2]??0,(string)($a[3]??$a[1]??''),(int)($a[4]??0));
      case 'jtAdminDeletePoint': return jt_admin_delete_point((string)($a[0]??''),(string)($a[1]??''),(int)($a[2]??0));
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
            'portfolioBasis'=>(string)($r['portfolio_basis']??''),'roleTemplateId'=>$r['role_template_id']!==null?(int)$r['role_template_id']:null,'manualRef'=>(string)($r['manual_ref']??''),'component'=>(string)($r['component_code']??''),'hierarchyLevel'=>(int)($r['hierarchy_level']??1)];
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
    $rules=[];foreach(jt_rows('SELECT * FROM point_rules WHERE aktif=1 ORDER BY mata DESC,kod_skor') as $r)$rules[]=[
      'code'=>(string)$r['kod_skor'],'points'=>(float)$r['mata'],
      'recommendedPoints'=>$r['recommended_points']!==null?(float)$r['recommended_points']:null,
      'roleLevel'=>(int)($r['role_level']??6),'roleGroup'=>(string)($r['role_group']??''),
      'sourceRef'=>(string)($r['source_ref']??''),'officialStructure'=>!empty($r['official_structure']),
      'usageCount'=>$usage[(string)$r['kod_skor']]??0,'activeUsageCount'=>$activeUsage[(string)$r['kod_skor']]??0
    ];
    return ['ok'=>true,'user'=>jt_user_public($u),'rules'=>$rules];
}
function jt_get_admin_teachers(string $token,bool $forceSync): array {
    $u=jt_user($token,true);$sync=$forceSync?jt_sync_roster_from_eke($u,$token):null;
    $teachers=array_map(static fn($r)=>[
      'id'=>(string)$r['person_id'],'name'=>(string)$r['nama'],'group'=>(string)$r['kumpulan'],
      'jobTitle'=>(string)$r['jawatan_hakiki'],'email'=>(string)($r['emel']??'')
    ],jt_rows("SELECT person_id,nama,kumpulan,jawatan_hakiki,emel FROM guru WHERE kumpulan IN ('Guru','AKP') AND aktif=1 ORDER BY kumpulan='AKP',nama"));
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
function jt_get_committee_roles(string $token,string $committeeId,int $requestedYear=0): array {
    $u=jt_user($token,true);$year=jt_resolve_session_year($u,$requestedYear);
    $comm=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=? AND session_year=? AND aktif=1',[$committeeId,$year]);
    if(!$comm)throw new RuntimeException('Jawatankuasa tidak dijumpai untuk sesi ini.');
    $templates=jt_rows('SELECT t.*,r.mata default_points,r.recommended_points,r.role_level,r.role_group
      FROM committee_role_templates t LEFT JOIN point_rules r ON r.kod_skor=t.role_code
      WHERE t.session_year=? AND t.committee_id=? AND t.aktif=1 ORDER BY t.hierarchy_rank,t.role_name',[$year,$committeeId]);
    $roles=[];
    foreach($templates as $r)$roles[]=[
      'templateId'=>(int)$r['template_id'],'roleName'=>(string)$r['role_name'],'roleCode'=>(string)$r['role_code'],
      'rank'=>(int)$r['hierarchy_rank'],'min'=>(int)$r['min_count'],'max'=>$r['max_count']!==null?(int)$r['max_count']:null,
      'required'=>!empty($r['required_role']),'points'=>$r['points_override']!==null?(float)$r['points_override']:(float)($r['default_points']??0),
      'recommendedPoints'=>$r['recommended_points']!==null?(float)$r['recommended_points']:null,
      'portfolioBasis'=>(string)($r['portfolio_basis']??''),'sourceType'=>(string)$r['source_type'],'sourceRef'=>(string)($r['source_ref']??'')
    ];
    return ['ok'=>true,'year'=>$year,'committeeId'=>$committeeId,'manualRef'=>(string)($comm['manual_ref']??''),'roles'=>$roles];
}
function jt_get_session_dashboard(string $token,int $requestedYear=0,int $compareYear=0): array {
    $u=jt_user($token,true);$year=jt_resolve_session_year($u,$requestedYear);$stats=jt_teacher_stats($year);
    $count=count($stats);$sum=array_sum(array_column($stats,'points'));$avg=$count?($sum/$count):0.0;
    $highest=$count?max(array_column($stats,'points')):0;$lowNonZero=null;
    foreach($stats as $t)if($t['points']>0&&($lowNonZero===null||$t['points']<$lowNonZero))$lowNonZero=$t['points'];
    $over=array_values(array_filter($stats,static fn($t)=>$avg>0&&$t['points']>$avg*1.20));
    $under=array_values(array_filter($stats,static fn($t)=>$t['points']>0&&$avg>0&&$t['points']<$avg*0.80));
    $zero=array_values(array_filter($stats,static fn($t)=>$t['count']===0));

    $vacancies=jt_rows("SELECT t.template_id,t.committee_id,j.nama_jawatankuasa,j.manual_ref,t.role_name,t.role_code,t.min_count,t.max_count,
      COUNT(p.assignment_id) filled
      FROM committee_role_templates t
      JOIN jawatankuasa j ON j.committee_id=t.committee_id
      LEFT JOIN pelantikan p ON p.session_year=t.session_year AND p.committee_id=t.committee_id AND p.aktif=1 AND (p.role_template_id=t.template_id OR p.peranan=t.role_name)
      WHERE t.session_year=? AND t.aktif=1 AND t.required_role=1 AND j.aktif=1
      GROUP BY t.template_id,t.committee_id,j.nama_jawatankuasa,j.manual_ref,t.role_name,t.role_code,t.min_count,t.max_count
      HAVING filled < t.min_count ORDER BY j.sort_order,j.nama_jawatankuasa,t.hierarchy_rank",[$year]);
    $dupes=jt_rows("SELECT p.person_id,p.nama,p.committee_id,p.jawatankuasa,p.peranan,COUNT(*) duplicate_count
      FROM pelantikan p WHERE p.session_year=? AND p.aktif=1
      GROUP BY p.person_id,p.nama,p.committee_id,p.jawatankuasa,p.peranan HAVING COUNT(*)>1
      ORDER BY duplicate_count DESC,p.nama",[$year]);
    $overCapacity=jt_rows("SELECT t.template_id,t.committee_id,j.nama_jawatankuasa,t.role_name,t.max_count,COUNT(p.assignment_id) filled
      FROM committee_role_templates t JOIN jawatankuasa j ON j.committee_id=t.committee_id
      JOIN pelantikan p ON p.session_year=t.session_year AND p.committee_id=t.committee_id AND p.aktif=1 AND (p.role_template_id=t.template_id OR p.peranan=t.role_name)
      WHERE t.session_year=? AND t.aktif=1 AND t.max_count IS NOT NULL
      GROUP BY t.template_id,t.committee_id,j.nama_jawatankuasa,t.role_name,t.max_count HAVING filled>t.max_count",[$year]);
    $duplicateCommittees=jt_rows("SELECT UPPER(TRIM(nama_jawatankuasa)) normalized_name,MIN(nama_jawatankuasa) name,COUNT(*) duplicate_count
      FROM jawatankuasa WHERE session_year=? AND aktif=1 GROUP BY UPPER(TRIM(nama_jawatankuasa)) HAVING COUNT(*)>1",[$year]);
    $hier=jt_one("SELECT COUNT(*) total,SUM(manual_ref IS NOT NULL AND manual_ref<>'') mapped,SUM(parent_committee_id IS NULL) roots
      FROM jawatankuasa WHERE session_year=? AND aktif=1",[$year]);

    $compare=[];$compareSummary=null;
    if($compareYear>0&&jt_session_row($compareYear)){
      $old=jt_teacher_stats($compareYear);$oldMap=[];foreach($old as $t)$oldMap[$t['id']]=$t;
      foreach($stats as $t){$o=$oldMap[$t['id']]??['points'=>0,'count'=>0];$compare[]=[
        'id'=>$t['id'],'name'=>$t['name'],'points'=>$t['points'],'previousPoints'=>$o['points'],'deltaPoints'=>$t['points']-$o['points'],
        'count'=>$t['count'],'previousCount'=>$o['count'],'deltaCount'=>$t['count']-$o['count']
      ];}
      usort($compare,static fn($a,$b)=>abs($b['deltaPoints'])<=>abs($a['deltaPoints']));
      $compareSummary=['year'=>$compareYear,'changed'=>count(array_filter($compare,static fn($x)=>abs($x['deltaPoints'])>0.0001||$x['deltaCount']!==0))];
    }
    return ['ok'=>true,'year'=>$year,'session'=>jt_session_row($year),
      'summary'=>['teachers'=>$count,'totalPoints'=>$sum,'avgPoints'=>round($avg,2),'highest'=>$highest,'lowestNonZero'=>$lowNonZero,'overloaded'=>count($over),'underloaded'=>count($under),'zeroAssignments'=>count($zero)],
      'overloaded'=>$over,'underloaded'=>$under,'zeroAssignments'=>$zero,'vacancies'=>$vacancies,'duplicates'=>$dupes,'overCapacity'=>$overCapacity,'duplicateCommittees'=>$duplicateCommittees,
      'hierarchy'=>['total'=>(int)($hier['total']??0),'mapped'=>(int)($hier['mapped']??0),'roots'=>(int)($hier['roots']??0)],
      'compare'=>$compare,'compareSummary'=>$compareSummary];
}
function jt_admin_clone_session(string $token,int $sourceYear,int $targetYear): array {
    $u=jt_user($token,true);if($sourceYear<2000||$targetYear<2000||$targetYear>2100||$sourceYear===$targetYear)throw new InvalidArgumentException('Tahun sumber/sasaran tidak sah.');
    if(!jt_session_row($sourceYear))throw new RuntimeException('Sesi sumber tidak dijumpai.');
    if(jt_session_row($targetYear))throw new RuntimeException('Sesi '.$targetYear.' sudah wujud.');
    $pdo=jt_db();$pdo->beginTransaction();
    try{
      jt_exec('INSERT INTO sessions(session_year,label,status,source_year,created_at,created_by) VALUES (?,?,\'DRAFT\',?,NOW(),?)',[$targetYear,'Sesi '.$targetYear,$sourceYear,$u['email']]);
      $maxJ=(int)(jt_one("SELECT MAX(CAST(SUBSTRING(committee_id,2) AS UNSIGNED)) n FROM jawatankuasa WHERE committee_id LIKE 'J%'")['n']??0);
      $maxA=(int)(jt_one("SELECT MAX(CAST(SUBSTRING(assignment_id,2) AS UNSIGNED)) n FROM pelantikan WHERE assignment_id LIKE 'A%'")['n']??0);
      $map=[];$commRows=jt_rows('SELECT * FROM jawatankuasa WHERE session_year=? AND aktif=1 ORDER BY sort_order,committee_id',[$sourceYear]);
      $stmt=$pdo->prepare('INSERT INTO jawatankuasa(committee_id,kod_manual,nama_jawatankuasa,bidang,aktif,session_year,manual_ref,component_code,parent_committee_id,hierarchy_level,sort_order,source_ref) VALUES (?,?,?,?,1,?,?,?,?,?,?,?)');
      foreach($commRows as $r){$new='J'.str_pad((string)(++$maxJ),3,'0',STR_PAD_LEFT);$map[(string)$r['committee_id']]=$new;
        $stmt->execute([$new,$r['kod_manual'],$r['nama_jawatankuasa'],$r['bidang'],$targetYear,$r['manual_ref'],$r['component_code'],null,$r['hierarchy_level'],$r['sort_order'],$r['source_ref']]);}
      foreach($commRows as $r){$oldParent=(string)($r['parent_committee_id']??'');if($oldParent!==''&&isset($map[$oldParent]))jt_exec('UPDATE jawatankuasa SET parent_committee_id=? WHERE committee_id=?',[$map[$oldParent],$map[(string)$r['committee_id']]]);}

      $tplCount=0;$tplStmt=$pdo->prepare('INSERT INTO committee_role_templates(session_year,committee_id,role_name,role_code,hierarchy_rank,min_count,max_count,required_role,points_override,portfolio_basis,source_type,source_ref,notes,aktif) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
      foreach(jt_rows('SELECT * FROM committee_role_templates WHERE session_year=? AND aktif=1 ORDER BY template_id',[$sourceYear]) as $t){$oldC=(string)$t['committee_id'];if(!isset($map[$oldC]))continue;
        $tplStmt->execute([$targetYear,$map[$oldC],$t['role_name'],$t['role_code'],$t['hierarchy_rank'],$t['min_count'],$t['max_count'],$t['required_role'],$t['points_override'],$t['portfolio_basis'],$t['source_type'],$t['source_ref'],$t['notes']]);$tplCount++;}

      $asgCount=0;$asgStmt=$pdo->prepare('INSERT INTO pelantikan(assignment_id,person_id,nama,kumpulan,committee_id,jawatankuasa,peranan,kod_skor,mata,bidang,jenis,catatan,mapping_note,perlu_semakan,aktif,updated_at,updated_by,session_year,role_template_id,portfolio_basis,source_assignment_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?,?,?)');
      foreach(jt_rows('SELECT * FROM pelantikan WHERE session_year=? AND aktif=1 ORDER BY assignment_id',[$sourceYear]) as $r){$oldC=(string)$r['committee_id'];if(!isset($map[$oldC]))continue;$newA='A'.str_pad((string)(++$maxA),4,'0',STR_PAD_LEFT);
        $newC=$map[$oldC];$newComm=jt_one('SELECT nama_jawatankuasa,bidang FROM jawatankuasa WHERE committee_id=?',[$newC]);
        $asgStmt->execute([$newA,$r['person_id'],$r['nama'],$r['kumpulan'],$newC,$newComm['nama_jawatankuasa'],$r['peranan'],$r['kod_skor'],$r['mata'],$newComm['bidang'],$r['jenis'],$r['catatan'],'',0,jt_now(),$u['email'],$targetYear,null,$r['portfolio_basis']??null,$r['assignment_id']]);$asgCount++;}
      jt_audit($u,'CLONE_SESSION','SESSION',(string)$targetYear,['sourceYear'=>$sourceYear,'committees'=>count($commRows),'assignments'=>$asgCount,'roleTemplates'=>$tplCount]);
      $pdo->commit();
      return ['ok'=>true,'sourceYear'=>$sourceYear,'targetYear'=>$targetYear,'status'=>'DRAFT','committees'=>count($commRows),'assignments'=>$asgCount,'roleTemplates'=>$tplCount];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_set_session_status(string $token,int $year,string $status): array {
    $u=jt_user($token,true);$status=strtoupper(trim($status));if(!in_array($status,['DRAFT','REVIEW','PUBLISHED','ARCHIVED'],true))throw new InvalidArgumentException('Status sesi tidak sah.');
    $old=jt_session_row($year);if(!$old)throw new RuntimeException('Sesi tidak dijumpai.');
    $pdo=jt_db();$pdo->beginTransaction();
    try{
      if($status==='PUBLISHED'){
        jt_exec("UPDATE sessions SET status='ARCHIVED',archived_at=NOW(),archived_by=? WHERE status='PUBLISHED' AND session_year<>?",[$u['email'],$year]);
        jt_exec("UPDATE sessions SET status='PUBLISHED',published_at=NOW(),published_by=? WHERE session_year=?",[$u['email'],$year]);
      }elseif($status==='ARCHIVED')jt_exec("UPDATE sessions SET status='ARCHIVED',archived_at=NOW(),archived_by=? WHERE session_year=?",[$u['email'],$year]);
      elseif($status==='REVIEW')jt_exec("UPDATE sessions SET status='REVIEW',reviewed_at=NOW(),reviewed_by=? WHERE session_year=?",[$u['email'],$year]);
      else jt_exec("UPDATE sessions SET status='DRAFT' WHERE session_year=?",[$year]);
      jt_audit($u,'SET_SESSION_STATUS','SESSION',(string)$year,['before'=>$old['status'],'after'=>$status]);$pdo->commit();
      return ['ok'=>true,'session'=>jt_session_row($year)];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_bulk_reassign(string $token,array $assignmentIds,string $targetPersonId,int $requestedYear=0): array {
    $u=jt_user($token,true);$year=jt_resolve_session_year($u,$requestedYear);jt_assert_session_editable($year);
    $target=jt_one("SELECT * FROM guru WHERE person_id=? AND kumpulan IN ('Guru','AKP') AND aktif=1",[$targetPersonId]);if(!$target)throw new RuntimeException('Penerima tidak dijumpai atau tidak aktif.');
    $ids=array_values(array_unique(array_filter(array_map('strval',$assignmentIds))));if(!$ids)throw new InvalidArgumentException('Pilih sekurang-kurangnya satu pelantikan.');
    $moved=0;$skipped=0;$pdo=jt_db();$pdo->beginTransaction();
    try{
      foreach($ids as $id){$r=jt_one('SELECT * FROM pelantikan WHERE assignment_id=? AND session_year=? AND aktif=1',[$id,$year]);if(!$r){$skipped++;continue;}
        $dup=jt_one('SELECT assignment_id FROM pelantikan WHERE session_year=? AND person_id=? AND committee_id=? AND peranan=? AND aktif=1 AND assignment_id<>? LIMIT 1',[$year,$targetPersonId,$r['committee_id'],$r['peranan'],$id]);
        if($dup){$skipped++;continue;}
        jt_exec('UPDATE pelantikan SET person_id=?,nama=?,kumpulan=?,updated_at=?,updated_by=? WHERE assignment_id=?',[$target['person_id'],$target['nama'],$target['kumpulan'],jt_now(),$u['email'],$id]);$moved++;}
      jt_audit($u,'BULK_REASSIGN','PELANTIKAN',(string)$year,['targetPersonId'=>$targetPersonId,'moved'=>$moved,'skipped'=>$skipped,'ids'=>$ids]);$pdo->commit();
      return ['ok'=>true,'moved'=>$moved,'skipped'=>$skipped,'teachers'=>jt_rebuild_stats($year)];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_bulk_deactivate(string $token,array $assignmentIds,int $requestedYear=0): array {
    $u=jt_user($token,true);$year=jt_resolve_session_year($u,$requestedYear);jt_assert_session_editable($year);
    $ids=array_values(array_unique(array_filter(array_map('strval',$assignmentIds))));if(!$ids)throw new InvalidArgumentException('Pilih sekurang-kurangnya satu pelantikan.');
    $count=0;$pdo=jt_db();$pdo->beginTransaction();
    try{foreach($ids as $id){$r=jt_one('SELECT assignment_id FROM pelantikan WHERE assignment_id=? AND session_year=? AND aktif=1',[$id,$year]);if(!$r)continue;jt_exec('UPDATE pelantikan SET aktif=0,updated_at=?,updated_by=? WHERE assignment_id=?',[jt_now(),$u['email'],$id]);$count++;}
      jt_audit($u,'BULK_DEACTIVATE','PELANTIKAN',(string)$year,['count'=>$count,'ids'=>$ids]);$pdo->commit();return ['ok'=>true,'count'=>$count,'teachers'=>jt_rebuild_stats($year)];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_update_point(string $token,string $code,$points,string $newName,int $requestedYear=0): array {
    $u=jt_user($token,true);$year=jt_resolve_session_year($u,$requestedYear);jt_assert_session_editable($year);$code=trim($code);$newName=trim($newName);$points=(float)$points;
    if($code===''||$newName===''||$points<0||$points>100) throw new InvalidArgumentException('Nama jawatan atau mata tidak sah.');
    $old=jt_one('SELECT * FROM point_rules WHERE kod_skor=?',[$code]);if(!$old)throw new RuntimeException('Jawatan tidak dijumpai.');
    $pdo=jt_db();$pdo->beginTransaction();
    try{
      $count=(int)(jt_one("SELECT COUNT(*) c FROM pelantikan p WHERE (p.peranan=? OR p.kod_skor=?) AND p.aktif=1 AND p.session_year=?",[$code,$code,$year])['c']??0);
      if(jt_norm($newName)!==jt_norm($code)){
        if(jt_one('SELECT kod_skor FROM point_rules WHERE UPPER(TRIM(kod_skor))=UPPER(TRIM(?)) LIMIT 1',[$newName]))throw new RuntimeException('Nama jawatan baharu sudah wujud.');
        jt_exec('INSERT INTO point_rules(kod_skor,mata,sumber,catatan,aktif,recommended_points,role_level,role_group,source_ref,official_structure) VALUES (?,?,?,?,1,?,?,?,?,?)',[
          $newName,$points,$old['sumber'],$old['catatan'],$old['recommended_points'],$old['role_level'],$old['role_group'],$old['source_ref'],$old['official_structure']]);
        jt_exec("UPDATE pelantikan SET peranan=?,kod_skor=?,mata=?,updated_at=?,updated_by=? WHERE (peranan=? OR kod_skor=?) AND session_year=?",[$newName,$newName,$points,jt_now(),$u['email'],$code,$code,$year]);
        jt_exec("UPDATE committee_role_templates SET role_name=?,role_code=? WHERE role_code=? AND session_year=?",[$newName,$newName,$code,$year]);
        jt_exec('UPDATE point_rules SET aktif=0 WHERE kod_skor=?',[$code]);
      }else{
        jt_exec('UPDATE point_rules SET mata=?,aktif=1 WHERE kod_skor=?',[$points,$code]);
        jt_exec("UPDATE pelantikan SET mata=?,updated_at=?,updated_by=? WHERE kod_skor=? AND session_year=?",[$points,jt_now(),$u['email'],$code,$year]);
      }
      jt_audit($u,'UPDATE_POINT_RULE','POINT_RULE',$code,['before'=>['name'=>$code,'points'=>(float)$old['mata']],'after'=>['name'=>$newName,'points'=>$points],'draftAssignmentsUpdated'=>$count]);$pdo->commit();
      $year=jt_admin_session_year();return ['ok'=>true,'oldName'=>$code,'name'=>$newName,'points'=>$points,'assignmentsUpdated'=>$count,'teachers'=>jt_teacher_stats($year)];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_delete_point(string $token,string $code,int $requestedYear=0): array {
    $u=jt_user($token,true);$year=jt_resolve_session_year($u,$requestedYear);jt_assert_session_editable($year);$code=trim($code);if($code==='')throw new InvalidArgumentException('Nama jawatan diperlukan.');
    $rule=jt_one('SELECT * FROM point_rules WHERE kod_skor=? AND aktif=1',[$code]);if(!$rule)throw new RuntimeException('Jawatan tidak dijumpai atau sudah dipadam.');
    $active=(int)(jt_one("SELECT COUNT(*) c FROM pelantikan WHERE kod_skor=? AND aktif=1 AND session_year=?",[$code,$year])['c']??0);
    $templates=(int)(jt_one("SELECT COUNT(*) c FROM committee_role_templates WHERE role_code=? AND aktif=1 AND session_year=?",[$code,$year])['c']??0);
    if($active>0||$templates>0)throw new RuntimeException('Jawatan ini masih digunakan dalam sesi DRAFT/REVIEW ('.$active.' pelantikan, '.$templates.' template). Ubah struktur dahulu.');
    jt_exec('UPDATE point_rules SET aktif=0 WHERE kod_skor=?',[$code]);jt_audit($u,'DELETE_POINT_RULE','POINT_RULE',$code,['mode'=>'soft-delete','points'=>(float)$rule['mata']]);return ['ok'=>true,'code'=>$code];
}
function jt_admin_update_teacher(string $token,string $personId,string $jobTitle): array {
    $u=jt_user($token,true);$jobTitle=trim($jobTitle);if($jobTitle==='')throw new InvalidArgumentException('Jawatan hakiki diperlukan.');
    $p=jt_one('SELECT jawatan_hakiki FROM guru WHERE person_id=?',[$personId]);if(!$p)throw new RuntimeException('Guru tidak dijumpai.');
    jt_exec('UPDATE guru SET jawatan_hakiki=? WHERE person_id=?',[$jobTitle,$personId]);jt_audit($u,'UPDATE_TEACHER','GURU',$personId,['before'=>$p['jawatan_hakiki'],'after'=>$jobTitle]);return ['ok'=>true,'personId'=>$personId,'jobTitle'=>$jobTitle];
}
function jt_admin_save_committee(string $token,array $p): array {
    $u=jt_user($token,true);$year=(int)($p['sessionYear']??jt_admin_session_year());jt_assert_session_editable($year);
    $id=trim((string)($p['id']??$p['committeeId']??''));$name=trim((string)($p['name']??''));$code=trim((string)($p['code']??''));$field=trim((string)($p['field']??''));
    $manualRef=trim((string)($p['manualRef']??''));$component=trim((string)($p['component']??''));$parentId=trim((string)($p['parentId']??''));$sortOrder=(int)($p['sortOrder']??0);
    if($name===''||$field==='')throw new InvalidArgumentException('Nama jawatankuasa dan Bidang diperlukan.');
    if(jt_one('SELECT committee_id FROM jawatankuasa WHERE session_year=? AND aktif=1 AND UPPER(TRIM(nama_jawatankuasa))=UPPER(TRIM(?)) AND committee_id<>? LIMIT 1',[$year,$name,$id]))throw new RuntimeException('Nama jawatankuasa ini sudah wujud dalam sesi '.$year.'.');
    $level=1;if($parentId!==''){$parent=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=? AND session_year=? AND aktif=1',[$parentId,$year]);if(!$parent)throw new RuntimeException('Jawatankuasa induk tidak sah.');if($parentId===$id)throw new RuntimeException('Jawatankuasa tidak boleh menjadi induk kepada dirinya sendiri.');$level=(int)$parent['hierarchy_level']+1;}
    $pdo=jt_db();$pdo->beginTransaction();
    try{
      if($id!==''){
        $old=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=? AND session_year=?',[$id,$year]);if(!$old)throw new RuntimeException('Jawatankuasa tidak dijumpai untuk sesi ini.');
        $count=(int)(jt_one('SELECT COUNT(*) c FROM pelantikan WHERE committee_id=? AND session_year=?',[$id,$year])['c']??0);
        jt_exec('UPDATE jawatankuasa SET kod_manual=?,nama_jawatankuasa=?,bidang=?,manual_ref=?,component_code=?,parent_committee_id=?,hierarchy_level=?,sort_order=? WHERE committee_id=?',[$code,$name,$field,$manualRef?:null,$component?:null,$parentId?:null,$level,$sortOrder,$id]);
        jt_exec('UPDATE pelantikan SET jawatankuasa=?,bidang=?,updated_at=?,updated_by=? WHERE committee_id=? AND session_year=?',[$name,$field,jt_now(),$u['email'],$id,$year]);
        jt_audit($u,'UPDATE_COMMITTEE','JAWATANKUASA',$id,['year'=>$year,'before'=>$old,'after'=>['name'=>$name,'field'=>$field,'manualRef'=>$manualRef,'parentId'=>$parentId],'assignmentsUpdated'=>$count]);
        $pdo->commit();return ['ok'=>true,'mode'=>'update','committee'=>['id'=>$id,'code'=>$code,'manualRef'=>$manualRef,'name'=>$name,'field'=>$field,'component'=>$component,'parentId'=>$parentId,'hierarchyLevel'=>$level,'sortOrder'=>$sortOrder,'usageCount'=>$count],'assignmentsUpdated'=>$count];
      }
      $id=jt_next_id('jawatankuasa','committee_id','J',3);
      jt_exec('INSERT INTO jawatankuasa(committee_id,kod_manual,nama_jawatankuasa,bidang,aktif,session_year,manual_ref,component_code,parent_committee_id,hierarchy_level,sort_order) VALUES (?,?,?,?,1,?,?,?,?,?,?)',[$id,$code,$name,$field,$year,$manualRef?:null,$component?:null,$parentId?:null,$level,$sortOrder]);
      jt_audit($u,'ADD_COMMITTEE','JAWATANKUASA',$id,['year'=>$year,'name'=>$name,'manualRef'=>$manualRef]);
      $pdo->commit();return ['ok'=>true,'mode'=>'add','committee'=>['id'=>$id,'code'=>$code,'manualRef'=>$manualRef,'name'=>$name,'field'=>$field,'component'=>$component,'parentId'=>$parentId,'hierarchyLevel'=>$level,'sortOrder'=>$sortOrder,'usageCount'=>0],'assignmentsUpdated'=>0];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_delete_committee(string $token,string $id): array {
    $u=jt_user($token,true);$id=trim($id);if($id==='')throw new InvalidArgumentException('ID jawatankuasa diperlukan.');
    $row=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=? AND aktif=1',[$id]);if(!$row)throw new RuntimeException('Jawatankuasa tidak dijumpai atau sudah dipadam.');
    $year=(int)$row['session_year'];jt_assert_session_editable($year);
    $active=(int)(jt_one('SELECT COUNT(*) c FROM pelantikan WHERE committee_id=? AND session_year=? AND aktif=1',[$id,$year])['c']??0);
    $children=(int)(jt_one('SELECT COUNT(*) c FROM jawatankuasa WHERE parent_committee_id=? AND session_year=? AND aktif=1',[$id,$year])['c']??0);
    if($active>0||$children>0)throw new RuntimeException('Jawatankuasa ini masih mempunyai '.$active.' pelantikan aktif dan '.$children.' sub-jawatankuasa. Selesaikan dahulu sebelum memadam.');
    jt_exec('UPDATE jawatankuasa SET aktif=0 WHERE committee_id=?',[$id]);jt_exec('UPDATE committee_role_templates SET aktif=0 WHERE committee_id=?',[$id]);
    jt_audit($u,'DELETE_COMMITTEE','JAWATANKUASA',$id,['year'=>$year,'mode'=>'soft-delete','name'=>$row['nama_jawatankuasa']]);return ['ok'=>true,'committeeId'=>$id,'year'=>$year];
}
function jt_admin_save_assignment(string $token,array $p): array {
    $u=jt_user($token,true);$personId=trim((string)($p['personId']??''));$committeeId=trim((string)($p['committeeId']??''));$score=trim((string)($p['scoreCode']??$p['roleCode']??''));$templateId=(int)($p['roleTemplateId']??0);
    if($personId===''||$committeeId===''||($score===''&&$templateId<=0))throw new InvalidArgumentException('Individu, jawatankuasa dan jawatan diperlukan.');
    $person=jt_one("SELECT * FROM guru WHERE person_id=? AND kumpulan IN ('Guru','AKP') AND aktif=1",[$personId]);if(!$person)throw new RuntimeException('Individu tidak dijumpai atau tidak aktif.');
    $comm=jt_one('SELECT * FROM jawatankuasa WHERE committee_id=? AND aktif=1',[$committeeId]);if(!$comm)throw new RuntimeException('Jawatankuasa tidak dijumpai atau tidak aktif.');
    $year=(int)($p['sessionYear']??$comm['session_year']);if($year!==(int)$comm['session_year'])throw new RuntimeException('Sesi jawatankuasa tidak sepadan.');jt_assert_session_editable($year);
    $tpl=$templateId>0?jt_one('SELECT * FROM committee_role_templates WHERE template_id=? AND session_year=? AND committee_id=? AND aktif=1',[$templateId,$year,$committeeId]):null;
    if(!$tpl&&$score!=='')$tpl=jt_one('SELECT * FROM committee_role_templates WHERE session_year=? AND committee_id=? AND role_code=? AND aktif=1 ORDER BY hierarchy_rank,template_id LIMIT 1',[$year,$committeeId,$score]);
    if($tpl)$score=(string)$tpl['role_code'];
    $rule=jt_one('SELECT * FROM point_rules WHERE kod_skor=? AND aktif=1',[$score]);if(!$rule)throw new RuntimeException('Kategori nilai mata tidak sah.');
    $roleName=$tpl?(string)$tpl['role_name']:$score;
    $points=$tpl&&$tpl['points_override']!==null?(float)$tpl['points_override']:(float)$rule['mata'];$portfolio=trim((string)($p['portfolioBasis']??($tpl['portfolio_basis']??'')));
    $id=trim((string)($p['assignmentId']??''));$old=$id!==''?jt_one('SELECT * FROM pelantikan WHERE assignment_id=?',[$id]):null;
    if($id!==''&&!$old)throw new RuntimeException('Pelantikan tidak dijumpai.');if($old&&(int)$old['session_year']!==$year)throw new RuntimeException('Pelantikan berada dalam sesi lain.');
    if($id===''&&jt_one('SELECT assignment_id FROM pelantikan WHERE session_year=? AND person_id=? AND committee_id=? AND peranan=? AND aktif=1 LIMIT 1',[$year,$personId,$committeeId,$roleName]))throw new RuntimeException('Pelantikan yang sama sudah wujud.');
    if($id==='')$id=jt_next_id('pelantikan','assignment_id','A',4);
    $jenis=$old?(string)$old['jenis']:'Terus';$note=trim((string)($p['note']??''));
    $pdo=jt_db();$pdo->beginTransaction();
    try{
      if($old)jt_exec('UPDATE pelantikan SET person_id=?,nama=?,kumpulan=?,committee_id=?,jawatankuasa=?,peranan=?,kod_skor=?,mata=?,bidang=?,jenis=?,catatan=?,mapping_note=?,perlu_semakan=0,aktif=1,updated_at=?,updated_by=?,session_year=?,role_template_id=?,portfolio_basis=? WHERE assignment_id=?',
        [$person['person_id'],$person['nama'],$person['kumpulan'],$comm['committee_id'],$comm['nama_jawatankuasa'],$roleName,$score,$points,$comm['bidang'],$jenis,$note,'',jt_now(),$u['email'],$year,$tpl['template_id']??null,$portfolio?:null,$id]);
      else jt_exec('INSERT INTO pelantikan(assignment_id,person_id,nama,kumpulan,committee_id,jawatankuasa,peranan,kod_skor,mata,bidang,jenis,catatan,mapping_note,perlu_semakan,aktif,updated_at,updated_by,session_year,role_template_id,portfolio_basis) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,1,?,?,?,?,?)',
        [$id,$person['person_id'],$person['nama'],$person['kumpulan'],$comm['committee_id'],$comm['nama_jawatankuasa'],$roleName,$score,$points,$comm['bidang'],$jenis,$note,'',jt_now(),$u['email'],$year,$tpl['template_id']??null,$portfolio?:null]);
      $teachers=jt_rebuild_stats($year);jt_audit($u,$old?'UPDATE_ASSIGNMENT':'ADD_ASSIGNMENT','PELANTIKAN',$id,['year'=>$year,'before'=>$old,'after'=>['personId'=>$personId,'committeeId'=>$committeeId,'role'=>$roleName,'scoreCode'=>$score,'points'=>$points]]);
      $pdo->commit();return ['ok'=>true,'assignmentId'=>$id,'assignment'=>['assignmentId'=>$id,'committeeId'=>$committeeId,'committee'=>$comm['nama_jawatankuasa'],'role'=>$roleName,'scoreCode'=>$score,'points'=>$points,'field'=>$comm['bidang'],'note'=>$note,'portfolioBasis'=>$portfolio],'teachers'=>$teachers];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function jt_admin_deactivate_assignment(string $token,string $id): array {
    $u=jt_user($token,true);$old=jt_one('SELECT * FROM pelantikan WHERE assignment_id=?',[$id]);if(!$old)throw new RuntimeException('Pelantikan tidak dijumpai.');
    $year=(int)$old['session_year'];jt_assert_session_editable($year);jt_exec('UPDATE pelantikan SET aktif=0,updated_at=?,updated_by=? WHERE assignment_id=?',[jt_now(),$u['email'],$id]);
    $teachers=jt_rebuild_stats($year);jt_audit($u,'DEACTIVATE_ASSIGNMENT','PELANTIKAN',$id,['year'=>$year,'before'=>$old]);return ['ok'=>true,'assignmentId'=>$id,'before'=>$old,'teachers'=>$teachers];
}
