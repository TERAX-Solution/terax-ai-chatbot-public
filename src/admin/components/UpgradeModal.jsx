import { useEffect, useRef, useState } from "react";
import {
  ArrowRight,
  Gauge,
  KeyRound,
  MessageSquare,
  MousePointerClick,
  PenLine,
  ShieldCheck,
  Sparkles,
  X,
} from "lucide-react";
import { adminConfig } from "../lib/api";
import { t } from "../lib/i18n";
import { plansWith, unlimitedPlan } from "../lib/planFacts";
import { cn } from "@/lib/utils";

/**
 * Copy per locked feature. Keep the text factual: only promise what the plan really includes.
 */
const FEATURES = {
  managed_ai: () => ({
    badge: plansWith("managed_ai"),
    title: t("Let TERAX run the AI for you"),
    text: t("Managed AI is part of the %s plans.", plansWith("managed_ai")),
    benefits: [
      {
        icon: KeyRound,
        title: t("No API key needed"),
        text: t("No AI vendor account to create or pay for."),
      },
      {
        icon: MousePointerClick,
        title: t("Connect in one click"),
        text: t("Then the chat just works."),
      },
      {
        icon: Gauge,
        title: t("Everything in one place"),
        text: t("Your usage and limits are shown in one place."),
      },
    ],
  }),
  custom_prompt: () => ({
    badge: plansWith("custom_prompt"),
    title: t("Teach the assistant your house rules"),
    text: t(
      "Extra instructions are part of the %s plans.",
      plansWith("custom_prompt"),
    ),
    benefits: [
      {
        icon: PenLine,
        title: t("Your rules, in your words"),
        text: t("For example which treatments you do not offer."),
      },
      {
        icon: ShieldCheck,
        title: t("Safety rules stay on"),
        text: t("No diagnoses and emergencies go to the phone, always."),
      },
      {
        icon: Gauge,
        title: t("Everything in one place"),
        text: t("Your usage and limits are shown in one place."),
      },
    ],
  }),
  callback_requests: () => ({
    badge: plansWith("callback_requests"),
    title: t("Never lose a visitor who needs a call"),
    text: t(
      "Call-back requests are part of the %s plans.",
      plansWith("callback_requests"),
    ),
    benefits: [
      {
        icon: MessageSquare,
        title: t("Call-back form in the chat"),
        text: t(
          "When the assistant cannot answer, visitors leave their number instead of leaving the site.",
        ),
      },
      {
        icon: Gauge,
        title: t("Requests inbox"),
        text: t(
          "See every request, mark it as done, and get an e-mail for each new one.",
        ),
      },
    ],
  }),
  sales_mode: () => ({
    badge: plansWith("sales_mode"),
    title: t("Turn visitors into customers"),
    text: t("Sales mode is part of the %s plans.", plansWith("sales_mode")),
    benefits: [
      {
        icon: MousePointerClick,
        title: t("A gentle next step"),
        text: t(
          "After an answer the assistant points to signing up or booking, never pushy.",
        ),
      },
      {
        icon: MessageSquare,
        title: t("Contact form in the chat"),
        text: t("Interested visitors leave their details; you get an e-mail."),
      },
      {
        icon: Gauge,
        title: t("Leads on your dashboard"),
        text: t("See leads and how many conversations turn into one."),
      },
    ],
  }),
  usage_warning: ({ used, limit }) => ({
    badge: t("Almost at your limit"),
    title: t("You have used %s of %s messages this month", used, limit),
    text: t("Your assistant keeps answering until the limit is reached."),
    benefits: usageBenefits(),
  }),
  usage_limit: ({ used, limit }) => ({
    badge: t("Monthly limit reached"),
    title: t("You have used all %s messages this month", limit),
    text: t(
      "Visitors now see your fallback message instead of an AI answer until next month.",
    ),
    benefits: usageBenefits(),
  }),
  usage_warning_top: ({ used, limit }) => ({
    badge: t("Almost at your limit"),
    title: t("You have used %s of %s messages this month", used, limit),
    text: t("Your assistant keeps answering until the limit is reached."),
    benefits: topBenefits(),
  }),
  usage_limit_top: ({ limit }) => ({
    badge: t("Monthly limit reached"),
    title: t("You have used all %s messages this month", limit),
    text: t(
      "Visitors now see your fallback message instead of an AI answer until next month.",
    ),
    benefits: topBenefits(),
  }),
};

