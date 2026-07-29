# Development Roadmap
## v1.0 → v2.0 → v3.0

| | |
|---|---|
| Document | 10-Roadmap.md |
| Related | [01-PRD](01-PRD.md) · [11-Development-Checklist](11-Development-Checklist.md) |

## v1.0 — Foundation: "Diet & Transformasi 90 Hari" on the AI Health Coach Platform

Goal: ship the platform architecture (Rule Engine + Knowledge Base + multi-provider AI + Coach layer) with the Diet program as the first fully working module, so later modules are additive, not architectural rewrites.

- Core/Auth/RBAC (Admin, Coach, Member)
- Onboarding wizard (~55 questions)
- Rule Engine (calorie/macro/workout/water/disease-restriction rules)
- Knowledge Base v1 content (foods, exercises, diseases, articles, FAQ)
- Multi-provider AI layer (OpenAI, Claude, Groq, Gemini, Ollama, LM Studio) with per-user settings
- Program generation pipeline + AI Memory + automatic bounded adjustments
- Progress tracking + Health Score
- Coach module (assignment, dashboard, chat, notes, review/override)
- Admin panel (users, AI config, rule engine, KB, analytics)
- Achievements (basic)
- Subscription schema + plan gating (no live payment gateway yet)
- PWA-ready responsive web app

**Definition of done for v1.0**: a new Member can register, complete onboarding, receive a generated 90-day program, check in daily, see the program adapt after a week, and a Coach can see and act on a flagged concern — all backed by at least 2 working AI provider integrations (one cloud, one local) to prove the abstraction isn't theoretical.

## v1.1 — Payments & Notification Channels

- Live payment gateway integration (Stripe/Xendit/Midtrans — pick primary per target market)
- Push notification channel (web push at minimum; native push once v2 mobile ships)
- WhatsApp/email reminder channel option
- Subscription lifecycle automation (renewal, dunning, cancellation flows)
- Referral/promo code support on `plans`/`subscriptions`

## v2.0 — Expansion Modules & Native Apps

- Additional program categories activated on the existing `programs` schema: Bulking, Marathon/Running, Diabetes Management, Hypertension Management, Cholesterol Management, Gout Management, Intermittent Fasting, Vegetarian
- Wearable integrations (Apple Health, Google Fit) feeding `weight_logs`/`sleep_logs`/activity data automatically
- Native Android/iOS apps (React Native or Flutter) consuming the existing `/api/v1` namespace — no backend rewrite required per the API-first design in [04-Architecture.md](04-Architecture.md) §11
- Coach marketplace features: public coach profiles, member-initiated coach selection/booking
- Advanced analytics: cohort retention, AI provider quality comparison (which provider's recommendations get applied vs. rejected most)
- Prenatal (Ibu Hamil) and Senior (Lansia) program categories, each with dedicated KB disease/nutrition content and Rule Engine rule sets

## v3.0 — Platform & Scale

- Multi-tenant / white-label offering (additive `tenant_id` migration per [04-Architecture.md](04-Architecture.md) §7)
- Corporate/enterprise wellness accounts (bulk member provisioning, aggregate reporting for HR)
- Marketplace-style content contributions (third-party KB content packs, reviewed/approved by Admin)
- Expanded AI capabilities: computer-vision meal photo logging (auto-estimate calories from a photo), voice check-in
- Search infrastructure upgrade (Meilisearch) if KB/content volume outgrows MySQL full-text
- Formal compliance certification pass (ISO 27001 / SOC 2 track) if enterprise sales require it

## Cross-Cutting: API Versioning Policy

- `/api/v1` is supported for a minimum of 12 months after `/api/v2` ships.
- Breaking changes (removed fields, changed auth) require a version bump; additive changes (new optional fields, new endpoints) do not.

## Cross-Cutting: AI Provider Additions

Adding a new provider at any point in the roadmap is always: implement `AIProviderInterface`, register in `ai_providers`, no core service changes — this is the architectural payoff of the interface pattern and should never require a "roadmap item" of its own beyond the adapter implementation.
