import { NavLink, Outlet, useLocation } from "react-router-dom";
import { Toaster } from "sonner";
import {
  Bot,
  BookOpen,
  Gauge,
  HelpCircle,
  Inbox,
  KeyRound,
  Lightbulb,
  MessagesSquare,
  Settings,
} from "lucide-react";
import { adminConfig } from "../lib/api";
import { cn } from "@/lib/utils";
import { t } from "../lib/i18n";
import { Suspense, useEffect } from "react";
import { ProBanner } from "./ProBanner";
import { useLicenseChanges } from "../lib/licenseEvents";
import { Loading } from "./ui";

const NAV = [
  { to: "/dashboard", label: t("Dashboard"), icon: Gauge },
  { to: "/settings", label: t("Settings"), icon: Settings },
  { to: "/knowledge", label: t("Knowledge"), icon: BookOpen },
  { to: "/conversations", label: t("Conversations"), icon: MessagesSquare },
  { to: "/insights", label: t("Insights"), icon: Lightbulb },
  { to: "/requests", label: t("Requests"), icon: Inbox },
  // The license belongs to the Pro add-on, which tells the admin app that it is active.
  { to: "/license", label: t("License"), icon: KeyRound, pro: true },
].filter((item) => !item.pro || adminConfig.pro);

export default function Layout() {
  const location = useLocation();
  useLicenseChanges();

  // WordPress marks the active submenu item in PHP, which never sees the "#/route" part of the URL,
  // so it always highlights Dashboard. Mirror the current route onto the WP sidebar instead.
  useEffect(() => {
    const route = location.pathname.split("/")[1] || "dashboard";

    document
      .querySelectorAll("#toplevel_page_terax-ai-assistant .wp-submenu li")
      .forEach((li) => {
        const link = li.querySelector("a");
        if (!link) return;

        // The first submenu item (Dashboard) has no hash in its href.
        const target =
          new URL(link.href, window.location.href).hash.split("/")[1] ||
          "dashboard";
        const active = target === route;

        li.classList.toggle("current", active);
        link.classList.toggle("current", active);
        if (active) {
          link.setAttribute("aria-current", "page");
        } else {
          link.removeAttribute("aria-current");
        }
      });
  }, [location]);

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-6 px-6">
          <div className="flex items-center gap-2 py-4 font-semibold">
            <span className="grid h-8 w-8 place-items-center rounded-lg bg-primary text-primary-foreground">
              <Bot className="h-5 w-5" />
            </span>
            TERAX AI Assistant
            <span className="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium uppercase text-slate-600">
              {adminConfig.planLabel || adminConfig.plan}
            </span>
          </div>
          <nav className="flex flex-wrap gap-1">
            {NAV.map(({ to, label, icon: Icon }) => (
              <NavLink
                key={to}
                to={to}
                className={({ isActive }) =>
                  cn(
                    "flex items-center gap-2 border-b-2 px-3 py-4 text-sm font-medium no-underline transition-colors",
                    isActive
                      ? "border-primary text-primary"
                      : "border-transparent text-muted-foreground hover:text-foreground",
                  )
                }>
                <Icon className="h-4 w-4" />
                {label}
              </NavLink>
            ))}
          </nav>
          {adminConfig.docsUrl && (
            <a
              href={adminConfig.docsUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="ml-auto flex items-center gap-1.5 py-4 text-sm font-medium text-muted-foreground no-underline hover:text-foreground">
              <HelpCircle className="h-4 w-4" />
              {t("Help")}
            </a>
          )}
        </div>
      </header>
      <ProBanner />
      <main className="mx-auto max-w-6xl px-6 py-8">
        <Suspense fallback={<Loading />}>
          <Outlet />
        </Suspense>
      </main>
      <Toaster richColors position="bottom-right" />
    </div>
  );
}
