/**
 * JawatankuasaSMKBBSuL — Committee / workload API
 * Add this file to the existing eKeberadaan Apps Script project.
 * It intentionally reuses requireSessionUser_() from eKeberadaan.
 */
const JT_DB_ID = '1T4FRkd5IqnNw6PoDYFnXZmX8wjDUF_m2-7dQ2BxaBig';
const JT_SHEETS = Object.freeze({
  PEOPLE:'GURU', RULES:'POINT_RULES', COMMITTEES:'JAWATANKUASA',
  ASSIGNMENTS:'PELANTIKAN', SETTINGS:'SETTINGS', AUDIT:'AUDIT_LOG'
});

function jtDb_(){ return SpreadsheetApp.openById(JT_DB_ID); }
function jtSheet_(name){ const sh=jtDb_().getSheetByName(name); if(!sh) throw new Error('Sheet database tidak dijumpai: '+name); return sh; }
function jtNorm_(v){ return String(v||'').trim().toUpperCase().replace(/\s+/g,' '); }
function jtCanonicalName_(v){
  return jtNorm_(v)
    .replace(/\bBT\b/g,'BINTI')
    .replace(/\bBTE\b/g,'BINTI')
    .replace(/\bBINTE\b/g,'BINTI');
}
function jtBool_(v){ return v===true || String(v||'').toUpperCase()==='TRUE' || String(v||'')==='1'; }
function jtNow_(){ return Utilities.formatDate(new Date(),'Asia/Kuala_Lumpur','yyyy-MM-dd HH:mm:ss'); }
function jtRows_(name){
  const sh=jtSheet_(name), lr=sh.getLastRow(), lc=sh.getLastColumn();
  if(lr<2||lc<1) return {sh,headers:lr?sh.getRange(1,1,1,lc).getDisplayValues()[0]:[],rows:[]};
  const headers=sh.getRange(1,1,1,lc).getDisplayValues()[0];
  const vals=sh.getRange(2,1,lr-1,lc).getValues();
  return {sh,headers,rows:vals};
}
function jtObj_(headers,row){ const o={}; headers.forEach((h,i)=>o[h]=row[i]); return o; }
function jtIndex_(headers){ const x={}; headers.forEach((h,i)=>x[h]=i); return x; }
function jtAudit_(user,action,targetType,targetId,details){
  try{ jtSheet_(JT_SHEETS.AUDIT).appendRow([jtNow_(),user.email,user.name,action,targetType,targetId,JSON.stringify(details||{})]); }catch(e){}
}
function jtNextPersonId_(rows,ix){
  let m=0;
  rows.forEach(r=>{
    const x=String(r[ix.PersonID]||'').match(/(\d+)/);
    if(x)m=Math.max(m,Number(x[1])||0);
  });
  return 'P'+String(m+1).padStart(3,'0');
}
function jtSyncTeachersFromEkeb_(actor,force){
  const cache=CacheService.getScriptCache(),cacheKey='jt_roster_sync_v3';
  if(!force){try{const cached=cache.get(cacheKey);if(cached)return Object.assign({cached:true},JSON.parse(cached))}catch(e){}}
  if(typeof getAllUsers_!=='function')throw new Error('Fungsi getAllUsers_ eKeberadaan tidak dijumpai.');
  const source=getAllUsers_().filter(u=>{const cat=jtNorm_(u.category);return !!u.active&&(cat==='PENGURUSAN'||cat==='PPP')&&String(u.name||'').trim()});
  const d=jtRows_(JT_SHEETS.PEOPLE),ix=jtIndex_(d.headers),rows=d.rows.map(r=>r.slice()),byEmail={},byName={},matched={};
  let added=0,updated=0,deactivated=0,m=0;
  rows.forEach((r,i)=>{const x=String(r[ix.PersonID]||'').match(/(\d+)/);if(x)m=Math.max(m,Number(x[1])||0);if(String(r[ix.Kumpulan]||'')!=='Guru')return;const email=String(r[ix.Emel]||'').trim().toLowerCase(),name=jtCanonicalName_(r[ix.NormalizedName]||r[ix.Nama]);if(email)byEmail[email]=i;if(name)byName[name]=i});
  source.forEach(u=>{
    const email=String(u.email||'').trim().toLowerCase(),canonical=jtCanonicalName_(u.name);
    let idx=(email&&byEmail[email]!==undefined)?byEmail[email]:(byName[canonical]!==undefined?byName[canonical]:-1);
    if(idx<0){
      const values={PersonID:'P'+String(++m).padStart(3,'0'),Nama:String(u.name||'').trim(),Kumpulan:'Guru',JawatanHakiki:String(u.jobTitle||'').trim()||'Guru Akademik Biasa',Emel:email,KategoriLogin:String(u.category||'PPP'),Aktif:true,NormalizedName:jtNorm_(u.name),Catatan:'Auto sync daripada eKeberadaan',TotalMata:0,BilTugas:0};
      rows.push(d.headers.map(h=>values[h]===undefined?'':values[h]));idx=rows.length-1;added++;byName[canonical]=idx;if(email)byEmail[email]=idx;
    }else{
      const r=rows[idx],before=JSON.stringify(r);
      if(ix.Nama!==undefined)r[ix.Nama]=String(u.name||'').trim();
      if(ix.Emel!==undefined)r[ix.Emel]=email;
      if(ix.KategoriLogin!==undefined)r[ix.KategoriLogin]=String(u.category||'PPP');
      if(ix.Aktif!==undefined)r[ix.Aktif]=true;
      if(ix.NormalizedName!==undefined)r[ix.NormalizedName]=jtNorm_(u.name);
      if(ix.JawatanHakiki!==undefined&&!String(r[ix.JawatanHakiki]||'').trim())r[ix.JawatanHakiki]=String(u.jobTitle||'').trim()||'Guru Akademik Biasa';
      if(JSON.stringify(r)!==before)updated++;
    }
    matched[idx]=true;
  });
  rows.forEach((r,i)=>{if(String(r[ix.Kumpulan]||'')==='Guru'&&!matched[i]&&jtBool_(r[ix.Aktif])){r[ix.Aktif]=false;deactivated++}});
  if((added||updated||deactivated)&&rows.length){d.sh.getRange(2,1,rows.length,d.headers.length).setValues(rows);SpreadsheetApp.flush();jtAudit_(actor||{email:'SYSTEM',name:'Auto Sync eKeberadaan'},'SYNC_GURU_EKEBERADAAN','GURU','ROSTER',{sourceActive:source.length,added,updated,deactivated})}
  const result={sourceActive:source.length,added,updated,deactivated,syncedAt:jtNow_()};try{cache.put(cacheKey,JSON.stringify(result),600)}catch(e){}return result;
}
function jtFindPersonForUser_(user, allowBind, allowAkp){
  const d=jtRows_(JT_SHEETS.PEOPLE), ix=jtIndex_(d.headers);
  const email=String(user.email||'').trim().toLowerCase(), uname=jtCanonicalName_(user.name);
  let found=null, rowNo=0;
  d.rows.some((r,i)=>{
    if(!jtBool_(r[ix.Aktif])) return false;
    const group=String(r[ix.Kumpulan]||'');
    if(group!=='Guru' && !(allowAkp && group==='AKP')) return false;
    const re=String(r[ix.Emel]||'').trim().toLowerCase();
    const rn=jtCanonicalName_(r[ix.NormalizedName]||r[ix.Nama]);
    if((re && re===email) || (!re && rn===uname)){ found=r; rowNo=i+2; return true; }
    return false;
  });
  if(!found) throw new Error(allowAkp
    ? 'Akaun ini tidak dipadankan dengan senarai Guru/AKP sistem. Hubungi pentadbir.'
    : 'Akaun ini tidak dipadankan dengan senarai guru sistem. Hubungi pentadbir.');
  const currentEmail=String(found[ix.Emel]||'').trim().toLowerCase();
  if(currentEmail && currentEmail!==email) throw new Error('Akaun DELIMa tidak sepadan dengan rekod pengguna.');
  if(!currentEmail && allowBind){
    d.sh.getRange(rowNo,ix.Emel+1).setValue(email);
    if(ix.KategoriLogin!==undefined) d.sh.getRange(rowNo,ix.KategoriLogin+1).setValue(String(user.category||''));
    jtAudit_(user,'BIND_LOGIN',String(found[ix.Kumpulan]||'GURU'),String(found[ix.PersonID]||''),{email});
    found[ix.Emel]=email;
  }
  return jtObj_(d.headers,found);
}
function jtRequireUser_(token){
  const user=requireSessionUser_(token);jtSyncTeachersFromEkeb_(user,false);
  const isManagement=jtNorm_(user.category)==='PENGURUSAN',isSystemAdmin=!!user.isAdmin,person=jtFindPersonForUser_(user,true,isSystemAdmin);
  return {user,person,isManagement,isSystemAdmin,isAdmin:isManagement||isSystemAdmin,adminSource:isManagement&&isSystemAdmin?'Pengurusan + Pentadbir Sistem':(isSystemAdmin?'Pentadbir Sistem':(isManagement?'Pengurusan':''))};
}
function jtRequireAdmin_(token){
  const ctx=jtRequireUser_(token);
  if(!ctx.isAdmin) throw new Error('Akses ini hanya untuk Pengurusan atau Pentadbir Sistem.');
  return ctx;
}

