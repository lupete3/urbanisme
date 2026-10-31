(()=>{
const W=1654,H=1169,$=(s)=>document.querySelector(s);
const el=(t,a={},...k)=>{const e=document.createElement(t);Object.assign(e,a);e.append(...k);return e;};
const KINDS={fenetre:['Fenêtre',140,160,'#2f3640',120,135],porte:['Porte',110,230,'#6b4423',90,215],volet:['Volet',60,160,'#cfcfcf',60,135],lucarne:['Lucarne',150,150,'#2f3640',120,120],cadre:['Cadre',220,160,'#d11',0,0]};
const TOOLS={DP1:['poly','cadre','cote','text'],DP2:['poly','cadre','cote','text'],DP3:['poly','cadre','cote','text'],DP4:['fenetre','porte','volet','lucarne','cadre','cote','text'],DP5:['fenetre','porte','volet','lucarne','cadre','text'],DP6:['fenetre','porte','volet','lucarne','cadre','text'],DP7:[],DP8:[],AP1:['poly','cadre','cote','text'],AP2:['poly','cadre','cote','text'],AP3:['cadre','cote','text'],AP4:['text'],AP5:['cadre','cote','text'],'AP6.1':[],'AP6.2':['cadre','cote','text'],PD1:['poly','cadre','cote','text'],PD2:['poly','cadre','cote','text'],PD3:['poly','text']};
const NAMES={poly:'Contour parcelle',cote:'Cote',text:'Texte',fenetre:'+ Fenêtre',porte:'+ Porte',volet:'+ Volet',lucarne:'+ Lucarne',cadre:'+ Cadre'};
function wrap(c,t,x,y,mw,lh){String(t).split('\n').forEach(p=>{let l='';p.split(' ').forEach(w=>{const n=l?l+' '+w:w;if(c.measureText(n).width>mw&&l){c.fillText(l,x,y);y+=lh;l=w;}else l=n;});c.fillText(l,x,y);y+=lh;});return y;}
function fit(c,t,mw){t=String(t);while(t.length>3&&c.measureText(t).width>mw)t=t.slice(0,-2);return t;}
let HEAD=['Déclaration préalable','de travaux',''];
function frame(c,m,code,title){
 c.fillStyle='#fff';c.fillRect(0,0,W,H);c.strokeStyle='#000';c.lineWidth=2;c.strokeRect(40,40,W-80,H-80);
 [[40,1000,W-40,1000],[660,1000,660,H-40],[1100,1000,1100,H-40],[1380,1000,1380,H-40]].forEach(l=>{c.beginPath();c.moveTo(l[0],l[1]);c.lineTo(l[2],l[3]);c.stroke();});
 c.fillStyle='#eef1f6';c.fillRect(41,1001,W-82,127);c.fillStyle='#1f4fd8';c.fillRect(1380,1001,W-1421,127);c.fillRect(41,1001,10,127);c.fillStyle='#14213d';c.textBaseline='top';c.textAlign='left';c.font='bold 30px Arial';c.fillText(fit(c,HEAD[0].toUpperCase(),580),68,1018);c.fillText(fit(c,HEAD[1].toUpperCase(),580),68,1056);
 c.font='15px Arial';c.fillText('Ces documents ne se substituent pas aux plans d’exécution.',68,1104);
 c.font='20px Arial';[['Propriétaire : '+m.owner],['Projet : '+m.titre],['Adresse : '+m.adresse],['Parcelle n° '+m.parcelle]].forEach((t,i)=>c.fillText(fit(c,t[0],410),680,1012+i*29));
 c.font='bold 21px Arial';wrap(c,title,1118,1014,250,26);c.font='18px Arial';c.fillText(new Date().toLocaleDateString('fr-FR'),1118,1100);
 c.fillStyle='#fff';c.font='bold 54px Arial';c.textAlign='center';c.fillText(code,1517,1040);c.textAlign='left';}
let REDRAW=()=>{};const IM={};const getImg=(id,cb)=>{if(!IM[id]){const i=new Image();i.src='index.php?a=libimg&id='+id;IM[id]=i;i.onload=()=>cb&&cb();}return IM[id];};
const imgsReady=os=>Promise.all(os.filter(o=>o.t==='img').map(o=>{const i=getImg(o.id);return i.complete?0:new Promise(r=>{i.onload=i.onerror=r;});}));
const LIB=['DP4','DP5','DP6','AP3','AP5','AP6.2'];
const seg=(px,py,x1,y1,x2,y2)=>{const dx=x2-x1,dy=y2-y1,l=dx*dx+dy*dy||1,t=Math.max(0,Math.min(1,((px-x1)*dx+(py-y1)*dy)/l));return Math.hypot(px-x1-t*dx,py-y1-t*dy);};
function label(c,t,x,y){c.font='bold 20px Arial';c.textBaseline='top';const w=c.measureText(t).width+12;c.fillStyle='rgba(255,255,255,.92)';c.fillRect(x,y,w,26);c.fillStyle='#000';c.fillText(t,x+6,y+3);}
let HK=1;const R=o=>(o.rot||0)*Math.PI/180;
function rot(c,o){const cx=o.x+o.w/2,cy=o.y+o.h/2;c.translate(cx,cy);c.rotate(R(o));c.translate(-cx,-cy);}
function xf(o,x,y,a){const cx=o.x+o.w/2,cy=o.y+o.h/2,dx=x-cx,dy=y-cy;return[cx+dx*Math.cos(a)-dy*Math.sin(a),cy+dx*Math.sin(a)+dy*Math.cos(a)];}
const toLocal=(o,x,y)=>xf(o,x,y,-R(o)),toWorld=(o,x,y)=>xf(o,x,y,R(o));
const HS=[['nw',0,0],['n',.5,0],['ne',1,0],['e',1,.5],['se',1,1],['s',.5,1],['sw',0,1],['w',0,.5]];
function handles(c,o){c.setLineDash([]);c.fillStyle='#fff';c.strokeStyle='#1d4ed8';c.lineWidth=3;const h=9*HK;
 HS.forEach(([n,fx,fy])=>{const x=o.x+o.w*fx,y=o.y+o.h*fy;c.fillRect(x-h,y-h,2*h,2*h);c.strokeRect(x-h,y-h,2*h,2*h);});
 const rx=o.x+o.w/2,ry=o.y-44*HK;c.beginPath();c.moveTo(rx,o.y);c.lineTo(rx,ry);c.stroke();c.beginPath();c.arc(rx,ry,11*HK,0,7);c.fillStyle='#1d4ed8';c.fill();}
function drawObj(c,o,s){c.save();c.lineJoin='round';if(o.t==='comp'||o.t==='img')rot(c,o);
 if(o.t==='comp'){c.lineWidth=o.k==='cadre'?5:8;c.strokeStyle=o.color;
  if(o.k==='cadre')c.strokeRect(o.x,o.y,o.w,o.h);
  else{c.fillStyle=o.k==='porte'?o.color:'rgba(255,255,255,.9)';c.fillRect(o.x,o.y,o.w,o.h);c.strokeRect(o.x,o.y,o.w,o.h);
   if(o.k==='fenetre'||o.k==='lucarne'){c.lineWidth=5;c.beginPath();c.moveTo(o.x+o.w/2,o.y);c.lineTo(o.x+o.w/2,o.y+o.h);c.stroke();}
   if(o.k==='volet'){c.lineWidth=2;for(let y=o.y+14;y<o.y+o.h;y+=14){c.beginPath();c.moveTo(o.x,y);c.lineTo(o.x+o.w,y);c.stroke();}}}
  const t=[o.label,o.wcm?o.wcm+' × '+o.hcm+' cm':''].filter(Boolean).join(' – ');if(t)label(c,t,o.x,o.y+o.h+6);
  if(s){c.strokeStyle='#1d4ed8';c.lineWidth=2;c.setLineDash([6,4]);c.strokeRect(o.x-5,o.y-5,o.w+10,o.h+10);c.setLineDash([]);handles(c,o);}}
 else if(o.t==='img'){const i=getImg(o.id,()=>REDRAW());if(i.complete&&i.naturalWidth){c.save();if(o.flip){c.translate(o.x*2+o.w,0);c.scale(-1,1);}c.drawImage(i,o.x,o.y,o.w,o.h);c.restore();}else{c.strokeStyle='#999';c.strokeRect(o.x,o.y,o.w,o.h);}
  const t=[o.label,o.wcm?o.wcm+' × '+o.hcm+' cm':''].filter(Boolean).join(' – ');if(t)label(c,t,o.x,o.y+o.h+6);
  if(s){c.strokeStyle='#1d4ed8';c.lineWidth=2;c.setLineDash([6,4]);c.strokeRect(o.x-5,o.y-5,o.w+10,o.h+10);c.setLineDash([]);handles(c,o);}}
 else if(o.t==='poly'){c.strokeStyle=o.color||'#e00';c.lineWidth=6;c.beginPath();o.pts.forEach(([x,y],i)=>i?c.lineTo(x,y):c.moveTo(x,y));if(o.closed)c.closePath();c.stroke();if(s){c.fillStyle='#1d4ed8';o.pts.forEach(([x,y])=>c.fillRect(x-6,y-6,12,12));}}
 else if(o.t==='cote'){c.strokeStyle=s?'#1d4ed8':'#000';c.lineWidth=3;c.beginPath();c.moveTo(o.x1,o.y1);c.lineTo(o.x2,o.y2);c.stroke();[[o.x1,o.y1],[o.x2,o.y2]].forEach(([x,y])=>{c.beginPath();c.arc(x,y,6,0,7);c.fillStyle=c.strokeStyle;c.fill();});if(o.txt)label(c,o.txt,(o.x1+o.x2)/2-30,(o.y1+o.y2)/2-13);}
 else if(o.t==='text'){c.font='bold '+o.size+'px Arial';c.textBaseline='top';c.fillStyle=o.color||'#000';c.fillText(o.txt,o.x,o.y);if(s){c.strokeStyle='#1d4ed8';c.lineWidth=2;c.strokeRect(o.x-4,o.y-4,c.measureText(o.txt).width+8,o.size+10);}}
 c.restore();}
function hit(c,o,x,y){if(o.t==='comp'||o.t==='img'){[x,y]=toLocal(o,x,y);return x>=o.x-6&&x<=o.x+o.w+6&&y>=o.y-6&&y<=o.y+o.h+6;}
 if(o.t==='cote')return seg(x,y,o.x1,o.y1,o.x2,o.y2)<14;
 if(o.t==='text'){c.font='bold '+o.size+'px Arial';return x>=o.x&&x<=o.x+c.measureText(o.txt).width&&y>=o.y&&y<=o.y+o.size+6;}
 const p=o.pts;for(let i=0;i<p.length;i++){const q=p[(i+1)%p.length];if(seg(x,y,p[i][0],p[i][1],q[0],q[1])<14)return true;}return false;}

function floodClear(d,tol){const{width:w,height:h,data}=d;let r=0,g=0,b=0;[[0,0],[w-1,0],[0,h-1],[w-1,h-1]].forEach(([x,y])=>{const p=(y*w+x)*4;r+=data[p];g+=data[p+1];b+=data[p+2];});r/=4;g/=4;b/=4;
 const seen=new Uint8Array(w*h),st=[],push=i=>{if(seen[i])return;const p=i*4;if(Math.hypot(data[p]-r,data[p+1]-g,data[p+2]-b)<=tol*1.7){seen[i]=1;st.push(i);}};
 for(let x=0;x<w;x++){push(x);push((h-1)*w+x);}for(let y=0;y<h;y++){push(y*w);push(y*w+w-1);}
 while(st.length){const i=st.pop(),x=i%w,y=(i-x)/w;data[i*4+3]=0;if(x>0)push(i-1);if(x<w-1)push(i+1);if(y>0)push(i-w);if(y<h-1)push(i+w);}}
function maker(cfg,done){const m=el('div'),box=el('div'),pv=el('canvas'),f=el('input',{type:'file',accept:'image/*'}),tol=el('input',{type:'range',min:0,max:120,value:40}),cut=el('input',{type:'checkbox',checked:true}),
  nm=el('input',{placeholder:'Nom (ex : Fenêtre PVC 2 vantaux)',maxLength:60}),wi=el('input',{type:'number',placeholder:'Largeur réelle (cm)'}),hi=el('input',{type:'number',placeholder:'Hauteur réelle (cm)'}),sh=cfg.admin?el('input',{type:'checkbox'}):null;
 m.style.cssText='position:fixed;inset:0;background:#000b;z-index:9;overflow:auto;padding:16px';box.style.cssText='background:var(--card);color:var(--tx);max-width:620px;margin:0 auto;padding:16px;border-radius:10px';
 pv.style.cssText='max-width:100%;max-height:320px;border:1px solid #bbb;margin:8px 0;background:repeating-conic-gradient(#ccc 0 25%,#fff 0 50%) 0 0/20px 20px';pv.width=300;pv.height=120;
 let src=null;const render=()=>{if(!src)return;pv.width=src.width;pv.height=src.height;const c=pv.getContext('2d');c.drawImage(src,0,0);if(cut.checked){const d=c.getImageData(0,0,pv.width,pv.height);floodClear(d,+tol.value);c.putImageData(d,0,0);}};
 f.onchange=()=>{if(!f.files[0])return;const u=URL.createObjectURL(f.files[0]),i=new Image();i.onload=()=>{const k=Math.min(1,700/Math.max(i.naturalWidth,i.naturalHeight));src=el('canvas',{width:Math.round(i.naturalWidth*k),height:Math.round(i.naturalHeight*k)});src.getContext('2d').drawImage(i,0,0,src.width,src.height);URL.revokeObjectURL(u);render();};i.src=u;};
 tol.oninput=cut.onchange=render;
 const row=(t,x)=>{const r=el('div',{},t,x);r.style.margin='8px 0';return r;};
 const ok=el('button',{type:'button',textContent:'Enregistrer dans la bibliothèque',onclick:async()=>{
  if(!src||!nm.value.trim()){dlg.alert('Choisissez une image et donnez un nom.');return;}
  const w=pv.width,h=pv.height,d=pv.getContext('2d').getImageData(0,0,w,h).data;let x0=w,y0=h,x1=0,y1=0;
  for(let y=0;y<h;y++)for(let x=0;x<w;x++)if(d[(y*w+x)*4+3]>10){if(x<x0)x0=x;if(x>x1)x1=x;if(y<y0)y0=y;if(y>y1)y1=y;}
  if(x1<=x0||y1<=y0){dlg.alert('Objet vide : réduisez la tolérance.');return;}
  const o=el('canvas',{width:x1-x0+1,height:y1-y0+1});o.getContext('2d').drawImage(pv,x0,y0,o.width,o.height,0,0,o.width,o.height);
  const fd=new FormData();fd.append('t',cfg.t);fd.append('name',nm.value);fd.append('wcm',wi.value||0);fd.append('hcm',hi.value||0);if(sh&&sh.checked)fd.append('shared',1);fd.append('png',await new Promise(r=>o.toBlob(r,'image/png')),'o.png');
  ok.disabled=true;try{const j=await(await fetch('index.php?a=libsave',{method:'POST',body:fd})).json();if(j.ok){m.remove();done();dlg.alert('Objet ajouté à votre bibliothèque.','success');return;}dlg.alert('Échec : '+(j.err||''));}catch(e){dlg.alert('Échec réseau.');}ok.disabled=false;}});
 box.append(el('h3',{textContent:'Créer un objet à partir d’une image'}),el('p',{textContent:'Photo ou image d’une fenêtre, porte, volet… L’arrière-plan peut être retiré automatiquement.'}),f,pv,
  row('',el('label',{},cut,' Supprimer l’arrière-plan')),row('Tolérance : ',tol),nm,wi,hi);if(sh)box.append(row('',el('label',{},sh,' Partager avec tous les utilisateurs (admin)')));
 box.append(ok,el('button',{type:'button',textContent:'Annuler',onclick:()=>m.remove(),style:'margin-left:8px'}));m.append(box);document.body.append(m);}

function editor(root,cfg){
 const tools=TOOLS[cfg.c]||[];let img=null,changed=false,objs=(cfg.data&&cfg.data.objs)||[],sel=-1,tool='select',tmp=[],drag=null;
 const cv=el('canvas',{width:W,height:H});cv.style.cssText='width:100%;border:1px solid #bbb;touch-action:none;background:#fff;cursor:crosshair';const ctx=cv.getContext('2d');
 const bar=el('div'),bar2=el('div'),pp=el('div');bar.style.cssText=bar2.style.cssText='display:flex;flex-wrap:wrap;gap:6px;margin:8px 0';pp.style.cssText='min-height:44px;display:flex;flex-wrap:wrap;gap:8px;align-items:end';
 const btn=(p,t,f)=>{const b=el('button',{type:'button',textContent:t,className:(p===bar||p===bar2)?'ghost':''});b.onclick=f;p.append(b);return b;};
 const fi=el('input',{type:'file',accept:'image/*'});fi.hidden=true;fi.onchange=()=>fi.files[0]&&loadFile(fi.files[0]);
 const k0=()=>{const a=Math.min(1534/img.width,920/img.height);return{w:img.width*a,h:img.height*a,x:60+(1534-img.width*a)/2,y:60+(920-img.height*a)/2};};
 function draw(ex){const bw=cv.getBoundingClientRect().width||W;HK=Math.max(1,W/bw*.55);frame(ctx,cfg.meta,cfg.c,cfg.title);
  if(img){const b=k0();ctx.drawImage(img,b.x,b.y,b.w,b.h);}else{ctx.fillStyle='#888';ctx.font='30px Arial';ctx.textAlign='center';ctx.fillText('Importez, prenez en photo ou capturez une image',W/2,480);ctx.textAlign='left';}
  ctx.save();ctx.beginPath();ctx.rect(41,41,W-82,958);ctx.clip();objs.forEach((o,i)=>drawObj(ctx,o,!ex&&i===sel));
  if(!ex&&tmp.length){ctx.strokeStyle='#e00';ctx.lineWidth=4;ctx.beginPath();tmp.forEach(([x,y],i)=>i?ctx.lineTo(x,y):ctx.moveTo(x,y));ctx.stroke();tmp.forEach(([x,y])=>ctx.fillRect(x-5,y-5,10,10));}ctx.restore();}
 function setSrc(s,w,h){if(!w||!h)return;const k=Math.min(1,2400/Math.max(w,h)),c=el('canvas',{width:Math.round(w*k),height:Math.round(h*k)});c.getContext('2d').drawImage(s,0,0,c.width,c.height);img=c;changed=true;draw();}
 function loadFile(f){const u=URL.createObjectURL(f),i=new Image();i.onload=()=>{setSrc(i,i.naturalWidth,i.naturalHeight);URL.revokeObjectURL(u);};i.src=u;}
 async function cam(){try{const st=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}),v=el('video',{autoplay:true,playsInline:true,muted:true}),m=el('div');v.srcObject=st;m.style.cssText='position:fixed;inset:0;background:#000d;z-index:9;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px';v.style.cssText='max-width:95%;max-height:75%';
  const stop=()=>{st.getTracks().forEach(t=>t.stop());m.remove();};m.append(v,el('button',{type:'button',textContent:'Prendre la photo',onclick:()=>{setSrc(v,v.videoWidth,v.videoHeight);stop();}}),el('button',{type:'button',textContent:'Annuler',onclick:stop}));document.body.append(m);}
  catch(e){fi.setAttribute('capture','environment');fi.click();}}
 async function scr(){try{const st=await navigator.mediaDevices.getDisplayMedia({video:true}),v=el('video',{muted:true,playsInline:true});v.srcObject=st;await v.play();await new Promise(r=>setTimeout(r,600));setSrc(v,v.videoWidth,v.videoHeight);st.getTracks().forEach(t=>t.stop());}catch(e){dlg.alert('Capture impossible : '+e.message+'\nFaites une capture d’écran, puis collez-la ici avec Ctrl+V.');}}
 document.addEventListener('paste',e=>{const f=[...(e.clipboardData?.files||[])].find(x=>x.type.startsWith('image/'));if(f)loadFile(f);});
 btn(bar,'Importer une image',()=>{fi.removeAttribute('capture');fi.click();});btn(bar,'Appareil photo',cam);
 if(navigator.mediaDevices?.getDisplayMedia)btn(bar,'Capturer un onglet / écran',scr);
 if(/^(DP|AP|PD)1$/.test(cfg.c)){bar.append(el('a',{href:'https://www.google.com/maps',target:'_blank',rel:'noopener',textContent:'Ouvrir Google Maps ↗',className:'b'}),el('a',{href:'https://cadastre.gouv.fr/scpc/accueil.do',target:'_blank',rel:'noopener',textContent:'Ouvrir le cadastre ↗',className:'b'}),el('a',{href:'https://earth.google.com/web/',target:'_blank',rel:'noopener',textContent:'Vue aérienne (Google Earth) ↗',className:'b'}));}
 bar.append(el('small',{textContent:'Astuce : Ctrl+V colle une capture d’écran.'}));
 const setTool=t=>{tool=t;tmp=[];[...bar2.children].forEach(b=>b.style.outline=b.dataset.t===t?'3px solid #f59e0b':'');draw();};
 if(tools.length){const b=btn(bar2,'Sélection',()=>setTool('select'));b.dataset.t='select';tools.forEach(t=>{const x=btn(bar2,NAMES[t],()=>setTool(t));x.dataset.t=t;});}
 function props(){pp.innerHTML='';const o=objs[sel];if(!o)return;
  const inp=(l,v,f,ty='text')=>{const i=el('input',{type:ty,value:v});i.style.cssText='width:110px;margin:0';i.oninput=()=>{f(i.value);draw();};pp.append(el('label',{},l,el('br'),i));};
  if(o.t==='comp'||o.t==='img'){
   inp('Largeur (cm)',o.wcm,v=>{const n=+v;if(n>0&&o.wcm){o.w*=n/o.wcm;}o.wcm=n;},'number');inp('Hauteur (cm)',o.hcm,v=>{const n=+v;if(n>0&&o.hcm){o.h*=n/o.hcm;}o.hcm=n;},'number');
   inp('Rotation (°)',o.rot||0,v=>o.rot=+v||0,'number');inp('Étiquette',o.label,v=>o.label=v);if(o.t==='comp')inp('Couleur',o.color,v=>o.color=v,'color');
   const lk=el('input',{type:'checkbox',checked:!!o.lock});lk.onchange=()=>{o.lock=lk.checked;};pp.append(el('label',{},lk,'Garder les proportions'));
   if(o.t==='img')btn(pp,'Miroir',()=>{o.flip=!o.flip;draw();}).className='ghost';
   btn(pp,'Dupliquer',()=>{const k=JSON.parse(JSON.stringify(o));k.x+=30;k.y+=30;objs.push(k);sel=objs.length-1;props();draw();}).className='ghost';}
  if(o.t==='cote')inp('Valeur',o.txt,v=>o.txt=v);
  if(o.t==='text'){inp('Texte',o.txt,v=>o.txt=v);inp('Taille',o.size,v=>o.size=+v||30,'number');inp('Couleur',o.color||'#000000',v=>o.color=v,'color');}
  if(o.t==='poly')inp('Couleur',o.color||'#ee0000',v=>o.color=v,'color');
  btn(pp,'Supprimer',()=>del()).className='danger';}
 function hh(o,x,y){const[lx,ly]=toLocal(o,x,y),t=16*HK;if(Math.hypot(lx-(o.x+o.w/2),ly-(o.y-44*HK))<t)return 'rot';for(const[n,fx,fy]of HS)if(Math.abs(lx-(o.x+o.w*fx))<t&&Math.abs(ly-(o.y+o.h*fy))<t)return n;return null;}
 function resize(o,o0,h,x,y,keep){const[px,py]=toLocal(o0,x,y);let L=o0.x,T=o0.y,Rr=o0.x+o0.w,B=o0.y+o0.h;
  if(h.includes('e'))Rr=Math.max(L+20,px);if(h.includes('w'))L=Math.min(Rr-20,px);if(h.includes('s'))B=Math.max(T+20,py);if(h.includes('n'))T=Math.min(B-20,py);
  if(keep&&h.length===2){let k=Math.max((Rr-L)/o0.w,(B-T)/o0.h);k=Math.max(k,20/Math.min(o0.w,o0.h));const nw=o0.w*k,nh=o0.h*k;if(h.includes('e'))Rr=L+nw;else L=Rr-nw;if(h.includes('s'))B=T+nh;else T=B-nh;}
  const[wx,wy]=toWorld(o0,(L+Rr)/2,(T+B)/2);o.w=Rr-L;o.h=B-T;o.x=wx-o.w/2;o.y=wy-o.h/2;if(o0.wcm)o.wcm=Math.round(o0.wcm*o.w/o0.w);if(o0.hcm)o.hcm=Math.round(o0.hcm*o.h/o0.h);}
 function del(){if(sel<0)return;objs.splice(sel,1);sel=-1;props();draw();}
 document.addEventListener('keydown',e=>{if(e.key==='Delete'&&!/INPUT|TEXTAREA/.test(document.activeElement.tagName))del();if(e.key==='Enter'&&tool==='poly')closePoly();});
 function closePoly(){if(tmp.length>2){objs.push({t:'poly',pts:tmp,closed:true,color:'#ee0000'});sel=objs.length-1;}tmp=[];setTool('select');props();}
 REDRAW=()=>draw();
 const pos=e=>{const r=cv.getBoundingClientRect();return[(e.clientX-r.left)*W/r.width,(e.clientY-r.top)*H/r.height];};
 cv.onpointerdown=async e=>{const[x,y]=pos(e);
  if(tool==='select'){const s0=objs[sel];
   if(s0&&(s0.t==='comp'||s0.t==='img')){const h=hh(s0,x,y);if(h){drag={m:h==='rot'?'rot':'rs',h,o:{...s0}};cv.setPointerCapture(e.pointerId);return;}}
   if(s0&&s0.t==='poly'){const i=s0.pts.findIndex(([a,b])=>Math.hypot(a-x,b-y)<14*HK);if(i>=0){drag={m:'pt',i};cv.setPointerCapture(e.pointerId);return;}}
   if(s0&&s0.t==='cote'){const k=Math.hypot(s0.x1-x,s0.y1-y)<14*HK?1:Math.hypot(s0.x2-x,s0.y2-y)<14*HK?2:0;if(k){drag={m:'cp',k};cv.setPointerCapture(e.pointerId);return;}}
   sel=-1;for(let i=objs.length-1;i>=0;i--)if(hit(ctx,objs[i],x,y)){sel=i;break;}
   drag=sel>=0?{m:'mv',x,y,o:JSON.parse(JSON.stringify(objs[sel]))}:null;if(drag)cv.setPointerCapture(e.pointerId);props();draw();return;}
  if(tool==='poly'){if(tmp.length>2&&Math.hypot(x-tmp[0][0],y-tmp[0][1])<20)return closePoly();tmp.push([x,y]);draw();return;}
  if(tool==='cote'){tmp.push([x,y]);if(tmp.length===2){const t=await dlg.prompt('Valeur de la cote (ex : 6,00 m)','');objs.push({t:'cote',x1:tmp[0][0],y1:tmp[0][1],x2:x,y2:y,txt:t||''});sel=objs.length-1;setTool('select');props();}draw();return;}
  if(tool==='text'){const t=await dlg.prompt('Texte à afficher');if(t){objs.push({t:'text',x,y,txt:t,size:34,color:'#000000'});sel=objs.length-1;}setTool('select');props();return;}
  const k=KINDS[tool];if(k){objs.push({t:'comp',k:tool,x:x-k[1]/2,y:y-k[2]/2,w:k[1],h:k[2],wcm:k[4],hcm:k[5],color:k[3],label:''});sel=objs.length-1;setTool('select');props();}};
 cv.ondblclick=()=>{if(tool==='poly')closePoly();};
 cv.onpointermove=e=>{if(!drag||sel<0)return;const[x,y]=pos(e),o=objs[sel];
  if(drag.m==='pt')o.pts[drag.i]=[x,y];
  else if(drag.m==='cp'){if(drag.k===1){o.x1=x;o.y1=y;}else{o.x2=x;o.y2=y;}}
  else if(drag.m==='rot'){let a=Math.atan2(y-(o.y+o.h/2),x-(o.x+o.w/2))*180/Math.PI+90;a=((a+540)%360)-180;const q=Math.round(a/90)*90;if(e.shiftKey)a=Math.round(a/15)*15;else if(Math.abs(a-q)<4)a=q;o.rot=Math.round(a);props();}
  else if(drag.m==='rs'){resize(o,drag.o,drag.h,x,y,e.shiftKey||o.lock);props();}
  else{const dx=x-drag.x,dy=y-drag.y,s=drag.o;if(o.t==='poly')o.pts=s.pts.map(([a,b])=>[a+dx,b+dy]);else if(o.t==='cote'){o.x1=s.x1+dx;o.y1=s.y1+dy;o.x2=s.x2+dx;o.y2=s.y2+dy;}else{o.x=s.x+dx;o.y=s.y+dy;}}
  draw();};
 cv.onpointerup=()=>{drag=null;};
 function libPanel(box){box.style.cssText='border:1px solid #ddd;border-radius:8px;padding:8px;margin:8px 0';
  const head=el('div'),grid=el('div');grid.style.cssText='display:flex;flex-wrap:wrap;gap:8px;margin-top:6px';
  head.append(el('b',{textContent:'Bibliothèque d’objets  '}),el('button',{type:'button',textContent:'＋ Créer un objet à partir d’une image',onclick:()=>maker(cfg,load)}));box.append(head,grid);
  async function load(){let L=[];try{L=await(await fetch('index.php?a=lib')).json();}catch(e){}grid.innerHTML='';if(!L.length)grid.textContent='Aucun objet pour le moment : créez le vôtre à partir d’une photo de fenêtre, de porte, etc.';
   L.forEach(it=>{const w=el('div');w.style.cssText='position:relative;width:86px;text-align:center;font-size:11px;cursor:pointer';
    const im=el('img',{src:'index.php?a=libimg&id='+it.id,title:it.name});im.style.cssText='width:82px;height:82px;object-fit:contain;border:1px solid #ccc;background:repeating-conic-gradient(#ddd 0 25%,#fff 0 50%) 0 0/12px 12px';
    w.append(im,el('div',{textContent:it.name+(it.shared==1?' ★':'')}));
    w.onclick=()=>{const i=getImg(it.id),add=()=>{const ww=220,hh=ww*i.naturalHeight/i.naturalWidth;objs.push({t:'img',id:+it.id,x:W/2-ww/2,y:400,w:ww,h:hh,wcm:+it.wcm,hcm:+it.hcm,label:it.name});sel=objs.length-1;setTool('select');props();};i.complete&&i.naturalWidth?add():i.addEventListener('load',add,{once:true});};
    if(it.mine==1||cfg.admin){const x=el('span',{textContent:'✕',title:'Supprimer'});x.style.cssText='position:absolute;top:0;right:0;background:#fff;border:1px solid #ccc;padding:0 4px;color:#b00';
     x.onclick=async ev=>{ev.stopPropagation();if(!await dlg.confirm('Supprimer « '+it.name+' » de la bibliothèque ?',{type:'danger',ok:'Supprimer'}))return;const fd=new FormData();fd.append('t',cfg.t);fd.append('id',it.id);await fetch('index.php?a=libdel',{method:'POST',body:fd});load();};w.append(x);}
    grid.append(w);});}
  load();}
 const sv=btn(el('div'),'Enregistrer cette étape',async()=>{if(!img){dlg.alert('Ajoutez d’abord une image.');return;}sv.disabled=true;sv.textContent='Enregistrement…';await imgsReady(objs);draw(true);
  const sheet=await new Promise(r=>cv.toBlob(r,'image/jpeg',.88)),fd=new FormData();fd.append('t',cfg.t);fd.append('id',cfg.id);fd.append('c',cfg.c);fd.append('data',JSON.stringify({objs}));fd.append('sheet',sheet,'s.jpg');
  if(changed)fd.append('base',await new Promise(r=>img.toBlob(r,'image/jpeg',.9)),'b.jpg');
  try{const r=await fetch('index.php?a=save',{method:'POST',body:fd}),j=await r.json();if(j.ok)return location.href='index.php?a=dossier&id='+cfg.id;dlg.alert('Échec : '+(j.err||r.status));}catch(e){dlg.alert('Échec réseau.');}
  sv.disabled=false;sv.textContent='Enregistrer cette étape';draw();});
 const foot=el('div');foot.append(sv,el('a',{href:'index.php?a=dossier&id='+cfg.id,textContent:'Annuler',style:'margin-left:12px'}));
 const libBox=el('div');root.append(bar,bar2,libBox,cv,pp,foot,fi);if(LIB.includes(cfg.c))libPanel(libBox);
 if(cfg.base){const i=new Image();i.onload=()=>{img=i;draw();};i.src='index.php?a=img&id='+cfg.id+'&c='+cfg.c;}
 draw();}

