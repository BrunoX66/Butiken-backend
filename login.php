<?php

/* 
Programmet representerar inloggningssidan på webbplatsen.
Programmet hanterar situationer där användare loggar in på sitt konto i nätbutiken.
Inloggningar sker via ett formulär som skickar data via HTTP POST.
För extra säkerhet kontrollerar programmet att alla fält har fyllts i istället för att lämna ansvaret 
åt HTML-dokumentet på klientsidan - detta gör att programmet kan visa ett felmeddelande till klienten om den ser ett tomt värde.
Varje inloggning som lyckas loggas i en textfil.
*/

/* 
Startar PHPs inbyggda stöd för sessionshantering genom sessionsvariabler innehållandes information om klienten/sessionen.
Argumenten sätter att HTTP Header för sessionskakan ska ha sant värde på flaggorna för secure och httponly.
Secure ser till att kakan enbart ska användas vid en krypterad anslutning (HTTPS).
Httponly anger att kakan inte ska vara åtkomlig utanför HTTP-protokollet, för att skydda kakan från illvilliga skript hos klienten.
*/
session_start(["cookie_secure" => true, "cookie_httponly" => true]);

/* 
Kontrollerar om ett specifikt index i cookievariabeln existerar, 
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten har en kaka som anger ett sessions-id för ett tidigare inloggat konto sparad.
I sådana fall kontrolleras detta sparade sessions-id om det matchar ett existerande sessions-id i databasen,
gör den det så hämtas information om kontot, sedan omdirigeras användaren till startsidan för nätbutiken och programmet avslutas.
Detta förhindrar användaren att nå inloggningssidan om användaren redan har ett inloggat konto sparad.
*/ 
if (isset($_COOKIE["account_session_id"])) {
    if (getAccountSessInfo()) {
        header("Location: index.php");
        exit();
    }
}

// Läser av innehållet i HTML-filen och lagrar det som string i variabeln.
$html = file_get_contents("login.html");

setFieldValues(); // Sätter värdet för epost-fältet.

// Deklarering av tomma variabler som ska representera olika felmeddelanden.
$loginError = $emailError = $pwError = "";

/* 
Kontrollerar om anropet till programmet skett via HTTP metoden POST samt om nyckeln 'submit' som skickas med i POST,
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten klickat på knappen 'Sign In' vid inloggning och bekräfta att sändningen gjorts via POST.
*/
if (isset($_POST["submit"]) && $_SERVER["REQUEST_METHOD"] == "POST") {

    $error = ""; // Variabel som ska fånga upp eventuella felmeddeladen.

    /* 
    Kontroll för att se om variabeln 'email' som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    I annat fall så för säkerhetsskull saneras värdet innan det sparas.
    */
    if (empty($_POST["email"])) {
        $error = $emailError = "Email is required.";
    } else {
        $email = inputSanitizer($_POST["email"]);
    }

    /* 
    Kontroll för att se om variabeln 'password' som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    Ingen sanering sker här eftersom lösenordet kan innehålla tecken och blanksteg som användaren valt.
    Lösenordet kommer bara användas för att jämföra dess hash med hashen i databasen.
    */
    if (empty($_POST["password"])) {
        $error = $pwError = "Password is required.";
    } else {
        $password = $_POST["password"];
    }

    /* 
    Om inga felmeddelanden uppstått så skickas inloggningsuppgifterna till den funktion
    som hanterar inloggningsprocessen.
    */
    if (!$error) {
        loginHandler($email, $password);
    }

}

setLoginError(); // Sätter eventuellt felmeddelande för inloggning.

setFormError(); // Sätter eventuellt felmeddelanden för inmatningarna i formuläret.

echo($html); // Skriver ut HTML-strängen som respons till klientens webbläsare.

/* 
Funktionen sätter det värde som ska stå i formulärets epostfält.
Syftet är att om något fel sker i inmatningen ska den inmatade textvärdet bevaras vid
omladdning av webbsidan för att användaren ska slippa skriva om adressen varje gång.
Detta görs genom kontroll att den variabel som skickats via POST finns och ej är null, då sparas värdet i variabeln.
Ett annat syfte görs i andra kontrollen som tittar på om den variabel som skickats via GET finns och ej är null, då sparas värdet i variabeln,
syftet här är att om en epostadress skickats med till programmet via GET så appliceras den i fältet. Det fallet sker när en användare precis
registrerat ett konto och omdirigeras till login-sidan med den registrerade adressen.
I sista fall så sätts variablerna till tomma strängar.
Avslutningsvis ersätts markören för fältet i HTML-dokumentet med värdet i den sparade strängvariabeln,
alltså det tidigare inmatade värdet eller medskickade värdet från HTTP GET.
*/
function setFieldValues() {
    global $html;
    
    if (isset($_POST["email"])) {
        $emailField = $_POST["email"];
    } elseif (isset($_GET["email"])) {
        $emailField = $_GET["email"];
    } else {
        $emailField = "";
    }
    
    $html = str_replace("---email_value---", $emailField, $html);
}

