import { useEffect, useRef, useState } from "preact/hooks";
import { post } from "./api";
import { palette } from "./color";
import { t } from "./i18n";
import { Markdown } from "./markdown";
import { store, uuid } from "./storage";

const STATE_KEY = "terax_ai_chat";
const CONSENT_KEY = "terax_ai_consent";
const FOCUSABLE =
  'button:not([disabled]), a[href], input:not([disabled]):not([tabindex="-1"]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])';

function Icon({ name }) {
  const paths = {
    chat: "M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2z",
    close: "M6 6l12 12M18 6L6 18",
    send: "M4 12l16-8-6 16-2-6-8-2z",
    calendar:
      "M7 3v3M17 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z",
    refresh: "M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7",
    phone:
      "M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z",
  };
  return (
    <svg
      viewBox="0 0 24 24"
      width="20"
      height="20"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true">
      <path d={paths[name]} />
    </svg>
  );
}

/**
 * Contact form in the chat. kind "lead" (sales mode): name, phone or e-mail, message, posts to /lead.
 * kind "callback": name, phone, preferred time, reason, posts to /callback.
 */
function LeadForm({ config, lang, session, onDone, kind = "lead" }) {
  const callback = kind === "callback";
  const [v, setV] = useState({
    name: "",
    phone: "",
    email: "",
    message: "",
    preferred_time: "any",
    consent: false,
    website: "",
  });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");
  const nameRef = useRef(null);

  useEffect(() => {
    if (nameRef.current) nameRef.current.focus();
  }, []);

  const set = (k) => (e) =>
    setV({
      ...v,
      [k]:
        e.currentTarget.type === "checkbox"
          ? e.currentTarget.checked
          : e.currentTarget.value,
    });

  async function submit(e) {
    e.preventDefault();
    if (busy) return;
    if (
      !v.name.trim() ||
      (callback ? !v.phone.trim() : !v.phone.trim() && !v.email.trim())
    )
      return setErr(t(lang, callback ? "callbackNeed" : "leadNeed"));
    if (!v.consent) return setErr(t(lang, "leadConsentNeed"));
    setBusy(true);
    setErr("");
    try {
      await post(config.endpoint, callback ? "/callback" : "/lead", {
        ...v,
        session_id: session,
        page_url: window.location.href,
      });
      onDone();
    } catch (x) {
      setErr(x.serverMessage || t(lang, "error"));
      setBusy(false);
    }
  }

  return (
    <form
      class="tx-lead"
      onSubmit={submit}
      aria-label={t(lang, callback ? "callbackTitle" : "leadTitle")}>
      <label>
        {t(lang, "leadName")}
        <input
          ref={nameRef}
          type="text"
          autocomplete="name"
          maxLength={120}
          value={v.name}
          onInput={set("name")}
        />
      </label>
      <label>
        {t(lang, "leadPhone")}
        <input
          type="tel"
          autocomplete="tel"
          maxLength={50}
          value={v.phone}
          onInput={set("phone")}
        />
      </label>
      {callback ? (
        <label>
          {t(lang, "callbackTime")}
          <select value={v.preferred_time} onChange={set("preferred_time")}>
            <option value="any">{t(lang, "timeAny")}</option>
            <option value="morning">{t(lang, "timeMorning")}</option>
            <option value="afternoon">{t(lang, "timeAfternoon")}</option>
          </select>
        </label>
      ) : (
        <label>
          {t(lang, "leadEmail")}
          <input
            type="email"
            autocomplete="email"
            maxLength={190}
            value={v.email}
            onInput={set("email")}
          />
        </label>
      )}
      <label>
        {t(lang, callback ? "callbackReason" : "leadMessage")}
        <textarea
          rows={2}
          maxLength={1000}
          value={v.message}
          onInput={set("message")}
        />
      </label>
      {/* Honeypot: invisible to people, bots fill it. */}
      <input
        class="tx-hp"
        type="text"
        name="website"
        tabindex="-1"
        autocomplete="off"
        aria-hidden="true"
        value={v.website}
        onInput={set("website")}
      />
      <label class="tx-check">
        <input type="checkbox" checked={v.consent} onChange={set("consent")} />
        <span>
          {t(lang, "leadConsent")}
          {config.privacyUrl && (
            <>
              {" "}
              <a
                href={config.privacyUrl}
                target="_blank"
                rel="noopener noreferrer">
                {t(lang, "privacy")}
              </a>
            </>
          )}
        </span>
      </label>
      {err && (
        <p class="tx-lead-err" role="alert">
          {err}
        </p>
      )}
      <button type="submit" class="tx-primary" disabled={busy}>
        {t(lang, "leadSend")}
      </button>
    </form>
  );
}