function cover(c,m,list){c.fillStyle='#1f4fd8';c.fillRect(41,41,W-82,250);c.fillStyle='#fff';c.textBaseline='top';c.font='bold 66px Arial';c.fillText(HEAD[0],90,80);c.fillText(HEAD[1],90,160);c.font='26px Arial';c.fillText(HEAD[2]||'Dossier de demande déposé auprès de la mairie',90,244);
 c.fillStyle='#14213d';c.font='bold 44px Arial';wrap(c,m.titre,90,340,W-180,56);c.font='28px Arial';c.fillStyle='#5b6678';c.fillText('Adresse du projet : '+fit(c,m.adresse,1100),90,470);c.fillText('Parcelle cadastrée n° '+m.parcelle,90,512);c.fillText('Déclarant : '+m.owner,90,554);
 c.fillStyle='#14213d';c.font='bold 30px Arial';c.fillText('Pièces du dossier',90,640);c.fillRect(90,682,W-180,2);
 const lh=list.length>6?31:44;list.forEach((t,i)=>{const x=90,y=704+i*lh;c.fillStyle='#1f4fd8';c.fillRect(x,y+6,14,14);c.fillStyle='#14213d';c.font='25px Arial';c.fillText(fit(c,t,W-220),x+30,y);});}
function auto(a){const cfg=JSON.parse(a.dataset.cfg);if(!cfg.edit)return;
 const prep=async()=>{const ta=document.querySelector('textarea[name=description]'),desc=ta?ta.value:(cfg.desc||''),cv=el('canvas',{width:W,height:H}),c=cv.getContext('2d');
  const cv0=cfg.ty+'0';const up=async(code,title,lines,d)=>{frame(c,cfg.meta,code,title);if(code===cv0)cover(c,cfg.meta,cfg.list);else{c.fillStyle='#14213d';c.textBaseline='top';c.font='28px Arial';let y=80;lines.forEach(t=>{y=wrap(c,t,80,y,W-160,40);});}
   const fd=new FormData();fd.append('t',cfg.t);fd.append('id',cfg.id);fd.append('c',code);fd.append('data','{}');fd.append('sheet',await new Promise(r=>cv.toBlob(r,'image/jpeg',.88)),'s.jpg');if(d!==undefined)fd.append('desc',d);await fetch('index.php?a=save',{method:'POST',body:fd});};
  await up(cv0,'PAGE DE GARDE',[]);if(cfg.txt)await up(cfg.txt[0],cfg.txt[1],[desc||'(à compléter)'],desc);};
 document.querySelectorAll('.pdfgo').forEach(l=>l.addEventListener('click',async ev=>{ev.preventDefault();l.classList.add('loading');try{await prep();location.href=l.href;dlg.alert('Votre PDF est en cours de téléchargement.','success');}catch(x){dlg.alert('Préparation du PDF impossible : '+x.message,'danger');}l.classList.remove('loading');}));
 document.querySelectorAll('.submitgo').forEach(b=>b.form.addEventListener('submit',async ev=>{ev.preventDefault();b.disabled=true;try{await prep();ev.target.submit();}catch(x){b.disabled=false;b.classList.remove('loading');dlg.alert('Envoi impossible : '+x.message,'danger');}}));}
const r=$('#ed'),a=$('#auto');[r,a].forEach(x=>{if(x){const h=JSON.parse(x.dataset.cfg).head;if(h)HEAD=h;}});if(r)editor(r,JSON.parse(r.dataset.cfg));if(a)auto(a);
})();
