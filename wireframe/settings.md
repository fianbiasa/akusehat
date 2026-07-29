# Wireframe — Settings

Related: [docs/09-UI-UX-Wireframe.md](../docs/09-UI-UX-Wireframe.md) · [06-AI-Provider-Interface.md](../docs/06-AI-Provider-Interface.md) §8

## Layout

```
┌───────────────────────────────────────────────────────┐
│  Settings                                               │
│  ┌───────────┬──────────────────────────────────────┐  │
│  │ Profil     │                                       │  │
│  │ AI Provider│   AI Provider                          │  │
│  │ Notifikasi │   ┌─────────────────────────────────┐ │  │
│  │ Langganan  │   │ ( ) OpenAI                        │ │  │
│  │ Keamanan   │   │     API Key: [________________]  │ │  │
│  │            │   │     Model:   [GPT-5.5        ▾]  │ │  │
│  │            │   │                                   │ │  │
│  │            │   │ (•) Groq                          │ │  │
│  │            │   │     API Key: [________________]  │ │  │
│  │            │   │     Model:   [Llama 4         ▾]  │ │  │
│  │            │   │     [Default] ✓                   │ │  │
│  │            │   │                                   │ │  │
│  │            │   │ ( ) Claude    ( ) Gemini           │ │  │
│  │            │   │ ( ) Ollama (Local)  Base URL: [__] │ │  │
│  │            │   │ ( ) LM Studio       Base URL: [__] │ │  │
│  │            │   │                                   │ │  │
│  │            │   │  [Test Koneksi]   [Simpan]         │ │  │
│  │            │   └─────────────────────────────────┘ │  │
│  └───────────┴──────────────────────────────────────┘  │
└───────────────────────────────────────────────────────┘
```

## AI Provider Tab Behaviors

- Selecting a radio expands that provider's fields (API key for cloud providers, Base URL for local providers per `ai_providers.type`).
- "Test Koneksi" fires `POST /ai/settings/{id}/test` and shows latency + a short success/failure toast — lets a Member confirm a local Ollama/LM Studio server is reachable before relying on it.
- API keys are write-only in the UI: once saved, the field re-renders masked (`sk-••••••3f2a`) and is never re-fetched in plaintext (`user_ai_settings.api_key_encrypted` is never returned decrypted via the API).
- Only one provider can be marked Default at a time; selecting a new default silently unsets the previous one.

## Other Tabs (brief)

- **Profil**: editable health/lifestyle fields captured at onboarding (re-uses the same input components as the wizard, one screen instead of stepped).
- **Notifikasi**: per-`reminders.type` toggle + time picker, plus channel preference (in-app/email; push in v1.1).
- **Langganan**: current `plans` tier, usage against `max_programs`, upgrade CTA, `payments` history.
- **Keamanan**: password change, active sessions list, account deletion (maps to the soft-delete + data export flow noted in [01-PRD.md](../docs/01-PRD.md) §13).
