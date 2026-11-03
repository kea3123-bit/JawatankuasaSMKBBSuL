<?php
declare(strict_types=1);

function jt_cfg(): array {
    static $cfg=null;
    if ($cfg!==null) return $cfg;
    $path=__DIR__.'/config.local.php';
    if (!is_file($path)) throw new RuntimeException('php/config.local.php belum diwujudkan pada hosting.');
    $cfg=require $path;
    if (!is_array($cfg)) throw new RuntimeException('Konfigurasi MySQL tidak sah.');
    return $cfg;
}
function jt_db(): PDO {
    static $pdo=null;
    if ($pdo instanceof PDO) return $pdo;
    $c=jt_cfg();
    $host=(string)($c['host']??'localhost'); $port=(int)($c['port']??3306);
    $db=(string)($c['database']??''); $user=(string)($c['username']??''); $pass=(string)($c['password']??'');
    if ($db===''||$user==='') throw new RuntimeException('Nama database / pengguna MySQL belum ditetapkan.');
    $pdo=new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $pdo;
}
function jt_rows(string $sql,array $params=[]): array {$s=jt_db()->prepare($sql);$s->execute($params);return $s->fetchAll();}
function jt_one(string $sql,array $params=[]): ?array {$s=jt_db()->prepare($sql);$s->execute($params);$r=$s->fetch();return $r===false?null:$r;}
function jt_exec(string $sql,array $params=[]): int {$s=jt_db()->prepare($sql);$s->execute($params);return $s->rowCount();}
function jt_norm($v): string {
    $s=strtoupper(trim((string)$v));
    $s=preg_replace('/\b(?:BT|BTE|BINTE)\b/u','BINTI',$s)??$s;
    $s=preg_replace('/\s+/u',' ',$s)??$s;
    return trim($s);
}
function jt_now(): string {return date('Y-m-d H:i:s');}

