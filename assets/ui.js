(()=>{const q=s=>document.querySelector(s);
const IC={success:'<path d="M5 12l5 5 9-10"/>',danger:'<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/>',warning:'<path d="M12 3l10 18H2zM12 10v5M12 18v.5"/>',info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>'};
const TI={success:'Succès',danger:'Erreur',warning:'Attention',info:'Information'};
function box({type='info',title,msg,ok='OK',cancel=null,input=null}){return new Promise(res=>{
 const d=document.createElement('dialog'),h=document.createElement('h3'),p=document.createElement('p'),act=document.createElement('div'),ic=document.createElementNS('http://www.w3.org/2000/svg','svg');
 d.className='dlg '+type;ic.setAttribute('viewBox','0 0 24 24');ic.setAttribute('class','i dlg-i');ic.innerHTML=IC[type]||IC.info;h.textContent=title||TI[type];p.textContent=msg;
 let inp=null;if(input!==null){inp=document.createElement('input');inp.value=input;}
 const done=v=>{d.close();d.remove();res(v);},mk=(t,c,f)=>{const b=document.createElement('button');b.type='button';b.textContent=t;b.className=c;b.onclick=f;return b;};
 if(cancel)act.append(mk(cancel,'ghost',()=>done(inp?null:false)));const bo=mk(ok,'',()=>done(inp?inp.value:true));act.append(bo);
 d.addEventListener('cancel',e=>{e.preventDefault();done(inp?null:false);});
 if(inp)inp.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();done(inp.value);}});
 d.append(ic,h,p);if(inp)d.append(inp);d.append(act);document.body.append(d);d.showModal();(inp||bo).focus();});}
window.dlg={alert:(m,t,ti)=>box({type:t||(/^Échec|impossible/.test(m)?'danger':'warning'),msg:m,title:ti}),
 confirm:(m,o={})=>box({type:o.type||'warning',msg:m,title:o.title||'Confirmation',ok:o.ok||'Confirmer',cancel:'Annuler'}),
 prompt:(m,v='')=>box({type:'info',msg:m,title:'Saisie',input:v,ok:'Valider',cancel:'Annuler'})};
if(document.documentElement.classList.contains('splash'))setTimeout(()=>{document.documentElement.classList.add('splash-out');setTimeout(()=>document.documentElement.classList.remove('splash','splash-out'),450);},1300);
const f=q('#flash');if(f)dlg.alert(f.dataset.msg,f.dataset.type);
q('#mb')?.addEventListener('click',()=>q('.side').classList.toggle('open'));
document.addEventListener('submit',async e=>{const b=e.submitter||e.target.querySelector('button:not([type=button])');
 if(b&&b.dataset.confirm){e.preventDefault();if(await dlg.confirm(b.dataset.confirm,{ok:b.dataset.ok||'Confirmer',type:b.dataset.type})){b.classList.add('loading');e.target.submit();}return;}
 if(b)setTimeout(()=>b.classList.add('loading'),0);});
document.addEventListener('change',e=>{if(e.target.matches('select[data-auto]'))e.target.form.submit();});
if('serviceWorker' in navigator)addEventListener('load',()=>navigator.serviceWorker.register('sw.js').catch(()=>{}));
// Bouton « Installer l'application » (Android/Chrome via beforeinstallprompt, iPhone via instructions)
const SA=matchMedia('(display-mode: standalone)').matches||navigator.standalone,IOS=/iphone|ipad|ipod/i.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
const get=k=>{try{return localStorage.getItem(k);}catch{return null;}},set=k=>{try{localStorage.setItem(k,'1');}catch{}};
function ib(go){if(SA||get('dp-noinst')||q('.inst'))return;const w=document.createElement('div'),b=document.createElement('button'),x=document.createElement('button');
 w.className='inst';b.type=x.type='button';b.textContent='📲 Installer l’application';x.className='ghost';x.textContent='×';x.setAttribute('aria-label','Masquer');
 b.onclick=async()=>{if(await go())w.remove();};x.onclick=()=>{set('dp-noinst');w.remove();};w.append(b,x);document.body.append(w);}
let ev=null;addEventListener('beforeinstallprompt',e=>{e.preventDefault();ev=e;ib(async()=>{ev.prompt();const r=await ev.userChoice;ev=null;return r.outcome==='accepted';});});
addEventListener('appinstalled',()=>q('.inst')?.remove());
if(IOS&&!SA)ib(async()=>{await dlg.alert('Dans Safari, touchez le bouton Partager (carré avec une flèche), puis « Sur l’écran d’accueil ».','info','Installer sur iPhone');return false;});
addEventListener('pageshow',()=>document.querySelectorAll('.loading').forEach(b=>b.classList.remove('loading')));})();
