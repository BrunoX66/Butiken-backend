<?php

/* 
Programmet hanterar registrering av nytt konto på nätbutiken.
Detta sker genom ett formulär som finns i HTML-dokumentet och datan skickas till programmet
via HTTP POST.
Programmet har inmatningskontroller för att se till att all nödvändig data finns med,
att formatet på kontoinformation fylls i korrekt och att angiven användarnamn och epostadress inte redan är registrerad på nätbutiken.
För extra säkerhet kontrollerar programmet att alla obligatoriska fält har fyllts i istället för att lämna ansvaret 
åt HTML-dokumentet på klientsidan - detta gör att programmet kan visa ett felmeddelande till klienten om den ser ett tomt värde.
En bekräftelse att kontoskaparen är en människa sker genom krav på att fylla i rätt CAPTCHA-kod.
*/

/* 
Startar PHPs inbyggda stöd för sessionshantering genom sessionsvariabler innehållandes information om klienten/sessionen.
Argumenten sätter att HTTP Header för sessionskakan ska ha sant värde på flaggorna för secure och httponly.
Secure ser till att kakan enbart ska användas vid en krypterad anslutning (HTTPS).
Httponly anger att kakan inte ska vara åtkomlig utanför HTTP-protokollet, för att skydda kakan från illvilliga skript hos klienten.
*/
session_start(["cookie_secure" => true, "cookie_httponly" => true]);

// Läser av innehållet i HTML-filen och lagrar det som string i variabeln.
$html = file_get_contents("register.html");

setFieldValues(); // Sätter värden på fälten för epost och användarnamn.

// Deklarering av tomma variabler som ska representera olika felmeddelanden.
$emailError = $unameError = $pwError = $captchaError = "";

/* 
Kontrollerar om anropet till programmet skett via HTTP metoden POST samt om nyckeln 'submit' som skickas med i POST,
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten klickat på knappen 'Sign Up' vid registrering och bekräfta att sändningen gjorts via POST.
*/
if (isset($_POST["submit"]) && $_SERVER["REQUEST_METHOD"] == "POST") {

    /* 
    Tilldelar datan som skickats från formuläret till nya variabler för tydlighetensskull.
    För CAPTCHA, epost och användarnamn sker borttagning av blanksteg i början och slutet av värdet innan tilldelning.
    */
    $captcha = trim($_POST["captcha"]);
    $email = trim($_POST["email"]);
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $error = ""; // Variabel som ska fånga upp eventuella felmeddeladen.

    /* 
    Kontroll för att se om CAPTCHA-koden som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    I annat fall så jämförs den angivna koden i formuläret med den korrekta CAPTCHA-koden lagrad i sessionsvariabeln.
    Skulle dessa två inte vara lika sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    */
    if (empty($captcha)) {
        $error = $captchaError = "CAPTCHA is required.";
    } elseif ($captcha != $_SESSION["captcha"]) {
        $error = $captchaError = "CAPTCHA is invalid.";
    }

    /* 
    Kontroll för att se om epostadressen som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    Är den ej tom kontrolleras formatet på epostadressen genom att köra strängvärdet genom ett filter
    som returnerar 'false' om filtreringen av strängen misslyckades. Här används ett inbyggt PHP-filter
    för validering av epostadress-format. Misslyckas validering sätts variablerna för fel med ett 
    felmeddelande som beskriver felet för klienten.
    I sista fall så för säkerhetsskull saneras värdet innan det sparas.
    */
    if (empty($email)) {
        $error = $emailError = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $emailError = "Email format is invalid.";
    } else {
        $email = inputSanitizer($email);
    }

    /* 
    Kontroll för att se om användarnamnet som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    Är den ej tom kontrolleras användarnamnets format enligt bestämda regler med hjälp av ett
    reguljärt uttryck. Det reguljära uttrycket anger att strängen endast får innehålla små bokstäver
    mellan a och z, samt siffror. Dessutom ska strängen vara minst 3 tecken lång och inte mer än 25.
    Matchar inte angivna användarnamnet med det reguljära uttrycket så sätts variablerna för fel 
    med ett felmeddelande som beskriver felet för klienten.
    I sista fall så för säkerhetsskull saneras värdet innan det sparas.
    */
    if (empty($username)) {
        $error = $unameError = "Username is required.";
    } elseif (!preg_match("/^[a-z0-9]{3,25}$/", $username)) {
        $error = $unameError = "Username format is invalid. Must be 3-25 characters long. Only lowercase letters and numbers are allowed.";
    } else {
        $username = inputSanitizer($username);
    }

    /* 
    Kontroll för att se om lösenordet som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    Annars så kontrolleras det om längden på det angivna lösenordet är mindre än 8 tecken.
    Är lösenordet det så anses det för kort och variablerna för fel sätts med ett felmeddelande 
    som beskriver felet för klienten.
    Ingen sanering sker här eftersom lösenordet kan innehålla tecken och blanksteg som användaren valt.
    Lösenordet kommer ändå att lagras som ett hashvärde i databasen.
    */
    if (empty($password)) {
        $error = $pwError = "Password is required.";
    } elseif (strlen($password) < 8) {
        $error = $pwError = "Minimum password length is 8 characters.";
    }

    /* 
    Om inga felmeddelanden uppstått så hämtas en anslutning till databasen för att sedan anropa en funktion
    som kontrollerar att tabellen för användarkonton existerar i databasen.
    Därefter skickas kontouppgifterna till den funktion som hanterar registreringsprocessen.
    Denna funktion returnerar sedan ett boolskt värde som anger om kontoregistering lyckades eller ej.
    Lyckades registreringen, det returnerade värdet är av sann typ, så dirigeras klienten till inloggningssidan
    med den registrerade epostadressen sänt som GET-parameter till URL och programmet avslutas.
    */
    if (!$error) {
        $connection = connectToDatabase();
        checkTableExists($connection);
        $added = addNewUser($connection, $email, $username, $password);

        if ($added) {
            header("Location: login.php?email=$email");
            exit();
        }
    }
    
}

