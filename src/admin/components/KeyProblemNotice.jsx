import { useState } from "react";
import { Button } from "@/components/ui/button";
import { t } from "../lib/i18n";

const DISMISS_KEY = "terax_ai_dismiss_no_openssl";

function dismissed() {
  try {
    return localStorage.getItem(DISMISS_KEY) === "1";
  } catch (e) {
    return false;
  }
}

/**
 * TX-503: a stored API key the plugin cannot use safely.
 *  - "unreadable": the key cannot be decrypted (salts changed, database moved). Always shown: the chat cannot answer.
 *  - "no_openssl": the server has no OpenSSL, so keys are stored as plain text. Dismissible.
 */
export function KeyProblemNotice({ problem, className = "mb-6" }) {
  const [hidden, setHidden] = useState(dismissed);
  if (!problem || (problem === "no_openssl" && hidden)) return null;

  const unreadable = problem === "unreadable";
  return (
    <div
      role={unreadable ? "alert" : "status"}
      className={`${className} flex flex-wrap items-center justify-between gap-3 rounded-md border p-4 text-sm ${
        unreadable
          ? "border-red-200 bg-red-50 text-red-900"
          : "border-amber-200 bg-amber-50 text-amber-900"
      }`}>
      <span>
        {unreadable
          ? t(
              "The saved API key cannot be read any more (the site's security keys changed or the database was moved). Please re-enter the API key.",
            )
          : t(
              "This server has no PHP OpenSSL extension, so your API key is stored as plain text. Ask your host to enable OpenSSL.",
            )}
      </span>
      {!unreadable && (
        <Button
          size="sm"
          variant="outline"
          onClick={() => {
            try {
              localStorage.setItem(DISMISS_KEY, "1");
            } catch (e) {
              /* storage blocked: the notice just comes back next time */
            }
            setHidden(true);
          }}>
          {t("Dismiss")}
        </Button>
      )}
    </div>
  );
}
