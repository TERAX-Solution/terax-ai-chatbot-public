/**
 * Tiny, safe Markdown renderer for assistant replies.
 * Produces VNodes only (never innerHTML), so model output cannot inject HTML.
 * Supports: paragraphs, line breaks, - / 1. lists, **bold**, *italic*, [links](https://…).
 * Web links only become clickable for trusted hosts (TX-500); mailto: and tel: always do.
 */

import { Fragment } from "preact";

const SAFE_URL = /^(https?:|mailto:|tel:)/i;

/** True when the URL may be rendered as a link: mailto/tel, or http(s) on an allowed host or its subdomain. */
export function trusted(href, hosts) {
  if (!SAFE_URL.test(href)) return false;
  if (!/^https?:/i.test(href)) return true;
  let host;
  try {
    host = new URL(href).hostname.toLowerCase();
  } catch (e) {
    return false;
  }
  return (hosts || []).some((h) => host === h || host.endsWith(`.${h}`));
}

function inline(text, keyBase, hosts) {
  const out = [];
  const re =
    /(\[([^\]]+)\]\(([^)\s]+)\))|(\*\*([^*]+)\*\*)|(\*([^*]+)\*)|(https?:\/\/[^\s)]+)/g;
  let last = 0;
  let m;
  let i = 0;
  while ((m = re.exec(text))) {
    if (m.index > last) out.push(text.slice(last, m.index));
    const key = `${keyBase}-${i++}`;
    if (m[1]) {
      const href = m[3];
      out.push(
        trusted(href, hosts) ? (
          <a key={key} href={href} target="_blank" rel="noopener noreferrer">
            {m[2]}
          </a>
        ) : (
          m[2]
        ),
      );
    } else if (m[4]) {
      out.push(<strong key={key}>{m[5]}</strong>);
    } else if (m[6]) {
      out.push(<em key={key}>{m[7]}</em>);
    } else if (m[8]) {
      out.push(
        trusted(m[8], hosts) ? (
          <a key={key} href={m[8]} target="_blank" rel="noopener noreferrer">
            {m[8]}
          </a>
        ) : (
          m[8]
        ),
      );
    }
    last = re.lastIndex;
  }
  if (last < text.length) out.push(text.slice(last));
  return out;
}

/**
 * Splits the text into paragraphs and lists. A "loose" list (blank line between the items)
 * arrives as several one-item blocks; consecutive blocks of the same list type are merged,
 * otherwise every item would restart the numbering at 1.
 */
function parse(text) {
  const groups = [];
  for (const block of String(text || "")
    .trim()
    .split(/\n{2,}/)) {
    const lines = block.split("\n");
    const isUl = lines.every((l) => /^\s*[-*•]\s+/.test(l));
    const isOl = lines.every((l) => /^\s*\d+[.)]\s+/.test(l));
    const type = isOl ? "ol" : isUl ? "ul" : "p";
    const prev = groups[groups.length - 1];
    if (type !== "p" && prev && prev.type === type) prev.lines.push(...lines);
    else
      groups.push({
        type,
        lines,
        start: isOl ? parseInt(lines[0], 10) : undefined,
      });
  }
  return groups;
}

export function Markdown({ text, hosts }) {
  return (
    <>
      {parse(text).map(({ type, lines, start }, bi) => {
        if (type !== "p") {
          const Tag = type;
          return (
            <Tag key={bi} start={start}>
              {lines.map((l, li) => (
                <li key={li}>
                  {inline(
                    l.replace(/^\s*([-*•]|\d+[.)])\s+/, ""),
                    `${bi}-${li}`,
                    hosts,
                  )}
                </li>
              ))}
            </Tag>
          );
        }
        return (
          <p key={bi}>
            {lines.map((l, li) => (
              <Fragment key={li}>
                {li > 0 && <br />}
                {inline(l.replace(/^#{1,6}\s+/, ""), `${bi}-${li}`, hosts)}
              </Fragment>
            ))}
          </p>
        );
      })}
    </>
  );
}