/** For plans that already have everything but a message cap: the first plan without a limit (see dueUsagePrompt). */
function topBenefits() {
  return [
    {
      icon: MessageSquare,
      title: t("No monthly message limit"),
      text: t(
        "The %s plan never stops the chat because of a quota.",
        unlimitedPlan(),
      ),
    },
    {
      icon: ShieldCheck,
      title: t("Everything you have stays"),
      text: t("Managed AI, your own prompt and no branding come along."),
    },
    {
      icon: Gauge,
      title: t("Everything in one place"),
      text: t("Your usage and limits are shown in one place."),
    },
  ];
}

function usageBenefits() {
  return [
    {
      icon: MessageSquare,
      title: t("Many more messages"),
      text: t("Higher monthly limits, so busy weeks never stop the chat."),
    },
    {
      icon: KeyRound,
      title: t("No API key needed"),
      text: t("No AI vendor account to create or pay for."),
    },
    {
      icon: Gauge,
      title: t("Everything in one place"),
      text: t("Your usage and limits are shown in one place."),
    },
  ];
}

/**
 * "Upgrade" popup shown when an admin clicks a feature their plan does not include.
 * Rendered inside #terax-ai-admin (not a portal) so the scoped styles apply.
 */
export function UpgradeModal({ feature, context = {}, onClose }) {
  const primary = useRef(null);
  const [shown, setShown] = useState(false);
  const copy = feature && FEATURES[feature] ? FEATURES[feature](context) : null;

  useEffect(() => {
    if (!copy) {
      setShown(false);
      return undefined;
    }
    const onKey = (e) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", onKey);
    primary.current?.focus();
    const frame = requestAnimationFrame(() => setShown(true));
    return () => {
      document.removeEventListener("keydown", onKey);
      cancelAnimationFrame(frame);
    };
  }, [copy, onClose]);

  if (!copy) return null;

  return (
    <div
      className={cn(
        "fixed inset-0 z-[100000] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm transition-opacity duration-200",
        shown ? "opacity-100" : "opacity-0",
      )}
      onClick={onClose}>
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="terax-upgrade-title"
        className={cn(
          "relative w-full max-w-lg overflow-hidden rounded-2xl bg-background shadow-2xl transition duration-200 ease-out",
          shown
            ? "translate-y-0 scale-100 opacity-100"
            : "translate-y-2 scale-95 opacity-0",
        )}
        onClick={(e) => e.stopPropagation()}>
        <div className="relative overflow-hidden bg-gradient-to-br from-primary to-primary/70 px-7 pb-7 pt-8 text-primary-foreground">
          <span
            aria-hidden="true"
            className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"
          />
          <span
            aria-hidden="true"
            className="pointer-events-none absolute -bottom-12 right-16 h-24 w-24 rounded-full bg-white/10"
          />
          <button
            type="button"
            onClick={onClose}
            aria-label={t("Close")}
            className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-primary-foreground transition-colors hover:bg-white/25">
            <X className="h-4 w-4" aria-hidden="true" />
          </button>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide">
            <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
            {copy.badge}
          </span>
          <h2
            id="terax-upgrade-title"
            className="m-0 mt-4 text-2xl font-bold leading-tight">
            {copy.title}
          </h2>
          <p className="m-0 mt-2 text-sm opacity-90">{copy.text}</p>
        </div>

        <div className="px-7 py-6">
          <ul className="m-0 grid list-none gap-4 p-0">
            {copy.benefits.map(({ icon: Icon, title, text }) => (
              <li key={title} className="m-0 flex items-start gap-3">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <span className="text-sm">
                  <span className="block font-semibold">{title}</span>
                  <span className="text-muted-foreground">{text}</span>
                </span>
              </li>
            ))}
          </ul>

          <div className="mt-7 flex flex-col-reverse items-stretch gap-2 sm:flex-row sm:items-center sm:justify-end">
            <button
              type="button"
              onClick={onClose}
              className="rounded-md px-4 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
              {t("Maybe later")}
            </button>
            <a
              ref={primary}
              href={adminConfig.upgradeUrl}
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center justify-center gap-2 rounded-md bg-primary no-underline hover:no-underline px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow transition-all hover:opacity-90 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
              {t("See plans")}
              <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}
