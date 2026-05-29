const root=document.documentElement,btn=document.getElementById('themeBtn');
const saved=localStorage.getItem('theme')||'dark'; root.dataset.theme=saved; btn.textContent=saved==='dark'?'🌙 Gelap':'☀️ Terang';
btn.onclick=()=>{const t=root.dataset.theme==='dark'?'light':'dark';root.dataset.theme=t;localStorage.setItem('theme',t);btn.textContent=t==='dark'?'🌙 Gelap':'☀️ Terang'};
const input=document.getElementById('image'),drop=document.getElementById('drop'),preview=document.getElementById('preview');
input?.addEventListener('change',()=>{const f=input.files[0]; if(!f)return; preview.src=URL.createObjectURL(f); drop.classList.add('has-img');});
['dragenter','dragover'].forEach(ev=>drop?.addEventListener(ev,e=>{e.preventDefault();drop.style.transform='scale(1.01)'}));
['dragleave','drop'].forEach(ev=>drop?.addEventListener(ev,e=>{e.preventDefault();drop.style.transform=''}));
drop?.addEventListener('drop',e=>{input.files=e.dataTransfer.files; input.dispatchEvent(new Event('change'));});
