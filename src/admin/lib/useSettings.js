import { useEffect, useState } from "react";
import { toast } from "sonner";
import { api } from "./api";
import { t } from "./i18n";

/**
 * Loads settings once, keeps an editable draft and saves it.
 * New API keys are kept separately so masked hints are never sent back.
 */
export function useSettings() {
  const [payload, setPayload] = useState(null);
  const [draft, setDraft] = useState(null);
  const [newKeys, setNewKeys] = useState({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    api("/settings")
      .then((p) => {
        setPayload(p);
        setDraft(p.settings);
      })
      .catch((e) => setError(e.message));
  }, []);

  /** set("assistant.name", "Bot") */
  function set(path, value) {
    setDraft((prev) => {
      const next = structuredClone(prev);
      const keys = path.split(".");
      let obj = next;
      keys.slice(0, -1).forEach((k) => (obj = obj[k]));
      obj[keys[keys.length - 1]] = value;
      return next;
    });
  }

  /** `extra` is merged over the draft, for values that must not wait for a state update (e.g. setup.completed). */
  async function save(extra = {}) {
    setSaving(true);
    try {
      const body = { ...structuredClone(draft), ...extra };
      body.ai.api_keys = newKeys;
      const p = await api("/settings", { method: "POST", body });
      setPayload(p);
      setDraft(p.settings);
      setNewKeys({});
      toast.success(t("Settings saved"));
      return p;
    } catch (e) {
      toast.error(e.message);
    } finally {
      setSaving(false);
    }
  }

  const dirty =
    payload &&
    draft &&
    (JSON.stringify(payload.settings) !== JSON.stringify(draft) ||
      Object.keys(newKeys).length > 0);

  return {
    draft,
    options: payload?.options,
    set,
    save,
    saving,
    dirty,
    error,
    newKeys,
    setNewKeys,
  };
}
