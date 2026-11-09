import { useEffect, useRef } from "react";
import { ArrowRight, Check, PartyPopper } from "lucide-react";
import { t } from "../lib/i18n";

/**
 * Shown right after a license key unlocked something new, so the customer sees what they got.
 * `unlocked` is a list of already translated labels; `nextHref` is an optional hash link for the next step.
 */
export function PlanWelcomeModal({
  planLabel,
  unlocked,
  messageLimit,
  nextHref,
  nextLabel,
  onClose,
}) {
  const primary = useRef(null);

  useEffect(() => {
    const onKey = (e) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", onKey);
    primary.current?.focus();
    return () => document.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div
      className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm animate-in fade-in duration-200"
      onClick={onClose}>
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="terax-welcome-title"
        className="relative w-full max-w-md overflow-hidden rounded-2xl bg-background shadow-2xl animate-in zoom-in-95 duration-200"
        onClick={(e) => e.stopPropagation()}>
        <div className="bg-gradient-to-br from-primary to-primary/70 px-7 pb-6 pt-7 text-primary-foreground">
          <PartyPopper className="h-8 w-8" aria-hidden="true" />
          <h2
            id="terax-welcome-title"
            className="m-0 mt-3 text-2xl font-bold leading-tight">
            {t("Welcome to %s", planLabel)}
          </h2>
          <p className="m-0 mt-2 text-sm opacity-90">
            {t("Your license is active. Here is what you unlocked:")}
          </p>
        </div>

        <div className="px-7 py-6">
          <ul className="m-0 grid list-none gap-3 p-0 text-sm">
            {unlocked.map((label) => (
              <li key={label} className="m-0 flex items-start gap-2.5">
                <Check
                  className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                  aria-hidden="true"
                />
                <span>{label}</span>
              </li>
            ))}
            {messageLimit && (
              <li className="m-0 flex items-start gap-2.5">
                <Check
                  className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                  aria-hidden="true"
                />
                <span>{messageLimit}</span>
              </li>
            )}
          </ul>

          <div className="mt-6 flex flex-col-reverse items-stretch gap-2 sm:flex-row sm:items-center sm:justify-end">
            <button
              type="button"
              onClick={onClose}
              className="rounded-md px-4 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
              {t("Close")}
            </button>
            {nextHref && (
              <a
                ref={primary}
                href={nextHref}
                onClick={onClose}
                className="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground no-underline shadow transition-all hover:no-underline hover:opacity-90 hover:shadow-md">
                {nextLabel}
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </a>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
