/**
 * JawatankuasaSMKBBSuL — Committee / workload API
 * Add this file to the existing eKeberadaan Apps Script project.
 * It intentionally reuses requireSessionUser_() from eKeberadaan.
 */
const JT_DB_ID = '1Rt8U0xvd3B1sXIG_KSXWWWlezSct5cpOXRP2xOZrEYU';
const JT_EKEB_DB_ID = '18XP3epj-kKTgjeeUdYxIrseyh4y_fJHoY0ygNXrtFZA';
const JT_EKEB_USERS_SHEET = 'PENGGUNA';
const JT_SHEETS = Object.freeze({
  PEOPLE:'GURU', RULES:'POINT_RULES', COMMITTEES:'JAWATANKUASA',
  ASSIGNMENTS:'PELANTIKAN', SETTINGS:'SETTINGS', AUDIT:'AUDIT_LOG'
});

function jtDb_(){ return SpreadsheetApp.openById(JT_DB_ID); }
function jtSheet_(name){ const sh=jtDb_().getSheetByName(name); if(!sh) throw new Error('Sheet database tidak dijumpai: '+name); return sh; }
function jtNorm_(v){ return String(v||'').trim().toUpperCase().replace(/\s+/g,' '); }
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
function jtEkebAccess_(user){
  const email=String(user&&user.email||'').trim().toLowerCase(), uname=jtNorm_(user&&user.name);
  if(!email && !uname) return {found:false,active:false,isSystemAdmin:false,category:''};

  const cache=CacheService.getScriptCache();
  const cacheKey='jt_ekeb_access_'+Utilities.base64EncodeWebSafe(email||uname).slice(0,120);
  try{
    const cached=cache.get(cacheKey);
    if(cached) return JSON.parse(cached);
  }catch(e){}

  const sh=SpreadsheetApp.openById(JT_EKEB_DB_ID).getSheetByName(JT_EKEB_USERS_SHEET);
  if(!sh) throw new Error('Sheet PENGGUNA eKeberadaan tidak dijumpai.');
  const lr=sh.getLastRow();
  if(lr<2) return {found:false,active:false,isSystemAdmin:false,category:''};

  // PENGGUNA: A=Aktif, B=Nama, C=Emel, D=Kategori, E=Pentadbir
  const rows=sh.getRange(2,1,lr-1,5).getValues();
  let access={found:false,active:false,isSystemAdmin:false,category:''};
  rows.some(r=>{
    const rowEmail=String(r[2]||'').trim().toLowerCase();
    const rowName=jtNorm_(r[1]);
    if((email && rowEmail===email) || (!email && uname && rowName===uname)){
      access={
        found:true,
        active:jtBool_(r[0]),
        isSystemAdmin:jtBool_(r[4]),
        category:String(r[3]||''),
        name:String(r[1]||''),
        email:rowEmail
      };
      return true;
    }
    return false;
  });
  try{ cache.put(cacheKey,JSON.stringify(access),120); }catch(e){}
  return access;
}
function jtFindPersonForUser_(user, allowBind, allowAkp){
  const d=jtRows_(JT_SHEETS.PEOPLE), ix=jtIndex_(d.headers);
  const email=String(user.email||'').trim().toLowerCase(), uname=jtNorm_(user.name);
  let found=null, rowNo=0;
  d.rows.some((r,i)=>{
    if(!jtBool_(r[ix.Aktif])) return false;
    const group=String(r[ix.Kumpulan]||'');
    if(group!=='Guru' && !(allowAkp && group==='AKP')) return false;
    const re=String(r[ix.Emel]||'').trim().toLowerCase();
    const rn=jtNorm_(r[ix.NormalizedName]||r[ix.Nama]);
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
  const user=requireSessionUser_(token);
  const ekeb=jtEkebAccess_(user);
  if(!ekeb.found || !ekeb.active) throw new Error('Akaun eKeberadaan tidak aktif atau tidak dijumpai.');
  const isManagement=jtNorm_(user.category||ekeb.category)==='PENGURUSAN';
  const isSystemAdmin=!!ekeb.isSystemAdmin;
  const person=jtFindPersonForUser_(user,true,isSystemAdmin);
  return {
    user,
    person,
    ekeb,
    isManagement,
    isSystemAdmin,
    isAdmin:isManagement||isSystemAdmin,
    adminSource:isManagement&&isSystemAdmin?'Pengurusan + Pentadbir Sistem':(isSystemAdmin?'Pentadbir Sistem':(isManagement?'Pengurusan':''))
  };
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
  const x=jtTeacherStats_(), d=x.peopleData, ix=x.peopleIndex, byId={}; x.list.forEach(v=>byId[v.id]=v);
  if(ix.TotalMata===undefined||ix.BilTugas===undefined) return;
  const vals=d.rows.map(r=>{const s=byId[String(r[ix.PersonID])]; return [s?s.points:0,s?s.count:0];});
  if(vals.length) d.sh.getRange(2,ix.TotalMata+1,vals.length,2).setValues(vals);
}
function jtPublicUser_(ctx){ return {name:ctx.user.name,email:ctx.user.email,category:ctx.user.category,jobTitle:ctx.person.JawatanHakiki||ctx.user.jobTitle||'',personId:ctx.person.PersonID,group:ctx.person.Kumpulan||'Guru',isAdmin:ctx.isAdmin,isManagement:!!ctx.isManagement,isSystemAdmin:!!ctx.isSystemAdmin,adminSource:ctx.adminSource||''}; }

function jtGetBootstrap(token){
  const ctx=jtRequireUser_(token), stats=jtTeacherStats_();
  return {ok:true,user:jtPublicUser_(ctx),teachers:stats.list,school:'SMK Bandar Baru Sungai Lalang',year:'2026'};
}
function jtGetTeacherDetail(token,personId){
  const ctx=jtRequireUser_(token); personId=String(personId||'').trim();
  const pd=jtRows_(JT_SHEETS.PEOPLE), pi=jtIndex_(pd.headers), prow=pd.rows.find(r=>String(r[pi.PersonID])===personId&&String(r[pi.Kumpulan])==='Guru');
  if(!prow) throw new Error('Guru tidak dijumpai.');
  const ad=jtRows_(JT_SHEETS.ASSIGNMENTS), ai=jtIndex_(ad.headers), rules=jtRuleMap_();
  const assignments=ad.rows.filter(r=>String(r[ai.PersonID])===personId&&jtBool_(r[ai.Aktif])).map(r=>({
    assignmentId:String(r[ai.AssignmentID]),committeeId:String(r[ai.CommitteeID]||''),committee:String(r[ai.Jawatankuasa]||''),role:String(r[ai.Peranan]||''),scoreCode:String(r[ai.KodSkor]||''),points:Number(rules[String(r[ai.KodSkor]||'')]||0),field:String(r[ai.Bidang]||''),type:String(r[ai.Jenis]||''),page:String(r[ai.MukaSurat]||''),note:String(r[ai.Catatan]||''),mappingNote:String(r[ai.MappingNote]||''),needsReview:jtBool_(r[ai.PerluSemakan])
  }));
  const total=assignments.reduce((s,a)=>s+a.points,0);
  return {ok:true,teacher:{personId,name:String(prow[pi.Nama]),jobTitle:String(prow[pi.JawatanHakiki]||''),email:String(prow[pi.Emel]||''),totalPoints:total,taskCount:assignments.length},assignments};
}
function jtGetAdminData(token){
  const ctx=jtRequireAdmin_(token);
  const rd=jtRows_(JT_SHEETS.RULES), ri=jtIndex_(rd.headers);
  const cd=jtRows_(JT_SHEETS.COMMITTEES), ci=jtIndex_(cd.headers);
  const pd=jtRows_(JT_SHEETS.PEOPLE), pi=jtIndex_(pd.headers);
  return {ok:true,user:jtPublicUser_(ctx),rules:rd.rows.filter(r=>jtBool_(r[ri.Aktif])).map(r=>({code:String(r[ri.KodSkor]),points:Number(r[ri.Mata])||0,source:String(r[ri.Sumber]||''),note:String(r[ri.Catatan]||'')})),committees:cd.rows.filter(r=>jtBool_(r[ci.Aktif])).map(r=>({id:String(r[ci.CommitteeID]),code:String(r[ci.KodManual]||''),name:String(r[ci.NamaJawatankuasa]),field:String(r[ci.Bidang]||''),page:String(r[ci.MukaSurat]||'')})),teachers:pd.rows.filter(r=>String(r[pi.Kumpulan])==='Guru'&&jtBool_(r[pi.Aktif])).map(r=>({id:String(r[pi.PersonID]),name:String(r[pi.Nama]),jobTitle:String(r[pi.JawatanHakiki]||''),email:String(r[pi.Emel]||'')}))};
}
function jtAdminUpdatePoint(token,code,points){
  const ctx=jtRequireAdmin_(token); code=String(code||'').trim(); points=Number(points); if(!code||!Number.isFinite(points)||points<0||points>100) throw new Error('Kod atau mata tidak sah.');
  const d=jtRows_(JT_SHEETS.RULES), ix=jtIndex_(d.headers); let rowNo=0, before=null;
  d.rows.some((r,i)=>{if(String(r[ix.KodSkor])===code){rowNo=i+2;before=Number(r[ix.Mata])||0;return true;}return false;});
  if(!rowNo) throw new Error('Kod skor tidak dijumpai.');
  d.sh.getRange(rowNo,ix.Mata+1).setValue(points);
  const ad=jtRows_(JT_SHEETS.ASSIGNMENTS), ai=jtIndex_(ad.headers);
  const pointCells=[];
  ad.rows.forEach((r,i)=>{ if(String(r[ai.KodSkor]||'')===code) pointCells.push(i+2); });
  pointCells.forEach(rn=>ad.sh.getRange(rn,ai.Mata+1).setValue(points));
  jtRebuildTeacherStats_(); jtAudit_(ctx.user,'UPDATE_POINT','POINT_RULE',code,{before,after:points,assignmentsUpdated:pointCells.length}); return {ok:true,code,points,assignmentsUpdated:pointCells.length};
}
function jtAdminUpdateTeacher(token,personId,jobTitle){
  const ctx=jtRequireAdmin_(token); personId=String(personId||''); jobTitle=String(jobTitle||'').trim(); if(!jobTitle) throw new Error('Jawatan hakiki diperlukan.');
  const d=jtRows_(JT_SHEETS.PEOPLE), ix=jtIndex_(d.headers); let rowNo=0,before=''; d.rows.some((r,i)=>{if(String(r[ix.PersonID])===personId){rowNo=i+2;before=String(r[ix.JawatanHakiki]||'');return true;}return false;}); if(!rowNo) throw new Error('Guru tidak dijumpai.');
  d.sh.getRange(rowNo,ix.JawatanHakiki+1).setValue(jobTitle); jtAudit_(ctx.user,'UPDATE_TEACHER','GURU',personId,{before,after:jobTitle}); return {ok:true};
}
function jtNextAssignmentId_(rows,ix){ let m=0; rows.forEach(r=>{const x=String(r[ix.AssignmentID]||'').match(/(\d+)/);if(x)m=Math.max(m,Number(x[1])||0);}); return 'A'+String(m+1).padStart(4,'0'); }
function jtAdminSaveAssignment(token,payload){
  const ctx=jtRequireAdmin_(token); payload=payload||{}; const personId=String(payload.personId||'').trim(), committee=String(payload.committee||'').trim(), role=String(payload.role||'').trim(), scoreCode=String(payload.scoreCode||'-').trim(); if(!personId||!committee||!role) throw new Error('Guru, jawatankuasa dan peranan diperlukan.');
  const pd=jtRows_(JT_SHEETS.PEOPLE), pi=jtIndex_(pd.headers), p=pd.rows.find(r=>String(r[pi.PersonID])===personId&&String(r[pi.Kumpulan])==='Guru'); if(!p) throw new Error('Guru tidak dijumpai.');
  const rules=jtRuleMap_(); if(rules[scoreCode]===undefined) throw new Error('Kod skor tidak sah.');
  const ad=jtRows_(JT_SHEETS.ASSIGNMENTS), ai=jtIndex_(ad.headers); let rowNo=0,id=String(payload.assignmentId||'').trim(),before=null; if(id) ad.rows.some((r,i)=>{if(String(r[ai.AssignmentID])===id){rowNo=i+2;before=jtObj_(ad.headers,r);return true;}return false;}); else id=jtNextAssignmentId_(ad.rows,ai);
  const committeeId=String(payload.committeeId||'').trim(); const values={AssignmentID:id,PersonID:personId,Nama:String(p[pi.Nama]),Kumpulan:'Guru',CommitteeID:committeeId,Jawatankuasa:committee,Peranan:role,KodSkor:scoreCode,Mata:Number(rules[scoreCode])||0,Bidang:String(payload.field||''),Jenis:String(payload.type||'Terus'),MukaSurat:String(payload.page||''),Catatan:String(payload.note||''),MappingNote:'Dikemaskini admin',PerluSemakan:false,Aktif:true,UpdatedAt:jtNow_(),UpdatedBy:ctx.user.email};
  const row=ad.headers.map(h=>values[h]===undefined?'':values[h]); if(rowNo) ad.sh.getRange(rowNo,1,1,row.length).setValues([row]); else ad.sh.appendRow(row);
  jtRebuildTeacherStats_(); jtAudit_(ctx.user,rowNo?'UPDATE_ASSIGNMENT':'ADD_ASSIGNMENT','PELANTIKAN',id,{before,after:values}); return {ok:true,assignmentId:id};
}
function jtAdminDeactivateAssignment(token,assignmentId){
  const ctx=jtRequireAdmin_(token), ad=jtRows_(JT_SHEETS.ASSIGNMENTS), ai=jtIndex_(ad.headers); let rowNo=0,before=null; ad.rows.some((r,i)=>{if(String(r[ai.AssignmentID])===String(assignmentId||'')){rowNo=i+2;before=jtObj_(ad.headers,r);return true;}return false;}); if(!rowNo) throw new Error('Pelantikan tidak dijumpai.');
  ad.sh.getRange(rowNo,ai.Aktif+1).setValue(false); if(ai.UpdatedAt!==undefined)ad.sh.getRange(rowNo,ai.UpdatedAt+1).setValue(jtNow_()); if(ai.UpdatedBy!==undefined)ad.sh.getRange(rowNo,ai.UpdatedBy+1).setValue(ctx.user.email); jtRebuildTeacherStats_(); jtAudit_(ctx.user,'DEACTIVATE_ASSIGNMENT','PELANTIKAN',assignmentId,{before}); return {ok:true};
}