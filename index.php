<?php

/* 
Programmet representerar nätbutikens start- och huvudsida.
Härifrån kan man navigera till de flesta andra sidor på webbplatsen.
Detta program hanterar främst hämtning av alla nätbutikens produkter som finns
i databasen samt ger information om besökaren är inloggad eller ej.
Programmet ser till att kontrollera om man är inloggad eller ej och anpassar sidan efter det.
Besökaren kan även här lägga till produkter i kundvagnen.
*/

/* 
Startar PHPs inbyggda stöd för sessionshantering genom sessionsvariabler innehållandes information om klienten/sessionen.
Argumenten sätter att HTTP Header för sessionskakan ska ha sant värde på flaggorna för secure och httponly.
Secure ser till att kakan enbart ska användas vid en krypterad anslutning (HTTPS).
Httponly anger att kakan inte ska vara åtkomlig utanför HTTP-protokollet, för att skydda kakan från illvilliga skript hos klienten.
*/
session_start(["cookie_secure" => true, "cookie_httponly" => true]);

// Läser av innehållet i HTML-filen och lagrar det som string i variabeln.
$html = file_get_contents("index.html");

/* 
Delar upp HTML-strängen i flera delar, separerad med markörerna som finns i HTML-dokumentet.
Lagrar varje delsträng i en variabel som array.
*/
$htmlPieces = explode("<!--===product===-->", $html);

/* 
Kontrollerar om ett specifikt index i aktuella sessionsvariabeln existerar, 
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten är inloggad då indexet i variabeln finns endast hos en inloggad användare.
I sådana fall sätts webbsidan till utseendet för en inloggad användare.
Här kontrolleras även om ett produkt-id skickats med i en HTTP GET-förfrågan,
i sådana fall innebär det att användaren vill lägga till en produkt i kundvagnen för kontot.
*/
if (isset($_SESSION["current_user"])) {
    setLoggedInUserInfo();

    if (isset($_GET["productId"]) && $_SERVER["REQUEST_METHOD"] == "GET") {
        addItemToAccountCart();
    }

/* 
Kontrollerar om ett specifikt index i cookievariabeln existerar, 
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten har en kaka som anger ett sessions-id för ett tidigare inloggat konto sparad.
I sådana fall kontrolleras detta sparade sessions-id om det matchar ett existerande sessions-id i databasen,
gör den det så hämtas information om kontot, sedan sätts webbsidan till utseendet för en inloggad användare.
Därefter kontrolleras även om ett produkt-id skickats med i en HTTP GET-förfrågan,
i sådana fall innebär det att användaren vill lägga till en produkt i kundvagnen för kontot.
Om kontrollen inte matchar något konto sätts webbsidan till utseendet för en utloggad användare, sessionsanvändare.
Därefter kontrolleras även om ett produkt-id skickats med i en HTTP GET-förfrågan,
i sådana fall innebär det att användaren vill lägga till en produkt i kundvagnen för sessionen.
*/ 
} elseif (isset($_COOKIE["account_session_id"])) {

    if (getAccountSessInfo()) {
        setLoggedInUserInfo();

        if (isset($_GET["productId"]) && $_SERVER["REQUEST_METHOD"] == "GET") {
            addItemToAccountCart();
        }
    } else {
        setLoggedOutState("Account session retrieval failed. Please sign in again!");

        if (isset($_GET["productId"]) && $_SERVER["REQUEST_METHOD"] == "GET") {
            addItemToSessionCart();
        }
    }
/* 
I alla andra fall sätts webbsidan till utseendet för en utloggad användare, sessionsanvändare.
Därefter kontrolleras även om ett produkt-id skickats med i en HTTP GET-förfrågan,
i sådana fall innebär det att användaren vill lägga till en produkt i kundvagnen för sessionen.
*/
} else {
    setLoggedOutState();

    if (isset($_GET["productId"]) && $_SERVER["REQUEST_METHOD"] == "GET") {
        addItemToSessionCart();
    }
}

getAllProducts(); // Hämtning av alla nätbutikens produkter i databasen.

echo($htmlPieces[2]); // Skriver ut sista delen av HTML-strängen som respons till klientens webbläsare.

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
Funktionen hämtar alla nätbutikens produkter från dess databas.
Hämtning sker med en SQL-fråga och resultatets alla rader loopas igenom för utskrift.
Varje produktrad använder en kopia av den del av HTML-strängen som innehåller produktsektionen.
I strängen ersätts markörerna med relevant information om produkten och sedan skrivs den delen av
strängen ut.
*/
function getAllProducts() {
    global $htmlPieces;

    $connection = connectToDatabase();

    $sql = "SELECT productId, name, description, price FROM Products";

    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $productHTML = $htmlPieces[1];

        $productHTML = str_replace("---product_id---", $row["productId"], $productHTML);
        $productHTML = str_replace("---product_name---", $row["name"], $productHTML);
        $productHTML = str_replace("---product_desc---", $row["description"], $productHTML);
        $productHTML = str_replace("---product_price---", $row["price"], $productHTML);

        echo($productHTML);
    }
    
    mysqli_free_result($result);
    mysqli_close($connection);
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