function jtRuleMap_(){
  const d=jtRows_(JT_SHEETS.RULES), ix=jtIndex_(d.headers), map={};
  d.rows.forEach(r=>{ if(jtBool_(r[ix.Aktif])) map[String(r[ix.KodSkor]||'')]=Number(r[ix.Mata])||0; });
  return map;
}
function jtTeacherStats_(){
  const pd=jtRows_(JT_SHEETS.PEOPLE), pi=jtIndex_(pd.headers), ad=jtRows_(JT_SHEETS.ASSIGNMENTS), ai=jtIndex_(ad.headers), rules=jtRuleMap_();
  const stats={};
  pd.rows.forEach(r=>{ if(String(r[pi.Kumpulan])==='Guru'&&jtBool_(r[pi.Aktif])) stats[String(r[pi.PersonID])]={id:String(r[pi.PersonID]),name:String(r[pi.Nama]),jobTitle:String(r[pi.JawatanHakiki]||''),email:String(r[pi.Emel]||''),points:0,count:0}; });
  ad.rows.forEach(r=>{ if(!jtBool_(r[ai.Aktif]))return; const id=String(r[ai.PersonID]||''); if(!stats[id])return; stats[id].points += Number(rules[String(r[ai.KodSkor]||'')]||0); stats[id].count++; });
  return {list:Object.values(stats).sort((a,b)=>b.points-a.points||a.name.localeCompare(b.name,'ms')),peopleData:pd,peopleIndex:pi};
}
function jtRebuildTeacherStats_(){
  const x=jtTeacherStats_(),d=x.peopleData,ix=x.peopleIndex,byId={};x.list.forEach(v=>byId[v.id]=v);
  if(ix.TotalMata!==undefined&&ix.BilTugas!==undefined){const vals=d.rows.map(r=>{const s=byId[String(r[ix.PersonID])];return [s?s.points:0,s?s.count:0]});if(vals.length)d.sh.getRange(2,ix.TotalMata+1,vals.length,2).setValues(vals)}
  return x.list;
}
function jtPublicUser_(ctx){ return {name:ctx.user.name,email:ctx.user.email,category:ctx.user.category,jobTitle:ctx.person.JawatanHakiki||ctx.user.jobTitle||'',personId:ctx.person.PersonID,group:ctx.person.Kumpulan||'Guru',isAdmin:ctx.isAdmin,isManagement:!!ctx.isManagement,isSystemAdmin:!!ctx.isSystemAdmin,adminSource:ctx.adminSource||''}; }

