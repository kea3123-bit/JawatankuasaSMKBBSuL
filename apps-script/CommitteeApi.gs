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
function jtSyncTeachersFromEkeb_(actor){
  if(typeof getAllUsers_!=='function') throw new Error('Fungsi getAllUsers_ eKeberadaan tidak dijumpai.');

  const source=getAllUsers_().filter(u=>{
    const cat=jtNorm_(u.category);
    return !!u.active && (cat==='PENGURUSAN' || cat==='PPP') && String(u.name||'').trim();
  });

  const d=jtRows_(JT_SHEETS.PEOPLE), ix=jtIndex_(d.headers);
  const byEmail={}, byName={};
  const matchedRows={};
  let added=0, updated=0, deactivated=0;

  d.rows.forEach((r,i)=>{
    if(String(r[ix.Kumpulan]||'')!=='Guru') return;
    const email=String(r[ix.Emel]||'').trim().toLowerCase();
    const name=jtCanonicalName_(r[ix.NormalizedName]||r[ix.Nama]);
    if(email) byEmail[email]=i;
    if(name) byName[name]=i;
  });

  source.forEach(u=>{
    const email=String(u.email||'').trim().toLowerCase();
    const canonical=jtCanonicalName_(u.name);
    let idx=(email && byEmail[email]!==undefined) ? byEmail[email] :
      (byName[canonical]!==undefined ? byName[canonical] : -1);

    if(idx<0){
      const id=jtNextPersonId_(d.rows,ix);
      const values={
        PersonID:id,
        Nama:String(u.name||'').trim(),
        Kumpulan:'Guru',
        JawatanHakiki:String(u.jobTitle||'').trim() || 'Guru Akademik Biasa',
        Emel:email,
        KategoriLogin:String(u.category||'PPP'),
        Aktif:true,
        NormalizedName:jtNorm_(u.name),
        Catatan:'Auto sync daripada eKeberadaan',
        TotalMata:0,
        BilTugas:0
      };
      const row=d.headers.map(h=>values[h]===undefined?'':values[h]);
      d.sh.appendRow(row);
      d.rows.push(row);
      idx=d.rows.length-1;
      byName[canonical]=idx;
      if(email)byEmail[email]=idx;
      added++;
    }else{
      const row=d.rows[idx];
      const rowNo=idx+2;
      const changes={};
      const set=(header,value)=>{
        if(ix[header]===undefined)return;
        const old=row[ix[header]];
        if(String(old??'')===String(value??''))return;
        d.sh.getRange(rowNo,ix[header]+1).setValue(value);
        row[ix[header]]=value;
        changes[header]={before:old,after:value};
      };
      set('Nama',String(u.name||'').trim());
      set('Emel',email);
      set('KategoriLogin',String(u.category||'PPP'));
      set('Aktif',true);
      set('NormalizedName',jtNorm_(u.name));
      if(!String(row[ix.JawatanHakiki]||'').trim()){
        set('JawatanHakiki',String(u.jobTitle||'').trim() || 'Guru Akademik Biasa');
      }
      if(Object.keys(changes).length)updated++;
    }
    matchedRows[idx]=true;
  });

  // eKeberadaan is the master active roster for Guru. Preserve old rows/history,
  // but hide teachers no longer active in PENGGUNA by setting Aktif=FALSE.
  d.rows.forEach((r,i)=>{
    if(String(r[ix.Kumpulan]||'')!=='Guru' || matchedRows[i])return;
    if(jtBool_(r[ix.Aktif])){
      d.sh.getRange(i+2,ix.Aktif+1).setValue(false);
      r[ix.Aktif]=false;
      deactivated++;
    }
  });

  if(added||updated||deactivated){
    SpreadsheetApp.flush();
    jtAudit_(actor||{email:'SYSTEM',name:'Auto Sync eKeberadaan'},'SYNC_GURU_EKEBERADAAN','GURU','ROSTER',{
      sourceActive:source.length,added,updated,deactivated
    });
  }
  return {sourceActive:source.length,added,updated,deactivated};
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
  const user=requireSessionUser_(token);
  jtSyncTeachersFromEkeb_(user);
  const isManagement=jtNorm_(user.category)==='PENGURUSAN';
  const isSystemAdmin=!!user.isAdmin;
  const person=jtFindPersonForUser_(user,true,isSystemAdmin);
  return {
    user,
    person,
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
function jtGetAdminRules(token){
  const ctx=jtRequireAdmin_(token);
  const d=jtRows_(JT_SHEETS.RULES), ix=jtIndex_(d.headers);
  return {ok:true,user:jtPublicUser_(ctx),rules:d.rows.filter(r=>jtBool_(r[ix.Aktif])).map(r=>({
    code:String(r[ix.KodSkor]),points:Number(r[ix.Mata])||0,
    source:String(r[ix.Sumber]||''),note:String(r[ix.Catatan]||'')
  }))};
}
function jtGetAdminTeachers(token){
  const ctx=jtRequireAdmin_(token);
  const d=jtRows_(JT_SHEETS.PEOPLE), ix=jtIndex_(d.headers);
  return {ok:true,user:jtPublicUser_(ctx),teachers:d.rows
    .filter(r=>String(r[ix.Kumpulan])==='Guru'&&jtBool_(r[ix.Aktif]))
    .map(r=>({id:String(r[ix.PersonID]),name:String(r[ix.Nama]),jobTitle:String(r[ix.JawatanHakiki]||''),email:String(r[ix.Emel]||'')}))
    .sort((a,b)=>a.name.localeCompare(b.name,'ms'))};
}
function jtGetAdminCommittees(token){
  const ctx=jtRequireAdmin_(token);
  const d=jtRows_(JT_SHEETS.COMMITTEES), ix=jtIndex_(d.headers);
  return {ok:true,user:jtPublicUser_(ctx),committees:d.rows
    .filter(r=>jtBool_(r[ix.Aktif]))
    .map(r=>({id:String(r[ix.CommitteeID]),code:String(r[ix.KodManual]||''),name:String(r[ix.NamaJawatankuasa]),field:String(r[ix.Bidang]||''),page:String(r[ix.MukaSurat]||'')}))
    .sort((a,b)=>a.name.localeCompare(b.name,'ms'))};
}

function jtGetAdminData(token){
  const ctx=jtRequireAdmin_(token);
  const rd=jtRows_(JT_SHEETS.RULES), ri=jtIndex_(rd.headers);
  const cd=jtRows_(JT_SHEETS.COMMITTEES), ci=jtIndex_(cd.headers);
  const pd=jtRows_(JT_SHEETS.PEOPLE), pi=jtIndex_(pd.headers);
  return {ok:true,user:jtPublicUser_(ctx),rules:rd.rows.filter(r=>jtBool_(r[ri.Aktif])).map(r=>({code:String(r[ri.KodSkor]),points:Number(r[ri.Mata])||0,source:String(r[ri.Sumber]||''),note:String(r[ri.Catatan]||'')})),committees:cd.rows.filter(r=>jtBool_(r[ci.Aktif])).map(r=>({id:String(r[ci.CommitteeID]),code:String(r[ci.KodManual]||''),name:String(r[ci.NamaJawatankuasa]),field:String(r[ci.Bidang]||''),page:String(r[ci.MukaSurat]||'')})),teachers:pd.rows.filter(r=>String(r[pi.Kumpulan])==='Guru'&&jtBool_(r[pi.Aktif])).map(r=>({id:String(r[pi.PersonID]),name:String(r[pi.Nama]),jobTitle:String(r[pi.JawatanHakiki]||''),email:String(r[pi.Emel]||'')}))};
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
  const ctx=jtRequireAdmin_(token);
  payload=payload||{};
  const name=String(payload.name||'').trim();
  const code=String(payload.code||'').trim();
  const field=String(payload.field||'').trim();
  const page=String(payload.page||'').trim();
  if(!name) throw new Error('Nama jawatankuasa diperlukan.');

  const d=jtRows_(JT_SHEETS.COMMITTEES), ix=jtIndex_(d.headers);
  const normalized=jtNorm_(name);
  const duplicate=d.rows.find(r=>jtNorm_(r[ix.NamaJawatankuasa])===normalized && jtBool_(r[ix.Aktif]));
  if(duplicate) throw new Error('Jawatankuasa ini sudah wujud.');

  const id=jtNextCommitteeId_(d.rows,ix);
  const values={
    CommitteeID:id,
    KodManual:code,
    NamaJawatankuasa:name,
    Bidang:field,
    MukaSurat:page,
    Aktif:true
  };
  const row=d.headers.map(h=>values[h]===undefined?'':values[h]);
  d.sh.appendRow(row);
  jtAudit_(ctx.user,'ADD_COMMITTEE','JAWATANKUASA',id,{after:values});
  return {ok:true,committee:{id,code,name,field,page}};
}

function jtAdminUpdatePoint(token,code,points,newName){
  const ctx=jtRequireAdmin_(token);
  code=String(code||'').trim();
  newName=String(newName==null?code:newName).trim();
  points=Number(points);
  if(!code||!newName||!Number.isFinite(points)||points<0||points>100) throw new Error('Nama jawatan atau mata tidak sah.');

  const d=jtRows_(JT_SHEETS.RULES), ix=jtIndex_(d.headers);
  let rowNo=0,before=null;
  d.rows.some((r,i)=>{
    if(String(r[ix.KodSkor]||'').trim()===code){
      rowNo=i+2;
      before={name:String(r[ix.KodSkor]||''),points:Number(r[ix.Mata])||0};
      return true;
    }
    return false;
  });
  if(!rowNo) throw new Error('Jawatan tidak dijumpai.');

  if(jtNorm_(newName)!==jtNorm_(code)){
    const duplicate=d.rows.find(r=>jtNorm_(r[ix.KodSkor])===jtNorm_(newName));
    if(duplicate) throw new Error('Nama jawatan baharu sudah wujud.');
  }

  d.sh.getRange(rowNo,ix.KodSkor+1).setValue(newName);
  d.sh.getRange(rowNo,ix.Mata+1).setValue(points);
  if(ix.Sumber!==undefined)d.sh.getRange(rowNo,ix.Sumber+1).setValue('');
  if(ix.Catatan!==undefined)d.sh.getRange(rowNo,ix.Catatan+1).setValue('');

  const ad=jtRows_(JT_SHEETS.ASSIGNMENTS), ai=jtIndex_(ad.headers);
  let assignmentsUpdated=0;
  ad.rows.forEach((r,i)=>{
    const role=String(r[ai.Peranan]||'').trim();
    const score=String(r[ai.KodSkor]||'').trim();
    if(role===code || score===code){
      const rn=i+2;
      ad.sh.getRange(rn,ai.Peranan+1).setValue(newName);
      ad.sh.getRange(rn,ai.KodSkor+1).setValue(newName);
      ad.sh.getRange(rn,ai.Mata+1).setValue(points);
      if(ai.UpdatedAt!==undefined)ad.sh.getRange(rn,ai.UpdatedAt+1).setValue(jtNow_());
      if(ai.UpdatedBy!==undefined)ad.sh.getRange(rn,ai.UpdatedBy+1).setValue(ctx.user.email);
      assignmentsUpdated++;
    }
  });

  jtRebuildTeacherStats_();
  jtAudit_(ctx.user,'UPDATE_POINT_RULE','POINT_RULE',code,{
    before,
    after:{name:newName,points},
    assignmentsUpdated
  });
  return {ok:true,oldName:code,name:newName,points,assignmentsUpdated};
}
function jtAdminUpdateTeacher(token,personId,jobTitle){
  const ctx=jtRequireAdmin_(token); personId=String(personId||''); jobTitle=String(jobTitle||'').trim(); if(!jobTitle) throw new Error('Jawatan hakiki diperlukan.');
  const d=jtRows_(JT_SHEETS.PEOPLE), ix=jtIndex_(d.headers); let rowNo=0,before=''; d.rows.some((r,i)=>{if(String(r[ix.PersonID])===personId){rowNo=i+2;before=String(r[ix.JawatanHakiki]||'');return true;}return false;}); if(!rowNo) throw new Error('Guru tidak dijumpai.');
  d.sh.getRange(rowNo,ix.JawatanHakiki+1).setValue(jobTitle); jtAudit_(ctx.user,'UPDATE_TEACHER','GURU',personId,{before,after:jobTitle}); return {ok:true};
}
function jtNextAssignmentId_(rows,ix){ let m=0; rows.forEach(r=>{const x=String(r[ix.AssignmentID]||'').match(/(\d+)/);if(x)m=Math.max(m,Number(x[1])||0);}); return 'A'+String(m+1).padStart(4,'0'); }
function jtAdminSaveAssignment(token,payload){
  const ctx=jtRequireAdmin_(token); payload=payload||{}; const personId=String(payload.personId||'').trim(), committee=String(payload.committee||'').trim(), scoreCode=String(payload.scoreCode||'').trim(), role=scoreCode; if(!personId||!committee||!scoreCode) throw new Error('Guru, jawatankuasa dan jawatan diperlukan.');
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