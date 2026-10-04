"use client";

import dynamic from "next/dynamic";
import { useEffect, useRef, useState } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

const HeroScene = dynamic(() => import("./HeroScene"), {
  ssr: false,
  loading: () => <div className="hero-canvas hero-canvas--loading" />,
});

const categories = [
  {
    id: "internet",
    eyebrow: "Pure speed",
    title: "Internet",
    body: "Private fibre offers, clear rewards and an application flow built around your address.",
    stat: "Up to 3 Gig",
  },
  {
    id: "mobility",
    eyebrow: "Always connected",
    title: "Mobility",
    body: "Compare BYOD, device and multi-line options without drowning in carrier fine print.",
    stat: "5G+",
  },
  {
    id: "tv",
    eyebrow: "One place",
    title: "TV + Streaming",
    body: "Build entertainment around what you actually watch, not a wall of confusing packages.",
    stat: "Flexible",
  },
  {
    id: "security",
    eyebrow: "Home aware",
    title: "Security",
    body: "Connected-home and security options presented with the same private, guided experience.",
    stat: "24 / 7",
  },
];

const steps = [
  ["01", "Request access", "Create your account. Private pricing stays protected."],
  ["02", "Compare intelligently", "See eligible services, rewards and important terms together."],
  ["03", "Submit securely", "Send the details required to review and process your request."],
  ["04", "Track activation", "Follow review, appointment and activation from one account."],
];

