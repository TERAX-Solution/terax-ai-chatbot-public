# Datenschutz

> Diese Seite erklärt, was die Einstellungen bewirken. Sie ist keine Rechtsberatung. Lassen Sie Hinweistext, Datenschutzerklärung und Verträge von einer fachkundigen Person prüfen.

Die Einstellungen finden Sie unter **AI Assistant → Einstellungen → Datenschutz** (Abschnitt **Datenschutz & DSGVO**).

## Was der Assistent verarbeitet

- **Chat-Nachrichten** der Besucher und die Antworten. Sie werden in der Datenbank Ihrer Website gespeichert, solange **Unterhaltungen speichern** eingeschaltet ist.
- **IP-Adresse:** nie im Klartext, sondern nur als gesalzener Hash. Er dient dem Missbrauchsschutz (Begrenzung der Nachrichten pro Stunde).
- **Weitergabe an den KI-Anbieter:** Für jede Antwort gehen die Nachricht, die letzten Gesprächsteile und passende Auszüge Ihrer Website-Texte an den KI-Anbieter.
- Es gibt keine Tracking-Cookies, keine Namen und keine E-Mail-Adressen im Plugin. Gespräche sind keiner Person zugeordnet.

## Einwilligungshinweis

Bevor ein Besucher schreiben kann, sieht er einen Hinweis und muss **Chat starten** klicken. Erst danach sendet der Chat Anfragen an den Server.

- **Einwilligungshinweis:** der Text des Hinweises. Die Voreinstellung lautet: „Mit der Nutzung des Chats stimmen Sie der Verarbeitung Ihrer Eingaben gemäß unserer Datenschutzerklärung zu. Bitte geben Sie keine sensiblen Gesundheitsdaten ein.“ Passen Sie ihn an Ihre Praxis an. Lassen Sie das Feld nur leer, wenn Sie bewusst auf den Hinweis verzichten; in der EU wird davon abgeraten.
- **URL der Datenschutzerklärung:** der Link zu Ihrer Datenschutzerklärung. Er erscheint im Hinweis als „Datenschutz“.

## Speicherdauer und Gespräche

- **Unterhaltungen löschen nach (Tagen):** Gespräche werden nach dieser Zahl von Tagen automatisch gelöscht, täglich. Voreinstellung: 30 Tage.
- **Unterhaltungen speichern:** ausgeschaltet, speichert das Plugin keine Gespräche, sondern nur anonyme Zähler. Dann sehen Sie unter **Unterhaltungen** nichts mehr. Das ist die datensparsamste Einstellung.
- **Telefonnummern und E-Mail-Adressen in gespeicherten Nachrichten unkenntlich machen:** Telefonnummern und E-Mail-Adressen werden in der gespeicherten Kopie durch `[Telefon]` und `[E-Mail]` ersetzt (Voreinstellung: an). Die KI bekommt weiterhin die Originalnachricht. Die Erkennung ist eine Näherung: Namen und Gesundheitsangaben werden nicht erkannt.

## Löschen auf Anfrage

Die WordPress-Werkzeuge „Personenbezogene Daten exportieren / löschen“ arbeiten mit E-Mail-Adressen. Weil Gespräche keine E-Mail-Adresse enthalten, werden sie dort nicht gefunden. Stattdessen:

1. **AI Assistant → Unterhaltungen** öffnen.
2. Ein einzelnes Gespräch öffnen und **Löschen** klicken, oder alle löschen mit **Alle Unterhaltungen löschen**.

Ein Besucher kann sein Gespräch nur identifizieren, wenn er Datum und Inhalt nennt.

## Datenschutzerklärung

WordPress erzeugt einen Textvorschlag für den Abschnitt „AI Assistant“, mit Speicherdauer und KI-Anbieter aus Ihren Einstellungen:

1. **Einstellungen → Datenschutz** im WordPress-Menü öffnen.
2. Auf **Leitfaden zur Datenschutzerklärung** klicken (je nach Sprache auch „Policy Guide“).
3. Den Abschnitt „AI Assistant“ in Ihre Datenschutzerklärung übernehmen.
4. Platzhalter in eckigen Klammern ausfüllen, z. B. Rechtsgrundlage, Auftragsverarbeitungsvertrag (AVV) und Drittlandübermittlung.

## Verträge

- Mit dem **KI-Anbieter** (bei eigenem Schlüssel) oder mit **TERAX Solution** (bei Managed AI) einen Auftragsverarbeitungsvertrag (AVV) abschließen.
- Beim Anbieter klären: Serverstandort, Speicherdauer, kein Training mit Ihren Daten.
- Bei einer Übermittlung in Länder außerhalb der EU (z. B. USA) die Rechtsgrundlage dokumentieren.
- Den Hosting-Anbieter Ihrer Website haben Sie meist schon unter Vertrag. Dort liegen die gespeicherten Gespräche.

## Missbrauchsschutz (Rate Limit)

- **Maximale Nachrichten pro Besucher und Stunde:** Obergrenze pro Besucher und Stunde. Voreinstellung: 30.
- **Maximale Nachrichtenlänge:** maximale Länge einer Nachricht in Zeichen. Voreinstellung: 1000.
- **Header für die Besucher-IP:** Nur wichtig, wenn Ihre Website hinter einem Proxy oder Cloudflare läuft (siehe [Fehlerbehebung](07-fehlerbehebung.md)).
