import { useEffect, useRef, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { RefreshCw, Save, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { api } from "../lib/api";
import { locale, t } from "../lib/i18n";
import { useApi } from "../lib/useApi";
import { useSettings } from "../lib/useSettings";
import {
  ErrorBox,
  Field,
  Loading,
  PageHeader,
  Section,
  Spinner,
  Stat,
} from "../components/ui";

export default function Knowledge() {
  const s = useSettings();
  const status = useApi("/knowledge/status");
  const sources = useApi("/knowledge/sources");
  const [query, setQuery] = useState("");
  const [results, setResults] = useState(null);
  const [searching, setSearching] = useState(false);

  const running = status.data?.status === "running";

  // "Add answer" on the Insights page: append the question to the extra information, once, and jump to the box.
  const [params, setParams] = useSearchParams();
  const prefilled = useRef(false);
  useEffect(() => {
    const q = params.get("add");
    if (!q || !s.draft || prefilled.current) return;
    prefilled.current = true;
    const cur = s.draft.knowledge.custom_text || "";
    s.set("knowledge.custom_text", `${cur}${cur.trim() ? "\n\n" : ""}${q}\n`);
    setParams({}, { replace: true });
    setTimeout(() => {
      const el = document.getElementById("custom");
      if (el) {
        el.scrollIntoView({ block: "center" });
        el.focus();
        el.setSelectionRange(el.value.length, el.value.length);
      }
    }, 50);
  }, [params, s.draft]);

  // While a rebuild runs, polling /knowledge/status also processes the next batch.
  useEffect(() => {
    if (!running) return;
    const id = setInterval(async () => {
      const st = await api("/knowledge/status").catch(() => null);
      if (st) {
        status.setData(st);
        if (st.status !== "running") {
          sources.reload();
          toast.success(t("Knowledge index updated"));
        }
      }
    }, 1500);
    return () => clearInterval(id);
  }, [running]);

  async function rebuild() {
    try {
      status.setData(await api("/knowledge/rebuild", { method: "POST" }));
    } catch (e) {
      toast.error(e.message);
    }
  }

  async function search(e) {
    e.preventDefault();
    if (!query.trim()) return;
    setSearching(true);
    try {
      setResults(
        await api("/knowledge/search", { method: "POST", body: { query } }),
      );
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSearching(false);
    }
  }

  async function saveAndRebuild() {
    await s.save();
    status.reload();
  }

  if (s.error) return <ErrorBox message={s.error} />;
  if (!s.draft || !status.data) return <Loading />;

  const k = s.draft.knowledge;
  const st = status.data;
  const pct = st.total ? Math.round((st.done / st.total) * 100) : 0;

  return (
    <>
      <PageHeader
        title={t("Knowledge")}
        description={t(
          "The assistant answers only from these pages and the extra information below. Pages are re-indexed automatically when you edit them.",
        )}
        actions={
          <Button onClick={rebuild} disabled={running} variant="outline">
            {running ? (
              <Spinner className="mr-2" />
            ) : (
              <RefreshCw className="mr-2 h-4 w-4" />
            )}
            {t("Rebuild index")}
          </Button>
        }
      />

      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <Stat label={t("Indexed pages")} value={st.sources} />
        <Stat label={t("Text chunks")} value={st.chunks} />
        <Stat
          label={t("Last update")}
          value={
            st.updated
              ? new Date(st.updated.replace(" ", "T") + "Z").toLocaleDateString(
                  locale,
                )
              : "—"
          }
        />
      </div>

      {running && (
        <div className="mb-6 rounded-lg border bg-white p-4">
          <p className="mb-2 text-sm font-medium">
            {t("Indexing… %s / %s", st.done, st.total)}
          </p>
          <div className="h-2 overflow-hidden rounded bg-muted">
            <div
              className="h-full bg-primary transition-all"
              style={{ width: `${pct}%` }}
            />
          </div>
        </div>
      )}
      {st.skipped > 0 && (
        <div className="mb-6 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
          {t(
            "%s pages were not indexed because of your plan limit. Upgrade under “License” to index everything.",
            st.skipped,
          )}
        </div>
      )}

      <Section
        title={t("Test what the assistant finds")}
        description={t(
          "Type a visitor question to see which content will be given to the AI.",
        )}>
        <form onSubmit={search} className="flex gap-2">
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t(
              "e.g. Was kostet eine professionelle Zahnreinigung?",
            )}
          />
          <Button type="submit" disabled={searching}>
            {searching ? <Spinner /> : <Search className="h-4 w-4" />}
          </Button>
        </form>
        {results && results.length === 0 && (
          <p className="text-sm text-muted-foreground">
            {t(
              "Nothing found – the assistant would say it doesn’t know. Add the answer to a page or to “Extra information”.",
            )}
          </p>
        )}
        {results && results.length > 0 && (
          <ol className="space-y-3 pl-0">
            {results.map((r, i) => (
              <li key={i} className="list-none rounded-md border p-3">
                <div className="mb-1 flex items-center justify-between gap-2">
                  <a
                    href={r.url}
                    target="_blank"
                    rel="noreferrer"
                    className="font-medium text-primary underline">
                    {r.title}
                  </a>
                  <span className="text-xs text-muted-foreground">
                    {t("score %s", r.score)}
                  </span>
                </div>
                <p className="whitespace-pre-line text-xs text-muted-foreground">
                  {r.excerpt}…
                </p>
              </li>
            ))}
          </ol>
        )}
      </Section>

      <Section title={t("Sources")}>
        <Field label={t("Content types to index")}>
          <div className="flex flex-wrap gap-4">
            {(s.options?.post_types || []).map((pt) => (
              <label key={pt.value} className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={k.post_types.includes(pt.value)}
                  onChange={(e) =>
                    s.set(
                      "knowledge.post_types",
                      e.target.checked
                        ? [...k.post_types, pt.value]
                        : k.post_types.filter((x) => x !== pt.value),
                    )
                  }
                />
                {t(pt.label)}
              </label>
            ))}
          </div>
        </Field>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field
            label={t("Exclude page IDs")}
            htmlFor="excl"
            help={t("Comma-separated, e.g. imprint or job pages.")}>
            <Input
              id="excl"
              value={(k.exclude_ids || []).join(", ")}
              onChange={(e) =>
                s.set(
                  "knowledge.exclude_ids",
                  e.target.value
                    .split(",")
                    .map((x) => x.trim())
                    .filter(Boolean),
                )
              }
            />
          </Field>
          <Field
            label={t("Context pieces per answer")}
            htmlFor="mc"
            help={t(
              "More = better coverage, higher AI cost. 4–6 recommended.",
            )}>
            <Input
              id="mc"
              type="number"
              min="1"
              max="12"
              value={k.max_chunks}
              onChange={(e) =>
                s.set(
                  "knowledge.max_chunks",
                  parseInt(e.target.value || "1", 10),
                )
              }
            />
          </Field>
        </div>
        <Field
          label={t("Extra information (not on the website)")}
          htmlFor="custom"
          help={t(
            "Opening hours, FAQs, parking, accepted insurances … Plain text or Markdown.",
          )}>
          <Textarea
            id="custom"
            rows={8}
            value={k.custom_text}
            onChange={(e) => s.set("knowledge.custom_text", e.target.value)}
          />
        </Field>
        <Button onClick={saveAndRebuild} disabled={!s.dirty || s.saving}>
          {s.saving ? (
            <Spinner className="mr-2" />
          ) : (
            <Save className="mr-2 h-4 w-4" />
          )}
          {t("Save")}
        </Button>
      </Section>

      <Section title={t("Indexed content (%s)", sources.data?.length || 0)}>
        {sources.loading && !sources.data ? (
          <Loading />
        ) : (
          <div className="max-h-96 overflow-auto">
            <table className="w-full text-left text-sm">
              <thead className="sticky top-0 bg-white text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="py-2">{t("Title")}</th>
                  <th className="py-2">{t("Chunks")}</th>
                  <th className="py-2">{t("Updated")}</th>
                </tr>
              </thead>
              <tbody>
                {(sources.data || []).map((r) => (
                  <tr key={r.post_id} className="border-t">
                    <td className="py-2">
                      <a
                        href={r.url}
                        target="_blank"
                        rel="noreferrer"
                        className="hover:underline">
                        {r.title}
                      </a>
                    </td>
                    <td className="py-2 tabular-nums">{r.chunks}</td>
                    <td className="py-2 text-muted-foreground">
                      {r.updated_at}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Section>
    </>
  );
}
