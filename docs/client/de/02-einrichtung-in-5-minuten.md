# Einrichtung in 5 Minuten

Nach der [Installation](01-installation.md) richten Sie den Assistenten in fünf Schritten ein. Auf der Startseite **AI Assistant → Dashboard** zeigt die **Checkliste zur Einrichtung**, welche Schritte schon erledigt sind. Ein Klick auf einen Eintrag führt direkt zur passenden Einstellung.

Die ersten vier Schritte entsprechen den Punkten der Checkliste:

1. **Assistent eingeschaltet**
2. **KI-Anbieter verbunden**
3. **Wissen indexiert**
4. **Buchungslink hinterlegt**

## Schritt 1: Assistent einschalten und benennen

Öffnen Sie **AI Assistant → Einstellungen → Assistent**.

- **Chat auf der Website anzeigen**: Schalter einschalten. Dann erscheint der Chat-Knopf unten auf jeder Seite. Möchten Sie den Chat stattdessen mitten in einer Seite zeigen, schreiben Sie `[terax_ai_chat]` in einen Absatz.
- **Name des Assistenten**: Name im Chat, z. B. „Praxis-Assistent“.
- **Art des Betriebs**: kurze Beschreibung Ihres Betriebs, z. B. „Zahnarztpraxis“. Das hilft der KI, den Zusammenhang zu verstehen.
- **Telefon (wird bei Notfällen genannt)** und **E-Mail**: Kontaktdaten, auf die der Assistent bei dringenden Fällen verweist. Ohne Angabe verweist er auf die Kontaktdaten Ihrer Website.
- **Antwortsprache**: **Automatisch** antwortet in der Sprache des Besuchers. **Immer Deutsch** antwortet immer auf Deutsch. Die Beschriftungen im Chat-Fenster (z. B. „Chat starten“, „Ihre Frage…“) richten sich dagegen nach der Sprache Ihrer Website. Stellen Sie unter **Einstellungen → Allgemein → Website-Sprache** „Deutsch“ ein, damit sie auf Deutsch erscheinen.
- **Begrüßung**: Begrüßungstext, auf Wunsch mit Markdown.
- **Vorgeschlagene Fragen**: Eine Frage pro Zeile. Sie erscheinen als Knöpfe vor der ersten Nachricht, z. B. „Wann haben Sie geöffnet?“.
- **Markenfarbe**: Farbe des Chat-Fensters. Die Schriftfarbe passt sich automatisch an, damit sie gut lesbar bleibt.
- **Position**: unten rechts oder unten links.

Klicken Sie oben rechts auf **Änderungen speichern**.

## Schritt 2: KI-Anbieter verbinden

Öffnen Sie **Einstellungen → KI-Modell** und wählen Sie zwischen einem eigenen API-Schlüssel und „Managed AI“. Die Schritte stehen in [KI-Anbieter und API-Schlüssel](03-ki-anbieter-api-schluessel.md).

Klicken Sie nach der Eingabe auf **Verbindung testen** und danach auf **Änderungen speichern**. Der Test funktioniert auch mit einem noch nicht gespeicherten Schlüssel. Der Chat läuft aber erst, nachdem Sie gespeichert haben.

## Schritt 3: Wissen aufbauen

Öffnen Sie **AI Assistant → Wissensdatenbank** und klicken Sie auf **Index neu erstellen**. Der Assistent liest danach Ihre Seiten und Beiträge. Details stehen in [Wissen pflegen](04-wissen-pflegen.md).

## Schritt 4: Terminbuchung hinterlegen

Öffnen Sie **Einstellungen → Terminbuchung**, wählen Sie Ihr Buchungssystem und tragen Sie den Link ein. Details stehen in [Terminbuchung](05-terminbuchung.md).

## Schritt 5: Datenschutz prüfen

Öffnen Sie **Einstellungen → Datenschutz** und prüfen Sie den Hinweistext und den Link zur Datenschutzerklärung. Details stehen in [Datenschutz](06-datenschutz.md). Tun Sie das, bevor der Chat für Besucher sichtbar ist.

## Testen

Öffnen Sie Ihre Website in einem neuen Fenster (am besten ohne angemeldet zu sein). Klicken Sie auf den Chat-Knopf, bestätigen Sie den Hinweis mit **Chat starten** und stellen Sie eine Frage, deren Antwort auf Ihrer Website steht, zum Beispiel: „Was kostet eine professionelle Zahnreinigung?“. Im Dashboard sehen Sie danach die Zahl der Gespräche.

Funktioniert etwas nicht, hilft die [Fehlerbehebung](07-fehlerbehebung.md).
