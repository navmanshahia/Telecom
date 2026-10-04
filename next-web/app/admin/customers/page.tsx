"use client";

import { useEffect, useMemo, useState } from "react";

type Status = "pending" | "approved" | "rejected" | "suspended" | "blocked";
type Customer = { id:number; name:string; email:string; phone:string; status:Status; createdAt:string };
const statuses: Status[] = ["pending","approved","rejected","suspended","blocked"];

export default function CustomersAdmin() {
  const [customers,setCustomers]=useState<Customer[]>([]);
  const [filter,setFilter]=useState<"all"|Status>("pending");
  const [loading,setLoading]=useState(true);

  async function load(){ setLoading(true); const r=await fetch("../api/customers",{cache:"no-store"}); const d=await r.json(); setCustomers(d.customers||[]); setLoading(false); }
  useEffect(()=>{ void load(); },[]);
  async function update(id:number,status:Status){
    const r=await fetch("../api/customers",{method:"PATCH",headers:{"content-type":"application/json"},body:JSON.stringify({id,status})});
    if(r.ok) setCustomers((items)=>items.map((x)=>x.id===id?{...x,status}:x));
  }
  const shown=useMemo(()=>filter==="all"?customers:customers.filter(x=>x.status===filter),[customers,filter]);
  const pending=customers.filter(x=>x.status==="pending").length;

  return <main style={{minHeight:"100vh",background:"#02070c",color:"#f4fbff",padding:"32px",fontFamily:"system-ui"}}>
    <div style={{maxWidth:1180,margin:"0 auto"}}>
      <a href="../" style={{color:"#61e8ff",textDecoration:"none"}}>← SecureLink</a>
      <p style={{marginTop:48,color:"#61e8ff",letterSpacing:2,fontSize:12}}>ACCESS CONTROL</p>
      <h1 style={{fontSize:"clamp(42px,7vw,82px)",margin:"8px 0 12px"}}>Customers</h1>
      <p style={{color:"#89a0ad"}}>{pending} customer{pending===1?"":"s"} waiting for approval.</p>
      <div style={{display:"flex",gap:8,flexWrap:"wrap",margin:"28px 0"}}>
        {(["all",...statuses] as const).map(s=><button key={s} onClick={()=>setFilter(s)} style={{padding:"10px 15px",borderRadius:999,border:"1px solid #28404d",background:filter===s?"#61e8ff":"transparent",color:filter===s?"#031014":"#cde7f2",cursor:"pointer"}}>{s[0].toUpperCase()+s.slice(1)}</button>)}
      </div>
      {loading?<p>Loading…</p>:<div style={{display:"grid",gridTemplateColumns:"repeat(auto-fit,minmax(280px,1fr))",gap:14}}>
        {shown.map(c=><article key={c.id} style={{border:"1px solid #18303d",borderRadius:20,padding:22,background:"#071019"}}>
          <small style={{color:"#61e8ff"}}>{c.status.toUpperCase()}</small><h2>{c.name}</h2><p style={{color:"#89a0ad"}}>{c.email}<br/>{c.phone}</p>
          <select value={c.status} onChange={e=>void update(c.id,e.target.value as Status)} style={{width:"100%",padding:12,borderRadius:10,background:"#02070c",color:"white",border:"1px solid #28404d"}}>
            {statuses.map(s=><option key={s} value={s}>{s[0].toUpperCase()+s.slice(1)}</option>)}
          </select>
        </article>)}
        {!shown.length&&<p style={{color:"#89a0ad"}}>No customers match this filter.</p>}
      </div>}
      <p style={{marginTop:30,color:"#526b78",fontSize:12}}>Prototype store: connect PostgreSQL/Auth before production use. This branch does not yet share the PHP customer database.</p>
    </div>
  </main>;
}
