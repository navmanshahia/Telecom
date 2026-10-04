# SecureLink — Next / Three / GSAP

This directory is the new cinematic SecureLink web experience built separately from the legacy PHP portal.

## Stack

- Next.js App Router
- React + TypeScript
- Three.js through React Three Fiber
- React Three Drei
- GSAP + ScrollTrigger
- CSS visual system
- Next.js route handlers for future API work

## Local development

```bash
cd next-web
npm install
npm run dev
```

Open http://localhost:3000.

## Production build

```bash
npm install
npm run build
npm start
```

## Current experience

The first version contains:

- Responsive glass navigation
- Real-time Three.js network orb
- Animated fibre/network node visualization
- GSAP hero and scroll reveals
- Interactive service-category cards
- Cinematic customer journey section
- Order-status phone concept
- Referral experience
- Private-access CTA
- Health endpoint at `/api/health`

## Architecture direction

Public experience:
`Next.js + R3F + GSAP`

Application:
`Next.js + TypeScript`

Database target:
`PostgreSQL`

The existing PHP application on other branches is intentionally left untouched. Authentication, CRM, order processing and database migration will be connected in later production stages.

## Deployment

Best fits:
- Vercel
- Cloudflare (with an appropriate Next adapter)
- Node-capable VPS/PaaS

Traditional PHP-only cPanel hosting will not run this Next.js app unless the hosting plan includes Node.js application support.
