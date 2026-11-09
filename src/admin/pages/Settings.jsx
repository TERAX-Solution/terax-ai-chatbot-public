import { KeyProblemNotice } from "../components/KeyProblemNotice";
import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import { CheckCircle2, RefreshCw, Save, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { api } from "../lib/api";
import { t } from "../lib/i18n";
import { onContrast } from "../../frontend/color";
import { useApi } from "../lib/useApi";
import { useSettings } from "../lib/useSettings";
import {
  ErrorBox,
  Field,
  Loading,
  PageHeader,
  Section,
  Select,
  Spinner,
} from "../components/ui";
import { UpgradeModal } from "../components/UpgradeModal";
import { IndustryPicker } from "../components/IndustryPicker";
import { plansWith } from "../lib/planFacts";
import { cn } from "@/lib/utils";

const TABS = [
  { id: "ai", label: t("AI model") },
  { id: "assistant", label: t("Assistant") },
  { id: "booking", label: t("Booking") },
  { id: "privacy", label: t("Privacy") },
];

const LANGS = [
  { value: "auto", label: t("Auto – reply in the visitor's language") },
  { value: "German", label: t("Always German") },
  { value: "English", label: t("Always English") },
];

export default function Settings() {
  const { tab = "ai" } = useParams();
  const navigate = useNavigate();
  const s = useSettings();
  const providers = useApi("/providers");
  const license = useApi("/license");

  if (s.error) return <ErrorBox message={s.error} />;
  if (!s.draft || !providers.data || !license.data) return <Loading />;

  const features = license.data.features || {};

  return (
    <>
      <PageHeader
        title={t("Settings")}
        description={t(
          "Configure the AI model, the assistant's behaviour and appointment booking.",
        )}
        actions={
          <Button onClick={() => s.save()} disabled={!s.dirty || s.saving}>
            {s.saving ? (
              <Spinner className="mr-2" />
            ) : (
              <Save className="mr-2 h-4 w-4" />
            )}
            {t("Save changes")}
          </Button>
        }
      />

      <div className="mb-6 inline-flex rounded-lg border bg-white p-1">
        {TABS.map((item) => (
          <button
            key={item.id}
            onClick={() => navigate(`/settings/${item.id}`)}
            className={cn(
              "rounded-md px-4 py-1.5 text-sm font-medium",
              tab === item.id
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:text-foreground",
            )}>
            {item.label}
          </button>
        ))}
      </div>

      {tab === "ai" && (
        <AiTab s={s} providers={providers.data} features={features} />
      )}
      {tab === "assistant" && <AssistantTab s={s} features={features} />}
      {tab === "booking" && <BookingTab s={s} />}
      {tab === "privacy" && <PrivacyTab s={s} features={features} />}
    </>
  );
}

/** `compact` hides the generation tuning (used by the setup wizard). */
export function AiTab({ s, providers, features, compact = false }) {
  const { draft, set, newKeys, setNewKeys } = s;
  const ai = draft.ai;
  const provider = providers.find((p) => p.id === ai.provider) || providers[0];
  const [models, setModels] = useState(provider?.models || []);
  const [loadingModels, setLoadingModels] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState(null);
  const [upgradeFeature, setUpgradeFeature] = useState(null);

  useEffect(() => {
    setModels(provider?.models || []);
    setTestResult(null);
  }, [ai.provider, ai.mode]);

  const keyHint = ai.api_keys?.[ai.provider] || "";
  // Managed-only plans (Praxis and up) have no own-key option.
  const managed = ai.mode === "managed" || features.byok === false;

  const requestBody = () => ({
    provider: ai.provider,
    mode: ai.mode,
    model: ai.model,
    api_key: newKeys[ai.provider] || "",
    base_url: ai.base_url,
  });

  async function refreshModels() {
    setLoadingModels(true);
    try {
      const r = await api("/providers/models", {
        method: "POST",
        body: requestBody(),
      });
      setModels(r.models);
      toast.success(t("%s models found", r.models.length));
    } catch (e) {
      toast.error(e.message);
    } finally {
      setLoadingModels(false);
    }
  }

  async function test() {
    setTesting(true);
    setTestResult(null);
    try {
      const r = await api("/providers/test", {
        method: "POST",
        body: requestBody(),
      });
      setTestResult({
        ok: true,
        text: t("Connected to %s in %s ms", r.model, r.latency_ms),
      });
    } catch (e) {
      setTestResult({ ok: false, text: e.message });
    } finally {
      setTesting(false);
    }
  }

  return (
    <>
      <UpgradeModal
        feature={upgradeFeature}
        onClose={() => setUpgradeFeature(null)}
      />
      <Section
        title={t("Connection")}
        description={t(
          "Use your own API key, or let TERAX manage the AI for you (no key needed).",
        )}>
        <div className="grid gap-3 sm:grid-cols-2">
          {[
            {
              id: "byok",
              title: t("Own API key"),
              text: t("You pay the AI vendor directly."),
            },
            {
              id: "managed",
              title: t("Managed AI by TERAX"),
              text: t("Included in %s plans.", plansWith("managed_ai")),
              locked: !features.managed_ai,
            },
          ]
            .filter((m) => m.id !== "byok" || features.byok !== false)
            .map((m) => (
              <button
                key={m.id}
                aria-haspopup={m.locked ? "dialog" : undefined}
                onClick={() =>
                  m.locked
                    ? setUpgradeFeature("managed_ai")
                    : set("ai.mode", m.id)
                }
                className={cn(
                  "rounded-lg border p-4 text-left transition-colors",
                  m.locked && "opacity-70",
                  (managed ? "managed" : "byok") === m.id
                    ? "border-primary bg-primary/5 ring-1 ring-primary"
                    : "hover:bg-muted",
                )}>
                <p className="font-medium">
                  {m.title}{" "}
                  {m.locked && (
                    <span className="ml-1 text-xs text-amber-700">
                      {t("(upgrade)")}
                    </span>
                  )}
                </p>
                <p className="text-xs text-muted-foreground">{m.text}</p>
              </button>
            ))}
        </div>

        <div className="grid gap-5 sm:grid-cols-2">
          <Field label={t("Provider")} htmlFor="provider">
            <Select
              id="provider"
              value={ai.provider}
              onChange={(v) => {
                set("ai.provider", v);
                const p = providers.find((x) => x.id === v);
                if (p?.models?.length) set("ai.model", p.models[0]);
              }}
              options={providers.map((p) => ({
                value: p.id,
                label: t(p.label),
              }))}
            />
          </Field>

          {!managed && (
            <KeyProblemNotice
              problem={s.options?.key_problems?.[ai.provider]}
              className="mb-4"
            />
          )}

          {!managed && (
            <Field
              label={t("API key")}
              htmlFor="apikey"
              help={
                keyHint
                  ? t("Saved key %s. Leave empty to keep it.", keyHint)
                  : t("Stored encrypted. Never shown to visitors.")
              }>
              <div className="flex gap-2">
                <Input
                  id="apikey"
                  type="password"
                  autoComplete="off"
                  placeholder={keyHint || "sk-…"}
                  value={
                    newKeys[ai.provider] &&
                    newKeys[ai.provider] !== "__delete__"
                      ? newKeys[ai.provider]
                      : ""
                  }
                  onChange={(e) =>
                    setNewKeys({ ...newKeys, [ai.provider]: e.target.value })
                  }
                />
                {keyHint && (
                  <Button
                    variant="outline"
                    size="icon"
                    title={t("Remove saved key")}
                    onClick={() =>
                      setNewKeys({ ...newKeys, [ai.provider]: "__delete__" })
                    }>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                )}
              </div>
            </Field>
          )}

          {provider?.base_url && !managed && (
            <Field
              label={t("Base URL")}
              htmlFor="baseurl"
              help={t(
                "e.g. https://api.groq.com/openai/v1 or http://localhost:11434/v1 (Ollama)",
              )}>
              <Input
                id="baseurl"
                value={ai.base_url}
                onChange={(e) => set("ai.base_url", e.target.value)}
              />
            </Field>
          )}

          <Field
            label={t("Model")}
            htmlFor="model"
            help={t(
              "Pick from the list or type any model ID – new models work without a plugin update.",
            )}>
            <div className="flex gap-2">
              <Input
                id="model"
                list="terax-models"
                value={ai.model}
                onChange={(e) => set("ai.model", e.target.value)}
              />
              <datalist id="terax-models">
                {models.map((m) => (
                  <option key={m} value={m} />
                ))}
              </datalist>
              <Button
                variant="outline"
                size="icon"
                title={t("Load models from provider")}
                onClick={refreshModels}
                disabled={loadingModels}>
                {loadingModels ? (
                  <Spinner />
                ) : (
                  <RefreshCw className="h-4 w-4" />
                )}
              </Button>
            </div>
          </Field>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Button variant="secondary" onClick={test} disabled={testing}>
            {testing ? (
              <Spinner className="mr-2" />
            ) : (
              <CheckCircle2 className="mr-2 h-4 w-4" />
            )}
            {t("Test connection")}
          </Button>
          {testResult && (
            <span
              className={cn(
                "text-sm",
                testResult.ok ? "text-emerald-700" : "text-red-700",
              )}>
              {testResult.text}
            </span>
          )}
        </div>
      </Section>

      {!compact && (
        <Section title={t("Generation")}>
          <div className="grid gap-5 sm:grid-cols-2">
            <Field
              label={t("Creativity (temperature): %s", ai.temperature)}
              help={t("Low = factual and consistent. 0.2–0.4 recommended.")}>
              <input
                type="range"
                min="0"
                max="1"
                step="0.1"
                value={ai.temperature}
                onChange={(e) =>
                  set("ai.temperature", parseFloat(e.target.value))
                }
                className="w-full accent-[hsl(var(--primary))]"
              />
            </Field>
            <Field label={t("Max answer length (tokens)")} htmlFor="maxtok">
              <Input
                id="maxtok"
                type="number"
                min="100"
                max="4000"
                value={ai.max_tokens}
                onChange={(e) =>
                  set("ai.max_tokens", parseInt(e.target.value || "0", 10))
                }
              />
            </Field>
          </div>
        </Section>
      )}
    </>
  );
}

function AssistantTab({ s, features }) {
  const a = s.draft.assistant;
  const set = (k, v) => s.set(`assistant.${k}`, v);
  const [upgradeFeature, setUpgradeFeature] = useState(null);
  const promptLocked = !features.custom_prompt;
  // Sales mode needs the plan feature and an industry that allows it (never dental).
  const salesIndustry =
    s.options?.industries?.[a.industry]?.sales_allowed !== false;
  const salesAvailable = !!features.sales_mode && salesIndustry;

  return (
    <>
      <UpgradeModal
        feature={upgradeFeature}
        onClose={() => setUpgradeFeature(null)}
      />
      <Section title={t("Industry")}>
        <IndustryPicker s={s} />
      </Section>

      <Section title={t("Assistant mode")}>
        {/* A disabled select swallows clicks, so a plan lock opens the upgrade popup from the wrapper. */}
        <Field
          label={t("Mode")}
          htmlFor="mode"
          locked={features.sales_mode ? null : plansWith("sales_mode")}
          help={
            !salesIndustry
              ? t(
                  "Not available for medical practices (advertising rules). The assistant stays informative.",
                )
              : t(
                  "Informative: answers questions and shows the booking button on request. Sales: also points gently to the next step and offers a contact form when a visitor is interested.",
                )
          }>
          <div
            onClick={
              !features.sales_mode
                ? () => setUpgradeFeature("sales_mode")
                : undefined
            }>
            <Select
              id="mode"
              value={salesAvailable ? a.mode : "informative"}
              disabled={!salesAvailable}
              onChange={(v) => set("mode", v)}
              options={[
                { value: "informative", label: t("Informative (neutral)") },
                {
                  value: "sales",
                  label: t(
                    "Sales (guides visitors to sign up, captures leads)",
                  ),
                },
              ]}
            />
          </div>
        </Field>
      </Section>

      <Section title={t("Call-back requests")}>
        <div className="flex items-center justify-between gap-4">
          <div>
            <p className="font-medium">{t("Offer a call-back form")}</p>
            <p className="text-xs text-muted-foreground">
              {features.callback_requests
                ? t(
                    "Visitors can ask for a call back when the assistant cannot answer, from a button in the chat header, and instead of a booking button when none is set up. You find the requests under Requests.",
                  )
                : t(
                    "Call-back requests are part of the %s plans.",
                    plansWith("callback_requests"),
                  )}
            </p>
          </div>
          <div
            onClick={
              !features.callback_requests
                ? () => setUpgradeFeature("callback_requests")
                : undefined
            }>
            <Switch
              checked={!!features.callback_requests && a.callback !== false}
              disabled={!features.callback_requests}
              onCheckedChange={(v) => set("callback", v)}
            />
          </div>
        </div>
      </Section>

      <Section title={t("Visibility")}>
        <div className="flex items-center justify-between">
          <div>
            <p className="font-medium">{t("Show chat on website")}</p>
            <p className="text-xs text-muted-foreground">
              {t(
                "Floating button on all pages. Use [terax_ai_chat] to embed it inline.",
              )}
            </p>
          </div>
          <Switch
            checked={a.enabled}
            onCheckedChange={(v) => set("enabled", v)}
          />
        </div>
      </Section>

      <Section title={t("Identity")}>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field label={t("Assistant name")} htmlFor="name">
            <Input
              id="name"
              value={a.name}
              onChange={(e) => set("name", e.target.value)}
            />
          </Field>
          <Field
            label={t("Business type")}
            htmlFor="btype"
            help={t(
              "Helps the AI understand context, e.g. “dental practice”.",
            )}>
            <Input
              id="btype"
              value={a.business_type}
              onChange={(e) => set("business_type", e.target.value)}
            />
          </Field>
          <Field label={t("Phone (shown for urgent cases)")} htmlFor="phone">
            <Input
              id="phone"
              value={a.phone}
              onChange={(e) => set("phone", e.target.value)}
            />
          </Field>
          <Field label={t("Email")} htmlFor="email">
            <Input
              id="email"
              value={a.email}
              onChange={(e) => set("email", e.target.value)}
            />
          </Field>
          <Field label={t("Answer language")} htmlFor="lang">
            <Select
              id="lang"
              value={a.language}
              onChange={(v) => set("language", v)}
              options={LANGS}
            />
          </Field>
        </div>
        <Field
          label={t("Greeting")}
          htmlFor="greeting"
          help={t("Markdown supported.")}>
          <Textarea
            id="greeting"
            rows={4}
            value={a.greeting}
            onChange={(e) => set("greeting", e.target.value)}
          />
        </Field>
        <Field
          label={t("Suggested questions")}
          htmlFor="sugg"
          help={t("One per line. Shown as buttons before the first message.")}>
          <Textarea
            id="sugg"
            rows={3}
            value={(a.suggestions || []).join("\n")}
            onChange={(e) => set("suggestions", e.target.value.split("\n"))}
            onBlur={(e) =>
              set(
                "suggestions",
                e.target.value
                  .split("\n")
                  .map((x) => x.trim())
                  .filter(Boolean),
              )
            }
          />
        </Field>
        <Field
          label={t("Extra instructions for the AI")}
          htmlFor="instr"
          locked={features.custom_prompt ? null : plansWith("custom_prompt")}
          help={t(
            "e.g. “We do not treat children under 6.” Safety rules (no diagnoses, emergency → phone) always apply.",
          )}>
          {/* Read-only (not disabled) so a click can open the upgrade popup; disabled fields swallow clicks. */}
          <Textarea
            id="instr"
            rows={4}
            readOnly={promptLocked}
            aria-haspopup={promptLocked ? "dialog" : undefined}
            className={promptLocked ? "cursor-pointer opacity-70" : undefined}
            value={a.instructions}
            onClick={
              promptLocked
                ? () => setUpgradeFeature("custom_prompt")
                : undefined
            }
            onChange={(e) => set("instructions", e.target.value)}
          />
        </Field>
      </Section>

      <Section title={t("Appearance")}>
        <div className="grid gap-5 sm:grid-cols-3">
          <Field label={t("Brand color")} htmlFor="color">
            <div className="flex gap-2">
              <input
                id="color"
                type="color"
                value={a.primary_color}
                onChange={(e) => set("primary_color", e.target.value)}
                className="h-9 w-12 cursor-pointer rounded border"
              />
              <Input
                value={a.primary_color}
                onChange={(e) => set("primary_color", e.target.value)}
              />
            </div>
          </Field>
          <Field
            label={t("Text color on brand color")}
            htmlFor="onbrand"
            help={
              onContrast(a.primary_color, a.text_on_brand) < 4.5
                ? t(
                    "Contrast %s:1 is below the recommended 4.5:1 (WCAG AA / BFSG). Some visitors may find the text hard to read.",
                    onContrast(a.primary_color, a.text_on_brand),
                  )
                : t(
                    "Contrast: %s:1 (4.5:1 or more is recommended).",
                    onContrast(a.primary_color, a.text_on_brand),
                  )
            }>
            <Select
              id="onbrand"
              value={a.text_on_brand}
              onChange={(v) => set("text_on_brand", v)}
              options={[
                { value: "auto", label: t("Automatic (most readable)") },
                { value: "light", label: t("White") },
                { value: "dark", label: t("Dark") },
              ]}
            />
          </Field>
          <Field label={t("Position")} htmlFor="pos">
            <Select
              id="pos"
              value={a.position}
              onChange={(v) => set("position", v)}
              options={[
                { value: "right", label: t("Bottom right") },
                { value: "left", label: t("Bottom left") },
              ]}
            />
          </Field>
          <Field
            label={t("Auto-open after (seconds)")}
            htmlFor="auto"
            help={t("0 = never.")}>
            <Input
              id="auto"
              type="number"
              min="0"
              value={a.auto_open_secs}
              onChange={(e) =>
                set("auto_open_secs", parseInt(e.target.value || "0", 10))
              }
            />
          </Field>
        </div>
      </Section>
    </>
  );
}

export function BookingTab({ s }) {
  const b = s.draft.booking;
  const set = (k, v) => s.set(`booking.${k}`, v);
  const allPresets = s.options?.booking_presets || {};
  // Only the booking systems that fit the chosen industry (plus the one already selected).
  const allowed =
    s.options?.industries?.[s.draft.assistant.industry]?.booking_presets;
  const presets = Object.fromEntries(
    Object.entries(allPresets).filter(
      ([id]) => !allowed || allowed.includes(id) || id === b.preset,
    ),
  );

  return (
    <Section
      title={t("Appointment booking")}
      description={t(
        "When a visitor wants an appointment, the assistant shows a button that opens your existing online booking.",
      )}>
      <div className="flex items-center justify-between">
        <p className="font-medium">{t("Enable booking button")}</p>
        <Switch
          checked={b.enabled}
          onCheckedChange={(v) => set("enabled", v)}
        />
      </div>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label={t("Booking system")} htmlFor="preset">
          <Select
            id="preset"
            value={b.preset}
            onChange={(v) => set("preset", v)}
            options={Object.entries(presets).map(([value, p]) => ({
              value,
              label: t(p.label),
            }))}
          />
        </Field>
        <Field
          label={t("Booking URL")}
          htmlFor="burl"
          help={t(presets[b.preset]?.hint)}>
          <Input
            id="burl"
            type="url"
            value={b.url}
            placeholder={t(presets[b.preset]?.hint)}
            onChange={(e) => set("url", e.target.value)}
          />
        </Field>
        <Field label={t("Button label")} htmlFor="blabel">
          <Input
            id="blabel"
            value={b.label}
            onChange={(e) => set("label", e.target.value)}
          />
        </Field>
        <Field
          label={t("Open in")}
          htmlFor="openin"
          help={t(
            "Some booking sites block embedding – use “New tab” if the popup stays blank.",
          )}>
          <Select
            id="openin"
            value={b.open_in}
            onChange={(v) => set("open_in", v)}
            options={[
              { value: "new_tab", label: t("New tab") },
              { value: "modal", label: t("Popup over the page") },
            ]}
          />
        </Field>
      </div>
    </Section>
  );
}

function PrivacyTab({ s, features }) {
  const p = s.draft.privacy;
  const set = (k, v) => s.set(`privacy.${k}`, v);

  return (
    <Section
      title={t("Privacy & GDPR")}
      description={t(
        "Visitors must accept the notice before chatting. IP addresses are only stored as salted hashes.",
      )}>
      <div className="flex items-center justify-between">
        <div>
          <p className="font-medium">{t("Store conversations")}</p>
          <p className="text-xs text-muted-foreground">
            {t(
              "Lets you review questions in “Conversations”. Off = only anonymous counts.",
            )}
          </p>
        </div>
        <Switch
          checked={p.log_conversations}
          disabled={!features.conversation_log}
          onCheckedChange={(v) => set("log_conversations", v)}
        />
      </div>
      <div className="flex items-center justify-between">
        <div>
          <p className="font-medium">
            {t("Mask phone numbers and e-mails in stored messages")}
          </p>
          <p className="text-xs text-muted-foreground">
            {t(
              "Stored copies show [Telefon] / [E-Mail]. The AI still receives the original message. Best effort, names are not detected.",
            )}
          </p>
        </div>
        <Switch
          checked={p.mask_pii}
          onCheckedChange={(v) => set("mask_pii", v)}
        />
      </div>
      <div className="flex items-center justify-between">
        <div>
          <p className="font-medium">
            {t("E-mail me when the AI keeps failing")}
          </p>
          <p className="text-xs text-muted-foreground">
            {t(
              "After 5 failed answers in a row (e.g. expired key or no credit). At most one e-mail per day, sent to the site admin address.",
            )}
          </p>
        </div>
        <Switch
          checked={s.draft.ai.notify_email}
          onCheckedChange={(v) => s.set("ai.notify_email", v)}
        />
      </div>
      <div className="grid gap-5 sm:grid-cols-3">
        <Field label={t("Delete conversations after (days)")} htmlFor="ret">
          <Input
            id="ret"
            type="number"
            min="1"
            value={p.retention_days}
            onChange={(e) =>
              set("retention_days", parseInt(e.target.value || "0", 10))
            }
          />
        </Field>
        <Field label={t("Max messages per visitor / hour")} htmlFor="rl">
          <Input
            id="rl"
            type="number"
            min="0"
            value={p.rate_limit_per_hour}
            onChange={(e) =>
              set("rate_limit_per_hour", parseInt(e.target.value || "0", 10))
            }
          />
        </Field>
        <Field label={t("Max message length")} htmlFor="ml">
          <Input
            id="ml"
            type="number"
            min="100"
            value={p.max_message_length}
            onChange={(e) =>
              set("max_message_length", parseInt(e.target.value || "0", 10))
            }
          />
        </Field>
      </div>
      <Field
        label={t("Visitor IP header")}
        htmlFor="tph"
        help={t(
          "Using Cloudflare? Select CF-Connecting-IP, otherwise all visitors share one rate limit. Keep None if the site is not behind a proxy.",
        )}>
        <Select
          id="tph"
          value={p.trusted_proxy_header}
          onChange={(v) => set("trusted_proxy_header", v)}
          options={[
            { value: "", label: t("None (default)") },
            { value: "cf", label: "CF-Connecting-IP (Cloudflare)" },
            { value: "xff", label: t("X-Forwarded-For (first IP)") },
            { value: "real_ip", label: "X-Real-IP" },
          ]}
        />
      </Field>
      <Field
        label={t("Consent notice")}
        htmlFor="consent"
        help={t(
          "Shown before the first message. Leave empty to skip (not recommended in the EU).",
        )}>
        <Textarea
          id="consent"
          rows={3}
          value={p.consent_text}
          onChange={(e) => set("consent_text", e.target.value)}
        />
      </Field>
      <Field label={t("Privacy policy URL")} htmlFor="purl">
        <Input
          id="purl"
          type="url"
          value={p.privacy_url}
          onChange={(e) => set("privacy_url", e.target.value)}
        />
      </Field>
    </Section>
  );
}
