# Datenschutz-Info für Praxen (TERAX AI Assistant)

> Entwurf, keine Rechtsberatung. Vor der Weitergabe an Kunden von einer fachkundigen Person prüfen lassen.

## Was der Assistent verarbeitet

| Daten | Wo | Wie lange |
|---|---|---|
| Nachrichten der Besucher und Antworten | Datenbank der Praxis-Website (nur wenn „Gespräche speichern“ aktiv ist) | Einstellung „Gespräche löschen nach (Tagen)“, Standard 30, tägliche automatische Löschung |
| Seite, auf der das Gespräch begann | Datenbank der Website | wie oben |
| IP-Adresse | nur als gesalzener Hash (für Missbrauchsschutz), nie im Klartext | wie oben |
| Nachricht + letzte Gesprächsteile + passende Auszüge der Website-Texte | werden an den KI-Anbieter gesendet, um die Antwort zu erzeugen | gemäß Vertrag mit dem Anbieter |

Es gibt keine Namen, E-Mail-Adressen oder Cookies für das Tracking. Gespräche sind nicht einer Person zugeordnet.

## Einwilligung

Der Chat sendet erst eine Anfrage an den Server, nachdem der Besucher den Hinweis bestätigt und „Chat starten“ geklickt hat. Der Text des Hinweises und der Link zur Datenschutzerklärung stehen unter **Einstellungen → Datenschutz**. Rechtsgrundlage ist im Textvorschlag als Platzhalter (Einwilligung, Art. 6 Abs. 1 lit. a DSGVO) angegeben und muss von der Praxis bestätigt werden.

## Was in die Datenschutzerklärung gehört

Im WordPress-Menü **Einstellungen → Datenschutz → Leitfaden zur Datenschutzerklärung** erscheint der Abschnitt „AI Assistant“ (Deutsch und Englisch) mit Speicherdauer und KI-Anbieter aus den aktuellen Einstellungen. Platzhalter in eckigen Klammern (Rechtsgrundlage, AVV, Drittlandübermittlung) müssen ausgefüllt werden.

## Was in den AVV (Auftragsverarbeitungsvertrag) gehört

- Mit dem **KI-Anbieter** (bei eigenem API-Schlüssel) bzw. mit **TERAX Solution** (bei „Managed AI“) einen AVV abschließen.
- Gegenstand: Verarbeitung von Chat-Nachrichten zur Beantwortung von Besucherfragen.
- Datenkategorien: freie Texteingaben der Besucher (können unbeabsichtigt Kontakt- oder Gesundheitsdaten enthalten).
- Unterauftragnehmer und Serverstandorte, Speicherdauer beim Anbieter, **kein Training mit Kundendaten**, Löschpflichten.
- Bei Übermittlung in Drittländer (z. B. USA): Rechtsgrundlage dokumentieren (Angemessenheitsbeschluss / Standardvertragsklauseln).
- Hosting-Anbieter der Website (Speicherung der Gespräche) hat in der Regel bereits einen AVV.

## KI-Anbieter und Serverstandort

Angaben ändern sich; **vor Vertragsabschluss beim Anbieter prüfen**.

| Anbieter | Hinweis |
|---|---|
| Mistral AI | Unternehmen mit Sitz in der EU, in der Plugin-Auswahl als „EU-hosted“ gekennzeichnet. Serverstandort im AVV bestätigen lassen. |
| OpenAI, Anthropic, Google Gemini | Standard-Verarbeitung kann in Drittländern (USA) erfolgen. EU-Datenresidenz bzw. Region-Auswahl nur nach aktueller Anbieterdokumentation und Vertrag (teils nur über Enterprise- oder Cloud-Angebote). |
| OpenAI-kompatibel (eigene Base-URL) | Abhängig vom gewählten Dienst bzw. Eigenbetrieb. |

## Datenminimierung und Betroffenenrechte

- **Telefonnummern und E-Mail-Adressen maskieren** (Einstellungen → Datenschutz, standardmäßig an): In der Datenbank steht `[Telefon]` / `[E-Mail]`. Die KI erhält weiterhin die Originalnachricht. Die Erkennung ist eine Näherung; Namen oder Gesundheitsangaben werden nicht erkannt.
- Der Hinweis im Chat bittet Besucher, keine sensiblen Gesundheitsdaten einzugeben.
- **Auskunft / Löschung:** Die Werkzeuge „Personenbezogene Daten exportieren / löschen“ von WordPress arbeiten mit E-Mail-Adressen. Gespräche enthalten keine E-Mail-Adresse oder keinen Namen und sind daher nicht auffindbar. Stattdessen: **AI Assistant → Gespräche** → einzelne Gespräche löschen oder **„Alle Gespräche löschen“** (englische Oberfläche: „Delete all conversations“). Auf Anfrage kann ein Besucher ein Gespräch nur dann identifizieren, wenn er Datum und Inhalt nennt (Suche in „Gespräche“).
- Gespräche nicht speichern: **Gespräche speichern** deaktivieren, dann werden nur anonyme Zähler geführt.
