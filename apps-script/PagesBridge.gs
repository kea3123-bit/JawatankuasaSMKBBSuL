/**
 * JawatankuasaSMKBBSuL — GitHub Pages bridge
 *
 * Tambah fail ini sebagai PagesBridge.gs dalam PROJEK Apps Script eKeberadaan
 * yang sama dengan Code.gs. Tidak perlu ubah Code.gs.
 */

const JT_PAGES_ALLOWED_ORIGINS = Object.freeze([
  'https://kea3123-bit.github.io'
]);

const JT_PAGES_ALLOWED_METHODS = Object.freeze({
  checkDelimaAccount: true,
  loginWithPin: true,
  setFirstPin: true,
  resumeSession: true,
  resumeTrustedDevice: true,
  logoutApp: true,
  jtGetBootstrap: true,
  jtGetTeacherDetail: true,
  jtGetAdminData: true,
  jtAdminUpdatePoint: true,
  jtAdminUpdateTeacher: true,
  jtAdminSaveAssignment: true,
  jtAdminDeactivateAssignment: true
});

function doPost(e) {
  if (!e || String((e.parameter || {}).bridge || '') !== '1') {
    return ContentService.createTextOutput('Unsupported request');
  }

  let req = {};
  try {
    req = JSON.parse(String(e.parameter.payload || '{}'));
  } catch (err) {
    return jtPagesBridgeHtml_('https://kea3123-bit.github.io', {
      type:'EK_BRIDGE_RESULT', id:'', channel:'jt-pages',
      ok:false, error:'Payload bridge tidak sah.'
    });
  }

  const origin = String(req.origin || '').replace(/\/$/, '');
  const id = String(req.id || '');
  const channel = String(req.channel || '');
  const method = String(req.method || '');
  const args = Array.isArray(req.args) ? req.args : [];
  const base = {type:'EK_BRIDGE_RESULT', id:id, channel:channel || 'jt-pages'};

  if (JT_PAGES_ALLOWED_ORIGINS.indexOf(origin) < 0) {
    return jtPagesBridgeHtml_('https://kea3123-bit.github.io',
      Object.assign(base,{ok:false,error:'Origin tidak dibenarkan.'}));
  }
  if (channel !== 'jt-pages') {
    return jtPagesBridgeHtml_(origin,
      Object.assign(base,{ok:false,error:'Channel bridge tidak sah.'}));
  }
  if (!JT_PAGES_ALLOWED_METHODS[method]) {
    return jtPagesBridgeHtml_(origin,
      Object.assign(base,{ok:false,error:'Kaedah bridge tidak dibenarkan.'}));
  }

  try {
    const value = jtPagesInvoke_(method, args);
    return jtPagesBridgeHtml_(origin, Object.assign(base,{ok:true,value:value}));
  } catch (err) {
    const message = err && err.message ? err.message : String(err || 'Ralat backend');
    return jtPagesBridgeHtml_(origin, Object.assign(base,{ok:false,error:message}));
  }
}

function jtPagesInvoke_(method, args) {
  switch (method) {
    case 'checkDelimaAccount': return checkDelimaAccount.apply(null,args);
    case 'loginWithPin': return loginWithPin.apply(null,args);
    case 'setFirstPin': return setFirstPin.apply(null,args);
    case 'resumeSession': return resumeSession.apply(null,args);
    case 'resumeTrustedDevice': return resumeTrustedDevice.apply(null,args);
    case 'logoutApp': return logoutApp.apply(null,args);
    case 'jtGetBootstrap': return jtGetBootstrap.apply(null,args);
    case 'jtGetTeacherDetail': return jtGetTeacherDetail.apply(null,args);
    case 'jtGetAdminData': return jtGetAdminData.apply(null,args);
    case 'jtAdminUpdatePoint': return jtAdminUpdatePoint.apply(null,args);
    case 'jtAdminUpdateTeacher': return jtAdminUpdateTeacher.apply(null,args);
    case 'jtAdminSaveAssignment': return jtAdminSaveAssignment.apply(null,args);
    case 'jtAdminDeactivateAssignment': return jtAdminDeactivateAssignment.apply(null,args);
    default: throw new Error('Kaedah bridge tidak disokong.');
  }
}

function jtPagesBridgeHtml_(targetOrigin, payload) {
  const safeOrigin = JSON.stringify(String(targetOrigin || 'https://kea3123-bit.github.io'));
  const safePayload = JSON.stringify(payload).replace(/</g,'\\u003c');
  const html = '<!doctype html><html><head><meta charset="utf-8"></head><body>'+
    '<script>(function(){try{window.parent.postMessage('+safePayload+','+safeOrigin+');}catch(e){}})();<\\/script>'+
    '</body></html>';
  return HtmlService.createHtmlOutput(html)
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}