setFormError(); // Sätter eventuellt felmeddelanden för inmatningarna i formuläret.

echo($html); // Skriver ut HTML-strängen som respons till klientens webbläsare.

/* 
Funktionen sätter det värde som ska stå i formulärets fält för epost och användarnamn.
Syftet är att om något fel sker i inmatningen ska de inmatade textvärdena bevaras vid
omladdning av webbsidan för att användaren ska slippa skriva om uppgifterna varje gång.
Detta görs genom kontroller att variablerna som skickats via POST finns och ej är null, 
då tas eventuella blanksteg i början och slutet av värdena bort och sparar sedan dem i variabler.
Finns inte POST-variablerna så sätts variablerna till tomma strängar.
Avslutningsvis ersätts markörerna för fälten i HTML-dokumentet med värdena i de sparade strängvariablerna,
alltså de tidigare inmatade värdena.
*/
function setFieldValues() {
    global $html;
    
    $emailField = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $unameField = isset($_POST["username"]) ? trim($_POST["username"]) : "";
    
    $html = str_replace("---email_value---", $emailField, $html);
    $html = str_replace("---username_value---", $unameField, $html);
}

/* 
Funktion som sätter eventuella felmeddelanden och visar de för användaren.
Funktionen får åtkomst till globalt deklarerade variabler.
I detta fall de variabler som innehåller HTML-strängen och felmeddelanden.
Dessa meddelanden appliceras i HTML-dokumentet genom att ersätta markörer.
Här felmeddelanden för: epost, användarnamn, lösenord, CAPTHCHA-kod
*/
function setFormError() {
    global $html;
    global $emailError;
    global $unameError;
    global $pwError;
    global $captchaError;


    $html = str_replace("---email_error---", $emailError, $html);
    $html = str_replace("---username_error---", $unameError, $html);
    $html = str_replace("---password_error---", $pwError, $html);
    $html = str_replace("---captcha_error---", $captchaError, $html);
}

/* 
Funktion som av säkerhetsskäl sanerar inmatningsvärden, i de fall en sträng illvilligt försöker utnyttja sårbarheter i programmet.
Detta görs genom att ta bort eventuell PHP samt HTML-kod ur strängvärdet från argumentet samt borttagning av
eventuella omvända snedstreck som använts. Returnerar den sanerade strängen.
*/
function inputSanitizer(string $string) {
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
Funktion som skapar SQL-fråga för skapandet av tabell för användarkonton.
Skapandet av tabell tillämpas endast om tabellen inte redan existerar i databasen.
Om körning av frågan skulle misslyckas avslutas programmet med ett felmeddelande
som beskriver det uppstådda felet vid exekvering av frågan.
*/
function checkTableExists($connection) {
    $sql = "CREATE TABLE IF NOT EXISTS Users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        username VARCHAR(25) NOT NULL,
        password VARCHAR(50) NOT NULL,
        UNIQUE (email, username)
    )";

    if (!mysqli_query($connection, $sql)) {
        die("Creation of table 'Users' failed: " . mysqli_error($connection) . "\n");
    }
}

