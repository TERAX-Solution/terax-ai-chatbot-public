# Wissen pflegen

Der Assistent antwortet **nur** mit Informationen von Ihrer Website und aus dem Feld „Extra information“. Er erfindet keine Preise, Öffnungszeiten oder Leistungen. Steht eine Antwort nirgends, sagt er das ehrlich und verweist auf Ihre Kontaktdaten.

Alles Wissen verwalten Sie unter **AI Assistant → Wissensdatenbank**.

## Wie das Indexieren funktioniert

- Das Plugin liest Ihre Seiten und Beiträge und zerlegt sie in kleine Textabschnitte. Diese Sammlung heißt **Index**.
- Bei jeder Frage sucht es die passenden Abschnitte heraus und gibt sie der KI als Grundlage.
- **Änderungen erkennt das Plugin automatisch:** Speichern Sie eine Seite, wird sie neu indexiert. Ein manueller Neuaufbau ist nur nötig, wenn Sie das Plugin neu installiert haben, die Einstellungen unter **Quellen** geändert haben oder der Index leer wirkt.
- Mit **Index neu erstellen** lesen Sie alle Seiten neu ein. Bei wenigen Seiten ist das fast sofort fertig. Bei vielen Seiten läuft es in Schritten, solange Sie die Seite offen lassen.
- Es werden Texte aus dem Block-Editor (Gutenberg), dem klassischen Editor und Elementor verstanden.

Die Zahl der indexierten Seiten hängt vom Tarif ab (siehe [Installation](01-installation.md)). Oben auf der Seite sehen Sie **Indexierte Seiten** und **Textabschnitte**. Unten listet **Indexierte Inhalte**, welche Seiten im Index sind.

## Zusätzliche Informationen eintragen

Manches steht nicht auf der Website, z. B. Parkplätze, akzeptierte Versicherungen oder häufige Fragen. Dafür gibt es das Feld **Zusätzliche Informationen (nicht auf der Website)** im Abschnitt **Quellen**. Schreiben Sie dort Klartext oder Markdown, zum Beispiel:

```
Wir behandeln gesetzlich und privat Versicherte.
Parkplätze: Drei Plätze direkt vor der Praxis, weitere im Parkhaus Mitte (2 Gehminuten).
Öffnungszeiten: Montag bis Donnerstag 8–18 Uhr, Freitag 8–14 Uhr.
```

Klicken Sie danach auf **Speichern**. Das Feld zählt wie eine eigene Seite im Index.

Tipp: Schreiben Sie wichtige Fakten (Öffnungszeiten, Preise, Adresse) in ganzen, klaren Sätzen. Der Assistent gibt wieder, was dort steht.

## Prüfen, was der Assistent findet

Im Abschnitt **Testen, was der Assistent findet** geben Sie eine typische Besucherfrage ein, zum Beispiel „Wann haben Sie geöffnet?“, und bestätigen mit der Eingabetaste oder der Lupe. Das Plugin zeigt die Textabschnitte, die an die KI gehen würden.

- **Es werden passende Abschnitte angezeigt:** Die Antwort steht im Index.
- **„Nichts gefunden“:** Der Assistent würde sagen, dass er es nicht weiß. Ergänzen Sie die Antwort auf einer Seite oder unter **Zusätzliche Informationen**.

## Seiten ausschließen

Nicht alles sollte im Chat landen, z. B. Impressum oder Stellenanzeigen.

1. **Wissensdatenbank → Quellen**
2. Bei **Inhaltstypen, die indexiert werden** wählen, ob **Beiträge** (Beiträge) und/oder **Seiten** (Seiten) indexiert werden.
3. Bei **Seiten-IDs ausschließen** die Nummern der auszuschließenden Seiten eintragen, getrennt durch Kommas. Die Nummer einer Seite steht im Adressfeld, wenn Sie sie im Editor öffnen (`post=123`).
4. **Speichern** klicken und danach **Index neu erstellen**.

## Wie viele Textabschnitte pro Antwort?

**Textabschnitte pro Antwort** legt fest, wie viele Abschnitte die KI pro Frage bekommt. Mehr Abschnitte decken mehr ab, kosten aber mehr. Empfohlen sind 4 bis 6.

## Gute Praxis

- Pflegen Sie die Antworten dort, wo Besucher sie auch auf der Website finden.
- Testen Sie nach größeren Änderungen drei bis fünf typische Fragen.
- Lesen Sie ab und zu die Gespräche unter **AI Assistant → Unterhaltungen** (wenn gespeichert wird). Fragen ohne gute Antwort zeigen, welche Informationen fehlen.
