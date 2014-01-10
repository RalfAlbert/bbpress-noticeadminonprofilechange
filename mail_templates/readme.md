# Formatierung und Platzhalter #
Diser Text bezieht sich ausschließlich auf die in den hier verwendeten Templates.

## Generelles ##
Alle Platzhalter sind in Jango-Style anzugeben ( `{{platzhalter}}` )

Bsp.:
`{{user}}` -> gibt den Benutzernamen aus

Platzhalter für bereits übersetzte Strings beginnen mit einem Unterstrich (_)

Bsp.:
`{{_name_}}` -> Gibt den mit `__( 'Name' )` im Code übersetzten String aus.


## Derzeit verfügbare bzw. verwendetet Platzhalter ##
### Platzhalter für Variablen ###
<table>
<thead><tr><th>Platzhalter</th><th>Wird erstetzt durch...</th></tr></thead>
<tr><td>`{{user_name}}`</td><td>Benutzername</td></tr>
<tr><td>`{{user_email}}`</td><td>E-Mail des Benutzers</td></tr>
<tr><td>`{{display_name}}`</td><td>Anzuzeigender Name wie er vom Benutzer eingestellt wurde</td></tr>
<tr><td>`{{table}}`</td><td>Die vom Plugin erzeugte Tabelle</td></tr>
<tr><td>`{{group}}`</td><td>Der Name der Feldgruppe</td></tr>
<tr><td>`{{field}}`</td><td>Der Feldname</td></tr>
<tr><td>`{{value}}`</td><td>Der Feldinhalt</td></tr>
</table>

### Platzhalter für übersetzte Strings ###
Im Plugin werden einige Strings verwendet die mit `l18n` Funktionen übersetzt werden. Dieser Strings können als Platzhalter verwendet werden.

Was in der verschickten Mail für diese Platzhalter angezeigt wird, hängt von der jeweiligen Übersetzumg ab.
<table>
<thead><tr><th>Platzhalter</th><th>Origanltext (nicht übersetzt)</th></tr></thead>
<tr><td>`{{_name_}}`</td><td>Name</td></tr>
<tr><td>`{{_email_}}`</td><td>Email</td></tr>
<tr><td>`{{_headline_}}`</td><td>changed this fields</td></tr>
<tr><td>`{{_group_}}`</td><td>Group</td></tr>
</table>

## Schleifen zur Verarbeitung von Arrays ##
Der in den Tags `[loop]` und `[/loop]` eingeschlossene Text wird zur Bildung von Tabellenzeilen verwendet. Um z.B. eine HTML-Tabelle zu erzeugen die die Daten eines Arrays anzeigt, müsste folgender Code verwendet werden:

	<table>
	<thead><tr><th>Feld</th><th>Wert</th></tr></thead>
	[loop]<tr><td>{{field}}</td><td>{{value}}</td></tr>[/loop]
	</table>

Die Zeile mit `[loop] ... [/loop]` wird auf jeden Wert des Feldes angewendet.

**Alle Zeilenumbrüche müssen genauso im Template angegeben werden wie sie in der Ausgabe erwartet werden!!**

	[loop]{{value}}|[/loop]
erzeugt die Ausgabe	

	foo|bar|baz|

Während hingegen

	[loop]{{value}}
	[/loop]
die Ausgabe

	foo
	bar
	baz
erzeugt.
 