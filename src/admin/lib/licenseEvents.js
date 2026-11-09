import { useEffect, useState } from "react";
import { adminConfig } from "./api";

const EVENT = "terax-license-changed";

/**
 * `window.teraxAiAdmin` is rendered once by PHP, so after a license change (activate, deactivate,
 * check) the plan-based parts of the shell would stay stale until a reload. Update the shared
 * config from the fresh /license response and tell mounted components to re-render.
 */
export function applyLicense(license) {
  adminConfig.plan = license.plan;
  adminConfig.planLabel = license.plan_label || license.plan;
  adminConfig.managedAi = !!(license.features && license.features.managed_ai);
  window.dispatchEvent(new Event(EVENT));
}

/** Re-render the calling component whenever the license changes. */
export function useLicenseChanges() {
  const [, setVersion] = useState(0);
  useEffect(() => {
    const bump = () => setVersion((v) => v + 1);
    window.addEventListener(EVENT, bump);
    return () => window.removeEventListener(EVENT, bump);
  }, []);
}
