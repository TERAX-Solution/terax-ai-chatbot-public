import { adminConfig } from "./api";

const facts = () => adminConfig.planFacts || {};

/** "Pro", "Pro & Agency", "Starter, Pro & Agency": plan names that include a feature, from config/plans.php. */
export function plansWith(feature) {
  const labels = facts()[feature] || [];
  if (labels.length < 2) return labels.join("");
  return `${labels.slice(0, -1).join(", ")} & ${labels[labels.length - 1]}`;
}

/** Monthly messages of the first plan with Managed AI (-1 = unlimited, null = unknown). */
export function managedAiMessages() {
  const n = facts().managed_ai_msgs;
  return typeof n === "number" ? n : null;
}

/** Name of the first plan without a message limit, or null when there is none. */
export function unlimitedPlan() {
  return facts().unlimited_plan || null;
}
