import { useEffect, useRef, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import {
  ArrowLeft,
  ArrowRight,
  Check,
  ExternalLink,
  MessageCircle,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { adminConfig, api } from "../lib/api";
import { t } from "../lib/i18n";
import { useApi } from "../lib/useApi";
import { useSettings } from "../lib/useSettings";
import {
  ErrorBox,
  Field,
  Loading,
  PageHeader,
  Section,
  Spinner,
} from "../components/ui";
import { cn } from "@/lib/utils";
import { AiTab, BookingTab } from "./Settings";
import { IndustryPicker } from "../components/IndustryPicker";
import { palette } from "../../frontend/color";

const STEPS = [t("AI"), t("Business details"), t("Booking"), t("Knowledge")];
const HEX = /^#[0-9a-f]{6}$/i;
const URL_RE = /^https?:\/\/\S+\.\S+/i;

export default function Setup() {
  const navigate = useNavigate();
  const s = useSettings();
  const providers = useApi("/providers");
  const license = useApi("/license");
  const [step, setStep] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [finished, setFinished] = useState(false);

  if (s.error) return <ErrorBox message={s.error} />;
  if (!s.draft || !providers.data || !license.data) return <Loading />;

  const features = license.data.features || {};
  const { ai, assistant: a, booking: b } = s.draft;

  /** Returns true when the step is valid and saved. Shows the reason otherwise. */
  async function validateAndSave() {
    if (step === 0) {
      const typed = s.newKeys[ai.provider];
      const key = typed && typed !== "__delete__" ? typed : "";
      if (
        ai.mode !== "managed" &&
        ai.provider !== "openai_compatible" &&
        !key &&
        !ai.api_keys?.[ai.provider]
      ) {
        setError(t("Enter your API key to continue."));
        return false;
      }
      try {
        // Test before saving so a wrong key is never stored.
        await api("/providers/test", {
          method: "POST",
          body: {
            provider: ai.provider,
            mode: ai.mode,
            model: ai.model,
            api_key: key,
            base_url: ai.base_url,
          },
        });
      } catch (e) {
        setError(t("The AI connection failed: %s", e.message));
        return false;
      }
    }
    if (step === 1) {
      if (!a.name.trim()) {
        setError(t("Please enter a name for the assistant."));
        return false;
      }
      if (!HEX.test(a.primary_color)) {
        setError(t("The brand color must look like #0f766e."));
        return false;
      }
    }
    if (step === 2 && b.enabled && !URL_RE.test(b.url.trim())) {
      setError(
        t(
          "Enter a valid booking URL starting with https://, or switch the booking button off.",
        ),
      );
      return false;
    }
    return !!(await s.save());
  }

  async function next() {
    setError(null);
    setBusy(true);
    try {
      if (await validateAndSave()) setStep(step + 1);
    } finally {
      setBusy(false);
    }
  }

  async function finish() {
    setBusy(true);
    try {
      if (await s.save({ setup: { completed: true } })) setFinished(true);
    } finally {
      setBusy(false);
    }
  }

  if (finished) {
    return (
      <>
        <PageHeader
          title={t("You're all set")}
          description={t("The assistant is live on your website.")}
        />
        <Section title={t("Next steps")}>
          <div className="flex flex-wrap gap-3">
            <Button asChild>
              <a href={adminConfig.siteUrl} target="_blank" rel="noreferrer">
                {t("Try it")} <ExternalLink className="ml-2 h-4 w-4" />
              </a>
            </Button>
            <Button variant="outline" onClick={() => navigate("/dashboard")}>
              {t("Go to dashboard")}
            </Button>
          </div>
        </Section>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title={t("Setup")}
        description={t("Four quick steps to get your assistant running.")}
        actions={
          <Button variant="ghost" asChild>
            <Link to="/dashboard">{t("Skip setup")}</Link>
          </Button>
        }
      />

      <ol className="m-0 mb-6 flex gap-2 p-0" aria-label={t("Progress")}>
        {STEPS.map((label, i) => (
          <li
            key={label}
            className="list-none min-w-0 flex-1"
            aria-current={i === step ? "step" : undefined}>
            <div
              className={cn(
                "h-1.5 rounded",
                i <= step ? "bg-primary" : "bg-muted",
              )}
            />
            <p
              className={cn(
                "mt-2 break-words text-xs font-medium",
                i === step ? "text-foreground" : "text-muted-foreground",
              )}>
              {i + 1}. {label}
            </p>
          </li>
        ))}
      </ol>

      {step === 0 && (
        <AiTab s={s} providers={providers.data} features={features} compact />
      )}
      {step === 1 && <DetailsStep s={s} />}
      {step === 2 && <BookingTab s={s} />}
      {step === 3 && <KnowledgeStep onError={setError} />}

      {error && (
        <div className="mb-6" role="alert">
          <ErrorBox message={error} />
        </div>
      )}

      <div className="flex items-center justify-between">
        <Button
          variant="outline"
          onClick={() => {
            setError(null);
            setStep(step - 1);
          }}
          disabled={step === 0 || busy}>
          <ArrowLeft className="mr-2 h-4 w-4" /> {t("Back")}
        </Button>
        {step < STEPS.length - 1 ? (
          <Button onClick={next} disabled={busy}>
            {busy ? <Spinner className="mr-2" /> : null} {t("Continue")}{" "}
            <ArrowRight className="ml-2 h-4 w-4" />
          </Button>
        ) : (
          <Button onClick={finish} disabled={busy}>
            {busy ? (
              <Spinner className="mr-2" />
            ) : (
              <Check className="mr-2 h-4 w-4" />
            )}{" "}
            {t("Finish setup")}
          </Button>
        )}
      </div>
    </>
  );
}

function DetailsStep({ s }) {
  const a = s.draft.assistant;
  const set = (k, v) => s.set(`assistant.${k}`, v);
  const color = HEX.test(a.primary_color) ? a.primary_color : "#0f766e";

  return (
    <Section
      title={t("Business details")}
      description={t(
        "How the assistant introduces itself and how the chat button looks.",
      )}>
      <IndustryPicker s={s} autoApply />
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label={t("Assistant name")} htmlFor="su-name">
          <Input
            id="su-name"
            value={a.name}
            onChange={(e) => set("name", e.target.value)}
          />
        </Field>
        <Field label={t("Phone (shown for urgent cases)")} htmlFor="su-phone">
          <Input
            id="su-phone"
            value={a.phone}
            onChange={(e) => set("phone", e.target.value)}
          />
        </Field>
        <Field label={t("Brand color")} htmlFor="su-color">
          <div className="flex gap-2">
            <input
              type="color"
              aria-label={t("Brand color")}
              value={color}
              onChange={(e) => set("primary_color", e.target.value)}
              className="h-9 w-12 cursor-pointer rounded border"
            />
            <Input
              id="su-color"
              value={a.primary_color}
              onChange={(e) => set("primary_color", e.target.value)}
            />
          </div>
        </Field>
        <div className="grid gap-2">
          <p className="text-sm font-medium">{t("Preview")}</p>
          <div className="flex items-center gap-3">
            <span
              className="flex h-12 w-12 items-center justify-center rounded-full shadow"
              style={{
                backgroundColor: color,
                color: palette(color, a.text_on_brand).on,
              }}>
              <MessageCircle className="h-6 w-6" />
            </span>
            <span className="text-sm text-muted-foreground">{a.name}</span>
          </div>
        </div>
      </div>
    </Section>
  );
}

function KnowledgeStep({ onError }) {
  const [status, setStatus] = useState(null);
  const started = useRef(false);

  // Start the index once when the step opens; polling /knowledge/status also processes the next batch.
  useEffect(() => {
    if (started.current) return;
    started.current = true;
    api("/knowledge/rebuild", { method: "POST" })
      .then(setStatus)
      .catch((e) => onError(e.message));
  }, []);

  const running = !status || status.status === "running";

  useEffect(() => {
    if (!running) return;
    const id = setInterval(async () => {
      const st = await api("/knowledge/status").catch(() => null);
      if (st) setStatus(st);
    }, 1500);
    return () => clearInterval(id);
  }, [running]);

  const pct = status?.total
    ? Math.round((status.done / status.total) * 100)
    : 0;

  return (
    <Section
      title={t("Knowledge")}
      description={t(
        "The assistant reads your website pages so it can answer from them.",
      )}>
      {running ? (
        <div>
          <p className="mb-2 text-sm font-medium">
            {status
              ? t("Indexing… %s / %s", status.done, status.total)
              : t("Starting…")}
          </p>
          <div
            className="h-2 overflow-hidden rounded bg-muted"
            role="progressbar"
            aria-valuenow={pct}
            aria-valuemin={0}
            aria-valuemax={100}>
            <div
              className="h-full bg-primary transition-all"
              style={{ width: `${pct}%` }}
            />
          </div>
        </div>
      ) : status.chunks > 0 ? (
        <p className="text-sm text-emerald-700">
          {t("Done: %s pages indexed.", status.sources)}
        </p>
      ) : (
        <p className="text-sm text-amber-800">
          {t(
            "Nothing was indexed yet. Publish some pages, or add facts under Knowledge → Extra information.",
          )}
        </p>
      )}
    </Section>
  );
}