/* 
Funktion som sätter eventuella felmeddelanden och visar de för användaren.
Funktionen får åtkomst till globalt deklarerade variabler.
I detta fall de variabler som innehåller HTML-strängen och felmeddelanden.
Dessa meddelanden appliceras i HTML-dokumentet genom att ersätta markörer.
Här felmeddelanden för: epost, lösenord
*/
function setFormError() {
    global $html;
    global $emailError;
    global $pwError;


    $html = str_replace("---email_error---", $emailError, $html);
    $html = str_replace("---password_error---", $pwError, $html);
}

/* 
Funktion som hanterar felmeddelanden för misslyckade inloggningsförsök.
Funktionen får åtkomst till globalt deklarerade variabler.
I detta fall de variabler som innehåller HTML-strängen och felmeddelandet.
Detta meddelande appliceras i HTML-dokumentet genom att ersätta markörer.
*/
function setLoginError() {
    global $html;
    global $loginError;

    $html = str_replace("---login_error---", $loginError, $html);
}

/* 
Funktion som av säkerhetsskäl sanerar inmatningsvärden, i de fall en sträng illvilligt försöker utnyttja sårbarheter i programmet.
Detta görs genom att ta bort eventuell PHP samt HTML-kod ur strängvärdet från argumentet samt borttagning av
eventuella omvända snedstreck som använts. Även blanksteg i början och slutet av strängen tas bort.
Returnerar den sanerade strängen.
*/
function inputSanitizer(string $string) {
    $string = trim($string);
    $string = stripslashes($string);
    $string = strip_tags($string);

    return $string;
} 

/* 
Funktion som hämtar en anslutningsreferens till webbplatsens databas.
Returnerar därefter referensen till anslutningen.
Skulle anslutningen misslyckas avslutas programmet med ett felmeddelande.
*/
function connectToDatabase() {
    $dbserver = "atlas.dsv.su.se";
    $dbusername = "username";
    $dbpassword = "password";
    $dbname = "name";

    $connection = mysqli_connect($dbserver, $dbusername, $dbpassword, $dbname);

    if (!$connection) {
        die("Connection to database failed: " . mysqli_connect_error());
    }

    // Sätts för att frågor mot databasen ska exekveras direkt som standard.
    mysqli_autocommit($connection, true);

    return $connection;
}

/* 
Funktion som hanterar inloggningsprocessen.
Arbetet utförs utifrån inloggningsuppgifterna som skickats från formuläret, epost och lösenord.
Kontroll av att ett konto existerar med det angivna epostadressen görs genom en hämtning av den rad
i databasen som innehåller samma värde för adressen. Hämtning görs för användarnamn och lösenord och
sker genom en preparerad SQL-fråga med epostadressen som parameter.
Resultatet, rade, binds till deklarerade variabler för varje kolumnvärde.
Sedan utförs en kontroll om kontouppgifterna stämmer överens med ett existerande konto.
Kontrollen tittar först på om epostadressen stämmer överens med databasens epostadress, detta
tittar huvudsaklingen på om resultatet från databasen faktiskt innehåller ett värde, annars innebär det
att frågan till databasen inte hittade en matchande rad.
Om epostadressen stämmer kontrolleras om det angivna lösenordet stämmer med det hashvärde som finns för kontot i databasen.
Stämmer båda dessa lagras epostadressen och användarnamnet i indexet för inloggad användare i sessionsvariabeln.
Lyckad inloggning anropar funktion för loggning av inloggningen.
Klienten omdirigeras till nätbutikens startsida och programmet avslutas.
Här utförs även en kontroll om användaren bockat i checkboxen som heter 'remember', genom att kontrollera om variabeln 
skickats via POST finns och ej är null samt har värdet 'true'. I sådant fall anropas funktionen som hanterar sparning
av kontosessionen på klientens webbläsare.
Om epostadressen och lösenordet inte stämde överens med databasens kontouppgifter sparas ett felmeddelande för
inloggningsförsöket som ska visas för klienten.
*/
function loginHandler($email, $password) {
    global $loginError;

    $connection = connectToDatabase();

    $sql = "SELECT email, username, password FROM Users WHERE email=?";

    $stmt = mysqli_prepare($connection, $sql);

    mysqli_stmt_bind_param($stmt, "s", $email);

    if (!mysqli_stmt_execute($stmt)) {
        die("Database query failed: " . mysqli_error($connection));
    }

    mysqli_stmt_bind_result($stmt, $emailCol, $usernameCol, $passwordHashCol);

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);

    mysqli_close($connection);

    if ($email == $emailCol && password_verify($password, $passwordHashCol)) {
        $_SESSION["current_user"]["email"] = $emailCol;
        $_SESSION["current_user"]["username"] = $usernameCol;

        if (isset($_POST["remember"]) && $_POST["remember"] == "true") {
            setRememberAccountSess($usernameCol);
        }

        loginLogger($usernameCol, $emailCol);

        header("Location: index.php");

        exit();
    } else {
        $loginError = "Incorrect email and/or password.";
    }
    
}

