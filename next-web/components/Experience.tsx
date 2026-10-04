"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import gsap from "gsap";

type Category = "internet" | "mobility" | "bundles";

const catalog = {
  internet: {
    telus: {
      eyebrow: "TELUS Top Offer",
      title: "PureFibre Internet 1.5 Gbps",
      subtitle: "Fast fibre internet for connected homes.",
      price: 95,
      regular: 110,
      accent: "PureFibre",
      features: ["1.5 Gbps download", "1.0 Gbps upload", "$200 bill credit", "No installation fee", "TELUS Internet Security included"],
    },
    rogers: {
      eyebrow: "Rogers Top Offer",
      title: "Rogers Ignite Internet 1.5 Gbps",
      subtitle: "Ultra-fast speeds for work, play and everything in between.",
      price: 89,
      regular: 105,
      accent: "Ignite",
      features: ["1.5 Gbps download", "1.0 Gbps upload", "$200 bill credit", "Free professional installation", "Rogers Security Suite included"],
    },
  },
  mobility: {
    telus: {
      eyebrow: "TELUS Mobility",
      title: "5G+ Complete 100GB",
      subtitle: "Premium 5G+ connectivity built for Canada and the U.S.",
      price: 45,
      regular: 80,
      accent: "5G+",
      features: ["100GB high-speed data", "Canada + U.S. use", "5G+ access", "$25 closing credit", "Bring your own device"],
    },
    rogers: {
      eyebrow: "Rogers Mobility",
      title: "5G+ Essential 100GB",
      subtitle: "Flexible mobile service with nationwide 5G coverage.",
      price: 45,
      regular: 65,
      accent: "5G+",
      features: ["100GB high-speed data", "Canada-wide calling", "5G access", "BYOD ready", "Roam options available"],
    },
  },
  bundles: {
    telus: {
      eyebrow: "TELUS Bundle",
      title: "PureFibre + Mobility",
      subtitle: "Home and mobile in one connected TELUS experience.",
      price: 120,
      regular: 150,
      accent: "Bundle",
      features: ["1.5 Gbps PureFibre", "100GB mobile data", "Bundle savings", "Private offer credits", "One guided order"],
    },
    rogers: {
      eyebrow: "Rogers Bundle",
      title: "Ignite + Wireless",
      subtitle: "Internet and wireless combined for simple monthly value.",
      price: 115,
      regular: 145,
      accent: "Bundle",
      features: ["1.5 Gbps Ignite Internet", "100GB wireless data", "Bundle savings", "Private offer credits", "One guided order"],
    },
  },
} as const;

const comparisonByCategory = {
  internet: [
    ["Monthly Price", "$95/month", "$89/month", "different"],
    ["Download Speed", "1.5 Gbps", "1.5 Gbps", "same"],
    ["Upload Speed", "1.0 Gbps", "1.0 Gbps", "same"],
    ["Data Allowance", "Unlimited", "Unlimited", "same"],
    ["Bill Credit", "$200 bill credit", "$200 bill credit", "same"],
    ["Installation", "No installation fee", "Free professional installation", "different"],
    ["Security / Extras", "TELUS Internet Security", "Rogers Security Suite", "different"],
    ["Contract Term", "24 months", "24 months", "same"],
    ["Customer Support", "24/7 support", "24/7 support", "same"],
  ],
  mobility: [
    ["Monthly Price", "$45/month", "$45/month", "same"],
    ["High-speed Data", "100GB", "100GB", "same"],
    ["Network", "5G+", "5G+", "same"],
    ["Roaming", "Canada + U.S.", "Canada-wide", "different"],
    ["Closing Credit", "$25", "Varies by offer", "different"],
    ["Device Type", "BYOD", "BYOD", "same"],
    ["Multi-line", "Available", "Available", "same"],
    ["Support", "TELUS Mobility support", "Rogers Wireless support", "different"],
  ],
  bundles: [
    ["Monthly Price", "$120/month", "$115/month", "different"],
    ["Home Internet", "1.5 Gbps PureFibre", "1.5 Gbps Ignite", "different"],
    ["Mobile Data", "100GB", "100GB", "same"],
    ["Bundle Savings", "Included", "Included", "same"],
    ["Private Credits", "Available", "Available", "same"],
    ["Installation", "TELUS guided install", "Rogers professional install", "different"],
    ["Order Flow", "One SecureLink request", "One SecureLink request", "same"],
  ],
} as const;

