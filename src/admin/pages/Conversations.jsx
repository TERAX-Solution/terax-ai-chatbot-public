import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import {
  ArrowLeft,
  CalendarCheck,
  Download,
  Search,
  Trash2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { adminConfig, api } from "../lib/api";
import { apiRoute } from "../lib/apiUrl";
import { locale, t } from "../lib/i18n";
import { ErrorBox, Loading, PageHeader } from "../components/ui";
import { cn } from "@/lib/utils";

function when(utc) {
  return utc
    ? new Date(utc.replace(" ", "T") + "Z").toLocaleString(locale)
    : "";
}

export default function Conversations() {
  const { id } = useParams();
  return id ? <Detail id={id} /> : <List />;
}

function List() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  const [reload, setReload] = useState(0);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  // Excel splits columns on the computer's list separator: ";" in Germany, "," elsewhere (US, Google Sheets).
  const [delimiter, setDelimiter] = useState(
    (locale || "").toLowerCase().startsWith("de") ? "semicolon" : "comma",
  );

  useEffect(() => {
    api(`/conversations?page=${page}&search=${encodeURIComponent(query)}`)
      .then(setData)
      .catch((e) => setError(e.message));
  }, [page, query, reload]);

  // A browser download cannot send the REST nonce header, so it goes in the query string (admin-only route).
  function exportCsv() {
    const q = new URLSearchParams({ _wpnonce: adminConfig.nonce, delimiter });
    if (from) q.set("from", from);
    if (to) q.set("to", to);
    window.location.href = apiRoute(
      adminConfig.apiUrl,
      `/conversations/export?${q}`,
    );
  }

  async function removeAll() {
    if (
      !window.confirm(
        t("Delete ALL conversations permanently? This cannot be undone."),
      )
    )
      return;
    try {
      const r = await api("/conversations/delete-all", { method: "POST" });
      toast.success(t("%s conversations deleted", r.deleted));
      setPage(1);
      setReload((n) => n + 1);
    } catch (e) {
      toast.error(e.message);
    }
  }

  if (error) return <ErrorBox message={error} />;
  if (!data) return <Loading />;

  const pages = Math.max(1, Math.ceil(data.total / data.per_page));

  return (
    <>
      <PageHeader
        title={t("Conversations")}
        description={t(
          "What visitors ask – great input for improving your website content.",
        )}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
              {t("From")}
              <Input
                type="date"
                value={from}
                max={to || undefined}
                onChange={(e) => setFrom(e.target.value)}
                className="h-9 w-36"
              />
            </label>
            <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
              {t("To")}
              <Input
                type="date"
                value={to}
                min={from || undefined}
                onChange={(e) => setTo(e.target.value)}
                className="h-9 w-36"
              />
            </label>
            <select
              value={delimiter}
              onChange={(e) => setDelimiter(e.target.value)}
              aria-label={t("CSV separator")}
              title={t(
                "If Excel puts everything in one column, switch the separator.",
              )}
              className="h-9 rounded-md border border-input bg-background py-0 pl-3 pr-9 text-sm">
              <option value="semicolon">{t("Semicolon (German Excel)")}</option>
              <option value="comma">
                {t("Comma (US Excel, Google Sheets)")}
              </option>
            </select>
            <Button
              variant="outline"
              onClick={exportCsv}
              disabled={data.total === 0}>
              <Download className="mr-2 h-4 w-4" /> {t("Export CSV")}
            </Button>
            <Button
              variant="destructive"
              onClick={removeAll}
              disabled={data.total === 0}>
              <Trash2 className="mr-2 h-4 w-4" />{" "}
              {t("Delete all conversations")}
            </Button>
          </div>
        }
      />
      <form
        className="mb-4 flex max-w-md gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          setPage(1);
          setQuery(search);
        }}>
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={t("Search messages…")}
        />
        <Button type="submit" variant="outline" size="icon">
          <Search className="h-4 w-4" />
        </Button>
      </form>

      <Card>
        <CardContent className="p-0">
          {data.items.length === 0 ? (
            <p className="p-6 text-sm text-muted-foreground">
              {t("No conversations yet.")}
            </p>
          ) : (
            <ul className="divide-y pl-0">
              {data.items.map((c) => (
                <li key={c.id} className="list-none">
                  <Link
                    to={`/conversations/${c.id}`}
                    className="flex items-center gap-4 px-5 py-3 no-underline hover:bg-muted/50">
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">
                        {c.first_message || (
                          <em className="text-muted-foreground">
                            {t("(content not stored)")}
                          </em>
                        )}
                      </p>
                      <p className="truncate text-xs text-muted-foreground">
                        {when(c.updated_at)} ·{" "}
                        {+c.message_count === 1
                          ? t("%s question", c.message_count)
                          : t("%s questions", c.message_count)}
                        {c.page_url ? ` · ${c.page_url}` : ""}
                      </p>
                    </div>
                    {+c.booking_clicks > 0 && (
                      <span className="flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                        <CalendarCheck className="h-3.5 w-3.5" /> {t("booking")}
                      </span>
                    )}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      {pages > 1 && (
        <div className="mt-4 flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage(page - 1)}>
            {t("Previous")}
          </Button>
          <span className="text-sm text-muted-foreground">
            {t("Page %s of %s", page, pages)}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={page >= pages}
            onClick={() => setPage(page + 1)}>
            {t("Next")}
          </Button>
        </div>
      )}
    </>
  );
}

function Detail({ id }) {
  const navigate = useNavigate();
  const [conv, setConv] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    api(`/conversations/${id}`)
      .then(setConv)
      .catch((e) => setError(e.message));
  }, [id]);

  async function remove() {
    if (!window.confirm(t("Delete this conversation?"))) return;
    await api(`/conversations/${id}/delete`, { method: "POST" });
    toast.success(t("Deleted"));
    navigate("/conversations");
  }

  if (error) return <ErrorBox message={error} />;
  if (!conv) return <Loading />;

  return (
    <>
      <PageHeader
        title={t("Conversation #%s", conv.id)}
        description={`${when(conv.created_at)} · ${conv.provider || "—"} ${conv.model || ""}`}
        actions={
          <>
            <Button
              variant="outline"
              onClick={() => navigate("/conversations")}>
              <ArrowLeft className="mr-2 h-4 w-4" /> {t("Back")}
            </Button>
            <Button variant="destructive" onClick={remove}>
              <Trash2 className="mr-2 h-4 w-4" /> {t("Delete")}
            </Button>
          </>
        }
      />
      <Card>
        <CardContent className="space-y-3 p-6">
          {conv.messages.length === 0 && (
            <p className="text-sm text-muted-foreground">
              {t("Message content was not stored (logging disabled).")}
            </p>
          )}
          {conv.messages.map((m) => (
            <div
              key={m.id}
              className={cn(
                "flex",
                m.role === "user" ? "justify-end" : "justify-start",
              )}>
              <div
                className={cn(
                  "max-w-[75%] rounded-2xl px-4 py-2 text-sm",
                  m.role === "user"
                    ? "bg-primary text-primary-foreground"
                    : "border bg-white",
                )}>
                <p className="whitespace-pre-wrap">{m.content}</p>
                {m.sources?.length > 0 && (
                  <p className="mt-2 text-xs opacity-70">
                    {t("Sources:")}{" "}
                    {m.sources.map((s, i) => (
                      <a
                        key={i}
                        href={s.url}
                        target="_blank"
                        rel="noreferrer"
                        className="mr-2 underline">
                        {s.title}
                      </a>
                    ))}
                  </p>
                )}
                <p className="mt-1 text-[11px] opacity-60">
                  {when(m.created_at)}
                  {m.role === "assistant" &&
                    +m.output_tokens > 0 &&
                    ` · ${t("%s tokens", +m.input_tokens + +m.output_tokens)}`}
                </p>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </>
  );
}
