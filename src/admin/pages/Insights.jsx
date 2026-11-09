import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { api } from "../lib/api";
import { locale, t } from "../lib/i18n";
import { ErrorBox, Loading, PageHeader, Stat } from "../components/ui";

function when(utc) {
  return utc
    ? new Date(utc.replace(" ", "T") + "Z").toLocaleDateString(locale)
    : "";
}

/** Questions the assistant could not answer from the website content, grouped by topic (TX-460). */
export default function Insights() {
  const [days, setDays] = useState(30);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    setData(null);
    api(`/insights?days=${days}`)
      .then(setData)
      .catch((e) => setError(e.message));
  }, [days]);

  if (error) return <ErrorBox message={error} />;
  if (!data) return <Loading />;

  const { replies, unanswered } = data.stats;
  const rate = replies
    ? Math.round(((replies - unanswered) / replies) * 100)
    : null;

  return (
    <>
      <PageHeader
        title={t("Insights")}
        description={t(
          "What visitors ask that your website does not answer yet. Add the missing information and the assistant gets better.",
        )}
        actions={
          <select
            className="h-9 rounded-md border bg-white px-2 text-sm"
            value={days}
            onChange={(e) => setDays(parseInt(e.target.value, 10))}
            aria-label={t("Period")}>
            <option value={7}>{t("Last 7 days")}</option>
            <option value={30}>{t("Last 30 days")}</option>
            <option value={90}>{t("Last 90 days")}</option>
          </select>
        }
      />

      {!data.logging && (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          <span>
            {t(
              "This report needs conversation logging. Turn it on under Settings → Privacy; questions asked from then on will show up here.",
            )}
          </span>
          <Button asChild size="sm">
            <a href="#/settings/privacy">{t("Open privacy settings")}</a>
          </Button>
        </div>
      )}

      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <Stat
          label={t("Answer rate")}
          value={rate === null ? "–" : `${rate}%`}
          hint={t("Replies the assistant could answer from your content")}
        />
        <Stat label={t("Replies")} value={replies} />
        <Stat label={t("Unanswered")} value={unanswered} />
      </div>

      <Card>
        <CardContent className="p-0">
          {data.groups.length === 0 ? (
            <p className="p-6 text-sm text-muted-foreground">
              {replies
                ? t("Nothing unanswered in this period. Nice.")
                : t("No questions in this period yet.")}
            </p>
          ) : (
            <ul className="divide-y pl-0">
              {data.groups.map((g, i) => (
                <li
                  key={i}
                  className="flex flex-wrap items-start justify-between gap-3 p-4">
                  <div className="min-w-0 space-y-1">
                    <p className="font-medium">{g.question}</p>
                    {g.variants.length > 0 && (
                      <p className="text-xs text-muted-foreground">
                        {t("Also asked:")} {g.variants.join(" · ")}
                      </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                      {t("%s× asked", g.count)} ·{" "}
                      {t("last: %s", when(g.last_at))}
                    </p>
                  </div>
                  <Button size="sm" variant="outline" asChild>
                    <Link
                      to={`/knowledge?add=${encodeURIComponent(g.question)}`}>
                      <Plus className="mr-1.5 h-4 w-4" /> {t("Add answer")}
                    </Link>
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </>
  );
}
