<?php

/* 
Programmet hanterar situationer där användaren glömt sitt lösenord och begär att få ett nytt tillskickat.
Programmet tar emot en epostadress via ett formulär som skickar datan via HTTP POST.
Epostadressen undersöks mot databasen för att se om ett konto med den epostadressen finns registrerad.
Om den gör det genereras ett nytt lösenord, hashas och uppdateras i databasen.
Slutligen mailas det nya lösenordet till epostadressen.
Programmet utnyttjar en klass/modul som heter 'PHPMailer' för att möjliggöra hantering och sändning av epost.
*/

/* 
PHPMailer-klassen använder en specifik namnrymd.
Detta importerar klassen till det egna programmets namnrymd.
*/
use PHPMailer\PHPMailer\PHPMailer;

/* 
Dessa rader inkluderar PHPMailers specifika klassfiler.
Detta anger vilka filer som ska laddas in i programmet.
*/
require "PHPMailer/PHPMailer.php";
require "PHPMailer/SMTP.php";
require "PHPMailer/Exception.php";

// Läser av innehållet i HTML-filen och lagrar det som string i variabeln.
$html = file_get_contents("reset.html");

// Deklarering av tomma variabler som ska representera felmeddelande och status.
$emailError = $resetStatus = "";

/* 
Kontrollerar om anropet till programmet skett via HTTP metoden POST samt om nyckeln 'submit' som skickas med i POST,
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten klickat på knappen 'Email me a new password' vid begäran om nytt lösenord
och bekräfta att sändningen gjorts via POST.
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
    Om inga felmeddelanden uppstått så anropas funktionen som hanterar återställning av lösenord
    med epostadressen som argumentvärde.
    */
    if (!$error) {
        resetPWHandler($email);
    }

}

setResetStatus(); // Sätter statusmeddelandet till klienten.

setFormError();  // Sätter eventuellt felmeddelandet för inmatningen i formuläret.

echo($html); // Skriver ut HTML-strängen som respons till klientens webbläsare.

/* 
Funktion som sätter eventuellt felmeddelande och visar de för användaren.
Funktionen får åtkomst till globalt deklarerade variabler.
I detta fall de variabler som innehåller HTML-strängen och felmeddelandet.
Detta meddelande appliceras i HTML-dokumentet genom att ersätta markören.
Här felmeddelande för: epost
*/
function setFormError() {
    global $html;
    global $emailError;


    $html = str_replace("---email_error---", $emailError, $html);
}

/* 
Funktion som sätter ett eventuellt statusmeddelande och visar den för användaren.
Funktionen får åtkomst till globalt deklarerade variabler.
I detta fall de variabler som innehåller HTML-strängen och felmeddelandet.
Detta meddelande appliceras i HTML-dokumentet genom att ersätta markören med felmeddelandet.
Annars en tom sträng för att dölja markören för klienten.
*/
function setResetStatus() {
    global $html;
    global $resetStatus;

    $html = str_replace("---reset_status---", $resetStatus, $html);
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
Funktion som hanterar återställning och generering av nytt lösenord.
Funktionen använder sig av värdet för klientens angivna epostadress och
den globala variabeln för status av som beskriver resultatet av återställningen.
Funktionen börjar med en preparerad SQL-fråga till databasen för hämta information om
ett konto som har den angivna epostadressen registrerad. Den angivna epostadressen blir
alltså parameter/villkoret till frågan.
När exekvering och hämtning av frågans resultat sker en kontroll som tittar på om den
angivna epostadressen inte är lika med epostadressen i resultatet från databasen -
detta är egentligen en kontroll för att se om resultatet returnerades tom, alltså
att det inte finns något konto med angiven epostadress. I sådant fall sätts variabeln 
för status med ett meddelande som anger att angiven epostadressen inte är registrerad.
I annat fall om ett konto hittades i databasen fortsätter återställningsprocessen.
Då börjar man ett anrop till en funktion som slumpgenererar ett lösenord på 8 tecken.
Detta lösenord tas sedan till en hashfunktion för att omvandla lösenordet till ett hashvärde.
Den hashalgoritmen som används här är PHPs standard algoritm för lösenord, CRYPT_BLOWFISH.
Av säkerhetsskäl kan hashvärdet som skapats inte avkrypteras tillbaka för att få ut lösenordet och
hashvärdet gör att man undviker att lagra lösenordet i klartext i databasen.
Sedan sker en ny SQL-fråga som begär uppdatering av den rad i tabellen för användarkonton som matchar
epostadressen, där värdet i kolumnen för lösenord ändras till det nya hashvärdet.
En kontroll utförs för att se om exekvering av frågan lyckades. Lyckades uppdateringen sätts variabeln 
för status med ett meddelande som anger att lösenordet har återställts och att det nya lösenordet har 
skickats som epost till angiven adress, sedan anropas funktionen som hanterar sändning av epost med det
nya lösenordet till användaren.
Om exekveringen misslyckades sätts variabeln för status med ett meddelande som anger att återställningen
inte lyckades och ber klienten försöka igen.
*/
function resetPWHandler($email) {
    global $resetStatus;

    $connection = connectToDatabase();

    $sql = "SELECT email FROM Users WHERE email = ?";

    $stmt = mysqli_prepare($connection, $sql);

    mysqli_stmt_bind_param($stmt, "s", $email);

    if (!mysqli_stmt_execute($stmt)) {
        die("Database query failed: " . mysqli_error($connection));
    }

    mysqli_stmt_bind_result($stmt, $emailCol);

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);

    if ($email !== $emailCol) {
        $resetStatus = "$email is not registered.";
    } else {
        $password = generatePW();
        $pwHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "UPDATE Users SET password='$pwHash' WHERE email='$email'";

        if (mysqli_query($connection, $sql)) {
            $resetStatus = "Password has been reset! A new password has been sent to $email";
            emailNewPW($email, $password);
        } else {
            $resetStatus = "Password reset failed. Please try again!";
        }
    }
    
    mysqli_close($connection);
}