/* 
Funktion som hanterar registreringsprocessen för konton.
Det första programmet gör att med en hashfunktion omvandla det angivna lösenordet till ett hashvärde.
Den hashalgoritmen som används här är PHPs standard algoritm för lösenord, CRYPT_BLOWFISH.
Av säkerhetsskäl kan hashvärdet som skapats inte avkrypteras tillbaka för att få ut lösenordet och
hashvärdet gör att man undviker att lagra lösenordet i klartext i databasen.
Sedan utförs en fråga till databasen om insättning av ny rad i tabellen för användarkonton för att skapa
ett nytt konto med klientens angivna uppgifter.
Kommunikationen till databasen sker genom en preparerad SQL-fråga och transaktionshantering.
Som parametrar till den preparerade frågan anges epostadressen, användarnamnet och lösenordet.
Skulle ett fel uppstå när frågan exekveras på databasen sparas felmeddelandet och skickas som argument
tillsammans med epostadressen och användarnamnet till den funktion som undersöker felmeddelandet.
Den boolska variabeln som anger lyckad insättning sätts också till falskt vid misslyckad exekvering.
I slutet funktionen kontrolleras att inga fel uppstått i förfrågan till databasen och i sådant fall sker en commit
som tillämpar insättningen. Om något fel trots allt uppstått sker en återställning till stadiet innan ändringarna på databasen,
och boolska variabeln som anger lyckad insättning sätts till falskt.
Slutligen returneras värdet på den boolska variabeln för att ange lyckad/misslyckad insättning, skapande, av konto.
*/
function addNewUser($connection, $email, $username, $password) {
    $password = password_hash($password, PASSWORD_DEFAULT);

    $querySuccess = true;

    // Sätts för att frågor mot databasen inte ska exekveras direkt eftersom här utförs en transaktion.
    mysqli_autocommit($connection, false);

    mysqli_begin_transaction($connection);

    $sql = "INSERT INTO Users (email, username, password) VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($connection, $sql);

    mysqli_stmt_bind_param($stmt, "sss", $email, $username, $password);

    if (!mysqli_stmt_execute($stmt)) {
        $sqlError = mysqli_error($connection);

        checkInsertionError($sqlError, $email, $username);

        $querySuccess = false;
    }

    if (!$querySuccess || !mysqli_commit($connection)) {
        $querySuccess = false;
        mysqli_rollback($connection);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($connection);

    return $querySuccess;
} 

/* 
Funktionen undersöker felmeddelandet från databasen som skickats som argument till funktionen.
Funktionen utnyttjar även de globalt deklarerade variablerna för felmeddelande på epostadress och användarnamn.
Syftet med funktionen är att undersöka om felmeddelandet innehåller en beskrivning av fel som uppstått på
grund av att uppgifterna som försökts sättas in i databasen redan existerar i tabellen, alltså att insättningen 
misslyckades eftersom att det då skulle uppstå dubbletter i tabellen som ej är tillåtet.
Den första kontrollen undersöker om felmeddelandet innehåller texten för ett fel som beskriver att både
epostadressen och användarnamnet redan är registrerad.
I sådant fall sätts variablerna för fel med felmeddelande som anger att uppgifterna redan finns.
Den andra kontrollen undersöker om felmeddelandet innehåller texten för ett fel som beskriver att
epostadressen redan är registrerad.
I sådant fall sätts variabeln för fel med felmeddelande som anger att epostadressen redan finns.
Den tredje kontrollen undersöker om felmeddelandet innehåller texten för ett fel som beskriver att
användarnamnet redan är registrerad.
I sådant fall sätts variabeln för fel med felmeddelande som anger att användarnamnet redan finns.
*/
function checkInsertionError($sqlError, $email, $username) {
    global $emailError;
    global $unameError;

    if (strpos($sqlError, "Duplicate entry '$email-$username' for key 'email_username'") !== false) {
        $emailError = "This email address is already registered.";
        $unameError = "This username is already registered.";
    } elseif (strpos($sqlError, "Duplicate entry '$email' for key 'email'") !== false) {
        $emailError = "This email address is already registered.";
    } elseif (strpos($sqlError, "Duplicate entry '$username' for key 'username'") !== false) {
        $unameError = "This username is already registered.";
    }

}

?>