/* 
Funktionen hanterar de fall användaren bockat i checkboxen för 'Remember me',
alltså att användaren vill komma ihåg kontoinloggningen på dennes webbläsare för
att slippa skriva sina inloggningsuppgifter varje gång.
Detta hanteras på två steg.
I första steget skapas en kaka, som innehåller ett värde för sessions-id, denna kaka ska existera i
30 dagar från att den skapats och gälla för hela webbplatsen på domänen 'localhost'.
Kakan har 'secure'-flaggan satt till på för att ange att denna kaka endast får överföras i en krypterad
HTTPS-anslutning. Kakan har också 'httponly'-flaggan aktiv för att förhindra åtkomst till den utanför
HTTP-protokollet, exempelvis kan inte skript hos klienten få åtkomst och läsa av eller ändra kakan.
I andra steget utförs en SQL-fråga som uppdaterar den rad och kolumn som gäller för aktuell inloggad användare.
Uppdateringen sker på den kolumn som lagrar ett sessions-id för kontot och raden bestäms utifrån användarnamn som villkor.
Samma sessions-id-värde som i kakan placeras alltså även i databasen för att matcha varandra.
*/
function setRememberAccountSess($username) {
    $connection = connectToDatabase();

    $sessId = session_id(); // Utnyttjar sessions-id värdet som finns i aktuell PHP-session.
    setcookie("account_session_id", $sessId, time() + (86400 * 30), "/", "localhost", true, true);

    $sql = "UPDATE Users SET sessionId='$sessId' WHERE username='$username'";
    mysqli_query($connection, $sql);

    mysqli_close($connection);
}

/* 
Funktion som loggar varje inloggning som lyckas i en textfil.
Funktionen börjar med att öppna filen för skrivning.
Sedan utförs en förfrågan om att få ett exklusivt lås till den öppnade filen och kontrollerar att ingen annan session/process
redan låst filen för användning. Annars skrivs ett meddelande ut om att låsbegäran misslyckades.
Får programmet låset utförs först en kontroll på filens storlek motsvarar 0 bytes, i sådant fall betyder
det att filen är tom och då skrivs det till filen ett antal rubriker på kolumnerna som ska finnas.
Sedan initieras variabler med:
- År, månad, datum och tid för inloggning.
- Användarens IP-adress vid inloggning.
- Användarens hostnamn på IP-adressen.
Dessa värden samt kontots användarnamn och epostadress skrivs vid varje inloggning på ny rad i loggfilen.
Slutligen släpps låset om filen för användning av andra processer.
*/
function loginLogger($username, $email) {
    $file = fopen("./logs/login.txt", "a");

    if (flock($file, LOCK_EX)) {

        if (filesize("./logs/login.txt") === 0) {

            fwrite($file, "       TIME         |         Username        |               Email               |           REMOTE IP\n\n");

        }

        $time = date("Y-m-d H:i:s");
        $remoteAddr = $_SERVER["REMOTE_ADDR"];
        $remoteHost = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
        fwrite($file, "$time | $username | $email | $remoteAddr ($remoteHost) \n\n" );

        flock($file, LOCK_UN);
    } else {

        echo("Cannot lock file.");

    }

    fclose($file);
}

/* 
Funktionen hanterar hämtning av aktuell kontosession om klienten har en lagrad kaka med sessions-id för ett konto.
En kontroll utförs genom en preparerad SQL-fråga till databasen för att hämta den eventuella rad som matchar sessions-id i kakan.
Hämtning görs för epost och användarnamn. När hämtning från databas är gjord kontrolleras om resultatet för epost och användarnamn
innehåller hämtade värden och ej är av falskt värde, i sådant fall lagras dessa två värden i sessionsvariabeln på det index som representerar
inloggad användare. I annat fall om epost och användarnamn är tomma så returneras 'false'.
*/
function getAccountSessInfo() {
    $connection = connectToDatabase();

    $sql = "SELECT email, username FROM Users WHERE sessionId=?";

    $stmt = mysqli_prepare($connection, $sql);

    mysqli_stmt_bind_param($stmt, "s", $_COOKIE["account_session_id"]);

    if (!mysqli_stmt_execute($stmt)) {
        echo("Account session retrieval failed: " . mysqli_error($connection));
    }

    mysqli_stmt_bind_result($stmt, $emailCol, $usernameCol);

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);

    mysqli_close($connection);

    if ($emailCol && $usernameCol) {
        $_SESSION["current_user"]["email"] = $emailCol;
        $_SESSION["current_user"]["username"] = $usernameCol;
        return true;
    } else {
        return false;
    }
}

?>