const C='dp-v7';
self.addEventListener('install',e=>{self.skipWaiting();});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==C).map(x=>caches.delete(x)))).then(()=>clients.claim()));});
self.addEventListener('fetch',e=>{const r=e.request,u=new URL(r.url);if(r.method!=='GET'||u.origin!==location.origin)return;
 if(u.pathname.includes('/assets/')&&/\.(css|js|png)$/.test(u.pathname)){e.respondWith(fetch(r).then(x=>{const c=x.clone();caches.open(C).then(k=>k.put(r,c));return x;}).catch(()=>caches.match(r,{ignoreSearch:true})));return;}
 if(r.mode==='navigate')e.respondWith(fetch(r).catch(()=>new Response('<meta charset=utf-8><meta name=viewport content="width=device-width"><body style="font-family:sans-serif;padding:2em"><h2>Hors ligne</h2><p>Reconnectez-vous à Internet pour accéder à vos dossiers.</p>',{headers:{'Content-Type':'text/html; charset=utf-8'}})));});