function jtGetBootstrap(token){
  const ctx=jtRequireUser_(token), stats=jtTeacherStats_();
  return {ok:true,user:jtPublicUser_(ctx),teachers:stats.list,school:'SMK Bandar Baru Sungai Lalang',year:'2026'};
}
function jtGetTeacherDetail(token,personId){
  jtRequireUser_(token);personId=String(personId||'').trim();
  const pd=jtRows_(JT_SHEETS.PEOPLE),pi=jtIndex_(pd.headers),prow=pd.rows.find(r=>String(r[pi.PersonID])===personId&&String(r[pi.Kumpulan])==='Guru');
  if(!prow)throw new Error('Guru tidak dijumpai.');
  const ad=jtRows_(JT_SHEETS.ASSIGNMENTS),ai=jtIndex_(ad.headers),rules=jtRuleMap_();
  const assignments=ad.rows.filter(r=>String(r[ai.PersonID])===personId&&jtBool_(r[ai.Aktif])).map(r=>({assignmentId:String(r[ai.AssignmentID]),committeeId:String(r[ai.CommitteeID]||''),committee:String(r[ai.Jawatankuasa]||''),role:String(r[ai.Peranan]||''),scoreCode:String(r[ai.KodSkor]||''),points:Number(rules[String(r[ai.KodSkor]||'')]||0),field:String(r[ai.Bidang]||''),type:String(r[ai.Jenis]||''),note:String(r[ai.Catatan]||'')}));
  const total=assignments.reduce((s,a)=>s+a.points,0);
  return {ok:true,teacher:{personId,name:String(prow[pi.Nama]),jobTitle:String(prow[pi.JawatanHakiki]||''),email:String(prow[pi.Emel]||''),totalPoints:total,taskCount:assignments.length},assignments};
}
function jtGetAdminRules(token){
  const ctx=jtRequireAdmin_(token),d=jtRows_(JT_SHEETS.RULES),ix=jtIndex_(d.headers),ad=jtRows_(JT_SHEETS.ASSIGNMENTS),ai=jtIndex_(ad.headers),usage={};
  ad.rows.forEach(r=>{if(jtBool_(r[ai.Aktif])){const k=String(r[ai.KodSkor]||'');usage[k]=(usage[k]||0)+1}});
  return {ok:true,user:jtPublicUser_(ctx),rules:d.rows.filter(r=>jtBool_(r[ix.Aktif])).map(r=>({code:String(r[ix.KodSkor]),points:Number(r[ix.Mata])||0,usageCount:Number(usage[String(r[ix.KodSkor])]||0)})).sort((a,b)=>b.points-a.points||a.code.localeCompare(b.code,'ms'))};
}
function jtGetAdminTeachers(token,forceSync){
  const ctx=jtRequireAdmin_(token),sync=forceSync?jtSyncTeachersFromEkeb_(ctx.user,true):null,d=jtRows_(JT_SHEETS.PEOPLE),ix=jtIndex_(d.headers);
  return {ok:true,user:jtPublicUser_(ctx),sync,teachers:d.rows.filter(r=>String(r[ix.Kumpulan])==='Guru'&&jtBool_(r[ix.Aktif])).map(r=>({id:String(r[ix.PersonID]),name:String(r[ix.Nama]),jobTitle:String(r[ix.JawatanHakiki]||''),email:String(r[ix.Emel]||'')})).sort((a,b)=>a.name.localeCompare(b.name,'ms'))};
}
function jtGetAdminCommittees(token){
  const ctx=jtRequireAdmin_(token),d=jtRows_(JT_SHEETS.COMMITTEES),ix=jtIndex_(d.headers),ad=jtRows_(JT_SHEETS.ASSIGNMENTS),ai=jtIndex_(ad.headers),usage={};
  ad.rows.forEach(r=>{if(jtBool_(r[ai.Aktif])){const k=String(r[ai.CommitteeID]||'');usage[k]=(usage[k]||0)+1}});
  return {ok:true,user:jtPublicUser_(ctx),committees:d.rows.filter(r=>jtBool_(r[ix.Aktif])).map(r=>({id:String(r[ix.CommitteeID]),code:String(r[ix.KodManual]||''),name:String(r[ix.NamaJawatankuasa]),field:String(r[ix.Bidang]||''),usageCount:Number(usage[String(r[ix.CommitteeID])]||0)})).sort((a,b)=>a.name.localeCompare(b.name,'ms'))};
}
function jtGetAdminData(token){
  const ctx=jtRequireAdmin_(token);
  const rd=jtRows_(JT_SHEETS.RULES), ri=jtIndex_(rd.headers);
  const cd=jtRows_(JT_SHEETS.COMMITTEES), ci=jtIndex_(cd.headers);
  const pd=jtRows_(JT_SHEETS.PEOPLE), pi=jtIndex_(pd.headers);
  return {ok:true,user:jtPublicUser_(ctx),rules:rd.rows.filter(r=>jtBool_(r[ri.Aktif])).map(r=>({code:String(r[ri.KodSkor]),points:Number(r[ri.Mata])||0,source:String(r[ri.Sumber]||''),note:String(r[ri.Catatan]||'')})),committees:cd.rows.filter(r=>jtBool_(r[ci.Aktif])).map(r=>({id:String(r[ci.CommitteeID]),code:String(r[ci.KodManual]||''),name:String(r[ci.NamaJawatankuasa]),field:String(r[ci.Bidang]||'')})),teachers:pd.rows.filter(r=>String(r[pi.Kumpulan])==='Guru'&&jtBool_(r[pi.Aktif])).map(r=>({id:String(r[pi.PersonID]),name:String(r[pi.Nama]),jobTitle:String(r[pi.JawatanHakiki]||''),email:String(r[pi.Emel]||'')}))};
}
function jtNextCommitteeId_(rows,ix){
  let m=0;
  rows.forEach(r=>{
    const x=String(r[ix.CommitteeID]||'').match(/(\d+)/);
    if(x)m=Math.max(m,Number(x[1])||0);
  });
  return 'J'+String(m+1).padStart(3,'0');
}
function jtAdminSaveCommittee(token,payload){
  const ctx=jtRequireAdmin_(token);payload=payload||{};const id=String(payload.id||payload.committeeId||'').trim(),name=String(payload.name||'').trim(),code=String(payload.code||'').trim(),field=String(payload.field||'').trim();
  if(!name)throw new Error('Nama jawatankuasa diperlukan.');if(!field)throw new Error('Bidang diperlukan.');
  const d=jtRows_(JT_SHEETS.COMMITTEES),ix=jtIndex_(d.headers),normalized=jtNorm_(name);
  if(d.rows.some(r=>jtBool_(r[ix.Aktif])&&jtNorm_(r[ix.NamaJawatankuasa])===normalized&&String(r[ix.CommitteeID]||'')!==id))throw new Error('Nama jawatankuasa ini sudah wujud.');
  if(id){
    let rowNo=0,before=null;d.rows.some((r,i)=>{if(String(r[ix.CommitteeID]||'')===id){rowNo=i+2;before=jtObj_(d.headers,r);return true}return false});if(!rowNo)throw new Error('Jawatankuasa tidak dijumpai.');
    d.sh.getRange(rowNo,ix.KodManual+1,1,3).setValues([[code,name,field]]);
    const ad=jtRows_(JT_SHEETS.ASSIGNMENTS),ai=jtIndex_(ad.headers),now=jtNow_(),matches=ad.rows.map(r=>String(r[ai.CommitteeID]||'')===id);let assignmentsUpdated=matches.filter(Boolean).length;
    if(ad.rows.length){ad.sh.getRange(2,ai.Jawatankuasa+1,ad.rows.length,1).setValues(ad.rows.map((r,i)=>[matches[i]?name:r[ai.Jawatankuasa]]));ad.sh.getRange(2,ai.Bidang+1,ad.rows.length,1).setValues(ad.rows.map((r,i)=>[matches[i]?field:r[ai.Bidang]]))}
    if(ai.UpdatedAt!==undefined&&ai.UpdatedBy!==undefined){ad.sh.getRange(2,ai.UpdatedAt+1,ad.rows.length,2).setValues(ad.rows.map((r,i)=>matches[i]?[now,ctx.user.email]:[r[ai.UpdatedAt],r[ai.UpdatedBy]]))}
    jtAudit_(ctx.user,'UPDATE_COMMITTEE','JAWATANKUASA',id,{before,after:{CommitteeID:id,KodManual:code,NamaJawatankuasa:name,Bidang:field},assignmentsUpdated});
    return {ok:true,mode:'update',committee:{id,code,name,field,usageCount:assignmentsUpdated},assignmentsUpdated};
  }
  const newId=jtNextCommitteeId_(d.rows,ix),values={CommitteeID:newId,KodManual:code,NamaJawatankuasa:name,Bidang:field,Aktif:true};d.sh.appendRow(d.headers.map(h=>values[h]===undefined?'':values[h]));jtAudit_(ctx.user,'ADD_COMMITTEE','JAWATANKUASA',newId,{after:values});
  return {ok:true,mode:'add',committee:{id:newId,code,name,field,usageCount:0},assignmentsUpdated:0};
}
function jtAdminUpdatePoint(token,code,points,newName){
  const ctx=jtRequireAdmin_(token);code=String(code||'').trim();newName=String(newName==null?code:newName).trim();points=Number(points);
  if(!code||!newName||!Number.isFinite(points)||points<0||points>100)throw new Error('Nama jawatan atau mata tidak sah.');
  const d=jtRows_(JT_SHEETS.RULES),ix=jtIndex_(d.headers);let rowNo=0,before=null;
  d.rows.some((r,i)=>{if(String(r[ix.KodSkor]||'').trim()===code){rowNo=i+2;before={name:String(r[ix.KodSkor]||''),points:Number(r[ix.Mata])||0};return true}return false});
  if(!rowNo)throw new Error('Jawatan tidak dijumpai.');
  if(jtNorm_(newName)!==jtNorm_(code)&&d.rows.some(r=>jtNorm_(r[ix.KodSkor])===jtNorm_(newName)))throw new Error('Nama jawatan baharu sudah wujud.');
  d.sh.getRange(rowNo,ix.KodSkor+1,1,2).setValues([[newName,points]]);if(ix.Sumber!==undefined)d.sh.getRange(rowNo,ix.Sumber+1,1,2).clearContent();
  const ad=jtRows_(JT_SHEETS.ASSIGNMENTS),ai=jtIndex_(ad.headers),now=jtNow_();let assignmentsUpdated=0;
  const matches=ad.rows.map(r=>String(r[ai.Peranan]||'').trim()===code||String(r[ai.KodSkor]||'').trim()===code);
  const roleScore=ad.rows.map((r,i)=>{if(matches[i]){assignmentsUpdated++;return [newName,newName,points]}return [r[ai.Peranan],r[ai.KodSkor],r[ai.Mata]]});
  if(roleScore.length)ad.sh.getRange(2,ai.Peranan+1,roleScore.length,3).setValues(roleScore);
  if(ai.UpdatedAt!==undefined&&ai.UpdatedBy!==undefined){const stamp=ad.rows.map((r,i)=>matches[i]?[now,ctx.user.email]:[r[ai.UpdatedAt],r[ai.UpdatedBy]]);ad.sh.getRange(2,ai.UpdatedAt+1,stamp.length,2).setValues(stamp)}
  const teachers=jtRebuildTeacherStats_();jtAudit_(ctx.user,'UPDATE_POINT_RULE','POINT_RULE',code,{before,after:{name:newName,points},assignmentsUpdated});
  return {ok:true,oldName:code,name:newName,points,assignmentsUpdated,teachers};
}
function jtAdminUpdateTeacher(token,personId,jobTitle){
  const ctx=jtRequireAdmin_(token);personId=String(personId||'');jobTitle=String(jobTitle||'').trim();if(!jobTitle)throw new Error('Jawatan hakiki diperlukan.');
  const d=jtRows_(JT_SHEETS.PEOPLE),ix=jtIndex_(d.headers);let rowNo=0,before='';d.rows.some((r,i)=>{if(String(r[ix.PersonID])===personId){rowNo=i+2;before=String(r[ix.JawatanHakiki]||'');return true}return false});if(!rowNo)throw new Error('Guru tidak dijumpai.');
  d.sh.getRange(rowNo,ix.JawatanHakiki+1).setValue(jobTitle);jtAudit_(ctx.user,'UPDATE_TEACHER','GURU',personId,{before,after:jobTitle});return {ok:true,personId,jobTitle};
}
function jtNextAssignmentId_(rows,ix){ let m=0; rows.forEach(r=>{const x=String(r[ix.AssignmentID]||'').match(/(\d+)/);if(x)m=Math.max(m,Number(x[1])||0);}); return 'A'+String(m+1).padStart(4,'0'); }
function jtAdminSaveAssignment(token,payload){
  const ctx=jtRequireAdmin_(token);payload=payload||{};const personId=String(payload.personId||'').trim(),committeeId=String(payload.committeeId||'').trim(),scoreCode=String(payload.scoreCode||'').trim();
  if(!personId||!committeeId||!scoreCode)throw new Error('Guru, jawatankuasa dan jawatan diperlukan.');
  const pd=jtRows_(JT_SHEETS.PEOPLE),pi=jtIndex_(pd.headers),p=pd.rows.find(r=>String(r[pi.PersonID])===personId&&String(r[pi.Kumpulan])==='Guru'&&jtBool_(r[pi.Aktif]));if(!p)throw new Error('Guru tidak dijumpai atau tidak aktif.');
  const cd=jtRows_(JT_SHEETS.COMMITTEES),ci=jtIndex_(cd.headers),cr=cd.rows.find(r=>String(r[ci.CommitteeID])===committeeId&&jtBool_(r[ci.Aktif]));if(!cr)throw new Error('Jawatankuasa tidak dijumpai atau tidak aktif.');
  const committee=String(cr[ci.NamaJawatankuasa]||''),field=String(cr[ci.Bidang]||''),rules=jtRuleMap_();if(rules[scoreCode]===undefined)throw new Error('Jawatan / nilai mata tidak sah.');
  const ad=jtRows_(JT_SHEETS.ASSIGNMENTS),ai=jtIndex_(ad.headers);let rowNo=0,id=String(payload.assignmentId||'').trim(),before=null;if(id)ad.rows.some((r,i)=>{if(String(r[ai.AssignmentID])===id){rowNo=i+2;before=jtObj_(ad.headers,r);return true}return false});else id=jtNextAssignmentId_(ad.rows,ai);
  const values={AssignmentID:id,PersonID:personId,Nama:String(p[pi.Nama]),Kumpulan:'Guru',CommitteeID:committeeId,Jawatankuasa:committee,Peranan:scoreCode,KodSkor:scoreCode,Mata:Number(rules[scoreCode])||0,Bidang:field,Jenis:rowNo&&before?String(before.Jenis||'Terus'):'Terus',Catatan:String(payload.note||''),MappingNote:'',PerluSemakan:false,Aktif:true,UpdatedAt:jtNow_(),UpdatedBy:ctx.user.email};
  const row=ad.headers.map(h=>values[h]===undefined?'':values[h]);if(rowNo)ad.sh.getRange(rowNo,1,1,row.length).setValues([row]);else ad.sh.appendRow(row);
  const teachers=jtRebuildTeacherStats_();jtAudit_(ctx.user,rowNo?'UPDATE_ASSIGNMENT':'ADD_ASSIGNMENT','PELANTIKAN',id,{before,after:values});
  return {ok:true,assignmentId:id,assignment:{assignmentId:id,committeeId,committee,role:scoreCode,scoreCode,points:Number(rules[scoreCode])||0,field,note:String(payload.note||'')},teachers};
}
function jtAdminDeactivateAssignment(token,assignmentId){
  const ctx=jtRequireAdmin_(token),ad=jtRows_(JT_SHEETS.ASSIGNMENTS),ai=jtIndex_(ad.headers);let rowNo=0,before=null;ad.rows.some((r,i)=>{if(String(r[ai.AssignmentID])===String(assignmentId||'')){rowNo=i+2;before=jtObj_(ad.headers,r);return true}return false});if(!rowNo)throw new Error('Pelantikan tidak dijumpai.');
  ad.sh.getRange(rowNo,ai.Aktif+1).setValue(false);if(ai.UpdatedAt!==undefined&&ai.UpdatedBy!==undefined)ad.sh.getRange(rowNo,ai.UpdatedAt+1,1,2).setValues([[jtNow_(),ctx.user.email]]);
  const teachers=jtRebuildTeacherStats_();jtAudit_(ctx.user,'DEACTIVATE_ASSIGNMENT','PELANTIKAN',assignmentId,{before});return {ok:true,assignmentId:String(assignmentId),before,teachers};
}