export function Widget({ config, mode }) {
  const inline = mode === "inline";
  const lang = config.lang === "de" ? "de" : "en";
  const saved = store.get(STATE_KEY, null);

  const [open, setOpen] = useState(inline || (saved && saved.open) || false);
  const [messages, setMessages] = useState((saved && saved.messages) || []);
  const [session, setSession] = useState((saved && saved.session) || uuid());
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [consented, setConsented] = useState(
    !config.consent || store.getLocal(CONSENT_KEY) === "1",
  );
  const [modalUrl, setModalUrl] = useState(null);
  const [leadOpen, setLeadOpen] = useState(null); // index of the message whose lead form is open
  const [leadDone, setLeadDone] = useState({});
  const listRef = useRef(null);
  const inputRef = useRef(null);
  const launcherRef = useRef(null);
  const panelRef = useRef(null);
  const consentBtnRef = useRef(null);
  const modalCloseRef = useRef(null);
  const userOpened = useRef(false); // Focus only moves after a user action, never on page load or auto-open.
  const modalTrigger = useRef(null);

  // Persist across page navigations (same tab only).
  useEffect(() => {
    if (!inline)
      store.set(STATE_KEY, { open, messages: messages.slice(-30), session });
  }, [open, messages, session]);

  useEffect(() => {
    if (listRef.current)
      listRef.current.scrollTop = listRef.current.scrollHeight;
  }, [messages, busy, open]);

  // Dialog focus: into the panel when opened by the user, back to the launcher when closed.
  useEffect(() => {
    if (inline || !userOpened.current) return;
    if (open) {
      const target = consented ? inputRef.current : consentBtnRef.current;
      if (target) target.focus();
    } else if (launcherRef.current) {
      launcherRef.current.focus();
    }
  }, [open, consented]);

  useEffect(() => {
    if (modalUrl) {
      if (modalCloseRef.current) modalCloseRef.current.focus();
    } else if (modalTrigger.current) {
      modalTrigger.current.focus();
      modalTrigger.current = null;
    }
  }, [modalUrl]);

  // Optional auto-open once per session.
  useEffect(() => {
    if (inline || !Number(config.autoOpenSecs) || saved) return;
    const id = setTimeout(
      () => setOpen(true),
      Number(config.autoOpenSecs) * 1000,
    );
    return () => clearTimeout(id);
  }, []);

  useEffect(() => {
    const onKey = (e) => {
      if (e.key !== "Escape" || inline) return;
      userOpened.current = true; // Closing by keyboard is a user action too: return focus to the launcher.
      if (modalUrl) setModalUrl(null);
      else setOpen(false);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [modalUrl]);

  // Keep Tab inside the dialog (or inside the booking modal while it is open).
  function trapFocus(e) {
    if (e.key !== "Tab" || inline || !panelRef.current) return;
    const scope = modalUrl
      ? panelRef.current.querySelector(".tx-modal")
      : panelRef.current;
    const items = Array.from(scope.querySelectorAll(FOCUSABLE));
    if (!items.length) return;
    const active = panelRef.current.getRootNode().activeElement;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && (active === first || !items.includes(active))) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && (active === last || !items.includes(active))) {
      e.preventDefault();
      first.focus();
    }
  }

  function openChat() {
    userOpened.current = true;
    setOpen(true);
  }

  function closeChat() {
    userOpened.current = true;
    setOpen(false);
  }

  async function send(text) {
    const message = (text ?? input).trim();
    if (!message || busy) return;
    setInput("");
    const history = messages
      .filter((m) => !m.error)
      .map(({ role, content }) => ({ role, content }));
    setMessages((prev) => [...prev, { role: "user", content: message }]);
    setBusy(true);
    try {
      const data = await post(config.endpoint, "/chat", {
        message,
        history: history.slice(-10),
        session_id: session,
        page_url: window.location.href,
        // Sales mode: tells the server a button was just offered, so it does not nag in every reply.
        recent_cta: messages
          .filter((m) => m.role === "assistant")
          .slice(-2)
          .some((m) =>
            (m.actions || []).some(
              (a) =>
                a &&
                (a.type === "lead" ||
                  a.type === "link" ||
                  a.type === "callback"),
            ),
          ),
      });
      setMessages((prev) => [
        ...prev,
        {
          role: "assistant",
          content: data.reply,
          sources: data.sources || [],
          actions: data.actions || [],
        },
      ]);
    } catch (err) {
      setMessages((prev) => [
        ...prev,
        {
          role: "assistant",
          error: true,
          content: err.serverMessage || t(lang, "error"),
        },
      ]);
    } finally {
      setBusy(false);
    }
  }

  function book(action) {
    post(config.endpoint, "/booking-click", { session_id: session }).catch(
      () => {},
    );
    if (action.open_in === "modal") {
      if (panelRef.current)
        modalTrigger.current = panelRef.current.getRootNode().activeElement;
      setModalUrl(action.url);
    } else window.open(action.url, "_blank", "noopener");
  }

  function restart() {
    setMessages([]);
    setSession(uuid());
  }

  function acceptConsent() {
    store.setLocal(CONSENT_KEY, "1");
    userOpened.current = true;
    setConsented(true);
  }

  // Text colors are derived from the brand color so any color stays readable (WCAG 1.4.3).
  const colors = palette(config.color, config.textOnBrand);
  const style = {
    "--tx-color": colors.color,
    "--tx-on": colors.on,
    "--tx-link": colors.link,
  };

  if (!open) {
    return (
      <div class={`tx-root tx-${config.position}`} style={style} lang={lang}>
        <button
          ref={launcherRef}
          class="tx-launcher"
          aria-label={t(lang, "open")}
          aria-haspopup="dialog"
          onClick={openChat}>
          <Icon name="chat" />
        </button>
      </div>
    );
  }

  return (
    <div
      class={`tx-root tx-${config.position} ${inline ? "tx-inline" : "tx-floating"}`}
      style={style}
      lang={lang}>
      <section
        ref={panelRef}
        class="tx-panel"
        role={inline ? "region" : "dialog"}
        aria-modal={inline ? undefined : "true"}
        aria-label={config.name}
        onKeyDown={trapFocus}>
        {/* div, not <header>/<footer>: those would become extra banner/contentinfo landmarks of the host page. */}
        <div class="tx-header">
          <div class="tx-avatar" aria-hidden="true">
            <Icon name="chat" />
          </div>
          <div class="tx-title">
            <strong>{config.name}</strong>
            <span>{config.phone ? `☎ ${config.phone}` : " "}</span>
          </div>
          {config.booking && (
            <button
              class="tx-icon-btn"
              title={config.booking.label}
              aria-label={config.booking.label}
              onClick={() => book(config.booking)}>
              <Icon name="calendar" />
            </button>
          )}
          {config.callback && (
            <button
              class="tx-icon-btn"
              title={t(lang, "callbackBtn")}
              aria-label={t(lang, "callbackBtn")}
              aria-expanded={leadOpen === "h"}
              onClick={() => setLeadOpen(leadOpen === "h" ? null : "h")}>
              <Icon name="phone" />
            </button>
          )}
          {messages.length > 0 && (
            <button
              class="tx-icon-btn"
              title={t(lang, "restart")}
              aria-label={t(lang, "restart")}
              onClick={restart}>
              <Icon name="refresh" />
            </button>
          )}
          {!inline && (
            <button
              class="tx-icon-btn"
              aria-label={t(lang, "close")}
              onClick={closeChat}>
              <Icon name="close" />
            </button>
          )}
        </div>

        {!consented ? (
          <div class="tx-consent">
            <p>{config.consent}</p>
            {config.privacyUrl && (
              <a
                href={config.privacyUrl}
                target="_blank"
                rel="noopener noreferrer">
                {t(lang, "privacy")}
              </a>
            )}
            <button
              ref={consentBtnRef}
              class="tx-primary"
              onClick={acceptConsent}>
              {t(lang, "start")}
            </button>
          </div>
        ) : (
          <>
            <div
              class="tx-messages"
              ref={listRef}
              role="log"
              aria-live="polite"
              aria-relevant="additions"
              aria-label={config.name}
              tabindex="0">
              <div class="tx-msg tx-assistant">
                <div class="tx-bubble">
                  <Markdown text={config.greeting} hosts={config.linkHosts} />
                </div>
              </div>

              {messages.length === 0 &&
                config.suggestions &&
                config.suggestions.length > 0 && (
                  <div class="tx-suggestions">
                    {config.suggestions.map((s) => (
                      <button key={s} onClick={() => send(s)}>
                        {s}
                      </button>
                    ))}
                  </div>
                )}

              {messages.map((m, i) => (
                <div
                  key={i}
                  class={`tx-msg tx-${m.role} ${m.error ? "tx-error" : ""}`}>
                  <div class="tx-bubble">
                    {m.role === "assistant" ? (
                      <Markdown text={m.content} hosts={config.linkHosts} />
                    ) : (
                      m.content
                    )}
                  </div>
                  {m.actions &&
                    m.actions.map((a, ai) => {
                      if (a && a.type === "link") {
                        return (
                          <button
                            key={ai}
                            class="tx-primary tx-action"
                            onClick={() => book(a)}>
                            <Icon name="calendar" /> {a.label}
                          </button>
                        );
                      }
                      if (a && (a.type === "lead" || a.type === "callback")) {
                        const cb = a.type === "callback";
                        if (leadDone[i]) {
                          return (
                            <p key={ai} class="tx-lead-done" role="status">
                              {t(lang, cb ? "callbackThanks" : "leadThanks")}
                            </p>
                          );
                        }
                        return leadOpen === i ? (
                          <LeadForm
                            key={ai}
                            kind={a.type}
                            config={config}
                            lang={lang}
                            session={session}
                            onDone={() => {
                              setLeadDone({ ...leadDone, [i]: true });
                              setLeadOpen(null);
                            }}
                          />
                        ) : (
                          <button
                            key={ai}
                            class="tx-primary tx-action"
                            onClick={() => setLeadOpen(i)}>
                            <Icon name={cb ? "phone" : "chat"} />{" "}
                            {cb ? t(lang, "callbackBtn") : a.label}
                          </button>
                        );
                      }
                      return null;
                    })}
                  {m.sources && m.sources.length > 0 && (
                    <div class="tx-sources">
                      {t(lang, "sources")}:{" "}
                      {m.sources.map((s, si) => (
                        <a key={si} href={s.url}>
                          {s.title}
                        </a>
                      ))}
                    </div>
                  )}
                </div>
              ))}

              {/* Opened from the header button: not tied to one reply, so it sits at the end of the list. */}
              {config.callback && (leadOpen === "h" || leadDone.h) && (
                <div class="tx-msg tx-assistant">
                  {leadDone.h ? (
                    <p class="tx-lead-done" role="status">
                      {t(lang, "callbackThanks")}
                    </p>
                  ) : (
                    <LeadForm
                      kind="callback"
                      config={config}
                      lang={lang}
                      session={session}
                      onDone={() => {
                        setLeadDone({ ...leadDone, h: true });
                        setLeadOpen(null);
                      }}
                    />
                  )}
                </div>
              )}

              {busy && (
                <div class="tx-msg tx-assistant">
                  <div class="tx-bubble tx-typing">
                    <span class="tx-dot" aria-hidden="true" />
                    <span class="tx-dot" aria-hidden="true" />
                    <span class="tx-dot" aria-hidden="true" />
                    <span class="tx-sr">{t(lang, "typing")}</span>
                  </div>
                </div>
              )}
            </div>

            <form
              class="tx-input"
              onSubmit={(e) => {
                e.preventDefault();
                send();
              }}>
              <textarea
                ref={inputRef}
                rows={1}
                value={input}
                maxLength={config.maxLength || 1000}
                placeholder={t(lang, "placeholder")}
                aria-label={t(lang, "placeholder")}
                onInput={(e) => setInput(e.currentTarget.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" && !e.shiftKey) {
                    e.preventDefault();
                    send();
                  }
                }}
              />
              <button
                type="submit"
                class="tx-send"
                disabled={busy || !input.trim()}
                aria-label={t(lang, "send")}>
                <Icon name="send" />
              </button>
            </form>
          </>
        )}

        {config.branding && (
          <div class="tx-branding">
            <a
              href={config.branding.url}
              target="_blank"
              rel="noopener noreferrer">
              {config.branding.text}
            </a>
          </div>
        )}

        {modalUrl && (
          <div
            class="tx-modal"
            role="dialog"
            aria-modal="true"
            aria-label={
              config.booking ? config.booking.label : t(lang, "book")
            }>
            <button
              ref={modalCloseRef}
              class="tx-icon-btn tx-modal-close"
              aria-label={t(lang, "close")}
              onClick={() => setModalUrl(null)}>
              <Icon name="close" />
            </button>
            <iframe
              src={modalUrl}
              title={config.booking ? config.booking.label : t(lang, "book")}
            />
          </div>
        )}
      </section>
    </div>
  );
}