export default function Experience() {
  const root = useRef<HTMLDivElement>(null);
  const [activeCategory, setActiveCategory] = useState("internet");

  useEffect(() => {
    gsap.registerPlugin(ScrollTrigger);

    const reducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)"
    ).matches;

    if (reducedMotion) return;

    const ctx = gsap.context(() => {
      gsap.from(".nav-shell", {
        y: -24,
        opacity: 0,
        duration: 0.9,
        ease: "power3.out",
      });

      gsap.from(".hero-copy > *", {
        y: 32,
        opacity: 0,
        duration: 1,
        stagger: 0.09,
        ease: "power3.out",
        delay: 0.12,
      });

      gsap.to(".hero-orbit", {
        rotate: 190,
        scrollTrigger: {
          trigger: ".hero",
          start: "top top",
          end: "bottom top",
          scrub: 1,
        },
      });

      gsap.utils.toArray<HTMLElement>(".reveal").forEach((element) => {
        gsap.from(element, {
          y: 56,
          opacity: 0,
          duration: 1,
          ease: "power3.out",
          scrollTrigger: {
            trigger: element,
            start: "top 86%",
            once: true,
          },
        });
      });

      gsap.utils.toArray<HTMLElement>(".service-card").forEach((card, index) => {
        gsap.from(card, {
          y: 70 + index * 8,
          opacity: 0,
          scale: 0.96,
          duration: 0.95,
          ease: "power3.out",
          scrollTrigger: {
            trigger: card,
            start: "top 90%",
            once: true,
          },
        });
      });

      gsap.to(".signal-track", {
        scaleX: 1,
        ease: "none",
        scrollTrigger: {
          trigger: ".journey",
          start: "top 72%",
          end: "bottom 45%",
          scrub: true,
        },
      });
    }, root);

    return () => ctx.revert();
  }, []);

  return (
    <div ref={root} className="site-shell">
      <header className="nav-wrap">
        <nav className="nav-shell" aria-label="Primary">
          <a className="brand" href="#top" aria-label="SecureLink home">
            <span className="brand-mark">
              <i />
              <i />
              <i />
            </span>
            <span>SECURELINK</span>
          </a>

          <div className="nav-links">
            <a href="#services">Services</a>
            <a href="#experience">How it works</a>
            <a href="#referrals">Referrals</a>
          </div>

          <a className="nav-cta" href="#access">
            Request access
            <span>↗</span>
          </a>
        </nav>
      </header>

      <main id="top">
        <section className="hero">
          <HeroScene />
          <div className="hero-grid" />
          <div className="hero-noise" />
          <div className="hero-orbit" />

          <div className="hero-copy">
            <div className="status-pill">
              <span className="status-dot" />
              PRIVATE TELECOM ACCESS
            </div>

            <h1>
              Your connection.
              <span>Reimagined.</span>
            </h1>

            <p>
              Internet, mobility, entertainment and connected home offers in one
              guided private experience — from first comparison to activation.
            </p>

            <div className="hero-actions">
              <a className="button button--primary" href="#access">
                Explore private offers
                <span>↗</span>
              </a>
              <a className="button button--ghost" href="#experience">
                See how it works
              </a>
            </div>

            <div className="hero-metrics">
              <div>
                <strong>01</strong>
                <span>Private pricing</span>
              </div>
              <div>
                <strong>02</strong>
                <span>Human review</span>
              </div>
              <div>
                <strong>03</strong>
                <span>Order tracking</span>
              </div>
            </div>
          </div>

          <div className="scroll-cue">
            <span>SCROLL TO CONNECT</span>
            <i />
          </div>
        </section>

        <section className="section intro" id="services">
          <div className="section-heading reveal">
            <div>
              <span className="kicker">THE SIGNAL, SIMPLIFIED</span>
              <h2>One private portal.<br />Every connection.</h2>
            </div>
            <p>
              No generic catalogue. SecureLink is built around guided selection,
              transparent rewards and a cleaner path from interest to activation.
            </p>
          </div>

          <div className="category-switcher reveal" role="tablist">
            {categories.map((category) => (
              <button
                key={category.id}
                type="button"
                className={activeCategory === category.id ? "active" : ""}
                onClick={() => setActiveCategory(category.id)}
                role="tab"
                aria-selected={activeCategory === category.id}
              >
                {category.title}
              </button>
            ))}
          </div>

          <div className="service-grid">
            {categories.map((category, index) => (
              <article
                className={
                  "service-card " +
                  (activeCategory === category.id ? "service-card--active" : "")
                }
                key={category.id}
                onMouseEnter={() => setActiveCategory(category.id)}
              >
                <span className="card-number">0{index + 1}</span>
                <div className="service-icon">
                  <span />
                  <span />
                  <span />
                </div>
                <div className="service-content">
                  <small>{category.eyebrow}</small>
                  <h3>{category.title}</h3>
                  <p>{category.body}</p>
                </div>
                <div className="service-footer">
                  <strong>{category.stat}</strong>
                  <span>Explore ↗</span>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="section experience" id="experience">
          <div className="experience-panel reveal">
            <div className="experience-visual">
              <div className="device-frame">
                <div className="device-island" />
                <div className="device-screen">
                  <span className="mini-label">SECURELINK / ORDER</span>
                  <div className="signal-rings">
                    <i />
                    <i />
                    <i />
                    <b />
                  </div>
                  <div className="device-status">
                    <small>Activation status</small>
                    <strong>Appointment confirmed</strong>
                    <span>Next update will appear here</span>
                  </div>
                </div>
              </div>
              <div className="floating-chip chip-one">5G+</div>
              <div className="floating-chip chip-two">FIBRE</div>
              <div className="floating-chip chip-three">LIVE</div>
            </div>

            <div className="experience-copy">
              <span className="kicker">NOT JUST ANOTHER DEAL PAGE</span>
              <h2>A sales experience that stays connected after checkout.</h2>
              <p>
                Your request does not disappear into a form. The portal is designed
                to carry the relationship through review, processing, appointment,
                activation and referrals.
              </p>

              <div className="experience-points">
                <div><span>01</span><p>Approved customer access</p></div>
                <div><span>02</span><p>Offer + reward snapshots</p></div>
                <div><span>03</span><p>Application status timeline</p></div>
                <div><span>04</span><p>Customer-visible updates</p></div>
              </div>
            </div>
          </div>
        </section>

        <section className="section journey">
          <div className="section-heading reveal">
            <div>
              <span className="kicker">FROM SIGNAL TO SERVICE</span>
              <h2>Four moments.<br />One clean journey.</h2>
            </div>
          </div>

          <div className="journey-track">
            <div className="signal-track" />
            {steps.map(([number, title, description]) => (
              <article className="journey-step reveal" key={number}>
                <div className="step-node"><span>{number}</span></div>
                <h3>{title}</h3>
                <p>{description}</p>
              </article>
            ))}
          </div>
        </section>

        <section className="section referral" id="referrals">
          <div className="referral-card reveal">
            <div className="referral-glow" />
            <span className="kicker">SECURELINK REFERRALS</span>
            <h2>Good connections<br />should travel.</h2>
            <p>
              Refer friends or family, follow eligibility and keep payout status
              visible from one place.
            </p>

            <div className="reward-orb">
              <span>REWARD</span>
              <strong>$100</strong>
              <small>current promo*</small>
            </div>

            <a className="button button--light" href="#access">
              Enter referral portal
              <span>↗</span>
            </a>

            <small className="terms">
              *Program eligibility, retention requirements, dates and provider terms apply.
            </small>
          </div>
        </section>

        <section className="section access" id="access">
          <div className="access-card reveal">
            <div>
              <span className="kicker">PRIVATE ACCESS</span>
              <h2>Ready to see what’s available?</h2>
              <p>
                Request an account. Once approved, your eligible private offers
                and application tools live in one secure space.
              </p>
            </div>
            <div className="access-actions">
              <button className="button button--primary" type="button">
                Request access <span>↗</span>
              </button>
              <button className="text-button" type="button">
                Already approved? Sign in →
              </button>
            </div>
          </div>
        </section>
      </main>

      <footer>
        <a className="brand" href="#top">
          <span className="brand-mark"><i /><i /><i /></span>
          <span>SECURELINK</span>
        </a>
        <p>Private telecom access, thoughtfully connected.</p>
        <span>© 2026 SecureLink Tech Inc.</span>
      </footer>
    </div>
  );
}
