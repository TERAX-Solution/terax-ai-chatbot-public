import { useState } from "react";
import {
  ArrowRight,
  Crown,
  Gauge,
  KeyRound,
  PenLine,
  Sparkles,
  X,
} from "lucide-react";
import { adminConfig } from "../lib/api";
import { locale, t } from "../lib/i18n";
import { managedAiMessages, plansWith } from "../lib/planFacts";
import { useLicenseChanges } from "../lib/licenseEvents";

const DISMISS_KEY = "terax-ai-pro-banner-dismissed";

function wasDismissed() {
  try {
    return window.localStorage.getItem(DISMISS_KEY) === "1";
  } catch (e) {
    return false;
  }
}

/**
 * Hero banner above the admin pages that introduces the Pro plan.
 * Hidden when the plan already includes Managed AI, and after the admin dismissed it.
 */
export function ProBanner() {
  const [hidden, setHidden] = useState(wasDismissed);
  useLicenseChanges();

  if (hidden || adminConfig.managedAi) return null;

  const dismiss = () => {
    try {
      window.localStorage.setItem(DISMISS_KEY, "1");
    } catch (e) {
      // Private mode: hide for this visit only.
    }
    setHidden(true);
  };

  const messages = managedAiMessages();
  const perks = [
    { icon: KeyRound, label: t("No API key needed") },
    messages !== null && {
      icon: Gauge,
      label:
        messages < 0
          ? t("Unlimited messages per month")
          : t("%s messages per month", messages.toLocaleString(locale)),
    },
    { icon: PenLine, label: t("Your own prompt, no TERAX branding") },
  ].filter(Boolean);

  return (
    <div className="relative overflow-hidden bg-gradient-to-br from-primary via-primary to-primary/70 text-primary-foreground shadow-md motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-top-4 motion-safe:duration-500">
      <span
        aria-hidden="true"
        className="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-white/10 motion-safe:animate-terax-float"
      />
      <span
        aria-hidden="true"
        className="pointer-events-none absolute right-40 top-16 h-40 w-40 rounded-full bg-white/10 motion-safe:animate-terax-float-slow"
      />
      <span
        aria-hidden="true"
        className="pointer-events-none absolute -bottom-20 left-1/3 h-48 w-48 rounded-full bg-black/10 motion-safe:animate-terax-float"
      />

      <div className="relative mx-auto flex max-w-6xl flex-wrap items-center gap-x-8 gap-y-5 px-6 py-7 pr-16">
        <span
          aria-hidden="true"
          className="hidden h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/20 shadow-inner ring-1 ring-white/30 sm:flex motion-safe:animate-terax-glow">
          <Crown className="h-8 w-8" />
        </span>

        <div className="min-w-[280px] flex-1">
          {plansWith("managed_ai") && (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide">
              <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
              {plansWith("managed_ai")}
            </span>
          )}
          <h2 className="m-0 mt-3 text-2xl font-bold leading-tight sm:text-3xl">
            {t("Get more from your assistant.")}
          </h2>
          <p className="m-0 mt-2 max-w-2xl text-base opacity-90">
            {t(
              "Let TERAX run the AI for you and answer more of your visitors.",
            )}
          </p>
          <ul className="m-0 mt-4 flex list-none flex-wrap gap-2 p-0">
            {perks.map(({ icon: Icon, label }, i) => (
              <li
                key={label}
                style={{ animationDelay: `${200 + i * 120}ms` }}
                className="m-0 motion-safe:animate-terax-rise inline-flex items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-sm font-medium ring-1 ring-white/20">
                <Icon className="h-4 w-4" aria-hidden="true" />
                {label}
              </li>
            ))}
          </ul>
        </div>

        <a
          href={adminConfig.upgradeUrl}
          target="_blank"
          rel="noreferrer"
          className="group relative inline-flex items-center gap-2 overflow-hidden rounded-lg bg-white px-6 py-3 text-base font-semibold text-primary no-underline shadow-lg transition hover:-translate-y-0.5 hover:no-underline hover:shadow-xl">
          <span
            aria-hidden="true"
            className="pointer-events-none absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-primary/15 to-transparent motion-safe:animate-terax-shine"
          />
          <span className="relative">{t("See plans")}</span>
          <ArrowRight
            className="relative h-5 w-5 transition-transform group-hover:translate-x-1"
            aria-hidden="true"
          />
        </a>
      </div>

      <button
        type="button"
        onClick={dismiss}
        aria-label={t("Dismiss")}
        className="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-primary-foreground transition-colors hover:bg-white/25">
        <X className="h-4 w-4" aria-hidden="true" />
      </button>
    </div>
  );
}
