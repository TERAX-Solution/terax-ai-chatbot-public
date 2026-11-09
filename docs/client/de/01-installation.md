# Installation

Diese Anleitung zeigt, wie Sie den TERAX AI Assistant auf Ihrer WordPress-Website installieren. Sie brauchen dafür einen Administrator-Zugang und die Plugin-Datei (eine ZIP-Datei, z. B. `terax-ai-assistant-1.0.0.zip`).

## Voraussetzungen

- WordPress 6.2 oder neuer mit PHP 7.4 oder neuer (empfohlen: eine aktuelle PHP-Version). Details: [Systemvoraussetzungen und Kompatibilität](#systemvoraussetzungen-und-kompatibilität).
- Ein Administrator-Konto.
- Die ZIP-Datei des Plugins von TERAX Solution.
- Ein KI-Zugang: entweder ein eigener API-Schlüssel (siehe [KI-Anbieter und API-Schlüssel](03-ki-anbieter-api-schluessel.md)) oder „Managed AI“ in den Tarifen Pro und Agency.

## Plugin hochladen und aktivieren

1. Melden Sie sich im WordPress-Dashboard an.
2. Öffnen Sie **Plugins → Installieren**.
3. Klicken Sie oben auf **Plugin hochladen**.
4. Wählen Sie die ZIP-Datei aus und klicken Sie auf **Jetzt installieren**.
5. Klicken Sie nach der Installation auf **Plugin aktivieren**.

Im linken Menü erscheint jetzt der Eintrag **AI Assistant**.

> Hinweis: Bezeichnungen aus der Oberfläche des Assistenten stehen in dieser Anleitung **fett**, damit Sie sie auf dem Bildschirm wiederfinden. Die Oberfläche erscheint auf Deutsch, wenn Ihr WordPress-Benutzerkonto (oder die Website) auf Deutsch eingestellt ist. Texte, die Ihre Besucher sehen (Begrüßung, Hinweis zum Datenschutz), können Sie auf Deutsch schreiben.

## Lizenzschlüssel eintragen

Ohne Lizenzschlüssel läuft das Plugin im Tarif **Free** (Testbetrieb mit niedrigen Grenzen). Mit einem Schlüssel schalten Sie einen größeren Tarif frei.

1. Öffnen Sie **AI Assistant → Lizenz**.
2. Tragen Sie Ihren Schlüssel im Format `XXXX-XXXX-XXXX-XXXX` in das Feld ein.
3. Bestätigen Sie. Oben auf der Seite steht danach **Aktueller Tarif** mit Ihrem Tarif.

Was die Tarife unterscheidet:

| | Free | Starter | Pro | Agency |
|---|---|---|---|---|
| Nachrichten pro Monat | 100 | 2.000 | 10.000 | unbegrenzt |
| Indexierte Seiten | 30 | 300 | unbegrenzt | unbegrenzt |
| Eigener API-Schlüssel | ja | ja | ja | ja |
| Managed AI (ohne eigenen Schlüssel) | nein | nein | ja | ja |
| Eigene Zusatzanweisungen für die KI | nein | ja | ja | ja |
| TERAX-Hinweis im Chat entfernen | nein | nein | ja | ja |
| Updates | nein | ja | ja | ja |

Die Zahlen entsprechen den Voreinstellungen des Plugins. Verbindlich ist Ihr Vertrag mit TERAX Solution.

## Nach der Installation

Weiter mit [Einrichtung in 5 Minuten](02-einrichtung-in-5-minuten.md).

## Aktualisieren

Mit einem Tarif ab **Starter** werden Updates über WordPress angeboten (**Dashboard → Aktualisierungen**). Im Tarif **Free** laden Sie neue ZIP-Dateien selbst hoch. Ihre Einstellungen bleiben dabei erhalten.

## Systemvoraussetzungen und Kompatibilität

| Bereich | Anforderung |
| -- | -- |
| WordPress | 6.2 oder neuer |
| PHP | 7.4 oder neuer |
| Datenbank | MySQL oder MariaDB. Die Volltextsuche wird genutzt, wenn die Datenbank sie unterstützt. Sonst arbeitet das Plugin mit einer einfacheren Suche, die Treffer sind dann etwas weniger genau. |

**Automatisch getestet:** Bei jeder Änderung am Plugin läuft eine Testreihe (Besucher-Chat, Verwaltungsbereich und die Programmlogik des Plugins) mit diesen Kombinationen:

| PHP | WordPress |
| -- | -- |
| 8.1 | 6.2 (Mindestversion) |
| 8.1 | aktuelle Version |
| 8.3 | aktuelle Version |

Für PHP 7.4 (Mindestversion) läuft die Programmlogik des Plugins in eigenen Tests, und eine automatische Prüfung stellt sicher, dass kein Code verwendet wird, der erst ab PHP 8 verfügbar ist. Eine vollständige Testreihe mit WordPress auf PHP 7.4 gibt es noch nicht.

**Gut zu wissen**

- **Sicherheits-Plugins:** Der Chat ruft die Adresse `/wp-json/terax-ai/v1/chat` auf. Bei der Permalink-Einstellung „Einfach“ lautet sie `/?rest_route=/terax-ai/v1/chat`. Sperrt ein Sicherheits-Plugin („REST-API deaktivieren“, Firewall-Regeln) die REST-API für nicht angemeldete Besucher, kann der Chat keine Antworten laden. Erlauben Sie in diesem Fall die Adressen unter `/wp-json/terax-ai/v1/` für alle Besucher.
- **Cache-Plugins:** Der Chat braucht keinen Sicherheitsschlüssel (Nonce) und funktioniert deshalb auch auf zwischengespeicherten Seiten. Wenn Ihr Cache-Plugin JavaScript zusammenfasst oder verkleinert und der Chat nicht erscheint, nehmen Sie die Dateien des Plugins von dieser Optimierung aus.
- **WP-Cron:** Ist WP-Cron abgeschaltet (`DISABLE_WP_CRON`), wird der Wissensindex trotzdem fertig, solange die Seite **AI Assistant → Wissensdatenbank** geöffnet bleibt.
- **Cookie-Banner:** Das Plugin zeigt vor dem ersten Chat einen eigenen Datenschutzhinweis. Eine Kopplung an ein Cookie-Banner (zum Beispiel Borlabs Cookie) haben wir noch nicht getestet.
- **Noch nicht getestet:** einzelne Cache-, Sicherheits- und Cookie-Plugins sowie Themes wie Divi oder Avada. Wenn Sie ein Problem mit einer bestimmten Kombination finden, melden Sie es uns bitte.
