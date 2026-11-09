import { useState } from "react";
import { toast } from "sonner";
import { Check, KeyRound, RefreshCw, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { adminConfig, api } from "../lib/api";
import { locale, t } from "../lib/i18n";
import { useApi } from "../lib/useApi";
import {
  ErrorBox,
  Loading,
  PageHeader,
  Section,
  Spinner,
} from "../components/ui";
import { PlanWelcomeModal } from "../components/PlanWelcomeModal";
import { applyLicense } from "../lib/licenseEvents";
import { cn } from "@/lib/utils";

const FEATURE_LABELS = {
  byok: t("Use your own AI key"),
  managed_ai: t("Managed AI (no key needed)"),
  booking: t("Booking button"),
  conversation_log: t("Conversation log"),
  custom_prompt: t("Custom AI instructions"),
  remove_branding: t("Remove “Powered by TERAX”"),
  updates: t("Automatic updates"),
};

/**
 * What the new plan adds compared with the previous one, or null if nothing (then only the toast shows).
 * The next step points to AI settings when Managed AI was just unlocked, because the mode is not switched automatically.
 */
function whatsNew(before, after) {
  const had = (before && before.features) || {};
  const unlocked = Object.keys(FEATURE_LABELS)
    .filter((f) => after.features[f] && !had[f])
    .map((f) => FEATURE_LABELS[f]);
  const oldLimit =
    before && before.limits ? before.limits.messages_per_month : 0;
  const newLimit = after.limits.messages_per_month;
  const more =
    newLimit < 0 ? oldLimit >= 0 : oldLimit >= 0 && newLimit > oldLimit;
  if (!unlocked.length && !more) return null;

  const managed = unlocked.includes(FEATURE_LABELS.managed_ai);
  return {
    planLabel: after.plan_label,
    unlocked,
    messageLimit: more
      ? newLimit < 0
        ? t("Unlimited messages per month")
        : t("%s messages per month", newLimit.toLocaleString(locale))
      : null,
    nextHref: managed ? "#/settings/ai" : "#/dashboard",
    nextLabel: managed ? t("Open AI settings") : t("Go to dashboard"),
  };
}

/** Without the Pro add-on there is no license to manage: say so instead of calling endpoints that do not exist. */
export default function License() {
  if (!adminConfig.pro) {
    return (
      <>
        <PageHeader
          title={t("License")}
          description={t(
            "Licenses and paid plans come with the TERAX AI Assistant Pro add-on.",
          )}
        />
        <Section
          title={t("Pro add-on not active")}
          description={t(
            "Install and activate TERAX AI Assistant Pro to manage a license here.",
          )}>
          <Button asChild>
            <a href={adminConfig.upgradeUrl} target="_blank" rel="noreferrer">
              {t("See plans")}
            </a>
          </Button>
        </Section>
      </>
    );
  }
  return <LicensePage />;
}

function LicensePage() {
  const { data, setData, error, loading } = useApi("/license");
  const [key, setKey] = useState("");
  const [busy, setBusy] = useState(null);
  const [welcome, setWelcome] = useState(null);

  async function run(action, body) {
    setBusy(action);
    try {
      const before = data;
      const d = await api(`/license/${action}`, { method: "POST", body });
      setData(d);
      applyLicense(d);
      if (action === "activate") {
        if (d.error) {
          toast.error(d.error);
        } else {
          toast.success(t("Activated: %s", d.plan_label));
          setWelcome(whatsNew(before, d));
        }
        setKey("");
      }
    } catch (e) {
      toast.error(e.message);
    } finally {
      setBusy(null);
    }
  }

  if (loading && !data) return <Loading />;
  if (error) return <ErrorBox message={error} />;

  const active = !!data.key_hint;

  return (
    <>
      {welcome && (
        <PlanWelcomeModal {...welcome} onClose={() => setWelcome(null)} />
      )}
      <PageHeader
        title={t("License")}
        description={t(
          "Your plan controls which features and limits apply on this site.",
        )}
      />

      <Section
        title={t("Current plan: %s", data.plan_label)}
        description={
          data.dev_mode
            ? t("Developer override active (TERAX_AI_DEV_PLAN).")
            : undefined
        }>
        {active ? (
          <div className="grid gap-2 text-sm">
            <p>
              {t("Key:")}{" "}
              <code className="rounded bg-muted px-1.5 py-0.5">
                {data.key_hint}
              </code>{" "}
              · {t("Status:")}{" "}
              <span
                className={cn(
                  "font-medium",
                  data.status === "active"
                    ? "text-emerald-700"
                    : "text-red-700",
                )}>
                {t(data.status)}
              </span>
            </p>
            {data.expires_at && (
              <p>
                {t(
                  "Renews / expires: %s",
                  new Date(data.expires_at).toLocaleDateString(locale),
                )}
              </p>
            )}
            {data.last_check && (
              <p className="text-muted-foreground">
                {t(
                  "Last checked: %s",
                  new Date(data.last_check).toLocaleString(locale),
                )}
              </p>
            )}
            {data.error && <p className="text-red-700">{data.error}</p>}
            <div className="mt-2 flex gap-2">
              <Button
                variant="outline"
                onClick={() => run("check")}
                disabled={!!busy}>
                {busy === "check" ? (
                  <Spinner className="mr-2" />
                ) : (
                  <RefreshCw className="mr-2 h-4 w-4" />
                )}{" "}
                {t("Check now")}
              </Button>
              <Button
                variant="destructive"
                onClick={() =>
                  window.confirm(t("Deactivate the license on this site?")) &&
                  run("deactivate")
                }
                disabled={!!busy}>
                {t("Deactivate")}
              </Button>
            </div>
          </div>
        ) : (
          <form
            className="flex max-w-xl gap-2"
            onSubmit={(e) => {
              e.preventDefault();
              run("activate", { key });
            }}>
            <Input
              value={key}
              onChange={(e) => setKey(e.target.value)}
              placeholder="XXXX-XXXX-XXXX-XXXX"
            />
            <Button type="submit" disabled={!key.trim() || !!busy}>
              {busy === "activate" ? (
                <Spinner className="mr-2" />
              ) : (
                <KeyRound className="mr-2 h-4 w-4" />
              )}{" "}
              {t("Activate")}
            </Button>
          </form>
        )}
        {!active && data.error && (
          <p className="text-sm text-red-700">{data.error}</p>
        )}
      </Section>

      <Section title={t("Compare plans")}>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="text-xs uppercase text-muted-foreground">
                <th className="py-2">{t("Feature")}</th>
                {Object.entries(data.plans).map(([slug, p]) => (
                  <th
                    key={slug}
                    className={cn(
                      "py-2 text-center",
                      slug === data.plan && "text-primary",
                    )}>
                    {p.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              <tr className="border-t">
                <td className="py-2">{t("Messages / month")}</td>
                {Object.entries(data.plans).map(([slug, p]) => (
                  <td key={slug} className="py-2 text-center tabular-nums">
                    {p.limits.messages_per_month < 0
                      ? t("Unlimited")
                      : p.limits.messages_per_month.toLocaleString(locale)}
                  </td>
                ))}
              </tr>
              <tr className="border-t">
                <td className="py-2">{t("Indexed pages")}</td>
                {Object.entries(data.plans).map(([slug, p]) => (
                  <td key={slug} className="py-2 text-center tabular-nums">
                    {p.limits.indexed_items < 0
                      ? t("Unlimited")
                      : p.limits.indexed_items}
                  </td>
                ))}
              </tr>
              {Object.entries(FEATURE_LABELS).map(([f, label]) => (
                <tr key={f} className="border-t">
                  <td className="py-2">{label}</td>
                  {Object.entries(data.plans).map(([slug, p]) => (
                    <td key={slug} className="py-2 text-center">
                      {p.features[f] ? (
                        <Check className="mx-auto h-4 w-4 text-emerald-600" />
                      ) : (
                        <X className="mx-auto h-4 w-4 text-slate-300" />
                      )}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Section>
    </>
  );
}
