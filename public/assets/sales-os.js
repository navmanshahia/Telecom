(()=>{'use strict';
const body=document.body;if(!body||!String(body.dataset.page||'').startsWith('sales'))return;
const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
const touch=matchMedia('(pointer:coarse)').matches;
const lite=reduce||touch||matchMedia('(max-width:820px)').matches||(navigator.deviceMemory&&navigator.deviceMemory<=4);
body.classList.add('sales-os');if(lite)body.classList.add('sales-performance-lite');

const progress=document.createElement('div');progress.id='sales-page-progress';progress.setAttribute('aria-hidden','true');document.body.append(progress);
const setProgress=()=>{const d=document.documentElement,h=Math.max(1,d.scrollHeight-innerHeight);progress.style.width=Math.min(100,Math.max(0,scrollY/h*100))+'%'};addEventListener('scroll',setProgress,{passive:true});setProgress();

if(!touch&&!reduce){const glow=document.createElement('div');glow.className='sales-cursor-glow';glow.setAttribute('aria-hidden','true');document.body.append(glow);let gx=innerWidth/2,gy=innerHeight/2,tx=gx,ty=gy;addEventListener('pointermove',e=>{tx=e.clientX;ty=e.clientY},{passive:true});const gloop=()=>{gx+=(tx-gx)*.12;gy+=(ty-gy)*.12;glow.style.left=gx+'px';glow.style.top=gy+'px';requestAnimationFrame(gloop)};gloop();}

document.querySelectorAll('[data-spotlight]').forEach(el=>el.addEventListener('pointermove',e=>{const r=el.getBoundingClientRect();el.style.setProperty('--mx',((e.clientX-r.left)/r.width*100)+'%');el.style.setProperty('--my',((e.clientY-r.top)/r.height*100)+'%')}));

if(!lite){
 document.querySelectorAll('.tilt-card,.sales-v6-launch a,.sales-customer-card').forEach(el=>{
   el.addEventListener('pointermove',e=>{const r=el.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;el.style.transform='perspective(900px) rotateX('+(-y*5)+'deg) rotateY('+(x*7)+'deg) translateY(-2px)'});
   el.addEventListener('pointerleave',()=>{el.style.transform=''});
 });
 document.querySelectorAll('.magnetic').forEach(el=>{
   el.addEventListener('pointermove',e=>{const r=el.getBoundingClientRect(),x=e.clientX-(r.left+r.width/2),y=e.clientY-(r.top+r.height/2);el.style.transform='translate('+x*.10+'px,'+y*.14+'px)'});
   el.addEventListener('pointerleave',()=>el.style.transform='');
 });
}

const initMotion=()=>{
 if(window.gsap&&!reduce){
   try{if(window.ScrollTrigger)gsap.registerPlugin(ScrollTrigger);
     gsap.from('body[data-page^="sales"] .nav',{y:-18,opacity:0,duration:.55,ease:'power2.out'});
     gsap.utils.toArray('.reveal').forEach((el,i)=>gsap.fromTo(el,{opacity:0,y:28},{opacity:1,y:0,duration:.72,delay:Math.min(i*.025,.15),ease:'power3.out',scrollTrigger:window.ScrollTrigger?{trigger:el,start:'top 90%',once:true}:undefined}));
     gsap.utils.toArray('.sales-v6-launch a,.sales-customer-card,.sales-script-grid .card,.sales-search-results .card').forEach((el,i)=>gsap.from(el,{opacity:0,y:18,scale:.985,duration:.5,delay:Math.min(i*.035,.22),ease:'power2.out',scrollTrigger:window.ScrollTrigger?{trigger:el,start:'top 94%',once:true}:undefined}));
   }catch(e){body.classList.add('sales-os-ready');}
 }else body.classList.add('sales-os-ready');
};
if(document.readyState==='complete')initMotion();else addEventListener('load',initMotion,{once:true});
setTimeout(()=>body.classList.add('sales-os-ready'),800);

function startShader(canvas){
 if(!canvas||lite)return;
 const gl=canvas.getContext('webgl2',{antialias:false,alpha:true,powerPreference:'low-power'});if(!gl)return;
 const vs=gl.createShader(gl.VERTEX_SHADER);gl.shaderSource(vs,'#version 300 es\nin vec2 p;void main(){gl_Position=vec4(p,0.,1.);}');gl.compileShader(vs);
 const fs=gl.createShader(gl.FRAGMENT_SHADER);gl.shaderSource(fs,`#version 300 es
 precision highp float;out vec4 o;uniform vec2 r;uniform float t;uniform vec2 m;
 float h(vec2 p){return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453123);}
 float n(vec2 p){vec2 i=floor(p),f=fract(p);f=f*f*(3.-2.*f);return mix(mix(h(i),h(i+vec2(1,0)),f.x),mix(h(i+vec2(0,1)),h(i+vec2(1,1)),f.x),f.y);}
 void main(){vec2 uv=(gl_FragCoord.xy-.5*r)/min(r.x,r.y);float d=length(uv);float a=atan(uv.y,uv.x);float wave=sin(d*17.-t*1.25+a*2.5)*.5+.5;float fog=n(uv*3.2+vec2(t*.035,-t*.025));vec2 mp=(m-.5)*vec2(r.x/r.y,1.);float pointer=exp(-length(uv-mp)*3.8);vec3 c=vec3(.035,.045,.075);c+=vec3(.34,.16,.76)*wave*.16*(1.-smoothstep(.1,1.3,d));c+=vec3(.08,.55,.48)*fog*.09;c+=vec3(.30,.16,.62)*pointer*.22;float vign=1.-smoothstep(.35,1.35,d);o=vec4(c*(.45+.75*vign),.92);}`);
 gl.compileShader(fs);
 const pr=gl.createProgram();gl.attachShader(pr,vs);gl.attachShader(pr,fs);gl.bindAttribLocation(pr,0,'p');gl.linkProgram(pr);if(!gl.getProgramParameter(pr,gl.LINK_STATUS))return;gl.useProgram(pr);
 const buf=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,buf);gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,3,-1,-1,3]),gl.STATIC_DRAW);gl.enableVertexAttribArray(0);gl.vertexAttribPointer(0,2,gl.FLOAT,false,0,0);
 const ur=gl.getUniformLocation(pr,'r'),ut=gl.getUniformLocation(pr,'t'),um=gl.getUniformLocation(pr,'m');let mx=.5,my=.5,raf=0;
 canvas.addEventListener('pointermove',e=>{const b=canvas.getBoundingClientRect();mx=(e.clientX-b.left)/b.width;my=1-(e.clientY-b.top)/b.height},{passive:true});
 const resize=()=>{const dpr=Math.min(devicePixelRatio||1,1.5),w=Math.max(2,Math.floor(canvas.clientWidth*dpr)),h=Math.max(2,Math.floor(canvas.clientHeight*dpr));if(canvas.width!==w||canvas.height!==h){canvas.width=w;canvas.height=h;gl.viewport(0,0,w,h)}};
 const draw=ms=>{resize();gl.uniform2f(ur,canvas.width,canvas.height);gl.uniform1f(ut,ms*.001);gl.uniform2f(um,mx,my);gl.drawArrays(gl.TRIANGLES,0,3);raf=requestAnimationFrame(draw)};raf=requestAnimationFrame(draw);
 document.addEventListener('visibilitychange',()=>{if(document.hidden&&raf){cancelAnimationFrame(raf);raf=0}else if(!document.hidden&&!raf)raf=requestAnimationFrame(draw)});
}
document.querySelectorAll('[data-sales-gl]').forEach(startShader);
})();