function jt_external_rpc(string $url,string $fn,array $args): array {
    if (!preg_match('#^https://#i',$url)) throw new RuntimeException('URL API eKeberadaan mesti HTTPS.');
    $payload=json_encode(['fn'=>$fn,'args'=>$args],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if ($payload===false) throw new RuntimeException('Gagal membina permintaan API.');
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5]);
        $body=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if ($body===false) throw new RuntimeException('Tidak dapat hubungi API eKeberadaan: '.$err);
    } else {
        $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>$payload,'timeout'=>15,'ignore_errors'=>true]]);
        $body=@file_get_contents($url,false,$ctx);$status=0;
        if (isset($http_response_header[0])&&preg_match('/\s(\d{3})\s/',$http_response_header[0],$m)) $status=(int)$m[1];
        if ($body===false) throw new RuntimeException('Tidak dapat hubungi API eKeberadaan.');
    }
    $obj=json_decode((string)$body,true);
    if (!is_array($obj)) throw new RuntimeException('Respons API eKeberadaan bukan JSON.');
    if ($status>=400||empty($obj['ok'])) throw new RuntimeException((string)($obj['error']??'Pengesahan eKeberadaan gagal.'));
    return is_array($obj['result']??null)?$obj['result']:[];
}
function jt_eke_rpc(string $fn,array $args): array {
    return jt_external_rpc((string)(jt_cfg()['eke_api_url']??'https://ppdkmy.edu.my/ekeberadaansmkbbsul/api.php'),$fn,$args);
}
function jt_user(string $token,bool $admin=false): array {
    if ($token==='') throw new RuntimeException('Sesi tidak sah. Sila log masuk semula.');
    $r=jt_eke_rpc('resumeSession',[$token,'',[]]); $u=$r['boot']['user']??null;
    if (!is_array($u)||empty($u['email'])) throw new RuntimeException('Sesi eKeberadaan tidak sah.');
    if (!empty($u['mustChangePassword'])) throw new RuntimeException('PIN sementara mesti ditukar dahulu dalam eKeberadaan sebelum membuka Jawatankuasa.');
    $isManagement=jt_norm($u['category']??'')==='PENGURUSAN'; $isSystemAdmin=!empty($u['isAdmin']);
    $u['isAdmin']=$isManagement||$isSystemAdmin;
    $u['adminSource']=$isManagement&&$isSystemAdmin?'Pengurusan + Pentadbir Sistem':($isSystemAdmin?'Pentadbir Sistem':($isManagement?'Pengurusan':''));
    if ($admin&&!$u['isAdmin']) throw new RuntimeException('Akses pentadbir tidak dibenarkan.');
    return $u;
}
function jt_audit(array $u,string $action,string $targetType,string $targetId,$details=[]): void {
    try {
        jt_exec('INSERT INTO audit_log (event_at,email,nama,action,target_type,target_id,details) VALUES (?,?,?,?,?,?,?)',[
            jt_now(),(string)($u['email']??''),(string)($u['name']??''),$action,$targetType,$targetId,
            is_string($details)?$details:json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        ]);
    } catch(Throwable $ignored) {}
}
function jt_settings(): array {$out=[];foreach(jt_rows('SELECT setting_key,setting_value FROM settings') as $r)$out[(string)$r['setting_key']]=$r['setting_value'];return $out;}
function jt_rule_map(): array {$out=[];foreach(jt_rows('SELECT kod_skor,mata FROM point_rules WHERE aktif=1') as $r)$out[(string)$r['kod_skor']]=(float)$r['mata'];return $out;}
function jt_teacher_stats(): array {
    $rows=jt_rows("SELECT g.person_id,g.nama,g.jawatan_hakiki,g.emel,COALESCE(x.points,0) points,COALESCE(x.cnt,0) cnt
      FROM guru g LEFT JOIN (
        SELECT p.person_id,SUM(COALESCE(r.mata,0)) points,COUNT(*) cnt
        FROM pelantikan p LEFT JOIN point_rules r ON r.kod_skor=p.kod_skor AND r.aktif=1
        WHERE p.aktif=1 GROUP BY p.person_id
      ) x ON x.person_id=g.person_id
      WHERE g.kumpulan='Guru' AND g.aktif=1 ORDER BY g.nama");
    return array_map(static fn($r)=>[
        'id'=>(string)$r['person_id'],'name'=>(string)$r['nama'],'jobTitle'=>(string)$r['jawatan_hakiki'],
        'email'=>(string)($r['emel']??''),'points'=>(float)$r['points'],'count'=>(int)$r['cnt']
    ],$rows);
}
function jt_rebuild_stats(): array {
    jt_exec("UPDATE guru g LEFT JOIN (
      SELECT p.person_id,SUM(COALESCE(r.mata,0)) total_mata,COUNT(*) bil_tugas
      FROM pelantikan p LEFT JOIN point_rules r ON r.kod_skor=p.kod_skor AND r.aktif=1
      WHERE p.aktif=1 GROUP BY p.person_id
    ) x ON x.person_id=g.person_id
    SET g.total_mata=COALESCE(x.total_mata,0),g.bil_tugas=COALESCE(x.bil_tugas,0)");
    return jt_teacher_stats();
}
function jt_next_id(string $table,string $column,string $prefix,int $pad): string {
    if (!preg_match('/^[a-z_]+$/',$table)||!preg_match('/^[a-z_]+$/',$column)) throw new RuntimeException('ID config tidak sah.');
    $r=jt_one("SELECT MAX(CAST(SUBSTRING($column,2) AS UNSIGNED)) n FROM $table WHERE $column LIKE ?",[$prefix.'%']);
    return $prefix.str_pad((string)(((int)($r['n']??0))+1),$pad,'0',STR_PAD_LEFT);
}
function jt_sync_roster_from_eke(array $auth,string $token): array {
    try {$d=jt_eke_rpc('getAdminData',[$token,date('Y-m-d')]);}
    catch(Throwable $e){throw new RuntimeException('Sync eKeberadaan memerlukan akaun Pentadbir Sistem eKeberadaan: '.$e->getMessage());}
    $source=array_values(array_filter(is_array($d['users']??null)?$d['users']:[],static function($u){
        $cat=jt_norm($u['category']??''); return !empty($u['active'])&&in_array($cat,['PENGURUSAN','PPP'],true)&&trim((string)($u['name']??''))!=='';
    }));
    $existing=jt_rows("SELECT * FROM guru WHERE kumpulan='Guru'");$byEmail=[];$byName=[];$max=0;
    foreach($existing as $r){$id=(string)$r['person_id'];if(preg_match('/(\d+)/',$id,$m))$max=max($max,(int)$m[1]);$mail=strtolower(trim((string)($r['emel']??'')));if($mail!=='')$byEmail[$mail]=$r;$byName[jt_norm($r['normalized_name']?:$r['nama'])]=$r;}
    $seen=[];$added=0;$updated=0;$deactivated=0;
    foreach($source as $u){
        $email=strtolower(trim((string)($u['email']??'')));$canon=jt_norm($u['name']??'');
        $r=$email!==''&&isset($byEmail[$email])?$byEmail[$email]:($byName[$canon]??null);
        if(!$r){
            $person='P'.str_pad((string)(++$max),3,'0',STR_PAD_LEFT);
            jt_exec('INSERT INTO guru (person_id,nama,kumpulan,jawatan_hakiki,emel,kategori_login,aktif,normalized_name,catatan,total_mata,bil_tugas) VALUES (?,?,?,?,?,?,1,?,?,0,0)',[$person,(string)$u['name'],'Guru',(string)($u['jobTitle']??'')?:'Guru Akademik Biasa',$email,(string)($u['category']??'PPP'),$canon,'Auto sync daripada eKeberadaan MySQL']);
            $seen[$person]=true;$added++;continue;
        }
        $person=(string)$r['person_id'];$seen[$person]=true;
        $job=trim((string)($r['jawatan_hakiki']??''))!==''?(string)$r['jawatan_hakiki']:((string)($u['jobTitle']??'')?:'Guru Akademik Biasa');
        jt_exec('UPDATE guru SET nama=?,jawatan_hakiki=?,emel=?,kategori_login=?,aktif=1,normalized_name=? WHERE person_id=?',[(string)$u['name'],$job,$email,(string)($u['category']??'PPP'),$canon,$person]);$updated++;
    }
    foreach($existing as $r){$id=(string)$r['person_id'];if(!isset($seen[$id])&&(int)$r['aktif']===1){jt_exec('UPDATE guru SET aktif=0 WHERE person_id=?',[$id]);$deactivated++;}}
    jt_audit($auth,'SYNC_GURU_EKEBERADAAN','GURU','ROSTER',['sourceActive'=>count($source),'added'=>$added,'updated'=>$updated,'deactivated'=>$deactivated]);
    return ['sourceActive'=>count($source),'added'=>$added,'updated'=>$updated,'deactivated'=>$deactivated,'syncedAt'=>jt_now()];
}
function jt_db_public_error(PDOException $e): string {
    $driver=(int)($e->errorInfo[1]??0);
    switch($driver){
      case 1044: case 1045:return 'MySQL menolak sambungan: semak username, password dan privileges dalam php/config.local.php.';
      case 1049:return 'Database Jawatankuasa tidak dijumpai.';
      case 1146:return 'Jadual MySQL belum diimport. Import fail JawatankuasaSMKBBSL_mysql.sql.';
      case 1054:return 'Struktur MySQL belum sama dengan schema terbaru.';
      case 2002: case 2003: case 2005:return 'Tidak dapat sambung MySQL. Untuk PHP di cPanel yang sama, gunakan host=localhost.';
      default:return 'Ralat operasi MySQL. Semak phpMyAdmin dan PHP error_log. (Kod MySQL: '.($driver?:'unknown').')';
    }
}
