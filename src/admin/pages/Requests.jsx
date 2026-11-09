import { useEffect, useState } from "react";
import { toast } from "sonner";
import { Check, Mail, Phone, Trash2, Undo2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { api } from "../lib/api";
import { locale, t } from "../lib/i18n";
import { ErrorBox, Loading, PageHeader } from "../components/ui";
import { cn } from "@/lib/utils";

function when(utc) {
  return utc
    ? new Date(utc.replace(" ", "T") + "Z").toLocaleString(locale)
    : "";
}

const FILTERS = [
  { id: "", label: () => t("All") },
  { id: "new", label: () => t("New") },
  { id: "done", label: () => t("Done") },
];

/** Leads (sales mode) and call-back requests (TX-459), newest first. Rows are deleted by the retention setting too. */
export default function Requests() {
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    api(`/requests?page=${page}&status=${status}`)
      .then(setData)
      .catch((e) => setError(e.message));
  }, [page, status, reload]);

  async function mark(id, next) {
    try {
      await api(`/requests/${id}/status`, {
        method: "POST",
        body: { status: next },
      });
      setReload((n) => n + 1);
    } catch (e) {
      toast.error(e.message);
    }
  }

  async function remove(id) {
    if (!window.confirm(t("Delete this request permanently?"))) return;
    try {
      await api(`/requests/${id}/delete`, { method: "POST" });
      setReload((n) => n + 1);
    } catch (e) {
      toast.error(e.message);
    }
  }

  if (error) return <ErrorBox message={error} />;
  if (!data) return <Loading />;

  const pages = Math.max(1, Math.ceil(data.total / 20));

  return (
    <>
      <PageHeader
        title={t("Requests")}
        description={t(
          "Visitors who left their details in the chat: call-back requests and contact forms. They are also e-mailed to you and deleted after the retention period.",
        )}
      />

      <div
        className="mb-4 flex gap-1 rounded-md bg-muted p-1 w-fit"
        role="group"
        aria-label={t("Filter")}>
        {FILTERS.map((f) => (
          <button
            key={f.id}
            onClick={() => {
              setStatus(f.id);
              setPage(1);
            }}
            aria-pressed={status === f.id}
            className={cn(
              "rounded px-3 py-1 text-sm font-medium",
              status === f.id
                ? "bg-white shadow-sm"
                : "text-muted-foreground hover:text-foreground",
            )}>
            {f.label()}
            {f.id === "new" && data.new > 0 ? ` (${data.new})` : ""}
          </button>
        ))}
      </div>

      <Card>
        <CardContent className="p-0">
          {data.items.length === 0 ? (
            <p className="p-6 text-sm text-muted-foreground">
              {t("No requests yet.")}
            </p>
          ) : (
            <ul className="divide-y pl-0">
              {data.items.map((r) => (
                <li
                  key={r.id}
                  className="flex flex-wrap items-start justify-between gap-3 p-4">
                  <div className="min-w-0 space-y-1">
                    <p className="font-medium">
                      {r.name}{" "}
                      <span
                        className={cn(
                          "ml-1 rounded px-1.5 py-0.5 text-[11px] font-medium",
                          r.status === "new"
                            ? "bg-amber-100 text-amber-800"
                            : "bg-slate-100 text-slate-600",
                        )}>
                        {r.status === "new" ? t("New") : t("Done")}
                      </span>
                      <span className="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                        {r.type === "callback"
                          ? t("Call back")
                          : t("Contact form")}
                      </span>
                    </p>
                    <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                      {r.phone && (
                        <a
                          href={`tel:${r.phone}`}
                          className="flex items-center gap-1">
                          <Phone className="h-3.5 w-3.5" /> {r.phone}
                        </a>
                      )}
                      {r.email && (
                        <a
                          href={`mailto:${r.email}`}
                          className="flex items-center gap-1">
                          <Mail className="h-3.5 w-3.5" /> {r.email}
                        </a>
                      )}
                      <span>{when(r.created_at)}</span>
                    </p>
                    {r.message && (
                      <p className="whitespace-pre-line text-sm">{r.message}</p>
                    )}
                  </div>
                  <div className="flex gap-2">
                    {r.status === "new" ? (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => mark(r.id, "done")}>
                        <Check className="mr-1.5 h-4 w-4" /> {t("Mark as done")}
                      </Button>
                    ) : (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => mark(r.id, "new")}>
                        <Undo2 className="mr-1.5 h-4 w-4" /> {t("Mark as new")}
                      </Button>
                    )}
                    <Button
                      size="icon"
                      variant="outline"
                      title={t("Delete")}
                      aria-label={t("Delete")}
                      onClick={() => remove(r.id)}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      {pages > 1 && (
        <div className="mt-4 flex items-center gap-3 text-sm">
          <Button
            variant="outline"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage(page - 1)}>
            {t("Previous")}
          </Button>
          <span>{t("Page %s of %s", page, pages)}</span>
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