/* 
Funktion som slumpgenererar ett lösenord på 8 tecken.
Den utgår ifrån en sträng innehållandes hela engelska alfabetet med både små och stora
bokstäver samt siffror 0 till 9.
Det är ur denna sträng som loopen i varje varv slumpar fram ett index som motsvarar antalet
index i strängen. Av säkerhetsskäl används inte PHPs vanliga random-funktion eftersom här
handlar det om lösenord, därför används en funktion som slumpgenererar ett kryptografiskt säkert
slumpat heltal.
Under varje varv appliceras varje slumpgenererat tecken till strängvariabeln för att bilda ett helt lösenord
på 8 tecken när loopen är klar.
Sedan returneras det genererade lösenordet.
*/
function generatePW() {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $pw = "";
    for ($i = 0; $i < 8; $i++) {
        $pw .= $chars[random_int(0, 61)];
    }
    return $pw;
}

/* 
Funktion som hanterar sändning av meddelande med det uppdaterade lösenordet till kontots epostadress.
Funktionen använder sig av epostadressen och det nya lösenordet som angivits i funktionens argument.
Här används modulen 'PHPMailer' för att skapa och sända epost.
Sändningen använder nätbutikens egna epostadress som avsändare, med ett namn på avsändaren som avser nätbutikens namn.
Till alla meddelanden skickas en text som informerar mottagaren att en begäran om nytt lösenord har gjorts och
det nya lösenordet appliceras in.
Slutligen när alla nödvändiga inställningar och uppgifter angetts skickas meddelandet iväg.
*/
function emailNewPW($email, $password) {
    $mail = new PHPMailer(); // Skapar en instans av PHPMailer.
    $mail -> CharSet = "UTF-8"; // Anger att teckenkodning ska vara UTF-8.
    $mail -> isSMTP(); // Anger att SMTP ska användas.
    $mail -> Host = "smtp.gmail.com"; // Anger Googles mailserver.
    $mail -> Port = 587; // Anger SMPTP-porten för TLS autentisering.
    $mail -> SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Anger krypteringsmetod för SMTP-anslutningen.
    $mail -> SMTPAuth = true; // Anger att autentisering ska ske med SMTP.
    $mail -> Username = "username"; // Anger användarnamn/e-postadress som ska skickas till mailservern.
    $mail -> Password = "password"; // Anger lösenordet som ska skickas till mailservern.

    /* 
    Anger epostadress och namn på den som ska stå som avsändare.
    Samma epostadress som i användarnamnet.
    */
    $mail -> setFrom($mail -> Username, "Butiken");

    /* 
    Anger vem som ska stå som mottagare.
    Epostadressen som går till kontoinnehavaren.
    */
    $mail -> addAddress($email);


    $mail -> Subject = "Butiken - Your new password"; // Anger vad som ska stå på ämnesraden.

    // Anger innehållet i meddelandet, epostens kropp.
    $mail -> Body = "A request to reset your password has been made.\n\n" . 
    "Below is your new password:\n\n". $password . "\n\nBest regards,\nButiken";
    
    $mail -> send();
}

?>