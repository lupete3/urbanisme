(()=>{const r=document.documentElement,g=()=>{try{return localStorage.getItem('theme')}catch(e){return null}};
const set=t=>{r.dataset.theme=t;r.style.colorScheme=t;};
set(g()||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'));
document.addEventListener('click',e=>{if(!e.target.closest('.th'))return;const t=r.dataset.theme==='dark'?'light':'dark';set(t);try{localStorage.setItem('theme',t)}catch(x){}});})();
