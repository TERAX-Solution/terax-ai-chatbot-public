import { lazy, Suspense, useEffect, useState } from "react";
import { ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { adminConfig } from "../lib/api";
import { locale, t } from "../lib/i18n";
import { useApi } from "../lib/useApi";
import { Check, ErrorBox, Loading, PageHeader, Stat } from "../components/ui";
import { UpgradeModal } from "../components/UpgradeModal";
import { KeyProblemNotice } from "../components/KeyProblemNotice";
import { unlimitedPlan } from "../lib/planFacts";

// The chart library is the biggest part of the admin bundle: load it after the numbers are on screen (TX-445).
const ActivityChart = lazy(() => import("../components/ActivityChart"));

function fmt(n) {
  return new Intl.NumberFormat(locale).format(n || 0);
}

/**
 * Which usage popup is due: "usage_limit" at 100%, "usage_warning" from 80%, otherwise none.
 * Plans with Managed AI get the "_top" copy (next step up: no monthly limit). Unlimited plans never see one.
 */
function dueUsagePrompt(usage) {
  if (!usage || usage.limit <= 0) return null;
  // Nothing better to offer a plan with Managed AI when no plan is without a limit.
  if (adminConfig.managedAi && !unlimitedPlan()) return null;
  const suffix = adminConfig.managedAi ? "_top" : "";
  const ratio = usage.messages / usage.limit;
  if (ratio >= 1) return `usage_limit${suffix}`;
  return ratio >= 0.8 ? `usage_warning${suffix}` : null;
}

/** Each level is shown once per month and browser, so the popup nags but never loops. */
function seenKey(usage, level) {
  return `terax-ai-${level}-${usage.month}`;
}

function wasSeen(usage, level) {
  try {
    return window.localStorage.getItem(seenKey(usage, level)) === "1";
  } catch (e) {
    return false;
  }
}

function markSeen(usage, level) {
  try {
    window.localStorage.setItem(seenKey(usage, level), "1");
  } catch (e) {
    // Private mode: it may show again on reload, which is acceptable.
  }
}

export default function Dashboard() {
  const { data, error, loading } = useApi("/dashboard");
  const [prompt, setPrompt] = useState(null);

  useEffect(() => {
    if (!data) return;
    const level = dueUsagePrompt(data.usage);
    if (level && !wasSeen(data.usage, level)) {
      markSeen(data.usage, level);
      setPrompt(level);
    }
  }, [data]);

  if (loading && !data) return <Loading />;
  if (error) return <ErrorBox message={error} />;

  const { stats, usage, index, checks, last_error: lastError } = data;
  const limit = usage.limit < 0 ? "∞" : fmt(usage.limit);
  const rate = stats.conversations
    ? Math.round((stats.booking_clicks / stats.conversations) * 100)
    : 0;
  // Sales mode: conversion = (leads + booking clicks) / conversations.
  const showLeads = data.sales_mode || stats.leads > 0;
  const conversion = stats.conversations
    ? Math.round(
        ((stats.leads + stats.booking_clicks) / stats.conversations) * 100,
      )
    : 0;

  return (
    <>
      <UpgradeModal
        feature={prompt}
        context={{ used: fmt(usage.messages), limit }}
        onClose={() => setPrompt(null)}
      />
      <PageHeader
        title={t("Dashboard")}
        description={t("Last 30 days")}
        actions={
          <Button variant="outline" asChild>
            <a href={adminConfig.siteUrl} target="_blank" rel="noreferrer">
              {t("View site")} <ExternalLink className="ml-2 h-4 w-4" />
            </a>
          </Button>
        }
      />

      <KeyProblemNotice problem={checks.key_problem} />

      {checks.managed_lost && (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900">
          <span>
            {t(
              "Your plan no longer includes Managed AI, so the chat cannot answer. Add your own API key to bring it back.",
            )}
          </span>
          <Button asChild size="sm">
            <a href="#/settings/ai">{t("Open AI settings")}</a>
          </Button>
        </div>
      )}

      {/* Only nag sites that are really unfinished, not configured sites that never ran the wizard. */}
      {!checks.setup_done &&
        (!checks.ai_ready || index.chunks <= 0 || !checks.booking_ready) && (
          <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span>{t("Your assistant is not fully set up yet.")}</span>
            <Button asChild size="sm">
              <a href="#/setup">{t("Finish setup")}</a>
            </Button>
          </div>
        )}

      <div
        className={`mb-6 grid gap-4 sm:grid-cols-2 ${showLeads ? "lg:grid-cols-5" : "lg:grid-cols-4"}`}>
        <Stat label={t("Conversations")} value={fmt(stats.conversations)} />
        <Stat label={t("Questions answered")} value={fmt(stats.messages)} />
        {stats.answers.replies > 0 && (
          <Stat
            label={t("Answer rate")}
            value={`${Math.round(((stats.answers.replies - stats.answers.unanswered) / stats.answers.replies) * 100)}%`}
            hint={t("%s unanswered questions", stats.answers.unanswered)}
          />
        )}
        <Stat
          label={t("Booking clicks")}
          value={fmt(stats.booking_clicks)}
          hint={t("%s% of conversations", rate)}
        />
        {showLeads && (
          <Stat
            label={t("Leads")}
            value={fmt(stats.leads)}
            hint={t("%s% conversion (leads + booking clicks)", conversion)}
          />
        )}
        <Stat
          label={t("Usage this month")}
          value={`${fmt(usage.messages)} / ${limit}`}
          hint={t("%s tokens", fmt(usage.input_tokens + usage.output_tokens))}
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">
              {t("Conversations per day")}
            </CardTitle>
          </CardHeader>
          <CardContent className="h-64">
            {stats.daily.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                {t("No conversations yet. Open your website and try the chat.")}
              </p>
            ) : (
              <Suspense fallback={<Loading />}>
                <ActivityChart daily={stats.daily} />
              </Suspense>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("Setup checklist")}</CardTitle>
          </CardHeader>
          <CardContent className="divide-y">
            <Check
              ok={checks.enabled}
              label={t("Assistant enabled")}
              hint={t("The chat is hidden from visitors.")}
              to="#/settings/assistant"
            />
            <Check
              ok={checks.ai_ready}
              label={t("AI provider connected")}
              hint={t("Add an API key or use Managed AI.")}
              to="#/settings/ai"
            />
            <Check
              ok={index.chunks > 0}
              label={t("Knowledge indexed (%s pages)", fmt(index.sources))}
              hint={t("Build the index from your pages.")}
              to="#/knowledge"
            />
            <Check
              ok={checks.booking_ready}
              label={t("Booking link set")}
              hint={t("Add your Dr. Flex / Doctolib link.")}
              to="#/settings/booking"
            />
          </CardContent>
        </Card>
      </div>

      {lastError && (
        <div className="mt-6">
          <ErrorBox
            message={t(
              "Last AI error (%s): %s",
              new Date(lastError.time * 1000).toLocaleString(locale),
              lastError.message,
            )}
          />
        </div>
      )}
    </>
  );
}
