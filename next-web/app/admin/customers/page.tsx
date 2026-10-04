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

  return <main className="admin-nxt">
    <div className="admin-nxt-shell">
      <a href="../" className="admin-back">← SECURELINK / COMMAND</a>
      <p className="admin-kicker">ACCESS CONTROL / LIVE</p>
      <h1>Customer<br/><span>access.</span></h1>
      <p className="admin-lede"><b>{pending}</b> customer{pending===1?"":"s"} waiting for approval.</p>
      <div className="admin-filters">
        {(["all",...statuses] as const).map(s=><button key={s} onClick={()=>setFilter(s)} className={filter===s?"active":""}>{s[0].toUpperCase()+s.slice(1)}</button>)}
      </div>
      {loading?<p>Loading…</p>:<div className="admin-customer-grid">
        {shown.map(c=><article key={c.id} className="admin-customer-card">
          <small>{c.status.toUpperCase()}</small><h2>{c.name}</h2><p className="muted">{c.email}<br/>{c.phone}</p>
          <select value={c.status} onChange={e=>void update(c.id,e.target.value as Status)} >
            {statuses.map(s=><option key={s} value={s}>{s[0].toUpperCase()+s.slice(1)}</option>)}
          </select>
        </article>)}
        {!shown.length&&<p className="muted">No customers match this filter.</p>}
      </div>}
      <p style={{marginTop:30,color:"#526b78",fontSize:12}}>Prototype store: connect PostgreSQL/Auth before production use. This branch does not yet share the PHP customer database.</p>
    </div>
  </main>;
}