/* 
Funktionen skriver ut den information på webbsidan som är relevant för en inloggad användare.
Detta ger alltså utseendet för startsidan om man har loggat in.
Främst ges möjligheten för användaren att logga ut.
Detta görs genom att ersätta markörer i HTML-dokumentet och sedan skriva ut strängen,
i detta fall första delen av HTML-strängen.
*/
function setLoggedInUserInfo() {
    global $htmlPieces;

    $username = $_SESSION["current_user"]["username"];
    $email = $_SESSION["current_user"]["email"];

    $htmlPieces[0] = str_replace("---username---", ", $username!", $htmlPieces[0]);
    $htmlPieces[0] = str_replace("---email---", "$email", $htmlPieces[0]);
    $htmlPieces[0] = str_replace("---sign_in_out_page---", "logout.php", $htmlPieces[0]);
    $htmlPieces[0] = str_replace("---sign_in_out_status---", "Sign out", $htmlPieces[0]);

    echo($htmlPieces[0]);
}

/* 
Funktionen lägger till ett antal produkter till kundvagnen för det inloggade kontot.
För att utföra arbetet behöver funktionen användarnamn - hämtas från aktuell sessionsvariabel,
produkt-id - hämtas från det värde som skickats med i HTTP GET, samt
värdet för hur många av produkten som ska läggas till i kundvagnen - hämtas från det värde som skickats med i HTTP GET.
Först utför funktionen en hämtning från databasen för värde på antalet av denna produkt som redan ligger i kundvagnen.
Detta görs genom en SQL-fråga som söker efter den rad som har samma produkt-id och användarnamn.
När hämtningen gjorts kontrolleras om frågan mot databasen lyckades och om resultetet returnerade något eller ej.
Om en rad returnerades innebär det att produkten redan finns i användarens kundvagn, då måste nya antalet adderas till det gamla.
Det gamla och nya värdet adderas, sedan skickas en ny SQL-fråga som uppdaterar den rad och kolumn för antal-värdet i databasen.
Däremot om resultatet i första frågan inte returnerade något så innebär det att produkten inte finns i användarens kundvagn.
Då kan en direkt insättning ske i kundvagns-tabellen genom en ny SQL-fråga som sätter in användarnamn, produkt-id och antal.
I slutet funktionen kontrolleras att inga fel uppstått i förfrågningarna till databasen och i sådant fall sker en commit
som tillämpar ändringar. Om något fel trots allt uppstått sker en återställning till stadiet innan ändringarna på databasen,
och programmet avbryts med ett felmeddelande till klienten.
*/
function addItemToAccountCart() {
    $username = $_SESSION["current_user"]["username"];
    $productId = $_GET["productId"];
    $quantity = $_GET["quantity"];

    $querySuccess = true;

    $connection = connectToDatabase();

    // Sätts för att frågor mot databasen inte ska exekveras direkt eftersom här utförs en transaktion med eventuellt flera frågor.
    mysqli_autocommit($connection, false);

    mysqli_begin_transaction($connection);

    $sql = "SELECT quantity FROM CartItems WHERE username='$username' AND productId=$productId";

    $result = mysqli_query($connection, $sql);

    if ($result) {
        $obj = mysqli_fetch_object($result);
    
        mysqli_free_result($result);
    
        if ($obj) {
            $newQuantity = $quantity + $obj -> quantity;
            $sql = "UPDATE CartItems SET quantity=$newQuantity WHERE username='$username' AND productId=$productId";

            if (!mysqli_query($connection, $sql)) {
                $querySuccess = false;
            }
        } else {
            $sql = "INSERT INTO CartItems (username, productId, quantity) VALUES ('$username', $productId, $quantity)";

            if (!mysqli_query($connection, $sql)) {
                $querySuccess = false;
            }
        }
    } else {
        $querySuccess = false;
    }

    if (!$querySuccess || !mysqli_commit($connection)) {
        mysqli_rollback($connection);
        mysqli_close($connection);
        die("Retrieval and/or updating of shopping cart failed: " . mysqli_error($connection));
    }

    mysqli_close($connection);
}

/* 
Funktionen skriver ut den information på webbsidan som är relevant för en utloggad användare.
Detta ger alltså utseendet för startsidan om man inte har loggat in.
Främst ges möjligheten för användaren att logga in.
Detta görs genom att ersätta markörer i HTML-dokumentet och sedan skriva ut strängen,
i detta fall första delen av HTML-strängen.
*/
function setLoggedOutState($status = "Not signed in") {
    global $htmlPieces;

    $htmlPieces[0] = str_replace("---username---", "", $htmlPieces[0]);
    $htmlPieces[0] = str_replace("---email---", $status, $htmlPieces[0]);
    $htmlPieces[0] = str_replace("---sign_in_out_page---", "login.php", $htmlPieces[0]);
    $htmlPieces[0] = str_replace("---sign_in_out_status---", "Sign in", $htmlPieces[0]);

    echo($htmlPieces[0]);
}

/* 
Funktionen lägger till ett antal produkter till kundvagnen för en sessionsanvändare (utloggad).
För att utföra arbetet behöver funktionen produkt-id - hämtas från det värde som skickats med i HTTP GET.
En kontroll utförs om det specifika indexet i aktuella sessionsvariabeln existerar, 
är deklarerad och innehåller ett värde som inte är NULL.
Det som kontrolleras är alltså om en kundvagnssession för aktuellt produkt-id redan finns i sessionen,
i sådana fall adderas antalet i kundvagnen med det värde som skickats med i HTTP GET.
I annat fall så skapas ett nytt index i sessionen med aktuellt produkt-id och medskickade antal-värdet.
*/
function addItemToSessionCart() {
    $productId = $_GET["productId"];

    if (isset($_SESSION["cart"][$productId])) {
        $_SESSION["cart"][$productId] += $_GET["quantity"];
    } else {
        $_SESSION["cart"][$productId] = $_GET["quantity"];
    }
}

?>