import { useState } from "react";
import { useLocation } from "react-router-dom";
import { Sparkles, X } from "lucide-react";
import { adminConfig } from "../lib/api";
import { t } from "../lib/i18n";
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
 * One line about the Pro add-on, on the Dashboard only (WordPress.org: upsells must stay unobtrusive).
 * Motion is off for reduced-motion users, and the admin can dismiss the line for good. Hidden when the plan already includes Managed AI.
 */
export function ProBanner() {
  const [hidden, setHidden] = useState(wasDismissed);
  const { pathname } = useLocation();
  useLicenseChanges();

  const onDashboard = pathname === "/" || pathname.startsWith("/dashboard");
  if (hidden || !onDashboard || adminConfig.managedAi) return null;

  const dismiss = () => {
    try {
      window.localStorage.setItem(DISMISS_KEY, "1");
    } catch (e) {
      // Private mode: hide for this visit only.
    }
    setHidden(true);
  };

  return (
    <div className="relative overflow-hidden border-b bg-[length:200%_100%] bg-gradient-to-r from-primary/10 via-primary/30 to-primary/10 motion-safe:animate-terax-sheen">
      <span
        aria-hidden="true"
        className="pointer-events-none absolute inset-y-0 left-0 w-1/6 bg-gradient-to-r from-transparent via-white/60 to-transparent motion-safe:animate-terax-shine"
      />
      <div className="relative mx-auto flex max-w-6xl items-center gap-3 px-6 py-3 text-sm">
        <Sparkles
          className="h-5 w-5 shrink-0 text-primary motion-safe:animate-terax-hop"
          aria-hidden="true"
        />
        <p className="m-0 flex-1 font-medium text-foreground">
          {t("Let TERAX run the AI for you and answer more of your visitors.")}
        </p>
        <a
          href={adminConfig.upgradeUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="shrink-0 rounded-md bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground no-underline hover:no-underline hover:opacity-90 motion-safe:animate-terax-wiggle">
          {t("See plans")}
        </a>
        <button
          type="button"
          onClick={dismiss}
          aria-label={t("Dismiss")}
          className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-white/60 hover:text-foreground">
          <X className="h-4 w-4" aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}
