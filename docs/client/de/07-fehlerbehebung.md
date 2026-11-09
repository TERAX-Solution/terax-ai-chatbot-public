# Fehlerbehebung (FAQ)

## Der Chat erscheint nicht auf meiner Website

1. **Ist der Chat eingeschaltet?** Unter **AI Assistant → Einstellungen → Assistent** muss **Chat auf der Website anzeigen** an sein. Auf dem Dashboard zeigt die **Checkliste zur Einrichtung** „Assistant enabled“.
2. **Cache leeren.** Caching-Plugins, Server-Cache oder ein CDN können eine alte Seite ausliefern. Leeren Sie alle Caches und laden Sie die Seite neu (Strg + F5). Der Chat funktioniert mit Seiten-Cache, braucht aber nach einer Änderung eine frische Seite.
3. **Cookie-Banner.** Einige Cookie-Banner blockieren Skripte, bis der Besucher zustimmt. Prüfen Sie die Einstellungen Ihres Banners.
4. **Sicherheits-Plugins und Firewalls.** Der Chat spricht über die WordPress-Schnittstelle (REST-API) unter `/wp-json/terax-ai/v1/`. Blockiert ein Sicherheits-Plugin oder eine Firewall (WAF) diese Adresse, öffnet sich der Chat, antwortet aber nicht. Erlauben Sie diese Adresse für Besucher.
5. **Anderer Browser oder privates Fenster.** So erkennen Sie, ob eine Erweiterung (Werbeblocker) stört.

## „Leider kann ich gerade nicht antworten“

Der Chat konnte den KI-Anbieter nicht erreichen. (Erscheint stattdessen „Entschuldigung, gerade ist ein Fehler aufgetreten“, lag das Problem zwischen Browser und Website, z. B. bei einer Firewall, siehe oben.) Auf dem **Dashboard** steht unter **Letzter KI-Fehler** die letzte Fehlermeldung. Häufige Ursachen:

- **Kein API-Schlüssel gespeichert**, oder der Schlüssel gehört zu einem anderen Anbieter als dem ausgewählten. Prüfen Sie **Einstellungen → KI-Modell**, klicken Sie **Verbindung testen** und danach **Änderungen speichern**.
- **Guthaben aufgebraucht** oder keine Zahlungsmethode beim Anbieter. Das zeigt die Fehlermeldung meist an. Prüfen Sie das Konto beim Anbieter.
- **Ausgabenlimit des Anbieters erreicht.**
- **Falscher Modellname.** Wählen Sie ein Modell aus der Liste oder aktualisieren Sie die Liste mit dem Aktualisieren-Knopf.
- **Störung beim Anbieter.** Versuchen Sie es später noch einmal.

## Der Assistent antwortet falsch oder weiß etwas nicht

Der Assistent kennt nur Ihre Website und das Feld **Zusätzliche Informationen**. Er rät nicht.

1. Öffnen Sie **AI Assistant → Wissensdatenbank** und stellen Sie die Frage unter **Testen, was der Assistent findet**.
2. Zeigt der Test **„Nichts gefunden“**, steht die Antwort nicht im Index. Ergänzen Sie sie auf einer Seite oder unter **Zusätzliche Informationen** (siehe [Wissen pflegen](04-wissen-pflegen.md)).
3. Zeigt der Test eine falsche Seite, prüfen Sie, ob die richtige Seite im Index steht (**Indexierte Inhalte**) und ob sie ausgeschlossen ist (**Seiten-IDs ausschließen**).
4. Klicken Sie nach größeren Änderungen **Index neu erstellen**.
5. Schreiben Sie wichtige Fakten als ganze Sätze. Zahlen und Zeiten in Tabellen oder Bildern liest der Assistent schlechter.

## Der Assistent verweigert eine Frage

Das ist gewollt. Der Assistent beantwortet nur Fragen zu Ihrer Praxis. Er stellt **keine Diagnosen**, empfiehlt **keine Medikamente** und bewertet keine Symptome. Bei Schmerzen oder Notfällen verweist er auf Ihre Telefonnummer und auf den Notruf 112 bzw. den zahnärztlichen Notdienst. Fragen, die nichts mit der Praxis zu tun haben, lehnt er höflich ab.

## Alle Besucher teilen sich ein Limit / Besucher werden zu früh gesperrt

Läuft Ihre Website hinter **Cloudflare** oder einem anderen Proxy, sieht das Plugin sonst nur die Adresse des Proxys. Dann teilen sich alle Besucher ein gemeinsames Limit.

1. **AI Assistant → Einstellungen → Datenschutz**
2. Bei **Header für die Besucher-IP** **CF-Connecting-IP (Cloudflare)** wählen. Bei anderen Proxys: **X-Forwarded-For** oder **X-Real-IP**, je nachdem, was Ihr Hoster verwendet.
3. **Änderungen speichern** klicken.

Steht die Website nicht hinter einem Proxy, lassen Sie **Keiner (Standard)** stehen. Eine falsche Wahl kann das Limit umgehbar machen. Fragen Sie im Zweifel Ihren Hoster.

Reicht das Limit nicht, erhöhen Sie **Maximale Nachrichten pro Besucher und Stunde** unter derselben Einstellung.

## Das Monatslimit ist erreicht

Jeder Tarif enthält eine Obergrenze für Nachrichten pro Monat und indexierte Seiten (siehe [Installation](01-installation.md)). Auf dem Dashboard zeigt **Nutzung in diesem Monat**, wie viel verbraucht ist. Ein größerer Tarif hebt die Grenze an.

## Das Buchungs-Popup bleibt leer

Das Buchungssystem erlaubt keine Einbettung. Stellen Sie unter **Einstellungen → Terminbuchung** bei **Öffnen in** auf **Neuer Tab** um (siehe [Terminbuchung](05-terminbuchung.md)).

## Die Beschriftungen im Chat sind auf Englisch

Die Beschriftungen („Start chat“, „Type your question…“) richten sich nach der Sprache Ihrer Website. Stellen Sie unter **Einstellungen → Allgemein → Website-Sprache** „Deutsch“ ein. Die Antwortsprache steuern Sie getrennt unter **Einstellungen → Assistent → Antwortsprache**.

## Weitere Hilfe

Wenn Sie nicht weiterkommen, schreiben Sie an den Support von TERAX Solution. Nennen Sie dabei die Meldung unter **Letzter KI-Fehler** (Dashboard), die Plugin-Version und was Sie vor dem Fehler getan haben. Schicken Sie niemals Ihren API-Schlüssel.
