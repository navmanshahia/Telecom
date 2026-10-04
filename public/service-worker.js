const CACHE_NAME="securelink-static-v4";
const STATIC_ASSETS=[
  "./offline.html",
  "./assets/app.css",
  "./assets/portal.js",
  "./assets/app-icon.svg",
  "./manifest.webmanifest"
];

self.addEventListener("install",event=>{
  event.waitUntil(caches.open(CACHE_NAME).then(cache=>cache.addAll(STATIC_ASSETS)).catch(()=>null));
  self.skipWaiting();
});

self.addEventListener("activate",event=>{
  event.waitUntil(
    caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE_NAME).map(key=>caches.delete(key))))
      .then(()=>self.clients.claim())
  );
});

self.addEventListener("fetch",event=>{
  const request=event.request;
  if(request.method!=="GET") return;
  const url=new URL(request.url);

  // Never cache authenticated/dynamic HTML. Network first with an offline fallback only.
  if(request.mode==="navigate"){
    event.respondWith(fetch(request).catch(()=>caches.match("./offline.html")));
    return;
  }

  // Cache only same-origin static assets.
  if(url.origin===self.location.origin && (
    url.pathname.includes("/assets/") ||
    url.pathname.endsWith("/manifest.webmanifest")
  )){
    event.respondWith(
      caches.match(request).then(cached=>{
        const network=fetch(request).then(response=>{
          if(response && response.ok){
            const copy=response.clone();
            caches.open(CACHE_NAME).then(cache=>cache.put(request,copy));
          }
          return response;
        }).catch(()=>cached);
        return cached||network;
      })
    );
  }
});
