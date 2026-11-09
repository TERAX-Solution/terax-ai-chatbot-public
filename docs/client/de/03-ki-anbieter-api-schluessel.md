# KI-Anbieter und API-Schlüssel

Der Assistent braucht ein KI-Modell, das die Antworten formuliert. Dafür gibt es zwei Wege.

## Zwei Wege

| | Eigener API-Schlüssel (**Eigener API-Schlüssel**) | Managed AI by TERAX |
|---|---|---|
| Wer rechnet ab? | Sie direkt mit dem KI-Anbieter | TERAX Solution (im Tarif enthalten) |
| Was brauchen Sie? | Konto und Schlüssel beim Anbieter | Tarif **Pro** oder **Agency** |
| Vertrag zur Datenverarbeitung (AVV) | mit dem KI-Anbieter | mit TERAX Solution |
| Geeignet für | volle Kontrolle über Anbieter und Modell | wenig Aufwand, alles aus einer Hand |

Die Auswahl treffen Sie unter **AI Assistant → Einstellungen → KI-Modell** im Abschnitt **Verbindung**. „Managed AI“ ist in den Tarifen Free und Starter gesperrt (Hinweis „upgrade“).

## Welcher Anbieter?

Zur Auswahl stehen **Anthropic Claude**, **OpenAI GPT**, **Google Gemini** und **Mistral AI (in der EU gehostet)**. Fortgeschrittene können außerdem einen OpenAI-kompatiblen Dienst mit eigener Adresse (**Basis-URL**) eintragen.

Kurz zur Orientierung:

- **Mistral AI** ist ein Unternehmen mit Sitz in der EU und im Plugin als „EU-hosted“ gekennzeichnet. Es ist deshalb die naheliegende Wahl, wenn Ihnen ein europäischer Anbieter wichtig ist. Lassen Sie sich Serverstandort und Datenverarbeitung trotzdem im AVV bestätigen.
- **Anthropic, OpenAI und Google** verarbeiten Daten standardmäßig auch in den USA. Ob und wie eine Verarbeitung nur in der EU möglich ist, hängt vom Angebot und Vertrag ab. Das sollten Sie vor dem Einsatz beim Anbieter klären.
- Alle Anbieter funktionieren im Plugin gleich. Sie können später wechseln.

## API-Schlüssel erstellen

Die Oberfläche der Anbieter ändert sich gelegentlich. Die Schritte sind überall ähnlich:

1. Konto beim Anbieter anlegen: Anthropic (`console.anthropic.com`), OpenAI (`platform.openai.com`), Mistral (`console.mistral.ai`) oder Google (`aistudio.google.com`).
2. Eine Zahlungsmethode oder ein Guthaben hinterlegen. Ohne Guthaben antwortet der Anbieter meist mit einer Fehlermeldung.
3. Im Bereich „API keys“ einen neuen Schlüssel erzeugen und kopieren. Der Schlüssel wird meist nur einmal angezeigt.
4. Optional, aber empfohlen: beim Anbieter ein monatliches Ausgabenlimit setzen.

Schlüssel sind wie Passwörter. Schicken Sie sie nicht per E-Mail oder Chat und geben Sie sie nicht auf der Website weiter.

## Schlüssel im Plugin eintragen

1. **AI Assistant → Einstellungen → KI-Modell**
2. **Eigener API-Schlüssel** wählen.
3. Bei **Anbieter** den Anbieter wählen.
4. Den Schlüssel bei **API-Schlüssel** einfügen.
5. Bei **Modell** ein Modell wählen. Voreingestellte Beispiele: `claude-haiku-4-5`, `claude-sonnet-4-5`, `gpt-4.1-mini`, `gemini-2.5-flash`, `mistral-small-latest`. Die Liste kann veraltet sein. Mit dem Aktualisieren-Knopf neben dem Feld lädt das Plugin die aktuellen Modelle vom Anbieter. Sie können auch jede Modell-Kennung von Hand eintragen.
6. **Verbindung testen** klicken. Erscheint eine Erfolgsmeldung, ist der Schlüssel in Ordnung.
7. **Änderungen speichern** klicken. Erst danach nutzt der Chat den Schlüssel.

Der Schlüssel wird verschlüsselt gespeichert und nie an Besucher ausgeliefert. Ein gespeicherter Schlüssel erscheint nur mit den letzten Zeichen. Lassen Sie das Feld leer, um ihn zu behalten.

Bei **Kreativität (Temperatur)** empfehlen wir einen niedrigen Wert (0,2 bis 0,4): Der Assistent bleibt dann sachlich und konsistent.

## Kosten

Bei eigenem Schlüssel zahlen Sie nach Verbrauch an den Anbieter. Die Kosten hängen vom gewählten Modell, von der Länge der Antworten und von der Zahl der Gespräche ab. Kleine, schnelle Modelle sind für Praxis-Websites meist ausreichend und deutlich günstiger als große.

<!-- TODO (Review): grobe Monatskosten nennen, sobald Tuan die Zahlen bestätigt hat. Preise der Anbieter ändern sich; keine Zahlen ohne aktuelle Quelle veröffentlichen. -->

Aktuelle Preise stehen auf der Preisseite des Anbieters. Prüfen Sie zu Beginn die Nutzung: Im Dashboard zeigt **Nutzung in diesem Monat** die Zahl der Nachrichten und der verbrauchten Tokens. Ein Ausgabenlimit beim Anbieter schützt vor Überraschungen.

## Managed AI

Mit **Managed AI von TERAX** brauchen Sie kein eigenes Konto und keinen Schlüssel. Wählen Sie die Kachel aus und speichern Sie. Voraussetzung ist ein Tarif **Pro** oder **Agency** (siehe [Installation](01-installation.md)). Den Vertrag zur Datenverarbeitung schließen Sie dann mit TERAX Solution.
