# Patch Bridges.gs untuk JawatankuasaSMKBBSuL

Tambahkan nama fungsi berikut ke array `pagesBridgeMethodAllowed_`:

```js
'jtGetBootstrap',
'jtGetTeacherDetail',
'jtGetAdminData',
'jtAdminUpdatePoint',
'jtAdminUpdateTeacher',
'jtAdminSaveAssignment',
'jtAdminDeactivateAssignment',
```

Kemudian tambah kes berikut di dalam `invokePagesBridgeMethod_`:

```js
case 'jtGetBootstrap': return jtGetBootstrap.apply(null,args);
case 'jtGetTeacherDetail': return jtGetTeacherDetail.apply(null,args);
case 'jtGetAdminData': return jtGetAdminData.apply(null,args);
case 'jtAdminUpdatePoint': return jtAdminUpdatePoint.apply(null,args);
case 'jtAdminUpdateTeacher': return jtAdminUpdateTeacher.apply(null,args);
case 'jtAdminSaveAssignment': return jtAdminSaveAssignment.apply(null,args);
case 'jtAdminDeactivateAssignment': return jtAdminDeactivateAssignment.apply(null,args);
```

`EK_PAGES_ORIGINS` sedia ada sudah mempunyai `https://kea3123-bit.github.io`, jadi origin GitHub Pages repo ini tidak perlu ditambah jika kod zip yang diberi digunakan.