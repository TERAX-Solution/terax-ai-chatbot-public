import { useState } from "react";
import { Button } from "@/components/ui/button";
import { t } from "../lib/i18n";
import { Field, Select } from "./ui";

/** The settings an industry preset recommends, as [setting path, value, label of what it changes]. */
function recommended(industry) {
  const x = industry.texts;
  return [
    ["assistant.name", x.name, t("Assistant name")],
    ["assistant.business_type", x.business_type, t("Business type")],
    ["assistant.greeting", x.greeting, t("Greeting")],
    ["assistant.suggestions", x.suggestions, t("Suggested questions")],
    ["privacy.consent_text", x.consent_text, t("Consent text")],
    ["booking.preset", x.booking_preset, t("Booking system")],
    ["booking.label", x.booking_label, t("Button label")],
  ];
}

function read(draft, path) {
  return path.split(".").reduce((o, k) => (o ? o[k] : undefined), draft);
}

/**
 * "Industry" select. Switching it never overwrites texts silently: the admin is asked first
 * ("Apply recommended texts?"). In the setup wizard nothing was edited yet, so `autoApply` fills them straight away.
 */
export function IndustryPicker({ s, autoApply = false }) {
  const industries = s.options?.industries || {};
  const ids = Object.keys(industries);
  const [pending, setPending] = useState(null);

  if (!ids.length) return null;

  const current =
    s.draft.assistant.industry && industries[s.draft.assistant.industry]
      ? s.draft.assistant.industry
      : ids[0];

  const changes = (id) =>
    recommended(industries[id]).filter(
      ([path, value]) =>
        JSON.stringify(read(s.draft, path)) !== JSON.stringify(value),
    );

  function apply(id) {
    recommended(industries[id]).forEach(([path, value]) => s.set(path, value));
    setPending(null);
  }

  function change(id) {
    s.set("assistant.industry", id);
    if (industries[id].sales_allowed === false)
      s.set("assistant.mode", "informative"); // e.g. dental: no sales mode
    if (autoApply) {
      apply(id);
    } else {
      setPending(changes(id).length ? id : null);
    }
  }

  return (
    <div className="grid gap-4">
      <Field
        label={t("Industry")}
        htmlFor="industry"
        help={t(
          "Sets the assistant's rules, example questions and booking keywords for your type of business.",
        )}>
        <Select
          id="industry"
          value={current}
          onChange={change}
          options={ids.map((id) => ({
            value: id,
            label: t(industries[id].label),
          }))}
        />
      </Field>

      {pending && (
        <div
          className="rounded-lg border border-primary/30 bg-primary/5 p-4"
          role="group"
          aria-label={t("Apply recommended texts?")}>
          <p className="m-0 font-medium">{t("Apply recommended texts?")}</p>
          <p className="m-0 mt-1 text-sm text-muted-foreground">
            {t(
              "These settings would be replaced with the recommendation for %s:",
              t(industries[pending].label),
            )}{" "}
            {changes(pending)
              .map(([, , label]) => label)
              .join(", ")}
            .
          </p>
          <div className="mt-3 flex gap-2">
            <Button size="sm" onClick={() => apply(pending)}>
              {t("Apply recommended texts")}
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() => setPending(null)}>
              {t("Keep my texts")}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
