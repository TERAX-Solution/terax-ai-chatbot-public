import { render } from "preact";
import styles from "./widget.css?inline";
import { Widget } from "./Widget";

/**
 * Mounts the chat into every container:
 *   #terax-ai-widget   – floating launcher (wp_footer)
 *   .terax-ai-inline   – [terax_ai_chat] shortcode
 * Each mount gets its own Shadow DOM so theme styles can't interfere.
 */
/**
 * The config comes from the container's data-config. window.teraxAiWidget is only a fallback:
 * optimizer plugins may delay that inline script until after this bundle has run.
 */
function readConfig(el) {
  try {
    if (el.dataset.config) return JSON.parse(el.dataset.config);
  } catch (e) {
    // Fall through to the global.
  }
  return window.teraxAiWidget;
}

function mount(el) {
  if (el.__teraxMounted) return;
  const config = readConfig(el);
  // Only mark as mounted once we can really mount, so a later boot() can retry.
  if (!config) return;
  el.__teraxMounted = true;

  const shadow = el.attachShadow ? el.attachShadow({ mode: "open" }) : el;
  const style = document.createElement("style");
  style.textContent = styles;
  shadow.appendChild(style);
  const root = document.createElement("div");
  if (el.dataset.mode === "inline") root.style.height = "100%";
  shadow.appendChild(root);

  render(<Widget config={config} mode={el.dataset.mode || "floating"} />, root);
}

function boot() {
  document
    .querySelectorAll("#terax-ai-widget, .terax-ai-inline")
    .forEach(mount);
}

if (document.readyState === "loading")
  document.addEventListener("DOMContentLoaded", boot);
else boot();
// Retry once everything (including delayed scripts) has loaded; mount() is idempotent.
window.addEventListener("load", boot);
