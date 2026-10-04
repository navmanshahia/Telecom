document.documentElement.classList.add("js-ready");

document.addEventListener("DOMContentLoaded",()=>{
  const fine=matchMedia("(pointer:fine)").matches;
  const reduce=matchMedia("(prefers-reduced-motion: reduce)").matches;

  if(fine&&!reduce){
    document.querySelectorAll(".deal-card,.feature-card,.shop-card,.provider-story").forEach(card=>{
      card.addEventListener("pointermove",e=>{
        const r=card.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;
        card.style.transform=`perspective(950px) rotateX(${-y*4}deg) rotateY(${x*5}deg) translateY(-5px)`;
      });
      card.addEventListener("pointerleave",()=>card.style.transform="");
    });
  }

  const revealEls=[...document.querySelectorAll(".card,.deal-toolbar,.portal-cta,.experience-strip,.shop-card,.provider-story,.compare-offer,.how-it-works li")];
  if("IntersectionObserver" in window&&!reduce){
    const reveal=new IntersectionObserver(entries=>entries.forEach(entry=>{
      if(entry.isIntersecting){entry.target.classList.add("revealed");reveal.unobserve(entry.target);}
    }),{threshold:.1,rootMargin:"0px 0px -25px"});
    revealEls.forEach(el=>reveal.observe(el));
  }else{
    revealEls.forEach(el=>el.classList.add("revealed"));
  }

  const toast=document.querySelector(".toast");
  if(toast)setTimeout(()=>{toast.style.opacity="0";toast.style.transform="translateY(-8px)";setTimeout(()=>toast.remove(),250)},2600);

  document.querySelectorAll(".multi-step-form").forEach(form=>{
    const key=form.dataset.storageKey||"securelink-apply";
    const steps=[...form.querySelectorAll(".form-step")];
    const dots=[...document.querySelectorAll(".step-dot")];
    let current=1,maxReached=1;

    const safeFields=[...form.querySelectorAll("[data-persist]")].filter(el=>!el.matches("[data-sensitive]"));
    try{
      const saved=JSON.parse(sessionStorage.getItem(key)||"{}");
      safeFields.forEach(el=>{
        if(!(el.name in saved))return;
        if(el.type==="checkbox")el.checked=!!saved[el.name];
        else el.value=saved[el.name];
      });
    }catch(e){}

    const persist=()=>{
      const data={};
      safeFields.forEach(el=>data[el.name]=el.type==="checkbox"?el.checked:el.value);
      try{sessionStorage.setItem(key,JSON.stringify(data));}catch(e){}
    };
    safeFields.forEach(el=>{el.addEventListener("input",persist);el.addEventListener("change",persist);});

    const validateStep=n=>{
      const panel=form.querySelector(`.form-step[data-step="${n}"]`);
      if(!panel)return true;
      const fields=[...panel.querySelectorAll("input,select,textarea")].filter(el=>!el.disabled);
      for(const el of fields){
        el.classList.remove("field-error");
        if(!el.checkValidity()){
          el.classList.add("field-error");
          el.reportValidity();
          el.focus({preventScroll:true});
          el.scrollIntoView({behavior:reduce?"auto":"smooth",block:"center"});
          return false;
        }
      }
      return true;
    };

    const textValue=name=>{
      const el=form.elements[name];
      if(!el)return "";
      if(el.type==="checkbox")return el.checked?"Yes":"No";
      if(el.tagName==="SELECT")return el.selectedOptions[0]?.text||"";
      return String(el.value||"").trim();
    };

    const buildReview=()=>{
      const box=form.querySelector(".review-summary");
      if(!box)return;
      const fields=[
        ["Email",textValue("email")],["Phone",textValue("phone")],
        ["Service address",textValue("address")],["Porting number",textValue("current_phone")],
        ["Current provider",textValue("current_provider")],["Referred by",textValue("referred_by")]
      ].filter(([,v])=>v);
      box.innerHTML=fields.length?fields.map(([k,v])=>`<div class="review-row"><span>${escapeHtml(k)}</span><b>${escapeHtml(v)}</b></div>`).join(""):'<p class="muted">Your selected offer and secure details are ready to submit.</p>';
    };

    const show=n=>{
      current=Math.max(1,Math.min(steps.length,n));
      maxReached=Math.max(maxReached,current);
      steps.forEach(s=>s.classList.toggle("active",Number(s.dataset.step)===current));
      dots.forEach(d=>{
        const n=Number(d.dataset.stepTarget);
        d.classList.toggle("active",n===current);
        d.setAttribute("aria-current",n===current?"step":"false");
        d.disabled=n>maxReached;
      });
      if(current===4)buildReview();
      form.closest(".application-card")?.scrollIntoView({behavior:reduce?"auto":"smooth",block:"start"});
    };

    form.addEventListener("click",e=>{
      const next=e.target.closest(".next-step");
      const prev=e.target.closest(".prev-step");
      if(next){if(validateStep(current)){persist();show(current+1);}return;}
      if(prev){show(current-1);return;}
    });
    dots.forEach(dot=>dot.addEventListener("click",()=>{const n=Number(dot.dataset.stepTarget);if(n<=maxReached){if(n<current||validateStep(current))show(n);}}));

    const port=form.querySelector('input[name="porting"]');
    const portFields=form.querySelector(".porting-fields");
    const syncPort=()=>{if(portFields)portFields.hidden=!!port&&!port.checked;};
    if(port){port.addEventListener("change",syncPort);syncPort();}

    form.addEventListener("submit",e=>{
      if(current!==4){e.preventDefault();if(validateStep(current))show(Math.min(4,current+1));return;}
      if(!validateStep(current)){e.preventDefault();return;}
      persist();
      try{sessionStorage.setItem("securelink-last-application-key",key);}catch(err){}
      const submit=form.querySelector('button[type="submit"]');
      if(submit){submit.disabled=true;submit.textContent="Submitting…";}
    });

    show(1);
  });

  if(document.querySelector("[data-clear-application-storage]")){
    try{
      const key=sessionStorage.getItem("securelink-last-application-key");
      if(key)sessionStorage.removeItem(key);
      sessionStorage.removeItem("securelink-last-application-key");
    }catch(e){}
  }

  const sortable=document.querySelector("[data-deal-sortable]");
  if(sortable){
    let dragging=null;
    const cards=()=>[...sortable.querySelectorAll("[data-deal-id]")];
    const syncOrder=()=>{
      const input=document.querySelector(".deal-order-input");
      if(input)input.value=cards().map(x=>x.dataset.dealId).join(",");
    };
    cards().forEach(card=>{
      const handle=card.querySelector(".drag-handle");
      card.draggable=false;
      handle?.addEventListener("pointerdown",()=>card.draggable=true);
      card.addEventListener("dragstart",e=>{
        if(!card.draggable){e.preventDefault();return;}
        dragging=card;card.classList.add("dragging");
        e.dataTransfer.effectAllowed="move";
      });
      card.addEventListener("dragend",()=>{
        card.classList.remove("dragging");card.draggable=false;dragging=null;syncOrder();
      });
    });
    sortable.addEventListener("dragover",e=>{
      if(!dragging)return;
      e.preventDefault();
      const after=[...sortable.querySelectorAll("[data-deal-id]:not(.dragging)")].reduce((closest,child)=>{
        const box=child.getBoundingClientRect(),offset=e.clientY-box.top-box.height/2;
        return offset<0&&offset>closest.offset?{offset,element:child}:closest;
      },{offset:Number.NEGATIVE_INFINITY,element:null}).element;
      if(after)sortable.insertBefore(dragging,after);else sortable.appendChild(dragging);
    });
    document.querySelector(".deal-order-form")?.addEventListener("submit",syncOrder);
    syncOrder();
  }

  // Progressive Web App: static-only service worker and install experience.
  if("serviceWorker" in navigator){
    window.addEventListener("load",()=>navigator.serviceWorker.register("service-worker.js").catch(()=>{}));
  }
  let deferredInstallPrompt=null;
  const installButtons=[...document.querySelectorAll("[data-install-app]")];
  const installHint=document.querySelector("[data-install-hint]");
  const isStandalone=window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone===true;
  const isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
  if(isStandalone){
    installButtons.forEach(btn=>btn.hidden=true);
  }else if(isIOS){
    installButtons.forEach(btn=>{
      btn.hidden=false;
      btn.addEventListener("click",()=>{if(installHint){installHint.hidden=false;installHint.scrollIntoView({behavior:"smooth",block:"nearest"});}});
    });
  }else{
    installButtons.forEach(btn=>btn.hidden=true);
    window.addEventListener("beforeinstallprompt",e=>{
      e.preventDefault();deferredInstallPrompt=e;
      installButtons.forEach(btn=>btn.hidden=false);
    });
    installButtons.forEach(btn=>btn.addEventListener("click",async()=>{
      if(!deferredInstallPrompt)return;
      deferredInstallPrompt.prompt();
      await deferredInstallPrompt.userChoice.catch(()=>null);
      deferredInstallPrompt=null;
      installButtons.forEach(b=>b.hidden=true);
    }));
    window.addEventListener("appinstalled",()=>installButtons.forEach(btn=>btn.hidden=true));
  }

  const networkToast=message=>{
    const el=document.createElement("div");el.className="toast network-toast";el.setAttribute("role","status");el.textContent=message;
    document.body.appendChild(el);setTimeout(()=>el.remove(),2600);
  };
  window.addEventListener("offline",()=>networkToast("You’re offline. SecureLink will reconnect when your network returns."));
  window.addEventListener("online",()=>networkToast("Back online."));

  document.querySelectorAll(".compare-picker select").forEach(select=>{
    select.addEventListener("change",()=>select.form?.requestSubmit());
  });

  document.querySelectorAll("form[data-confirm]").forEach(form=>{
    form.addEventListener("submit",e=>{
      const message=form.dataset.confirm||"Continue?";
      if(!window.confirm(message))e.preventDefault();
    });
  });

  document.querySelectorAll("form:not(.multi-step-form)").forEach(form=>{
    form.addEventListener("submit",()=>{
      if(!form.checkValidity())return;
      const button=form.querySelector('button[type="submit"],button:not([type])');
      if(button&&!button.dataset.keepEnabled){
        button.dataset.originalText=button.textContent;
        button.disabled=true;
        button.textContent=button.dataset.loadingText||"Working…";
      }
    });
  });
});

function escapeHtml(value){
  return String(value).replace(/[&<>"']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[ch]));
}
