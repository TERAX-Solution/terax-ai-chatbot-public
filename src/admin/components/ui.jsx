import { Loader2 } from "lucide-react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { t } from "../lib/i18n";

export function PageHeader({ title, description, actions }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description && (
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        )}
      </div>
      {actions && <div className="flex gap-2">{actions}</div>}
    </div>
  );
}

export function Section({ title, description, children, className }) {
  return (
    <Card className={cn("mb-6", className)}>
      <CardHeader className="pb-4">
        <CardTitle className="text-base">{title}</CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
      </CardHeader>
      <CardContent className="space-y-5">{children}</CardContent>
    </Card>
  );
}

export function Field({ label, help, htmlFor, children, locked }) {
  return (
    <div className="grid gap-2">
      {label && (
        <Label htmlFor={htmlFor} className="flex items-center gap-2">
          {label}
          {locked && (
            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">
              {locked}
            </span>
          )}
        </Label>
      )}
      {children}
      {help && <p className="text-xs text-muted-foreground">{help}</p>}
    </div>
  );
}

/** Native select (no portal → works inside the scoped WP admin styles). */
export function Select({ value, onChange, options, id, disabled }) {
  return (
    <select
      id={id}
      value={value ?? ""}
      disabled={disabled}
      onChange={(e) => onChange(e.target.value)}
      className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50">
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

export function Spinner({ className }) {
  return <Loader2 className={cn("h-4 w-4 animate-spin", className)} />;
}

export function Loading() {
  return (
    <div className="flex items-center gap-2 p-8 text-muted-foreground">
      <Spinner /> {t("Loading…")}
    </div>
  );
}

export function ErrorBox({ message }) {
  return (
    <div className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
      {message}
    </div>
  );
}

export function Stat({ label, value, hint }) {
  return (
    <Card>
      <CardContent className="p-5">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {label}
        </p>
        <p className="mt-2 text-2xl font-semibold tabular-nums">{value}</p>
        {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
      </CardContent>
    </Card>
  );
}

export function Check({ ok, label, hint, to }) {
  return (
    <div className="flex items-start gap-3 py-2">
      <span
        className={cn(
          "mt-1 h-2.5 w-2.5 flex-none rounded-full",
          ok ? "bg-emerald-500" : "bg-amber-500",
        )}
      />
      <div className="flex-1">
        <p className="text-sm font-medium">{label}</p>
        {!ok && hint && <p className="text-xs text-muted-foreground">{hint}</p>}
      </div>
      {!ok && to && (
        <a href={to} className="text-xs font-medium text-primary underline">
          {t("Fix")}
        </a>
      )}
    </div>
  );
}