function ProviderMark({ provider }: { provider: "telus" | "rogers" }) {
  return (
    <div className={"provider-mark " + provider}>
      <span className="provider-symbol">{provider === "telus" ? "⌁" : "◉"}</span>
      <strong>{provider === "telus" ? "TELUS" : "ROGERS"}</strong>
    </div>
  );
}

export default function Experience() {
  const root = useRef<HTMLDivElement>(null);
  const [category, setCategory] = useState<Category>("internet");
  const [highlight, setHighlight] = useState(true);
  const [region, setRegion] = useState("British Columbia");
  const [notice, setNotice] = useState("");
  const current = useMemo(() => catalog[category], [category]);
  const comparisonRows = comparisonByCategory[category];

  useEffect(() => {
    const ctx = gsap.context(() => {
      gsap.from(".compare-nav", { y: -24, opacity: 0, duration: .7, ease: "power3.out" });
      gsap.from(".provider-hero > *", { y: 26, opacity: 0, duration: .8, stagger: .06, ease: "power3.out" });
      gsap.from(".offer-card", { y: 35, opacity: 0, scale: .985, duration: .8, stagger: .08, delay: .15, ease: "power3.out" });
    }, root);
    return () => ctx.revert();
  }, [category]);

  const handleOffer = (provider: string) => {
    setNotice(provider + " offer selected — continue in the SecureLink private portal to submit your request.");
    document.getElementById("comparison")?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  return (
    <div className="compare-app" ref={root}>
      <header className="compare-nav">
        <a className="securelink-logo" href="#top"><span className="link-icon">∞</span><b>SecureLink</b></a>
        <div className="nav-divider" />
        <strong className="compare-title">Compare Offers</strong>
        <nav className="category-tabs" aria-label="Offer categories">
          {(["internet","mobility","bundles"] as Category[]).map((item) => (
            <button key={item} className={category === item ? "active" : ""} onClick={() => setCategory(item)}>
              <span>{item === "internet" ? "⌁" : item === "mobility" ? "▯" : "◇"}</span>
              {item[0].toUpperCase() + item.slice(1)}
            </button>
          ))}
        </nav>
        <label className="region-picker">
          <span>⌖</span>
          <select value={region} onChange={(e) => setRegion(e.target.value)} aria-label="Province">
            <option>British Columbia</option>
            <option>Alberta</option>
            <option>Ontario</option>
          </select>
        </label>
      </header>

      <main id="top">
        <section className="provider-split">
          <article className="provider-hero telus-theme">
            <div className="brand-watermark telus-wave" />
            <ProviderMark provider="telus" />
            <h1>Smarter<br /><span>connections</span><br />for what matters.</h1>
            <p>Fast, reliable internet and mobile plans to keep your home and life connected.</p>
            <div className="hero-benefits">
              <span>♧ <b>Built for real life</b></span><span>⌂ <b>Reliable coverage</b></span><span>♟ <b>Great for everyone</b></span>
            </div>
            <div className="telus-device-scene" aria-hidden="true">
              <div className="leaf l1" /><div className="leaf l2" /><div className="leaf l3" />
              <div className="router tall">TELUS</div><div className="router mini" />
              <div className="laptop"><i /></div>
            </div>
          </article>

          <article className="provider-hero rogers-theme">
            <div className="rogers-ribbons" />
            <ProviderMark provider="rogers" />
            <h1>Powering more<br />possibilities for<br />every Canadian.</h1>
            <p>Fast, reliable internet and mobile plans built for the ways you live today.</p>
            <div className="hero-benefits">
              <span>ϟ <b>Ultra-fast speeds</b></span><span>◈ <b>Built for work & play</b></span><span>♟ <b>A stronger network</b></span>
            </div>
            <div className="rogers-device-scene" aria-hidden="true">
              <div className="rogers-modem">ROGERS</div><div className="monitor"><i /></div><div className="gamepad">✣</div><div className="speaker" />
            </div>
          </article>

          <div className="vs-badge"><b>VS</b><small>See how<br />they compare</small><span>⌄</span></div>

          <div className="split-offers">
            <OfferCard provider="telus" plan={current.telus} onSelect={() => handleOffer("TELUS")} />
            <OfferCard provider="rogers" plan={current.rogers} onSelect={() => handleOffer("Rogers")} />
          </div>
        </section>

        {notice && <div className="selection-notice" role="status">{notice}<button onClick={() => setNotice("")}>×</button></div>}

        <section className="comparison-shell" id="comparison">
          <div className="comparison-head">
            <div><span className="chart-icon">▥</span><h2>Plan Comparison</h2><p>See the key differences between these offers side by side.</p></div>
            <label className="difference-toggle">
              <input type="checkbox" checked={highlight} onChange={(e)=>setHighlight(e.target.checked)} />
              <span />
              Highlight differences
            </label>
          </div>

          <div className="comparison-layout">
            <div className="comparison-table-wrap">
              <div className="table-provider-head">
                <span>Feature</span>
                <div className="telus-col"><ProviderMark provider="telus" /><b>{current.telus.title}</b></div>
                <div className="rogers-col"><ProviderMark provider="rogers" /><b>{current.rogers.title}</b></div>
              </div>
              <div className="comparison-rows">
                {comparisonRows.map(([feature,telus,rogers,state]) => (
                  <div className={"comparison-row " + (highlight && state === "different" ? "is-different" : "")} key={feature}>
                    <strong>{feature}</strong><span>{telus}</span><span>{rogers}{feature === "Monthly Price" && category !== "mobility" && <i>Lower price</i>}</span>
                  </div>
                ))}
              </div>
            </div>

            <aside className="recommendation">
              <div className="recommendation-title"><span>✨</span><div><h3>Which offer is right for you?</h3><p>Both are strong options. Here is a quick guide.</p></div></div>
              <div className="recommend-card telus-rec">
                <ProviderMark provider="telus" />
                <b>Choose TELUS if you want…</b>
                <ul><li>No installation fee</li><li>Advanced Wi-Fi/security extras</li><li>A softer, home-first experience</li></ul>
              </div>
              <div className="recommend-card rogers-rec">
                <ProviderMark provider="rogers" />
                <b>Choose Rogers if you want…</b>
                <ul><li>A lower monthly price</li><li>Professional installation</li><li>A bold entertainment-first experience</li></ul>
              </div>
            </aside>
          </div>
        </section>
      </main>
    </div>
  );
}

function OfferCard({ provider, plan, onSelect }: {
  provider: "telus" | "rogers";
  plan: { eyebrow: string; title: string; subtitle: string; price: number; regular: number; accent: string; features: readonly string[] };
  onSelect: () => void;
}) {
  return (
    <article className={"offer-card " + provider}>
      <div className="offer-topline"><span>★ {plan.eyebrow}</span><b>{plan.accent}</b></div>
      <h2>{plan.title}</h2>
      <p>{plan.subtitle}</p>
      <div className="offer-body">
        <div className="offer-price"><strong>${plan.price}</strong><span>/month</span><small>for 24 months<br />Regular ${plan.regular}/month</small></div>
        <ul>{plan.features.map((feature, i)=><li key={feature}><span>{i < 2 ? "⌁" : i === 2 ? "▣" : "✓"}</span>{feature}</li>)}</ul>
      </div>
      <button className="provider-cta" onClick={onSelect}>View {provider === "telus" ? "TELUS" : "Rogers"} Offer <span>→</span></button>
    </article>
  );
}
