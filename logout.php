<?php

/* 
Programmet hanterar utloggning av en inloggad användare.
Detta görs genom att nollställa och förstöra den aktuella sessionen samt skapa ett nytt sessions-id.
Om användaren sedan tidigare har en kaka sparad i webbläsaren för en kontosession så tas denna kaka
bort och sessions-id-värdet för aktuellt konto i databasens tabell sätts till null för att nollställa lagrade
session för kontot.
När allt detta gjorts omdirigeras användaren tillbaka till nätbutikens startsida.
*/

/* 
Startar PHPs inbyggda stöd för sessionshantering genom sessionsvariabler innehållandes information om klienten/sessionen.
Argumenten sätter att HTTP Header för sessionskakan ska ha sant värde på flaggorna för secure och httponly.
Secure ser till att kakan enbart ska användas vid en krypterad anslutning (HTTPS).
Httponly anger att kakan inte ska vara åtkomlig utanför HTTP-protokollet, för att skydda kakan från illvilliga skript hos klienten.
*/
session_start(["cookie_secure" => true, "cookie_httponly" => true]);

// Genererar nytt sessions-id för sessionen och tar bort den gamla sessionsfilen.
session_regenerate_id(true);

/* 
Kontrollerar om ett specifikt index i cookievariabeln existerar, 
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten har en kaka som anger ett sessions-id för ett tidigare inloggat konto sparad.
Finns det så börjar man med att uppdatera databasen genom att skicka en SQL-fråga om att uppdatera den rad som gäller för 
aktuellt konto, med användarnamnet taget ur sessionsvariabeln, och den kolumn som representerar sessions-id.
I denna kolumn ersätts aktuell sessions-id med null för att nollställa lagrade session för kontot.
Sedan tas kakan med sessions-id bort genom att ange en utgångstid bak i tiden.
*/ 
if (isset($_COOKIE["account_session_id"])) {
    $connection = connectToDatabase();

    $username = $_SESSION["current_user"]["username"];

    $sql = "UPDATE Users SET sessionId=NULL WHERE username='$username'";
    mysqli_query($connection, $sql);
    mysqli_close($connection);

    setcookie("account_session_id", "", time() - 99999, "/", "localhost", true, true);
}

$_SESSION = array(); // Nollställer sessionsvariabeln genom att tilldela en ny tom array.
session_destroy(); // Förstör all data som finns registrerad för aktuell session. 

header("Location: index.php"); // Omdirigerar klienten till nätbutikens startsida.

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

?>